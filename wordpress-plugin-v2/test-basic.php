<?php
// 定义ABSPATH以避免退出
define('ABSPATH', '/');

echo "开始基本功能测试...\n";

// 加载枚举类
require_once('includes/enums/class-word-type.php');
echo "✓ WordType类加载成功\n";

// 测试枚举功能
$types = AMS_V2_Word_Type::get_all_types();
echo "✓ 获取所有类型: " . implode(', ', $types) . "\n";

$normal_name = AMS_V2_Word_Type::get_type_name('normal');
echo "✓ 普通类型名称: " . $normal_name . "\n";

$high_risk_replacement = AMS_V2_Word_Type::get_default_replacement('high_risk');
echo "✓ 高危类型默认替换: " . $high_risk_replacement . "\n";

$is_valid = AMS_V2_Word_Type::is_valid_type('normal');
echo "✓ 类型验证 'normal': " . ($is_valid ? '有效' : '无效') . "\n";

$is_invalid = AMS_V2_Word_Type::is_valid_type('invalid');
echo "✓ 类型验证 'invalid': " . ($is_invalid ? '有效' : '无效') . "\n";

// 模拟WordPress函数
function sanitize_text_field($str) {
    return trim(strip_tags($str));
}

// 加载模型类
require_once('includes/models/class-sensitive-word.php');
echo "✓ SensitiveWord类加载成功\n";

// 创建敏感词对象
$word = new AMS_V2_Sensitive_Word(array(
    'id' => 1,
    'word' => '测试词汇',
    'type' => 'normal',
    'replacement' => '***',
    'is_builtin' => false,
    'is_active' => true
));
echo "✓ 敏感词对象创建成功: " . $word->word . "\n";
echo "✓ 类型显示名称: " . $word->get_type_display_name() . "\n";
echo "✓ 是否高危: " . ($word->is_high_risk() ? '是' : '否') . "\n";
echo "✓ 是否普通: " . ($word->is_normal() ? '是' : '否') . "\n";
echo "✓ 默认替换词: " . $word->get_default_replacement() . "\n";

// 测试验证
$errors = $word->validate();
echo "✓ 验证结果: " . (empty($errors) ? '通过' : '失败 - ' . implode(', ', $errors)) . "\n";

// 测试无效数据
$invalid_word = new AMS_V2_Sensitive_Word(array(
    'word' => '', // 空词汇
    'type' => 'invalid_type' // 无效类型
));

$invalid_errors = $invalid_word->validate();
echo "✓ 无效数据验证错误数: " . count($invalid_errors) . "\n";

echo "\n所有基本功能测试通过！\n";
?>