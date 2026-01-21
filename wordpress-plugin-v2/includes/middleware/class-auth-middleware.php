<?php
/**
 * 认证中间件
 *
 * @package Article_Management_V2
 * @subpackage Middleware
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 认证中间件类
 */
class AMS_V2_Auth_Middleware {

    /**
     * 认证管理器
     *
     * @var AMS_V2_Authentication_Manager
     */
    private $auth_manager;

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
     * @param AMS_V2_Logger $logger 日志记录器
     */
    public function __construct($auth_manager, $logger) {
        $this->auth_manager = $auth_manager;
        $this->logger = $logger;
    }

    /**
     * 初始化中间件
     */
    public function init() {
        // 注册REST API认证过滤器
        add_filter('rest_authentication_errors', array($this, 'rest_authentication_errors'));
        
        // 注册AJAX认证检查
        add_action('wp_ajax_nopriv_ams_v2_check_auth', array($this, 'ajax_check_auth'));
        add_action('wp_ajax_ams_v2_check_auth', array($this, 'ajax_check_auth'));
        
        // 注册管理页面访问检查
        add_action('admin_init', array($this, 'check_admin_access'));
        
        // 注册前端页面访问检查
        add_action('template_redirect', array($this, 'check_frontend_access'));
    }

    /**
     * REST API认证错误处理
     *
     * @param WP_Error|null|bool $result 认证结果
     * @return WP_Error|null|bool 处理后的认证结果
     */
    public function rest_authentication_errors($result) {
        // 如果已经有错误，直接返回
        if (!empty($result)) {
            return $result;
        }

        // 检查是否为AMS V2的API请求
        $request_uri = $_SERVER['REQUEST_URI'];
        if (strpos($request_uri, '/wp-json/ams-v2/') === false) {
            return $result;
        }

        // 公开端点不需要认证
        $public_endpoints = array(
            '/wp-json/ams-v2/v1/auth/login',
        );

        foreach ($public_endpoints as $endpoint) {
            if (strpos($request_uri, $endpoint) !== false) {
                return $result;
            }
        }

        // 检查用户是否已登录
        $current_user = $this->auth_manager->get_current_user();
        if (!$current_user) {
            return new WP_Error(
                'rest_not_logged_in',
                '您需要登录才能访问此资源',
                array('status' => 401)
            );
        }

        // 检查用户是否激活
        if (!$current_user->is_active()) {
            return new WP_Error(
                'rest_user_inactive',
                '您的账户已被禁用',
                array('status' => 403)
            );
        }

        return $result;
    }

    /**
     * AJAX认证检查
     */
    public function ajax_check_auth() {
        $current_user = $this->auth_manager->get_current_user();
        
        if (!$current_user) {
            wp_send_json_error(array(
                'message' => '用户未登录',
                'code' => 'not_logged_in'
            ), 401);
        }

        if (!$current_user->is_active()) {
            wp_send_json_error(array(
                'message' => '用户账户已被禁用',
                'code' => 'user_inactive'
            ), 403);
        }

        wp_send_json_success(array(
            'user_id' => $current_user->get_id(),
            'username' => $current_user->get_username(),
            'display_name' => $current_user->get_display_name(),
            'is_administrator' => $current_user->is_administrator(),
        ));
    }

    /**
     * 检查管理页面访问权限
     */
    public function check_admin_access() {
        // 只检查AMS V2的管理页面
        if (!isset($_GET['page']) || strpos($_GET['page'], 'article-management-v2') !== 0) {
            return;
        }

        $current_user = $this->auth_manager->get_current_user();
        
        // 如果用户未登录，重定向到登录页面
        if (!$current_user) {
            $this->redirect_to_login();
            return;
        }

        // 如果用户被禁用，显示错误信息
        if (!$current_user->is_active()) {
            wp_die('您的账户已被禁用，无法访问此页面。', '访问被拒绝', array('response' => 403));
        }

        // 检查页面特定权限
        $page = $_GET['page'];
        $required_capability = $this->get_page_required_capability($page);
        
        if ($required_capability && !$current_user->has_capability($required_capability)) {
            wp_die('您没有权限访问此页面。', '权限不足', array('response' => 403));
        }

        $this->logger->debug('Admin page access granted', array(
            'user_id' => $current_user->get_id(),
            'page' => $page,
            'capability' => $required_capability
        ));
    }

    /**
     * 检查前端页面访问权限
     */
    public function check_frontend_access() {
        // 检查是否为AMS V2的前端页面
        global $wp_query;
        
        if (!isset($wp_query->query_vars['ams_v2_page'])) {
            return;
        }

        $page = $wp_query->query_vars['ams_v2_page'];
        $current_user = $this->auth_manager->get_current_user();

        // 公开页面不需要认证
        $public_pages = array('login', 'register');
        if (in_array($page, $public_pages)) {
            return;
        }

        // 检查用户是否已登录
        if (!$current_user) {
            $this->redirect_to_login();
            return;
        }

        // 检查用户是否激活
        if (!$current_user->is_active()) {
            wp_die('您的账户已被禁用。', '账户被禁用', array('response' => 403));
        }

        // 检查页面特定权限
        $required_capability = $this->get_frontend_page_required_capability($page);
        
        if ($required_capability && !$current_user->has_capability($required_capability)) {
            wp_die('您没有权限访问此页面。', '权限不足', array('response' => 403));
        }
    }

