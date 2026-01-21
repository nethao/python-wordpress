<?php
/**
 * 内容审核器实现类
 *
 * @package Article_Management_V2
 * @subpackage Managers
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 内容审核器实现类
 */
class AMS_V2_Content_Moderator implements AMS_V2_Content_Moderator_Interface {

    /**
     * 敏感词管理器
     *
     * @var AMS_V2_Sensitive_Word_Manager
     */
    private $word_manager;

    /**
     * 配置管理器
     *
     * @var AMS_V2_Moderation_Config_Manager
     */
    private $config_manager;

    /**
     * 审计日志记录器
     *
     * @var AMS_V2_Logger
     */
    private $logger;

    /**
     * WordPress数据库对象
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * 审核日志表名
     *
     * @var string
     */
    private $moderation_logs_table;

    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->word_manager = new AMS_V2_Sensitive_Word_Manager();
        $this->config_manager = new AMS_V2_Moderation_Config_Manager();
        $this->logger = new AMS_V2_Logger();
        $this->moderation_logs_table = $wpdb->prefix . 'ams_v2_moderation_logs';
        
        // 创建审核日志表
        $this->create_moderation_logs_table();
    }

    /**
     * 审核文本内容
     *
     * @param string $text 待审核文本
     * @param string $moderation_level 审核等级
     * @return array 审核结果
     */
    public function moderate_text($text, $moderation_level = null) {
        if ($moderation_level === null) {
            $moderation_level = $this->get_current_moderation_level();
        }

        $config = $this->get_moderation_config();
        $strategy = AMS_V2_Moderation_Level::get_level_strategy($moderation_level);

        // 检查用户是否有绕过权限
        if ($this->user_can_bypass()) {
            return array(
                'approved' => true,
                'modified_text' => $text,
                'found_words' => array(),
                'action_taken' => 'bypassed',
                'message' => '用户具有审核绕过权限'
            );
        }

        // 检查白名单
        if ($this->is_whitelisted_content($text)) {
            return array(
                'approved' => true,
                'modified_text' => $text,
                'found_words' => array(),
                'action_taken' => 'whitelisted',
                'message' => '内容在白名单中'
            );
        }

        // 检查黑名单
        if ($this->is_blacklisted_content($text)) {
            return array(
                'approved' => false,
                'modified_text' => $text,
                'found_words' => array(),
                'action_taken' => 'blacklisted',
                'message' => '内容在黑名单中'
            );
        }

        // 检查敏感词
        $check_types = array();
        if ($strategy['block_high_risk'] || $strategy['replace_high_risk']) {
            $check_types[] = AMS_V2_Word_Type::HIGH_RISK;
        }
        if ($strategy['block_normal'] || $strategy['replace_normal']) {
            $check_types[] = AMS_V2_Word_Type::NORMAL;
        }

        $sensitive_check = $this->word_manager->check_text($text, $check_types);
        
        if (!$sensitive_check['has_sensitive_words']) {
            return array(
                'approved' => true,
                'modified_text' => $text,
                'found_words' => array(),
                'action_taken' => 'approved',
                'message' => '内容审核通过'
            );
        }

        // 处理发现的敏感词
        $modified_text = $text;
        $blocked_words = array();
        $replaced_words = array();

        foreach ($sensitive_check['found_words'] as $found_word) {
            $word_type = $found_word['type'];
            
            if ($word_type === AMS_V2_Word_Type::HIGH_RISK) {
                if ($strategy['block_high_risk']) {
                    $blocked_words[] = $found_word;
                } elseif ($strategy['replace_high_risk']) {
                    $modified_text = str_replace($found_word['word'], $found_word['replacement'], $modified_text);
                    $replaced_words[] = $found_word;
                }
            } elseif ($word_type === AMS_V2_Word_Type::NORMAL) {
                if ($strategy['block_normal']) {
                    $blocked_words[] = $found_word;
                } elseif ($strategy['replace_normal']) {
                    $modified_text = str_replace($found_word['word'], $found_word['replacement'], $modified_text);
                    $replaced_words[] = $found_word;
                }
            }
        }

        // 根据策略决定是否允许提交
        $approved = empty($blocked_words) && $strategy['allow_submission'];
        
        $action_taken = '';
        $message = '';
        
        if (!empty($blocked_words)) {
            $action_taken = 'blocked';
            $message = sprintf('内容包含 %d 个被拦截的敏感词', count($blocked_words));
        } elseif (!empty($replaced_words)) {
            $action_taken = 'replaced';
            $message = sprintf('内容中 %d 个敏感词已被替换', count($replaced_words));
        }

        $result = array(
            'approved' => $approved,
            'modified_text' => $modified_text,
            'found_words' => $sensitive_check['found_words'],
            'blocked_words' => $blocked_words,
            'replaced_words' => $replaced_words,
            'action_taken' => $action_taken,
            'message' => $message,
            'moderation_level' => $moderation_level
        );

        // 记录审核日志
        if ($config['log_all_actions']) {
            $this->log_moderation_action(array(
                'content_type' => 'text',
                'content_length' => mb_strlen($text),
                'moderation_level' => $moderation_level,
                'result' => $result,
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip()
            ));
        }

        return $result;
    }

    /**
     * 审核文章内容
     *
     * @param array $article_data 文章数据
     * @param string $moderation_level 审核等级
     * @return array 审核结果
     */
    public function moderate_article($article_data, $moderation_level = null) {
        $title = isset($article_data['title']) ? $article_data['title'] : '';
        $content = isset($article_data['content']) ? $article_data['content'] : '';
        $excerpt = isset($article_data['excerpt']) ? $article_data['excerpt'] : '';

        // 合并所有文本内容进行审核
        $combined_text = $title . ' ' . $content . ' ' . $excerpt;
        
        $moderation_result = $this->moderate_text($combined_text, $moderation_level);
        
        // 如果需要替换，分别处理各个字段
        if ($moderation_result['action_taken'] === 'replaced') {
            $title_result = $this->moderate_text($title, $moderation_level);
            $content_result = $this->moderate_text($content, $moderation_level);
            $excerpt_result = $this->moderate_text($excerpt, $moderation_level);
            
            $moderation_result['modified_article'] = array(
                'title' => $title_result['modified_text'],
                'content' => $content_result['modified_text'],
                'excerpt' => $excerpt_result['modified_text']
            );
        } else {
            $moderation_result['modified_article'] = $article_data;
        }

        // 记录文章审核日志
        $config = $this->get_moderation_config();
        if ($config['log_all_actions']) {
            $this->log_moderation_action(array(
                'content_type' => 'article',
                'article_id' => isset($article_data['id']) ? $article_data['id'] : null,
                'content_length' => mb_strlen($combined_text),
                'moderation_level' => $moderation_level ?: $this->get_current_moderation_level(),
                'result' => $moderation_result,
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip()
            ));
        }

        return $moderation_result;
    }

    /**
     * 获取当前审核等级
     *
     * @return string
     */
    public function get_current_moderation_level() {
        return $this->config_manager->get_current_level();
    }

    /**
     * 设置审核等级
     *
     * @param string $level 审核等级
     * @return bool|WP_Error
     */
    public function set_moderation_level($level) {
        return $this->config_manager->set_level($level);
    }

    /**
     * 获取审核配置
     *
     * @return array
     */
    public function get_moderation_config() {
        return $this->config_manager->get_config();
    }

    /**
     * 更新审核配置
     *
     * @param array $config 配置数据
     * @return bool|WP_Error
     */
    public function update_moderation_config($config) {
        return $this->config_manager->update_config($config);
    }

    /**
     * 重新加载审核配置（热更新）
     *
     * @return bool
     */
    public function reload_config() {
        return $this->config_manager->reload_config();
    }
    /**
     * 获取审核统计信息
     *
     * @param array $filters 过滤条件
     * @return array
     */
    public function get_moderation_statistics($filters = array()) {
        $defaults = array(
            'start_date' => date('Y-m-d', strtotime('-30 days')),
            'end_date' => date('Y-m-d'),
            'content_type' => '',
            'moderation_level' => '',
            'action_taken' => ''
        );

        $filters = wp_parse_args($filters, $defaults);

        $where_conditions = array();
        $params = array();

        // 日期范围
        $where_conditions[] = "DATE(created_at) BETWEEN %s AND %s";
        $params[] = $filters['start_date'];
        $params[] = $filters['end_date'];

        // 内容类型
        if (!empty($filters['content_type'])) {
            $where_conditions[] = "content_type = %s";
            $params[] = $filters['content_type'];
        }

        // 审核等级
        if (!empty($filters['moderation_level'])) {
            $where_conditions[] = "moderation_level = %s";
            $params[] = $filters['moderation_level'];
        }

        // 处理动作
        if (!empty($filters['action_taken'])) {
            $where_conditions[] = "action_taken = %s";
            $params[] = $filters['action_taken'];
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

        // 总体统计
        $total_sql = "SELECT COUNT(*) FROM {$this->moderation_logs_table} {$where_clause}";
        $total = $this->wpdb->get_var($this->wpdb->prepare($total_sql, $params));

        // 按动作统计
        $action_sql = "SELECT action_taken, COUNT(*) as count FROM {$this->moderation_logs_table} {$where_clause} GROUP BY action_taken";
        $action_stats = $this->wpdb->get_results($this->wpdb->prepare($action_sql, $params), ARRAY_A);

        // 按审核等级统计
        $level_sql = "SELECT moderation_level, COUNT(*) as count FROM {$this->moderation_logs_table} {$where_clause} GROUP BY moderation_level";
        $level_stats = $this->wpdb->get_results($this->wpdb->prepare($level_sql, $params), ARRAY_A);

        // 按日期统计
        $daily_sql = "SELECT DATE(created_at) as date, COUNT(*) as count FROM {$this->moderation_logs_table} {$where_clause} GROUP BY DATE(created_at) ORDER BY date";
        $daily_stats = $this->wpdb->get_results($this->wpdb->prepare($daily_sql, $params), ARRAY_A);

        return array(
            'total' => (int) $total,
            'by_action' => $action_stats ?: array(),
            'by_level' => $level_stats ?: array(),
            'by_date' => $daily_stats ?: array(),
            'filters' => $filters
        );
    }

    /**
     * 记录审核日志
     *
     * @param array $log_data 日志数据
     * @return bool
     */
    public function log_moderation_action($log_data) {
        $defaults = array(
            'content_type' => 'text',
            'article_id' => null,
            'content_length' => 0,
            'moderation_level' => $this->get_current_moderation_level(),
            'action_taken' => 'unknown',
            'found_words_count' => 0,
            'blocked_words_count' => 0,
            'replaced_words_count' => 0,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'result_data' => null
        );

        $log_data = wp_parse_args($log_data, $defaults);

        // 处理结果数据
        if (isset($log_data['result']) && is_array($log_data['result'])) {
            $result = $log_data['result'];
            $log_data['action_taken'] = $result['action_taken'] ?? 'unknown';
            $log_data['found_words_count'] = count($result['found_words'] ?? array());
            $log_data['blocked_words_count'] = count($result['blocked_words'] ?? array());
            $log_data['replaced_words_count'] = count($result['replaced_words'] ?? array());
            $log_data['result_data'] = json_encode($result, JSON_UNESCAPED_UNICODE);
            unset($log_data['result']);
        }

        $insert_result = $this->wpdb->insert(
            $this->moderation_logs_table,
            array(
                'content_type' => $log_data['content_type'],
                'article_id' => $log_data['article_id'],
                'content_length' => $log_data['content_length'],
                'moderation_level' => $log_data['moderation_level'],
                'action_taken' => $log_data['action_taken'],
                'found_words_count' => $log_data['found_words_count'],
                'blocked_words_count' => $log_data['blocked_words_count'],
                'replaced_words_count' => $log_data['replaced_words_count'],
                'user_id' => $log_data['user_id'],
                'ip_address' => $log_data['ip_address'],
                'user_agent' => $log_data['user_agent'],
                'result_data' => $log_data['result_data'],
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );

        return $insert_result !== false;
    }

    /**
     * 获取审核日志
     *
     * @param array $filters 过滤条件
     * @return array
     */
    public function get_moderation_logs($filters = array()) {
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'start_date' => '',
            'end_date' => '',
            'content_type' => '',
            'action_taken' => '',
            'user_id' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $filters = wp_parse_args($filters, $defaults);

        $where_conditions = array();
        $params = array();

        // 日期范围
        if (!empty($filters['start_date'])) {
            $where_conditions[] = "DATE(created_at) >= %s";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $where_conditions[] = "DATE(created_at) <= %s";
            $params[] = $filters['end_date'];
        }

        // 内容类型
        if (!empty($filters['content_type'])) {
            $where_conditions[] = "content_type = %s";
            $params[] = $filters['content_type'];
        }

        // 处理动作
        if (!empty($filters['action_taken'])) {
            $where_conditions[] = "action_taken = %s";
            $params[] = $filters['action_taken'];
        }

        // 用户ID
        if (!empty($filters['user_id'])) {
            $where_conditions[] = "user_id = %d";
            $params[] = $filters['user_id'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $order_clause = sprintf(
            'ORDER BY %s %s',
            sanitize_sql_orderby($filters['orderby']),
            $filters['order'] === 'ASC' ? 'ASC' : 'DESC'
        );

        $limit_clause = sprintf('LIMIT %d OFFSET %d', $filters['limit'], $filters['offset']);

        $sql = "SELECT * FROM {$this->moderation_logs_table} {$where_clause} {$order_clause} {$limit_clause}";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        $results = $this->wpdb->get_results($sql, ARRAY_A);

        // 获取总数
        $count_sql = "SELECT COUNT(*) FROM {$this->moderation_logs_table} {$where_clause}";
        if (!empty($params)) {
            $count_sql = $this->wpdb->prepare($count_sql, $params);
        }
        $total = $this->wpdb->get_var($count_sql);

        return array(
            'logs' => $results ?: array(),
            'total' => (int) $total,
            'pages' => ceil($total / $filters['limit'])
        );
    }

    /**
     * 预览审核结果
     *
     * @param string $text 待审核文本
     * @param string $moderation_level 审核等级
     * @return array 预览结果
     */
    public function preview_moderation($text, $moderation_level = null) {
        // 预览不记录日志
        $original_config = $this->get_moderation_config();
        $temp_config = $original_config;
        $temp_config['log_all_actions'] = false;
        
        // 临时更新配置
        $this->config_manager->update_config($temp_config);
        
        // 执行审核
        $result = $this->moderate_text($text, $moderation_level);
        
        // 恢复原配置
        $this->config_manager->update_config($original_config);
        
        $result['is_preview'] = true;
        return $result;
    }

    /**
     * 批量审核文本
     *
     * @param array $texts 文本数组
     * @param string $moderation_level 审核等级
     * @return array 批量审核结果
     */
    public function batch_moderate($texts, $moderation_level = null) {
        $results = array();
        $summary = array(
            'total' => count($texts),
            'approved' => 0,
            'blocked' => 0,
            'replaced' => 0,
            'errors' => 0
        );

        foreach ($texts as $index => $text) {
            try {
                $result = $this->moderate_text($text, $moderation_level);
                $results[$index] = $result;
                
                if ($result['approved']) {
                    if ($result['action_taken'] === 'replaced') {
                        $summary['replaced']++;
                    } else {
                        $summary['approved']++;
                    }
                } else {
                    $summary['blocked']++;
                }
            } catch (Exception $e) {
                $results[$index] = array(
                    'approved' => false,
                    'error' => $e->getMessage()
                );
                $summary['errors']++;
            }
        }

        return array(
            'results' => $results,
            'summary' => $summary
        );
    }

    /**
     * 获取审核规则
     *
     * @param string $moderation_level 审核等级
     * @return array 审核规则
     */
    public function get_moderation_rules($moderation_level = null) {
        if ($moderation_level === null) {
            $moderation_level = $this->get_current_moderation_level();
        }

        $strategy = AMS_V2_Moderation_Level::get_level_strategy($moderation_level);
        $config = $this->get_moderation_config();

        return array(
            'level' => $moderation_level,
            'level_name' => AMS_V2_Moderation_Level::get_level_name($moderation_level),
            'description' => AMS_V2_Moderation_Level::get_level_description($moderation_level),
            'strategy' => $strategy,
            'whitelist_words' => $config['whitelist_words'],
            'blacklist_words' => $config['blacklist_words'],
            'bypass_users' => $config['bypass_users'],
            'bypass_roles' => $config['bypass_roles'],
            'auto_moderate' => $config['auto_moderate'],
            'notify_on_block' => $config['notify_on_block']
        );
    }

    /**
     * 验证审核配置
     *
     * @param array $config 配置数据
     * @return array 验证结果
     */
    public function validate_config($config) {
        return $this->config_manager->validate_config($config);
    }

    /**
     * 检查用户是否可以绕过审核
     *
     * @param int $user_id 用户ID
     * @return bool
     */
    private function user_can_bypass($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $config = $this->get_moderation_config();

        // 检查用户ID绕过列表
        if (in_array($user_id, $config['bypass_users'])) {
            return true;
        }

        // 检查用户角色绕过列表
        $user = get_user_by('id', $user_id);
        if ($user && !empty($config['bypass_roles'])) {
            foreach ($user->roles as $role) {
                if (in_array($role, $config['bypass_roles'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 检查内容是否在白名单中
     *
     * @param string $text 文本内容
     * @return bool
     */
    private function is_whitelisted_content($text) {
        $config = $this->get_moderation_config();
        
        if (empty($config['whitelist_words'])) {
            return false;
        }

        foreach ($config['whitelist_words'] as $word) {
            if (mb_strpos($text, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查内容是否在黑名单中
     *
     * @param string $text 文本内容
     * @return bool
     */
    private function is_blacklisted_content($text) {
        $config = $this->get_moderation_config();
        
        if (empty($config['blacklist_words'])) {
            return false;
        }

        foreach ($config['blacklist_words'] as $word) {
            if (mb_strpos($text, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取客户端IP地址
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * 创建审核日志表
     */
    private function create_moderation_logs_table() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->moderation_logs_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            content_type varchar(20) NOT NULL DEFAULT 'text',
            article_id bigint(20) unsigned NULL,
            content_length int(11) NOT NULL DEFAULT 0,
            moderation_level varchar(20) NOT NULL,
            action_taken varchar(20) NOT NULL,
            found_words_count int(11) NOT NULL DEFAULT 0,
            blocked_words_count int(11) NOT NULL DEFAULT 0,
            replaced_words_count int(11) NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NULL,
            ip_address varchar(45),
            user_agent text,
            result_data longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY content_type (content_type),
            KEY article_id (article_id),
            KEY moderation_level (moderation_level),
            KEY action_taken (action_taken),
            KEY user_id (user_id),
            KEY created_at (created_at),
            KEY ip_address (ip_address)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}