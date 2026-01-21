<?php
/**
 * API管理器接口
 *
 * @package Article_Management_V2
 * @subpackage Interfaces
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API管理器接口
 */
interface AMS_V2_API_Manager_Interface {

    /**
     * 保存API密钥
     *
     * @param string $service_name 服务名称
     * @param string $api_key API密钥
     * @param array $config 配置信息
     * @return bool|WP_Error 保存结果
     */
    public function save_api_key($service_name, $api_key, $config = array());

    /**
     * 获取API密钥
     *
     * @param string $service_name 服务名称
     * @return string|null 解密后的API密钥
     */
    public function get_api_key($service_name);

    /**
     * 获取API配置
     *
     * @param string $service_name 服务名称
     * @return array API配置
     */
    public function get_api_config($service_name);

    /**
     * 删除API密钥
     *
     * @param string $service_name 服务名称
     * @return bool|WP_Error 删除结果
     */
    public function delete_api_key($service_name);

    /**
     * 验证API密钥
     *
     * @param string $service_name 服务名称
     * @return bool|WP_Error 验证结果
     */
    public function validate_api_key($service_name);

    /**
     * 获取所有API服务状态
     *
     * @return array 服务状态数组
     */
    public function get_all_services_status();
}