<?php
/**
 * 简单的测试运行器
 * 用于执行核心权限系统验证检查点
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 模拟WordPress环境
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// 模拟WordPress函数
require_once 'test-implementation.php';

// 全局变量
global $ams_v2_container;

echo "<html><head><title>核心权限系统验证测试</title></head><body>";
echo "<h1>WordPress Plugin V2.0 - 核心权限系统验证</h1>";

// 检查核心文件是否存在
$core_files = array(
    'includes/models/class-user.php',
    'includes/models/class-role.php', 
    'includes/models/class-article.php',
    'includes/managers/class-role-manager.php',
    'includes/managers/class-authentication-manager.php',
    'includes/managers/class-article-manager.php',
    'includes/interfaces/interface-article-manager.php',
    'includes/interfaces/interface-authentication-manager.php',
    'includes/interfaces/interface-role-manager.php'
);

echo "<h2>1. 核心文件检查</h2>";
$files_ok = true;
foreach ($core_files as $file) {
    $exists = file_exists($file);
    $status = $exists ? '✓' : '✗';
    $color = $exists ? 'green' : 'red';
    echo "<p style='color: $color;'>$status $file</p>";
    if (!$exists) $files_ok = false;
}

if (!$files_ok) {
    echo "<p style='color: red; font-weight: bold;'>错误: 核心文件缺失，无法继续测试</p>";
    echo "</body></html>";
    exit;
}

// 加载核心类
try {
    // 加载模型类
    require_once 'includes/models/class-user.php';
    require_once 'includes/models/class-role.php';
    require_once 'includes/models/class-article.php';
    
    // 加载接口
    require_once 'includes/interfaces/interface-role-manager.php';
    require_once 'includes/interfaces/interface-authentication-manager.php';
    require_once 'includes/interfaces/interface-article-manager.php';
    
    // 加载管理器
    require_once 'includes/managers/class-role-manager.php';
    require_once 'includes/managers/class-authentication-manager.php';
    require_once 'includes/managers/class-article-manager.php';
    
    // 加载容器和其他核心类
    require_once 'includes/class-container.php';
    require_once 'includes/class-database-manager.php';
    require_once 'includes/class-logger.php';
    
    echo "<h2>2. 类加载检查</h2>";
    echo "<p style='color: green;'>✓ 所有核心类加载成功</p>";
    
} catch (Exception $e) {
    echo "<h2>2. 类加载检查</h2>";
    echo "<p style='color: red;'>✗ 类加载失败: " . $e->getMessage() . "</p>";
    echo "</body></html>";
    exit;
}

// 初始化容器
try {
    $ams_v2_container = new AMS_V2_Container();
    
    // 注册数据库管理器
    $ams_v2_container->register('database_manager', function() {
        return new AMS_V2_Database_Manager();
    });
    
    // 注册日志记录器
    $ams_v2_container->register('logger', function() {
        return new AMS_V2_Logger();
    });
    
    // 注册角色管理器
    $ams_v2_container->register('role_manager', function($container) {
        return new AMS_V2_Role_Manager(
            $container->get('database_manager'),
            $container->get('logger')
        );
    });
    
    // 注册认证管理器
    $ams_v2_container->register('authentication_manager', function($container) {
        return new AMS_V2_Authentication_Manager(
            $container->get('database_manager'),
            $container->get('role_manager'),
            $container->get('logger')
        );
    });
    
    // 注册文章管理器
    $ams_v2_container->register('article_manager', function($container) {
        return new AMS_V2_Article_Manager(
            $container->get('database_manager'),
            $container->get('authentication_manager'),
            $container->get('logger')
        );
    });
    
    echo "<h2>3. 依赖注入容器检查</h2>";
    echo "<p style='color: green;'>✓ 容器初始化成功</p>";
    echo "<p style='color: green;'>✓ 所有服务注册成功</p>";
    
} catch (Exception $e) {
    echo "<h2>3. 依赖注入容器检查</h2>";
    echo "<p style='color: red;'>✗ 容器初始化失败: " . $e->getMessage() . "</p>";
    echo "</body></html>";
    exit;
}

// 运行基础单元测试
echo "<h2>4. 基础单元测试</h2>";
try {
    require_once 'tests/unit-test-basic.php';
    $basic_test = new AMS_V2_Basic_Unit_Test();
    $basic_results = $basic_test->run_all_tests();
    $basic_test->output_results($basic_results);
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 基础单元测试失败: " . $e->getMessage() . "</p>";
}

// 运行用户角色管理测试
echo "<h2>5. 用户角色管理测试</h2>";
try {
    require_once 'tests/test-user-role-management.php';
    $role_test = new AMS_V2_User_Role_Management_Test($ams_v2_container);
    $role_results = $role_test->run_all_tests();
    $role_test->output_results($role_results);
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 用户角色管理测试失败: " . $e->getMessage() . "</p>";
}

// 运行文章管理测试
echo "<h2>6. 文章管理测试</h2>";
try {
    require_once 'tests/test-article-management.php';
    AMS_V2_Article_Management_Test::run_tests();
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 文章管理测试失败: " . $e->getMessage() . "</p>";
}

// 总结
echo "<h2>7. 测试总结</h2>";
echo "<p>核心权限系统验证检查点测试完成。</p>";
echo "<p>如果所有测试都通过，说明核心权限系统工作正常。</p>";
echo "<p>如果有测试失败，请检查相关组件的实现。</p>";

echo "</body></html>";
?>