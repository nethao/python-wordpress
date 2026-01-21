<?php
/**
 * 统计收集器接口
 *
 * @package Article_Management_V2
 * @subpackage Interfaces
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 统计收集器接口
 */
interface AMS_V2_Statistics_Collector_Interface {

    /**
     * 收集用户统计数据
     *
     * @param int $user_id 用户ID，null表示所有用户
     * @param string $period 统计周期 (daily|weekly|monthly|yearly)
     * @param string $date_from 开始日期
     * @param string $date_to 结束日期
     * @return array 统计数据
     */
    public function collect_user_statistics($user_id = null, $period = 'daily', $date_from = null, $date_to = null);

    /**
     * 收集系统统计数据
     *
     * @param string $period 统计周期
     * @param string $date_from 开始日期
     * @param string $date_to 结束日期
     * @return array 统计数据
     */
    public function collect_system_statistics($period = 'daily', $date_from = null, $date_to = null);

    /**
     * 获取用户发布量统计
     *
     * @param int $user_id 用户ID
     * @param string $period 统计周期
     * @param int $limit 限制数量
     * @return array 发布量统计
     */
    public function get_user_publish_stats($user_id, $period = 'daily', $limit = 30);

    /**
     * 获取内容审核统计
     *
     * @param string $period 统计周期
     * @param string $date_from 开始日期
     * @param string $date_to 结束日期
     * @return array 审核统计
     */
    public function get_moderation_statistics($period = 'daily', $date_from = null, $date_to = null);

    /**
     * 获取敏感词拦截统计
     *
     * @param string $period 统计周期
     * @param string $date_from 开始日期
     * @param string $date_to 结束日期
     * @return array 拦截统计
     */
    public function get_sensitive_word_statistics($period = 'daily', $date_from = null, $date_to = null);

    /**
     * 获取站点发布统计
     *
     * @param int $site_id 站点ID，null表示所有站点
     * @param string $period 统计周期
     * @param string $date_from 开始日期
     * @param string $date_to 结束日期
     * @return array 站点统计
     */
    public function get_site_publish_statistics($site_id = null, $period = 'daily', $date_from = null, $date_to = null);

    /**
     * 获取实时统计数据
     *
     * @return array 实时统计
     */
    public function get_realtime_statistics();

    /**
     * 导出统计数据为CSV
     *
     * @param array $data 统计数据
     * @param string $filename 文件名
     * @return bool 导出结果
     */
    public function export_statistics_csv($data, $filename = null);

    /**
     * 更新统计缓存
     *
     * @param string $cache_key 缓存键
     * @param array $data 数据
     * @param int $expiration 过期时间（秒）
     * @return bool 更新结果
     */
    public function update_statistics_cache($cache_key, $data, $expiration = 3600);

    /**
     * 获取统计缓存
     *
     * @param string $cache_key 缓存键
     * @return mixed 缓存数据或false
     */
    public function get_statistics_cache($cache_key);

    /**
     * 清除统计缓存
     *
     * @param string $pattern 缓存键模式
     * @return bool 清除结果
     */
    public function clear_statistics_cache($pattern = null);
}