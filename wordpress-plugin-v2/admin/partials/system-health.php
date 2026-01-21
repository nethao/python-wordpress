<?php
/**
 * 系统健康检查页面
 *
 * @package Article_Management_V2
 * @subpackage Admin
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 获取系统监控器
global $ams_v2_container;
$system_monitor = $ams_v2_container->get('system_monitor');

// 执行健康检查
$health_data = $system_monitor->health_check();
$system_stats = $system_monitor->get_system_stats();

// 记录健康检查日志
$system_monitor->log_health_check($health_data);
?>

<div class="wrap">
    <h1 class="ams-page-header">
        <?php _e('System Health', 'article-management-v2'); ?>
        <button type="button" class="button button-secondary" onclick="location.reload()">
            <?php _e('Refresh', 'article-management-v2'); ?>
        </button>
    </h1>

    <!-- 总体健康状态 -->
    <div class="ams-health-overview">
        <div class="ams-health-score">
            <h2><?php _e('Overall Health Score', 'article-management-v2'); ?></h2>
            <div class="ams-score-circle <?php echo esc_attr($health_data['status']); ?>">
                <span class="ams-score-number"><?php echo round($health_data['overall_score'], 1); ?>%</span>
            </div>
            <p class="ams-health-status">
                <?php
                $status_labels = [
                    'excellent' => __('Excellent', 'article-management-v2'),
                    'good' => __('Good', 'article-management-v2'),
                    'fair' => __('Fair', 'article-management-v2'),
                    'poor' => __('Poor', 'article-management-v2'),
                    'critical' => __('Critical', 'article-management-v2')
                ];
                echo esc_html($status_labels[$health_data['status']] ?? __('Unknown', 'article-management-v2'));
                ?>
            </p>
        </div>

        <!-- 系统统计 -->
        <div class="ams-system-stats">
            <h3><?php _e('System Statistics', 'article-management-v2'); ?></h3>
            <div class="ams-stats-grid">
                <div class="ams-stat-item">
                    <span class="ams-stat-number"><?php echo esc_html($system_stats['users']['total'] ?? 0); ?></span>
                    <span class="ams-stat-label"><?php _e('Total Users', 'article-management-v2'); ?></span>
                </div>
                <div class="ams-stat-item">
                    <span class="ams-stat-number"><?php echo esc_html($system_stats['articles']['total'] ?? 0); ?></span>
                    <span class="ams-stat-label"><?php _e('Total Articles', 'article-management-v2'); ?></span>
                </div>
                <div class="ams-stat-item">
                    <span class="ams-stat-number"><?php echo esc_html($system_stats['sites']['total'] ?? 0); ?></span>
                    <span class="ams-stat-label"><?php _e('Total Sites', 'article-management-v2'); ?></span>
                </div>
                <div class="ams-stat-item">
                    <span class="ams-stat-number"><?php echo esc_html($system_stats['audit_logs']['today'] ?? 0); ?></span>
                    <span class="ams-stat-label"><?php _e('Today\'s Logs', 'article-management-v2'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 详细健康检查结果 -->
    <div class="ams-health-details">
        <h2><?php _e('Detailed Health Checks', 'article-management-v2'); ?></h2>

        <!-- 数据库连接 -->
        <div class="ams-health-section">
            <h3>
                <span class="ams-status-icon <?php echo esc_attr($health_data['database']['status']); ?>"></span>
                <?php _e('Database Connection', 'article-management-v2'); ?>
            </h3>
            <div class="ams-health-content">
                <p><strong><?php _e('Status:', 'article-management-v2'); ?></strong> <?php echo esc_html($health_data['database']['message']); ?></p>
                <?php if (isset($health_data['database']['response_time'])): ?>
                    <p><strong><?php _e('Response Time:', 'article-management-v2'); ?></strong> <?php echo esc_html($health_data['database']['response_time']); ?></p>
                <?php endif; ?>
                <div class="ams-progress-bar">
                    <div class="ams-progress-fill" style="width: <?php echo esc_attr($health_data['database']['score']); ?>%"></div>
                </div>
            </div>
        </div>

        <!-- 服务可用性 -->
        <div class="ams-health-section">
            <h3>
                <span class="ams-status-icon <?php echo $health_data['services']['available_services'] == $health_data['services']['total_services'] ? 'healthy' : 'warning'; ?>"></span>
                <?php _e('Services Availability', 'article-management-v2'); ?>
            </h3>
            <div class="ams-health-content">
                <p><strong><?php _e('Available Services:', 'article-management-v2'); ?></strong> 
                   <?php echo esc_html($health_data['services']['available_services']); ?> / <?php echo esc_html($health_data['services']['total_services']); ?>
                </p>
                <div class="ams-services-list">
                    <?php foreach ($health_data['services']['services'] as $service_name => $service_data): ?>
                        <div class="ams-service-item">
                            <span class="ams-service-status <?php echo esc_attr($service_data['status']); ?>"></span>
                            <span class="ams-service-name"><?php echo esc_html(str_replace('_', ' ', ucwords($service_name, '_'))); ?></span>
                            <span class="ams-service-message"><?php echo esc_html($service_data['message']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="ams-progress-bar">
                    <div class="ams-progress-fill" style="width: <?php echo esc_attr($health_data['services']['average_score']); ?>%"></div>
                </div>
            </div>
        </div>

        <!-- 文件系统 -->
        <div class="ams-health-section">
            <h3>
                <span class="ams-status-icon <?php echo $health_data['filesystem']['average_score'] >= 80 ? 'healthy' : ($health_data['filesystem']['average_score'] >= 60 ? 'warning' : 'error'); ?>"></span>
                <?php _e('File System', 'article-management-v2'); ?>
            </h3>
            <div class="ams-health-content">
                <?php foreach ($health_data['filesystem']['checks'] as $check_name => $check_data): ?>
                    <div class="ams-check-item">
                        <span class="ams-check-status <?php echo esc_attr($check_data['status']); ?>"></span>
                        <span class="ams-check-name"><?php echo esc_html(str_replace('_', ' ', ucwords($check_name, '_'))); ?></span>
                        <span class="ams-check-message"><?php echo esc_html($check_data['message']); ?></span>
                        <?php if (isset($check_data['usage_percent'])): ?>
                            <span class="ams-check-detail">(<?php echo esc_html($check_data['usage_percent']); ?>% used)</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="ams-progress-bar">
                    <div class="ams-progress-fill" style="width: <?php echo esc_attr($health_data['filesystem']['average_score']); ?>%"></div>
                </div>
            </div>
        </div>

        <!-- 内存使用 -->
        <div class="ams-health-section">
            <h3>
                <span class="ams-status-icon <?php echo esc_attr($health_data['memory']['status']); ?>"></span>
                <?php _e('Memory Usage', 'article-management-v2'); ?>
            </h3>
            <div class="ams-health-content">
                <p><strong><?php _e('Status:', 'article-management-v2'); ?></strong> <?php echo esc_html($health_data['memory']['message']); ?></p>
                <p><strong><?php _e('Memory Limit:', 'article-management-v2'); ?></strong> <?php echo esc_html($health_data['memory']['memory_limit']); ?></p>
                <p><strong><?php _e('Current Usage:', 'article-management-v2'); ?></strong> <?php echo esc_html($health_data['memory']['current_usage']); ?> (<?php echo esc_html($health_data['memory']['usage_percent']); ?>%)</p>
                <p><strong><?php _e('Peak Usage:', 'article-management-v2'); ?></strong> <?php echo esc_html($health_data['memory']['peak_usage']); ?> (<?php echo esc_html($health_data['memory']['peak_percent']); ?>%)</p>
                <div class="ams-progress-bar">
                    <div class="ams-progress-fill" style="width: <?php echo esc_attr($health_data['memory']['usage_percent']); ?>%"></div>
                </div>
            </div>
        </div>

        <!-- 数据库性能 -->
        <div class="ams-health-section">
            <h3>
                <span class="ams-status-icon <?php echo $health_data['database_performance']['average_score'] >= 80 ? 'healthy' : ($health_data['database_performance']['average_score'] >= 60 ? 'warning' : 'error'); ?>"></span>
                <?php _e('Database Performance', 'article-management-v2'); ?>
            </h3>
            <div class="ams-health-content">
                <?php foreach ($health_data['database_performance']['checks'] as $check_name => $check_data): ?>
                    <div class="ams-check-item">
                        <span class="ams-check-status <?php echo esc_attr($check_data['status']); ?>"></span>
                        <span class="ams-check-name"><?php echo esc_html(str_replace('_', ' ', ucwords($check_name, '_'))); ?></span>
                        <span class="ams-check-message"><?php echo esc_html($check_data['message']); ?></span>
                        <?php if (isset($check_data['query_time'])): ?>
                            <span class="ams-check-detail">(<?php echo esc_html($check_data['query_time']); ?>)</span>
                        <?php endif; ?>
                        <?php if (isset($check_data['size'])): ?>
                            <span class="ams-check-detail">(<?php echo esc_html($check_data['size']); ?>)</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="ams-progress-bar">
                    <div class="ams-progress-fill" style="width: <?php echo esc_attr($health_data['database_performance']['average_score']); ?>%"></div>
                </div>
            </div>
        </div>

        <!-- 外部API -->
        <div class="ams-health-section">
            <h3>
                <span class="ams-status-icon <?php echo $health_data['external_apis']['average_score'] >= 80 ? 'healthy' : ($health_data['external_apis']['average_score'] >= 60 ? 'warning' : 'error'); ?>"></span>
                <?php _e('External APIs', 'article-management-v2'); ?>
            </h3>
            <div class="ams-health-content">
                <?php foreach ($health_data['external_apis']['checks'] as $api_name => $api_data): ?>
                    <div class="ams-check-item">
                        <span class="ams-check-status <?php echo esc_attr($api_data['status']); ?>"></span>
                        <span class="ams-check-name"><?php echo esc_html(str_replace('_', ' ', ucwords($api_name, '_'))); ?></span>
                        <span class="ams-check-message"><?php echo esc_html($api_data['message']); ?></span>
                        <?php if (isset($api_data['response_time'])): ?>
                            <span class="ams-check-detail">(<?php echo esc_html($api_data['response_time']); ?>)</span>
                        <?php endif; ?>
                        <?php if (isset($api_data['connected']) && isset($api_data['total'])): ?>
                            <span class="ams-check-detail">(<?php echo esc_html($api_data['connected']); ?>/<?php echo esc_html($api_data['total']); ?>)</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="ams-progress-bar">
                    <div class="ams-progress-fill" style="width: <?php echo esc_attr($health_data['external_apis']['average_score']); ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 建议和操作 -->
    <div class="ams-health-recommendations">
        <h2><?php _e('Recommendations', 'article-management-v2'); ?></h2>
        
        <?php if ($health_data['overall_score'] >= 90): ?>
            <div class="ams-recommendation excellent">
                <h4><?php _e('System Status: Excellent', 'article-management-v2'); ?></h4>
                <p><?php _e('Your system is running optimally. All components are functioning well.', 'article-management-v2'); ?></p>
            </div>
        <?php elseif ($health_data['overall_score'] >= 80): ?>
            <div class="ams-recommendation good">
                <h4><?php _e('System Status: Good', 'article-management-v2'); ?></h4>
                <p><?php _e('Your system is running well with minor issues that should be addressed.', 'article-management-v2'); ?></p>
            </div>
        <?php elseif ($health_data['overall_score'] >= 70): ?>
            <div class="ams-recommendation fair">
                <h4><?php _e('System Status: Fair', 'article-management-v2'); ?></h4>
                <p><?php _e('Your system has some issues that may affect performance. Please review the failed checks.', 'article-management-v2'); ?></p>
            </div>
        <?php else: ?>
            <div class="ams-recommendation poor">
                <h4><?php _e('System Status: Needs Attention', 'article-management-v2'); ?></h4>
                <p><?php _e('Your system has significant issues that need immediate attention. Please address the failed checks as soon as possible.', 'article-management-v2'); ?></p>
            </div>
        <?php endif; ?>

        <!-- 具体建议 -->
        <div class="ams-specific-recommendations">
            <?php if (isset($health_data['memory']) && $health_data['memory']['usage_percent'] > 80): ?>
                <div class="ams-recommendation-item warning">
                    <strong><?php _e('High Memory Usage', 'article-management-v2'); ?></strong>
                    <p><?php _e('Consider increasing the PHP memory limit or optimizing your code to reduce memory consumption.', 'article-management-v2'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($health_data['database_performance']['checks']['query_performance']) && $health_data['database_performance']['checks']['query_performance']['score'] < 70): ?>
                <div class="ams-recommendation-item warning">
                    <strong><?php _e('Slow Database Queries', 'article-management-v2'); ?></strong>
                    <p><?php _e('Database queries are running slowly. Consider optimizing your database or adding indexes.', 'article-management-v2'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($health_data['external_apis']['checks']['deepseek_api']) && $health_data['external_apis']['checks']['deepseek_api']['status'] !== 'healthy'): ?>
                <div class="ams-recommendation-item error">
                    <strong><?php _e('DeepSeek API Issues', 'article-management-v2'); ?></strong>
                    <p><?php _e('The DeepSeek API is not responding properly. Please check your API configuration and network connectivity.', 'article-management-v2'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* 系统健康检查样式 */
