<?php
/**
 * 系统监控类
 *
 * @package Article_Management_V2
 * @subpackage System
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 系统监控类
 * 
 * 提供系统健康检查、性能监控和状态报告功能
 */
class AMS_V2_System_Monitor {

    /**
     * 依赖注入容器
     *
     * @var AMS_V2_Container
     */
    private $container;

    /**
     * 错误处理器
     *
     * @var AMS_V2_Error_Handler
     */
    private $error_handler;

    /**
     * 日志记录器
     *
     * @var AMS_V2_Logger
     */
    private $logger;

    /**
     * 构造函数
     *
     * @param AMS_V2_Container $container 依赖注入容器
     */
    public function __construct($container) {
        $this->container = $container;
        $this->error_handler = $container->get('error_handler');
        $this->logger = $container->get('logger');
    }

    /**
     * 执行系统健康检查
     *
     * @return array 健康检查结果
     */
    public function health_check() {
        $results = [];
        
        try {
            // 数据库连接检查
            $results['database'] = $this->check_database_connection();
            
            // 服务可用性检查
            $results['services'] = $this->check_services_availability();
            
            // 文件系统检查
            $results['filesystem'] = $this->check_filesystem();
            
            // 内存使用检查
            $results['memory'] = $this->check_memory_usage();
            
            // 数据库性能检查
            $results['database_performance'] = $this->check_database_performance();
            
            // 外部API连接检查
            $results['external_apis'] = $this->check_external_apis();
            
            // 计算总体健康评分
            $results['overall_score'] = $this->calculate_health_score($results);
            $results['status'] = $this->get_health_status($results['overall_score']);
            
        } catch (Exception $e) {
            $this->error_handler->handle_error($e);
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }

    /**
     * 检查数据库连接
     *
     * @return array 检查结果
     */
    private function check_database_connection() {
        global $wpdb;
        
        $start_time = microtime(true);
        
        try {
            // 测试数据库连接
            $result = $wpdb->get_var("SELECT 1");
            $response_time = (microtime(true) - $start_time) * 1000;
            
            if ($result == 1) {
                return [
                    'status' => 'healthy',
                    'message' => '数据库连接正常',
                    'response_time' => round($response_time, 2) . 'ms',
                    'score' => 100
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => '数据库连接异常',
                    'score' => 0
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => '数据库连接失败: ' . $e->getMessage(),
                'score' => 0
            ];
        }
    }

    /**
     * 检查服务可用性
     *
     * @return array 检查结果
     */
    private function check_services_availability() {
        $services = [
            'authentication_manager',
            'article_manager',
            'site_manager',
            'sensitive_word_manager',
            'content_moderator',
            'cleanup_scheduler',
            'statistics_collector',
            'audit_logger'
        ];
        
        $results = [];
        $total_score = 0;
        
        foreach ($services as $service) {
            try {
                $instance = $this->container->get($service);
                if ($instance) {
                    $results[$service] = [
                        'status' => 'available',
                        'message' => '服务可用',
                        'score' => 100
                    ];
                    $total_score += 100;
                } else {
                    $results[$service] = [
                        'status' => 'unavailable',
                        'message' => '服务不可用',
                        'score' => 0
                    ];
                }
            } catch (Exception $e) {
                $results[$service] = [
                    'status' => 'error',
                    'message' => '服务错误: ' . $e->getMessage(),
                    'score' => 0
                ];
            }
        }
        
        return [
            'services' => $results,
            'average_score' => count($services) > 0 ? $total_score / count($services) : 0,
            'total_services' => count($services),
            'available_services' => count(array_filter($results, function($r) { 
                return $r['status'] === 'available'; 
            }))
        ];
    }

    /**
     * 检查文件系统
     *
     * @return array 检查结果
     */
    private function check_filesystem() {
        $results = [];
        
        // 检查插件目录权限
        $plugin_dir = AMS_V2_PLUGIN_DIR;
        if (is_readable($plugin_dir) && is_writable($plugin_dir)) {
            $results['plugin_directory'] = [
                'status' => 'healthy',
                'message' => '插件目录权限正常',
                'score' => 100
            ];
        } else {
            $results['plugin_directory'] = [
                'status' => 'warning',
                'message' => '插件目录权限异常',
                'score' => 50
            ];
        }
        
        // 检查上传目录
        $upload_dir = wp_upload_dir();
        if (is_writable($upload_dir['basedir'])) {
            $results['upload_directory'] = [
                'status' => 'healthy',
                'message' => '上传目录可写',
                'score' => 100
            ];
        } else {
            $results['upload_directory'] = [
                'status' => 'error',
                'message' => '上传目录不可写',
                'score' => 0
            ];
        }
        
        // 检查磁盘空间
        $free_space = disk_free_space($plugin_dir);
        $total_space = disk_total_space($plugin_dir);
        $usage_percent = (($total_space - $free_space) / $total_space) * 100;
        
        if ($usage_percent < 80) {
            $results['disk_space'] = [
                'status' => 'healthy',
                'message' => '磁盘空间充足',
                'usage_percent' => round($usage_percent, 2),
                'score' => 100
            ];
        } elseif ($usage_percent < 90) {
            $results['disk_space'] = [
                'status' => 'warning',
                'message' => '磁盘空间紧张',
                'usage_percent' => round($usage_percent, 2),
                'score' => 70
            ];
        } else {
            $results['disk_space'] = [
                'status' => 'error',
                'message' => '磁盘空间不足',
                'usage_percent' => round($usage_percent, 2),
                'score' => 30
            ];
        }
        
        // 计算文件系统总分
        $total_score = 0;
        $count = 0;
        foreach ($results as $result) {
            $total_score += $result['score'];
            $count++;
        }
        
        return [
            'checks' => $results,
            'average_score' => $count > 0 ? $total_score / $count : 0
        ];
    }

    /**
     * 检查内存使用情况
     *
     * @return array 检查结果
     */
    private function check_memory_usage() {
        $memory_limit = ini_get('memory_limit');
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        
        // 转换内存限制为字节
        $limit_bytes = $this->convert_to_bytes($memory_limit);
        
        $usage_percent = ($memory_usage / $limit_bytes) * 100;
        $peak_percent = ($memory_peak / $limit_bytes) * 100;
        
        if ($usage_percent < 70) {
            $status = 'healthy';
            $message = '内存使用正常';
            $score = 100;
        } elseif ($usage_percent < 85) {
            $status = 'warning';
            $message = '内存使用较高';
            $score = 70;
        } else {
            $status = 'error';
            $message = '内存使用过高';
            $score = 30;
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'memory_limit' => $memory_limit,
            'current_usage' => $this->format_bytes($memory_usage),
            'peak_usage' => $this->format_bytes($memory_peak),
            'usage_percent' => round($usage_percent, 2),
            'peak_percent' => round($peak_percent, 2),
            'score' => $score
        ];
    }

    /**
     * 检查数据库性能
     *
     * @return array 检查结果
     */
    private function check_database_performance() {
        global $wpdb;
        
        $results = [];
        
        // 检查查询时间
        $start_time = microtime(true);
        $wpdb->get_results("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_articles");
        $query_time = (microtime(true) - $start_time) * 1000;
        
        if ($query_time < 100) {
            $results['query_performance'] = [
                'status' => 'healthy',
                'message' => '查询性能良好',
                'query_time' => round($query_time, 2) . 'ms',
                'score' => 100
            ];
        } elseif ($query_time < 500) {
            $results['query_performance'] = [
                'status' => 'warning',
                'message' => '查询性能一般',
                'query_time' => round($query_time, 2) . 'ms',
                'score' => 70
            ];
        } else {
            $results['query_performance'] = [
                'status' => 'error',
                'message' => '查询性能较差',
                'query_time' => round($query_time, 2) . 'ms',
                'score' => 30
            ];
        }
        
        // 检查数据库大小
        $db_size = $wpdb->get_var("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS 'DB Size in MB' 
            FROM information_schema.tables 
            WHERE table_schema='{$wpdb->dbname}'
        ");
        
        if ($db_size < 100) {
            $results['database_size'] = [
                'status' => 'healthy',
                'message' => '数据库大小正常',
                'size' => $db_size . 'MB',
                'score' => 100
            ];
        } elseif ($db_size < 500) {
            $results['database_size'] = [
                'status' => 'warning',
                'message' => '数据库较大',
                'size' => $db_size . 'MB',
                'score' => 80
            ];
        } else {
            $results['database_size'] = [
                'status' => 'warning',
                'message' => '数据库很大，建议优化',
                'size' => $db_size . 'MB',
                'score' => 60
            ];
        }
        
        // 计算数据库性能总分
        $total_score = 0;
        $count = 0;
        foreach ($results as $result) {
            $total_score += $result['score'];
            $count++;
        }
        
        return [
            'checks' => $results,
            'average_score' => $count > 0 ? $total_score / $count : 0
        ];
    }

    /**
     * 检查外部API连接
     *
     * @return array 检查结果
     */
    private function check_external_apis() {
        $results = [];
        
        try {
            // 检查DeepSeek API
            $api_manager = $this->container->get('api_key_manager');
            $deepseek_client = $this->container->get('deepseek_client');
            
            if ($api_manager->has_valid_key()) {
                $start_time = microtime(true);
                $api_status = $deepseek_client->check_health();
                $response_time = (microtime(true) - $start_time) * 1000;
                
                if ($api_status) {
                    $results['deepseek_api'] = [
                        'status' => 'healthy',
                        'message' => 'DeepSeek API连接正常',
                        'response_time' => round($response_time, 2) . 'ms',
                        'score' => 100
                    ];
                } else {
                    $results['deepseek_api'] = [
                        'status' => 'error',
                        'message' => 'DeepSeek API连接失败',
                        'score' => 0
                    ];
                }
            } else {
                $results['deepseek_api'] = [
                    'status' => 'warning',
                    'message' => 'DeepSeek API未配置',
                    'score' => 50
                ];
            }
        } catch (Exception $e) {
            $results['deepseek_api'] = [
                'status' => 'error',
                'message' => 'DeepSeek API检查异常: ' . $e->getMessage(),
                'score' => 0
            ];
        }
        
        // 检查WordPress站点连接
        try {
            $site_manager = $this->container->get('site_manager');
            $sites = $site_manager->get_all_sites();
            
            $connected_sites = 0;
            $total_sites = count($sites);
            
            foreach ($sites as $site) {
                if ($site_manager->test_connection($site)) {
                    $connected_sites++;
                }
            }
            
            if ($total_sites == 0) {
                $results['wordpress_sites'] = [
                    'status' => 'warning',
                    'message' => '未配置WordPress站点',
                    'score' => 50
                ];
            } elseif ($connected_sites == $total_sites) {
                $results['wordpress_sites'] = [
                    'status' => 'healthy',
                    'message' => '所有WordPress站点连接正常',
                    'connected' => $connected_sites,
                    'total' => $total_sites,
                    'score' => 100
                ];
            } else {
                $results['wordpress_sites'] = [
                    'status' => 'warning',
                    'message' => '部分WordPress站点连接异常',
                    'connected' => $connected_sites,
                    'total' => $total_sites,
                    'score' => ($connected_sites / $total_sites) * 100
                ];
            }
        } catch (Exception $e) {
            $results['wordpress_sites'] = [
                'status' => 'error',
                'message' => 'WordPress站点检查异常: ' . $e->getMessage(),
                'score' => 0
            ];
        }
        
        // 计算外部API总分
        $total_score = 0;
        $count = 0;
        foreach ($results as $result) {
            $total_score += $result['score'];
            $count++;
        }
        
        return [
            'checks' => $results,
            'average_score' => $count > 0 ? $total_score / $count : 0
        ];
    }

    /**
     * 计算健康评分
     *
     * @param array $results 检查结果
     * @return float 健康评分
     */
    private function calculate_health_score($results) {
        $weights = [
            'database' => 0.25,
            'services' => 0.25,
            'filesystem' => 0.15,
            'memory' => 0.15,
            'database_performance' => 0.10,
            'external_apis' => 0.10
        ];
        
        $total_score = 0;
        $total_weight = 0;
        
        foreach ($weights as $category => $weight) {
            if (isset($results[$category])) {
                $score = 0;
                
                if (isset($results[$category]['score'])) {
                    $score = $results[$category]['score'];
                } elseif (isset($results[$category]['average_score'])) {
                    $score = $results[$category]['average_score'];
                }
                
                $total_score += $score * $weight;
                $total_weight += $weight;
            }
        }
        
        return $total_weight > 0 ? $total_score / $total_weight : 0;
    }

    /**
     * 获取健康状态
     *
     * @param float $score 健康评分
     * @return string 健康状态
     */
    private function get_health_status($score) {
        if ($score >= 90) {
            return 'excellent';
        } elseif ($score >= 80) {
            return 'good';
        } elseif ($score >= 70) {
            return 'fair';
        } elseif ($score >= 60) {
            return 'poor';
        } else {
            return 'critical';
        }
    }

    /**
     * 获取系统统计信息
     *
     * @return array 系统统计信息
     */
    public function get_system_stats() {
        global $wpdb;
        
        try {
            $stats = [];
            
            // 用户统计
            $stats['users'] = [
                'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_users"),
                'active' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_users WHERE status = 'active'")
            ];
            
            // 文章统计
            $stats['articles'] = [
                'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_articles"),
                'published' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_articles WHERE status = 'published'"),
                'draft' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_articles WHERE status = 'draft'"),
                'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_articles WHERE status = 'pending'")
            ];
            
            // 站点统计
            $stats['sites'] = [
                'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_sites"),
                'active' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_sites WHERE status = 'active'")
            ];
            
            // 敏感词统计
            $stats['sensitive_words'] = [
                'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_sensitive_words"),
                'high_risk' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_sensitive_words WHERE type = 'high_risk'"),
                'normal' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_sensitive_words WHERE type = 'normal'")
            ];
            
            // 审计日志统计
            $stats['audit_logs'] = [
                'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_audit_logs"),
                'today' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_audit_logs WHERE DATE(created_at) = CURDATE()"),
                'this_week' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ams_v2_audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")
            ];
            
            return $stats;
            
        } catch (Exception $e) {
            $this->error_handler->handle_error($e);
            return [];
        }
    }

    /**
     * 转换内存大小为字节
     *
     * @param string $size 内存大小字符串
     * @return int 字节数
     */
    private function convert_to_bytes($size) {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int) $size;
        
        switch ($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }
        
        return $size;
    }

    /**
     * 格式化字节数为可读格式
     *
     * @param int $bytes 字节数
     * @return string 格式化后的大小
     */
    private function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 记录系统监控日志
     *
     * @param array $health_data 健康检查数据
     */
    public function log_health_check($health_data) {
        try {
            $this->logger->info('System health check completed', [
                'overall_score' => $health_data['overall_score'],
                'status' => $health_data['status'],
                'timestamp' => current_time('mysql')
            ]);
        } catch (Exception $e) {
            $this->error_handler->handle_error($e);
        }
    }
}