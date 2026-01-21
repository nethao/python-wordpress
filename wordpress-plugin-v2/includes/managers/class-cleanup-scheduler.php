<?php
/**
 * 清理调度器实现类
 *
 * @package Article_Management_V2
 * @subpackage Managers
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 清理调度器实现类
 */
class AMS_V2_Cleanup_Scheduler implements AMS_V2_Cleanup_Scheduler_Interface {

    /**
     * 数据库管理器
     *
     * @var AMS_V2_Database_Manager
     */
    private $db_manager;

    /**
     * 文章管理器
     *
     * @var AMS_V2_Article_Manager_Interface
     */
    private $article_manager;

    /**
     * 站点管理器
     *
     * @var AMS_V2_Site_Manager_Interface
     */
    private $site_manager;

    /**
     * 日志记录器
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
     * 构造函数
     *
     * @param AMS_V2_Database_Manager $db_manager 数据库管理器
     * @param AMS_V2_Article_Manager_Interface $article_manager 文章管理器
     * @param AMS_V2_Site_Manager_Interface $site_manager 站点管理器
     * @param AMS_V2_Logger $logger 日志记录器
     */
    public function __construct($db_manager, $article_manager, $site_manager, $logger) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->db_manager = $db_manager;
        $this->article_manager = $article_manager;
        $this->site_manager = $site_manager;
        $this->logger = $logger;
    }
    /**
     * 设置清理阈值天数
     *
     * @param int $days 天数
     * @return bool
     */
    public function set_threshold_days($days) {
        if (!is_numeric($days) || $days < 1) {
            return false;
        }

        return update_option('ams_v2_cleanup_threshold_days', (int) $days);
    }

    /**
     * 获取清理阈值天数
     *
     * @return int
     */
    public function get_threshold_days() {
        return (int) get_option('ams_v2_cleanup_threshold_days', 30);
    }

    /**
     * 启用自动清理
     *
     * @return bool
     */
    public function enable_auto_cleanup() {
        $result = update_option('ams_v2_auto_cleanup_enabled', true);
        if ($result) {
            $this->schedule_cleanup_task();
        }
        return $result;
    }

    /**
     * 禁用自动清理
     *
     * @return bool
     */
    public function disable_auto_cleanup() {
        $result = update_option('ams_v2_auto_cleanup_enabled', false);
        if ($result) {
            $this->unschedule_cleanup_task();
        }
        return $result;
    }

    /**
     * 检查自动清理是否启用
     *
     * @return bool
     */
    public function is_auto_cleanup_enabled() {
        return (bool) get_option('ams_v2_auto_cleanup_enabled', false);
    }
    /**
     * 识别需要清理的文章
     *
     * @param int $threshold_days 阈值天数
     * @return array 文章ID数组
     */
    public function identify_articles_for_cleanup($threshold_days = null) {
        if ($threshold_days === null) {
            $threshold_days = $this->get_threshold_days();
        }

        $articles_table = $this->db_manager->get_table_name('articles');
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$threshold_days} days"));

        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$articles_table} 
             WHERE created_at < %s 
             AND status IN ('draft', 'rejected', 'archived')
             ORDER BY created_at ASC",
            $cutoff_date
        );

        $results = $this->wpdb->get_col($sql);
        return array_map('intval', $results);
    }

    /**
     * 执行清理操作
     *
     * @param array $article_ids 要清理的文章ID数组
     * @param AMS_V2_User $triggered_by 触发清理的用户
     * @param string $cleanup_type 清理类型 (auto|manual)
     * @return array 清理结果
     */
    public function execute_cleanup($article_ids, $triggered_by = null, $cleanup_type = 'auto') {
        if (empty($article_ids)) {
            return array(
                'success' => true,
                'deleted_count' => 0,
                'failed_count' => 0,
                'message' => '没有找到需要清理的文章'
            );
        }

        // 记录清理开始
        $log_id = $this->start_cleanup_log($cleanup_type, count($article_ids), $this->get_threshold_days(), $triggered_by);
        
        $deleted_articles = array();
        $failed_articles = array();
        $deleted_count = 0;
        $failed_count = 0;

        foreach ($article_ids as $article_id) {
            try {
                $article = $this->article_manager->get_article_by_id($article_id);
                if (!$article) {
                    $failed_articles[] = array(
                        'id' => $article_id,
                        'error' => '文章不存在'
                    );
                    $failed_count++;
                    continue;
                }

                // 记录文章信息用于日志
                $article_info = array(
                    'id' => $article->get_id(),
                    'title' => $article->get_title(),
                    'author_id' => $article->get_author_id(),
                    'created_at' => $article->get_created_at(),
                    'status' => $article->get_status()
                );

                // 删除WordPress站点上的对应文章
                $this->delete_from_wordpress_sites($article);

                // 删除本地文章
                $result = $this->article_manager->delete_article($article, $triggered_by);
                
                if (is_wp_error($result)) {
                    $failed_articles[] = array(
                        'id' => $article_id,
                        'title' => $article->get_title(),
                        'error' => $result->get_error_message()
                    );
                    $failed_count++;
                } else {
                    $deleted_articles[] = $article_info;
                    $deleted_count++;
                }

            } catch (Exception $e) {
                $failed_articles[] = array(
                    'id' => $article_id,
                    'error' => $e->getMessage()
                );
                $failed_count++;
                
                $this->logger->error('清理文章时发生异常', array(
                    'article_id' => $article_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ));
            }
        }

        // 完成清理日志记录
        $this->complete_cleanup_log($log_id, $deleted_count, $deleted_articles, $failed_articles);

        $result = array(
            'success' => true,
            'deleted_count' => $deleted_count,
            'failed_count' => $failed_count,
            'deleted_articles' => $deleted_articles,
            'failed_articles' => $failed_articles
        );

        if ($deleted_count > 0) {
            $result['message'] = sprintf('清理完成：成功删除 %d 篇文章', $deleted_count);
            if ($failed_count > 0) {
                $result['message'] .= sprintf('，%d 篇文章删除失败', $failed_count);
            }
        } else {
            $result['message'] = '没有文章被删除';
            if ($failed_count > 0) {
                $result['message'] .= sprintf('，%d 篇文章删除失败', $failed_count);
            }
        }

        return $result;
    }
    /**
     * 从WordPress站点删除文章
     *
     * @param AMS_V2_Article $article 文章对象
     */
    private function delete_from_wordpress_sites($article) {
        try {
            // 获取文章的发布日志
            $publish_logs_table = $this->db_manager->get_table_name('publish_logs');
            
            $logs = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$publish_logs_table} 
                     WHERE article_id = %d AND status = 'published' AND wp_post_id IS NOT NULL",
                    $article->get_id()
                )
            );

            foreach ($logs as $log) {
                try {
                    $site = $this->site_manager->get_site_by_id($log->site_id);
                    if ($site && $site->is_active()) {
                        // 通过站点管理器删除WordPress文章
                        $this->site_manager->delete_post($site, $log->wp_post_id);
                        
                        // 更新发布日志状态
                        $this->wpdb->update(
                            $publish_logs_table,
                            array('status' => 'deleted'),
                            array('id' => $log->id),
                            array('%s'),
                            array('%d')
                        );
                    }
                } catch (Exception $e) {
                    $this->logger->warning('从WordPress站点删除文章失败', array(
                        'article_id' => $article->get_id(),
                        'site_id' => $log->site_id,
                        'wp_post_id' => $log->wp_post_id,
                        'error' => $e->getMessage()
                    ));
                }
            }
        } catch (Exception $e) {
            $this->logger->error('处理WordPress站点删除时发生错误', array(
                'article_id' => $article->get_id(),
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * 手动触发清理
     *
     * @param AMS_V2_User $user 触发用户
     * @param int $threshold_days 可选的阈值天数
     * @return array 清理结果
     */
    public function trigger_manual_cleanup($user, $threshold_days = null) {
        if (!$user->is_administrator()) {
            return array(
                'success' => false,
                'message' => '只有管理员可以执行手动清理'
            );
        }

        $article_ids = $this->identify_articles_for_cleanup($threshold_days);
        return $this->execute_cleanup($article_ids, $user, 'manual');
    }
    /**
     * 开始清理日志记录
     *
     * @param string $cleanup_type 清理类型
     * @param int $articles_count 文章数量
     * @param int $threshold_days 阈值天数
     * @param AMS_V2_User $triggered_by 触发用户
     * @return int 日志ID
     */
    private function start_cleanup_log($cleanup_type, $articles_count, $threshold_days, $triggered_by = null) {
        $cleanup_logs_table = $this->db_manager->get_table_name('cleanup_logs');
        
        $log_data = array(
            'cleanup_type' => $cleanup_type,
            'articles_deleted' => 0,
            'threshold_days' => $threshold_days,
            'started_at' => current_time('mysql'),
            'status' => 'running'
        );

        if ($triggered_by) {
            $log_data['triggered_by'] = $triggered_by->get_id();
        }

        $this->wpdb->insert($cleanup_logs_table, $log_data);
        return $this->wpdb->insert_id;
    }

    /**
     * 完成清理日志记录
     *
     * @param int $log_id 日志ID
     * @param int $deleted_count 删除数量
     * @param array $deleted_articles 删除的文章
     * @param array $failed_articles 失败的文章
     */
    private function complete_cleanup_log($log_id, $deleted_count, $deleted_articles, $failed_articles) {
        $cleanup_logs_table = $this->db_manager->get_table_name('cleanup_logs');
        
        $update_data = array(
            'articles_deleted' => $deleted_count,
            'completed_at' => current_time('mysql'),
            'status' => 'completed',
            'deleted_articles' => json_encode($deleted_articles)
        );

        if (!empty($failed_articles)) {
            $error_messages = array_map(function($item) {
                return "ID {$item['id']}: {$item['error']}";
            }, array_slice($failed_articles, 0, 10)); // 只记录前10个错误
            
            $update_data['error_message'] = implode('; ', $error_messages);
        }

        $this->wpdb->update(
            $cleanup_logs_table,
            $update_data,
            array('id' => $log_id),
            array('%d', '%s', '%s', '%s', '%s'),
            array('%d')
        );
    }
    /**
     * 获取清理日志
     *
     * @param array $filters 过滤条件
     * @return array
     */
    public function get_cleanup_logs($filters = array()) {
        $cleanup_logs_table = $this->db_manager->get_table_name('cleanup_logs');
        $users_table = $this->db_manager->get_table_name('users');
        
        $where_conditions = array('1=1');
        $where_values = array();

        // 过滤条件
        if (!empty($filters['cleanup_type'])) {
            $where_conditions[] = 'cl.cleanup_type = %s';
            $where_values[] = $filters['cleanup_type'];
        }

        if (!empty($filters['status'])) {
            $where_conditions[] = 'cl.status = %s';
            $where_values[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'DATE(cl.started_at) >= %s';
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'DATE(cl.started_at) <= %s';
            $where_values[] = $filters['date_to'];
        }

        // 分页
        $page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $per_page = isset($filters['per_page']) ? max(1, (int) $filters['per_page']) : 20;
        $offset = ($page - 1) * $per_page;

        $where_clause = implode(' AND ', $where_conditions);

        // 获取总数
        $count_sql = "SELECT COUNT(*) FROM {$cleanup_logs_table} cl WHERE {$where_clause}";
        if (!empty($where_values)) {
            $count_sql = $this->wpdb->prepare($count_sql, $where_values);
        }
        $total = (int) $this->wpdb->get_var($count_sql);

        // 获取数据
        $sql = "SELECT cl.*, u.username, u.display_name 
                FROM {$cleanup_logs_table} cl 
                LEFT JOIN {$users_table} u ON cl.triggered_by = u.id 
                WHERE {$where_clause} 
                ORDER BY cl.started_at DESC 
                LIMIT %d OFFSET %d";
        
        $query_values = array_merge($where_values, array($per_page, $offset));
        $sql = $this->wpdb->prepare($sql, $query_values);
        
        $logs = $this->wpdb->get_results($sql);

        return array(
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        );
    }

    /**
     * 获取清理统计信息
     *
     * @param int $days 统计天数
     * @return array
     */
    public function get_cleanup_statistics($days = 30) {
        $cleanup_logs_table = $this->db_manager->get_table_name('cleanup_logs');
        $date_from = date('Y-m-d', strtotime("-{$days} days"));

        // 总体统计
        $total_stats = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_cleanups,
                    SUM(articles_deleted) as total_deleted,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_cleanups,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_cleanups
                 FROM {$cleanup_logs_table} 
                 WHERE DATE(started_at) >= %s",
                $date_from
            )
        );

        // 按类型统计
        $type_stats = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT 
                    cleanup_type,
                    COUNT(*) as count,
                    SUM(articles_deleted) as deleted_count
                 FROM {$cleanup_logs_table} 
                 WHERE DATE(started_at) >= %s 
                 GROUP BY cleanup_type",
                $date_from
            )
        );

        // 按日期统计
        $daily_stats = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT 
                    DATE(started_at) as date,
                    COUNT(*) as cleanups,
                    SUM(articles_deleted) as deleted
                 FROM {$cleanup_logs_table} 
                 WHERE DATE(started_at) >= %s 
                 GROUP BY DATE(started_at) 
                 ORDER BY date DESC",
                $date_from
            )
        );

        return array(
            'total_stats' => $total_stats,
            'type_stats' => $type_stats,
            'daily_stats' => $daily_stats,
            'period_days' => $days
        );
    }
    /**
     * 调度定时清理任务
     *
     * @return bool
     */
    public function schedule_cleanup_task() {
        // 取消现有任务
        $this->unschedule_cleanup_task();
        
        // 调度新任务 - 每天凌晨2点执行
        $result = wp_schedule_event(
            strtotime('tomorrow 2:00 AM'),
            'daily',
            'ams_v2_auto_cleanup'
        );

        if ($result !== false) {
            $this->logger->info('自动清理任务已调度', array(
                'next_run' => wp_next_scheduled('ams_v2_auto_cleanup')
            ));
        }

        return $result !== false;
    }

    /**
     * 取消定时清理任务
     *
     * @return bool
     */
    public function unschedule_cleanup_task() {
        $timestamp = wp_next_scheduled('ams_v2_auto_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'ams_v2_auto_cleanup');
            $this->logger->info('自动清理任务已取消');
            return true;
        }
        return false;
    }

    /**
     * 执行自动清理任务（WordPress cron回调）
     */
    public function run_auto_cleanup() {
        if (!$this->is_auto_cleanup_enabled()) {
            return;
        }

        try {
            $this->logger->info('开始执行自动清理任务');
            
            $article_ids = $this->identify_articles_for_cleanup();
            $result = $this->execute_cleanup($article_ids, null, 'auto');
            
            $this->logger->info('自动清理任务完成', array(
                'deleted_count' => $result['deleted_count'],
                'failed_count' => $result['failed_count']
            ));
            
        } catch (Exception $e) {
            $this->logger->error('自动清理任务执行失败', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }
}