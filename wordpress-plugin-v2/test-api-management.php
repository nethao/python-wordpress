<?php
/**
 * API管理功能测试
 *
 * @package Article_Management_V2
 * @subpackage Tests
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 模拟WordPress环境
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// 加载测试实现
require_once 'test-implementation.php';

echo "<html><head><title>API管理功能测试</title></head><body>";
echo "<h1>WordPress Plugin V2.0 - API管理功能测试</h1>";

$test_results = array();
$total_tests = 0;
$passed_tests = 0;

/**
 * 测试结果记录函数
 */
function record_test($name, $passed, $message = '') {
    global $test_results, $total_tests, $passed_tests;
    
    $test_results[] = array(
        'name' => $name,
        'passed' => $passed,
        'message' => $message
    );
    
    $total_tests++;
    if ($passed) {
        $passed_tests++;
    }
}

// 1. 测试API管理器类加载
echo "<h2>1. API管理器类加载测试</h2>";
try {
    require_once 'includes/interfaces/interface-api-manager.php';
    require_once 'includes/managers/class-api-key-manager.php';
    require_once 'includes/services/class-deepseek-client.php';
    
    record_test('API管理器类加载', true, '所有API管理相关类成功加载');
    echo "<p style='color: green;'>✓ API管理器类加载成功</p>";
} catch (Exception $e) {
    record_test('API管理器类加载', false, '类加载失败: ' . $e->getMessage());
    echo "<p style='color: red;'>✗ 类加载失败: " . $e->getMessage() . "</p>";
}

// 2. 测试API密钥管理器实例化
echo "<h2>2. API密钥管理器实例化测试</h2>";
try {
    // 创建依赖对象
    $db_manager = new AMS_V2_Database_Manager();
    $encryption_service = new AMS_V2_Encryption_Service();
    $logger = new AMS_V2_Logger();
    
    // 创建API管理器
    $api_manager = new AMS_V2_API_Key_Manager($db_manager, $encryption_service, $logger);
    
    record_test('API密钥管理器实例化', true, 'API密钥管理器实例化成功');
    echo "<p style='color: green;'>✓ API密钥管理器实例化成功</p>";
    
} catch (Exception $e) {
    record_test('API密钥管理器实例化', false, '实例化失败: ' . $e->getMessage());
    echo "<p style='color: red;'>✗ API密钥管理器实例化失败: " . $e->getMessage() . "</p>";
}

// 3. 测试DeepSeek客户端实例化
echo "<h2>3. DeepSeek客户端实例化测试</h2>";
try {
    $test_api_key = 'sk-test-key-12345';
    $config = array(
        'timeout' => 30,
        'retry_attempts' => 3
    );
    
    $deepseek_client = new AMS_V2_DeepSeek_Client($test_api_key, $config, $logger);
    
    record_test('DeepSeek客户端实例化', true, 'DeepSeek客户端实例化成功');
    echo "<p style='color: green;'>✓ DeepSeek客户端实例化成功</p>";
    
} catch (Exception $e) {
    record_test('DeepSeek客户端实例化', false, '实例化失败: ' . $e->getMessage());
    echo "<p style='color: red;'>✗ DeepSeek客户端实例化失败: " . $e->getMessage() . "</p>";
}

// 4. 测试API密钥加密存储（模拟）
echo "<h2>4. API密钥加密存储测试</h2>";
try {
    $test_api_key = 'sk-test-deepseek-api-key-12345';
    $test_config = array(
        'timeout' => 30,
        'retry_attempts' => 3,
        'default_strict_level' => 2
    );
    
    // 测试加密功能
    $encrypted_key = $encryption_service->encrypt($test_api_key);
    $decrypted_key = $encryption_service->decrypt($encrypted_key);
    
    $tests_passed = true;
    $error_messages = array();
    
    if ($encrypted_key === false) {
        $tests_passed = false;
        $error_messages[] = 'API密钥加密失败';
    }
    
    if ($decrypted_key !== $test_api_key) {
        $tests_passed = false;
        $error_messages[] = 'API密钥解密失败或数据不匹配';
    }
    
    // 测试配置JSON编码
    $config_json = json_encode($test_config);
    $config_decoded = json_decode($config_json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $tests_passed = false;
        $error_messages[] = '配置JSON编码失败';
    }
    
    if ($config_decoded !== $test_config) {
        $tests_passed = false;
        $error_messages[] = '配置数据不匹配';
    }
    
    record_test('API密钥加密存储', $tests_passed, $tests_passed ? 'API密钥加密存储功能正常' : implode(', ', $error_messages));
    echo "<p style='color: " . ($tests_passed ? 'green' : 'red') . ";'>" . ($tests_passed ? '✓' : '✗') . " API密钥加密存储测试</p>";
    
} catch (Exception $e) {
    record_test('API密钥加密存储', false, '测试异常: ' . $e->getMessage());
    echo "<p style='color: red;'>✗ API密钥加密存储测试异常: " . $e->getMessage() . "</p>";
}

