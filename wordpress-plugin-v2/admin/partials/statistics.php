<?php
/**
 * 统计报表页面
 *
 * @package Article_Management_V2
 * @subpackage Admin/Partials
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 获取统计收集器
$statistics_collector = $this->container->get('statistics_collector');

// 处理导出请求
if (isset($_GET['export']) && wp_verify_nonce($_GET['_wpnonce'], 'ams_v2_export_statistics')) {
    $export_type = sanitize_text_field($_GET['export']);
    $period = sanitize_text_field($_GET['period'] ?? 'daily');
    $date_from = sanitize_text_field($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
    $date_to = sanitize_text_field($_GET['date_to'] ?? date('Y-m-d'));
    
    switch ($export_type) {
        case 'user_stats':
            $data = $statistics_collector->collect_user_statistics(null, $period, $date_from, $date_to);
            $filename = "user_statistics_{$period}_{$date_from}_{$date_to}.csv";
            break;
        case 'system_stats':
            $data = $statistics_collector->collect_system_statistics($period, $date_from, $date_to);
            $filename = "system_statistics_{$period}_{$date_from}_{$date_to}.csv";
            break;
        case 'moderation_stats':
            $data = $statistics_collector->get_moderation_statistics($period, $date_from, $date_to);
            $filename = "moderation_statistics_{$period}_{$date_from}_{$date_to}.csv";
            break;
        default:
            wp_die('无效的导出类型');
    }
    
    if (!empty($data)) {
        $statistics_collector->export_statistics_csv($data, $filename);
        exit;
    } else {
        echo '<div class="notice notice-error"><p>没有数据可导出</p></div>';
    }
}

// 获取查询参数
$current_period = sanitize_text_field($_GET['period'] ?? 'daily');
$current_date_from = sanitize_text_field($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
$current_date_to = sanitize_text_field($_GET['date_to'] ?? date('Y-m-d'));
$current_user_id = !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null;

// 获取统计数据
$realtime_stats = $statistics_collector->get_realtime_statistics();
$user_stats = $statistics_collector->collect_user_statistics($current_user_id, $current_period, $current_date_from, $current_date_to);
$system_stats = $statistics_collector->collect_system_statistics($current_period, $current_date_from, $current_date_to);
$moderation_stats = $statistics_collector->get_moderation_statistics($current_period, $current_date_from, $current_date_to);

// 获取用户列表用于筛选
$auth_manager = $this->container->get('authentication_manager');
$users = $auth_manager->get_all_users(array('limit' => 100));
?>

<div class="wrap">
    <h1><?php _e('统计报表', 'article-management-v2'); ?></h1>
    
    <div class="ams-v2-admin-container">
        <!-- 实时统计 -->
        <div class="ams-v2-card">
            <h2>实时统计</h2>
            
            <?php if (!empty($realtime_stats)): ?>
                <div class="ams-v2-stats-grid">
                    <div class="ams-v2-stat-item">
                        <div class="ams-v2-stat-number"><?php echo $realtime_stats['today']['new_articles']; ?></div>
                        <div class="ams-v2-stat-label">今日新增文章</div>
                    </div>
                    <div class="ams-v2-stat-item">
                        <div class="ams-v2-stat-number"><?php echo $realtime_stats['today']['published_articles']; ?></div>
                        <div class="ams-v2-stat-label">今日发布文章</div>
                    </div>
                    <div class="ams-v2-stat-item">
                        <div class="ams-v2-stat-number"><?php echo $realtime_stats['today']['pending_articles']; ?></div>
                        <div class="ams-v2-stat-label">待审核文章</div>
                    </div>
                    <div class="ams-v2-stat-item">
                        <div class="ams-v2-stat-number"><?php echo $realtime_stats['today']['active_users']; ?></div>
                        <div class="ams-v2-stat-label">今日活跃用户</div>
                    </div>
                </div>
                
                <div class="ams-v2-stats-grid">
                    <div class="ams-v2-stat-item">
                        <div class="ams-v2-stat-number"><?php echo $realtime_stats['totals']['total_articles']; ?></div>
                        <div class="ams-v2-stat-label">总文章数</div>
                    </div>
                    <div class="ams-v2-stat-item">
                        <div class="ams-v2-stat-number"><?php echo $realtime_stats['totals']['total_users']; ?></div>
                        <div class="ams-v2-stat-label">总用户数</div>
                    </div>
                    <div class="ams-v2-stat-item">
                        <div class="ams-v2-stat-number"><?php echo $realtime_stats['totals']['active_sites']; ?></div>
                        <div class="ams-v2-stat-label">活跃站点</div>
                    </div>
                    <div class="ams-v2-stat-item">
                        <div class="ams-v2-stat-number"><?php echo $realtime_stats['totals']['sensitive_words']; ?></div>
                        <div class="ams-v2-stat-label">敏感词数</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <!-- 筛选条件 -->
        <div class="ams-v2-card">
            <h2>筛选条件</h2>
            
            <form method="get" action="" class="ams-v2-filter-form">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />
                
                <div class="ams-v2-filter-row">
                    <div class="ams-v2-filter-item">
                        <label for="period">统计周期:</label>
                        <select id="period" name="period">
                            <option value="daily" <?php selected($current_period, 'daily'); ?>>按日</option>
                            <option value="weekly" <?php selected($current_period, 'weekly'); ?>>按周</option>
                            <option value="monthly" <?php selected($current_period, 'monthly'); ?>>按月</option>
                            <option value="yearly" <?php selected($current_period, 'yearly'); ?>>按年</option>
                        </select>
                    </div>
                    
                    <div class="ams-v2-filter-item">
                        <label for="date_from">开始日期:</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($current_date_from); ?>" />
                    </div>
                    
                    <div class="ams-v2-filter-item">
                        <label for="date_to">结束日期:</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($current_date_to); ?>" />
                    </div>
                    
                    <div class="ams-v2-filter-item">
                        <label for="user_id">用户筛选:</label>
                        <select id="user_id" name="user_id">
                            <option value="">所有用户</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user->get_id(); ?>" <?php selected($current_user_id, $user->get_id()); ?>>
                                    <?php echo esc_html($user->get_display_name() ?: $user->get_username()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="ams-v2-filter-item">
                        <button type="submit" class="button button-primary">筛选</button>
                        <button type="button" class="button" onclick="location.href='<?php echo admin_url('admin.php?page=' . $_GET['page']); ?>'">重置</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- 用户统计 -->
        <div class="ams-v2-card">
            <div class="ams-v2-card-header">
                <h2>用户发布统计</h2>
                <div class="ams-v2-card-actions">
                    <a href="<?php echo wp_nonce_url(add_query_arg(array(
                        'export' => 'user_stats',
                        'period' => $current_period,
                        'date_from' => $current_date_from,
                        'date_to' => $current_date_to
                    )), 'ams_v2_export_statistics'); ?>" class="button">导出CSV</a>
                </div>
            </div>
            
            <?php if (!empty($user_stats)): ?>
                <div class="ams-v2-table-responsive">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>用户</th>
                                <th>日期</th>
                                <th>总提交</th>
                                <th>审核通过</th>
                                <th>审核拒绝</th>
                                <th>已发布</th>
                                <th>敏感词拦截</th>
                                <th>通过率</th>
                                <th>平均审核时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($user_stats, 0, 20) as $stat): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($stat['display_name'] ?: $stat['username']); ?></strong>
                                        <br><small><?php echo esc_html($stat['username']); ?></small>
                                    </td>
                                    <td><?php echo esc_html($stat['date']); ?></td>
                                    <td><?php echo $stat['total_submissions']; ?></td>
                                    <td><span class="ams-v2-badge ams-v2-badge-success"><?php echo $stat['approved_count']; ?></span></td>
                                    <td><span class="ams-v2-badge ams-v2-badge-error"><?php echo $stat['rejected_count']; ?></span></td>
                                    <td><span class="ams-v2-badge ams-v2-badge-info"><?php echo $stat['published_count']; ?></span></td>
                                    <td><span class="ams-v2-badge ams-v2-badge-warning"><?php echo $stat['blocked_count']; ?></span></td>
                                    <td><?php echo $stat['approval_rate']; ?>%</td>
                                    <td><?php echo round($stat['avg_review_time'], 1); ?>h</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (count($user_stats) > 20): ?>
                    <p><em>显示前20条记录，共 <?php echo count($user_stats); ?> 条</em></p>
                <?php endif; ?>
            <?php else: ?>
                <p>暂无用户统计数据</p>
            <?php endif; ?>
        </div>

        <!-- 系统统计 -->
        <div class="ams-v2-card">
            <div class="ams-v2-card-header">
                <h2>系统统计</h2>
                <div class="ams-v2-card-actions">
                    <a href="<?php echo wp_nonce_url(add_query_arg(array(
                        'export' => 'system_stats',
                        'period' => $current_period,
                        'date_from' => $current_date_from,
                        'date_to' => $current_date_to
                    )), 'ams_v2_export_statistics'); ?>" class="button">导出CSV</a>
                </div>
            </div>
            
            <?php if (!empty($system_stats)): ?>
                <div class="ams-v2-table-responsive">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>日期</th>
                                <th>活跃用户</th>
                                <th>新用户</th>
                                <th>新增文章</th>
                                <th>已发布</th>
                                <th>待审核</th>
                                <th>敏感词拦截</th>
                                <th>发布率</th>
                                <th>用户活跃度</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($system_stats, 0, 15) as $stat): ?>
                                <tr>
                                    <td><?php echo esc_html($stat['date']); ?></td>
                                    <td><?php echo $stat['active_users']; ?></td>
                                    <td><?php echo $stat['new_users']; ?></td>
                                    <td><?php echo $stat['new_articles']; ?></td>
                                    <td><span class="ams-v2-badge ams-v2-badge-success"><?php echo $stat['published_articles']; ?></span></td>
                                    <td><span class="ams-v2-badge ams-v2-badge-warning"><?php echo $stat['pending_articles']; ?></span></td>
                                    <td><span class="ams-v2-badge ams-v2-badge-error"><?php echo $stat['blocked_submissions']; ?></span></td>
                                    <td><?php echo $stat['publish_rate']; ?>%</td>
                                    <td><?php echo $stat['user_activity_rate']; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>暂无系统统计数据</p>
            <?php endif; ?>
        </div>
        <!-- 内容审核统计 -->
        <div class="ams-v2-card">
            <div class="ams-v2-card-header">
                <h2>内容审核统计</h2>
                <div class="ams-v2-card-actions">
                    <a href="<?php echo wp_nonce_url(add_query_arg(array(
                        'export' => 'moderation_stats',
                        'period' => $current_period,
                        'date_from' => $current_date_from,
                        'date_to' => $current_date_to
                    )), 'ams_v2_export_statistics'); ?>" class="button">导出CSV</a>
                </div>
            </div>
            
            <?php if (!empty($moderation_stats)): ?>
                <div class="ams-v2-table-responsive">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>日期</th>
                                <th>总审核数</th>
                                <th>通过数</th>
                                <th>拒绝数</th>
                                <th>拦截数</th>
                                <th>通过率</th>
                                <th>拒绝率</th>
                                <th>平均审核时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($moderation_stats, 0, 15) as $stat): ?>
                                <tr>
                                    <td><?php echo esc_html($stat['date']); ?></td>
                                    <td><?php echo $stat['total_moderated']; ?></td>
                                    <td><span class="ams-v2-badge ams-v2-badge-success"><?php echo $stat['approved_count']; ?></span></td>
                                    <td><span class="ams-v2-badge ams-v2-badge-error"><?php echo $stat['rejected_count']; ?></span></td>
                                    <td><span class="ams-v2-badge ams-v2-badge-warning"><?php echo $stat['blocked_count']; ?></span></td>
                                    <td><?php echo $stat['approval_rate']; ?>%</td>
                                    <td><?php echo $stat['rejection_rate']; ?>%</td>
                                    <td><?php echo $stat['avg_moderation_time']; ?>h</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>暂无审核统计数据</p>
            <?php endif; ?>
        </div>

        <!-- 图表展示区域 -->
        <div class="ams-v2-card">
            <h2>趋势图表</h2>
            
            <div class="ams-v2-chart-container">
                <canvas id="statisticsChart" width="400" height="200"></canvas>
            </div>
            
            <div class="ams-v2-chart-controls">
                <button type="button" class="button" onclick="updateChart('articles')">文章趋势</button>
                <button type="button" class="button" onclick="updateChart('users')">用户趋势</button>
                <button type="button" class="button" onclick="updateChart('moderation')">审核趋势</button>
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
    border: 1px solid #ddd;
}

.ams-v2-stat-number {
    font-size: 2em;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 5px;
}

.ams-v2-stat-label {
    color: #666;
    font-size: 14px;
}

.ams-v2-filter-form {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
}

.ams-v2-filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: end;
}

.ams-v2-filter-item {
    display: flex;
    flex-direction: column;
    min-width: 150px;
}

.ams-v2-filter-item label {
    font-weight: 600;
    margin-bottom: 5px;
}

.ams-v2-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.ams-v2-card-header h2 {
    margin: 0;
}

.ams-v2-table-responsive {
    overflow-x: auto;
}

.ams-v2-badge {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    color: white;
}

.ams-v2-badge-success {
    background: #28a745;
}

.ams-v2-badge-error {
    background: #dc3545;
}

.ams-v2-badge-warning {
    background: #ffc107;
    color: #212529;
}

.ams-v2-badge-info {
    background: #17a2b8;
}

.ams-v2-chart-container {
    margin: 20px 0;
    height: 400px;
}

.ams-v2-chart-controls {
    text-align: center;
    margin-top: 15px;
}

.ams-v2-chart-controls .button {
    margin: 0 5px;
}

@media (max-width: 768px) {
    .ams-v2-filter-row {
        flex-direction: column;
    }
    
    .ams-v2-filter-item {
        min-width: 100%;
    }
    
    .ams-v2-stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // 初始化图表
    var ctx = document.getElementById('statisticsChart');
    if (ctx) {
        var chart = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: []
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // 默认加载文章趋势
        updateChart('articles');
    }
    
    // 实时更新统计数据
    setInterval(function() {
        updateRealtimeStats();
    }, 60000); // 每分钟更新一次
});

function updateChart(type) {
    var data = <?php echo json_encode(array(
        'user_stats' => $user_stats,
        'system_stats' => $system_stats,
        'moderation_stats' => $moderation_stats
    )); ?>;
    
    var chartData = {
        labels: [],
        datasets: []
    };
    
    switch (type) {
        case 'articles':
            if (data.system_stats) {
                chartData.labels = data.system_stats.map(item => item.date);
                chartData.datasets = [
                    {
                        label: '新增文章',
                        data: data.system_stats.map(item => item.new_articles),
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)'
                    },
                    {
                        label: '已发布',
                        data: data.system_stats.map(item => item.published_articles),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)'
                    }
                ];
            }
            break;
            
        case 'users':
            if (data.system_stats) {
                chartData.labels = data.system_stats.map(item => item.date);
                chartData.datasets = [
                    {
                        label: '活跃用户',
                        data: data.system_stats.map(item => item.active_users),
                        borderColor: '#17a2b8',
                        backgroundColor: 'rgba(23, 162, 184, 0.1)'
                    },
                    {
                        label: '新用户',
                        data: data.system_stats.map(item => item.new_users),
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)'
                    }
                ];
            }
            break;
            
        case 'moderation':
            if (data.moderation_stats) {
                chartData.labels = data.moderation_stats.map(item => item.date);
                chartData.datasets = [
                    {
                        label: '审核通过',
                        data: data.moderation_stats.map(item => item.approved_count),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)'
                    },
                    {
                        label: '审核拒绝',
                        data: data.moderation_stats.map(item => item.rejected_count),
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)'
                    }
                ];
            }
            break;
    }
    
    // 更新图表
    if (window.statisticsChart) {
        window.statisticsChart.data = chartData;
        window.statisticsChart.update();
    }
}

function updateRealtimeStats() {
    jQuery.post(ajaxurl, {
        action: 'ams_v2_get_realtime_statistics',
        nonce: ams_v2_admin.nonce
    }, function(response) {
        if (response.success) {
            // 更新实时统计数据
            location.reload();
        }
    });
}
</script>

<!-- 加载Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>