<?php
/**
 * 管理后台类
 *
 * @package Article_Management_V2
 * @subpackage Admin
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 管理后台类
 */
class AMS_V2_Admin {

    /**
     * 插件名称
     *
     * @var string
     */
    private $plugin_name;

    /**
     * 插件版本
     *
     * @var string
     */
    private $version;

    /**
     * 依赖注入容器
     *
     * @var AMS_V2_Container
     */
    private $container;

    /**
     * 构造函数
     *
     * @param string $plugin_name 插件名称
     * @param string $version 插件版本
     * @param AMS_V2_Container $container 依赖注入容器
     */
    public function __construct($plugin_name, $version, $container) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->container = $container;
    }

    /**
     * 加载样式文件
     *
     * @param string $hook 当前页面钩子
     */
    public function enqueue_styles($hook) {
        // 只在插件页面加载样式
        if (!$this->is_plugin_page($hook)) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name,
            AMS_V2_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            $this->version,
            'all'
        );
        
        // 用户体验优化样式
        wp_enqueue_style(
            $this->plugin_name . '-ux',
            AMS_V2_PLUGIN_URL . 'admin/css/user-experience.css',
            array($this->plugin_name),
            $this->version,
            'all'
        );

        // 加载第三方样式
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style('jquery-ui-datepicker');
    }

    /**
     * 加载脚本文件
     *
     * @param string $hook 当前页面钩子
     */
    public function enqueue_scripts($hook) {
        // 只在插件页面加载脚本
        if (!$this->is_plugin_page($hook)) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name,
            AMS_V2_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery', 'wp-color-picker', 'jquery-ui-datepicker'),
            $this->version,
            false
        );
        
        // 用户体验优化脚本
        wp_enqueue_script(
            $this->plugin_name . '-ux',
            AMS_V2_PLUGIN_URL . 'admin/js/user-experience.js',
            array('jquery', $this->plugin_name),
            $this->version,
            false
        );

        // 本地化脚本
        wp_localize_script(
            $this->plugin_name,
            'ams_v2_admin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ams_v2_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'article-management-v2'),
                    'confirm_cleanup' => __('Are you sure you want to run cleanup? This action cannot be undone.', 'article-management-v2'),
                    'loading' => __('Loading...', 'article-management-v2'),
                    'error' => __('An error occurred. Please try again.', 'article-management-v2'),
                    'success' => __('Operation completed successfully.', 'article-management-v2')
                )
            )
        );
    }

    /**
     * 添加管理菜单
     */
    public function add_plugin_admin_menu() {
        // 主菜单
        add_menu_page(
            __('Article Management V2', 'article-management-v2'),
            __('Article Mgmt V2', 'article-management-v2'),
            'manage_options',
            $this->plugin_name,
            array($this, 'display_dashboard_page'),
            'dashicons-edit-large',
            30
        );

        // 仪表板
        add_submenu_page(
            $this->plugin_name,
            __('Dashboard', 'article-management-v2'),
            __('Dashboard', 'article-management-v2'),
            'manage_options',
            $this->plugin_name,
            array($this, 'display_dashboard_page')
        );

        // 用户管理
        add_submenu_page(
            $this->plugin_name,
            __('Users', 'article-management-v2'),
            __('Users', 'article-management-v2'),
            'manage_options',
            $this->plugin_name . '-users',
            array($this, 'display_users_page')
        );

        // 文章管理
        add_submenu_page(
            $this->plugin_name,
            __('Articles', 'article-management-v2'),
            __('Articles', 'article-management-v2'),
            'edit_posts',
            $this->plugin_name . '-articles',
            array($this, 'display_articles_page')
        );

        // 站点管理
        add_submenu_page(
            $this->plugin_name,
            __('Sites', 'article-management-v2'),
            __('Sites', 'article-management-v2'),
            'manage_options',
            $this->plugin_name . '-sites',
            array($this, 'display_sites_page')
        );

        // 敏感词管理
        add_submenu_page(
            $this->plugin_name,
            __('Sensitive Words', 'article-management-v2'),
            __('Sensitive Words', 'article-management-v2'),
            'manage_options',
            $this->plugin_name . '-sensitive-words',
            array($this, 'display_sensitive_words_page')
        );

        // 审核配置
        add_submenu_page(
            $this->plugin_name,
            __('Moderation Settings', 'article-management-v2'),
            __('Moderation Settings', 'article-management-v2'),
            'manage_options',
            $this->plugin_name . '-moderation-settings',
            array($this, 'display_moderation_settings_page')
        );

        // 统计报表
        add_submenu_page(
            $this->plugin_name,
            __('Statistics', 'article-management-v2'),
            __('Statistics', 'article-management-v2'),
            'manage_options',
            $this->plugin_name . '-statistics',
            array($this, 'display_statistics_page')
        );

        // 系统设置
        add_submenu_page(
            $this->plugin_name,
            __('Settings', 'article-management-v2'),
            __('Settings', 'article-management-v2'),
            'manage_options',
            $this->plugin_name . '-settings',
            array($this, 'display_settings_page')
        );

        // API设置
        add_submenu_page(
            $this->plugin_name,
            __('API Settings', 'article-management-v2'),
            __('API Settings', 'article-management-v2'),
            'manage_options',
            $this->plugin_name . '-api-settings',
            array($this, 'display_api_settings_page')
        );

        // 清理设置
        add_submenu_page(
            $this->plugin_name,
            __('Cleanup Settings', 'article-management-v2'),
            __('Cleanup Settings', 'article-management-v2'),
            'manage_options',
            $this->plugin_name . '-cleanup-settings',
            array($this, 'display_cleanup_settings_page')
        );

        // 审计日志
        add_submenu_page(
            $this->plugin_name,
            __('Audit Logs', 'article-management-v2'),
            __('Audit Logs', 'article-management-v2'),
            'manage_options',
            $this->plugin_name . '-audit-logs',
            array($this, 'display_audit_logs_page')
        );

        // 系统健康检查
        add_submenu_page(
            $this->plugin_name,
            __('System Health', 'article-management-v2'),
            __('System Health', 'article-management-v2'),
            'manage_options',
            $this->plugin_name . '-system-health',
            array($this, 'display_system_health_page')
        );

        // 测试页面（仅在开发环境显示）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_submenu_page(
                $this->plugin_name,
                __('Test User Role Management', 'article-management-v2'),
                __('Test', 'article-management-v2'),
                'manage_options',
                $this->plugin_name . '-test',
                array($this, 'display_test_page')
            );
            
            add_submenu_page(
                $this->plugin_name,
                __('Test Article Interface', 'article-management-v2'),
                __('Test Articles', 'article-management-v2'),
                'manage_options',
                $this->plugin_name . '-test-articles',
                array($this, 'display_article_test_page')
            );
            
            add_submenu_page(
                $this->plugin_name,
                __('Test Cleanup System', 'article-management-v2'),
                __('Test Cleanup', 'article-management-v2'),
                'manage_options',
                $this->plugin_name . '-test-cleanup',
                array($this, 'display_cleanup_test_page')
            );
            
            add_submenu_page(
                $this->plugin_name,
                __('Test Statistics System', 'article-management-v2'),
                __('Test Statistics', 'article-management-v2'),
                'manage_options',
                $this->plugin_name . '-test-statistics',
                array($this, 'display_statistics_test_page')
            );
            
            add_submenu_page(
                $this->plugin_name,
                __('Test Audit Logging System', 'article-management-v2'),
                __('Test Audit Logs', 'article-management-v2'),
                'manage_options',
                $this->plugin_name . '-test-audit-logs',
                array($this, 'display_audit_logging_test_page')
            );
        }
    }

    /**
     * 初始化管理后台
     */
    public function admin_init() {
        // 注册设置
        $this->register_settings();
        
        // 添加AJAX处理器
        $this->register_ajax_handlers();
    }

    /**
     * 注册设置
     */
    private function register_settings() {
        // 基本设置组
        register_setting('ams_v2_basic_settings', 'ams_v2_moderation_level');
        register_setting('ams_v2_basic_settings', 'ams_v2_cleanup_threshold_days');
        register_setting('ams_v2_basic_settings', 'ams_v2_auto_cleanup_enabled');
        register_setting('ams_v2_basic_settings', 'ams_v2_email_notifications');
        register_setting('ams_v2_basic_settings', 'ams_v2_items_per_page');

        // API设置组
        register_setting('ams_v2_api_settings', 'ams_v2_deepseek_api_key');
        register_setting('ams_v2_api_settings', 'ams_v2_api_timeout');
        register_setting('ams_v2_api_settings', 'ams_v2_api_retry_attempts');

        // 高级设置组
        register_setting('ams_v2_advanced_settings', 'ams_v2_log_retention_days');
        register_setting('ams_v2_advanced_settings', 'ams_v2_max_upload_size');
        register_setting('ams_v2_advanced_settings', 'ams_v2_allowed_file_types');
        register_setting('ams_v2_advanced_settings', 'ams_v2_enable_debug_mode');
    }

    /**
     * 注册AJAX处理器
     */
    private function register_ajax_handlers() {
        // 用户管理
        add_action('wp_ajax_ams_v2_create_user', array($this, 'ajax_create_user'));
        add_action('wp_ajax_ams_v2_update_user', array($this, 'ajax_update_user'));
        add_action('wp_ajax_ams_v2_delete_user', array($this, 'ajax_delete_user'));
        add_action('wp_ajax_ams_v2_get_user', array($this, 'ajax_get_user'));

        // 文章管理
        add_action('wp_ajax_ams_v2_get_article', array($this, 'ajax_get_article'));
        add_action('wp_ajax_ams_v2_delete_article', array($this, 'ajax_delete_article'));
        add_action('wp_ajax_ams_v2_moderate_article', array($this, 'ajax_moderate_article'));
        add_action('wp_ajax_ams_v2_bulk_moderate', array($this, 'ajax_bulk_moderate'));

        // 站点管理
        add_action('wp_ajax_ams_v2_test_site_connection', array($this, 'ajax_test_site_connection'));
        add_action('wp_ajax_ams_v2_sync_site', array($this, 'ajax_sync_site'));

        // 敏感词管理
        add_action('wp_ajax_ams_v2_import_sensitive_words', array($this, 'ajax_import_sensitive_words'));
        add_action('wp_ajax_ams_v2_export_sensitive_words', array($this, 'ajax_export_sensitive_words'));
        add_action('wp_ajax_ams_v2_get_word', array($this, 'ajax_get_word'));
        add_action('wp_ajax_ams_v2_update_word', array($this, 'ajax_update_word'));
        add_action('wp_ajax_ams_v2_delete_word', array($this, 'ajax_delete_word'));
        add_action('wp_ajax_ams_v2_bulk_delete_words', array($this, 'ajax_bulk_delete_words'));
        add_action('wp_ajax_ams_v2_toggle_word_status', array($this, 'ajax_toggle_word_status'));

        // 系统操作
        add_action('wp_ajax_ams_v2_run_cleanup', array($this, 'ajax_run_cleanup'));
        add_action('wp_ajax_ams_v2_get_statistics', array($this, 'ajax_get_statistics'));
        add_action('wp_ajax_ams_v2_get_realtime_statistics', array($this, 'ajax_get_realtime_statistics'));
        add_action('wp_ajax_ams_v2_check_api_status', array($this, 'ajax_check_api_status'));
        add_action('wp_ajax_ams_v2_get_cleanup_details', array($this, 'ajax_get_cleanup_details'));

        // 审计日志
        add_action('wp_ajax_ams_v2_get_audit_logs', array($this, 'ajax_get_audit_logs'));
        add_action('wp_ajax_ams_v2_export_audit_logs', array($this, 'ajax_export_audit_logs'));
        add_action('wp_ajax_ams_v2_get_audit_log_details', array($this, 'ajax_get_audit_log_details'));
        
        // 用户体验优化
        add_action('wp_ajax_ams_v2_bulk_action', array($this, 'ajax_bulk_action'));
    }

    /**
     * 显示仪表板页面
     */
    public function display_dashboard_page() {
        include_once AMS_V2_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }

    /**
     * 显示用户管理页面
     */
    public function display_users_page() {
        include_once AMS_V2_PLUGIN_DIR . 'admin/partials/users.php';
    }

    /**
     * 显示文章管理页面
     */
    public function display_articles_page() {
        include_once AMS_V2_PLUGIN_DIR . 'admin/partials/articles.php';
    }

    /**
     * 显示站点管理页面
     */
    public function display_sites_page() {
        include_once AMS_V2_PLUGIN_DIR . 'admin/partials/sites.php';
    }

    /**
     * 显示敏感词管理页面
     */
    public function display_sensitive_words_page() {
        include_once AMS_V2_PLUGIN_DIR . 'admin/partials/sensitive-words.php';
    }

    /**
     * 显示审核配置页面
     */
    public function display_moderation_settings_page() {
        include_once AMS_V2_PLUGIN_DIR . 'admin/partials/moderation-settings.php';
    }

    /**
     * 显示统计页面
     */
    public function display_statistics_page() {
        include_once AMS_V2_PLUGIN_DIR . 'admin/partials/statistics.php';
    }

    /**
     * 显示设置页面
     */
    public function display_settings_page() {
        include_once AMS_V2_PLUGIN_DIR . 'admin/partials/settings.php';
    }

    /**
     * 显示API设置页面
     */
    public function display_api_settings_page() {
        include_once AMS_V2_PLUGIN_DIR . 'admin/partials/api-settings.php';
    }

    /**
     * 显示清理设置页面
     */
    public function display_cleanup_settings_page() {
        include_once AMS_V2_PLUGIN_DIR . 'admin/partials/cleanup-settings.php';
    }

    /**
     * 显示审计日志页面
     */
    public function display_audit_logs_page() {
        include_once AMS_V2_PLUGIN_DIR . 'admin/partials/audit-logs.php';
    }

    /**
     * 显示系统健康检查页面
     */
    public function display_system_health_page() {
        include_once AMS_V2_PLUGIN_DIR . 'admin/partials/system-health.php';
    }

    /**
     * 显示文章界面测试页面
     */
    public function display_article_test_page() {
        echo '<div class="wrap">';
        echo '<h1>文章管理界面功能测试</h1>';
        
        if (isset($_GET['run_tests'])) {
            // 运行文章界面测试
            require_once AMS_V2_PLUGIN_DIR . 'test-article-interface.php';
            $test = new AMS_V2_Article_Interface_Test($this->container);
            $results = $test->run_all_tests();
            $test->output_results($results);
            
            echo '<p><a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-test-articles') . '" class="button">返回测试页面</a></p>';
            
        } else {
            echo '<p>测试文章管理界面的各项功能，包括用户登录验证、基于角色的文章列表过滤和管理员审核界面。</p>';
            echo '<div style="margin: 20px 0;">';
            echo '<a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-test-articles&run_tests=true') . '" class="button button-primary">运行文章界面测试</a>';
            echo '</div>';
            
            echo '<div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0;">';
            echo '<h4>测试内容：</h4>';
            echo '<ul>';
            echo '<li><strong>用户登录验证</strong>: 测试前端表单的用户登录验证功能</li>';
            echo '<li><strong>基于角色的文章列表过滤</strong>: 测试普通用户只能查看自己的文章，管理员可以查看所有文章</li>';
            echo '<li><strong>管理员审核界面</strong>: 测试仅管理员可见的文章审核功能</li>';
            echo '<li><strong>文章状态管理</strong>: 测试文章状态枚举和验证功能</li>';
            echo '<li><strong>权限控制</strong>: 测试各种权限验证方法</li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">';
            echo '<h4>需求验证：</h4>';
            echo '<p>此测试验证以下需求的实现：</p>';
            echo '<ul>';
            echo '<li><strong>需求 1.4</strong>: 普通用户查看文章列表时，系统应当仅显示该用户创建的文章</li>';
            echo '<li><strong>需求 1.5</strong>: 管理员查看文章列表时，系统应当显示所有用户的文章</li>';
            echo '<li><strong>需求 2.4</strong>: 用户未登录时，系统应当阻止文章提交并要求登录</li>';
            echo '</ul>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * 显示测试页面
     */
    public function display_test_page() {
        echo '<div class="wrap">';
        echo '<h1>用户角色管理系统测试</h1>';
        
        if (isset($_GET['run_tests'])) {
            $test_type = $_GET['run_tests'];
            
            if ($test_type === 'basic') {
                // 运行基础单元测试
                require_once AMS_V2_PLUGIN_DIR . 'tests/unit-test-basic.php';
                $test = new AMS_V2_Basic_Unit_Test();
                $results = $test->run_all_tests();
                $test->output_results($results);
                
            } elseif ($test_type === 'integration') {
                // 运行集成测试
                require_once AMS_V2_PLUGIN_DIR . 'tests/test-user-role-management.php';
                $test = new AMS_V2_User_Role_Management_Test($this->container);
                $results = $test->run_all_tests();
                $test->output_results($results);
            }
            
            echo '<p><a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-test') . '" class="button">返回测试页面</a></p>';
            
        } else {
            echo '<p>选择要运行的测试类型：</p>';
            echo '<div style="margin: 20px 0;">';
            echo '<a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-test&run_tests=basic') . '" class="button button-primary" style="margin-right: 10px;">运行基础单元测试</a>';
            echo '<a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-test&run_tests=integration') . '" class="button button-secondary">运行集成测试</a>';
            echo '</div>';
            
            echo '<div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0;">';
            echo '<h4>测试说明：</h4>';
            echo '<ul>';
            echo '<li><strong>基础单元测试</strong>: 测试模型类的基本功能，不需要数据库连接</li>';
            echo '<li><strong>集成测试</strong>: 测试完整的用户角色管理功能，需要数据库连接</li>';
            echo '</ul>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * 检查是否为插件页面
     *
     * @param string $hook 页面钩子
     * @return bool
     */
    private function is_plugin_page($hook) {
        $plugin_pages = array(
            'toplevel_page_' . $this->plugin_name,
            $this->plugin_name . '_page_' . $this->plugin_name . '-users',
            $this->plugin_name . '_page_' . $this->plugin_name . '-articles',
            $this->plugin_name . '_page_' . $this->plugin_name . '-sites',
            $this->plugin_name . '_page_' . $this->plugin_name . '-sensitive-words',
            $this->plugin_name . '_page_' . $this->plugin_name . '-moderation-settings',
            $this->plugin_name . '_page_' . $this->plugin_name . '-statistics',
            $this->plugin_name . '_page_' . $this->plugin_name . '-settings',
            $this->plugin_name . '_page_' . $this->plugin_name . '-api-settings',
            $this->plugin_name . '_page_' . $this->plugin_name . '-cleanup-settings',
            $this->plugin_name . '_page_' . $this->plugin_name . '-audit-logs',
            $this->plugin_name . '_page_' . $this->plugin_name . '-test',
            $this->plugin_name . '_page_' . $this->plugin_name . '-test-articles',
            $this->plugin_name . '_page_' . $this->plugin_name . '-test-cleanup',
            $this->plugin_name . '_page_' . $this->plugin_name . '-test-statistics',
            $this->plugin_name . '_page_' . $this->plugin_name . '-test-audit-logs'
        );

        return in_array($hook, $plugin_pages);
    }

    // AJAX处理器方法
    public function ajax_create_user() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $auth_manager = $this->container->get('authentication_manager');
        
        $user_data = array(
            'username' => sanitize_text_field($_POST['username']),
            'email' => sanitize_email($_POST['email']),
            'password' => $_POST['password'],
            'display_name' => sanitize_text_field($_POST['display_name']),
            'is_active' => isset($_POST['is_active']) ? (bool) $_POST['is_active'] : true,
            'role' => sanitize_text_field($_POST['role'])
        );

        $result = $auth_manager->create_user($user_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => '用户创建成功',
                'user' => $result->to_array()
            ));
        }
    }

    public function ajax_update_user() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $auth_manager = $this->container->get('authentication_manager');
        $user_id = (int) $_POST['user_id'];
        
        $user = $auth_manager->get_user_by_id($user_id);
        if (!$user) {
            wp_send_json_error('用户不存在');
            return;
        }

        $user->set_email(sanitize_email($_POST['email']));
        $user->set_display_name(sanitize_text_field($_POST['display_name']));
        $user->set_active(isset($_POST['is_active']) ? (bool) $_POST['is_active'] : false);

        $result = $auth_manager->update_user($user);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => '用户更新成功',
                'user' => $user->to_array()
            ));
        }
    }

    public function ajax_delete_user() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $auth_manager = $this->container->get('authentication_manager');
        $user_id = (int) $_POST['user_id'];

        $result = $auth_manager->delete_user($user_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('用户删除成功');
        }
    }

    public function ajax_get_user() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $auth_manager = $this->container->get('authentication_manager');
        $user_id = (int) $_POST['user_id'];
        
        $user = $auth_manager->get_user_by_id($user_id);
        if (!$user) {
            wp_send_json_error('用户不存在');
            return;
        }

        wp_send_json_success($user->to_array());
    }

    public function ajax_get_article() {
        // 验证权限和nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $auth_middleware = $this->container->get('auth_middleware');
        $current_user = $auth_middleware->get_current_user();
        
        if (!$current_user) {
            wp_send_json_error('用户未登录');
            return;
        }

        $article_manager = $this->container->get('article_manager');
        $article_id = (int) $_POST['article_id'];
        
        $article = $article_manager->get_article_by_id($article_id);
        if (!$article) {
            wp_send_json_error('文章不存在');
            return;
        }

        // 检查查看权限
        if (!$article_manager->can_view_article($current_user, $article)) {
            wp_send_json_error('您没有查看此文章的权限');
            return;
        }

        $article_data = $article->to_array();
        
        // 添加作者信息
        $author = $article->get_author();
        if ($author) {
            $article_data['author'] = array(
                'id' => $author->get_id(),
                'username' => $author->get_username(),
                'display_name' => $author->get_display_name(),
                'email' => $author->get_email()
            );
        }

        wp_send_json_success($article_data);
    }

    public function ajax_delete_article() {
        // 验证权限和nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $auth_middleware = $this->container->get('auth_middleware');
        $current_user = $auth_middleware->get_current_user();
        
        if (!$current_user) {
            wp_send_json_error('用户未登录');
            return;
        }

        $article_manager = $this->container->get('article_manager');
        $article_id = (int) $_POST['article_id'];
        
        $article = $article_manager->get_article_by_id($article_id);
        if (!$article) {
            wp_send_json_error('文章不存在');
            return;
        }

        $result = $article_manager->delete_article($article, $current_user);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('文章删除成功');
        }
    }

    public function ajax_moderate_article() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $auth_middleware = $this->container->get('auth_middleware');
        $current_user = $auth_middleware->get_current_user();
        
        if (!$current_user || !$current_user->is_administrator()) {
            wp_send_json_error('只有管理员可以审核文章');
            return;
        }

        $article_manager = $this->container->get('article_manager');
        $article_id = (int) $_POST['article_id'];
        $new_status = sanitize_text_field($_POST['new_status']);
        $notes = sanitize_textarea_field($_POST['moderation_notes']);
        
        $article = $article_manager->get_article_by_id($article_id);
        if (!$article) {
            wp_send_json_error('文章不存在');
            return;
        }

        $result = $article_manager->change_status($article, $new_status, $current_user, $notes);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => '文章状态更新成功',
                'new_status' => $new_status
            ));
        }
    }

    public function ajax_bulk_moderate() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $auth_middleware = $this->container->get('auth_middleware');
        $current_user = $auth_middleware->get_current_user();
        
        if (!$current_user || !$current_user->is_administrator()) {
            wp_send_json_error('只有管理员可以批量审核文章');
            return;
        }

        $article_manager = $this->container->get('article_manager');
        $article_ids = array_map('intval', $_POST['ids']);
        $action = sanitize_text_field($_POST['bulk_action']);
        
        // 映射批量操作到状态
        $status_map = array(
            'approve' => 'approved',
            'reject' => 'rejected',
            'publish' => 'published',
            'archive' => 'archived'
        );
        
        if ($action === 'delete') {
            $success_count = 0;
            $error_count = 0;
            
            foreach ($article_ids as $article_id) {
                $article = $article_manager->get_article_by_id($article_id);
                if ($article) {
                    $result = $article_manager->delete_article($article, $current_user);
                    if (is_wp_error($result)) {
                        $error_count++;
                    } else {
                        $success_count++;
                    }
                } else {
                    $error_count++;
                }
            }
            
            $message = "成功删除 {$success_count} 篇文章";
            if ($error_count > 0) {
                $message .= "，{$error_count} 篇文章删除失败";
            }
            
            wp_send_json_success($message);
            
        } elseif (isset($status_map[$action])) {
            $new_status = $status_map[$action];
            $results = $article_manager->batch_update_status($article_ids, $new_status, $current_user);
            
            $success_count = count($results['success']);
            $error_count = count($results['failed']);
            
            $message = "成功处理 {$success_count} 篇文章";
            if ($error_count > 0) {
                $message .= "，{$error_count} 篇文章处理失败";
            }
            
            wp_send_json_success($message);
        } else {
            wp_send_json_error('无效的批量操作');
        }
    }

    public function ajax_test_site_connection() {
        // TODO: 实现站点连接测试逻辑
        wp_die();
    }

    public function ajax_sync_site() {
        // TODO: 实现站点同步逻辑
        wp_die();
    }

    public function ajax_import_sensitive_words() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('文件上传失败');
            return;
        }

        $file = $_FILES['import_file'];
        $import_type = sanitize_text_field($_POST['import_type']);

        // 验证文件类型
        $allowed_types = array('text/plain', 'text/csv', 'application/json');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('不支持的文件类型');
            return;
        }

        try {
            $initializer = new AMS_V2_Sensitive_Words_Initializer();
            $result = $initializer->import_from_file($file['tmp_name'], $import_type);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result['message']);
            }
        } catch (Exception $e) {
            wp_send_json_error('导入过程中发生错误: ' . $e->getMessage());
        }
    }

    public function ajax_export_sensitive_words() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['nonce'], 'ams_v2_admin_nonce')) {
            wp_die('权限不足或安全验证失败');
            return;
        }

        try {
            $word_manager = new AMS_V2_Sensitive_Word_Manager();
            
            // 获取过滤条件
            $filters = array();
            if (!empty($_GET['filter_type'])) {
                $filters['type'] = sanitize_text_field($_GET['filter_type']);
            }
            if (!empty($_GET['search'])) {
                $filters['search'] = sanitize_text_field($_GET['search']);
            }
            
            // 获取所有符合条件的敏感词
            if (!empty($filters['search'])) {
                $words_data = $word_manager->search_words($filters['search'], array_merge($filters, array('limit' => 10000)));
            } else {
                $words_data = $word_manager->get_words(array_merge($filters, array('limit' => 10000)));
            }

            if (empty($words_data['words'])) {
                wp_die('没有找到符合条件的敏感词');
                return;
            }

            // 设置CSV下载头
            $filename = 'sensitive-words-' . date('Y-m-d') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            // 输出CSV内容
            $output = fopen('php://output', 'w');
            
            // 添加BOM以支持中文
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // 写入标题行
            fputcsv($output, array('敏感词', '类型', '替换词', '来源', '状态', '创建时间'));
            
            // 写入数据行
            foreach ($words_data['words'] as $word) {
                fputcsv($output, array(
                    $word->word,
                    $word->get_type_display_name(),
                    $word->replacement ?: $word->get_default_replacement(),
                    $word->is_builtin ? '内置' : '自定义',
                    $word->is_active ? '激活' : '停用',
                    $word->created_at
                ));
            }
            
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            wp_die('导出过程中发生错误: ' . $e->getMessage());
        }
    }

    public function ajax_get_word() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $word_id = (int) $_POST['word_id'];
        $word_manager = new AMS_V2_Sensitive_Word_Manager();
        
        $word = $word_manager->get_word_by_id($word_id);
        if (!$word) {
            wp_send_json_error('敏感词不存在');
            return;
        }

        wp_send_json_success($word->to_array());
    }

    public function ajax_update_word() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $word_id = (int) $_POST['word_id'];
        $word_manager = new AMS_V2_Sensitive_Word_Manager();
        
        $update_data = array(
            'word' => sanitize_text_field($_POST['word']),
            'type' => sanitize_text_field($_POST['type']),
            'replacement' => sanitize_text_field($_POST['replacement']),
            'is_active' => isset($_POST['is_active']) && $_POST['is_active'] == '1'
        );

        $result = $word_manager->update_word($word_id, $update_data);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => '敏感词更新成功',
                'word' => $result->to_array()
            ));
        }
    }

    public function ajax_delete_word() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $word_id = (int) $_POST['word_id'];
        $word_manager = new AMS_V2_Sensitive_Word_Manager();

        $result = $word_manager->delete_word($word_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('敏感词删除成功');
        }
    }

    public function ajax_bulk_delete_words() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $word_ids = array_map('intval', $_POST['word_ids']);
        if (empty($word_ids)) {
            wp_send_json_error('请选择要删除的敏感词');
            return;
        }

        $word_manager = new AMS_V2_Sensitive_Word_Manager();
        $result = $word_manager->delete_words($word_ids);

        $message = sprintf('批量删除完成：成功 %d 个，失败 %d 个', 
                          $result['success'], $result['failed']);

        if ($result['failed'] > 0 && !empty($result['errors'])) {
            $message .= '。错误信息：' . implode('; ', array_slice($result['errors'], 0, 3));
        }

        wp_send_json_success(array('message' => $message));
    }

    public function ajax_toggle_word_status() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $word_id = (int) $_POST['word_id'];
        $is_active = isset($_POST['is_active']) && $_POST['is_active'] == '1';
        
        $word_manager = new AMS_V2_Sensitive_Word_Manager();
        $result = $word_manager->toggle_word_status($word_id, $is_active);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('状态更新成功');
        }
    }

    public function ajax_run_cleanup() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $auth_middleware = $this->container->get('auth_middleware');
        $current_user = $auth_middleware->get_current_user();
        
        if (!$current_user || !$current_user->is_administrator()) {
            wp_send_json_error('只有管理员可以执行清理操作');
            return;
        }

        $cleanup_scheduler = $this->container->get('cleanup_scheduler');
        $custom_threshold = !empty($_POST['threshold_days']) ? (int) $_POST['threshold_days'] : null;
        
        $result = $cleanup_scheduler->trigger_manual_cleanup($current_user, $custom_threshold);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    public function ajax_get_cleanup_details() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $log_id = (int) $_POST['log_id'];
        $cleanup_logs_table = $this->container->get('database_manager')->get_table_name('cleanup_logs');
        
        global $wpdb;
        $log = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$cleanup_logs_table} WHERE id = %d",
                $log_id
            )
        );

        if (!$log) {
            wp_send_json_error('清理日志不存在');
            return;
        }

        $html = '<div class="cleanup-details">';
        $html .= '<h4>基本信息</h4>';
        $html .= '<table class="form-table">';
        $html .= '<tr><th>清理类型:</th><td>' . ($log->cleanup_type === 'auto' ? '自动清理' : '手动清理') . '</td></tr>';
        $html .= '<tr><th>开始时间:</th><td>' . esc_html($log->started_at) . '</td></tr>';
        $html .= '<tr><th>完成时间:</th><td>' . esc_html($log->completed_at ?: '未完成') . '</td></tr>';
        $html .= '<tr><th>状态:</th><td>' . esc_html($log->status) . '</td></tr>';
        $html .= '<tr><th>阈值天数:</th><td>' . $log->threshold_days . ' 天</td></tr>';
        $html .= '<tr><th>删除数量:</th><td>' . $log->articles_deleted . ' 篇文章</td></tr>';
        $html .= '</table>';

        if ($log->error_message) {
            $html .= '<h4>错误信息</h4>';
            $html .= '<p class="error-message">' . esc_html($log->error_message) . '</p>';
        }

        if ($log->deleted_articles) {
            $deleted_articles = json_decode($log->deleted_articles, true);
            if (!empty($deleted_articles)) {
                $html .= '<h4>删除的文章</h4>';
                $html .= '<table class="wp-list-table widefat">';
                $html .= '<thead><tr><th>ID</th><th>标题</th><th>作者ID</th><th>状态</th><th>创建时间</th></tr></thead>';
                $html .= '<tbody>';
                foreach (array_slice($deleted_articles, 0, 20) as $article) {
                    $html .= '<tr>';
                    $html .= '<td>' . $article['id'] . '</td>';
                    $html .= '<td>' . esc_html($article['title']) . '</td>';
                    $html .= '<td>' . $article['author_id'] . '</td>';
                    $html .= '<td>' . esc_html($article['status']) . '</td>';
                    $html .= '<td>' . esc_html($article['created_at']) . '</td>';
                    $html .= '</tr>';
                }
                $html .= '</tbody></table>';
                
                if (count($deleted_articles) > 20) {
                    $html .= '<p><em>只显示前20条记录，共 ' . count($deleted_articles) . ' 条</em></p>';
                }
            }
        }

        $html .= '</div>';

        wp_send_json_success(array('html' => $html));
    }

    public function ajax_get_statistics() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $statistics_collector = $this->container->get('statistics_collector');
        
        $type = sanitize_text_field($_POST['type'] ?? 'user');
        $period = sanitize_text_field($_POST['period'] ?? 'daily');
        $date_from = sanitize_text_field($_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
        $date_to = sanitize_text_field($_POST['date_to'] ?? date('Y-m-d'));
        $user_id = !empty($_POST['user_id']) ? (int) $_POST['user_id'] : null;

        try {
            switch ($type) {
                case 'user':
                    $data = $statistics_collector->collect_user_statistics($user_id, $period, $date_from, $date_to);
                    break;
                case 'system':
                    $data = $statistics_collector->collect_system_statistics($period, $date_from, $date_to);
                    break;
                case 'moderation':
                    $data = $statistics_collector->get_moderation_statistics($period, $date_from, $date_to);
                    break;
                case 'sensitive_words':
                    $data = $statistics_collector->get_sensitive_word_statistics($period, $date_from, $date_to);
                    break;
                case 'sites':
                    $site_id = !empty($_POST['site_id']) ? (int) $_POST['site_id'] : null;
                    $data = $statistics_collector->get_site_publish_statistics($site_id, $period, $date_from, $date_to);
                    break;
                default:
                    wp_send_json_error('无效的统计类型');
                    return;
            }

            wp_send_json_success($data);

        } catch (Exception $e) {
            wp_send_json_error('获取统计数据失败: ' . $e->getMessage());
        }
    }

    public function ajax_get_realtime_statistics() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        try {
            $statistics_collector = $this->container->get('statistics_collector');
            $data = $statistics_collector->get_realtime_statistics();
            wp_send_json_success($data);

        } catch (Exception $e) {
            wp_send_json_error('获取实时统计数据失败: ' . $e->getMessage());
        }
    }

    public function ajax_check_api_status() {
        // TODO: 实现API状态检查逻辑
        wp_die();
    }

    /**
     * 显示统计系统测试页面
     */
    public function display_statistics_test_page() {
        echo '<div class="wrap">';
        echo '<h1>统计系统功能测试</h1>';
        
        if (isset($_GET['run_tests'])) {
            // 运行统计系统测试
            require_once AMS_V2_PLUGIN_DIR . 'test-statistics-system.php';
            $test = new AMS_V2_Statistics_System_Test($this->container);
            $results = $test->run_all_tests();
            $test->output_results($results);
            
            echo '<p><a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-test-statistics') . '" class="button">返回测试页面</a></p>';
            
        } else {
            echo '<p>测试统计系统的各项功能，包括用户统计收集、系统统计收集、审核统计、实时统计、缓存机制和CSV导出功能。</p>';
            echo '<div style="margin: 20px 0;">';
            echo '<a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-test-statistics&run_tests=true') . '" class="button button-primary">运行统计系统测试</a>';
            echo '</div>';
            
            echo '<div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0;">';
            echo '<h4>测试内容：</h4>';
            echo '<ul>';
            echo '<li><strong>用户统计收集</strong>: 测试按用户收集文章提交、审核和发布统计</li>';
            echo '<li><strong>系统统计收集</strong>: 测试系统级别的活跃用户、新增文章和发布率统计</li>';
            echo '<li><strong>审核统计</strong>: 测试不同审核状态的文章统计和审核效率分析</li>';
            echo '<li><strong>实时统计</strong>: 测试今日统计、总体统计和最近活动的实时数据</li>';
            echo '<li><strong>用户发布统计</strong>: 测试用户发布趋势和按时间维度的统计</li>';
            echo '<li><strong>统计缓存</strong>: 测试统计数据的缓存机制和性能优化</li>';
            echo '<li><strong>CSV导出</strong>: 测试统计数据的CSV格式导出功能</li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">';
            echo '<h4>需求验证：</h4>';
            echo '<p>此测试验证以下需求的实现：</p>';
            echo '<ul>';
            echo '<li><strong>需求 6.1</strong>: 管理员查看统计报表时，系统应当显示用户文章提交和发布统计</li>';
            echo '<li><strong>需求 6.2</strong>: 统计系统应当按日、周、月维度聚合数据</li>';
            echo '<li><strong>需求 6.3</strong>: 管理员导出统计数据时，系统应当生成CSV格式文件</li>';
            echo '<li><strong>需求 6.4</strong>: 统计页面应当显示实时更新的数据</li>';
            echo '<li><strong>需求 6.5</strong>: 系统应当提供用户发布趋势和审核效率分析</li>';
            echo '</ul>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * 显示清理系统测试页面
     */
    public function display_cleanup_test_page() {
        echo '<div class="wrap">';
        echo '<h1>清理系统功能测试</h1>';
        
        if (isset($_GET['run_tests'])) {
            // 运行清理系统测试
            require_once AMS_V2_PLUGIN_DIR . 'test-cleanup-system.php';
            $test = new AMS_V2_Cleanup_System_Test($this->container);
            $results = $test->run_all_tests();
            $test->output_results($results);
            
            echo '<p><a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-test-cleanup') . '" class="button">返回测试页面</a></p>';
            
        } else {
            echo '<p>测试清理系统的各项功能，包括阈值设置、自动清理开关、文章识别、清理执行、日志记录和统计功能。</p>';
            echo '<div style="margin: 20px 0;">';
            echo '<a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-test-cleanup&run_tests=true') . '" class="button button-primary">运行清理系统测试</a>';
            echo '</div>';
            
            echo '<div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0;">';
            echo '<h4>测试内容：</h4>';
            echo '<ul>';
            echo '<li><strong>阈值设置</strong>: 测试清理阈值天数的设置和获取功能</li>';
            echo '<li><strong>自动清理开关</strong>: 测试启用/禁用自动清理功能</li>';
            echo '<li><strong>文章识别</strong>: 测试识别需要清理的过期文章</li>';
            echo '<li><strong>清理执行</strong>: 测试批量删除文章和WordPress站点同步</li>';
            echo '<li><strong>清理日志</strong>: 测试清理操作的日志记录功能</li>';
            echo '<li><strong>清理统计</strong>: 测试清理统计数据的生成</li>';
            echo '<li><strong>定时任务</strong>: 测试WordPress cron任务的调度</li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">';
            echo '<h4>需求验证：</h4>';
            echo '<p>此测试验证以下需求的实现：</p>';
            echo '<ul>';
            echo '<li><strong>需求 5.1</strong>: 管理员设置清理阈值时，系统应当保存配置并应用于后续清理任务</li>';
            echo '<li><strong>需求 5.2</strong>: 定时任务运行时，清理调度器应当识别超过阈值天数的文章</li>';
            echo '<li><strong>需求 5.3</strong>: 文章被自动删除时，系统应当同步删除WordPress站点上的对应文章</li>';
            echo '<li><strong>需求 5.4</strong>: 清理操作完成时，系统应当记录删除日志包含时间、标题和站点信息</li>';
            echo '<li><strong>需求 5.5</strong>: 管理员手动触发清理时，系统应当立即执行清理并显示操作结果</li>';
            echo '</ul>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * 获取审计日志数据 - AJAX处理器
     */
    public function ajax_get_audit_logs() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        try {
            $audit_logger = $this->container->get('audit_logger');
            
            // 获取分页参数
            $page = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
            $per_page = isset($_POST['per_page']) ? max(10, min(100, (int) $_POST['per_page'])) : 20;
            
            // 获取过滤参数
            $filters = array();
            if (!empty($_POST['user_id'])) {
                $filters['user_id'] = (int) $_POST['user_id'];
            }
            if (!empty($_POST['action'])) {
                $filters['action'] = sanitize_text_field($_POST['action']);
            }
            if (!empty($_POST['resource_type'])) {
                $filters['resource_type'] = sanitize_text_field($_POST['resource_type']);
            }
            if (!empty($_POST['date_from'])) {
                $filters['date_from'] = sanitize_text_field($_POST['date_from']);
            }
            if (!empty($_POST['date_to'])) {
                $filters['date_to'] = sanitize_text_field($_POST['date_to']);
            }
            if (!empty($_POST['search'])) {
                $filters['search'] = sanitize_text_field($_POST['search']);
            }

            $result = $audit_logger->get_logs($filters, $page, $per_page);
            
            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error('获取审计日志失败: ' . $e->getMessage());
        }
    }

    /**
     * 导出审计日志 - AJAX处理器
     */
    public function ajax_export_audit_logs() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['nonce'], 'ams_v2_admin_nonce')) {
            wp_die('权限不足或安全验证失败');
            return;
        }

        try {
            $audit_logger = $this->container->get('audit_logger');
            
            // 获取过滤参数
            $filters = array();
            if (!empty($_GET['user_id'])) {
                $filters['user_id'] = (int) $_GET['user_id'];
            }
            if (!empty($_GET['action'])) {
                $filters['action'] = sanitize_text_field($_GET['action']);
            }
            if (!empty($_GET['resource_type'])) {
                $filters['resource_type'] = sanitize_text_field($_GET['resource_type']);
            }
            if (!empty($_GET['date_from'])) {
                $filters['date_from'] = sanitize_text_field($_GET['date_from']);
            }
            if (!empty($_GET['date_to'])) {
                $filters['date_to'] = sanitize_text_field($_GET['date_to']);
            }

            // 获取所有符合条件的日志（限制最大10000条）
            $result = $audit_logger->get_logs($filters, 1, 10000);

            if (empty($result['logs'])) {
                wp_die('没有找到符合条件的审计日志');
                return;
            }

            // 设置CSV下载头
            $filename = 'audit-logs-' . date('Y-m-d') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            // 输出CSV内容
            $output = fopen('php://output', 'w');
            
            // 添加BOM以支持中文
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // 写入标题行
            fputcsv($output, array('ID', '用户ID', '用户名', '操作', '资源类型', '资源ID', '旧值', '新值', 'IP地址', '用户代理', '创建时间'));
            
            // 写入数据行
            foreach ($result['logs'] as $log) {
                fputcsv($output, array(
                    $log->get_id(),
                    $log->get_user_id(),
                    $log->get_username() ?: 'N/A',
                    $log->get_action(),
                    $log->get_resource_type(),
                    $log->get_resource_id(),
                    $log->get_old_values() ? json_encode($log->get_old_values(), JSON_UNESCAPED_UNICODE) : '',
                    $log->get_new_values() ? json_encode($log->get_new_values(), JSON_UNESCAPED_UNICODE) : '',
                    $log->get_ip_address(),
                    $log->get_user_agent(),
                    $log->get_created_at()
                ));
            }
            
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            wp_die('导出过程中发生错误: ' . $e->getMessage());
        }
    }

    /**
     * 获取审计日志详情 - AJAX处理器
     */
    public function ajax_get_audit_log_details() {
        // 验证权限和nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error('权限不足或安全验证失败');
            return;
        }

        $log_id = (int) $_POST['log_id'];
        
        try {
            $audit_logger = $this->container->get('audit_logger');
            $log = $audit_logger->get_log_by_id($log_id);

            if (!$log) {
                wp_send_json_error('审计日志不存在');
                return;
            }

            $html = '<div class="audit-log-details">';
            $html .= '<h4>基本信息</h4>';
            $html .= '<table class="form-table">';
            $html .= '<tr><th>日志ID:</th><td>' . $log->get_id() . '</td></tr>';
            $html .= '<tr><th>用户ID:</th><td>' . ($log->get_user_id() ?: 'N/A') . '</td></tr>';
            $html .= '<tr><th>用户名:</th><td>' . esc_html($log->get_username() ?: 'N/A') . '</td></tr>';
            $html .= '<tr><th>操作:</th><td>' . esc_html($log->get_action()) . '</td></tr>';
            $html .= '<tr><th>资源类型:</th><td>' . esc_html($log->get_resource_type()) . '</td></tr>';
            $html .= '<tr><th>资源ID:</th><td>' . ($log->get_resource_id() ?: 'N/A') . '</td></tr>';
            $html .= '<tr><th>IP地址:</th><td>' . esc_html($log->get_ip_address()) . '</td></tr>';
            $html .= '<tr><th>创建时间:</th><td>' . esc_html($log->get_created_at()) . '</td></tr>';
            $html .= '</table>';

            if ($log->get_old_values()) {
                $html .= '<h4>修改前的值</h4>';
                $html .= '<pre class="audit-values">' . esc_html(json_encode($log->get_old_values(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
            }

            if ($log->get_new_values()) {
                $html .= '<h4>修改后的值</h4>';
                $html .= '<pre class="audit-values">' . esc_html(json_encode($log->get_new_values(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
            }

            if ($log->get_user_agent()) {
                $html .= '<h4>用户代理</h4>';
                $html .= '<p class="user-agent">' . esc_html($log->get_user_agent()) . '</p>';
            }

            $html .= '</div>';

            wp_send_json_success(array('html' => $html));

        } catch (Exception $e) {
            wp_send_json_error('获取日志详情失败: ' . $e->getMessage());
        }
    }

    /**
     * 显示审计日志测试页面
     */
    public function display_audit_logging_test_page() {
        echo '<div class="wrap">';
        echo '<h1>审计日志系统功能测试</h1>';
        
        if (isset($_GET['run_tests'])) {
            // 运行审计日志测试
            require_once AMS_V2_PLUGIN_DIR . 'test-audit-logging.php';
            $test = new AMS_V2_Audit_Logging_Test($this->container);
            $results = $test->run_all_tests();
            $test->output_results($results);
            
            echo '<p><a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-test-audit-logs') . '" class="button">返回测试页面</a></p>';
            
        } else {
            echo '<p>测试审计日志系统的各项功能，包括基本日志记录、用户操作日志、日志查询和过滤功能。</p>';
            echo '<div style="margin: 20px 0;">';
            echo '<a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-test-audit-logs&run_tests=true') . '" class="button button-primary">运行审计日志测试</a>';
            echo '</div>';
            
            echo '<div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0;">';
            echo '<h4>测试内容：</h4>';
            echo '<ul>';
            echo '<li><strong>基本日志记录</strong>: 测试审计日志的基本记录功能</li>';
            echo '<li><strong>用户操作日志</strong>: 测试用户登录、创建、更新等操作的日志记录</li>';
            echo '<li><strong>日志查询功能</strong>: 测试根据ID和条件查询审计日志</li>';
            echo '<li><strong>日志过滤功能</strong>: 测试按操作类型、资源类型、时间范围等过滤日志</li>';
            echo '<li><strong>日志导出功能</strong>: 测试审计日志的CSV导出功能</li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;">';
            echo '<h4>需求验证：</h4>';
            echo '<p>此测试验证以下需求的实现：</p>';
            echo '<ul>';
            echo '<li><strong>需求 10.1</strong>: 管理员修改审核等级时，系统应当记录操作人、时间和修改内容</li>';
            echo '<li><strong>需求 10.2</strong>: 管理员修改敏感词库时，系统应当记录详细的变更信息</li>';
            echo '<li><strong>需求 10.3</strong>: 系统执行自动清理时，系统应当记录清理操作的完整日志</li>';
            echo '<li><strong>需求 10.4</strong>: 用户权限变更时，系统应当记录权限修改的审计信息</li>';
            echo '<li><strong>需求 10.5</strong>: 系统发生异常时，系统应当记录错误日志便于问题诊断</li>';
            echo '</ul>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * 批量操作处理器 - AJAX
     */
    public function ajax_bulk_action() {
        // 验证权限和nonce
        if (!current_user_can('edit_posts') || !wp_verify_nonce($_POST['nonce'], 'ams_v2_admin_nonce')) {
            wp_send_json_error(array('message' => '权限不足或安全验证失败'));
            return;
        }
        
        $bulk_action = sanitize_text_field($_POST['bulk_action']);
        $ids = array_map('intval', $_POST['ids']);
        
        if (empty($ids)) {
            wp_send_json_error(array('message' => '请选择要操作的项目'));
            return;
        }
        
        try {
            $result_message = '';
            $success_count = 0;
            
            switch ($bulk_action) {
                case '删除文章':
                    $article_manager = $this->container->get('article_manager');
                    foreach ($ids as $id) {
                        if ($article_manager->delete_article($id)) {
                            $success_count++;
                        }
                    }
                    $result_message = "成功删除 {$success_count} 篇文章";
                    break;
                    
                case '发布文章':
                    $article_manager = $this->container->get('article_manager');
                    foreach ($ids as $id) {
                        $article = $article_manager->get_article($id);
                        if ($article) {
                            $article->set_status('published');
                            if ($article_manager->update_article($article)) {
                                $success_count++;
                            }
                        }
                    }
                    $result_message = "成功发布 {$success_count} 篇文章";
                    break;
                    
                case '删除用户':
                    if (!current_user_can('manage_options')) {
                        wp_send_json_error(array('message' => '权限不足，无法删除用户'));
                        return;
                    }
                    
                    $user_manager = $this->container->get('authentication_manager');
                    foreach ($ids as $id) {
                        if ($user_manager->delete_user($id)) {
                            $success_count++;
                        }
                    }
                    $result_message = "成功删除 {$success_count} 个用户";
                    break;
                    
                case '删除敏感词':
                    if (!current_user_can('manage_options')) {
                        wp_send_json_error(array('message' => '权限不足，无法删除敏感词'));
                        return;
                    }
                    
                    $sensitive_word_manager = $this->container->get('sensitive_word_manager');
                    foreach ($ids as $id) {
                        if ($sensitive_word_manager->delete_word($id)) {
                            $success_count++;
                        }
                    }
                    $result_message = "成功删除 {$success_count} 个敏感词";
                    break;
                    
                case '删除站点':
                    if (!current_user_can('manage_options')) {
                        wp_send_json_error(array('message' => '权限不足，无法删除站点'));
                        return;
                    }
                    
                    $site_manager = $this->container->get('site_manager');
                    foreach ($ids as $id) {
                        if ($site_manager->delete_site($id)) {
                            $success_count++;
                        }
                    }
                    $result_message = "成功删除 {$success_count} 个站点";
                    break;
                    
                default:
                    wp_send_json_error(array('message' => '不支持的批量操作'));
                    return;
            }
            
            if ($success_count > 0) {
                wp_send_json_success(array('message' => $result_message));
            } else {
                wp_send_json_error(array('message' => '批量操作失败，请检查选择的项目'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => '操作失败：' . $e->getMessage()));
        }
    }
}