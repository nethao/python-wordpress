<?php
/**
 * 用户API控制器
 *
 * @package Article_Management_V2
 * @subpackage API
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 用户API控制器类
 */
class AMS_V2_User_API_Controller extends WP_REST_Controller {

    /**
     * 命名空间
     *
     * @var string
     */
    protected $namespace = 'ams-v2/v1';

    /**
     * 资源名称
     *
     * @var string
     */
    protected $rest_base = 'users';

    /**
     * 认证管理器
     *
     * @var AMS_V2_Authentication_Manager
     */
    private $auth_manager;

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
     * 构造函数
     *
     * @param AMS_V2_Authentication_Manager $auth_manager 认证管理器
     * @param AMS_V2_Role_Manager $role_manager 角色管理器
     * @param AMS_V2_Logger $logger 日志记录器
     */
    public function __construct($auth_manager, $role_manager, $logger) {
        $this->auth_manager = $auth_manager;
        $this->role_manager = $role_manager;
        $this->logger = $logger;
    }

    /**
     * 注册路由
     */
    public function register_routes() {
        // 获取用户列表
        register_rest_route($this->namespace, '/' . $this->rest_base, array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_items'),
                'permission_callback' => array($this, 'get_items_permissions_check'),
                'args' => $this->get_collection_params(),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_item'),
                'permission_callback' => array($this, 'create_item_permissions_check'),
                'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
            ),
            'schema' => array($this, 'get_public_item_schema'),
        ));

        // 获取、更新、删除单个用户
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_item'),
                'permission_callback' => array($this, 'get_item_permissions_check'),
                'args' => array(
                    'context' => $this->get_context_param(array('default' => 'view')),
                ),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_item'),
                'permission_callback' => array($this, 'update_item_permissions_check'),
                'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_item'),
                'permission_callback' => array($this, 'delete_item_permissions_check'),
            ),
            'schema' => array($this, 'get_public_item_schema'),
        ));

        // 用户认证
        register_rest_route($this->namespace, '/auth/login', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'login'),
            'permission_callback' => '__return_true',
            'args' => array(
                'username' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => '用户名',
                ),
                'password' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => '密码',
                ),
                'remember' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'description' => '是否记住登录状态',
                ),
            ),
        ));

        // 用户登出
        register_rest_route($this->namespace, '/auth/logout', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'logout'),
            'permission_callback' => array($this, 'logout_permissions_check'),
        ));

        // 获取当前用户信息
        register_rest_route($this->namespace, '/auth/me', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_current_user'),
            'permission_callback' => array($this, 'get_current_user_permissions_check'),
        ));

        // 修改密码
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/password', array(
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => array($this, 'update_password'),
            'permission_callback' => array($this, 'update_password_permissions_check'),
            'args' => array(
                'current_password' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => '当前密码',
                ),
                'new_password' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => '新密码',
                    'minLength' => 6,
                ),
            ),
        ));
    }

    /**
     * 获取用户列表
     *
     * @param WP_REST_Request $request 请求对象
     * @return WP_REST_Response|WP_Error 响应对象或错误
     */
    public function get_items($request) {
        $args = array(
            'limit' => $request['per_page'],
            'offset' => ($request['page'] - 1) * $request['per_page'],
            'orderby' => $request['orderby'],
            'order' => $request['order'],
        );

        if (isset($request['is_active'])) {
            $args['is_active'] = $request['is_active'];
        }

        $users = $this->auth_manager->get_users($args);
        $data = array();

        foreach ($users as $user) {
            $user_data = $this->prepare_item_for_response($user, $request);
            $data[] = $this->prepare_response_for_collection($user_data);
        }

        // 获取总数用于分页
        global $wpdb;
        $db_manager = $this->get_database_manager();
        $users_table = $db_manager->get_table_name('users');
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $users_table");

        $response = rest_ensure_response($data);
        $response->header('X-WP-Total', (int) $total);
        $response->header('X-WP-TotalPages', (int) ceil($total / $request['per_page']));

        return $response;
    }

    /**
     * 获取单个用户
     *
     * @param WP_REST_Request $request 请求对象
     * @return WP_REST_Response|WP_Error 响应对象或错误
     */
    public function get_item($request) {
        $user = $this->auth_manager->get_user_by_id($request['id']);

        if (!$user) {
            return new WP_Error('user_not_found', '用户不存在', array('status' => 404));
        }

        $data = $this->prepare_item_for_response($user, $request);
        return rest_ensure_response($data);
    }

    /**
     * 创建用户
     *
     * @param WP_REST_Request $request 请求对象
     * @return WP_REST_Response|WP_Error 响应对象或错误
     */
    public function create_item($request) {
        $user_data = array(
            'username' => $request['username'],
            'email' => $request['email'],
            'password' => $request['password'],
            'display_name' => $request['display_name'],
            'is_active' => isset($request['is_active']) ? $request['is_active'] : true,
            'role' => isset($request['role']) ? $request['role'] : 'user',
        );

        $user = $this->auth_manager->create_user($user_data);

        if (is_wp_error($user)) {
            return $user;
        }

        $this->logger->info('User created via API', array(
            'user_id' => $user->get_id(),
            'username' => $user->get_username(),
            'created_by' => get_current_user_id()
        ));

        $response = $this->prepare_item_for_response($user, $request);
        $response = rest_ensure_response($response);
        $response->set_status(201);
        $response->header('Location', rest_url(sprintf('%s/%s/%d', $this->namespace, $this->rest_base, $user->get_id())));

        return $response;
    }

    /**
     * 更新用户
     *
     * @param WP_REST_Request $request 请求对象
     * @return WP_REST_Response|WP_Error 响应对象或错误
     */
    public function update_item($request) {
        $user = $this->auth_manager->get_user_by_id($request['id']);

        if (!$user) {
            return new WP_Error('user_not_found', '用户不存在', array('status' => 404));
        }

        if (isset($request['email'])) {
            $user->set_email($request['email']);
        }

        if (isset($request['display_name'])) {
            $user->set_display_name($request['display_name']);
        }

        if (isset($request['is_active'])) {
            $user->set_active($request['is_active']);
        }

        $result = $this->auth_manager->update_user($user);

        if (is_wp_error($result)) {
            return $result;
        }

        $this->logger->info('User updated via API', array(
            'user_id' => $user->get_id(),
            'username' => $user->get_username(),
            'updated_by' => get_current_user_id()
        ));

        $response = $this->prepare_item_for_response($user, $request);
        return rest_ensure_response($response);
    }

    /**
     * 删除用户
     *
     * @param WP_REST_Request $request 请求对象
     * @return WP_REST_Response|WP_Error 响应对象或错误
     */
    public function delete_item($request) {
        $user = $this->auth_manager->get_user_by_id($request['id']);

        if (!$user) {
            return new WP_Error('user_not_found', '用户不存在', array('status' => 404));
        }

        $result = $this->auth_manager->delete_user($request['id']);

        if (is_wp_error($result)) {
            return $result;
        }

        $this->logger->info('User deleted via API', array(
            'user_id' => $request['id'],
            'username' => $user->get_username(),
            'deleted_by' => get_current_user_id()
        ));

        return new WP_REST_Response(array(
            'deleted' => true,
            'previous' => $this->prepare_item_for_response($user, $request)->get_data(),
        ));
    }

    /**
     * 用户登录
     *
     * @param WP_REST_Request $request 请求对象
     * @return WP_REST_Response|WP_Error 响应对象或错误
     */
    public function login($request) {
        $user = $this->auth_manager->authenticate($request['username'], $request['password']);

        if (is_wp_error($user)) {
            return $user;
        }

        $login_result = $this->auth_manager->login($user, $request['remember']);

        if (is_wp_error($login_result)) {
            return $login_result;
        }

        $response_data = $this->prepare_item_for_response($user, $request)->get_data();
        $response_data['token'] = $this->generate_auth_token($user);

        return rest_ensure_response($response_data);
    }

    /**
     * 用户登出
     *
     * @param WP_REST_Request $request 请求对象
     * @return WP_REST_Response 响应对象
     */
    public function logout($request) {
        $this->auth_manager->logout();

        return rest_ensure_response(array(
            'message' => '登出成功',
        ));
    }

    /**
     * 获取当前用户信息
     *
     * @param WP_REST_Request $request 请求对象
     * @return WP_REST_Response|WP_Error 响应对象或错误
     */
    public function get_current_user($request) {
        $user = $this->auth_manager->get_current_user();

        if (!$user) {
            return new WP_Error('not_logged_in', '用户未登录', array('status' => 401));
        }

        $data = $this->prepare_item_for_response($user, $request);
        return rest_ensure_response($data);
    }

    /**
     * 更新用户密码
     *
     * @param WP_REST_Request $request 请求对象
     * @return WP_REST_Response|WP_Error 响应对象或错误
     */
    public function update_password($request) {
        $user = $this->auth_manager->get_user_by_id($request['id']);

        if (!$user) {
            return new WP_Error('user_not_found', '用户不存在', array('status' => 404));
        }

        // 验证当前密码
        if (!$user->verify_password($request['current_password'])) {
            return new WP_Error('invalid_current_password', '当前密码错误', array('status' => 400));
        }

        $result = $this->auth_manager->update_password($user, $request['new_password']);

        if (is_wp_error($result)) {
            return $result;
        }

        $this->logger->info('User password updated via API', array(
            'user_id' => $user->get_id(),
            'username' => $user->get_username(),
            'updated_by' => get_current_user_id()
        ));

        return rest_ensure_response(array(
            'message' => '密码更新成功',
        ));
    }

    /**
     * 准备响应数据
     *
     * @param AMS_V2_User $user 用户对象
     * @param WP_REST_Request $request 请求对象
     * @return WP_REST_Response 响应对象
     */
    public function prepare_item_for_response($user, $request) {
        $user_roles = $this->role_manager->get_user_roles($user);
        $roles_data = array();

        foreach ($user_roles as $role) {
            $roles_data[] = array(
                'id' => $role->get_id(),
                'name' => $role->get_name(),
                'display_name' => $role->get_display_name(),
                'capabilities' => $role->get_capabilities(),
            );
        }

        $data = array(
            'id' => $user->get_id(),
            'username' => $user->get_username(),
            'email' => $user->get_email(),
            'display_name' => $user->get_display_name(),
            'is_active' => $user->is_active(),
            'roles' => $roles_data,
            'created_at' => $user->get_created_at() ? $user->get_created_at()->format('c') : null,
            'updated_at' => $user->get_updated_at() ? $user->get_updated_at()->format('c') : null,
            'last_login_at' => $user->get_last_login_at() ? $user->get_last_login_at()->format('c') : null,
        );

        $context = !empty($request['context']) ? $request['context'] : 'view';

        if ($context === 'edit') {
            // 在编辑上下文中包含更多信息
        }

        $data = $this->filter_response_by_context($data, $context);

        $response = rest_ensure_response($data);
        $response->add_links($this->prepare_links($user));

        return $response;
    }

    /**
     * 准备链接
     *
     * @param AMS_V2_User $user 用户对象
     * @return array 链接数组
     */
    protected function prepare_links($user) {
        $links = array(
            'self' => array(
                'href' => rest_url(sprintf('%s/%s/%d', $this->namespace, $this->rest_base, $user->get_id())),
            ),
            'collection' => array(
                'href' => rest_url(sprintf('%s/%s', $this->namespace, $this->rest_base)),
            ),
        );

        return $links;
    }

    /**
     * 获取集合参数
     *
     * @return array 参数数组
     */
    public function get_collection_params() {
        $params = parent::get_collection_params();

        $params['context']['default'] = 'view';

        $params['orderby'] = array(
            'description' => '排序字段',
            'type' => 'string',
            'default' => 'created_at',
            'enum' => array('id', 'username', 'email', 'created_at', 'last_login_at'),
        );

        $params['order'] = array(
            'description' => '排序方向',
            'type' => 'string',
            'default' => 'desc',
            'enum' => array('asc', 'desc'),
        );

        $params['is_active'] = array(
            'description' => '用户状态',
            'type' => 'boolean',
        );

        return $params;
    }

    /**
     * 获取项目架构
     *
     * @return array 架构数组
     */
    public function get_item_schema() {
        $schema = array(
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => 'user',
            'type' => 'object',
            'properties' => array(
                'id' => array(
                    'description' => '用户ID',
                    'type' => 'integer',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'username' => array(
                    'description' => '用户名',
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                    'required' => true,
                ),
                'email' => array(
                    'description' => '邮箱地址',
                    'type' => 'string',
                    'format' => 'email',
                    'context' => array('view', 'edit'),
                    'required' => true,
                ),
                'display_name' => array(
                    'description' => '显示名称',
                    'type' => 'string',
                    'context' => array('view', 'edit'),
                ),
                'password' => array(
                    'description' => '密码',
                    'type' => 'string',
                    'context' => array('edit'),
                    'minLength' => 6,
                ),
                'is_active' => array(
                    'description' => '是否激活',
                    'type' => 'boolean',
                    'context' => array('view', 'edit'),
                ),
                'role' => array(
                    'description' => '用户角色',
                    'type' => 'string',
                    'context' => array('edit'),
                ),
                'roles' => array(
                    'description' => '用户角色列表',
                    'type' => 'array',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'created_at' => array(
                    'description' => '创建时间',
                    'type' => 'string',
                    'format' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'updated_at' => array(
                    'description' => '更新时间',
                    'type' => 'string',
                    'format' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
                'last_login_at' => array(
                    'description' => '最后登录时间',
                    'type' => 'string',
                    'format' => 'date-time',
                    'context' => array('view', 'edit'),
                    'readonly' => true,
                ),
            ),
        );

        return $this->add_additional_fields_schema($schema);
    }

    // 权限检查方法
    public function get_items_permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function get_item_permissions_check($request) {
        return current_user_can('manage_options') || get_current_user_id() == $request['id'];
    }

    public function create_item_permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function update_item_permissions_check($request) {
        return current_user_can('manage_options') || get_current_user_id() == $request['id'];
    }

    public function delete_item_permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function logout_permissions_check($request) {
        return $this->auth_manager->is_logged_in();
    }

    public function get_current_user_permissions_check($request) {
        return $this->auth_manager->is_logged_in();
    }

    public function update_password_permissions_check($request) {
        return current_user_can('manage_options') || get_current_user_id() == $request['id'];
    }

    /**
     * 生成认证令牌
     *
     * @param AMS_V2_User $user 用户对象
     * @return string 认证令牌
     */
    private function generate_auth_token($user) {
        $payload = array(
            'user_id' => $user->get_id(),
            'username' => $user->get_username(),
            'exp' => time() + (24 * 60 * 60), // 24小时过期
        );

        return base64_encode(json_encode($payload));
    }

    /**
     * 获取数据库管理器
     *
     * @return AMS_V2_Database_Manager
     */
    private function get_database_manager() {
        global $ams_v2_container;
        return $ams_v2_container->get('database_manager');
    }
}