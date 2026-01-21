<?php
/**
 * 敏感词管理器实现类
 *
 * @package Article_Management_V2
 * @subpackage Managers
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 敏感词管理器实现类
 */
class AMS_V2_Sensitive_Word_Manager implements AMS_V2_Sensitive_Word_Manager_Interface {

    /**
     * WordPress数据库对象
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * 敏感词表名
     *
     * @var string
     */
    private $table_name;

    /**
     * 审计日志记录器
     *
     * @var AMS_V2_Logger
     */
    private $logger;

    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'ams_v2_sensitive_words';
        $this->logger = new AMS_V2_Logger();
    }

    /**
     * 创建敏感词
     *
     * @param array $data 敏感词数据
     * @return AMS_V2_Sensitive_Word|WP_Error
     */
    public function create_word($data) {
        $word = new AMS_V2_Sensitive_Word($data);
        
        // 验证数据
        $errors = $word->validate();
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }

        // 检查是否已存在
        $existing = $this->get_word_by_content($word->word, $word->type);
        if ($existing) {
            return new WP_Error('word_exists', '该敏感词已存在');
        }

        // 插入数据库
        $result = $this->wpdb->insert(
            $this->table_name,
            array(
                'word' => $word->word,
                'type' => $word->type,
                'replacement' => $word->replacement ?: AMS_V2_Word_Type::get_default_replacement($word->type),
                'is_builtin' => $word->is_builtin ? 1 : 0,
                'is_active' => $word->is_active ? 1 : 0,
                'created_by' => $word->created_by
            ),
            array('%s', '%s', '%s', '%d', '%d', '%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', '数据库操作失败: ' . $this->wpdb->last_error);
        }

        $word->id = $this->wpdb->insert_id;
        
        // 记录审计日志
        $this->logger->log_action(
            $word->created_by,
            'create_sensitive_word',
            'sensitive_word',
            $word->id,
            null,
            $word->to_array()
        );

        return $word;
    }

    /**
     * 更新敏感词
     *
     * @param int $id 敏感词ID
     * @param array $data 更新数据
     * @return AMS_V2_Sensitive_Word|WP_Error
     */
    public function update_word($id, $data) {
        $existing_word = $this->get_word_by_id($id);
        if (!$existing_word) {
            return new WP_Error('word_not_found', '敏感词不存在');
        }

        $old_data = $existing_word->to_array();
        
        // 更新数据
        $existing_word->fill($data);
        
        // 验证数据
        $errors = $existing_word->validate();
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }

        // 检查是否与其他词汇冲突
        $conflicting_word = $this->get_word_by_content($existing_word->word, $existing_word->type);
        if ($conflicting_word && $conflicting_word->id !== $id) {
            return new WP_Error('word_exists', '该敏感词已存在');
        }

        // 更新数据库
        $result = $this->wpdb->update(
            $this->table_name,
            array(
                'word' => $existing_word->word,
                'type' => $existing_word->type,
                'replacement' => $existing_word->replacement ?: AMS_V2_Word_Type::get_default_replacement($existing_word->type),
                'is_active' => $existing_word->is_active ? 1 : 0
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%d'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', '数据库操作失败: ' . $this->wpdb->last_error);
        }

        // 记录审计日志
        $this->logger->log_action(
            get_current_user_id(),
            'update_sensitive_word',
            'sensitive_word',
            $id,
            $old_data,
            $existing_word->to_array()
        );

        return $existing_word;
    }

    /**
     * 删除敏感词
     *
     * @param int $id 敏感词ID
     * @return bool|WP_Error
     */
    public function delete_word($id) {
        $word = $this->get_word_by_id($id);
        if (!$word) {
            return new WP_Error('word_not_found', '敏感词不存在');
        }

        // 检查是否为内置词汇
        if ($word->is_builtin) {
            return new WP_Error('cannot_delete_builtin', '不能删除内置敏感词');
        }

        $result = $this->wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', '数据库操作失败: ' . $this->wpdb->last_error);
        }

        // 记录审计日志
        $this->logger->log_action(
            get_current_user_id(),
            'delete_sensitive_word',
            'sensitive_word',
            $id,
            $word->to_array(),
            null
        );

        return true;
    }

    /**
     * 根据ID获取敏感词
     *
     * @param int $id 敏感词ID
     * @return AMS_V2_Sensitive_Word|null
     */
    public function get_word_by_id($id) {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return $result ? new AMS_V2_Sensitive_Word($result) : null;
    }

    /**
     * 根据词汇内容获取敏感词
     *
     * @param string $word 词汇内容
     * @param string $type 词汇类型（可选）
     * @return AMS_V2_Sensitive_Word|null
     */
    public function get_word_by_content($word, $type = null) {
        $sql = "SELECT * FROM {$this->table_name} WHERE word = %s";
        $params = array($word);

        if ($type) {
            $sql .= " AND type = %s";
            $params[] = $type;
        }

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );

        return $result ? new AMS_V2_Sensitive_Word($result) : null;
    }

    /**
     * 获取敏感词列表
     *
     * @param array $args 查询参数
     * @return array
     */
    public function get_words($args = array()) {
        $defaults = array(
            'type' => '',
            'is_active' => null,
            'is_builtin' => null,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $where_conditions = array();
        $params = array();

        if (!empty($args['type'])) {
            $where_conditions[] = "type = %s";
            $params[] = $args['type'];
        }

        if ($args['is_active'] !== null) {
            $where_conditions[] = "is_active = %d";
            $params[] = $args['is_active'] ? 1 : 0;
        }

        if ($args['is_builtin'] !== null) {
            $where_conditions[] = "is_builtin = %d";
            $params[] = $args['is_builtin'] ? 1 : 0;
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $order_clause = sprintf(
            'ORDER BY %s %s',
            sanitize_sql_orderby($args['orderby']),
            $args['order'] === 'ASC' ? 'ASC' : 'DESC'
        );

        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);

        $sql = "SELECT * FROM {$this->table_name} {$where_clause} {$order_clause} {$limit_clause}";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        $words = array();
        foreach ($results as $result) {
            $words[] = new AMS_V2_Sensitive_Word($result);
        }

        // 获取总数
        $count_sql = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        if (!empty($params)) {
            $count_sql = $this->wpdb->prepare($count_sql, $params);
        }
        $total = $this->wpdb->get_var($count_sql);

        return array(
            'words' => $words,
            'total' => (int) $total,
            'pages' => ceil($total / $args['limit'])
        );
    }
    /**
     * 搜索敏感词
     *
     * @param string $keyword 搜索关键词
     * @param array $args 查询参数
     * @return array
     */
    public function search_words($keyword, $args = array()) {
        $defaults = array(
            'type' => '',
            'is_active' => null,
            'is_builtin' => null,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $where_conditions = array();
        $params = array();

        // 添加关键词搜索条件
        if (!empty($keyword)) {
            $where_conditions[] = "word LIKE %s";
            $params[] = '%' . $this->wpdb->esc_like($keyword) . '%';
        }

        if (!empty($args['type'])) {
            $where_conditions[] = "type = %s";
            $params[] = $args['type'];
        }

        if ($args['is_active'] !== null) {
            $where_conditions[] = "is_active = %d";
            $params[] = $args['is_active'] ? 1 : 0;
        }

        if ($args['is_builtin'] !== null) {
            $where_conditions[] = "is_builtin = %d";
            $params[] = $args['is_builtin'] ? 1 : 0;
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $order_clause = sprintf(
            'ORDER BY %s %s',
            sanitize_sql_orderby($args['orderby']),
            $args['order'] === 'ASC' ? 'ASC' : 'DESC'
        );

        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);

        $sql = "SELECT * FROM {$this->table_name} {$where_clause} {$order_clause} {$limit_clause}";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        $words = array();
        foreach ($results as $result) {
            $words[] = new AMS_V2_Sensitive_Word($result);
        }

        // 获取总数
        $count_sql = "SELECT COUNT(*) FROM {$this->table_name} {$where_clause}";
        if (!empty($params)) {
            $count_sql = $this->wpdb->prepare($count_sql, $params);
        }
        $total = $this->wpdb->get_var($count_sql);

        return array(
            'words' => $words,
            'total' => (int) $total,
            'pages' => ceil($total / $args['limit'])
        );
    }

    /**
     * 批量导入敏感词
     *
     * @param array $words 敏感词数组
     * @param string $type 词汇类型
     * @param int $created_by 创建者ID
     * @return array 导入结果
     */
    public function import_words($words, $type, $created_by) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => array()
        );

        if (!AMS_V2_Word_Type::is_valid_type($type)) {
            $results['errors'][] = '无效的敏感词类型';
            return $results;
        }

        foreach ($words as $word_data) {
            $word_content = is_array($word_data) ? $word_data['word'] : $word_data;
            $replacement = is_array($word_data) ? ($word_data['replacement'] ?? '') : '';

            // 检查是否已存在
            if ($this->get_word_by_content($word_content, $type)) {
                $results['skipped']++;
                continue;
            }

            $word_result = $this->create_word(array(
                'word' => $word_content,
                'type' => $type,
                'replacement' => $replacement,
                'is_builtin' => false,
                'is_active' => true,
                'created_by' => $created_by
            ));

            if (is_wp_error($word_result)) {
                $results['failed']++;
                $results['errors'][] = sprintf('导入 "%s" 失败: %s', $word_content, $word_result->get_error_message());
            } else {
                $results['success']++;
            }
        }

        // 记录审计日志
        $this->logger->log_action(
            $created_by,
            'import_sensitive_words',
            'sensitive_word',
            null,
            null,
            $results
        );

        return $results;
    }

    /**
     * 批量删除敏感词
     *
     * @param array $ids 敏感词ID数组
     * @return array 删除结果
     */
    public function delete_words($ids) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array()
        );

        foreach ($ids as $id) {
            $delete_result = $this->delete_word($id);
            
            if (is_wp_error($delete_result)) {
                $results['failed']++;
                $results['errors'][] = sprintf('删除ID %d 失败: %s', $id, $delete_result->get_error_message());
            } else {
                $results['success']++;
            }
        }

        return $results;
    }

    /**
     * 激活/停用敏感词
     *
     * @param int $id 敏感词ID
     * @param bool $is_active 是否激活
     * @return bool|WP_Error
     */
    public function toggle_word_status($id, $is_active) {
        $word = $this->get_word_by_id($id);
        if (!$word) {
            return new WP_Error('word_not_found', '敏感词不存在');
        }

        $old_data = $word->to_array();
        
        $result = $this->wpdb->update(
            $this->table_name,
            array('is_active' => $is_active ? 1 : 0),
            array('id' => $id),
            array('%d'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', '数据库操作失败: ' . $this->wpdb->last_error);
        }

        $word->is_active = $is_active;

        // 记录审计日志
        $this->logger->log_action(
            get_current_user_id(),
            $is_active ? 'activate_sensitive_word' : 'deactivate_sensitive_word',
            'sensitive_word',
            $id,
            $old_data,
            $word->to_array()
        );

        return true;
    }

    /**
     * 获取敏感词统计信息
     *
     * @return array
     */
    public function get_word_statistics() {
        $stats = array();

        // 总数统计
        $stats['total'] = $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        
        // 按类型统计
        $type_stats = $this->wpdb->get_results(
            "SELECT type, COUNT(*) as count FROM {$this->table_name} GROUP BY type",
            ARRAY_A
        );
        
        $stats['by_type'] = array();
        foreach ($type_stats as $stat) {
            $stats['by_type'][$stat['type']] = (int) $stat['count'];
        }

        // 按状态统计
        $status_stats = $this->wpdb->get_results(
            "SELECT is_active, COUNT(*) as count FROM {$this->table_name} GROUP BY is_active",
            ARRAY_A
        );
        
        $stats['by_status'] = array();
        foreach ($status_stats as $stat) {
            $key = $stat['is_active'] ? 'active' : 'inactive';
            $stats['by_status'][$key] = (int) $stat['count'];
        }

        // 内置词汇统计
        $builtin_stats = $this->wpdb->get_results(
            "SELECT is_builtin, COUNT(*) as count FROM {$this->table_name} GROUP BY is_builtin",
            ARRAY_A
        );
        
        $stats['by_source'] = array();
        foreach ($builtin_stats as $stat) {
            $key = $stat['is_builtin'] ? 'builtin' : 'custom';
            $stats['by_source'][$key] = (int) $stat['count'];
        }

        return $stats;
    }

    /**
     * 检查文本中的敏感词
     *
     * @param string $text 待检查文本
     * @param array $types 检查的词汇类型
     * @return array 检查结果
     */
    public function check_text($text, $types = array()) {
        if (empty($types)) {
            $types = AMS_V2_Word_Type::get_all_types();
        }

        $found_words = array();
        
        // 获取激活的敏感词
        $words_data = $this->get_words(array(
            'is_active' => true,
            'limit' => 1000 // 获取足够多的词汇
        ));

        foreach ($words_data['words'] as $word) {
            if (!in_array($word->type, $types)) {
                continue;
            }

            // 检查文本中是否包含该敏感词
            if (mb_strpos($text, $word->word) !== false) {
                $found_words[] = array(
                    'word' => $word->word,
                    'type' => $word->type,
                    'replacement' => $word->get_default_replacement(),
                    'positions' => $this->find_word_positions($text, $word->word)
                );
            }
        }

        return array(
            'has_sensitive_words' => !empty($found_words),
            'found_words' => $found_words,
            'total_found' => count($found_words)
        );
    }

    /**
     * 替换文本中的敏感词
     *
     * @param string $text 待处理文本
     * @param array $types 处理的词汇类型
     * @return string 处理后的文本
     */
    public function replace_text($text, $types = array()) {
        if (empty($types)) {
            $types = AMS_V2_Word_Type::get_all_types();
        }

        // 获取激活的敏感词
        $words_data = $this->get_words(array(
            'is_active' => true,
            'limit' => 1000
        ));

        foreach ($words_data['words'] as $word) {
            if (!in_array($word->type, $types)) {
                continue;
            }

            $replacement = $word->get_default_replacement();
            $text = str_replace($word->word, $replacement, $text);
        }

        return $text;
    }

    /**
     * 初始化内置敏感词库
     *
     * @return bool
     */
    public function initialize_builtin_words() {
        // 高危敏感词
        $high_risk_words = array(
            '暴力', '恐怖', '极端', '仇恨', '歧视', '诈骗', '赌博', '毒品',
            '色情', '淫秽', '反动', '分裂', '邪教', '恐怖主义', '暴力革命',
            '推翻政府', '武装起义', '血腥', '杀戮', '屠杀', '自杀', '自残'
        );
        
        // 普通敏感词
        $normal_words = array(
            '垃圾', '废物', '愚蠢', '白痴', '傻瓜', '混蛋', '笨蛋', '蠢货',
            '死鬼', '该死', '滚蛋', '闭嘴', '放屁', '胡说', '鬼话', '扯淡'
        );

        $success_count = 0;

        // 插入高危敏感词
        foreach ($high_risk_words as $word) {
            if (!$this->get_word_by_content($word, AMS_V2_Word_Type::HIGH_RISK)) {
                $result = $this->create_word(array(
                    'word' => $word,
                    'type' => AMS_V2_Word_Type::HIGH_RISK,
                    'replacement' => '***',
                    'is_builtin' => true,
                    'is_active' => true,
                    'created_by' => 0
                ));
                
                if (!is_wp_error($result)) {
                    $success_count++;
                }
            }
        }

        // 插入普通敏感词
        foreach ($normal_words as $word) {
            if (!$this->get_word_by_content($word, AMS_V2_Word_Type::NORMAL)) {
                $result = $this->create_word(array(
                    'word' => $word,
                    'type' => AMS_V2_Word_Type::NORMAL,
                    'replacement' => '**',
                    'is_builtin' => true,
                    'is_active' => true,
                    'created_by' => 0
                ));
                
                if (!is_wp_error($result)) {
                    $success_count++;
                }
            }
        }

        return $success_count > 0;
    }

    /**
     * 重置敏感词库到默认状态
     *
     * @return bool
     */
    public function reset_to_default() {
        // 删除所有非内置敏感词
        $result = $this->wpdb->delete(
            $this->table_name,
            array('is_builtin' => 0),
            array('%d')
        );

        // 重新初始化内置敏感词
        $this->initialize_builtin_words();

        // 记录审计日志
        $this->logger->log_action(
            get_current_user_id(),
            'reset_sensitive_words',
            'sensitive_word',
            null,
            null,
            array('deleted_custom_words' => $result)
        );

        return true;
    }

    /**
     * 查找词汇在文本中的位置
     *
     * @param string $text 文本
     * @param string $word 词汇
     * @return array 位置数组
     */
    private function find_word_positions($text, $word) {
        $positions = array();
        $offset = 0;
        
        while (($pos = mb_strpos($text, $word, $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + mb_strlen($word);
        }
        
        return $positions;
    }
}