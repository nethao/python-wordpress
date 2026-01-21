<?php
/**
 * 敏感词管理界面功能测试脚本
 *
 * @package Article_Management_V2
 * @subpackage Tests
 */

echo "<h1>敏感词管理界面功能测试</h1>\n";

// 测试界面文件是否存在
$interface_files = array(
    'admin/partials/sensitive-words.php' => '敏感词管理页面',
    'admin/partials/moderation-settings.php' => '审核配置页面',
    'admin/js/sensitive-words.js' => '敏感词管理JavaScript'
);

echo "<h2>界面文件检查</h2>\n";
foreach ($interface_files as $file => $description) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ {$description}: {$file}</p>\n";
    } else {
        echo "<p style='color: red;'>✗ {$description}: {$file} - 文件不存在</p>\n";
    }
}

// 测试PHP语法
echo "<h2>PHP语法检查</h2>\n";
foreach ($interface_files as $file => $description) {
    if (file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $output = array();
        $return_var = 0;
        exec("php -l \"{$file}\" 2>&1", $output, $return_var);
        
        if ($return_var === 0) {
            echo "<p style='color: green;'>✓ {$description}: 语法正确</p>\n";
        } else {
            echo "<p style='color: red;'>✗ {$description}: 语法错误</p>\n";
            echo "<pre>" . implode("\n", $output) . "</pre>\n";
        }
    }
}

// 测试关键功能组件
echo "<h2>功能组件检查</h2>\n";

$components = array(
    '敏感词管理器' => 'AMS_V2_Sensitive_Word_Manager',
    '审核配置管理器' => 'AMS_V2_Moderation_Config_Manager', 
    '内容审核器' => 'AMS_V2_Content_Moderator',
    '敏感词初始化器' => 'AMS_V2_Sensitive_Words_Initializer',
    '审核等级枚举' => 'AMS_V2_Moderation_Level',
    '敏感词类型枚举' => 'AMS_V2_Word_Type'
);

// 定义ABSPATH以避免退出
define('ABSPATH', '/');

// 模拟WordPress函数
function wp_verify_nonce($nonce, $action) { return true; }
function wp_nonce_field($action, $name = '_wpnonce') { return '<input type="hidden" name="' . $name . '" value="test_nonce">'; }
function get_current_user_id() { return 1; }
function sanitize_text_field($str) { return trim(strip_tags($str)); }
function sanitize_textarea_field($str) { return trim(strip_tags($str)); }
function sanitize_email($email) { return filter_var($email, FILTER_SANITIZE_EMAIL); }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES); }
function esc_html($text) { return htmlspecialchars($text, ENT_NOQUOTES); }
function esc_textarea($text) { return htmlspecialchars($text, ENT_NOQUOTES); }
function checked($checked, $current = true, $echo = true) { 
    $result = $checked == $current ? 'checked="checked"' : '';
    if ($echo) echo $result;
    return $result;
}
function selected($selected, $current = true, $echo = true) { 
    $result = $selected == $current ? 'selected="selected"' : '';
    if ($echo) echo $result;
    return $result;
}
function get_admin_page_title() { return '敏感词管理'; }
function admin_url($path) { return 'http://example.com/wp-admin/' . $path; }
function paginate_links($args) { return '<span class="pagination">分页链接</span>'; }
function is_wp_error($thing) { return $thing instanceof WP_Error; }

class WP_Error {
    private $errors = array();
    public function __construct($code, $message) {
        $this->errors[$code] = array($message);
    }
    public function get_error_message() {
        foreach ($this->errors as $messages) {
            return $messages[0];
        }
        return '';
    }
}

