<?php
/**
 * 自动加载器类
 *
 * @package Article_Management_V2
 * @subpackage Includes
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 自动加载器类
 */
class AMS_V2_Autoloader {

    /**
     * 注册自动加载器
     */
    public static function register() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    /**
     * 自动加载类文件
     *
     * @param string $class_name 类名
     */
    public static function autoload($class_name) {
        // 只处理我们插件的类
        if (strpos($class_name, 'AMS_V2_') !== 0) {
            return;
        }

        // 转换类名为文件名
        $file_name = self::get_file_name_from_class($class_name);
        
        // 查找文件路径
        $file_path = self::find_file($file_name);
        
        if ($file_path && file_exists($file_path)) {
            require_once $file_path;
        }
    }

    /**
     * 从类名获取文件名
     *
     * @param string $class_name 类名
     * @return string 文件名
     */
    private static function get_file_name_from_class($class_name) {
        // 移除前缀
        $class_name = str_replace('AMS_V2_', '', $class_name);
        
        // 转换为小写并用连字符分隔
        $file_name = strtolower(str_replace('_', '-', $class_name));
        
        return 'class-' . $file_name . '.php';
    }

    /**
     * 查找文件路径
     *
     * @param string $file_name 文件名
     * @return string|false 文件路径或false
     */
    private static function find_file($file_name) {
        $directories = array(
            AMS_V2_PLUGIN_DIR . 'includes/',
            AMS_V2_PLUGIN_DIR . 'includes/models/',
            AMS_V2_PLUGIN_DIR . 'includes/interfaces/',
            AMS_V2_PLUGIN_DIR . 'includes/managers/',
            AMS_V2_PLUGIN_DIR . 'includes/services/',
            AMS_V2_PLUGIN_DIR . 'includes/controllers/',
            AMS_V2_PLUGIN_DIR . 'includes/repositories/',
            AMS_V2_PLUGIN_DIR . 'includes/exceptions/',
            AMS_V2_PLUGIN_DIR . 'includes/enums/',
            AMS_V2_PLUGIN_DIR . 'includes/middleware/',
            AMS_V2_PLUGIN_DIR . 'includes/api/',
            AMS_V2_PLUGIN_DIR . 'admin/',
            AMS_V2_PLUGIN_DIR . 'public/',
        );

        foreach ($directories as $directory) {
            $file_path = $directory . $file_name;
            if (file_exists($file_path)) {
                return $file_path;
            }
        }

        return false;
    }
}

// 注册自动加载器
AMS_V2_Autoloader::register();