<?php
echo "开始测试...\n";

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

// 加载模型类
require_once('includes/models/class-sensitive-word.php');
echo "✓ SensitiveWord类加载成功\n";

// 创建敏感词对象
$word = new AMS_V2_Sensitive_Word(array(
    'word' => '测试词汇',
    'type' => 'normal'
));
echo "✓ 敏感词对象创建成功: " . $word->word . "\n";

echo "所有基本测试通过！\n";
?>