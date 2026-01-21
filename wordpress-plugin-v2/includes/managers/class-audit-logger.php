<?php
/**
 * 审计日志记录器实现类
 *
 * @package Article_Management_V2
 * @subpackage Managers
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 审计日志记录器实现类
 */
class AMS_V2_Audit_Logger implements AMS_V2_Audit_Logger_Interface {

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
     * 记录审计日志
     *
     * @param string $action 操作类型
     * @param int $user_id 操作用户ID
     * @param string $resource_type 资源类型
     * @param int $resource_id 资源ID
     * @param array $old_values 旧值
     * @param array $new_values 新值
     * @param string $ip_address IP地址
     * @param string $user_agent 用户代理
     * @return bool|WP_Error 记录结果
     */
    public function log($action, $user_id, $resource_type = null, $resource_id = null, $old_values = null, $new_values = null, $ip_address = null, $user_agent = null) {
        try {
            // 自动获取IP地址和用户代理
            if (!$ip_address) {
                $ip_address = $this->get_client_ip();
            }
            if (!$user_agent) {
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            }

            $audit_log = new AMS_V2_Audit_Log([
                'action' => $action,
                'user_id' => $user_id,
                'object_type' => $resource_type, // 内部使用object_type，映射到数据库的resource_type
                'object_id' => $resource_id,
                'old_values' => $old_values,
                'new_values' => $new_values,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent
            ]);

            $validation = $audit_log->validate();
            if (is_wp_error($validation)) {
                return $validation;
            }

            $table_name = $this->db_manager->get_table_name('audit_logs');
            
            $result = $this->wpdb->insert(
                $table_name,
                [
                    'action' => $audit_log->get_action(),
                    'user_id' => $audit_log->get_user_id(),
                    'resource_type' => $audit_log->get_object_type(), // 映射到数据库字段
                    'resource_id' => $audit_log->get_object_id(),
                    'old_values' => $audit_log->get_old_values() ? wp_json_encode($audit_log->get_old_values()) : null,
                    'new_values' => $audit_log->get_new_values() ? wp_json_encode($audit_log->get_new_values()) : null,
                    'ip_address' => $audit_log->get_ip_address(),
                    'user_agent' => $audit_log->get_user_agent(),
                    'created_at' => current_time('mysql')
                ],
                [
                    '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s'
                ]
            );

            if ($result === false) {
                $error_message = $this->wpdb->last_error ?: '数据库插入失败';
                $this->logger->error('审计日志记录失败', [
                    'action' => $action,
                    'user_id' => $user_id,
                    'error' => $error_message
                ]);
                return new WP_Error('db_insert_failed', $error_message);
            }

            $audit_log->set_id($this->wpdb->insert_id);
            return true;

        } catch (Exception $e) {
            $this->logger->error('审计日志记录异常', [
                'action' => $action,
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
            return new WP_Error('audit_log_exception', $e->getMessage());
        }
    }

    /**
     * 记录用户权限变更
     *
     * @param int $user_id 用户ID
     * @param int $operator_id 操作者ID
     * @param string $action 操作类型
     * @param array $old_data 旧数据
     * @param array $new_data 新数据
     * @return bool|WP_Error 记录结果
     */
    public function log_user_permission_change($user_id, $operator_id, $action, $old_data = null, $new_data = null) {
        $action_map = [
            'create' => 'user_create',
            'update' => 'user_update',
            'delete' => 'user_delete',
            'role_change' => 'user_role_change'
        ];

        $audit_action = $action_map[$action] ?? 'user_' . $action;

        return $this->log(
            $audit_action,
            $operator_id,
            'user',
            $user_id,
            $old_data,
            $new_data
        );
    }

    /**
     * 记录敏感词库变更
     *
     * @param int $word_id 敏感词ID
     * @param int $operator_id 操作者ID
     * @param string $action 操作类型
     * @param array $old_data 旧数据
     * @param array $new_data 新数据
     * @return bool|WP_Error 记录结果
     */
    public function log_sensitive_word_change($word_id, $operator_id, $action, $old_data = null, $new_data = null) {
        return $this->log(
            'sensitive_word_' . $action,
            $operator_id,
            'sensitive_word',
            $word_id,
            $old_data,
            $new_data
        );
    }

    /**
     * 记录审核等级变更
     *
     * @param int $operator_id 操作者ID
     * @param string $old_level 旧等级
     * @param string $new_level 新等级
     * @param array $config_changes 配置变更详情
     * @return bool|WP_Error 记录结果
     */
    public function log_moderation_level_change($operator_id, $old_level, $new_level, $config_changes = null) {
        $old_values = ['level' => $old_level];
        $new_values = ['level' => $new_level];
        
        if ($config_changes) {
            $new_values['config_changes'] = $config_changes;
        }

        return $this->log(
            'moderation_level_change',
            $operator_id,
            'moderation_config',
            null,
            $old_values,
            $new_values
        );
    }

    /**
     * 记录清理操作
     *
     * @param int $operator_id 操作者ID
     * @param string $cleanup_type 清理类型
     * @param int $articles_deleted 删除文章数
     * @param array $cleanup_details 清理详情
     * @return bool|WP_Error 记录结果
     */
    public function log_cleanup_operation($operator_id, $cleanup_type, $articles_deleted, $cleanup_details = null) {
        $new_values = [
            'cleanup_type' => $cleanup_type,
            'articles_deleted' => $articles_deleted
        ];
        
        if ($cleanup_details) {
            $new_values['details'] = $cleanup_details;
        }

        return $this->log(
            'cleanup_' . $cleanup_type,
            $operator_id,
            'cleanup_operation',
            null,
            null,
            $new_values
        );
    }

    /**
     * 记录系统异常
     *
     * @param string $error_type 错误类型
     * @param string $error_message 错误消息
     * @param string $error_context 错误上下文
     * @param array $stack_trace 堆栈跟踪
     * @return bool|WP_Error 记录结果
     */
    public function log_system_error($error_type, $error_message, $error_context = null, $stack_trace = null) {
        $new_values = [
            'error_type' => $error_type,
            'error_message' => $error_message
        ];
        
        if ($error_context) {
            $new_values['error_context'] = $error_context;
        }
        
        if ($stack_trace) {
            $new_values['stack_trace'] = $stack_trace;
        }

        return $this->log(
            'system_error',
            0, // 系统错误没有特定用户
            'system',
            null,
            null,
            $new_values
        );
    }

    /**
     * 记录API操作
     *
     * @param int $user_id 用户ID
     * @param string $api_action API操作
     * @param string $endpoint 端点
     * @param array $request_data 请求数据
     * @param array $response_data 响应数据
     * @param bool $success 是否成功
     * @return bool|WP_Error 记录结果
     */
    public function log_api_operation($user_id, $api_action, $endpoint, $request_data = null, $response_data = null, $success = true) {
        $new_values = [
            'api_action' => $api_action,
            'endpoint' => $endpoint,
            'success' => $success
        ];
        
        if ($request_data) {
            $new_values['request_data'] = $request_data;
        }
        
        if ($response_data) {
            $new_values['response_data'] = $response_data;
        }

        return $this->log(
            'api_call',
            $user_id,
            'api',
            null,
            null,
            $new_values
        );
    }

    /**
     * 获取审计日志
     *
     * @param array $filters 过滤条件
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 审计日志列表
     */
    public function get_audit_logs($filters = [], $limit = 50, $offset = 0) {
        try {
            $table_name = $this->db_manager->get_table_name('audit_logs');
            
            $where_conditions = ['1=1'];
            $where_values = [];

            // 应用过滤条件
            if (!empty($filters['action'])) {
                $where_conditions[] = 'action = %s';
                $where_values[] = $filters['action'];
            }

            if (!empty($filters['user_id'])) {
                $where_conditions[] = 'user_id = %d';
                $where_values[] = $filters['user_id'];
            }

            if (!empty($filters['object_type'])) {
                $where_conditions[] = 'resource_type = %s';
                $where_values[] = $filters['object_type'];
            }

            if (!empty($filters['date_from'])) {
                $where_conditions[] = 'DATE(created_at) >= %s';
                $where_values[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where_conditions[] = 'DATE(created_at) <= %s';
                $where_values[] = $filters['date_to'];
            }

            $where_clause = implode(' AND ', $where_conditions);
            
            $sql = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
            $where_values[] = $limit;
            $where_values[] = $offset;

            if (!empty($where_values)) {
                $sql = $this->wpdb->prepare($sql, $where_values);
            }

            $results = $this->wpdb->get_results($sql);
            
            $audit_logs = [];
            foreach ($results as $row) {
                $audit_logs[] = AMS_V2_Audit_Log::from_db_row($row);
            }

            return $audit_logs;

        } catch (Exception $e) {
            $this->logger->error('获取审计日志失败', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 搜索审计日志
     *
     * @param string $search_term 搜索词
     * @param array $filters 过滤条件
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 搜索结果
     */
    public function search_audit_logs($search_term, $filters = [], $limit = 50, $offset = 0) {
        try {
            $table_name = $this->db_manager->get_table_name('audit_logs');
            
            $where_conditions = ['1=1'];
            $where_values = [];

            // 搜索条件
            if (!empty($search_term)) {
                $where_conditions[] = '(action LIKE %s OR old_values LIKE %s OR new_values LIKE %s OR ip_address LIKE %s)';
                $search_pattern = '%' . $this->wpdb->esc_like($search_term) . '%';
                $where_values[] = $search_pattern;
                $where_values[] = $search_pattern;
                $where_values[] = $search_pattern;
                $where_values[] = $search_pattern;
            }

            // 应用其他过滤条件
            if (!empty($filters['action'])) {
                $where_conditions[] = 'action = %s';
                $where_values[] = $filters['action'];
            }

            if (!empty($filters['user_id'])) {
                $where_conditions[] = 'user_id = %d';
                $where_values[] = $filters['user_id'];
            }

            $where_clause = implode(' AND ', $where_conditions);
            
            $sql = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
            $where_values[] = $limit;
            $where_values[] = $offset;

            if (!empty($where_values)) {
                $sql = $this->wpdb->prepare($sql, $where_values);
            }

            $results = $this->wpdb->get_results($sql);
            
            $audit_logs = [];
            foreach ($results as $row) {
                $audit_logs[] = AMS_V2_Audit_Log::from_db_row($row);
            }

            return $audit_logs;

        } catch (Exception $e) {
            $this->logger->error('搜索审计日志失败', [
                'search_term' => $search_term,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取审计日志统计
     *
     * @param string $period 统计周期
     * @param string $date_from 开始日期
     * @param string $date_to 结束日期
     * @return array 统计数据
     */
    public function get_audit_statistics($period = 'daily', $date_from = null, $date_to = null) {
        try {
            if (!$date_from) {
                $date_from = date('Y-m-d', strtotime('-30 days'));
            }
            if (!$date_to) {
                $date_to = date('Y-m-d');
            }

            $table_name = $this->db_manager->get_table_name('audit_logs');
            $date_format = $this->get_date_format_for_period($period);
            
            $sql = "SELECT 
                        {$date_format} as date,
                        COUNT(*) as total_logs,
                        COUNT(DISTINCT user_id) as active_users,
                        COUNT(CASE WHEN action LIKE 'user_%' THEN 1 END) as user_operations,
                        COUNT(CASE WHEN action LIKE 'sensitive_word_%' THEN 1 END) as word_operations,
                        COUNT(CASE WHEN action = 'moderation_level_change' THEN 1 END) as moderation_changes,
                        COUNT(CASE WHEN action LIKE 'cleanup_%' THEN 1 END) as cleanup_operations,
                        COUNT(CASE WHEN action = 'system_error' THEN 1 END) as system_errors,
                        COUNT(CASE WHEN action = 'api_call' THEN 1 END) as api_calls
                    FROM {$table_name}
                    WHERE DATE(created_at) >= %s AND DATE(created_at) <= %s
                    GROUP BY {$date_format}
                    ORDER BY date DESC";

            $results = $this->wpdb->get_results(
                $this->wpdb->prepare($sql, $date_from, $date_to)
            );

            $statistics = [];
            foreach ($results as $row) {
                $statistics[] = [
                    'date' => $row->date,
                    'total_logs' => (int) $row->total_logs,
                    'active_users' => (int) $row->active_users,
                    'user_operations' => (int) $row->user_operations,
                    'word_operations' => (int) $row->word_operations,
                    'moderation_changes' => (int) $row->moderation_changes,
                    'cleanup_operations' => (int) $row->cleanup_operations,
                    'system_errors' => (int) $row->system_errors,
                    'api_calls' => (int) $row->api_calls
                ];
            }

            return $statistics;

        } catch (Exception $e) {
            $this->logger->error('获取审计日志统计失败', [
                'period' => $period,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 清理过期日志
     *
     * @param int $retention_days 保留天数
     * @return int 清理的日志数量
     */
    public function cleanup_old_logs($retention_days = 90) {
        try {
            $table_name = $this->db_manager->get_table_name('audit_logs');
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
            
            $result = $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM {$table_name} WHERE created_at < %s",
                    $cutoff_date
                )
            );

            if ($result !== false && $result > 0) {
                $this->logger->info('清理过期审计日志', [
                    'retention_days' => $retention_days,
                    'deleted_count' => $result
                ]);
            }

            return $result !== false ? $result : 0;

        } catch (Exception $e) {
            $this->logger->error('清理过期审计日志失败', [
                'retention_days' => $retention_days,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 导出审计日志
     *
     * @param array $filters 过滤条件
     * @param string $format 导出格式
     * @param string $filename 文件名
     * @return bool|WP_Error 导出结果
     */
    public function export_audit_logs($filters = [], $format = 'csv', $filename = null) {
        try {
            $logs = $this->get_audit_logs($filters, 10000, 0); // 最多导出10000条
            
            if (empty($logs)) {
                return new WP_Error('no_data', '没有数据可导出');
            }

            if (!$filename) {
                $filename = 'audit_logs_' . date('Y-m-d_H-i-s') . '.' . $format;
            }

            if ($format === 'csv') {
                return $this->export_csv($logs, $filename);
            } elseif ($format === 'json') {
                return $this->export_json($logs, $filename);
            } else {
                return new WP_Error('invalid_format', '不支持的导出格式');
            }

        } catch (Exception $e) {
            $this->logger->error('导出审计日志失败', [
                'format' => $format,
                'error' => $e->getMessage()
            ]);
            return new WP_Error('export_failed', $e->getMessage());
        }
    }

    /**
     * 导出CSV格式
     *
     * @param array $logs 日志数据
     * @param string $filename 文件名
     * @return bool
     */
    private function export_csv($logs, $filename) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        
        // 添加BOM以支持中文
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // 写入标题行
        fputcsv($output, [
            'ID', '操作类型', '操作描述', '用户ID', '用户名', '对象类型', '对象ID', 
            '旧值', '新值', 'IP地址', '用户代理', '创建时间'
        ]);

        // 写入数据行
        foreach ($logs as $log) {
            $log_array = $log->to_array();
            $user_info = $log_array['user_info'];
            
            fputcsv($output, [
                $log_array['id'],
                $log_array['action'],
                $log_array['action_description'],
                $log_array['user_id'],
                $user_info ? $user_info['username'] : '',
                $log_array['object_type'],
                $log_array['object_id'],
                $log_array['old_values'] ? wp_json_encode($log_array['old_values']) : '',
                $log_array['new_values'] ? wp_json_encode($log_array['new_values']) : '',
                $log_array['ip_address'],
                $log_array['user_agent'],
                $log_array['created_at']
            ]);
        }

        fclose($output);
        return true;
    }

    /**
     * 导出JSON格式
     *
     * @param array $logs 日志数据
     * @param string $filename 文件名
     * @return bool
     */
    private function export_json($logs, $filename) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $data = [];
        foreach ($logs as $log) {
            $data[] = $log->to_array();
        }

        echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return true;
    }

    /**
     * 获取客户端IP地址
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // 处理多个IP的情况（X-Forwarded-For可能包含多个IP）
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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