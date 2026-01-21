<?php
/**
 * 站点认证信息模型类
 *
 * @package Article_Management_V2
 * @subpackage Models
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 站点认证信息模型类
 */
class AMS_V2_Site_Credentials {

    /**
     * 用户名
     *
     * @var string
     */
    private $username;

    /**
     * 加密的密码
     *
     * @var string
     */
    private $encrypted_password;

    /**
     * 加密的API密钥
     *
     * @var string|null
     */
    private $encrypted_api_key;

    /**
     * 认证方法
     *
     * @var string
     */
    private $auth_method;

    /**
     * 构造函数
     *
     * @param array $data 认证数据
     */
    public function __construct($data = array()) {
        $this->auth_method = 'password';
        
        if (!empty($data)) {
            $this->populate($data);
        }
    }

    /**
     * 填充认证数据
     *
     * @param array $data 认证数据
     */
    public function populate($data) {
        $this->username = isset($data['username']) ? sanitize_text_field($data['username']) : '';
        $this->encrypted_password = isset($data['encrypted_password']) ? $data['encrypted_password'] : '';
        $this->encrypted_api_key = isset($data['encrypted_api_key']) ? $data['encrypted_api_key'] : null;
        $this->auth_method = isset($data['auth_method']) ? sanitize_text_field($data['auth_method']) : 'password';
    }

    /**
     * 获取用户名
     *
     * @return string
     */
    public function get_username() {
        return $this->username;
    }

    /**
     * 设置用户名
     *
     * @param string $username 用户名
     */
    public function set_username($username) {
        $this->username = sanitize_text_field($username);
    }

    /**
     * 获取加密的密码
     *
     * @return string
     */
    public function get_encrypted_password() {
        return $this->encrypted_password;
    }

    /**
     * 设置加密的密码
     *
     * @param string $encrypted_password 加密的密码
     */
    public function set_encrypted_password($encrypted_password) {
        $this->encrypted_password = $encrypted_password;
    }

    /**
     * 获取加密的API密钥
     *
     * @return string|null
     */
    public function get_encrypted_api_key() {
        return $this->encrypted_api_key;
    }

    /**
     * 设置加密的API密钥
     *
     * @param string|null $encrypted_api_key 加密的API密钥
     */
    public function set_encrypted_api_key($encrypted_api_key) {
        $this->encrypted_api_key = $encrypted_api_key;
    }

    /**
     * 获取认证方法
     *
     * @return string
     */
    public function get_auth_method() {
        return $this->auth_method;
    }

    /**
     * 设置认证方法
     *
     * @param string $auth_method 认证方法 ('password' 或 'api_key')
     */
    public function set_auth_method($auth_method) {
        $valid_methods = array('password', 'api_key');
        if (in_array($auth_method, $valid_methods)) {
            $this->auth_method = $auth_method;
        }
    }

    /**
     * 设置明文密码（会自动加密）
     *
     * @param string $password 明文密码
     */
    public function set_password($password) {
        if (!empty($password)) {
            $encryption_service = AMS_V2_Container::get_instance()->get('encryption_service');
            if ($encryption_service) {
                $this->encrypted_password = $encryption_service->encrypt($password);
            } else {
                // 如果加密服务不可用，使用WordPress内置的哈希函数作为备选
                $this->encrypted_password = wp_hash_password($password);
            }
        }
    }

    /**
     * 获取明文密码（解密）
     *
     * @return string|null
     */
    public function get_password() {
        if (empty($this->encrypted_password)) {
            return null;
        }

        $encryption_service = AMS_V2_Container::get_instance()->get('encryption_service');
        if ($encryption_service) {
            return $encryption_service->decrypt($this->encrypted_password);
        }

        // 如果加密服务不可用，返回null（无法解密哈希值）
        return null;
    }

    /**
     * 设置明文API密钥（会自动加密）
     *
     * @param string|null $api_key 明文API密钥
     */
    public function set_api_key($api_key) {
        if (!empty($api_key)) {
            $encryption_service = AMS_V2_Container::get_instance()->get('encryption_service');
            if ($encryption_service) {
                $this->encrypted_api_key = $encryption_service->encrypt($api_key);
            } else {
                // 如果加密服务不可用，使用base64编码作为简单的混淆
                $this->encrypted_api_key = base64_encode($api_key);
            }
        } else {
            $this->encrypted_api_key = null;
        }
    }

    /**
     * 获取明文API密钥（解密）
     *
     * @return string|null
     */
    public function get_api_key() {
        if (empty($this->encrypted_api_key)) {
            return null;
        }

        $encryption_service = AMS_V2_Container::get_instance()->get('encryption_service');
        if ($encryption_service) {
            return $encryption_service->decrypt($this->encrypted_api_key);
        }

        // 如果加密服务不可用，尝试base64解码
        $decoded = base64_decode($this->encrypted_api_key, true);
        return $decoded !== false ? $decoded : null;
    }

    /**
     * 检查是否使用密码认证
     *
     * @return bool
     */
    public function uses_password_auth() {
        return $this->auth_method === 'password';
    }

    /**
     * 检查是否使用API密钥认证
     *
     * @return bool
     */
    public function uses_api_key_auth() {
        return $this->auth_method === 'api_key';
    }

    /**
     * 验证认证信息
     *
     * @return array 验证错误数组，空数组表示验证通过
     */
    public function validate() {
        $errors = array();

        if (empty($this->username)) {
            $errors[] = '用户名不能为空';
        }

        if ($this->uses_password_auth()) {
            if (empty($this->encrypted_password)) {
                $errors[] = '密码不能为空';
            }
        } elseif ($this->uses_api_key_auth()) {
            if (empty($this->encrypted_api_key)) {
                $errors[] = 'API密钥不能为空';
            }
        } else {
            $errors[] = '认证方法无效';
        }

        return $errors;
    }

    /**
     * 转换为数组（不包含敏感信息）
     *
     * @return array
     */
    public function to_array() {
        return array(
            'username' => $this->username,
            'auth_method' => $this->auth_method,
            'has_password' => !empty($this->encrypted_password),
            'has_api_key' => !empty($this->encrypted_api_key)
        );
    }

    /**
     * 获取用于数据库存储的数组
     *
     * @return array
     */
    public function to_database_array() {
        return array(
            'username' => $this->username,
            'encrypted_password' => $this->encrypted_password,
            'encrypted_api_key' => $this->encrypted_api_key,
            'auth_method' => $this->auth_method
        );
    }

    /**
     * 清除敏感数据
     */
    public function clear_sensitive_data() {
        $this->encrypted_password = '';
        $this->encrypted_api_key = null;
    }

    /**
     * 检查认证信息是否完整
     *
     * @return bool
     */
    public function is_complete() {
        if (empty($this->username)) {
            return false;
        }

        if ($this->uses_password_auth()) {
            return !empty($this->encrypted_password);
        } elseif ($this->uses_api_key_auth()) {
            return !empty($this->encrypted_api_key);
        }

        return false;
    }
}