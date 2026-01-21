<?php
/**
 * 审计日志记录器接口
 *
 * @package Article_Management_V2
 * @subpackage Interfaces
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 审计日志记录器接口
 */
interface AMS_V2_Audit_Logger_Interface {

    /**
     * 记录审计日志
     *
     * @param string $action 操作类型
     * @param int $user_id 操作用户ID
     * @param string $object_type 对象类型
     * @param int $object_id 对象ID
     * @param array $old_values 旧值
     * @param array $new_values 新值
     * @param string $ip_address IP地址
     * @param string $user_agent 用户代理
     * @return bool|WP_Error 记录结果
     */
    public function log($action, $user_id, $object_type = null, $object_id = null, $old_values = null, $new_values = null, $ip_address = null, $user_agent = null);

    /**
     * 记录用户权限变更
     *
     * @param int $user_id 用户ID
     * @param int $operator_id 操作者ID
     * @param string $action 操作类型 (create|update|delete|role_change)
     * @param array $old_data 旧数据
     * @param array $new_data 新数据
     * @return bool|WP_Error 记录结果
     */
    public function log_user_permission_change($user_id, $operator_id, $action, $old_data = null, $new_data = null);

    /**
     * 记录敏感词库变更
     *
     * @param int $word_id 敏感词ID
     * @param int $operator_id 操作者ID
     * @param string $action 操作类型 (create|update|delete|import|export)
     * @param array $old_data 旧数据
     * @param array $new_data 新数据
     * @return bool|WP_Error 记录结果
     */
    public function log_sensitive_word_change($word_id, $operator_id, $action, $old_data = null, $new_data = null);

    /**
     * 记录审核等级变更
     *
     * @param int $operator_id 操作者ID
     * @param string $old_level 旧等级
     * @param string $new_level 新等级
     * @param array $config_changes 配置变更详情
     * @return bool|WP_Error 记录结果
     */
    public function log_moderation_level_change($operator_id, $old_level, $new_level, $config_changes = null);

    /**
     * 记录清理操作
     *
     * @param int $operator_id 操作者ID
     * @param string $cleanup_type 清理类型 (auto|manual)
     * @param int $articles_deleted 删除文章数
     * @param array $cleanup_details 清理详情
     * @return bool|WP_Error 记录结果
     */
    public function log_cleanup_operation($operator_id, $cleanup_type, $articles_deleted, $cleanup_details = null);

    /**
     * 记录系统异常
     *
     * @param string $error_type 错误类型
     * @param string $error_message 错误消息
     * @param string $error_context 错误上下文
     * @param array $stack_trace 堆栈跟踪
     * @return bool|WP_Error 记录结果
     */
    public function log_system_error($error_type, $error_message, $error_context = null, $stack_trace = null);

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
    public function log_api_operation($user_id, $api_action, $endpoint, $request_data = null, $response_data = null, $success = true);

    /**
     * 获取审计日志
     *
     * @param array $filters 过滤条件
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 审计日志列表
     */
    public function get_audit_logs($filters = [], $limit = 50, $offset = 0);

    /**
     * 搜索审计日志
     *
     * @param string $search_term 搜索词
     * @param array $filters 过滤条件
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 搜索结果
     */
    public function search_audit_logs($search_term, $filters = [], $limit = 50, $offset = 0);

    /**
     * 获取审计日志统计
     *
     * @param string $period 统计周期
     * @param string $date_from 开始日期
     * @param string $date_to 结束日期
     * @return array 统计数据
     */
    public function get_audit_statistics($period = 'daily', $date_from = null, $date_to = null);

    /**
     * 清理过期日志
     *
     * @param int $retention_days 保留天数
     * @return int 清理的日志数量
     */
    public function cleanup_old_logs($retention_days = 90);

    /**
     * 导出审计日志
     *
     * @param array $filters 过滤条件
     * @param string $format 导出格式 (csv|json)
     * @param string $filename 文件名
     * @return bool|WP_Error 导出结果
     */
    public function export_audit_logs($filters = [], $format = 'csv', $filename = null);
}