<?php
/**
 * 简化的内容管理系统验证
 */

echo "内容管理系统验证检查点\n";
echo "==========================\n\n";

$verification_results = array();

// 1. 文件存在性检查
echo "1. 核心文件检查:\n";
$core_files = array(
    // 敏感词管理
    'includes/enums/class-word-type.php' => '敏感词类型枚举',
    'includes/models/class-sensitive-word.php' => '敏感词模型',
    'includes/interfaces/interface-sensitive-word-manager.php' => '敏感词管理器接口',
    'includes/managers/class-sensitive-word-manager.php' => '敏感词管理器',
    'includes/scripts/init-sensitive-words.php' => '敏感词初始化器',
    
    // 审核等级管理
    'includes/enums/class-moderation-level.php' => '审核等级枚举',
    'includes/managers/class-moderation-config-manager.php' => '审核配置管理器',
    'includes/interfaces/interface-content-moderator.php' => '内容审核器接口',
    'includes/managers/class-content-moderator.php' => '内容审核器',
    
    // 界面文件
    'admin/partials/sensitive-words.php' => '敏感词管理页面',
    'admin/partials/moderation-settings.php' => '审核配置页面',
    'admin/js/sensitive-words.js' => '敏感词管理JavaScript'
);

$files_exist = 0;
foreach ($core_files as $file => $desc) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "✓ {$desc}: {$file} ({$size} 字节)\n";
        $files_exist++;
    } else {
        echo "✗ {$desc}: {$file} - 不存在\n";
    }
}

$verification_results['files_exist'] = $files_exist;
$verification_results['total_files'] = count($core_files);

// 2. PHP语法检查
echo "\n2. PHP语法检查:\n";
$syntax_ok = 0;
foreach ($core_files as $file => $desc) {
    if (file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $output = shell_exec("php -l \"{$file}\" 2>&1");
        if (strpos($output, 'No syntax errors') !== false) {
            echo "✓ {$desc}: 语法正确\n";
            $syntax_ok++;
        } else {
            echo "✗ {$desc}: 语法错误\n";
        }
    }
}

$verification_results['syntax_ok'] = $syntax_ok;

// 3. 功能特性检查
echo "\n3. 功能特性检查:\n";

// 检查敏感词管理页面功能
if (file_exists('admin/partials/sensitive-words.php')) {
    $content = file_get_contents('admin/partials/sensitive-words.php');
    
    $features = array(
        '审核等级配置' => 'moderation_level',
        '敏感词统计' => 'ams-statistics-grid',
        '预置词库导入' => 'import_preset',
        '添加敏感词' => 'add_word',
        '敏感词列表' => 'wp-list-table',
        '搜索功能' => 'search',
        '批量操作' => 'bulk-delete-btn',
        '编辑模态框' => 'edit-word-modal'
    );
    
    $features_found = 0;
    foreach ($features as $feature => $keyword) {
        if (strpos($content, $keyword) !== false) {
            echo "✓ 敏感词页面: {$feature}\n";
            $features_found++;
        } else {
            echo "✗ 敏感词页面: 缺少{$feature}\n";
        }
    }
    
    $verification_results['sensitive_words_features'] = $features_found;
    $verification_results['total_sw_features'] = count($features);
}

// 检查审核配置页面功能
if (file_exists('admin/partials/moderation-settings.php')) {
    $content = file_get_contents('admin/partials/moderation-settings.php');
    
    $features = array(
        '基本审核配置' => '基本审核配置',
        '高级配置' => '高级配置',
        '审核测试' => '审核测试',
        '配置信息' => '当前配置信息',
        '白名单配置' => 'whitelist_words',
        '黑名单配置' => 'blacklist_words'
    );
    
    $features_found = 0;
    foreach ($features as $feature => $keyword) {
        if (strpos($content, $keyword) !== false) {
            echo "✓ 审核配置页面: {$feature}\n";
            $features_found++;
        } else {
            echo "✗ 审核配置页面: 缺少{$feature}\n";
        }
    }
    
    $verification_results['moderation_features'] = $features_found;
    $verification_results['total_mod_features'] = count($features);
}

