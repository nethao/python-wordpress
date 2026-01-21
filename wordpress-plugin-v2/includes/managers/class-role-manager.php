<?php
/**
 * 角色管理器实现类
 *
 * @package Article_Management_V2
 * @subpackage Managers
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 角色管理器实现类
 */
class AMS_V2_Role_Manager implements AMS_V2_Role_Manager_Interface {

    /**
     * WordPress数据库对象
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * 数据库管理器
     *
     * @var AMS_V2_Database_Manager
     */
    private $db_manager;

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
     * @param AMS_V2_Logger $logger 日志记录器
     */
    public function __construct($db_manager, $logger) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->db_manager = $db_manager;
        $this->logger = $logger;
    }

    /**
     * 创建角色
     *
     * @param string $role_name 角色名称
     * @param array $capabilities 权限列表
     * @param string $display_name 显示名称
     * @param string $description 角色描述
     * @return AMS_V2_Role|WP_Error 角色对象或错误
     */
    public function create_role($role_name, $capabilities, $display_name = '', $description = '') {
        // 验证输入
        if (empty($role_name)) {
            return new WP_Error('invalid_role_name', '角色名称不能为空');
        }

        if (!is_array($capabilities)) {
            return new WP_Error('invalid_capabilities', '权限列表必须是数组');
        }

        // 检查角色是否已存在
        if ($this->get_role($role_name)) {
            return new WP_Error('role_exists', '角色已存在');
        }

        $roles_table = $this->db_manager->get_table_name('roles');
        
        $role_data = array(
            'name' => sanitize_text_field($role_name),
            'display_name' => !empty($display_name) ? sanitize_text_field($display_name) : $role_name,
            'description' => sanitize_textarea_field($description),
            'capabilities' => json_encode($capabilities),
            'is_system' => 0
        );

        $result = $this->wpdb->insert($roles_table, $role_data);

        if ($result === false) {
            $this->logger->error('Failed to create role', array(
                'role_name' => $role_name,
                'error' => $this->wpdb->last_error
            ));
            return new WP_Error('db_error', '创建角色失败');
        }

        $role_id = $this->wpdb->insert_id;
        $role = $this->get_role($role_id);

        $this->logger->info('Role created', array(
            'role_id' => $role_id,
            'role_name' => $role_name
        ));

        return $role;
    }

    /**
     * 获取角色
     *
     * @param int|string $role_identifier 角色ID或名称
     * @return AMS_V2_Role|null 角色对象或null
     */
    public function get_role($role_identifier) {
        $roles_table = $this->db_manager->get_table_name('roles');
        
        if (is_numeric($role_identifier)) {
            $sql = $this->wpdb->prepare("SELECT * FROM $roles_table WHERE id = %d", $role_identifier);
        } else {
            $sql = $this->wpdb->prepare("SELECT * FROM $roles_table WHERE name = %s", $role_identifier);
        }

        $role_data = $this->wpdb->get_row($sql, ARRAY_A);

        if (!$role_data) {
            return null;
        }

        return new AMS_V2_Role($role_data);
    }

    /**
     * 更新角色
     *
     * @param AMS_V2_Role $role 角色对象
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function update_role($role) {
        if (!$role instanceof AMS_V2_Role) {
            return new WP_Error('invalid_role', '无效的角色对象');
        }

        if ($role->get_id() <= 0) {
            return new WP_Error('invalid_role_id', '无效的角色ID');
        }

        $roles_table = $this->db_manager->get_table_name('roles');
        
        $role_data = array(
            'display_name' => $role->get_display_name(),
            'description' => $role->get_description(),
            'capabilities' => $role->get_capabilities_json()
        );

        $result = $this->wpdb->update(
            $roles_table,
            $role_data,
            array('id' => $role->get_id()),
            array('%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            $this->logger->error('Failed to update role', array(
                'role_id' => $role->get_id(),
                'error' => $this->wpdb->last_error
            ));
            return new WP_Error('db_error', '更新角色失败');
        }

        $this->logger->info('Role updated', array(
            'role_id' => $role->get_id(),
            'role_name' => $role->get_name()
        ));

        return true;
    }

    /**
     * 删除角色
     *
     * @param int|string $role_identifier 角色ID或名称
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function delete_role($role_identifier) {
        $role = $this->get_role($role_identifier);
        
        if (!$role) {
            return new WP_Error('role_not_found', '角色不存在');
        }

        if ($role->is_system()) {
            return new WP_Error('cannot_delete_system_role', '不能删除系统角色');
        }

        // 检查是否有用户使用此角色
        $user_roles_table = $this->db_manager->get_table_name('user_roles');
        $user_count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM $user_roles_table WHERE role_id = %d",
                $role->get_id()
            )
        );

        if ($user_count > 0) {
            return new WP_Error('role_in_use', '角色正在使用中，无法删除');
        }

        $roles_table = $this->db_manager->get_table_name('roles');
        $result = $this->wpdb->delete(
            $roles_table,
            array('id' => $role->get_id()),
            array('%d')
        );

        if ($result === false) {
            $this->logger->error('Failed to delete role', array(
                'role_id' => $role->get_id(),
                'error' => $this->wpdb->last_error
            ));
            return new WP_Error('db_error', '删除角色失败');
        }

        $this->logger->info('Role deleted', array(
            'role_id' => $role->get_id(),
            'role_name' => $role->get_name()
        ));

        return true;
    }

    /**
     * 获取所有角色
     *
     * @param bool $include_system 是否包含系统角色
     * @return AMS_V2_Role[] 角色数组
     */
    public function get_all_roles($include_system = true) {
        $roles_table = $this->db_manager->get_table_name('roles');
        
        $sql = "SELECT * FROM $roles_table";
        if (!$include_system) {
            $sql .= " WHERE is_system = 0";
        }
        $sql .= " ORDER BY name";

        $roles_data = $this->wpdb->get_results($sql, ARRAY_A);
        $roles = array();

        foreach ($roles_data as $role_data) {
            $roles[] = new AMS_V2_Role($role_data);
        }

        return $roles;
    }

    /**
     * 为用户分配角色
     *
     * @param AMS_V2_User $user 用户对象
     * @param AMS_V2_Role $role 角色对象
     * @param int|null $assigned_by 分配者用户ID
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function assign_role($user, $role, $assigned_by = null) {
        if (!$user instanceof AMS_V2_User) {
            return new WP_Error('invalid_user', '无效的用户对象');
        }

        if (!$role instanceof AMS_V2_Role) {
            return new WP_Error('invalid_role', '无效的角色对象');
        }

        // 检查是否已分配此角色
        if ($this->has_role($user, $role->get_name())) {
            return new WP_Error('role_already_assigned', '用户已拥有此角色');
        }

        $user_roles_table = $this->db_manager->get_table_name('user_roles');
        
        $assignment_data = array(
            'user_id' => $user->get_id(),
            'role_id' => $role->get_id(),
            'assigned_by' => $assigned_by
        );

        $result = $this->wpdb->insert($user_roles_table, $assignment_data);

        if ($result === false) {
            $this->logger->error('Failed to assign role', array(
                'user_id' => $user->get_id(),
                'role_id' => $role->get_id(),
                'error' => $this->wpdb->last_error
            ));
            return new WP_Error('db_error', '分配角色失败');
        }

        $this->logger->info('Role assigned', array(
            'user_id' => $user->get_id(),
            'role_id' => $role->get_id(),
            'assigned_by' => $assigned_by
        ));

        return true;
    }

    /**
     * 移除用户角色
     *
     * @param AMS_V2_User $user 用户对象
     * @param AMS_V2_Role $role 角色对象
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function remove_role($user, $role) {
        if (!$user instanceof AMS_V2_User) {
            return new WP_Error('invalid_user', '无效的用户对象');
        }

        if (!$role instanceof AMS_V2_Role) {
            return new WP_Error('invalid_role', '无效的角色对象');
        }

        $user_roles_table = $this->db_manager->get_table_name('user_roles');
        
        $result = $this->wpdb->delete(
            $user_roles_table,
            array(
                'user_id' => $user->get_id(),
                'role_id' => $role->get_id()
            ),
            array('%d', '%d')
        );

        if ($result === false) {
            $this->logger->error('Failed to remove role', array(
                'user_id' => $user->get_id(),
                'role_id' => $role->get_id(),
                'error' => $this->wpdb->last_error
            ));
            return new WP_Error('db_error', '移除角色失败');
        }

        $this->logger->info('Role removed', array(
            'user_id' => $user->get_id(),
            'role_id' => $role->get_id()
        ));

        return true;
    }

    /**
     * 获取用户角色
     *
     * @param AMS_V2_User $user 用户对象
     * @return AMS_V2_Role[] 角色数组
     */
    public function get_user_roles($user) {
        if (!$user instanceof AMS_V2_User) {
            return array();
        }

        $user_roles_table = $this->db_manager->get_table_name('user_roles');
        $roles_table = $this->db_manager->get_table_name('roles');
        
        $sql = $this->wpdb->prepare("
            SELECT r.* 
            FROM $roles_table r
            INNER JOIN $user_roles_table ur ON r.id = ur.role_id
            WHERE ur.user_id = %d
            ORDER BY r.name
        ", $user->get_id());

        $roles_data = $this->wpdb->get_results($sql, ARRAY_A);
        $roles = array();

        foreach ($roles_data as $role_data) {
            $roles[] = new AMS_V2_Role($role_data);
        }

        return $roles;
    }

    /**
     * 检查用户是否有指定角色
     *
     * @param AMS_V2_User $user 用户对象
     * @param string $role_name 角色名称
     * @return bool
     */
    public function has_role($user, $role_name) {
        if (!$user instanceof AMS_V2_User) {
            return false;
        }

        $user_roles = $this->get_user_roles($user);
        
        foreach ($user_roles as $role) {
            if ($role->get_name() === $role_name) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查用户是否有指定权限
     *
     * @param AMS_V2_User $user 用户对象
     * @param string $capability 权限名称
     * @return bool
     */
    public function has_capability($user, $capability) {
        if (!$user instanceof AMS_V2_User) {
            return false;
        }

        $user_roles = $this->get_user_roles($user);
        
        foreach ($user_roles as $role) {
            if ($role->has_capability($capability)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取角色的权限列表
     *
     * @param AMS_V2_Role $role 角色对象
     * @return array 权限列表
     */
    public function get_role_capabilities($role) {
        if (!$role instanceof AMS_V2_Role) {
            return array();
        }

        return $role->get_capabilities();
    }

    /**
     * 为角色添加权限
     *
     * @param AMS_V2_Role $role 角色对象
     * @param string $capability 权限名称
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function add_capability_to_role($role, $capability) {
        if (!$role instanceof AMS_V2_Role) {
            return new WP_Error('invalid_role', '无效的角色对象');
        }

        if ($role->is_system()) {
            return new WP_Error('cannot_modify_system_role', '不能修改系统角色');
        }

        $role->add_capability($capability);
        return $this->update_role($role);
    }

    /**
     * 从角色移除权限
     *
     * @param AMS_V2_Role $role 角色对象
     * @param string $capability 权限名称
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function remove_capability_from_role($role, $capability) {
        if (!$role instanceof AMS_V2_Role) {
            return new WP_Error('invalid_role', '无效的角色对象');
        }

        if ($role->is_system()) {
            return new WP_Error('cannot_modify_system_role', '不能修改系统角色');
        }

        $role->remove_capability($capability);
        return $this->update_role($role);
    }
}