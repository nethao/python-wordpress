<?php
/**
 * 插件卸载器类
 *
 * @package Article_Management_V2
 * @subpackage Includes
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 确保是通过WordPress卸载钩子调用
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * 插件卸载器类
 */
class AMS_V2_Uninstaller {

    /**
     * 卸载插件
     */
    public static function uninstall() {
        // 检查用户权限
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // 记录卸载日志
        self::log_uninstall_start();
        
        // 清除所有定时任务
        self::clear_all_scheduled_events();
        
        // 删除数据库表（如果用户选择删除数据）
        if (self::should_delete_data()) {
            self::delete_database_tables();
        }
        
        // 删除所有插件选项
        self::delete_plugin_options();
        
        // 删除用户元数据
        self::delete_user_meta();
        
        // 删除上传的文件
        self::delete_uploaded_files();
        
        // 清理缓存
        self::clear_all_cache();
        
        // 记录卸载完成日志
        self::log_uninstall_complete();
    }

    /**
     * 检查是否应该删除数据
     *
     * @return bool
     */
    private static function should_delete_data() {
        // 检查是否设置了保留数据的选项
        $keep_data = get_option('ams_v2_keep_data_on_uninstall', false);
        
        return !$keep_data;
    }

    /**
     * 清除所有定时任务
     */
    private static function clear_all_scheduled_events() {
        $scheduled_events = array(
            'ams_v2_auto_cleanup',
            'ams_v2_log_cleanup',
            'ams_v2_update_statistics',
            'ams_v2_api_health_check'
        );

        foreach ($scheduled_events as $event) {
            wp_clear_scheduled_hook($event);
        }
    }

    /**
     * 删除数据库表
     */
    private static function delete_database_tables() {
        require_once AMS_V2_PLUGIN_DIR . 'includes/class-database-manager.php';
        
        try {
            $database_manager = new AMS_V2_Database_Manager();
            $database_manager->drop_tables();
        } catch (Exception $e) {
            error_log('Failed to drop database tables during uninstall: ' . $e->getMessage());
        }
    }

    /**
     * 删除所有插件选项
     */
    private static function delete_plugin_options() {
        global $wpdb;
        
        // 删除所有以 ams_v2_ 开头的选项
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                'ams_v2_%'
            )
        );

        // 删除特定的选项
        $options_to_delete = array(
            'ams_v2_version',
            'ams_v2_db_version',
            'ams_v2_activation_time',
            'ams_v2_first_install',
            'ams_v2_last_cleanup',
            'ams_v2_statistics_cache',
            'ams_v2_api_cache'
        );

        foreach ($options_to_delete as $option) {
            delete_option($option);
        }

        // 删除网络选项（如果是多站点）
        if (is_multisite()) {
            foreach ($options_to_delete as $option) {
                delete_site_option($option);
            }
        }
    }

    /**
     * 删除用户元数据
     */
    private static function delete_user_meta() {
        global $wpdb;
        
        // 删除所有以 ams_v2_ 开头的用户元数据
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
                'ams_v2_%'
            )
        );
    }

    /**
     * 删除上传的文件
     */
    private static function delete_uploaded_files() {
        $upload_dir = wp_upload_dir();
        
        // 删除插件相关的目录
        $directories_to_delete = array(
            $upload_dir['basedir'] . '/ams-v2-logs',
            $upload_dir['basedir'] . '/ams-v2-temp',
            $upload_dir['basedir'] . '/ams-v2-exports',
            $upload_dir['basedir'] . '/ams-v2-backups'
        );

        foreach ($directories_to_delete as $dir) {
            if (is_dir($dir)) {
                self::delete_directory_recursive($dir);
            }
        }
    }

    /**
     * 递归删除目录
     *
     * @param string $dir 目录路径
     */
    private static function delete_directory_recursive($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                self::delete_directory_recursive($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }

    /**
     * 清理所有缓存
     */
    private static function clear_all_cache() {
        // 清理WordPress对象缓存
        wp_cache_flush();
        
        // 清理所有瞬态数据
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 OR option_name LIKE %s",
                '_transient_ams_v2_%',
                '_transient_timeout_ams_v2_%'
            )
        );

        // 清理站点瞬态数据（多站点）
        if (is_multisite()) {
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->sitemeta} 
                     WHERE meta_key LIKE %s 
                     OR meta_key LIKE %s",
                    '_site_transient_ams_v2_%',
                    '_site_transient_timeout_ams_v2_%'
                )
            );
        }
    }

    /**
     * 记录卸载开始日志
     */
    private static function log_uninstall_start() {
        $log_message = sprintf(
            'Article Management V2 plugin uninstall started. Version: %s',
            defined('AMS_V2_VERSION') ? AMS_V2_VERSION : 'unknown'
        );

        error_log($log_message);
    }

    /**
     * 记录卸载完成日志
     */
    private static function log_uninstall_complete() {
        $log_message = sprintf(
            'Article Management V2 plugin uninstall completed. Version: %s',
            defined('AMS_V2_VERSION') ? AMS_V2_VERSION : 'unknown'
        );

        error_log($log_message);
    }

    /**
     * 获取卸载前的统计信息
     *
     * @return array 统计信息
     */
    public static function get_uninstall_stats() {
        global $wpdb;
        
        $stats = array(
            'uninstall_time' => current_time('mysql'),
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'plugin_version' => defined('AMS_V2_VERSION') ? AMS_V2_VERSION : 'unknown'
        );
        
        try {
            require_once AMS_V2_PLUGIN_DIR . 'includes/class-database-manager.php';
            $database_manager = new AMS_V2_Database_Manager();
            
            // 获取数据统计
            if ($database_manager->table_exists('users')) {
                $users_table = $database_manager->get_table_name('users');
                $stats['total_users'] = $wpdb->get_var("SELECT COUNT(*) FROM $users_table");
            }
            
            if ($database_manager->table_exists('articles')) {
                $articles_table = $database_manager->get_table_name('articles');
                $stats['total_articles'] = $wpdb->get_var("SELECT COUNT(*) FROM $articles_table");
            }
            
            if ($database_manager->table_exists('sites')) {
                $sites_table = $database_manager->get_table_name('sites');
                $stats['total_sites'] = $wpdb->get_var("SELECT COUNT(*) FROM $sites_table");
            }
            
        } catch (Exception $e) {
            $stats['error'] = $e->getMessage();
        }
        
        return $stats;
    }

    /**
     * 创建数据备份（可选）
     */
    public static function create_backup() {
        if (!self::should_delete_data()) {
            return false;
        }

        try {
            $upload_dir = wp_upload_dir();
            $backup_dir = $upload_dir['basedir'] . '/ams-v2-backups';
            
            if (!file_exists($backup_dir)) {
                wp_mkdir_p($backup_dir);
            }

            $backup_file = $backup_dir . '/ams-v2-backup-' . date('Y-m-d-H-i-s') . '.sql';
            
            // 这里可以实现数据库备份逻辑
            // 由于复杂性，这里只是创建一个占位符文件
            file_put_contents($backup_file, '-- AMS V2 Backup created on ' . date('Y-m-d H:i:s') . "\n");
            
            return $backup_file;
            
        } catch (Exception $e) {
            error_log('Failed to create backup during uninstall: ' . $e->getMessage());
            return false;
        }
    }
}