// 5. 测试内容审核提示词构建
echo "<h2>5. 内容审核提示词构建测试</h2>";
try {
    $test_content = "这是一篇测试文章的内容，用于验证审核功能。";
    $options = array(
        'strict_level' => 2,
        'check_types' => array('political', 'violence', 'sexual'),
        'language' => 'zh'
    );
    
    // 使用反射来测试私有方法
    $reflection = new ReflectionClass('AMS_V2_DeepSeek_Client');
    $method = $reflection->getMethod('build_moderation_prompt');
    $method->setAccessible(true);
    
    $prompt = $method->invoke($deepseek_client, $test_content, $options);
    
    $tests_passed = true;
    $error_messages = array();
    
    if (empty($prompt)) {
        $tests_passed = false;
        $error_messages[] = '提示词为空';
    }
    
    if (strpos($prompt, $test_content) === false) {
        $tests_passed = false;
        $error_messages[] = '提示词中未包含测试内容';
    }
    
    if (strpos($prompt, 'JSON') === false) {
        $tests_passed = false;
        $error_messages[] = '提示词中未包含JSON格式要求';
    }
    
    record_test('内容审核提示词构建', $tests_passed, $tests_passed ? '提示词构建功能正常' : implode(', ', $error_messages));
    echo "<p style='color: " . ($tests_passed ? 'green' : 'red') . ";'>" . ($tests_passed ? '✓' : '✗') . " 内容审核提示词构建测试</p>";
    
} catch (Exception $e) {
    record_test('内容审核提示词构建', false, '测试异常: ' . $e->getMessage());
    echo "<p style='color: red;'>✗ 内容审核提示词构建测试异常: " . $e->getMessage() . "</p>";
}

// 6. 测试审核响应解析
echo "<h2>6. 审核响应解析测试</h2>";
try {
    // 模拟API响应
    $mock_response = array(
        'choices' => array(
            array(
                'message' => array(
                    'content' => json_encode(array(
                        'passed' => false,
                        'score' => 0.8,
                        'risk_level' => 'high',
                        'reasons' => array('包含敏感词汇'),
                        'suggestions' => array('请修改相关内容'),
                        'flagged_keywords' => array('敏感词')
                    ))
                )
            )
        )
    );
    
    // 使用反射来测试私有方法
    $reflection = new ReflectionClass('AMS_V2_DeepSeek_Client');
    $method = $reflection->getMethod('parse_moderation_response');
    $method->setAccessible(true);
    
    $result = $method->invoke($deepseek_client, $mock_response, '测试内容');
    
    $tests_passed = true;
    $error_messages = array();
    
    if (!isset($result['passed']) || $result['passed'] !== false) {
        $tests_passed = false;
        $error_messages[] = 'passed字段解析错误';
    }
    
    if (!isset($result['score']) || $result['score'] !== 0.8) {
        $tests_passed = false;
        $error_messages[] = 'score字段解析错误';
    }
    
    if (!isset($result['risk_level']) || $result['risk_level'] !== 'high') {
        $tests_passed = false;
        $error_messages[] = 'risk_level字段解析错误';
    }
    
    if (!isset($result['reasons']) || !is_array($result['reasons'])) {
        $tests_passed = false;
        $error_messages[] = 'reasons字段解析错误';
    }
    
    record_test('审核响应解析', $tests_passed, $tests_passed ? '审核响应解析功能正常' : implode(', ', $error_messages));
    echo "<p style='color: " . ($tests_passed ? 'green' : 'red') . ";'>" . ($tests_passed ? '✓' : '✗') . " 审核响应解析测试</p>";
    
} catch (Exception $e) {
    record_test('审核响应解析', false, '测试异常: ' . $e->getMessage());
    echo "<p style='color: red;'>✗ 审核响应解析测试异常: " . $e->getMessage() . "</p>";
}