.ams-health-overview {
    display: flex;
    gap: 30px;
    margin-bottom: 30px;
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.ams-health-score {
    text-align: center;
    min-width: 200px;
}

.ams-score-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 20px auto;
    border: 8px solid;
    position: relative;
}

.ams-score-circle.excellent { border-color: #46b450; }
.ams-score-circle.good { border-color: #00a0d2; }
.ams-score-circle.fair { border-color: #ffb900; }
.ams-score-circle.poor { border-color: #ff8c00; }
.ams-score-circle.critical { border-color: #dc3232; }

.ams-score-number {
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.ams-health-status {
    font-size: 18px;
    font-weight: 500;
    margin: 10px 0;
}

.ams-system-stats {
    flex: 1;
}

.ams-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.ams-stat-item {
    text-align: center;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

.ams-stat-number {
    display: block;
    font-size: 28px;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 5px;
}

.ams-stat-label {
    font-size: 14px;
    color: #666;
}

.ams-health-details {
    margin-bottom: 30px;
}

.ams-health-section {
    background: #fff;
    margin-bottom: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.ams-health-section h3 {
    background: #f8f9fa;
    margin: 0;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    align-items: center;
    gap: 10px;
}

.ams-health-content {
    padding: 20px;
}

.ams-status-icon {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: inline-block;
}

.ams-status-icon.healthy { background-color: #46b450; }
.ams-status-icon.warning { background-color: #ffb900; }
.ams-status-icon.error { background-color: #dc3232; }

.ams-progress-bar {
    width: 100%;
    height: 8px;
    background: #e0e0e0;
    border-radius: 4px;
    margin-top: 15px;
    overflow: hidden;
}

.ams-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #dc3232 0%, #ffb900 50%, #46b450 100%);
    transition: width 0.3s ease;
}

.ams-services-list,
.ams-checks-list {
    margin-top: 15px;
}

.ams-service-item,
.ams-check-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.ams-service-item:last-child,
.ams-check-item:last-child {
    border-bottom: none;
}

.ams-service-status,
.ams-check-status {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}

.ams-service-status.available,
.ams-check-status.healthy { background-color: #46b450; }
.ams-service-status.unavailable,
.ams-check-status.warning { background-color: #ffb900; }
.ams-service-status.error,
.ams-check-status.error { background-color: #dc3232; }

.ams-service-name,
.ams-check-name {
    font-weight: 500;
    min-width: 150px;
}

.ams-service-message,
.ams-check-message {
    color: #666;
    flex: 1;
}

.ams-check-detail {
    color: #999;
    font-size: 12px;
}

.ams-health-recommendations {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.ams-recommendation {
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.ams-recommendation.excellent { background: #d4edda; border-left: 4px solid #46b450; }
.ams-recommendation.good { background: #cce7f0; border-left: 4px solid #00a0d2; }
.ams-recommendation.fair { background: #fff3cd; border-left: 4px solid #ffb900; }
.ams-recommendation.poor { background: #f8d7da; border-left: 4px solid #dc3232; }

.ams-recommendation h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.ams-specific-recommendations {
    margin-top: 20px;
}

.ams-recommendation-item {
    padding: 12px;
    border-radius: 4px;
    margin-bottom: 10px;
}

.ams-recommendation-item.warning {
    background: #fff3cd;
    border-left: 4px solid #ffb900;
}

.ams-recommendation-item.error {
    background: #f8d7da;
    border-left: 4px solid #dc3232;
}

.ams-recommendation-item strong {
    display: block;
    margin-bottom: 5px;
    color: #333;
}

.ams-recommendation-item p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

/* 响应式设计 */
@media (max-width: 768px) {
    .ams-health-overview {
        flex-direction: column;
        gap: 20px;
    }
    
    .ams-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .ams-service-item,
    .ams-check-item {
        flex-wrap: wrap;
    }
    
    .ams-service-name,
    .ams-check-name {
        min-width: auto;
        width: 100%;
    }
}
</style>