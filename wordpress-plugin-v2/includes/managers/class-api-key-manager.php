<?php
/**
 * API密钥管理器实现类
 *
 * @package Article_Management_V2
 * @subpackage Managers
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API密钥管理器实现类
 */
class AMS_V2_API_Key_Manager implements AMS_V2_API_Manager_Interface {

    /**
     * 数据库管理器
     *
     * @var AMS_V2_Database_Manager
     */
    private $db_manager;

    /**
     * 加密服务
     *
     * @var AMS_V2_Encryption_Service
     */
    private $encryption_service;

    /**
     * 日志记录器
     *
     * @var AMS_V2_Logger
     */
    private $logger;

    /**
     * 构造函数
     *
     * @param AMS_V2_Database_Manager $db_manager 数据库管理器
     * @param AMS_V2_Encryption_Service $encryption_service 加密服务
     * @param AMS_V2_Logger $logger 日志记录器
     */
    public function __construct($db_manager, $encryption_service, $logger) {
        $this->db_manager = $db_manager;
        $this->encryption_service = $encryption_service;
        $this->logger = $logger;
    }

    /**
     * 保存API密钥
     *
     * @param string $service_name 服务名称
     * @param string $api_key API密钥
     * @param array $config 配置信息
     * @return bool|WP_Error 保存结果
     */
    public function save_api_key($service_name, $api_key, $config = array()) {
        try {
            if (empty($service_name) || empty($api_key)) {
                return new WP_Error('invalid_params', '服务名称和API密钥不能为空');
            }

            // 加密API密钥
            $encrypted_key = $this->encryption_service->encrypt($api_key);
            if ($encrypted_key === false) {
                return new WP_Error('encryption_failed', 'API密钥加密失败');
            }

            global $wpdb;
            $table_name = $this->db_manager->get_table_name('api_keys');

            // 检查是否已存在
            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$table_name} WHERE service_name = %s",
                    $service_name
                ),
                ARRAY_A
            );

            $data = array(
                'service_name' => sanitize_text_field($service_name),
                'encrypted_key' => $encrypted_key,
                'config' => json_encode($config),
                'is_active' => 1,
                'updated_at' => current_time('mysql')
            );

            if ($existing) {
                // 更新现有记录
                $result = $wpdb->update(
                    $table_name,
                    $data,
                    array('service_name' => $service_name)
                );
            } else {
                // 插入新记录
                $data['created_at'] = current_time('mysql');
                $result = $wpdb->insert($table_name, $data);
            }

            if ($result === false) {
                return new WP_Error('database_error', '数据库操作失败: ' . $wpdb->last_error);
            }

            $this->logger->info('API密钥保存成功', array(
                'service_name' => $service_name,
                'action' => $existing ? 'update' : 'create'
            ));

            return true;

        } catch (Exception $e) {
            $this->logger->error('保存API密钥异常', array(
                'service_name' => $service_name,
                'error' => $e->getMessage()
            ));
            return new WP_Error('exception', '保存API密钥时发生异常: ' . $e->getMessage());
        }
    }

    /**
     * 获取API密钥
     *
     * @param string $service_name 服务名称
     * @return string|null 解密后的API密钥
     */
    public function get_api_key($service_name) {
        try {
            global $wpdb;
            $table_name = $this->db_manager->get_table_name('api_keys');

            $result = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT encrypted_key FROM {$table_name} WHERE service_name = %s AND is_active = 1",
                    $service_name
                ),
                ARRAY_A
            );

            if (!$result) {
                return null;
            }

            // 解密API密钥
            $decrypted_key = $this->encryption_service->decrypt($result['encrypted_key']);
            if ($decrypted_key === false) {
                $this->logger->error('API密钥解密失败', array('service_name' => $service_name));
                return null;
            }

            return $decrypted_key;

        } catch (Exception $e) {
            $this->logger->error('获取API密钥异常', array(
                'service_name' => $service_name,
                'error' => $e->getMessage()
            ));
            return null;
        }
    }

    /**
     * 获取API配置
     *
     * @param string $service_name 服务名称
     * @return array API配置
     */
    public function get_api_config($service_name) {
        try {
            global $wpdb;
            $table_name = $this->db_manager->get_table_name('api_keys');

            $result = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT config FROM {$table_name} WHERE service_name = %s AND is_active = 1",
                    $service_name
                ),
                ARRAY_A
            );

            if (!$result || empty($result['config'])) {
                return array();
            }

            $config = json_decode($result['config'], true);
            return is_array($config) ? $config : array();

        } catch (Exception $e) {
            $this->logger->error('获取API配置异常', array(
                'service_name' => $service_name,
                'error' => $e->getMessage()
            ));
            return array();
        }
    }

    /**
     * 删除API密钥
     *
     * @param string $service_name 服务名称
     * @return bool|WP_Error 删除结果
     */
    public function delete_api_key($service_name) {
        try {
            global $wpdb;
            $table_name = $this->db_manager->get_table_name('api_keys');

            $result = $wpdb->delete(
                $table_name,
                array('service_name' => $service_name),
                array('%s')
            );

            if ($result === false) {
                return new WP_Error('database_error', '数据库删除失败: ' . $wpdb->last_error);
            }

            $this->logger->info('API密钥删除成功', array('service_name' => $service_name));
            return true;

        } catch (Exception $e) {
            $this->logger->error('删除API密钥异常', array(
                'service_name' => $service_name,
                'error' => $e->getMessage()
            ));
            return new WP_Error('exception', '删除API密钥时发生异常: ' . $e->getMessage());
        }
    }

    /**
     * 验证API密钥
     *
     * @param string $service_name 服务名称
     * @return bool|WP_Error 验证结果
     */
    public function validate_api_key($service_name) {
        $api_key = $this->get_api_key($service_name);
        if (!$api_key) {
            return new WP_Error('no_api_key', 'API密钥不存在');
        }

        // 根据服务类型进行验证
        switch ($service_name) {
            case 'deepseek':
                return $this->validate_deepseek_api_key($api_key);
            default:
                return new WP_Error('unsupported_service', '不支持的服务类型');
        }
    }

    /**
     * 验证DeepSeek API密钥
     *
     * @param string $api_key API密钥
     * @return bool|WP_Error 验证结果
     */
    private function validate_deepseek_api_key($api_key) {
        try {
            // 调用DeepSeek API进行验证
            $response = wp_remote_get('https://api.deepseek.com/v1/models', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 10
            ));

            if (is_wp_error($response)) {
                return new WP_Error('api_request_failed', 'API请求失败: ' . $response->get_error_message());
            }

            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code === 200) {
                return true;
            } elseif ($status_code === 401) {
                return new WP_Error('invalid_api_key', 'API密钥无效');
            } else {
                return new WP_Error('api_error', 'API验证失败，状态码: ' . $status_code);
            }

        } catch (Exception $e) {
            return new WP_Error('exception', 'API验证异常: ' . $e->getMessage());
        }
    }

    /**
     * 获取所有API服务状态
     *
     * @return array 服务状态数组
     */
    public function get_all_services_status() {
        try {
            global $wpdb;
            $table_name = $this->db_manager->get_table_name('api_keys');

            $results = $wpdb->get_results(
                "SELECT service_name, is_active, created_at, updated_at FROM {$table_name}",
                ARRAY_A
            );

            $services = array();
            foreach ($results as $row) {
                $validation_result = $this->validate_api_key($row['service_name']);
                $services[] = array(
                    'service_name' => $row['service_name'],
                    'is_active' => (bool) $row['is_active'],
                    'is_valid' => !is_wp_error($validation_result),
                    'error_message' => is_wp_error($validation_result) ? $validation_result->get_error_message() : '',
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                );
            }

            return $services;

        } catch (Exception $e) {
            $this->logger->error('获取服务状态异常', array('error' => $e->getMessage()));
            return array();
        }
    }
}