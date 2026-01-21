<?php
/**
 * 公共前端类
 *
 * @package Article_Management_V2
 * @subpackage Public
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 公共前端类
 */
class AMS_V2_Public {

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
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            AMS_V2_PLUGIN_URL . 'public/css/public.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * 加载脚本文件
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            AMS_V2_PLUGIN_URL . 'public/js/public.js',
            array('jquery'),
            $this->version,
            false
        );

        // 本地化脚本
        wp_localize_script(
            $this->plugin_name,
            'ams_v2_public',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ams_v2_public_nonce'),
                'strings' => array(
                    'loading' => __('Loading...', 'article-management-v2'),
                    'error' => __('An error occurred. Please try again.', 'article-management-v2'),
                    'success' => __('Operation completed successfully.', 'article-management-v2')
                )
            )
        );
    }

    /**
     * 初始化公共功能
     */
    public function init() {
        // 注册短代码
        $this->register_shortcodes();
        
        // 注册AJAX处理器
        $this->register_ajax_handlers();
        
        // 添加重写规则
        $this->add_rewrite_rules();
    }

    /**
     * 注册短代码
     */
    private function register_shortcodes() {
        add_shortcode('ams_v2_article_form', array($this, 'shortcode_article_form'));
        add_shortcode('ams_v2_article_list', array($this, 'shortcode_article_list'));
        add_shortcode('ams_v2_user_dashboard', array($this, 'shortcode_user_dashboard'));
        add_shortcode('ams_v2_login_form', array($this, 'shortcode_login_form'));
    }

    /**
     * 注册AJAX处理器
     */
    private function register_ajax_handlers() {
        // 公共AJAX（需要登录）
        add_action('wp_ajax_ams_v2_submit_article', array($this, 'ajax_submit_article'));
        add_action('wp_ajax_ams_v2_get_user_articles', array($this, 'ajax_get_user_articles'));
        add_action('wp_ajax_ams_v2_delete_article', array($this, 'ajax_delete_article'));
        
        // 公共AJAX（无需登录）
        add_action('wp_ajax_nopriv_ams_v2_login', array($this, 'ajax_login'));
        add_action('wp_ajax_ams_v2_login', array($this, 'ajax_login'));
    }

    /**
     * 添加重写规则
     */
    private function add_rewrite_rules() {
        // 用户仪表板
        add_rewrite_rule(
            '^ams-v2/dashboard/?$',
            'index.php?ams_v2_page=dashboard',
            'top'
        );
        
        // 文章编辑
        add_rewrite_rule(
            '^ams-v2/article/([0-9]+)/?$',
            'index.php?ams_v2_page=article&ams_v2_article_id=$matches[1]',
            'top'
        );
        
        // 用户登录
        add_rewrite_rule(
            '^ams-v2/login/?$',
            'index.php?ams_v2_page=login',
            'top'
        );

        // 添加查询变量
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // 处理模板重定向
        add_action('template_redirect', array($this, 'template_redirect'));
    }

    /**
     * 添加查询变量
     *
     * @param array $vars 查询变量数组
     * @return array
     */
    public function add_query_vars($vars) {
        $vars[] = 'ams_v2_page';
        $vars[] = 'ams_v2_article_id';
        return $vars;
    }

    /**
     * 处理模板重定向
     */
    public function template_redirect() {
        $page = get_query_var('ams_v2_page');
        
        if (!$page) {
            return;
        }

        switch ($page) {
            case 'dashboard':
                $this->load_dashboard_template();
                break;
            case 'article':
                $this->load_article_template();
                break;
            case 'login':
                $this->load_login_template();
                break;
        }
    }

    /**
     * 加载仪表板模板
     */
    private function load_dashboard_template() {
        // 检查用户是否登录
        if (!$this->is_user_logged_in()) {
            wp_redirect(home_url('/ams-v2/login/'));
            exit;
        }

        include AMS_V2_PLUGIN_DIR . 'public/templates/dashboard.php';
        exit;
    }

    /**
     * 加载文章模板
     */
    private function load_article_template() {
        // 检查用户是否登录
        if (!$this->is_user_logged_in()) {
            wp_redirect(home_url('/ams-v2/login/'));
            exit;
        }

        $article_id = get_query_var('ams_v2_article_id');
        
        // 验证文章权限
        if (!$this->can_user_edit_article($article_id)) {
            wp_die(__('You do not have permission to edit this article.', 'article-management-v2'));
        }

        include AMS_V2_PLUGIN_DIR . 'public/templates/article-edit.php';
        exit;
    }

    /**
     * 加载登录模板
     */
    private function load_login_template() {
        // 如果已登录，重定向到仪表板
        if ($this->is_user_logged_in()) {
            wp_redirect(home_url('/ams-v2/dashboard/'));
            exit;
        }

        include AMS_V2_PLUGIN_DIR . 'public/templates/login.php';
        exit;
    }

    /**
     * 短代码：文章表单
     *
     * @param array $atts 短代码属性
     * @return string
     */
    public function shortcode_article_form($atts) {
        $atts = shortcode_atts(array(
            'article_id' => 0,
            'redirect_url' => ''
        ), $atts);

        ob_start();
        include AMS_V2_PLUGIN_DIR . 'public/templates/shortcodes/article-form.php';
        return ob_get_clean();
    }

    /**
     * 短代码：文章列表
     *
     * @param array $atts 短代码属性
     * @return string
     */
    public function shortcode_article_list($atts) {
        $atts = shortcode_atts(array(
            'per_page' => 10,
            'status' => 'all',
            'author' => 'current'
        ), $atts);

        ob_start();
        include AMS_V2_PLUGIN_DIR . 'public/templates/shortcodes/article-list.php';
        return ob_get_clean();
    }

    /**
     * 短代码：用户仪表板
     *
     * @param array $atts 短代码属性
     * @return string
     */
    public function shortcode_user_dashboard($atts) {
        $atts = shortcode_atts(array(
            'show_stats' => true,
            'show_recent' => true
        ), $atts);

        ob_start();
        include AMS_V2_PLUGIN_DIR . 'public/templates/shortcodes/user-dashboard.php';
        return ob_get_clean();
    }

    /**
     * 短代码：登录表单
     *
     * @param array $atts 短代码属性
     * @return string
     */
    public function shortcode_login_form($atts) {
        $atts = shortcode_atts(array(
            'redirect_url' => home_url('/ams-v2/dashboard/')
        ), $atts);

        ob_start();
        include AMS_V2_PLUGIN_DIR . 'public/templates/shortcodes/login-form.php';
        return ob_get_clean();
    }

    /**
     * 检查用户是否登录
     *
     * @return bool
     */
    private function is_user_logged_in() {
        // TODO: 实现插件用户登录检查逻辑
        return false;
    }

    /**
     * 检查用户是否可以编辑文章
     *
     * @param int $article_id 文章ID
     * @return bool
     */
    private function can_user_edit_article($article_id) {
        // TODO: 实现文章权限检查逻辑
        return false;
    }

    // AJAX处理器方法（占位符）
    public function ajax_submit_article() {
        // TODO: 实现文章提交逻辑
        wp_die();
    }

    public function ajax_get_user_articles() {
        // TODO: 实现获取用户文章逻辑
        wp_die();
    }

    public function ajax_delete_article() {
        // TODO: 实现删除文章逻辑
        wp_die();
    }

    public function ajax_login() {
        // TODO: 实现用户登录逻辑
        wp_die();
    }
}