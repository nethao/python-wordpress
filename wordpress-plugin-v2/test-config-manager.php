<?php
// 定义ABSPATH以避免退出
define('ABSPATH', '/');

echo "测试配置管理器基本功能...\n";

// 加载枚举类
require_once('includes/enums/class-moderation-level.php');
echo "✓ ModerationLevel类加载成功\n";

// 加载配置管理器
require_once('includes/managers/class-moderation-config-manager.php');
echo "✓ ModerationConfigManager类加载成功\n";

// 测试默认配置
$default_config = AMS_V2_Moderation_Config_Manager::get_default_config();
echo "✓ 获取默认配置成功\n";
echo "默认审核等级: " . $default_config['current_level'] . "\n";
echo "自动审核: " . ($default_config['auto_moderate'] ? '启用' : '禁用') . "\n";
echo "记录所有动作: " . ($default_config['log_all_actions'] ? '启用' : '禁用') . "\n";

echo "\n所有配置管理器基本功能测试通过！\n";
?>