<?php
/**
 * 插件激活器类
 *
 * @package Article_Management_V2
 * @subpackage Includes
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 插件激活器类
 */
class AMS_V2_Activator {

    /**
     * 激活插件
     */
    public static function activate() {
        // 检查WordPress版本
        self::check_wordpress_version();
        
        // 检查PHP版本
        self::check_php_version();
        
        // 检查必需的扩展
        self::check_required_extensions();
        
        // 创建数据库表
        self::create_database_tables();
        
        // 设置默认选项
        self::set_default_options();
        
        // 创建默认管理员用户
        self::create_default_admin();
        
        // 设置定时任务
        self::schedule_cron_jobs();
        
        // 刷新重写规则
        flush_rewrite_rules();
        
        // 记录激活日志
        self::log_activation();
    }

    /**
     * 检查WordPress版本
     */
    private static function check_wordpress_version() {
        global $wp_version;
        
        $required_version = '5.0';
        
        if (version_compare($wp_version, $required_version, '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                sprintf(
                    __('Article Management V2 requires WordPress %s or higher. You are running version %s.', 'article-management-v2'),
                    $required_version,
                    $wp_version
                ),
                __('Plugin Activation Error', 'article-management-v2'),
                array('back_link' => true)
            );
        }
    }

    /**
     * 检查PHP版本
     */
    private static function check_php_version() {
        $required_version = '7.4';
        
        if (version_compare(PHP_VERSION, $required_version, '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                sprintf(
                    __('Article Management V2 requires PHP %s or higher. You are running version %s.', 'article-management-v2'),
                    $required_version,
                    PHP_VERSION
                ),
                __('Plugin Activation Error', 'article-management-v2'),
                array('back_link' => true)
            );
        }
    }

    /**
     * 检查必需的扩展
     */
    private static function check_required_extensions() {
        $required_extensions = array(
            'curl' => 'cURL',
            'json' => 'JSON',
            'openssl' => 'OpenSSL',
            'mbstring' => 'Multibyte String'
        );

        $missing_extensions = array();

        foreach ($required_extensions as $extension => $name) {
            if (!extension_loaded($extension)) {
                $missing_extensions[] = $name;
            }
        }

        if (!empty($missing_extensions)) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                sprintf(
                    __('Article Management V2 requires the following PHP extensions: %s', 'article-management-v2'),
                    implode(', ', $missing_extensions)
                ),
                __('Plugin Activation Error', 'article-management-v2'),
                array('back_link' => true)
            );
        }
    }

    /**
     * 创建数据库表
     */
    private static function create_database_tables() {
        require_once AMS_V2_PLUGIN_DIR . 'includes/class-database-manager.php';
        
        $database_manager = new AMS_V2_Database_Manager();
        $database_manager->create_tables();
    }

    /**
     * 设置默认选项
     */
    private static function set_default_options() {
        $default_options = array(
            'ams_v2_version' => AMS_V2_VERSION,
            'ams_v2_db_version' => '1.0.0',
            'ams_v2_moderation_level' => 'default',
            'ams_v2_cleanup_threshold_days' => 30,
            'ams_v2_auto_cleanup_enabled' => true,
            'ams_v2_log_retention_days' => 90,
            'ams_v2_max_upload_size' => 10485760, // 10MB
            'ams_v2_allowed_file_types' => array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'),
            'ams_v2_email_notifications' => true,
            'ams_v2_admin_email' => get_option('admin_email'),
            'ams_v2_timezone' => get_option('timezone_string', 'UTC'),
            'ams_v2_date_format' => get_option('date_format'),
            'ams_v2_time_format' => get_option('time_format'),
            'ams_v2_items_per_page' => 20,
            'ams_v2_enable_statistics' => true,
            'ams_v2_enable_audit_log' => true
        );

        foreach ($default_options as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }
    }

    /**
     * 创建默认管理员用户
     */
    private static function create_default_admin() {
        global $wpdb;
        
        $database_manager = new AMS_V2_Database_Manager();
        $users_table = $database_manager->get_table_name('users');
        $roles_table = $database_manager->get_table_name('roles');
        $user_roles_table = $database_manager->get_table_name('user_roles');
        
        // 检查是否已存在管理员用户
        $existing_admin = $wpdb->get_var(
            "SELECT u.id FROM $users_table u 
             INNER JOIN $user_roles_table ur ON u.id = ur.user_id 
             INNER JOIN $roles_table r ON ur.role_id = r.id 
             WHERE r.name = 'administrator' 
             LIMIT 1"
        );

        if (!$existing_admin) {
            // 获取当前WordPress管理员信息
            $wp_admin = get_users(array(
                'role' => 'administrator',
                'number' => 1
            ));

            if (!empty($wp_admin)) {
                $wp_admin = $wp_admin[0];
                
                // 创建插件管理员用户
                $user_data = array(
                    'username' => $wp_admin->user_login,
                    'email' => $wp_admin->user_email,
                    'password_hash' => wp_hash_password('admin123'), // 默认密码
                    'display_name' => $wp_admin->display_name ?: $wp_admin->user_login,
                    'is_active' => 1
                );

                $wpdb->insert($users_table, $user_data);
                $user_id = $wpdb->insert_id;

                // 获取管理员角色ID
                $admin_role_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM $roles_table WHERE name = %s",
                        'administrator'
                    )
                );

                // 分配管理员角色
                if ($admin_role_id && $user_id) {
                    $wpdb->insert($user_roles_table, array(
                        'user_id' => $user_id,
                        'role_id' => $admin_role_id
                    ));
                }
            }
        }
    }

    /**
     * 设置定时任务
     */
    private static function schedule_cron_jobs() {
        // 自动清理任务 - 每天凌晨2点执行
        if (!wp_next_scheduled('ams_v2_auto_cleanup')) {
            wp_schedule_event(
                strtotime('tomorrow 2:00 AM'),
                'daily',
                'ams_v2_auto_cleanup'
            );
        }

        // 日志清理任务 - 每周执行
        if (!wp_next_scheduled('ams_v2_log_cleanup')) {
            wp_schedule_event(
                strtotime('next Sunday 3:00 AM'),
                'weekly',
                'ams_v2_log_cleanup'
            );
        }

        // 统计数据更新任务 - 每小时执行
        if (!wp_next_scheduled('ams_v2_update_statistics')) {
            wp_schedule_event(
                time(),
                'hourly',
                'ams_v2_update_statistics'
            );
        }

        // API健康检查任务 - 每6小时执行
        if (!wp_next_scheduled('ams_v2_api_health_check')) {
            wp_schedule_event(
                time(),
                'ams_v2_6hours',
                'ams_v2_api_health_check'
            );
        }
    }

    /**
     * 记录激活日志
     */
    private static function log_activation() {
        $log_message = sprintf(
            'Article Management V2 plugin activated. Version: %s, WordPress: %s, PHP: %s',
            AMS_V2_VERSION,
            get_bloginfo('version'),
            PHP_VERSION
        );

        error_log($log_message);

        // 如果可能，使用插件日志系统
        if (class_exists('AMS_V2_Logger')) {
            $logger = new AMS_V2_Logger();
            $logger->info('Plugin activated successfully', array(
                'version' => AMS_V2_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'activation_time' => current_time('mysql')
            ));
        }
    }

    /**
     * 添加自定义定时任务间隔
     */
    public static function add_cron_intervals($schedules) {
        $schedules['ams_v2_6hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('Every 6 Hours', 'article-management-v2')
        );

        return $schedules;
    }
}

// 添加自定义定时任务间隔
add_filter('cron_schedules', array('AMS_V2_Activator', 'add_cron_intervals'));