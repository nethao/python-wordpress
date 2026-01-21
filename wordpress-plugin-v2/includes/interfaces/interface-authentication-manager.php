<?php
/**
 * 认证管理器接口
 *
 * @package Article_Management_V2
 * @subpackage Interfaces
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 认证管理器接口
 */
interface AMS_V2_Authentication_Manager_Interface {

    /**
     * 用户认证
     *
     * @param string $username 用户名
     * @param string $password 密码
     * @return AMS_V2_User|WP_Error 用户对象或错误
     */
    public function authenticate($username, $password);

    /**
     * 获取当前用户
     *
     * @return AMS_V2_User|null 当前用户对象或null
     */
    public function get_current_user();

    /**
     * 设置当前用户
     *
     * @param AMS_V2_User $user 用户对象
     * @return bool 成功返回true
     */
    public function set_current_user($user);

    /**
     * 用户登录
     *
     * @param AMS_V2_User $user 用户对象
     * @param bool $remember 是否记住登录状态
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function login($user, $remember = false);

    /**
     * 用户登出
     *
     * @return bool 成功返回true
     */
    public function logout();

    /**
     * 检查用户是否已登录
     *
     * @return bool
     */
    public function is_logged_in();

    /**
     * 检查用户权限
     *
     * @param AMS_V2_User $user 用户对象
     * @param string $capability 权限名称
     * @return bool
     */
    public function has_capability($user, $capability);

    /**
     * 检查操作权限
     *
     * @param AMS_V2_User $user 用户对象
     * @param string $action 操作名称
     * @param mixed $resource 资源对象
     * @return bool
     */
    public function check_permission($user, $action, $resource = null);

    /**
     * 创建用户
     *
     * @param array $user_data 用户数据
     * @return AMS_V2_User|WP_Error 用户对象或错误
     */
    public function create_user($user_data);

    /**
     * 更新用户
     *
     * @param AMS_V2_User $user 用户对象
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function update_user($user);

    /**
     * 删除用户
     *
     * @param int $user_id 用户ID
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function delete_user($user_id);

    /**
     * 根据ID获取用户
     *
     * @param int $user_id 用户ID
     * @return AMS_V2_User|null 用户对象或null
     */
    public function get_user_by_id($user_id);

    /**
     * 根据用户名获取用户
     *
     * @param string $username 用户名
     * @return AMS_V2_User|null 用户对象或null
     */
    public function get_user_by_username($username);

    /**
     * 根据邮箱获取用户
     *
     * @param string $email 邮箱
     * @return AMS_V2_User|null 用户对象或null
     */
    public function get_user_by_email($email);

    /**
     * 获取所有用户
     *
     * @param array $args 查询参数
     * @return AMS_V2_User[] 用户数组
     */
    public function get_users($args = array());

    /**
     * 验证用户密码
     *
     * @param AMS_V2_User $user 用户对象
     * @param string $password 密码
     * @return bool
     */
    public function verify_password($user, $password);

    /**
     * 更新用户密码
     *
     * @param AMS_V2_User $user 用户对象
     * @param string $new_password 新密码
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function update_password($user, $new_password);

    /**
     * 更新最后登录时间
     *
     * @param AMS_V2_User $user 用户对象
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function update_last_login($user);
}