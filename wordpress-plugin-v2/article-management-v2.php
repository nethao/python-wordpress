<?php
/**
 * Plugin Name: Article Management System V2.0
 * Plugin URI: https://github.com/your-repo/article-management-v2
 * Description: 企业级多用户内容管理系统，支持多站点发布、分级审核、权限管理和自动化运维功能
 * Version: 2.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: article-management-v2
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义插件常量
define('AMS_V2_VERSION', '2.0.0');
define('AMS_V2_PLUGIN_FILE', __FILE__);
define('AMS_V2_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMS_V2_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AMS_V2_PLUGIN_BASENAME', plugin_basename(__FILE__));

// 自动加载器
require_once AMS_V2_PLUGIN_DIR . 'includes/class-autoloader.php';

// 主插件类
require_once AMS_V2_PLUGIN_DIR . 'includes/class-article-management-v2.php';

/**
 * 插件激活钩子
 */
function ams_v2_activate() {
    require_once AMS_V2_PLUGIN_DIR . 'includes/class-activator.php';
    AMS_V2_Activator::activate();
}
register_activation_hook(__FILE__, 'ams_v2_activate');

/**
 * 插件停用钩子
 */
function ams_v2_deactivate() {
    require_once AMS_V2_PLUGIN_DIR . 'includes/class-deactivator.php';
    AMS_V2_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'ams_v2_deactivate');

/**
 * 插件卸载钩子
 */
function ams_v2_uninstall() {
    require_once AMS_V2_PLUGIN_DIR . 'includes/class-uninstaller.php';
    AMS_V2_Uninstaller::uninstall();
}
register_uninstall_hook(__FILE__, 'ams_v2_uninstall');

/**
 * 开始执行插件
 */
function run_article_management_v2() {
    $plugin = new Article_Management_V2();
    $plugin->run();
}

// 插件加载完成后运行
add_action('plugins_loaded', 'run_article_management_v2');