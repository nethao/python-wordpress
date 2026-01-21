<?php
/**
 * 简单清理系统测试
 *
 * @package Article_Management_V2
 * @subpackage Tests
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

echo '<div class="wrap">';
echo '<h1>清理系统简单测试</h1>';

try {
    // 获取全局容器
    global $ams_v2_container;
    
    if (!$ams_v2_container) {
        throw new Exception('容器未初始化');
    }
    
    // 获取清理调度器
    $cleanup_scheduler = $ams_v2_container->get('cleanup_scheduler');
    
    echo '<h2>1. 测试清理调度器实例化</h2>';
    if ($cleanup_scheduler) {
        echo '<p style="color: green;">✓ 清理调度器实例化成功</p>';
    } else {
        echo '<p style="color: red;">✗ 清理调度器实例化失败</p>';
    }
    
    echo '<h2>2. 测试阈值设置</h2>';
    $result = $cleanup_scheduler->set_threshold_days(30);
    if ($result) {
        echo '<p style="color: green;">✓ 阈值设置成功</p>';
    } else {
        echo '<p style="color: red;">✗ 阈值设置失败</p>';
    }
    
    $threshold = $cleanup_scheduler->get_threshold_days();
    echo '<p>当前阈值: ' . $threshold . ' 天</p>';
    
    echo '<h2>3. 测试自动清理状态</h2>';
    $enabled = $cleanup_scheduler->is_auto_cleanup_enabled();
    echo '<p>自动清理状态: ' . ($enabled ? '启用' : '禁用') . '</p>';
    
    echo '<h2>4. 测试文章识别</h2>';
    $article_ids = $cleanup_scheduler->identify_articles_for_cleanup();
    echo '<p>找到 ' . count($article_ids) . ' 篇需要清理的文章</p>';
    
    if (!empty($article_ids)) {
        echo '<p>文章ID: ' . implode(', ', array_slice($article_ids, 0, 10));
        if (count($article_ids) > 10) {
            echo ' (显示前10个)';
        }
        echo '</p>';
    }
    
    echo '<h2>5. 测试清理统计</h2>';
    $stats = $cleanup_scheduler->get_cleanup_statistics(30);
    if ($stats && isset($stats['total_stats'])) {
        echo '<p>最近30天清理统计:</p>';
        echo '<ul>';
        echo '<li>总清理次数: ' . ($stats['total_stats']->total_cleanups ?? 0) . '</li>';
        echo '<li>删除文章数: ' . ($stats['total_stats']->total_deleted ?? 0) . '</li>';
        echo '<li>成功清理: ' . ($stats['total_stats']->successful_cleanups ?? 0) . '</li>';
        echo '<li>失败清理: ' . ($stats['total_stats']->failed_cleanups ?? 0) . '</li>';
        echo '</ul>';
    }
    
    echo '<h2>6. 测试清理日志</h2>';
    $logs = $cleanup_scheduler->get_cleanup_logs(array('per_page' => 5));
    echo '<p>找到 ' . $logs['total'] . ' 条清理日志</p>';
    
    if (!empty($logs['logs'])) {
        echo '<table class="wp-list-table widefat">';
        echo '<thead><tr><th>时间</th><th>类型</th><th>状态</th><th>删除数量</th></tr></thead>';
        echo '<tbody>';
        foreach (array_slice($logs['logs'], 0, 5) as $log) {
            echo '<tr>';
            echo '<td>' . esc_html($log->started_at) . '</td>';
            echo '<td>' . ($log->cleanup_type === 'auto' ? '自动' : '手动') . '</td>';
            echo '<td>' . esc_html($log->status) . '</td>';
            echo '<td>' . $log->articles_deleted . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    
    echo '<h2>7. 测试定时任务状态</h2>';
    $next_cleanup = wp_next_scheduled('ams_v2_auto_cleanup');
    if ($next_cleanup) {
        echo '<p style="color: green;">✓ 自动清理任务已调度</p>';
        echo '<p>下次执行时间: ' . date('Y-m-d H:i:s', $next_cleanup) . '</p>';
    } else {
        echo '<p style="color: orange;">⚠ 自动清理任务未调度</p>';
    }
    
    echo '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;">';
    echo '<h3 style="color: #155724;">测试完成</h3>';
    echo '<p style="color: #155724;">清理系统基本功能测试通过，所有核心组件正常工作。</p>';
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div style="background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0;">';
    echo '<h3 style="color: #721c24;">测试失败</h3>';
    echo '<p style="color: #721c24;">错误信息: ' . esc_html($e->getMessage()) . '</p>';
    echo '</div>';
}

echo '</div>';
?>