    /**
     * 验证AJAX请求权限
     *
     * @param string $action AJAX动作
     * @param string $capability 所需权限
     * @return bool|WP_Error 验证结果
     */
    public function verify_ajax_permission($action, $capability = null) {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            return new WP_Error('invalid_nonce', '安全验证失败');
        }

        $current_user = $this->auth_manager->get_current_user();
        
        // 检查用户是否已登录
        if (!$current_user) {
            return new WP_Error('not_logged_in', '用户未登录');
        }

        // 检查用户是否激活
        if (!$current_user->is_active()) {
            return new WP_Error('user_inactive', '用户账户已被禁用');
        }

        // 检查权限
        if ($capability && !$current_user->has_capability($capability)) {
            return new WP_Error('insufficient_permission', '权限不足');
        }

        $this->logger->debug('AJAX permission verified', array(
            'user_id' => $current_user->get_id(),
            'action' => $action,
            'capability' => $capability
        ));

        return true;
    }

    /**
     * 验证REST API请求权限
     *
     * @param WP_REST_Request $request 请求对象
     * @param string $capability 所需权限
     * @return bool|WP_Error 验证结果
     */
    public function verify_rest_permission($request, $capability = null) {
        $current_user = $this->auth_manager->get_current_user();
        
        // 检查用户是否已登录
        if (!$current_user) {
            return new WP_Error('rest_not_logged_in', '用户未登录', array('status' => 401));
        }

        // 检查用户是否激活
        if (!$current_user->is_active()) {
            return new WP_Error('rest_user_inactive', '用户账户已被禁用', array('status' => 403));
        }

        // 检查权限
        if ($capability && !$current_user->has_capability($capability)) {
            return new WP_Error('rest_insufficient_permission', '权限不足', array('status' => 403));
        }

        return true;
    }

    /**
     * 检查用户是否可以访问资源
     *
     * @param mixed $resource 资源对象
     * @param string $action 操作类型
     * @return bool|WP_Error 检查结果
     */
    public function can_access_resource($resource, $action = 'view') {
        $current_user = $this->auth_manager->get_current_user();
        
        if (!$current_user) {
            return new WP_Error('not_logged_in', '用户未登录');
        }

        if (!$current_user->is_active()) {
            return new WP_Error('user_inactive', '用户账户已被禁用');
        }

        // 使用认证管理器检查权限
        $has_permission = $this->auth_manager->check_permission($current_user, $action, $resource);
        
        if (!$has_permission) {
            return new WP_Error('insufficient_permission', '权限不足');
        }

        return true;
    }

    /**
     * 重定向到登录页面
     */
    private function redirect_to_login() {
        $login_url = wp_login_url(admin_url($_SERVER['REQUEST_URI']));
        wp_redirect($login_url);
        exit;
    }

    /**
     * 获取管理页面所需权限
     *
     * @param string $page 页面标识
     * @return string|null 所需权限
     */
    private function get_page_required_capability($page) {
        $page_capabilities = array(
            'article-management-v2' => 'view_dashboard',
            'article-management-v2-users' => 'manage_users',
            'article-management-v2-articles' => 'edit_posts',
            'article-management-v2-sites' => 'manage_sites',
            'article-management-v2-sensitive-words' => 'manage_sensitive_words',
            'article-management-v2-statistics' => 'view_statistics',
            'article-management-v2-settings' => 'manage_options',
        );

        return isset($page_capabilities[$page]) ? $page_capabilities[$page] : null;
    }

    /**
     * 获取前端页面所需权限
     *
     * @param string $page 页面标识
     * @return string|null 所需权限
     */
    private function get_frontend_page_required_capability($page) {
        $page_capabilities = array(
            'dashboard' => 'view_dashboard',
            'articles' => 'create_articles',
            'profile' => null, // 所有登录用户都可以访问
        );

        return isset($page_capabilities[$page]) ? $page_capabilities[$page] : null;
    }

    /**
     * 获取当前用户
     *
     * @return AMS_V2_User|null 当前用户对象
     */
    public function get_current_user() {
        return $this->auth_manager->get_current_user();
    }

    /**
     * 检查当前用户是否已登录
     *
     * @return bool 是否已登录
     */
    public function is_logged_in() {
        return $this->auth_manager->is_logged_in();
    }

    /**
     * 检查当前用户是否有指定权限
     *
     * @param string $capability 权限名称
     * @return bool 是否有权限
     */
    public function current_user_can($capability) {
        $current_user = $this->auth_manager->get_current_user();
        
        if (!$current_user) {
            return false;
        }

        return $current_user->has_capability($capability);
    }

    /**
     * 检查当前用户是否为管理员
     *
     * @return bool 是否为管理员
     */
    public function is_current_user_admin() {
        $current_user = $this->auth_manager->get_current_user();
        
        if (!$current_user) {
            return false;
        }

        return $current_user->is_administrator();
    }
}