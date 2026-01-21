<?php
/**
 * 清理设置页面
 *
 * @package Article_Management_V2
 * @subpackage Admin/Partials
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 获取清理调度器
$cleanup_scheduler = $this->container->get('cleanup_scheduler');

// 处理表单提交
if (isset($_POST['submit_cleanup_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'ams_v2_cleanup_settings')) {
    $threshold_days = (int) $_POST['threshold_days'];
    $auto_cleanup_enabled = isset($_POST['auto_cleanup_enabled']);
    
    $cleanup_scheduler->set_threshold_days($threshold_days);
    
    if ($auto_cleanup_enabled) {
        $cleanup_scheduler->enable_auto_cleanup();
    } else {
        $cleanup_scheduler->disable_auto_cleanup();
    }
    
    echo '<div class="notice notice-success"><p>清理设置已保存</p></div>';
}

// 处理手动清理
if (isset($_POST['manual_cleanup']) && wp_verify_nonce($_POST['_wpnonce'], 'ams_v2_manual_cleanup')) {
    $auth_middleware = $this->container->get('auth_middleware');
    $current_user = $auth_middleware->get_current_user();
    
    if ($current_user && $current_user->is_administrator()) {
        $custom_threshold = !empty($_POST['custom_threshold']) ? (int) $_POST['custom_threshold'] : null;
        $result = $cleanup_scheduler->trigger_manual_cleanup($current_user, $custom_threshold);
        
        if ($result['success']) {
            echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
        }
    }
}

// 获取当前设置
$threshold_days = $cleanup_scheduler->get_threshold_days();
$auto_cleanup_enabled = $cleanup_scheduler->is_auto_cleanup_enabled();
$next_cleanup = wp_next_scheduled('ams_v2_auto_cleanup');

// 获取统计信息
$statistics = $cleanup_scheduler->get_cleanup_statistics(30);
?>

<div class="wrap">
    <h1><?php _e('清理设置', 'article-management-v2'); ?></h1>
    
    <div class="ams-v2-admin-container">
        <!-- 清理设置 -->
        <div class="ams-v2-card">
            <h2>自动清理配置</h2>
            <form method="post" action="">
                <?php wp_nonce_field('ams_v2_cleanup_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="threshold_days">清理阈值天数</label>
                        </th>
                        <td>
                            <input type="number" id="threshold_days" name="threshold_days" 
                                   value="<?php echo esc_attr($threshold_days); ?>" 
                                   min="1" max="365" class="regular-text" />
                            <p class="description">
                                超过此天数的草稿、已拒绝和已归档文章将被自动清理
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="auto_cleanup_enabled">启用自动清理</label>
                        </th>
                        <td>
                            <input type="checkbox" id="auto_cleanup_enabled" name="auto_cleanup_enabled" 
                                   value="1" <?php checked($auto_cleanup_enabled); ?> />
                            <label for="auto_cleanup_enabled">每天自动执行清理任务</label>
                            <?php if ($auto_cleanup_enabled && $next_cleanup): ?>
                                <p class="description">
                                    下次清理时间: <?php echo date('Y-m-d H:i:s', $next_cleanup); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('保存设置', 'primary', 'submit_cleanup_settings'); ?>
            </form>
        </div>
        <!-- 手动清理 -->
        <div class="ams-v2-card">
            <h2>手动清理</h2>
            <form method="post" action="" id="manual-cleanup-form">
                <?php wp_nonce_field('ams_v2_manual_cleanup'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="custom_threshold">自定义阈值天数</label>
                        </th>
                        <td>
                            <input type="number" id="custom_threshold" name="custom_threshold" 
                                   placeholder="<?php echo esc_attr($threshold_days); ?>" 
                                   min="1" max="365" class="regular-text" />
                            <p class="description">
                                留空使用默认阈值 (<?php echo $threshold_days; ?> 天)
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="manual_cleanup" class="button button-secondary" 
                            onclick="return confirm('确定要执行手动清理吗？此操作不可撤销。');">
                        立即执行清理
                    </button>
                </p>
            </form>
        </div>

        <!-- 清理统计 -->
        <div class="ams-v2-card">
            <h2>清理统计 (最近30天)</h2>
            
            <?php if ($statistics['total_stats']): ?>
                <div class="ams-v2-stats-grid">
                    <div class="ams-v2-stat-item">
                        <div class="ams-v2-stat-number"><?php echo $statistics['total_stats']->total_cleanups; ?></div>
                        <div class="ams-v2-stat-label">总清理次数</div>
                    </div>
                    <div class="ams-v2-stat-item">
                        <div class="ams-v2-stat-number"><?php echo $statistics['total_stats']->total_deleted; ?></div>
                        <div class="ams-v2-stat-label">删除文章数</div>
                    </div>
                    <div class="ams-v2-stat-item">
                        <div class="ams-v2-stat-number"><?php echo $statistics['total_stats']->successful_cleanups; ?></div>
                        <div class="ams-v2-stat-label">成功清理</div>
                    </div>
                    <div class="ams-v2-stat-item">
                        <div class="ams-v2-stat-number"><?php echo $statistics['total_stats']->failed_cleanups; ?></div>
                        <div class="ams-v2-stat-label">失败清理</div>
                    </div>
                </div>
            <?php else: ?>
                <p>暂无清理统计数据</p>
            <?php endif; ?>

            <?php if (!empty($statistics['type_stats'])): ?>
                <h3>按类型统计</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>清理类型</th>
                            <th>次数</th>
                            <th>删除文章数</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statistics['type_stats'] as $stat): ?>
                            <tr>
                                <td><?php echo $stat->cleanup_type === 'auto' ? '自动清理' : '手动清理'; ?></td>
                                <td><?php echo $stat->count; ?></td>
                                <td><?php echo $stat->deleted_count; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <!-- 清理日志 -->
        <div class="ams-v2-card">
            <h2>清理日志</h2>
            
            <?php
            // 获取清理日志
            $page = isset($_GET['log_page']) ? max(1, (int) $_GET['log_page']) : 1;
            $logs_data = $cleanup_scheduler->get_cleanup_logs(array(
                'page' => $page,
                'per_page' => 10
            ));
            ?>
            
            <?php if (!empty($logs_data['logs'])): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>类型</th>
                            <th>状态</th>
                            <th>删除数量</th>
                            <th>阈值天数</th>
                            <th>触发用户</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs_data['logs'] as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log->started_at); ?></td>
                                <td>
                                    <span class="ams-v2-badge ams-v2-badge-<?php echo $log->cleanup_type; ?>">
                                        <?php echo $log->cleanup_type === 'auto' ? '自动' : '手动'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="ams-v2-status ams-v2-status-<?php echo $log->status; ?>">
                                        <?php 
                                        $status_labels = array(
                                            'running' => '运行中',
                                            'completed' => '已完成',
                                            'failed' => '失败'
                                        );
                                        echo $status_labels[$log->status] ?? $log->status;
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo $log->articles_deleted; ?></td>
                                <td><?php echo $log->threshold_days; ?> 天</td>
                                <td>
                                    <?php if ($log->username): ?>
                                        <?php echo esc_html($log->display_name ?: $log->username); ?>
                                    <?php else: ?>
                                        系统
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small view-cleanup-details" 
                                            data-log-id="<?php echo $log->id; ?>">
                                        查看详情
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- 分页 -->
                <?php if ($logs_data['total_pages'] > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php
                            $base_url = admin_url('admin.php?page=' . $_GET['page']);
                            echo paginate_links(array(
                                'base' => $base_url . '%_%',
                                'format' => '&log_page=%#%',
                                'current' => $page,
                                'total' => $logs_data['total_pages'],
                                'prev_text' => '&laquo; 上一页',
                                'next_text' => '下一页 &raquo;'
                            ));
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p>暂无清理日志</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 清理详情模态框 -->
<div id="cleanup-details-modal" class="ams-v2-modal" style="display: none;">
    <div class="ams-v2-modal-content">
        <div class="ams-v2-modal-header">
            <h3>清理详情</h3>
            <span class="ams-v2-modal-close">&times;</span>
        </div>
        <div class="ams-v2-modal-body">
            <div id="cleanup-details-content">
                <p>加载中...</p>
            </div>
        </div>
    </div>
</div>

<style>
.ams-v2-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.ams-v2-stat-item {
    background: #f9f9f9;
    padding: 20px;
    text-align: center;
    border-radius: 4px;
}

.ams-v2-stat-number {
    font-size: 2em;
    font-weight: bold;
    color: #0073aa;
}

.ams-v2-stat-label {
    margin-top: 5px;
    color: #666;
}

.ams-v2-badge {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.ams-v2-badge-auto {
    background: #e1f5fe;
    color: #0277bd;
}

.ams-v2-badge-manual {
    background: #f3e5f5;
    color: #7b1fa2;
}

.ams-v2-status {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
}

.ams-v2-status-running {
    background: #fff3cd;
    color: #856404;
}

.ams-v2-status-completed {
    background: #d4edda;
    color: #155724;
}

.ams-v2-status-failed {
    background: #f8d7da;
    color: #721c24;
}
</style>

<script>
jQuery(document).ready(function($) {
    // 查看清理详情
    $('.view-cleanup-details').on('click', function() {
        var logId = $(this).data('log-id');
        
        $('#cleanup-details-content').html('<p>加载中...</p>');
        $('#cleanup-details-modal').show();
        
        $.post(ajaxurl, {
            action: 'ams_v2_get_cleanup_details',
            log_id: logId,
            nonce: ams_v2_admin.nonce
        }, function(response) {
            if (response.success) {
                $('#cleanup-details-content').html(response.data.html);
            } else {
                $('#cleanup-details-content').html('<p>加载失败: ' + response.data + '</p>');
            }
        });
    });
    
    // 关闭模态框
    $('.ams-v2-modal-close, .ams-v2-modal').on('click', function(e) {
        if (e.target === this) {
            $('#cleanup-details-modal').hide();
        }
    });
});
</script>