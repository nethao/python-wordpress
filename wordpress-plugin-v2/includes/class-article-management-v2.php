<?php
/**
 * 主插件类
 *
 * @package Article_Management_V2
 * @subpackage Includes
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 主插件类
 */
class Article_Management_V2 {

    /**
     * 插件版本
     *
     * @var string
     */
    protected $version;

    /**
     * 插件名称
     *
     * @var string
     */
    protected $plugin_name;

    /**
     * 依赖注入容器
     *
     * @var AMS_V2_Container
     */
    protected $container;

    /**
     * 错误处理器
     *
     * @var AMS_V2_Error_Handler
     */
    protected $error_handler;

    /**
     * 日志记录器
     *
     * @var AMS_V2_Logger
     */
    protected $logger;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->version = AMS_V2_VERSION;
        $this->plugin_name = 'article-management-v2';
        
        $this->load_dependencies();
        $this->setup_container();
        $this->setup_error_handling();
        $this->setup_logging();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * 加载依赖
     */
    private function load_dependencies() {
        // 核心类
        require_once AMS_V2_PLUGIN_DIR . 'includes/class-container.php';
        require_once AMS_V2_PLUGIN_DIR . 'includes/class-error-handler.php';
        require_once AMS_V2_PLUGIN_DIR . 'includes/class-logger.php';
        require_once AMS_V2_PLUGIN_DIR . 'includes/class-database-manager.php';
        
        // 管理后台
        require_once AMS_V2_PLUGIN_DIR . 'admin/class-admin.php';
        
        // 公共前端
        require_once AMS_V2_PLUGIN_DIR . 'public/class-public.php';
        
        // 加载所有必需的类文件
        $this->load_all_classes();
    }

