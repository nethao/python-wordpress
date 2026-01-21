<?php
/**
 * 敏感词管理功能测试脚本
 *
 * @package Article_Management_V2
 * @subpackage Tests
 */

// 设置WordPress环境
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

// 加载插件文件
require_once('article-management-v2.php');

echo "<h1>敏感词管理功能测试</h1>\n";

try {
    // 初始化敏感词管理器
    $word_manager = new AMS_V2_Sensitive_Word_Manager();
    echo "<h2>✓ 敏感词管理器初始化成功</h2>\n";

    // 测试1: 创建敏感词
    echo "<h3>测试1: 创建敏感词</h3>\n";
    
    $test_word_data = array(
        'word' => '测试敏感词',
        'type' => AMS_V2_Word_Type::NORMAL,
        'replacement' => '***',
        'is_builtin' => false,
        'is_active' => true,
        'created_by' => 1
    );
    
    $created_word = $word_manager->create_word($test_word_data);
    if (is_wp_error($created_word)) {
        echo "<p style='color: red;'>✗ 创建敏感词失败: " . $created_word->get_error_message() . "</p>\n";
    } else {
        echo "<p style='color: green;'>✓ 成功创建敏感词: " . $created_word->word . " (ID: " . $created_word->id . ")</p>\n";
    }

    // 测试2: 获取敏感词
    echo "<h3>测试2: 获取敏感词</h3>\n";
    
    if (!is_wp_error($created_word)) {
        $retrieved_word = $word_manager->get_word_by_id($created_word->id);
        if ($retrieved_word) {
            echo "<p style='color: green;'>✓ 成功获取敏感词: " . $retrieved_word->word . "</p>\n";
        } else {
            echo "<p style='color: red;'>✗ 获取敏感词失败</p>\n";
        }
    }

    // 测试3: 搜索敏感词
    echo "<h3>测试3: 搜索敏感词</h3>\n";
    
    $search_results = $word_manager->search_words('测试', array('limit' => 10));
    echo "<p>搜索 '测试' 找到 " . $search_results['total'] . " 个结果</p>\n";
    
    foreach ($search_results['words'] as $word) {
        echo "<p>- " . $word->word . " (" . $word->get_type_display_name() . ")</p>\n";
    }

    // 测试4: 文本检查
    echo "<h3>测试4: 文本敏感词检查</h3>\n";
    
    $test_text = "这是一个包含测试敏感词的文本内容";
    $check_result = $word_manager->check_text($test_text);
    
    if ($check_result['has_sensitive_words']) {
        echo "<p style='color: orange;'>⚠ 文本包含敏感词:</p>\n";
        foreach ($check_result['found_words'] as $found) {
            echo "<p>- " . $found['word'] . " (" . AMS_V2_Word_Type::get_type_name($found['type']) . ")</p>\n";
        }
    } else {
        echo "<p style='color: green;'>✓ 文本未包含敏感词</p>\n";
    }

    // 测试5: 文本替换
    echo "<h3>测试5: 文本敏感词替换</h3>\n";
    
    $replaced_text = $word_manager->replace_text($test_text);
    echo "<p>原文本: " . $test_text . "</p>\n";
    echo "<p>替换后: " . $replaced_text . "</p>\n";

    // 测试6: 获取统计信息
    echo "<h3>测试6: 获取敏感词统计</h3>\n";
    
    $statistics = $word_manager->get_word_statistics();
    echo "<p>总敏感词数: " . $statistics['total'] . "</p>\n";
    
    if (isset($statistics['by_type'])) {
        echo "<p>按类型统计:</p>\n";
        foreach ($statistics['by_type'] as $type => $count) {
            echo "<p>- " . AMS_V2_Word_Type::get_type_name($type) . ": " . $count . "</p>\n";
        }
    }
    
    if (isset($statistics['by_status'])) {
        echo "<p>按状态统计:</p>\n";
        foreach ($statistics['by_status'] as $status => $count) {
            $status_name = $status === 'active' ? '激活' : '停用';
            echo "<p>- " . $status_name . ": " . $count . "</p>\n";
        }
    }

    // 测试7: 批量导入
    echo "<h3>测试7: 批量导入敏感词</h3>\n";
    
    $import_words = array('批量词1', '批量词2', '批量词3');
    $import_result = $word_manager->import_words($import_words, AMS_V2_Word_Type::NORMAL, 1);
    
    echo "<p>导入结果:</p>\n";
    echo "<p>- 成功: " . $import_result['success'] . "</p>\n";
    echo "<p>- 失败: " . $import_result['failed'] . "</p>\n";
    echo "<p>- 跳过: " . $import_result['skipped'] . "</p>\n";
    
    if (!empty($import_result['errors'])) {
        echo "<p>错误信息:</p>\n";
        foreach ($import_result['errors'] as $error) {
            echo "<p style='color: red;'>- " . $error . "</p>\n";
        }
    }

    // 测试8: 初始化内置敏感词库
    echo "<h3>测试8: 初始化内置敏感词库</h3>\n";
    
    $init_result = $word_manager->initialize_builtin_words();
    if ($init_result) {
        echo "<p style='color: green;'>✓ 内置敏感词库初始化成功</p>\n";
    } else {
        echo "<p style='color: blue;'>ℹ 内置敏感词库已存在</p>\n";
    }

    // 测试9: 测试敏感词初始化器
    echo "<h3>测试9: 测试敏感词初始化器</h3>\n";
    
    $initializer = new AMS_V2_Sensitive_Words_Initializer();
    $init_result = $initializer->initialize();
    
    echo "<p>初始化结果: " . ($init_result['success'] ? '成功' : '失败') . "</p>\n";
    echo "<p>消息: " . $init_result['message'] . "</p>\n";
    
    if (isset($init_result['statistics'])) {
        echo "<p>当前统计:</p>\n";
        echo "<p>- 总数: " . $init_result['statistics']['total'] . "</p>\n";
    }

    // 测试10: 预置词库
    echo "<h3>测试10: 预置词库功能</h3>\n";
    
    $preset_libraries = $initializer->get_preset_word_libraries();
    echo "<p>可用预置词库:</p>\n";
    
    foreach ($preset_libraries as $key => $library) {
        echo "<p>- " . $library['name'] . ": " . $library['description'] . "</p>\n";
        echo "<p>  高危词汇: " . count($library['high_risk_words']) . " 个</p>\n";
        echo "<p>  普通词汇: " . count($library['normal_words']) . " 个</p>\n";
    }

    // 清理测试数据
    echo "<h3>清理测试数据</h3>\n";
    
    if (!is_wp_error($created_word)) {
        $delete_result = $word_manager->delete_word($created_word->id);
        if (is_wp_error($delete_result)) {
            echo "<p style='color: red;'>✗ 删除测试敏感词失败: " . $delete_result->get_error_message() . "</p>\n";
        } else {
            echo "<p style='color: green;'>✓ 成功删除测试敏感词</p>\n";
        }
    }

    // 删除批量导入的测试词汇
    $cleanup_words = $word_manager->search_words('批量词', array('limit' => 10));
    if (!empty($cleanup_words['words'])) {
        $cleanup_ids = array();
        foreach ($cleanup_words['words'] as $word) {
            $cleanup_ids[] = $word->id;
        }
        
        $cleanup_result = $word_manager->delete_words($cleanup_ids);
        echo "<p>清理批量导入词汇: 成功 " . $cleanup_result['success'] . " 个</p>\n";
    }

    echo "<h2 style='color: green;'>✓ 所有敏感词管理功能测试完成</h2>\n";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ 测试过程中发生错误</h2>\n";
    echo "<p style='color: red;'>错误信息: " . $e->getMessage() . "</p>\n";
    echo "<p style='color: red;'>错误位置: " . $e->getFile() . ":" . $e->getLine() . "</p>\n";
}

echo "<hr>\n";
echo "<p><strong>测试完成时间:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
?>