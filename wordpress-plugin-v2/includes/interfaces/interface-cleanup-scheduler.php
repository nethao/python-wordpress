<?php
/**
 * 清理调度器接口
 *
 * @package Article_Management_V2
 * @subpackage Interfaces
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 清理调度器接口
 */
interface AMS_V2_Cleanup_Scheduler_Interface {

    /**
     * 设置清理阈值天数
     *
     * @param int $days 天数
     * @return bool
     */
    public function set_threshold_days($days);

    /**
     * 获取清理阈值天数
     *
     * @return int
     */
    public function get_threshold_days();

    /**
     * 启用自动清理
     *
     * @return bool
     */
    public function enable_auto_cleanup();

    /**
     * 禁用自动清理
     *
     * @return bool
     */
    public function disable_auto_cleanup();

    /**
     * 检查自动清理是否启用
     *
     * @return bool
     */
    public function is_auto_cleanup_enabled();

    /**
     * 识别需要清理的文章
     *
     * @param int $threshold_days 阈值天数
     * @return array 文章ID数组
     */
    public function identify_articles_for_cleanup($threshold_days = null);

    /**
     * 执行清理操作
     *
     * @param array $article_ids 要清理的文章ID数组
     * @param AMS_V2_User $triggered_by 触发清理的用户
     * @param string $cleanup_type 清理类型 (auto|manual)
     * @return array 清理结果
     */
    public function execute_cleanup($article_ids, $triggered_by = null, $cleanup_type = 'auto');

    /**
     * 手动触发清理
     *
     * @param AMS_V2_User $user 触发用户
     * @param int $threshold_days 可选的阈值天数
     * @return array 清理结果
     */
    public function trigger_manual_cleanup($user, $threshold_days = null);

    /**
     * 获取清理日志
     *
     * @param array $filters 过滤条件
     * @return array
     */
    public function get_cleanup_logs($filters = array());

    /**
     * 获取清理统计信息
     *
     * @param int $days 统计天数
     * @return array
     */
    public function get_cleanup_statistics($days = 30);

    /**
     * 调度定时清理任务
     *
     * @return bool
     */
    public function schedule_cleanup_task();

    /**
     * 取消定时清理任务
     *
     * @return bool
     */
    public function unschedule_cleanup_task();
}