    /**
     * 加载所有类文件
     */
    private function load_all_classes() {
        // 接口
        $interfaces = array(
            'interface-role-manager',
            'interface-authentication-manager', 
            'interface-article-manager',
            'interface-site-manager',
            'interface-api-manager',
            'interface-sensitive-word-manager',
            'interface-content-moderator',
            'interface-cleanup-scheduler',
            'interface-statistics-collector',
            'interface-audit-logger',
            'interface-wordpress-bridge'
        );
        
        foreach ($interfaces as $interface) {
            $file = AMS_V2_PLUGIN_DIR . 'includes/interfaces/' . $interface . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        // 枚举
        $enums = array('class-moderation-level', 'class-word-type');
        foreach ($enums as $enum) {
            $file = AMS_V2_PLUGIN_DIR . 'includes/enums/' . $enum . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        // 模型
        $models = array(
            'class-role', 'class-user', 'class-article', 'class-site', 
            'class-site-credentials', 'class-sensitive-word', 'class-audit-log',
            'class-user-stats', 'class-system-stats'
        );
        foreach ($models as $model) {
            $file = AMS_V2_PLUGIN_DIR . 'includes/models/' . $model . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        // 管理器
        $managers = array(
            'class-role-manager', 'class-authentication-manager', 'class-article-manager',
            'class-site-manager', 'class-api-key-manager', 'class-sensitive-word-manager',
            'class-content-moderator', 'class-moderation-config-manager', 'class-cleanup-scheduler',
            'class-statistics-collector', 'class-audit-logger'
        );
        foreach ($managers as $manager) {
            $file = AMS_V2_PLUGIN_DIR . 'includes/managers/' . $manager . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        // 服务
        $services = array('class-encryption-service', 'class-deepseek-client');
        foreach ($services as $service) {
            $file = AMS_V2_PLUGIN_DIR . 'includes/services/' . $service . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        // 中间件
        $middleware = array('class-auth-middleware');
        foreach ($middleware as $mw) {
            $file = AMS_V2_PLUGIN_DIR . 'includes/middleware/' . $mw . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        // API控制器
        $controllers = array('class-user-api-controller');
        foreach ($controllers as $controller) {
            $file = AMS_V2_PLUGIN_DIR . 'includes/api/' . $controller . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    /**
     * 设置依赖注入容器
     */
    private function setup_container() {
        $this->container = new AMS_V2_Container();
        
        // 注册核心服务
        $this->container->register('database_manager', function() {
            return new AMS_V2_Database_Manager();
        });
        
        $this->container->register('logger', function() {
            return new AMS_V2_Logger();
        });
        
        $this->container->register('error_handler', function() {
            return new AMS_V2_Error_Handler($this->container->get('logger'));
        });
        
        // 注册用户权限管理服务
        $this->container->register('role_manager', function($container) {
            return new AMS_V2_Role_Manager(
                $container->get('database_manager'),
                $container->get('logger')
            );
        });
        
        $this->container->register('authentication_manager', function($container) {
            return new AMS_V2_Authentication_Manager(
                $container->get('database_manager'),
                $container->get('role_manager'),
                $container->get('logger')
            );
        });

        // 注册文章管理服务
        $this->container->register('article_manager', function($container) {
            return new AMS_V2_Article_Manager(
                $container->get('database_manager'),
                $container->get('authentication_manager'),
                $container->get('logger')
            );
        });

        // 注册加密服务
        $this->container->register('encryption_service', function() {
            return new AMS_V2_Encryption_Service();
        });

        // 注册站点管理服务
        $this->container->register('site_manager', function($container) {
            return new AMS_V2_Site_Manager(
                $container->get('database_manager'),
                $container->get('encryption_service'),
                $container->get('logger')
            );
        });

        // 注册API管理服务
        $this->container->register('api_manager', function($container) {
            return new AMS_V2_API_Key_Manager(
                $container->get('database_manager'),
                $container->get('encryption_service'),
                $container->get('logger')
            );
        });

        // 注册DeepSeek客户端服务
        $this->container->register('deepseek_client', function($container) {
            $api_manager = $container->get('api_manager');
            $api_key = $api_manager->get_api_key('deepseek');
            $config = $api_manager->get_api_config('deepseek');
            
            if (empty($api_key)) {
                return null; // 没有配置API密钥
            }
            
            return new AMS_V2_DeepSeek_Client($api_key, $config, $container->get('logger'));
        });

        // 注册认证中间件
        $this->container->register('auth_middleware', function($container) {
            return new AMS_V2_Auth_Middleware(
                $container->get('authentication_manager'),
                $container->get('logger')
            );
        });

        // 注册清理调度器服务
        $this->container->register('cleanup_scheduler', function($container) {
            return new AMS_V2_Cleanup_Scheduler(
                $container->get('database_manager'),
                $container->get('article_manager'),
                $container->get('site_manager'),
                $container->get('logger')
            );
        });

        // 注册统计收集器服务
        $this->container->register('statistics_collector', function($container) {
            return new AMS_V2_Statistics_Collector(
                $container->get('database_manager'),
                $container->get('logger')
            );
        });

        // 注册审计日志服务
        $this->container->register('audit_logger', function($container) {
            return new AMS_V2_Audit_Logger(
                $container->get('database_manager'),
                $container->get('logger')
            );
        });

        // 注册API控制器
        $this->container->register('user_api_controller', function($container) {
            return new AMS_V2_User_API_Controller(
                $container->get('authentication_manager'),
                $container->get('role_manager'),
                $container->get('logger')
            );
        });

        // 注册系统监控服务
        $this->container->register('system_monitor', function($container) {
            return new AMS_V2_System_Monitor($container);
        });

        // 设置全局容器引用
        global $ams_v2_container;
        $ams_v2_container = $this->container;
    }

    /**
     * 设置错误处理
     */
    private function setup_error_handling() {
        $this->error_handler = $this->container->get('error_handler');
        $this->error_handler->init();
    }

    /**
     * 设置日志记录
     */
    private function setup_logging() {
        $this->logger = $this->container->get('logger');
        $this->logger->init();
    }

    /**
     * 定义管理后台钩子
     */
    private function define_admin_hooks() {
        $plugin_admin = new AMS_V2_Admin($this->get_plugin_name(), $this->get_version(), $this->container);

        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($plugin_admin, 'enqueue_scripts'));
        add_action('admin_menu', array($plugin_admin, 'add_plugin_admin_menu'));
        add_action('admin_init', array($plugin_admin, 'admin_init'));
    }

    /**
     * 定义公共前端钩子
     */
    private function define_public_hooks() {
        $plugin_public = new AMS_V2_Public($this->get_plugin_name(), $this->get_version(), $this->container);

        add_action('wp_enqueue_scripts', array($plugin_public, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($plugin_public, 'enqueue_scripts'));
        add_action('init', array($plugin_public, 'init'));

        // 初始化认证中间件
        $auth_middleware = $this->container->get('auth_middleware');
        $auth_middleware->init();

        // 注册REST API路由
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // 注册定时任务回调
        add_action('ams_v2_auto_cleanup', array($this, 'handle_auto_cleanup'));
    }

    /**
     * 处理自动清理任务
     */
    public function handle_auto_cleanup() {
        try {
            $cleanup_scheduler = $this->container->get('cleanup_scheduler');
            $cleanup_scheduler->run_auto_cleanup();
        } catch (Exception $e) {
            $this->logger->error('自动清理任务回调失败', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }

    /**
     * 运行插件
     */
    public function run() {
        // 插件已通过钩子系统初始化
        $this->logger->info('Article Management V2 plugin started successfully');
    }

    /**
     * 注册REST API路由
     */
    public function register_rest_routes() {
        $user_api_controller = $this->container->get('user_api_controller');
        $user_api_controller->register_routes();
    }

    /**
     * 获取插件名称
     *
     * @return string 插件名称
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * 获取插件版本
     *
     * @return string 插件版本
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * 获取容器实例
     *
     * @return AMS_V2_Container 容器实例
     */
    public function get_container() {
        return $this->container;
    }
}