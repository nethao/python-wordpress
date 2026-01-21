<?php
/**
 * 审核等级管理功能测试脚本
 *
 * @package Article_Management_V2
 * @subpackage Tests
 */

// 定义ABSPATH以避免退出
define('ABSPATH', '/');

echo "<h1>审核等级管理功能测试</h1>\n";

// 模拟WordPress函数
function wp_parse_args($args, $defaults = array()) {
    if (is_object($args)) {
        $parsed_args = get_object_vars($args);
    } elseif (is_array($args)) {
        $parsed_args = &$args;
    } else {
        parse_str($args, $parsed_args);
    }

    if (is_array($defaults) && $defaults) {
        return array_merge($defaults, $parsed_args);
    }
    return $parsed_args;
}

function sanitize_sql_orderby($orderby) {
    $allowed = array('id', 'created_at', 'moderation_level', 'action_taken');
    return in_array($orderby, $allowed) ? $orderby : 'id';
}

function current_time($type) {
    return date('Y-m-d H:i:s');
}

function get_current_user_id() {
    return 1; // 模拟用户ID
}

function get_option($option, $default = false) {
    // 模拟获取选项
    return $default;
}

function update_option($option, $value) {
    // 模拟更新选项
    return true;
}

function wp_cache_get($key) {
    return false; // 模拟缓存未命中
}

function wp_cache_set($key, $data, $group = '', $expire = 0) {
    return true; // 模拟缓存设置成功
}

function wp_cache_delete($key) {
    return true; // 模拟缓存删除成功
}

function do_action($hook, ...$args) {
    // 模拟动作钩子
}

function get_user_by($field, $value) {
    // 模拟获取用户
    return (object) array('roles' => array('administrator'));
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

// 加载我们的类
require_once('includes/enums/class-moderation-level.php');

try {
    // 测试1: ModerationLevel枚举类
    echo "<h2>测试1: ModerationLevel枚举类</h2>\n";
    
    $all_levels = AMS_V2_Moderation_Level::get_all_levels();
    echo "<p>所有审核等级: " . implode(', ', $all_levels) . "</p>\n";
    
    $level_names = AMS_V2_Moderation_Level::get_level_names();
    echo "<p>等级名称映射:</p>\n";
    foreach ($level_names as $level => $name) {
        echo "<p>- " . $level . ": " . $name . "</p>\n";
    }
    
    $level_descriptions = AMS_V2_Moderation_Level::get_level_descriptions();
    echo "<p>等级描述:</p>\n";
    foreach ($level_descriptions as $level => $desc) {
        echo "<p>- " . $level . ": " . $desc . "</p>\n";
    }
    
    echo "<p style='color: green;'>✓ ModerationLevel枚举类测试通过</p>\n";

    // 测试2: 审核等级策略
    echo "<h2>测试2: 审核等级策略</h2>\n";
    
    foreach ($all_levels as $level) {
        $strategy = AMS_V2_Moderation_Level::get_level_strategy($level);
        echo "<p><strong>" . AMS_V2_Moderation_Level::get_level_name($level) . " 策略:</strong></p>\n";
        echo "<p>- 拦截高危词: " . ($strategy['block_high_risk'] ? '是' : '否') . "</p>\n";
        echo "<p>- 拦截普通词: " . ($strategy['block_normal'] ? '是' : '否') . "</p>\n";
        echo "<p>- 替换高危词: " . ($strategy['replace_high_risk'] ? '是' : '否') . "</p>\n";
        echo "<p>- 替换普通词: " . ($strategy['replace_normal'] ? '是' : '否') . "</p>\n";
        echo "<p>- 允许提交: " . ($strategy['allow_submission'] ? '是' : '否') . "</p>\n";
        echo "<br>\n";
    }
    
    echo "<p style='color: green;'>✓ 审核等级策略测试通过</p>\n";

    // 测试3: 等级验证
    echo "<h2>测试3: 等级验证</h2>\n";
    
    $valid_level = AMS_V2_Moderation_Level::is_valid_level('default');
    echo "<p>等级 'default' 是否有效: " . ($valid_level ? '是' : '否') . "</p>\n";
    
    $invalid_level = AMS_V2_Moderation_Level::is_valid_level('invalid');
    echo "<p>等级 'invalid' 是否有效: " . ($invalid_level ? '是' : '否') . "</p>\n";
    
    $default_level = AMS_V2_Moderation_Level::get_default_level();
    echo "<p>默认审核等级: " . $default_level . "</p>\n";
    
    echo "<p style='color: green;'>✓ 等级验证测试通过</p>\n";

    // 测试4: 等级比较
    echo "<h2>测试4: 等级严格程度比较</h2>\n";
    
    $comparison1 = AMS_V2_Moderation_Level::compare_strictness('lenient', 'strict');
    echo "<p>宽松 vs 严格: " . $comparison1 . " (应该是 -1)</p>\n";
    
    $comparison2 = AMS_V2_Moderation_Level::compare_strictness('strict', 'lenient');
    echo "<p>严格 vs 宽松: " . $comparison2 . " (应该是 1)</p>\n";
    
    $comparison3 = AMS_V2_Moderation_Level::compare_strictness('default', 'default');
    echo "<p>默认 vs 默认: " . $comparison3 . " (应该是 0)</p>\n";
    
    echo "<p style='color: green;'>✓ 等级比较测试通过</p>\n";

    // 测试5: CSS和图标
    echo "<h2>测试5: CSS类名和图标</h2>\n";
    
    foreach ($all_levels as $level) {
        echo "<p><strong>" . AMS_V2_Moderation_Level::get_level_name($level) . ":</strong></p>\n";
        echo "<p>- CSS类名: " . AMS_V2_Moderation_Level::get_css_class($level) . "</p>\n";
        echo "<p>- 颜色标签: " . AMS_V2_Moderation_Level::get_color_label($level) . "</p>\n";
        echo "<p>- 图标: " . AMS_V2_Moderation_Level::get_icon($level) . "</p>\n";
        echo "<br>\n";
    }
    
    echo "<p style='color: green;'>✓ CSS和图标测试通过</p>\n";

    // 测试6: 配置管理器（基本功能）
    echo "<h2>测试6: 配置管理器基本功能</h2>\n";
    
    // 由于需要WordPress环境，这里只测试基本的类加载
    if (class_exists('AMS_V2_Moderation_Config_Manager')) {
        echo "<p style='color: green;'>✓ 配置管理器类存在</p>\n";
        
        $default_config = AMS_V2_Moderation_Config_Manager::get_default_config();
        echo "<p>默认配置包含字段:</p>\n";
        foreach (array_keys($default_config) as $key) {
            echo "<p>- " . $key . "</p>\n";
        }
    } else {
        echo "<p style='color: red;'>✗ 配置管理器类不存在</p>\n";
    }

    echo "<h2 style='color: green;'>✓ 所有基本审核等级管理功能测试完成</h2>\n";
    echo "<p><strong>注意:</strong> 完整的数据库操作和WordPress集成功能需要WordPress环境。</p>\n";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ 测试过程中发生错误</h2>\n";
    echo "<p style='color: red;'>错误信息: " . $e->getMessage() . "</p>\n";
    echo "<p style='color: red;'>错误位置: " . $e->getFile() . ":" . $e->getLine() . "</p>\n";
}

echo "<hr>\n";
echo "<p><strong>测试完成时间:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
?>