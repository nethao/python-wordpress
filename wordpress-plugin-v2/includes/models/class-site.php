<?php
/**
 * 站点模型类
 *
 * @package Article_Management_V2
 * @subpackage Models
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 站点模型类
 */
class AMS_V2_Site {

    /**
     * 站点ID
     *
     * @var int
     */
    private $id;

    /**
     * 站点名称
     *
     * @var string
     */
    private $name;

    /**
     * 站点URL
     *
     * @var string
     */
    private $url;

    /**
     * 站点认证信息
     *
     * @var AMS_V2_Site_Credentials
     */
    private $credentials;

    /**
     * 默认分类ID
     *
     * @var int
     */
    private $default_category_id;

    /**
     * 是否激活
     *
     * @var bool
     */
    private $is_active;

    /**
     * 创建时间
     *
     * @var DateTime
     */
    private $created_at;

    /**
     * 更新时间
     *
     * @var DateTime
     */
    private $updated_at;

    /**
     * 最后同步时间
     *
     * @var DateTime|null
     */
    private $last_sync_at;

    /**
     * 同步状态
     *
     * @var string
     */
    private $sync_status;

    /**
     * 构造函数
     *
     * @param array $data 站点数据
     */
    public function __construct($data = array()) {
        $this->sync_status = 'unknown';
        $this->default_category_id = 1;
        $this->is_active = true;
        
        if (!empty($data)) {
            $this->populate($data);
        }
    }

    /**
     * 填充站点数据
     *
     * @param array $data 站点数据
     */
    public function populate($data) {
        $this->id = isset($data['id']) ? (int) $data['id'] : 0;
        $this->name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
        $this->url = isset($data['url']) ? esc_url_raw($data['url']) : '';
        $this->default_category_id = isset($data['default_category_id']) ? (int) $data['default_category_id'] : 1;
        $this->is_active = isset($data['is_active']) ? (bool) $data['is_active'] : true;
        $this->sync_status = isset($data['sync_status']) ? sanitize_text_field($data['sync_status']) : 'unknown';
        
        // 处理认证信息
        if (isset($data['username']) || isset($data['encrypted_password']) || isset($data['encrypted_api_key'])) {
            $credentials_data = array(
                'username' => isset($data['username']) ? $data['username'] : '',
                'encrypted_password' => isset($data['encrypted_password']) ? $data['encrypted_password'] : '',
                'encrypted_api_key' => isset($data['encrypted_api_key']) ? $data['encrypted_api_key'] : '',
                'auth_method' => isset($data['auth_method']) ? $data['auth_method'] : 'password'
            );
            $this->credentials = new AMS_V2_Site_Credentials($credentials_data);
        }
        
        // 处理日期时间
        if (isset($data['created_at'])) {
            $this->created_at = new DateTime($data['created_at']);
        }
        if (isset($data['updated_at'])) {
            $this->updated_at = new DateTime($data['updated_at']);
        }
        if (isset($data['last_sync_at']) && !empty($data['last_sync_at'])) {
            $this->last_sync_at = new DateTime($data['last_sync_at']);
        }
    }

    /**
     * 获取站点ID
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * 设置站点ID
     *
     * @param int $id 站点ID
     */
    public function set_id($id) {
        $this->id = (int) $id;
    }

    /**
     * 获取站点名称
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * 设置站点名称
     *
     * @param string $name 站点名称
     */
    public function set_name($name) {
        $this->name = sanitize_text_field($name);
    }

    /**
     * 获取站点URL
     *
     * @return string
     */
    public function get_url() {
        return $this->url;
    }

    /**
     * 设置站点URL
     *
     * @param string $url 站点URL
     */
    public function set_url($url) {
        $this->url = esc_url_raw($url);
    }

    /**
     * 获取认证信息
     *
     * @return AMS_V2_Site_Credentials|null
     */
    public function get_credentials() {
        return $this->credentials;
    }

    /**
     * 设置认证信息
     *
     * @param AMS_V2_Site_Credentials $credentials 认证信息
     */
    public function set_credentials($credentials) {
        if ($credentials instanceof AMS_V2_Site_Credentials) {
            $this->credentials = $credentials;
        }
    }

    /**
     * 获取默认分类ID
     *
     * @return int
     */
    public function get_default_category_id() {
        return $this->default_category_id;
    }

