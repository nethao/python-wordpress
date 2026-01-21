<?php
/**
 * 审核配置管理器
 *
 * @package Article_Management_V2
 * @subpackage Managers
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 审核配置管理器类
 */
class AMS_V2_Moderation_Config_Manager {

    /**
     * 配置选项名称
     */
    const CONFIG_OPTION_NAME = 'ams_v2_moderation_config';

    /**
     * 配置缓存键
     */
    const CACHE_KEY = 'ams_v2_moderation_config_cache';

    /**
     * 缓存过期时间（秒）
     */
    const CACHE_EXPIRY = 3600; // 1小时

    /**
     * 默认配置
     *
     * @var array
     */
    private static $default_config = array(
        'current_level' => AMS_V2_Moderation_Level::DEFAULT_LEVEL,
        'auto_moderate' => true,
        'log_all_actions' => true,
        'notify_on_block' => true,
        'custom_rules' => array(),
        'whitelist_words' => array(),
        'blacklist_words' => array(),
        'bypass_users' => array(),
        'bypass_roles' => array(),
        'updated_at' => '',
        'updated_by' => 0
    );

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
        $this->logger = new AMS_V2_Logger();
    }

    /**
     * 获取审核配置
     *
     * @param bool $use_cache 是否使用缓存
     * @return array
     */
    public function get_config($use_cache = true) {
        if ($use_cache) {
            $cached_config = wp_cache_get(self::CACHE_KEY);
            if ($cached_config !== false) {
                return $cached_config;
            }
        }

        $config = get_option(self::CONFIG_OPTION_NAME, self::$default_config);
        
        // 确保配置完整性
        $config = wp_parse_args($config, self::$default_config);
        
        // 验证审核等级
        if (!AMS_V2_Moderation_Level::is_valid_level($config['current_level'])) {
            $config['current_level'] = AMS_V2_Moderation_Level::get_default_level();
        }

        // 缓存配置
        if ($use_cache) {
            wp_cache_set(self::CACHE_KEY, $config, '', self::CACHE_EXPIRY);
        }

        return $config;
    }

    /**
     * 更新审核配置
     *
     * @param array $new_config 新配置
     * @param int $user_id 更新用户ID
     * @return bool|WP_Error
     */
    public function update_config($new_config, $user_id = null) {
        $current_config = $this->get_config(false);
        
        // 验证配置
        $validation_result = $this->validate_config($new_config);
        if (!$validation_result['valid']) {
            return new WP_Error('invalid_config', implode(', ', $validation_result['errors']));
        }

        // 合并配置
        $updated_config = wp_parse_args($new_config, $current_config);
        $updated_config['updated_at'] = current_time('mysql');
        $updated_config['updated_by'] = $user_id ?: get_current_user_id();

        // 保存配置
        $result = update_option(self::CONFIG_OPTION_NAME, $updated_config);
        
        if ($result) {
            // 清除缓存
            wp_cache_delete(self::CACHE_KEY);
            
            // 记录审计日志
            $this->logger->log_action(
                $updated_config['updated_by'],
                'update_moderation_config',
                'moderation_config',
                null,
                $current_config,
                $updated_config
            );

            // 触发配置更新钩子
            do_action('ams_v2_moderation_config_updated', $updated_config, $current_config);
            
            return true;
        }

        return new WP_Error('update_failed', '配置更新失败');
    }

    /**
     * 获取当前审核等级
     *
     * @return string
     */
    public function get_current_level() {
        $config = $this->get_config();
        return $config['current_level'];
    }

    /**
     * 设置审核等级
     *
     * @param string $level 审核等级
     * @param int $user_id 用户ID
     * @return bool|WP_Error
     */
    public function set_level($level, $user_id = null) {
        if (!AMS_V2_Moderation_Level::is_valid_level($level)) {
            return new WP_Error('invalid_level', '无效的审核等级: ' . $level);
        }

        return $this->update_config(array('current_level' => $level), $user_id);
    }

    /**
     * 重新加载配置（热更新）
     *
     * @return bool
     */
    public function reload_config() {
        // 清除缓存
        wp_cache_delete(self::CACHE_KEY);
        
        // 重新加载配置
        $config = $this->get_config(false);
        
        // 触发重新加载钩子
        do_action('ams_v2_moderation_config_reloaded', $config);
        
        return true;
    }

    /**
     * 验证配置
     *
     * @param array $config 配置数据
     * @return array 验证结果
     */
    public function validate_config($config) {
        $errors = array();

        // 验证审核等级
        if (isset($config['current_level']) && !AMS_V2_Moderation_Level::is_valid_level($config['current_level'])) {
            $errors[] = '无效的审核等级';
        }

        // 验证布尔值字段
        $boolean_fields = array('auto_moderate', 'log_all_actions', 'notify_on_block');
        foreach ($boolean_fields as $field) {
            if (isset($config[$field]) && !is_bool($config[$field])) {
                $errors[] = sprintf('字段 %s 必须是布尔值', $field);
            }
        }

        // 验证数组字段
        $array_fields = array('custom_rules', 'whitelist_words', 'blacklist_words', 'bypass_users', 'bypass_roles');
        foreach ($array_fields as $field) {
            if (isset($config[$field]) && !is_array($config[$field])) {
                $errors[] = sprintf('字段 %s 必须是数组', $field);
            }
        }

        // 验证白名单和黑名单词汇
        if (isset($config['whitelist_words']) && is_array($config['whitelist_words'])) {
            foreach ($config['whitelist_words'] as $word) {
                if (!is_string($word) || empty(trim($word))) {
                    $errors[] = '白名单词汇必须是非空字符串';
                    break;
                }
            }
        }

        if (isset($config['blacklist_words']) && is_array($config['blacklist_words'])) {
            foreach ($config['blacklist_words'] as $word) {
                if (!is_string($word) || empty(trim($word))) {
                    $errors[] = '黑名单词汇必须是非空字符串';
                    break;
                }
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * 重置配置到默认值
     *
     * @param int $user_id 用户ID
     * @return bool|WP_Error
     */
    public function reset_to_default($user_id = null) {
        $default_config = self::$default_config;
        $default_config['updated_at'] = current_time('mysql');
        $default_config['updated_by'] = $user_id ?: get_current_user_id();

        $result = update_option(self::CONFIG_OPTION_NAME, $default_config);
        
        if ($result) {
            // 清除缓存
            wp_cache_delete(self::CACHE_KEY);
            
            // 记录审计日志
            $this->logger->log_action(
                $default_config['updated_by'],
                'reset_moderation_config',
                'moderation_config',
                null,
                $this->get_config(false),
                $default_config
            );

            return true;
        }

        return new WP_Error('reset_failed', '配置重置失败');
    }

    /**
     * 导出配置
     *
     * @return array
     */
    public function export_config() {
        $config = $this->get_config(false);
        
        // 移除敏感信息
        unset($config['updated_by']);
        
        return array(
            'config' => $config,
            'exported_at' => current_time('mysql'),
            'version' => '2.0'
        );
    }

    /**
     * 导入配置
     *
     * @param array $import_data 导入数据
     * @param int $user_id 用户ID
     * @return bool|WP_Error
     */
    public function import_config($import_data, $user_id = null) {
        if (!isset($import_data['config']) || !is_array($import_data['config'])) {
            return new WP_Error('invalid_import', '无效的导入数据格式');
        }

        $config = $import_data['config'];
        
        // 验证导入的配置
        $validation_result = $this->validate_config($config);
        if (!$validation_result['valid']) {
            return new WP_Error('invalid_import_config', '导入配置验证失败: ' . implode(', ', $validation_result['errors']));
        }

        return $this->update_config($config, $user_id);
    }

    /**
     * 获取配置历史记录
     *
     * @param int $limit 限制数量
     * @return array
     */
    public function get_config_history($limit = 10) {
        // 从审计日志中获取配置变更历史
        global $wpdb;
        
        $audit_table = $wpdb->prefix . 'ams_v2_audit_logs';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$audit_table} 
                 WHERE action IN ('update_moderation_config', 'reset_moderation_config') 
                 ORDER BY created_at DESC 
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return $results ?: array();
    }

    /**
     * 获取默认配置
     *
     * @return array
     */
    public static function get_default_config() {
        return self::$default_config;
    }
}