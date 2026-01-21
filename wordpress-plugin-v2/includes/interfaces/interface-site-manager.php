<?php
/**
 * 站点管理器接口
 *
 * @package Article_Management_V2
 * @subpackage Interfaces
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 站点管理器接口
 */
interface AMS_V2_Site_Manager_Interface {

    /**
     * 创建站点
     *
     * @param array $site_data 站点数据
     * @return AMS_V2_Site|WP_Error 创建的站点对象或错误
     */
    public function create_site($site_data);

    /**
     * 获取站点
     *
     * @param int $site_id 站点ID
     * @return AMS_V2_Site|null 站点对象或null
     */
    public function get_site($site_id);

    /**
     * 获取所有站点
     *
     * @param array $args 查询参数
     * @return array 站点数组
     */
    public function get_sites($args = array());

    /**
     * 更新站点
     *
     * @param AMS_V2_Site $site 站点对象
     * @return bool|WP_Error 更新结果
     */
    public function update_site($site);

    /**
     * 删除站点
     *
     * @param int $site_id 站点ID
     * @return bool|WP_Error 删除结果
     */
    public function delete_site($site_id);

    /**
     * 测试站点连接
     *
     * @param AMS_V2_Site $site 站点对象
     * @return bool|WP_Error 连接测试结果
     */
    public function test_site_connection($site);
}