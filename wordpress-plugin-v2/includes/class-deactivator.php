<?php
/**
 * 插件停用器类
 *
 * @package Article_Management_V2
 * @subpackage Includes
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 插件停用器类
 */
class AMS_V2_Deactivator {

    /**
     * 停用插件
     */
    public static function deactivate() {
        // 清除定时任务
        self::clear_scheduled_events();
        
        // 刷新重写规则
        flush_rewrite_rules();
        
        // 清理临时数据
        self::cleanup_temporary_data();
        
        // 记录停用日志
        self::log_deactivation();
    }

    /**
     * 清除定时任务
     */
    private static function clear_scheduled_events() {
        $scheduled_events = array(
            'ams_v2_auto_cleanup',
            'ams_v2_log_cleanup',
            'ams_v2_update_statistics',
            'ams_v2_api_health_check'
        );

        foreach ($scheduled_events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
        }

        // 清除所有相关的定时任务
        wp_clear_scheduled_hook('ams_v2_auto_cleanup');
        wp_clear_scheduled_hook('ams_v2_log_cleanup');
        wp_clear_scheduled_hook('ams_v2_update_statistics');
        wp_clear_scheduled_hook('ams_v2_api_health_check');
    }

    /**
     * 清理临时数据
     */
    private static function cleanup_temporary_data() {
        // 清理缓存
        self::clear_plugin_cache();
        
        // 清理临时文件
        self::cleanup_temp_files();
        
        // 清理过期的会话数据
        self::cleanup_expired_sessions();
    }

    /**
     * 清理插件缓存
     */
    private static function clear_plugin_cache() {
        // 清理WordPress对象缓存
        wp_cache_flush();
        
        // 清理插件特定的缓存
        $cache_keys = array(
            'ams_v2_user_stats',
            'ams_v2_system_stats',
            'ams_v2_site_status',
            'ams_v2_api_status',
            'ams_v2_sensitive_words'
        );

        foreach ($cache_keys as $key) {
            wp_cache_delete($key, 'ams_v2');
        }

        // 清理瞬态数据
        $transients = array(
            'ams_v2_dashboard_stats',
            'ams_v2_api_balance',
            'ams_v2_site_health',
            'ams_v2_cleanup_status'
        );

        foreach ($transients as $transient) {
            delete_transient($transient);
        }
    }

    /**
     * 清理临时文件
     */
    private static function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/ams-v2-temp';
        
        if (is_dir($temp_dir)) {
            self::delete_directory($temp_dir);
        }
    }

    /**
     * 清理过期的会话数据
     */
    private static function cleanup_expired_sessions() {
        global $wpdb;
        
        // 清理过期的用户会话
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_value < %s",
                'ams_v2_user_session_%',
                time() - (24 * HOUR_IN_SECONDS)
            )
        );
    }

    /**
     * 递归删除目录
     *
     * @param string $dir 目录路径
     */
    private static function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                self::delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }

    /**
     * 记录停用日志
     */
    private static function log_deactivation() {
        $log_message = sprintf(
            'Article Management V2 plugin deactivated. Version: %s',
            defined('AMS_V2_VERSION') ? AMS_V2_VERSION : 'unknown'
        );

        error_log($log_message);

        // 如果可能，使用插件日志系统
        if (class_exists('AMS_V2_Logger')) {
            try {
                $logger = new AMS_V2_Logger();
                $logger->info('Plugin deactivated', array(
                    'version' => defined('AMS_V2_VERSION') ? AMS_V2_VERSION : 'unknown',
                    'deactivation_time' => current_time('mysql')
                ));
            } catch (Exception $e) {
                error_log('Failed to log deactivation: ' . $e->getMessage());
            }
        }
    }

    /**
     * 获取停用统计信息
     *
     * @return array 停用统计信息
     */
    public static function get_deactivation_stats() {
        global $wpdb;
        
        $stats = array();
        
        try {
            require_once AMS_V2_PLUGIN_DIR . 'includes/class-database-manager.php';
            $database_manager = new AMS_V2_Database_Manager();
            
            // 获取用户数量
            $users_table = $database_manager->get_table_name('users');
            if ($database_manager->table_exists('users')) {
                $stats['total_users'] = $wpdb->get_var("SELECT COUNT(*) FROM $users_table");
            }
            
            // 获取文章数量
            $articles_table = $database_manager->get_table_name('articles');
            if ($database_manager->table_exists('articles')) {
                $stats['total_articles'] = $wpdb->get_var("SELECT COUNT(*) FROM $articles_table");
                $stats['published_articles'] = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM $articles_table WHERE status = %s",
                        'published'
                    )
                );
            }
            
            // 获取站点数量
            $sites_table = $database_manager->get_table_name('sites');
            if ($database_manager->table_exists('sites')) {
                $stats['total_sites'] = $wpdb->get_var("SELECT COUNT(*) FROM $sites_table");
            }
            
        } catch (Exception $e) {
            error_log('Failed to get deactivation stats: ' . $e->getMessage());
        }
        
        return $stats;
    }
}