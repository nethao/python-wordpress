<?php
/**
 * 认证管理器实现类
 *
 * @package Article_Management_V2
 * @subpackage Managers
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 认证管理器实现类
 */
class AMS_V2_Authentication_Manager implements AMS_V2_Authentication_Manager_Interface {

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
     * 角色管理器
     *
     * @var AMS_V2_Role_Manager
     */
    private $role_manager;

    /**
     * 日志记录器
     *
     * @var AMS_V2_Logger
     */
    private $logger;

    /**
     * 审计日志器
     *
     * @var AMS_V2_Audit_Logger
     */
    private $audit_logger;

    /**
     * 当前用户
     *
     * @var AMS_V2_User|null
     */
    private $current_user = null;

    /**
     * 构造函数
     *
     * @param AMS_V2_Database_Manager $db_manager 数据库管理器
     * @param AMS_V2_Role_Manager $role_manager 角色管理器
     * @param AMS_V2_Logger $logger 日志记录器
     */
    public function __construct($db_manager, $role_manager, $logger) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->db_manager = $db_manager;
        $this->role_manager = $role_manager;
        $this->logger = $logger;
        
        // 获取审计日志器（延迟加载以避免循环依赖）
        global $ams_v2_container;
        if ($ams_v2_container) {
            $this->audit_logger = $ams_v2_container->get('audit_logger');
        }
    }

    /**
     * 用户认证
     *
     * @param string $username 用户名
     * @param string $password 密码
     * @return AMS_V2_User|WP_Error 用户对象或错误
     */
    public function authenticate($username, $password) {
        if (empty($username) || empty($password)) {
            return new WP_Error('empty_credentials', '用户名和密码不能为空');
        }

        $user = $this->get_user_by_username($username);
        
        if (!$user) {
            $this->logger->warning('Authentication failed - user not found', array(
                'username' => $username,
                'ip' => $this->get_client_ip()
            ));
            return new WP_Error('invalid_username', '用户名不存在');
        }

        if (!$user->is_active()) {
            $this->logger->warning('Authentication failed - user inactive', array(
                'user_id' => $user->get_id(),
                'username' => $username,
                'ip' => $this->get_client_ip()
            ));
            return new WP_Error('user_inactive', '用户账户已被禁用');
        }

        if (!$user->verify_password($password)) {
            $this->logger->warning('Authentication failed - wrong password', array(
                'user_id' => $user->get_id(),
                'username' => $username,
                'ip' => $this->get_client_ip()
            ));
            return new WP_Error('invalid_password', '密码错误');
        }

        // 加载用户角色
        $roles = $this->role_manager->get_user_roles($user);
        $user->set_roles($roles);

        $this->logger->info('User authenticated successfully', array(
            'user_id' => $user->get_id(),
            'username' => $username,
            'ip' => $this->get_client_ip()
        ));

        // 记录审计日志
        if ($this->audit_logger) {
            $this->audit_logger->log_user_login($user);
        }

        return $user;
    }

    /**
     * 获取当前用户
     *
     * @return AMS_V2_User|null 当前用户对象或null
     */
    public function get_current_user() {
        if ($this->current_user !== null) {
            return $this->current_user;
        }

        // 从会话中获取用户ID
        if (!session_id()) {
            session_start();
        }

        if (isset($_SESSION['ams_v2_user_id'])) {
            $user_id = (int) $_SESSION['ams_v2_user_id'];
            $user = $this->get_user_by_id($user_id);
            
            if ($user && $user->is_active()) {
                // 加载用户角色
                $roles = $this->role_manager->get_user_roles($user);
                $user->set_roles($roles);
                $this->current_user = $user;
                return $user;
            } else {
                // 清除无效会话
                unset($_SESSION['ams_v2_user_id']);
            }
        }

        return null;
    }

    /**
     * 设置当前用户
     *
     * @param AMS_V2_User $user 用户对象
     * @return bool 成功返回true
     */
    public function set_current_user($user) {
        if (!$user instanceof AMS_V2_User) {
            return false;
        }

        $this->current_user = $user;
        
        if (!session_id()) {
            session_start();
        }
        
        $_SESSION['ams_v2_user_id'] = $user->get_id();
        return true;
    }

    /**
     * 用户登录
     *
     * @param AMS_V2_User $user 用户对象
     * @param bool $remember 是否记住登录状态
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function login($user, $remember = false) {
        if (!$user instanceof AMS_V2_User) {
            return new WP_Error('invalid_user', '无效的用户对象');
        }

        // 设置当前用户
        $this->set_current_user($user);

        // 更新最后登录时间
        $this->update_last_login($user);

        // 如果需要记住登录状态，设置cookie
        if ($remember) {
            $this->set_remember_cookie($user);
        }

        $this->logger->info('User logged in', array(
            'user_id' => $user->get_id(),
            'username' => $user->get_username(),
            'remember' => $remember,
            'ip' => $this->get_client_ip()
        ));

        return true;
    }

    /**
     * 用户登出
     *
     * @return bool 成功返回true
     */
    public function logout() {
        $current_user = $this->get_current_user();
        
        if ($current_user) {
            $this->logger->info('User logged out', array(
                'user_id' => $current_user->get_id(),
                'username' => $current_user->get_username(),
                'ip' => $this->get_client_ip()
            ));
        }

        // 清除会话
        if (session_id()) {
            session_destroy();
        }

        // 清除记住登录的cookie
        $this->clear_remember_cookie();

        $this->current_user = null;
        return true;
    }

    /**
     * 检查用户是否已登录
     *
     * @return bool
     */
    public function is_logged_in() {
        return $this->get_current_user() !== null;
    }

    /**
     * 检查用户权限
     *
     * @param AMS_V2_User $user 用户对象
     * @param string $capability 权限名称
     * @return bool
     */
    public function has_capability($user, $capability) {
        return $this->role_manager->has_capability($user, $capability);
    }

    /**
     * 检查操作权限
     *
     * @param AMS_V2_User $user 用户对象
     * @param string $action 操作名称
     * @param mixed $resource 资源对象
     * @return bool
     */
    public function check_permission($user, $action, $resource = null) {
        if (!$user instanceof AMS_V2_User) {
            return false;
        }

        // 管理员拥有所有权限
        if ($user->is_administrator()) {
            return true;
        }

        // 根据操作类型检查权限
        switch ($action) {
            case 'create_article':
                return $this->has_capability($user, 'create_articles');
                
            case 'edit_article':
                if ($resource && isset($resource->author_id)) {
                    // 只能编辑自己的文章
                    return $resource->author_id == $user->get_id() && 
                           $this->has_capability($user, 'edit_own_articles');
                }
                return false;
                
            case 'delete_article':
                if ($resource && isset($resource->author_id)) {
                    // 只能删除自己的文章
                    return $resource->author_id == $user->get_id() && 
                           $this->has_capability($user, 'delete_own_articles');
                }
                return false;
                
            case 'view_article':
                if ($resource && isset($resource->author_id)) {
                    // 可以查看自己的文章或已发布的文章
                    return $resource->author_id == $user->get_id() || 
                           $resource->status === 'published';
                }
                return $this->has_capability($user, 'view_own_articles');
                
            case 'moderate_content':
                return $this->has_capability($user, 'moderate_content');
                
            case 'manage_users':
                return $this->has_capability($user, 'manage_users');
                
            case 'manage_sites':
                return $this->has_capability($user, 'manage_sites');
                
            default:
                return $this->has_capability($user, $action);
        }
    }

    /**
     * 创建用户
     *
     * @param array $user_data 用户数据
     * @return AMS_V2_User|WP_Error 用户对象或错误
     */
    public function create_user($user_data) {
        // 验证必需字段
        $required_fields = array('username', 'email', 'password');
        foreach ($required_fields as $field) {
            if (empty($user_data[$field])) {
                return new WP_Error('missing_field', "字段 {$field} 不能为空");
            }
        }

        // 验证用户名格式
        if (!validate_username($user_data['username'])) {
            return new WP_Error('invalid_username', '用户名格式无效');
        }

        // 验证邮箱格式
        if (!is_email($user_data['email'])) {
            return new WP_Error('invalid_email', '邮箱格式无效');
        }

        // 检查用户名是否已存在
        if ($this->get_user_by_username($user_data['username'])) {
            return new WP_Error('username_exists', '用户名已存在');
        }

        // 检查邮箱是否已存在
        if ($this->get_user_by_email($user_data['email'])) {
            return new WP_Error('email_exists', '邮箱已存在');
        }

        $users_table = $this->db_manager->get_table_name('users');
        
        $insert_data = array(
            'username' => sanitize_text_field($user_data['username']),
            'email' => sanitize_email($user_data['email']),
            'password_hash' => wp_hash_password($user_data['password']),
            'display_name' => isset($user_data['display_name']) ? 
                sanitize_text_field($user_data['display_name']) : 
                sanitize_text_field($user_data['username']),
            'is_active' => isset($user_data['is_active']) ? (bool) $user_data['is_active'] : true
        );

        $result = $this->wpdb->insert($users_table, $insert_data);

        if ($result === false) {
            $this->logger->error('Failed to create user', array(
                'username' => $user_data['username'],
                'error' => $this->wpdb->last_error
            ));
            return new WP_Error('db_error', '创建用户失败');
        }

        $user_id = $this->wpdb->insert_id;
        $user = $this->get_user_by_id($user_id);

        // 分配默认角色
        $default_role = isset($user_data['role']) ? $user_data['role'] : 'user';
        $role = $this->role_manager->get_role($default_role);
        if ($role) {
            $this->role_manager->assign_role($user, $role);
        }

        $this->logger->info('User created', array(
            'user_id' => $user_id,
            'username' => $user_data['username'],
            'role' => $default_role
        ));

        // 记录审计日志
        if ($this->audit_logger) {
            $this->audit_logger->log_user_created($user, $this->get_current_user());
        }

        return $user;
    }

    /**
     * 更新用户
     *
     * @param AMS_V2_User $user 用户对象
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function update_user($user) {
        if (!$user instanceof AMS_V2_User) {
            return new WP_Error('invalid_user', '无效的用户对象');
        }

        if ($user->get_id() <= 0) {
            return new WP_Error('invalid_user_id', '无效的用户ID');
        }

        $users_table = $this->db_manager->get_table_name('users');
        
        $update_data = array(
            'email' => $user->get_email(),
            'display_name' => $user->get_display_name(),
            'is_active' => $user->is_active() ? 1 : 0
        );

        // 如果密码哈希不为空，更新密码
        if (!empty($user->get_password_hash())) {
            $update_data['password_hash'] = $user->get_password_hash();
        }

        $result = $this->wpdb->update(
            $users_table,
            $update_data,
            array('id' => $user->get_id()),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );

        if ($result === false) {
            $this->logger->error('Failed to update user', array(
                'user_id' => $user->get_id(),
                'error' => $this->wpdb->last_error
            ));
            return new WP_Error('db_error', '更新用户失败');
        }

        $this->logger->info('User updated', array(
            'user_id' => $user->get_id(),
            'username' => $user->get_username()
        ));

        return true;
    }

    /**
     * 删除用户
     *
     * @param int $user_id 用户ID
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function delete_user($user_id) {
        $user = $this->get_user_by_id($user_id);
        
        if (!$user) {
            return new WP_Error('user_not_found', '用户不存在');
        }

        // 检查是否有关联的文章
        $articles_table = $this->db_manager->get_table_name('articles');
        $article_count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM $articles_table WHERE author_id = %d",
                $user_id
            )
        );

        if ($article_count > 0) {
            return new WP_Error('user_has_articles', '用户有关联文章，无法删除');
        }

        // 删除用户角色关联
        $user_roles_table = $this->db_manager->get_table_name('user_roles');
        $this->wpdb->delete(
            $user_roles_table,
            array('user_id' => $user_id),
            array('%d')
        );

        // 删除用户
        $users_table = $this->db_manager->get_table_name('users');
        $result = $this->wpdb->delete(
            $users_table,
            array('id' => $user_id),
            array('%d')
        );

        if ($result === false) {
            $this->logger->error('Failed to delete user', array(
                'user_id' => $user_id,
                'error' => $this->wpdb->last_error
            ));
            return new WP_Error('db_error', '删除用户失败');
        }

        $this->logger->info('User deleted', array(
            'user_id' => $user_id,
            'username' => $user->get_username()
        ));

        return true;
    }

    /**
     * 根据ID获取用户
     *
     * @param int $user_id 用户ID
     * @return AMS_V2_User|null 用户对象或null
     */
    public function get_user_by_id($user_id) {
        $users_table = $this->db_manager->get_table_name('users');
        
        $user_data = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM $users_table WHERE id = %d", $user_id),
            ARRAY_A
        );

        if (!$user_data) {
            return null;
        }

        return new AMS_V2_User($user_data);
    }

    /**
     * 根据用户名获取用户
     *
     * @param string $username 用户名
     * @return AMS_V2_User|null 用户对象或null
     */
    public function get_user_by_username($username) {
        $users_table = $this->db_manager->get_table_name('users');
        
        $user_data = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM $users_table WHERE username = %s", $username),
            ARRAY_A
        );

        if (!$user_data) {
            return null;
        }

        return new AMS_V2_User($user_data);
    }

    /**
     * 根据邮箱获取用户
     *
     * @param string $email 邮箱
     * @return AMS_V2_User|null 用户对象或null
     */
    public function get_user_by_email($email) {
        $users_table = $this->db_manager->get_table_name('users');
        
        $user_data = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM $users_table WHERE email = %s", $email),
            ARRAY_A
        );

        if (!$user_data) {
            return null;
        }

        return new AMS_V2_User($user_data);
    }

    /**
     * 获取所有用户
     *
     * @param array $args 查询参数
     * @return AMS_V2_User[] 用户数组
     */
    public function get_users($args = array()) {
        $users_table = $this->db_manager->get_table_name('users');
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'is_active' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM $users_table";
        $where_conditions = array();
        
        if ($args['is_active'] !== null) {
            $where_conditions[] = $this->wpdb->prepare('is_active = %d', $args['is_active']);
        }
        
        if (!empty($where_conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $sql .= $this->wpdb->prepare(
            " ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        );

        $users_data = $this->wpdb->get_results($sql, ARRAY_A);
        $users = array();

        foreach ($users_data as $user_data) {
            $users[] = new AMS_V2_User($user_data);
        }

        return $users;
    }

    /**
     * 验证用户密码
     *
     * @param AMS_V2_User $user 用户对象
     * @param string $password 密码
     * @return bool
     */
    public function verify_password($user, $password) {
        if (!$user instanceof AMS_V2_User) {
            return false;
        }

        return $user->verify_password($password);
    }

    /**
     * 更新用户密码
     *
     * @param AMS_V2_User $user 用户对象
     * @param string $new_password 新密码
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function update_password($user, $new_password) {
        if (!$user instanceof AMS_V2_User) {
            return new WP_Error('invalid_user', '无效的用户对象');
        }

        if (empty($new_password)) {
            return new WP_Error('empty_password', '密码不能为空');
        }

        $user->set_password($new_password);
        return $this->update_user($user);
    }

    /**
     * 更新最后登录时间
     *
     * @param AMS_V2_User $user 用户对象
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function update_last_login($user) {
        if (!$user instanceof AMS_V2_User) {
            return new WP_Error('invalid_user', '无效的用户对象');
        }

        $users_table = $this->db_manager->get_table_name('users');
        
        $result = $this->wpdb->update(
            $users_table,
            array('last_login_at' => current_time('mysql')),
            array('id' => $user->get_id()),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', '更新登录时间失败');
        }

        return true;
    }

    /**
     * 获取客户端IP地址
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * 设置记住登录的cookie
     *
     * @param AMS_V2_User $user 用户对象
     */
    private function set_remember_cookie($user) {
        $token = wp_generate_password(64, false);
        $expiry = time() + (30 * DAY_IN_SECONDS); // 30天
        
        setcookie(
            'ams_v2_remember_' . COOKIEHASH,
            $user->get_id() . '|' . $token,
            $expiry,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
    }

    /**
     * 清除记住登录的cookie
     */
    private function clear_remember_cookie() {
        setcookie(
            'ams_v2_remember_' . COOKIEHASH,
            '',
            time() - YEAR_IN_SECONDS,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
    }
}