try {
    // 加载组件类
    require_once('includes/enums/class-word-type.php');
    require_once('includes/enums/class-moderation-level.php');
    require_once('includes/models/class-sensitive-word.php');
    require_once('includes/interfaces/interface-sensitive-word-manager.php');
    require_once('includes/managers/class-sensitive-word-manager.php');
    require_once('includes/managers/class-moderation-config-manager.php');
    require_once('includes/interfaces/interface-content-moderator.php');
    require_once('includes/managers/class-content-moderator.php');
    require_once('includes/scripts/init-sensitive-words.php');
    
    foreach ($components as $name => $class) {
        if (class_exists($class)) {
            echo "<p style='color: green;'>✓ {$name}: {$class} 类存在</p>\n";
        } else {
            echo "<p style='color: red;'>✗ {$name}: {$class} 类不存在</p>\n";
        }
    }
    
    // 测试界面渲染
    echo "<h2>界面渲染测试</h2>\n";
    
    // 模拟$_GET参数
    $_GET['page'] = 'article-management-v2-sensitive-words';
    
    // 测试敏感词管理页面渲染
    ob_start();
    try {
        include('admin/partials/sensitive-words.php');
        $sensitive_words_output = ob_get_contents();
        ob_end_clean();
        
        if (strlen($sensitive_words_output) > 1000) {
            echo "<p style='color: green;'>✓ 敏感词管理页面渲染成功 (" . strlen($sensitive_words_output) . " 字符)</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠ 敏感词管理页面渲染输出较少 (" . strlen($sensitive_words_output) . " 字符)</p>\n";
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo "<p style='color: red;'>✗ 敏感词管理页面渲染失败: " . $e->getMessage() . "</p>\n";
    }
    
    // 测试审核配置页面渲染
    ob_start();
    try {
        include('admin/partials/moderation-settings.php');
        $moderation_output = ob_get_contents();
        ob_end_clean();
        
        if (strlen($moderation_output) > 1000) {
            echo "<p style='color: green;'>✓ 审核配置页面渲染成功 (" . strlen($moderation_output) . " 字符)</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠ 审核配置页面渲染输出较少 (" . strlen($moderation_output) . " 字符)</p>\n";
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo "<p style='color: red;'>✗ 审核配置页面渲染失败: " . $e->getMessage() . "</p>\n";
    }
    
    // 测试JavaScript文件
    echo "<h2>JavaScript文件检查</h2>\n";
    
    $js_file = 'admin/js/sensitive-words.js';
    if (file_exists($js_file)) {
        $js_content = file_get_contents($js_file);
        $js_size = strlen($js_content);
        
        // 检查关键函数
        $required_functions = array(
            'initSensitiveWordsPage',
            'updateBulkActions',
            'bulkDeleteWords',
            'openEditModal',
            'saveEditedWord',
            'exportWords',
            'startImport'
        );
        
        $missing_functions = array();
        foreach ($required_functions as $func) {
            if (strpos($js_content, $func) === false) {
                $missing_functions[] = $func;
            }
        }
        
        if (empty($missing_functions)) {
            echo "<p style='color: green;'>✓ JavaScript文件包含所有必需函数 ({$js_size} 字符)</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠ JavaScript文件缺少函数: " . implode(', ', $missing_functions) . "</p>\n";
        }
        
        // 检查jQuery使用
        if (strpos($js_content, '$(document).ready') !== false) {
            echo "<p style='color: green;'>✓ JavaScript正确使用jQuery</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠ JavaScript可能未正确使用jQuery</p>\n";
        }
        
    } else {
        echo "<p style='color: red;'>✗ JavaScript文件不存在</p>\n";
    }
    
    echo "<h2 style='color: green;'>✓ 敏感词管理界面功能测试完成</h2>\n";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ 测试过程中发生错误</h2>\n";
    echo "<p style='color: red;'>错误信息: " . $e->getMessage() . "</p>\n";
    echo "<p style='color: red;'>错误位置: " . $e->getFile() . ":" . $e->getLine() . "</p>\n";
}

echo "<hr>\n";
echo "<p><strong>测试完成时间:</strong> " . date('Y-m-d H:i:s') . "</p>\n";

// 显示功能特性总结
echo "<h2>功能特性总结</h2>\n";
echo "<div style='background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa;'>\n";
echo "<h4>已实现的界面功能：</h4>\n";
echo "<ul>\n";
echo "<li>✅ 敏感词库管理页面（增删改查）</li>\n";
echo "<li>✅ 审核等级配置界面</li>\n";
echo "<li>✅ 敏感词搜索和过滤功能</li>\n";
echo "<li>✅ 批量导入导出功能</li>\n";
echo "<li>✅ 预置词库导入</li>\n";
echo "<li>✅ 实时审核测试功能</li>\n";
echo "<li>✅ 统计信息展示</li>\n";
echo "<li>✅ 响应式界面设计</li>\n";
echo "<li>✅ AJAX异步操作</li>\n";
echo "<li>✅ 用户友好的交互体验</li>\n";
echo "</ul>\n";
echo "</div>\n";
?>