    /**
     * 设置默认分类ID
     *
     * @param int $default_category_id 默认分类ID
     */
    public function set_default_category_id($default_category_id) {
        $this->default_category_id = (int) $default_category_id;
    }

    /**
     * 是否激活
     *
     * @return bool
     */
    public function is_active() {
        return $this->is_active;
    }

    /**
     * 设置激活状态
     *
     * @param bool $is_active 是否激活
     */
    public function set_active($is_active) {
        $this->is_active = (bool) $is_active;
    }

    /**
     * 获取创建时间
     *
     * @return DateTime|null
     */
    public function get_created_at() {
        return $this->created_at;
    }

    /**
     * 设置创建时间
     *
     * @param DateTime $created_at 创建时间
     */
    public function set_created_at($created_at) {
        $this->created_at = $created_at;
    }

    /**
     * 获取更新时间
     *
     * @return DateTime|null
     */
    public function get_updated_at() {
        return $this->updated_at;
    }

    /**
     * 设置更新时间
     *
     * @param DateTime $updated_at 更新时间
     */
    public function set_updated_at($updated_at) {
        $this->updated_at = $updated_at;
    }

    /**
     * 获取最后同步时间
     *
     * @return DateTime|null
     */
    public function get_last_sync_at() {
        return $this->last_sync_at;
    }

    /**
     * 设置最后同步时间
     *
     * @param DateTime|null $last_sync_at 最后同步时间
     */
    public function set_last_sync_at($last_sync_at) {
        $this->last_sync_at = $last_sync_at;
    }

    /**
     * 获取同步状态
     *
     * @return string
     */
    public function get_sync_status() {
        return $this->sync_status;
    }

    /**
     * 设置同步状态
     *
     * @param string $sync_status 同步状态
     */
    public function set_sync_status($sync_status) {
        $valid_statuses = array('unknown', 'connected', 'failed', 'testing');
        if (in_array($sync_status, $valid_statuses)) {
            $this->sync_status = $sync_status;
        }
    }

    /**
     * 获取同步状态显示名称
     *
     * @return string
     */
    public function get_sync_status_label() {
        $labels = array(
            'unknown' => '未知',
            'connected' => '已连接',
            'failed' => '连接失败',
            'testing' => '测试中'
        );
        return isset($labels[$this->sync_status]) ? $labels[$this->sync_status] : $this->sync_status;
    }

    /**
     * 检查是否连接正常
     *
     * @return bool
     */
    public function is_connected() {
        return $this->sync_status === 'connected';
    }

    /**
     * 验证站点数据
     *
     * @return array 验证错误数组，空数组表示验证通过
     */
    public function validate() {
        $errors = array();

        if (empty($this->name)) {
            $errors[] = '站点名称不能为空';
        }

        if (empty($this->url)) {
            $errors[] = '站点URL不能为空';
        } elseif (!filter_var($this->url, FILTER_VALIDATE_URL)) {
            $errors[] = '站点URL格式无效';
        }

        if ($this->credentials) {
            $credential_errors = $this->credentials->validate();
            $errors = array_merge($errors, $credential_errors);
        } else {
            $errors[] = '站点认证信息不能为空';
        }

        if ($this->default_category_id <= 0) {
            $errors[] = '默认分类ID必须大于0';
        }

        return $errors;
    }

    /**
     * 转换为数组
     *
     * @param bool $include_credentials 是否包含认证信息
     * @return array
     */
    public function to_array($include_credentials = false) {
        $data = array(
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'default_category_id' => $this->default_category_id,
            'is_active' => $this->is_active,
            'sync_status' => $this->sync_status,
            'sync_status_label' => $this->get_sync_status_label(),
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'last_sync_at' => $this->last_sync_at ? $this->last_sync_at->format('Y-m-d H:i:s') : null
        );

        if ($include_credentials && $this->credentials) {
            $data['credentials'] = $this->credentials->to_array();
        }

        return $data;
    }

    /**
     * 获取用于数据库存储的数组
     *
     * @return array
     */
    public function to_database_array() {
        $data = array(
            'name' => $this->name,
            'url' => $this->url,
            'default_category_id' => $this->default_category_id,
            'is_active' => $this->is_active ? 1 : 0,
            'sync_status' => $this->sync_status
        );

        if ($this->credentials) {
            $credentials_data = $this->credentials->to_database_array();
            $data = array_merge($data, $credentials_data);
        }

        if ($this->id > 0) {
            $data['id'] = $this->id;
        }

        return $data;
    }
}