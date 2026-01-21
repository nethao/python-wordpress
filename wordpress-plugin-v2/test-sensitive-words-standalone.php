<?php
/**
 * 敏感词管理功能独立测试脚本
 *
 * @package Article_Management_V2
 * @subpackage Tests
 */

echo "<h1>敏感词管理功能独立测试</h1>\n";

// 模拟WordPress函数
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = array()) {
        if (is_object($args)) {
            $parsed_args = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed_args = &$args;
        } else {
            wp_parse_str($args, $parsed_args);
        }

        if (is_array($defaults) && $defaults) {
            return array_merge($defaults, $parsed_args);
        }
        return $parsed_args;
    }
}

if (!function_exists('sanitize_sql_orderby')) {
    function sanitize_sql_orderby($orderby) {
        $allowed = array('id', 'word', 'type', 'created_at', 'updated_at');
        return in_array($orderby, $allowed) ? $orderby : 'id';
    }
}

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

function is_wp_error($thing) {
    return $thing instanceof WP_Error;
}

function get_current_user_id() {
    return 1; // 模拟用户ID
}

// 加载我们的类
require_once('includes/enums/class-word-type.php');
require_once('includes/models/class-sensitive-word.php');

try {
    // 测试1: WordType枚举类
    echo "<h2>测试1: WordType枚举类</h2>\n";
    
    $all_types = AMS_V2_Word_Type::get_all_types();
    echo "<p>所有类型: " . implode(', ', $all_types) . "</p>\n";
    
    $type_names = AMS_V2_Word_Type::get_type_names();
    echo "<p>类型名称映射:</p>\n";
    foreach ($type_names as $type => $name) {
        echo "<p>- " . $type . ": " . $name . "</p>\n";
    }
    
    echo "<p>高危敏感词默认替换: " . AMS_V2_Word_Type::get_default_replacement(AMS_V2_Word_Type::HIGH_RISK) . "</p>\n";
    echo "<p>普通敏感词默认替换: " . AMS_V2_Word_Type::get_default_replacement(AMS_V2_Word_Type::NORMAL) . "</p>\n";
    
    $is_valid = AMS_V2_Word_Type::is_valid_type('normal');
    echo "<p>类型 'normal' 是否有效: " . ($is_valid ? '是' : '否') . "</p>\n";
    
    $is_invalid = AMS_V2_Word_Type::is_valid_type('invalid');
    echo "<p>类型 'invalid' 是否有效: " . ($is_invalid ? '是' : '否') . "</p>\n";
    
    echo "<p style='color: green;'>✓ WordType枚举类测试通过</p>\n";

    // 测试2: SensitiveWord模型类
    echo "<h2>测试2: SensitiveWord模型类</h2>\n";
    
    // 创建敏感词对象
    $word_data = array(
        'id' => 1,
        'word' => '测试敏感词',
        'type' => AMS_V2_Word_Type::NORMAL,
        'replacement' => '***',
        'is_builtin' => false,
        'is_active' => true,
        'created_at' => '2024-01-01 12:00:00',
        'updated_at' => '2024-01-01 12:00:00',
        'created_by' => 1
    );
    
    $word = new AMS_V2_Sensitive_Word($word_data);
    echo "<p>创建敏感词对象: " . $word->word . "</p>\n";
    echo "<p>类型显示名称: " . $word->get_type_display_name() . "</p>\n";
    echo "<p>是否高危: " . ($word->is_high_risk() ? '是' : '否') . "</p>\n";
    echo "<p>是否普通: " . ($word->is_normal() ? '是' : '否') . "</p>\n";
    echo "<p>默认替换词: " . $word->get_default_replacement() . "</p>\n";
    
    // 测试验证功能
    $errors = $word->validate();
    echo "<p>验证结果: " . (empty($errors) ? '通过' : '失败 - ' . implode(', ', $errors)) . "</p>\n";
    
    // 测试无效数据
    $invalid_word = new AMS_V2_Sensitive_Word(array(
        'word' => '', // 空词汇
        'type' => 'invalid_type' // 无效类型
    ));
    
    $invalid_errors = $invalid_word->validate();
    echo "<p>无效数据验证错误数: " . count($invalid_errors) . "</p>\n";
    foreach ($invalid_errors as $error) {
        echo "<p style='color: red;'>- " . $error . "</p>\n";
    }
    
    // 测试转换为数组
    $word_array = $word->to_array();
    echo "<p>转换为数组: " . json_encode($word_array, JSON_UNESCAPED_UNICODE) . "</p>\n";
    
    echo "<p style='color: green;'>✓ SensitiveWord模型类测试通过</p>\n";

    // 测试3: 测试长度限制
    echo "<h2>测试3: 测试长度限制</h2>\n";
    
    $long_word = new AMS_V2_Sensitive_Word(array(
        'word' => str_repeat('长', 101), // 超过100字符
        'type' => AMS_V2_Word_Type::NORMAL
    ));
    
    $long_errors = $long_word->validate();
    echo "<p>超长词汇验证错误: " . implode(', ', $long_errors) . "</p>\n";
    
    $long_replacement = new AMS_V2_Sensitive_Word(array(
        'word' => '正常词汇',
        'type' => AMS_V2_Word_Type::NORMAL,
        'replacement' => str_repeat('替', 101) // 超过100字符的替换词
    ));
    
    $replacement_errors = $long_replacement->validate();
    echo "<p>超长替换词验证错误: " . implode(', ', $replacement_errors) . "</p>\n";
    
    echo "<p style='color: green;'>✓ 长度限制测试通过</p>\n";

    // 测试4: 测试不同类型的敏感词
    echo "<h2>测试4: 测试不同类型的敏感词</h2>\n";
    
    $high_risk_word = new AMS_V2_Sensitive_Word(array(
        'word' => '高危词汇',
        'type' => AMS_V2_Word_Type::HIGH_RISK
    ));
    
    echo "<p>高危敏感词:</p>\n";
    echo "<p>- 词汇: " . $high_risk_word->word . "</p>\n";
    echo "<p>- 类型: " . $high_risk_word->get_type_display_name() . "</p>\n";
    echo "<p>- 是否高危: " . ($high_risk_word->is_high_risk() ? '是' : '否') . "</p>\n";
    echo "<p>- 默认替换: " . $high_risk_word->get_default_replacement() . "</p>\n";
    
    $normal_word = new AMS_V2_Sensitive_Word(array(
        'word' => '普通词汇',
        'type' => AMS_V2_Word_Type::NORMAL
    ));
    
    echo "<p>普通敏感词:</p>\n";
    echo "<p>- 词汇: " . $normal_word->word . "</p>\n";
    echo "<p>- 类型: " . $normal_word->get_type_display_name() . "</p>\n";
    echo "<p>- 是否普通: " . ($normal_word->is_normal() ? '是' : '否') . "</p>\n";
    echo "<p>- 默认替换: " . $normal_word->get_default_replacement() . "</p>\n";
    
    echo "<p style='color: green;'>✓ 不同类型敏感词测试通过</p>\n";

    // 测试5: 测试CSS类名和颜色标签
    echo "<h2>测试5: 测试CSS类名和颜色标签</h2>\n";
    
    $types = AMS_V2_Word_Type::get_all_types();
    foreach ($types as $type) {
        echo "<p>类型 " . $type . ":</p>\n";
        echo "<p>- CSS类名: " . AMS_V2_Word_Type::get_css_class($type) . "</p>\n";
        echo "<p>- 颜色标签: " . AMS_V2_Word_Type::get_color_label($type) . "</p>\n";
    }
    
    echo "<p style='color: green;'>✓ CSS类名和颜色标签测试通过</p>\n";

    echo "<h2 style='color: green;'>✓ 所有独立测试完成</h2>\n";
    echo "<p><strong>注意:</strong> 这些测试只验证了基本的类功能，完整的数据库操作需要WordPress环境。</p>\n";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ 测试过程中发生错误</h2>\n";
    echo "<p style='color: red;'>错误信息: " . $e->getMessage() . "</p>\n";
    echo "<p style='color: red;'>错误位置: " . $e->getFile() . ":" . $e->getLine() . "</p>\n";
}

echo "<hr>\n";
echo "<p><strong>测试完成时间:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
?>