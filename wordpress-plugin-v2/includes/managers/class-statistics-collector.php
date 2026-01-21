<?php
/**
 * 统计收集器实现类
 *
 * @package Article_Management_V2
 * @subpackage Managers
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 统计收集器实现类
 */
class AMS_V2_Statistics_Collector implements AMS_V2_Statistics_Collector_Interface {

    /**
     * 数据库管理器
     *
     * @var AMS_V2_Database_Manager
     */
    private $db_manager;

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
     * 缓存前缀
     *
     * @var string
     */
    private $cache_prefix = 'ams_v2_stats_';

    /**
     * 构造函数
     *
     * @param AMS_V2_Database_Manager $db_manager 数据库管理器
     * @param AMS_V2_Logger $logger 日志记录器
     */
    public function __construct($db_manager, $logger) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->db_manager = $db_manager;
        $this->logger = $logger;
    }

    /**
     * 收集用户统计数据
     *
     * @param int $user_id 用户ID，null表示所有用户
     * @param string $period 统计周期 (daily|weekly|monthly|yearly)
     * @param string $date_from 开始日期
     * @param string $date_to 结束日期
     * @return array 统计数据
     */
    public function collect_user_statistics($user_id = null, $period = 'daily', $date_from = null, $date_to = null) {
        try {
            // 设置默认日期范围
            if (!$date_from) {
                $date_from = date('Y-m-d', strtotime('-30 days'));
            }
            if (!$date_to) {
                $date_to = date('Y-m-d');
            }

            $cache_key = $this->cache_prefix . "user_{$user_id}_{$period}_{$date_from}_{$date_to}";
            $cached_data = $this->get_statistics_cache($cache_key);
            
            if ($cached_data !== false) {
                return $cached_data;
            }

            $articles_table = $this->db_manager->get_table_name('articles');
            $users_table = $this->db_manager->get_table_name('users');
            
            $where_conditions = array('1=1');
            $where_values = array();

            // 用户过滤
            if ($user_id) {
                $where_conditions[] = 'a.author_id = %d';
                $where_values[] = $user_id;
            }

            // 日期过滤
            $where_conditions[] = 'DATE(a.created_at) >= %s';
            $where_conditions[] = 'DATE(a.created_at) <= %s';
            $where_values[] = $date_from;
            $where_values[] = $date_to;

            $where_clause = implode(' AND ', $where_conditions);

            // 根据周期分组
            $date_format = $this->get_date_format_for_period($period);
            
            $sql = "SELECT 
                        a.author_id as user_id,
                        u.username,
                        u.display_name,
                        {$date_format} as date,
                        COUNT(*) as total_submissions,
                        SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                        SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                        SUM(CASE WHEN a.status = 'published' THEN 1 ELSE 0 END) as published_count,
                        SUM(CASE WHEN a.status = 'blocked' THEN 1 ELSE 0 END) as blocked_count,
                        SUM(CASE WHEN a.status = 'draft' THEN 1 ELSE 0 END) as draft_count,
                        AVG(CASE 
                            WHEN a.moderated_at IS NOT NULL AND a.created_at IS NOT NULL 
                            THEN TIMESTAMPDIFF(HOUR, a.created_at, a.moderated_at) 
                            ELSE NULL 
                        END) as avg_review_time
                    FROM {$articles_table} a
                    LEFT JOIN {$users_table} u ON a.author_id = u.id
                    WHERE {$where_clause}
                    GROUP BY a.author_id, {$date_format}
                    ORDER BY date DESC, total_submissions DESC";

            if (!empty($where_values)) {
                $sql = $this->wpdb->prepare($sql, $where_values);
            }

            $results = $this->wpdb->get_results($sql);
            
            $statistics = array();
            foreach ($results as $row) {
                $user_stats = new AMS_V2_User_Stats(array(
                    'user_id' => $row->user_id,
                    'date' => $row->date,
                    'period' => $period,
                    'total_submissions' => $row->total_submissions,
                    'approved_count' => $row->approved_count,
                    'rejected_count' => $row->rejected_count,
                    'published_count' => $row->published_count,
                    'blocked_count' => $row->blocked_count,
                    'draft_count' => $row->draft_count,
                    'avg_review_time' => $row->avg_review_time
                ));
                
                $stats_array = $user_stats->to_array();
                $stats_array['username'] = $row->username;
                $stats_array['display_name'] = $row->display_name;
                
                $statistics[] = $stats_array;
            }

            // 缓存结果
            $this->update_statistics_cache($cache_key, $statistics, 1800); // 30分钟缓存

            return $statistics;

        } catch (Exception $e) {
            $this->logger->error('收集用户统计数据失败', array(
                'user_id' => $user_id,
                'period' => $period,
                'error' => $e->getMessage()
            ));
            return array();
        }
    }
    /**
     * 收集系统统计数据
     *
     * @param string $period 统计周期
     * @param string $date_from 开始日期
     * @param string $date_to 结束日期
     * @return array 统计数据
     */
    public function collect_system_statistics($period = 'daily', $date_from = null, $date_to = null) {
        try {
            if (!$date_from) {
                $date_from = date('Y-m-d', strtotime('-30 days'));
            }
            if (!$date_to) {
                $date_to = date('Y-m-d');
            }

            $cache_key = $this->cache_prefix . "system_{$period}_{$date_from}_{$date_to}";
            $cached_data = $this->get_statistics_cache($cache_key);
            
            if ($cached_data !== false) {
                return $cached_data;
            }

            $articles_table = $this->db_manager->get_table_name('articles');
            $users_table = $this->db_manager->get_table_name('users');
            $sites_table = $this->db_manager->get_table_name('sites');
            $audit_logs_table = $this->db_manager->get_table_name('audit_logs');

            $date_format = $this->get_date_format_for_period($period);
            
            // 系统统计查询
            $sql = "SELECT 
                        {$date_format} as date,
                        COUNT(DISTINCT CASE 
                            WHEN a.created_at >= %s AND a.created_at <= %s 
                            THEN a.author_id ELSE NULL END
                        ) as active_users,
                        COUNT(DISTINCT CASE 
                            WHEN u.created_at >= %s AND u.created_at <= %s 
                            THEN u.id ELSE NULL END
                        ) as new_users,
                        COUNT(a.id) as total_articles,
                        COUNT(CASE 
                            WHEN DATE(a.created_at) >= %s AND DATE(a.created_at) <= %s 
                            THEN a.id ELSE NULL END
                        ) as new_articles,
                        COUNT(CASE WHEN a.status = 'published' THEN a.id ELSE NULL END) as published_articles,
                        COUNT(CASE WHEN a.status IN ('pending', 'draft') THEN a.id ELSE NULL END) as pending_articles,
                        COUNT(CASE WHEN a.status = 'blocked' THEN a.id ELSE NULL END) as blocked_submissions
                    FROM {$articles_table} a
                    LEFT JOIN {$users_table} u ON a.author_id = u.id
                    WHERE DATE(a.created_at) >= %s AND DATE(a.created_at) <= %s
                    GROUP BY {$date_format}
                    ORDER BY date DESC";

            $results = $this->wpdb->get_results(
                $this->wpdb->prepare($sql, 
                    $date_from, $date_to, // active_users
                    $date_from, $date_to, // new_users
                    $date_from, $date_to, // new_articles
                    $date_from, $date_to  // main filter
                )
            );

            // 获取活跃站点数
            $active_sites = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$sites_table} WHERE is_active = 1"
                )
            );

            // 获取API调用次数（从审计日志）
            $api_calls = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$audit_logs_table} 
                     WHERE action LIKE 'api_%' 
                     AND DATE(created_at) >= %s AND DATE(created_at) <= %s",
                    $date_from, $date_to
                )
            );

            // 获取系统错误数
            $system_errors = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$audit_logs_table} 
                     WHERE action = 'system_error' 
                     AND DATE(created_at) >= %s AND DATE(created_at) <= %s",
                    $date_from, $date_to
                )
            );

            $statistics = array();
            foreach ($results as $row) {
                $system_stats = new AMS_V2_System_Stats(array(
                    'date' => $row->date,
                    'period' => $period,
                    'active_users' => $row->active_users,
                    'new_users' => $row->new_users,
                    'total_articles' => $row->total_articles,
                    'new_articles' => $row->new_articles,
                    'published_articles' => $row->published_articles,
                    'pending_articles' => $row->pending_articles,
                    'blocked_submissions' => $row->blocked_submissions,
                    'active_sites' => $active_sites,
                    'api_calls' => $api_calls,
                    'system_errors' => $system_errors
                ));
                
                $statistics[] = $system_stats->to_array();
            }

            $this->update_statistics_cache($cache_key, $statistics, 1800);
            return $statistics;

        } catch (Exception $e) {
            $this->logger->error('收集系统统计数据失败', array(
                'period' => $period,
                'error' => $e->getMessage()
            ));
            return array();
        }
    }

    /**
     * 获取用户发布量统计
     *
     * @param int $user_id 用户ID
     * @param string $period 统计周期
     * @param int $limit 限制数量
     * @return array 发布量统计
     */
    public function get_user_publish_stats($user_id, $period = 'daily', $limit = 30) {
        try {
            $cache_key = $this->cache_prefix . "user_publish_{$user_id}_{$period}_{$limit}";
            $cached_data = $this->get_statistics_cache($cache_key);
            
            if ($cached_data !== false) {
                return $cached_data;
            }

            $articles_table = $this->db_manager->get_table_name('articles');
            $date_format = $this->get_date_format_for_period($period);
            
            $sql = "SELECT 
                        {$date_format} as date,
                        COUNT(*) as total_count,
                        COUNT(CASE WHEN status = 'published' THEN 1 END) as published_count,
                        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
                        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
                        COUNT(CASE WHEN status = 'blocked' THEN 1 END) as blocked_count
                    FROM {$articles_table}
                    WHERE author_id = %d
                    GROUP BY {$date_format}
                    ORDER BY date DESC
                    LIMIT %d";

            $results = $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $user_id, $limit)
            );

            $statistics = array();
            foreach ($results as $row) {
                $statistics[] = array(
                    'date' => $row->date,
                    'total_count' => (int) $row->total_count,
                    'published_count' => (int) $row->published_count,
                    'approved_count' => (int) $row->approved_count,
                    'rejected_count' => (int) $row->rejected_count,
                    'blocked_count' => (int) $row->blocked_count,
                    'success_rate' => $row->total_count > 0 ? 
                        round((($row->published_count + $row->approved_count) / $row->total_count) * 100, 2) : 0
                );
            }

            $this->update_statistics_cache($cache_key, $statistics, 900); // 15分钟缓存
            return $statistics;

        } catch (Exception $e) {
            $this->logger->error('获取用户发布量统计失败', array(
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ));
            return array();
        }
    }
    /**
     * 获取内容审核统计
     *
     * @param string $period 统计周期
     * @param string $date_from 开始日期
     * @param string $date_to 结束日期
     * @return array 审核统计
     */
    public function get_moderation_statistics($period = 'daily', $date_from = null, $date_to = null) {
        try {
            if (!$date_from) {
                $date_from = date('Y-m-d', strtotime('-30 days'));
            }
            if (!$date_to) {
                $date_to = date('Y-m-d');
            }

            $cache_key = $this->cache_prefix . "moderation_{$period}_{$date_from}_{$date_to}";
            $cached_data = $this->get_statistics_cache($cache_key);
            
            if ($cached_data !== false) {
                return $cached_data;
            }

            $articles_table = $this->db_manager->get_table_name('articles');
            $date_format = $this->get_date_format_for_period($period);
            
            $sql = "SELECT 
                        {$date_format} as date,
                        COUNT(*) as total_moderated,
                        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
                        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
                        COUNT(CASE WHEN status = 'blocked' THEN 1 END) as blocked_count,
                        AVG(CASE 
                            WHEN moderated_at IS NOT NULL AND created_at IS NOT NULL 
                            THEN TIMESTAMPDIFF(HOUR, created_at, moderated_at) 
                            ELSE NULL 
                        END) as avg_moderation_time
                    FROM {$articles_table}
                    WHERE moderated_at IS NOT NULL
                    AND DATE(moderated_at) >= %s AND DATE(moderated_at) <= %s
                    GROUP BY {$date_format}
                    ORDER BY date DESC";

            $results = $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $date_from, $date_to)
            );

            $statistics = array();
            foreach ($results as $row) {
                $statistics[] = array(
                    'date' => $row->date,
                    'total_moderated' => (int) $row->total_moderated,
                    'approved_count' => (int) $row->approved_count,
                    'rejected_count' => (int) $row->rejected_count,
                    'blocked_count' => (int) $row->blocked_count,
                    'avg_moderation_time' => round($row->avg_moderation_time, 2),
                    'approval_rate' => $row->total_moderated > 0 ? 
                        round(($row->approved_count / $row->total_moderated) * 100, 2) : 0,
                    'rejection_rate' => $row->total_moderated > 0 ? 
                        round(($row->rejected_count / $row->total_moderated) * 100, 2) : 0
                );
            }

            $this->update_statistics_cache($cache_key, $statistics, 1800);
            return $statistics;

        } catch (Exception $e) {
            $this->logger->error('获取内容审核统计失败', array(
                'error' => $e->getMessage()
            ));
            return array();
        }
    }

    /**
     * 获取敏感词拦截统计
     *
     * @param string $period 统计周期
     * @param string $date_from 开始日期
     * @param string $date_to 结束日期
     * @return array 拦截统计
     */
    public function get_sensitive_word_statistics($period = 'daily', $date_from = null, $date_to = null) {
        try {
            if (!$date_from) {
                $date_from = date('Y-m-d', strtotime('-30 days'));
            }
            if (!$date_to) {
                $date_to = date('Y-m-d');
            }

            $cache_key = $this->cache_prefix . "sensitive_words_{$period}_{$date_from}_{$date_to}";
            $cached_data = $this->get_statistics_cache($cache_key);
            
            if ($cached_data !== false) {
                return $cached_data;
            }

            $audit_logs_table = $this->db_manager->get_table_name('audit_logs');
            $date_format = $this->get_date_format_for_period($period);
            
            $sql = "SELECT 
                        {$date_format} as date,
                        COUNT(*) as total_blocks,
                        COUNT(CASE WHEN new_values LIKE '%high_risk%' THEN 1 END) as high_risk_blocks,
                        COUNT(CASE WHEN new_values LIKE '%normal%' THEN 1 END) as normal_blocks
                    FROM {$audit_logs_table}
                    WHERE action = 'content_blocked'
                    AND DATE(created_at) >= %s AND DATE(created_at) <= %s
                    GROUP BY {$date_format}
                    ORDER BY date DESC";

            $results = $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $date_from, $date_to)
            );

            $statistics = array();
            foreach ($results as $row) {
                $statistics[] = array(
                    'date' => $row->date,
                    'total_blocks' => (int) $row->total_blocks,
                    'high_risk_blocks' => (int) $row->high_risk_blocks,
                    'normal_blocks' => (int) $row->normal_blocks,
                    'high_risk_rate' => $row->total_blocks > 0 ? 
                        round(($row->high_risk_blocks / $row->total_blocks) * 100, 2) : 0
                );
            }

            $this->update_statistics_cache($cache_key, $statistics, 1800);
            return $statistics;

        } catch (Exception $e) {
            $this->logger->error('获取敏感词拦截统计失败', array(
                'error' => $e->getMessage()
            ));
            return array();
        }
    }

    /**
     * 获取站点发布统计
     *
     * @param int $site_id 站点ID，null表示所有站点
     * @param string $period 统计周期
     * @param string $date_from 开始日期
     * @param string $date_to 结束日期
     * @return array 站点统计
     */
    public function get_site_publish_statistics($site_id = null, $period = 'daily', $date_from = null, $date_to = null) {
        try {
            if (!$date_from) {
                $date_from = date('Y-m-d', strtotime('-30 days'));
            }
            if (!$date_to) {
                $date_to = date('Y-m-d');
            }

            $cache_key = $this->cache_prefix . "site_publish_{$site_id}_{$period}_{$date_from}_{$date_to}";
            $cached_data = $this->get_statistics_cache($cache_key);
            
            if ($cached_data !== false) {
                return $cached_data;
            }

            $publish_logs_table = $this->db_manager->get_table_name('publish_logs');
            $sites_table = $this->db_manager->get_table_name('sites');
            $date_format = $this->get_date_format_for_period($period);
            
            $where_conditions = array('1=1');
            $where_values = array();

            if ($site_id) {
                $where_conditions[] = 'pl.site_id = %d';
                $where_values[] = $site_id;
            }

            $where_conditions[] = 'DATE(pl.created_at) >= %s';
            $where_conditions[] = 'DATE(pl.created_at) <= %s';
            $where_values[] = $date_from;
            $where_values[] = $date_to;

            $where_clause = implode(' AND ', $where_conditions);
            
            $sql = "SELECT 
                        pl.site_id,
                        s.name as site_name,
                        {$date_format} as date,
                        COUNT(*) as total_attempts,
                        COUNT(CASE WHEN pl.status = 'published' THEN 1 END) as successful_publishes,
                        COUNT(CASE WHEN pl.status = 'failed' THEN 1 END) as failed_publishes,
                        AVG(CASE 
                            WHEN pl.published_at IS NOT NULL AND pl.created_at IS NOT NULL 
                            THEN TIMESTAMPDIFF(SECOND, pl.created_at, pl.published_at) 
                            ELSE NULL 
                        END) as avg_publish_time
                    FROM {$publish_logs_table} pl
                    LEFT JOIN {$sites_table} s ON pl.site_id = s.id
                    WHERE {$where_clause}
                    GROUP BY pl.site_id, {$date_format}
                    ORDER BY date DESC, successful_publishes DESC";

            if (!empty($where_values)) {
                $sql = $this->wpdb->prepare($sql, $where_values);
            }

            $results = $this->wpdb->get_results($sql);

            $statistics = array();
            foreach ($results as $row) {
                $statistics[] = array(
                    'site_id' => (int) $row->site_id,
                    'site_name' => $row->site_name,
                    'date' => $row->date,
                    'total_attempts' => (int) $row->total_attempts,
                    'successful_publishes' => (int) $row->successful_publishes,
                    'failed_publishes' => (int) $row->failed_publishes,
                    'avg_publish_time' => round($row->avg_publish_time, 2),
                    'success_rate' => $row->total_attempts > 0 ? 
                        round(($row->successful_publishes / $row->total_attempts) * 100, 2) : 0
                );
            }

            $this->update_statistics_cache($cache_key, $statistics, 1800);
            return $statistics;

        } catch (Exception $e) {
            $this->logger->error('获取站点发布统计失败', array(
                'site_id' => $site_id,
                'error' => $e->getMessage()
            ));
            return array();
        }
    }
    /**
     * 获取实时统计数据
     *
     * @return array 实时统计
     */
    public function get_realtime_statistics() {
        try {
            $cache_key = $this->cache_prefix . 'realtime';
            $cached_data = $this->get_statistics_cache($cache_key);
            
            if ($cached_data !== false) {
                return $cached_data;
            }

            $articles_table = $this->db_manager->get_table_name('articles');
            $users_table = $this->db_manager->get_table_name('users');
            $sites_table = $this->db_manager->get_table_name('sites');
            $sensitive_words_table = $this->db_manager->get_table_name('sensitive_words');

            // 今日统计
            $today = date('Y-m-d');
            
            $statistics = array(
                'today' => array(
                    'new_articles' => $this->wpdb->get_var(
                        $this->wpdb->prepare(
                            "SELECT COUNT(*) FROM {$articles_table} WHERE DATE(created_at) = %s",
                            $today
                        )
                    ),
                    'published_articles' => $this->wpdb->get_var(
                        $this->wpdb->prepare(
                            "SELECT COUNT(*) FROM {$articles_table} WHERE DATE(published_at) = %s",
                            $today
                        )
                    ),
                    'pending_articles' => $this->wpdb->get_var(
                        "SELECT COUNT(*) FROM {$articles_table} WHERE status IN ('pending', 'draft')"
                    ),
                    'active_users' => $this->wpdb->get_var(
                        $this->wpdb->prepare(
                            "SELECT COUNT(DISTINCT author_id) FROM {$articles_table} WHERE DATE(created_at) = %s",
                            $today
                        )
                    )
                ),
                'totals' => array(
                    'total_articles' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$articles_table}"),
                    'total_users' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$users_table}"),
                    'active_sites' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$sites_table} WHERE is_active = 1"),
                    'sensitive_words' => $this->wpdb->get_var("SELECT COUNT(*) FROM {$sensitive_words_table} WHERE is_active = 1")
                ),
                'recent_activity' => array(
                    'last_article' => $this->wpdb->get_row(
                        "SELECT title, created_at, status FROM {$articles_table} ORDER BY created_at DESC LIMIT 1"
                    ),
                    'last_user' => $this->wpdb->get_row(
                        "SELECT username, created_at FROM {$users_table} ORDER BY created_at DESC LIMIT 1"
                    )
                )
            );

            $this->update_statistics_cache($cache_key, $statistics, 300); // 5分钟缓存
            return $statistics;

        } catch (Exception $e) {
            $this->logger->error('获取实时统计数据失败', array(
                'error' => $e->getMessage()
            ));
            return array();
        }
    }

    /**
     * 导出统计数据为CSV
     *
     * @param array $data 统计数据
     * @param string $filename 文件名
     * @return bool 导出结果
     */
    public function export_statistics_csv($data, $filename = null) {
        try {
            if (empty($data)) {
                return false;
            }

            if (!$filename) {
                $filename = 'statistics_' . date('Y-m-d_H-i-s') . '.csv';
            }

            // 设置CSV下载头
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');
            
            // 添加BOM以支持中文
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // 写入标题行
            if (!empty($data[0])) {
                $headers = array_keys($data[0]);
                fputcsv($output, $headers);
            }

            // 写入数据行
            foreach ($data as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
            return true;

        } catch (Exception $e) {
            $this->logger->error('导出统计数据CSV失败', array(
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * 更新统计缓存
     *
     * @param string $cache_key 缓存键
     * @param array $data 数据
     * @param int $expiration 过期时间（秒）
     * @return bool 更新结果
     */
    public function update_statistics_cache($cache_key, $data, $expiration = 3600) {
        return wp_cache_set($cache_key, $data, 'ams_v2_statistics', $expiration);
    }

    /**
     * 获取统计缓存
     *
     * @param string $cache_key 缓存键
     * @return mixed 缓存数据或false
     */
    public function get_statistics_cache($cache_key) {
        return wp_cache_get($cache_key, 'ams_v2_statistics');
    }

    /**
     * 清除统计缓存
     *
     * @param string $pattern 缓存键模式
     * @return bool 清除结果
     */
    public function clear_statistics_cache($pattern = null) {
        if ($pattern) {
            // WordPress没有内置的模式匹配缓存清除，这里简化处理
            return wp_cache_flush();
        } else {
            return wp_cache_flush();
        }
    }

    /**
     * 根据周期获取日期格式
     *
     * @param string $period 统计周期
     * @return string SQL日期格式
     */
    private function get_date_format_for_period($period) {
        switch ($period) {
            case 'hourly':
                return "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')";
            case 'daily':
                return "DATE(created_at)";
            case 'weekly':
                return "DATE_FORMAT(created_at, '%Y-%u')";
            case 'monthly':
                return "DATE_FORMAT(created_at, '%Y-%m')";
            case 'yearly':
                return "YEAR(created_at)";
            default:
                return "DATE(created_at)";
        }
    }
}