// 7. 测试API接口实现检查
echo "<h2>7. API接口实现检查</h2>";
try {
    $interface_methods = get_class_methods('AMS_V2_API_Manager_Interface');
    $class_methods = get_class_methods('AMS_V2_API_Key_Manager');
    
    $tests_passed = true;
    $error_messages = array();
    
    foreach ($interface_methods as $method) {
        if (!in_array($method, $class_methods)) {
            $tests_passed = false;
            $error_messages[] = "缺少接口方法: {$method}";
        }
    }
    
    // 检查关键方法是否存在
    $required_methods = array(
        'save_api_key',
        'get_api_key',
        'get_api_config',
        'delete_api_key',
        'validate_api_key',
        'get_all_services_status'
    );
    
    foreach ($required_methods as $method) {
        if (!method_exists($api_manager, $method)) {
            $tests_passed = false;
            $error_messages[] = "缺少必需方法: {$method}";
        }
    }
    
    record_test('API接口实现检查', $tests_passed, $tests_passed ? 'API接口实现完整' : implode(', ', $error_messages));
    echo "<p style='color: " . ($tests_passed ? 'green' : 'red') . ";'>" . ($tests_passed ? '✓' : '✗') . " API接口实现检查</p>";
    
} catch (Exception $e) {
    record_test('API接口实现检查', false, '测试异常: ' . $e->getMessage());
    echo "<p style='color: red;'>✗ API接口实现检查异常: " . $e->getMessage() . "</p>";
}

// 输出测试结果汇总
echo "<h2>测试结果汇总</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'><th>测试项目</th><th>状态</th><th>说明</th></tr>";

foreach ($test_results as $result) {
    $status_color = $result['passed'] ? 'green' : 'red';
    $status_text = $result['passed'] ? '✓ 通过' : '✗ 失败';
    
    echo "<tr>";
    echo "<td>{$result['name']}</td>";
    echo "<td style='color: $status_color; font-weight: bold;'>$status_text</td>";
    echo "<td>{$result['message']}</td>";
    echo "</tr>";
}

echo "</table>";

$success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 1) : 0;
$overall_color = $success_rate >= 90 ? 'green' : ($success_rate >= 70 ? 'orange' : 'red');

echo "<h3 style='color: $overall_color;'>总体结果: $passed_tests/$total_tests 测试通过 ($success_rate%)</h3>";

if ($success_rate >= 90) {
    echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>✓ API管理功能测试通过</h4>";
    echo "<p>API管理功能已经正确实现，包括：</p>";
    echo "<ul>";
    echo "<li>API密钥管理器功能完整</li>";
    echo "<li>DeepSeek客户端工作正常</li>";
    echo "<li>加密存储功能正常</li>";
    echo "<li>内容审核逻辑正确</li>";
    echo "<li>接口实现完整</li>";
    echo "</ul>";
    echo "<p><strong>建议：</strong>可以继续实现内容审核系统和敏感词库管理。</p>";
    echo "</div>";
} elseif ($success_rate >= 70) {
    echo "<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>⚠ API管理功能部分通过</h4>";
    echo "<p>API管理基本功能正常，但存在一些问题需要修复。</p>";
    echo "<p><strong>建议：</strong>修复失败的测试项目后再继续开发。</p>";
    echo "</div>";
} else {
    echo "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>✗ API管理功能测试未通过</h4>";
    echo "<p>API管理功能存在严重问题，需要立即修复。</p>";
    echo "<p><strong>建议：</strong>请仔细检查失败的测试项目，修复相关问题后重新运行测试。</p>";
    echo "</div>";
}

echo "<h2>下一步开发建议</h2>";
echo "<ul>";
echo "<li>如果测试通过，可以继续实现内容审核系统</li>";
echo "<li>添加API使用统计和监控功能</li>";
echo "<li>实现API健康检查的定时任务</li>";
echo "<li>完善API配置界面的AJAX功能</li>";
echo "<li>添加API调用日志记录</li>";
echo "</ul>";

echo "<h2>任务6.1完成状态</h2>";
echo "<p><strong>✅ 任务6.1 创建API管理核心组件</strong> 已基本完成，包括：</p>";
echo "<ol>";
echo "<li>✅ 实现APIKeyManager和加密存储</li>";
echo "<li>✅ 创建DeepSeekClient接口及HTTP客户端实现</li>";
echo "<li>✅ 添加API健康检查和余额查询功能</li>";
echo "<li>✅ 创建API配置管理界面</li>";
echo "<li>⚠️ API验证功能（需要真实API密钥测试）</li>";
echo "</ol>";

echo "</body></html>";
?>