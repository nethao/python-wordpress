<?php
/**
 * WordPress桥接器接口
 *
 * @package Article_Management_V2
 * @subpackage Interfaces
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress桥接器接口
 */
interface AMS_V2_WordPress_Bridge_Interface {

    /**
     * 测试站点连接
     *
     * @param AMS_V2_Site $site 站点对象
     * @return array 连接测试结果
     */
    public function test_connection($site);

    /**
     * 认证到WordPress站点
     *
     * @param AMS_V2_Site $site 站点对象
     * @return array 认证结果
     */
    public function authenticate($site);

    /**
     * 创建文章
     *
     * @param AMS_V2_Site $site 站点对象
     * @param array $post_data 文章数据
     * @return array 创建结果
     */
    public function create_post($site, $post_data);

    /**
     * 更新文章
     *
     * @param AMS_V2_Site $site 站点对象
     * @param int $post_id WordPress文章ID
     * @param array $post_data 文章数据
     * @return array 更新结果
     */
    public function update_post($site, $post_id, $post_data);

    /**
     * 删除文章
     *
     * @param AMS_V2_Site $site 站点对象
     * @param int $post_id WordPress文章ID
     * @return array 删除结果
     */
    public function delete_post($site, $post_id);

    /**
     * 获取文章信息
     *
     * @param AMS_V2_Site $site 站点对象
     * @param int $post_id WordPress文章ID
     * @return array 文章信息
     */
    public function get_post($site, $post_id);

    /**
     * 获取站点分类列表
     *
     * @param AMS_V2_Site $site 站点对象
     * @return array 分类列表
     */
    public function get_categories($site);

    /**
     * 获取站点标签列表
     *
     * @param AMS_V2_Site $site 站点对象
     * @return array 标签列表
     */
    public function get_tags($site);

    /**
     * 获取站点信息
     *
     * @param AMS_V2_Site $site 站点对象
     * @return array 站点信息
     */
    public function get_site_info($site);

    /**
     * 检查API可用性
     *
     * @param AMS_V2_Site $site 站点对象
     * @return array 可用性检查结果
     */
    public function check_api_availability($site);

    /**
     * 验证用户权限
     *
     * @param AMS_V2_Site $site 站点对象
     * @param string $capability 权限名称
     * @return array 权限验证结果
     */
    public function verify_user_capability($site, $capability);
}