<?php
/**
 * 角色管理器接口
 *
 * @package Article_Management_V2
 * @subpackage Interfaces
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 角色管理器接口
 */
interface AMS_V2_Role_Manager_Interface {

    /**
     * 创建角色
     *
     * @param string $role_name 角色名称
     * @param array $capabilities 权限列表
     * @param string $display_name 显示名称
     * @param string $description 角色描述
     * @return AMS_V2_Role|WP_Error 角色对象或错误
     */
    public function create_role($role_name, $capabilities, $display_name = '', $description = '');

    /**
     * 获取角色
     *
     * @param int|string $role_identifier 角色ID或名称
     * @return AMS_V2_Role|null 角色对象或null
     */
    public function get_role($role_identifier);

    /**
     * 更新角色
     *
     * @param AMS_V2_Role $role 角色对象
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function update_role($role);

    /**
     * 删除角色
     *
     * @param int|string $role_identifier 角色ID或名称
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function delete_role($role_identifier);

    /**
     * 获取所有角色
     *
     * @param bool $include_system 是否包含系统角色
     * @return AMS_V2_Role[] 角色数组
     */
    public function get_all_roles($include_system = true);

    /**
     * 为用户分配角色
     *
     * @param AMS_V2_User $user 用户对象
     * @param AMS_V2_Role $role 角色对象
     * @param int|null $assigned_by 分配者用户ID
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function assign_role($user, $role, $assigned_by = null);

    /**
     * 移除用户角色
     *
     * @param AMS_V2_User $user 用户对象
     * @param AMS_V2_Role $role 角色对象
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function remove_role($user, $role);

    /**
     * 获取用户角色
     *
     * @param AMS_V2_User $user 用户对象
     * @return AMS_V2_Role[] 角色数组
     */
    public function get_user_roles($user);

    /**
     * 检查用户是否有指定角色
     *
     * @param AMS_V2_User $user 用户对象
     * @param string $role_name 角色名称
     * @return bool
     */
    public function has_role($user, $role_name);

    /**
     * 检查用户是否有指定权限
     *
     * @param AMS_V2_User $user 用户对象
     * @param string $capability 权限名称
     * @return bool
     */
    public function has_capability($user, $capability);

    /**
     * 获取角色的权限列表
     *
     * @param AMS_V2_Role $role 角色对象
     * @return array 权限列表
     */
    public function get_role_capabilities($role);

    /**
     * 为角色添加权限
     *
     * @param AMS_V2_Role $role 角色对象
     * @param string $capability 权限名称
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function add_capability_to_role($role, $capability);

    /**
     * 从角色移除权限
     *
     * @param AMS_V2_Role $role 角色对象
     * @param string $capability 权限名称
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function remove_capability_from_role($role, $capability);
}