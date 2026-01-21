<?php
/**
 * 用户模型类
 *
 * @package Article_Management_V2
 * @subpackage Models
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 用户模型类
 */
class AMS_V2_User {

    /**
     * 用户ID
     *
     * @var int
     */
    private $id;

    /**
     * 用户名
     *
     * @var string
     */
    private $username;

    /**
     * 邮箱
     *
     * @var string
     */
    private $email;

    /**
     * 密码哈希
     *
     * @var string
     */
    private $password_hash;

    /**
     * 显示名称
     *
     * @var string
     */
    private $display_name;

    /**
     * 用户角色
     *
     * @var AMS_V2_Role[]
     */
    private $roles = array();

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
     * 最后登录时间
     *
     * @var DateTime|null
     */
    private $last_login_at;

    /**
     * 构造函数
     *
     * @param array $data 用户数据
     */
    public function __construct($data = array()) {
        if (!empty($data)) {
            $this->populate($data);
        }
    }

    /**
     * 填充用户数据
     *
     * @param array $data 用户数据
     */
    public function populate($data) {
        $this->id = isset($data['id']) ? (int) $data['id'] : 0;
        $this->username = isset($data['username']) ? sanitize_text_field($data['username']) : '';
        $this->email = isset($data['email']) ? sanitize_email($data['email']) : '';
        $this->password_hash = isset($data['password_hash']) ? $data['password_hash'] : '';
        $this->display_name = isset($data['display_name']) ? sanitize_text_field($data['display_name']) : '';
        $this->is_active = isset($data['is_active']) ? (bool) $data['is_active'] : true;
        
        // 处理日期时间
        if (isset($data['created_at'])) {
            $this->created_at = new DateTime($data['created_at']);
        }
        if (isset($data['updated_at'])) {
            $this->updated_at = new DateTime($data['updated_at']);
        }
        if (isset($data['last_login_at']) && !empty($data['last_login_at'])) {
            $this->last_login_at = new DateTime($data['last_login_at']);
        }
    }

    /**
     * 获取用户ID
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * 设置用户ID
     *
     * @param int $id 用户ID
     */
    public function set_id($id) {
        $this->id = (int) $id;
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
     * 获取邮箱
     *
     * @return string
     */
    public function get_email() {
        return $this->email;
    }

    /**
     * 设置邮箱
     *
     * @param string $email 邮箱
     */
    public function set_email($email) {
        $this->email = sanitize_email($email);
    }

    /**
     * 获取密码哈希
     *
     * @return string
     */
    public function get_password_hash() {
        return $this->password_hash;
    }

    /**
     * 设置密码哈希
     *
     * @param string $password_hash 密码哈希
     */
    public function set_password_hash($password_hash) {
        $this->password_hash = $password_hash;
    }

    /**
     * 获取显示名称
     *
     * @return string
     */
    public function get_display_name() {
        return $this->display_name;
    }

    /**
     * 设置显示名称
     *
     * @param string $display_name 显示名称
     */
    public function set_display_name($display_name) {
        $this->display_name = sanitize_text_field($display_name);
    }

    /**
     * 获取用户角色
     *
     * @return AMS_V2_Role[]
     */
    public function get_roles() {
        return $this->roles;
    }

    /**
     * 设置用户角色
     *
     * @param AMS_V2_Role[] $roles 角色数组
     */
    public function set_roles($roles) {
        $this->roles = is_array($roles) ? $roles : array();
    }

    /**
     * 添加角色
     *
     * @param AMS_V2_Role $role 角色对象
     */
    public function add_role($role) {
        if ($role instanceof AMS_V2_Role) {
            $this->roles[] = $role;
        }
    }

    /**
     * 检查是否有指定角色
     *
     * @param string $role_name 角色名称
     * @return bool
     */
    public function has_role($role_name) {
        foreach ($this->roles as $role) {
            if ($role->get_name() === $role_name) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查是否有指定权限
     *
     * @param string $capability 权限名称
     * @return bool
     */
    public function has_capability($capability) {
        foreach ($this->roles as $role) {
            if ($role->has_capability($capability)) {
                return true;
            }
        }
        return false;
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
     * 获取最后登录时间
     *
     * @return DateTime|null
     */
    public function get_last_login_at() {
        return $this->last_login_at;
    }

    /**
     * 设置最后登录时间
     *
     * @param DateTime|null $last_login_at 最后登录时间
     */
    public function set_last_login_at($last_login_at) {
        $this->last_login_at = $last_login_at;
    }

    /**
     * 验证密码
     *
     * @param string $password 明文密码
     * @return bool
     */
    public function verify_password($password) {
        return wp_check_password($password, $this->password_hash);
    }

    /**
     * 设置密码
     *
     * @param string $password 明文密码
     */
    public function set_password($password) {
        $this->password_hash = wp_hash_password($password);
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'display_name' => $this->display_name,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'last_login_at' => $this->last_login_at ? $this->last_login_at->format('Y-m-d H:i:s') : null,
            'roles' => array_map(function($role) {
                return $role->to_array();
            }, $this->roles)
        );
    }

    /**
     * 是否为管理员
     *
     * @return bool
     */
    public function is_administrator() {
        return $this->has_role('administrator');
    }

    /**
     * 是否为普通用户
     *
     * @return bool
     */
    public function is_user() {
        return $this->has_role('user');
    }
}