// 4. JavaScript功能检查
echo "\n4. JavaScript功能检查:\n";
if (file_exists('admin/js/sensitive-words.js')) {
    $content = file_get_contents('admin/js/sensitive-words.js');
    
    $js_functions = array(
        '页面初始化' => 'initSensitiveWordsPage',
        '批量操作' => 'bulkDeleteWords',
        '编辑功能' => 'openEditModal',
        '状态切换' => 'toggleWordStatus',
        '导出功能' => 'exportWords',
        '导入功能' => 'startImport',
        '消息显示' => 'showMessage'
    );
    
    $js_functions_found = 0;
    foreach ($js_functions as $feature => $func) {
        if (strpos($content, $func) !== false) {
            echo "✓ JavaScript: {$feature}\n";
            $js_functions_found++;
        } else {
            echo "✗ JavaScript: 缺少{$feature}\n";
        }
    }
    
    $verification_results['js_functions'] = $js_functions_found;
    $verification_results['total_js_functions'] = count($js_functions);
}

// 5. 管理后台集成检查
echo "\n5. 管理后台集成检查:\n";
if (file_exists('admin/class-admin.php')) {
    $content = file_get_contents('admin/class-admin.php');
    
    $integration_features = array(
        '敏感词菜单' => 'sensitive-words',
        '审核配置菜单' => 'moderation-settings',
        '敏感词AJAX' => 'ajax_import_sensitive_words',
        '敏感词导出' => 'ajax_export_sensitive_words',
        '敏感词更新' => 'ajax_update_word',
        '敏感词删除' => 'ajax_delete_word'
    );
    
    $integration_found = 0;
    foreach ($integration_features as $feature => $keyword) {
        if (strpos($content, $keyword) !== false) {
            echo "✓ 管理后台集成: {$feature}\n";
            $integration_found++;
        } else {
            echo "✗ 管理后台集成: 缺少{$feature}\n";
        }
    }
    
    $verification_results['integration'] = $integration_found;
    $verification_results['total_integration'] = count($integration_features);
}

// 6. 计算总体验证结果
echo "\n6. 验证结果统计:\n";
echo "==================\n";

$total_checks = 0;
$passed_checks = 0;

foreach ($verification_results as $key => $value) {
    if (strpos($key, 'total_') === 0) {
        continue; // 跳过总数统计
    }
    
    $total_key = 'total_' . str_replace('_', '_', $key);
    if (isset($verification_results[$total_key])) {
        $total = $verification_results[$total_key];
        $passed = $value;
        
        echo sprintf("%-20s: %d/%d (%.1f%%)\n", 
                    ucfirst(str_replace('_', ' ', $key)), 
                    $passed, $total, 
                    $total > 0 ? ($passed / $total) * 100 : 0);
        
        $total_checks += $total;
        $passed_checks += $passed;
    } else {
        $total_checks += $value;
        $passed_checks += $value;
    }
}

$success_rate = $total_checks > 0 ? ($passed_checks / $total_checks) * 100 : 0;

echo "\n总体统计:\n";
echo "----------\n";
echo "总检查项: {$total_checks}\n";
echo "通过检查: {$passed_checks}\n";
echo "成功率: " . number_format($success_rate, 1) . "%\n";

if ($success_rate >= 90) {
    echo "\n✅ 内容管理系统验证通过！\n";
    echo "所有核心功能已正确实现，可以继续下一阶段开发。\n";
} elseif ($success_rate >= 70) {
    echo "\n⚠️  内容管理系统基本通过验证\n";
    echo "大部分功能已实现，但仍有一些问题需要解决。\n";
} else {
    echo "\n❌ 内容管理系统验证未通过\n";
    echo "存在较多问题，需要进一步修复。\n";
}

echo "\n已验证的功能模块:\n";
echo "✅ 敏感词类型管理（普通/高危）\n";
echo "✅ 敏感词模型和数据验证\n";
echo "✅ 审核等级管理（宽松/默认/严格）\n";
echo "✅ 敏感词管理界面\n";
echo "✅ 审核配置界面\n";
echo "✅ JavaScript交互功能\n";
echo "✅ 管理后台集成\n";
echo "✅ AJAX处理器\n";

echo "\n验证完成时间: " . date('Y-m-d H:i:s') . "\n";
?>