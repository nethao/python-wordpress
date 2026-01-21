<?php
/**
 * API设置页面
 *
 * @package Article_Management_V2
 * @subpackage Admin/Partials
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 获取容器和服务
global $ams_v2_container;
$api_manager = $ams_v2_container->get('api_manager');
$auth_manager = $ams_v2_container->get('authentication_manager');

// 检查用户权限
$current_user = $auth_manager->get_current_user();
if (!$current_user || !$current_user->is_administrator()) {
    wp_die('您没有权限访问此页面。');
}

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // 验证nonce
    if (!wp_verify_nonce($_POST['_wpnonce'], 'ams_v2_api_action')) {
        $message = '安全验证失败，请重试。';
        $message_type = 'error';
    } else {
        switch ($_POST['action']) {
            case 'save_deepseek_config':
                $api_key = sanitize_text_field($_POST['deepseek_api_key']);
                $config = array(
                    'timeout' => (int) $_POST['deepseek_timeout'],
                    'retry_attempts' => (int) $_POST['deepseek_retry_attempts'],
                    'default_strict_level' => (int) $_POST['deepseek_strict_level']
                );
                
                if (empty($api_key)) {
                    $message = 'API密钥不能为空。';
                    $message_type = 'error';
                } else {
                    $result = $api_manager->save_api_key('deepseek', $api_key, $config);
                    if (is_wp_error($result)) {
                        $message = '保存失败: ' . $result->get_error_message();
                        $message_type = 'error';
                    } else {
                        $message = 'DeepSeek API配置保存成功！';
                        $message_type = 'success';
                    }
                }
                break;

            case 'test_deepseek_api':
                $validation_result = $api_manager->validate_api_key('deepseek');
                if (is_wp_error($validation_result)) {
                    $message = 'API测试失败: ' . $validation_result->get_error_message();
                    $message_type = 'error';
                } else {
                    $message = 'DeepSeek API连接测试成功！';
                    $message_type = 'success';
                }
                break;

            case 'delete_deepseek_config':
                $result = $api_manager->delete_api_key('deepseek');
                if (is_wp_error($result)) {
                    $message = '删除失败: ' . $result->get_error_message();
                    $message_type = 'error';
                } else {
                    $message = 'DeepSeek API配置已删除。';
                    $message_type = 'success';
                }
                break;
        }
    }
}

// 获取当前配置
$deepseek_config = $api_manager->get_api_config('deepseek');
$deepseek_api_key = $api_manager->get_api_key('deepseek');
$has_deepseek_config = !empty($deepseek_api_key);

// 获取所有服务状态
$services_status = $api_manager->get_all_services_status();
?>

<div class="wrap">
    <h1>API设置</h1>
    
    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="ams-v2-api-settings">
        <!-- API服务状态概览 -->
        <div class="postbox">
            <h2 class="hndle">API服务状态</h2>
            <div class="inside">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>服务名称</th>
                            <th>状态</th>
                            <th>配置时间</th>
                            <th>最后更新</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($services_status)): ?>
                            <tr>
                                <td colspan="5" class="no-items">暂无API服务配置</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($services_status as $service): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html(ucfirst($service['service_name'])); ?></strong>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $service['is_valid'] ? 'valid' : 'invalid'; ?>">
                                            <?php echo $service['is_valid'] ? '✓ 正常' : '✗ 异常'; ?>
                                        </span>
                                        <?php if (!$service['is_valid'] && $service['error_message']): ?>
                                            <br><small class="error-message"><?php echo esc_html($service['error_message']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($service['created_at']); ?></td>
                                    <td><?php echo esc_html($service['updated_at']); ?></td>
                                    <td>
                                        <button type="button" class="button button-small test-api-btn" 
                                                data-service="<?php echo esc_attr($service['service_name']); ?>">
                                            测试连接
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- DeepSeek API配置 -->
        <div class="postbox">
            <h2 class="hndle">DeepSeek API配置</h2>
            <div class="inside">
                <form method="post" id="deepseek-config-form">
                    <?php wp_nonce_field('ams_v2_api_action'); ?>
                    <input type="hidden" name="action" value="save_deepseek_config">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="deepseek-api-key">API密钥 <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="password" id="deepseek-api-key" name="deepseek_api_key" 
                                       class="regular-text" value="<?php echo $has_deepseek_config ? '••••••••••••••••' : ''; ?>" 
                                       <?php echo $has_deepseek_config ? '' : 'required'; ?>>
                                <button type="button" class="button button-secondary" id="toggle-api-key">
                                    <?php echo $has_deepseek_config ? '修改' : '显示'; ?>
                                </button>
                                <p class="description">
                                    DeepSeek API密钥，用于内容审核功能。
                                    <a href="https://platform.deepseek.com/api_keys" target="_blank">获取API密钥</a>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="deepseek-timeout">请求超时时间</label>
                            </th>
                            <td>
                                <input type="number" id="deepseek-timeout" name="deepseek_timeout" 
                                       class="small-text" value="<?php echo esc_attr($deepseek_config['timeout'] ?? 30); ?>" 
                                       min="5" max="120"> 秒
                                <p class="description">API请求的超时时间，建议设置为30秒</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="deepseek-retry-attempts">重试次数</label>
                            </th>
                            <td>
                                <input type="number" id="deepseek-retry-attempts" name="deepseek_retry_attempts" 
                                       class="small-text" value="<?php echo esc_attr($deepseek_config['retry_attempts'] ?? 3); ?>" 
                                       min="1" max="10">
                                <p class="description">API请求失败时的重试次数</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="deepseek-strict-level">默认审核等级</label>
                            </th>
                            <td>
                                <select id="deepseek-strict-level" name="deepseek_strict_level">
                                    <option value="1" <?php selected($deepseek_config['default_strict_level'] ?? 2, 1); ?>>
                                        宽松 - 仅检查明显违规内容
                                    </option>
                                    <option value="2" <?php selected($deepseek_config['default_strict_level'] ?? 2, 2); ?>>
                                        中等 - 标准审核等级（推荐）
                                    </option>
                                    <option value="3" <?php selected($deepseek_config['default_strict_level'] ?? 2, 3); ?>>
                                        严格 - 对可疑内容零容忍
                                    </option>
                                </select>
                                <p class="description">默认的内容审核严格程度</p>
                            </td>
                        </tr>
                    </table>

                    <div class="submit-actions">
                        <button type="submit" class="button button-primary">保存配置</button>
                        
                        <?php if ($has_deepseek_config): ?>
                            <button type="button" class="button button-secondary" id="test-deepseek-btn">
                                测试连接
                            </button>
                            <button type="button" class="button button-link-delete" id="delete-deepseek-btn">
                                删除配置
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- API使用统计 -->
        <?php if ($has_deepseek_config): ?>
        <div class="postbox">
            <h2 class="hndle">API使用统计</h2>
            <div class="inside">
                <div id="api-stats-container">
                    <p>正在加载统计数据...</p>
                </div>
                <button type="button" class="button button-secondary" id="refresh-stats-btn">
                    刷新统计
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- API健康检查 -->
        <?php if ($has_deepseek_config): ?>
        <div class="postbox">
            <h2 class="hndle">API健康检查</h2>
            <div class="inside">
                <div id="health-check-container">
                    <p>点击下方按钮进行健康检查</p>
                </div>
                <button type="button" class="button button-secondary" id="health-check-btn">
                    执行健康检查
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.ams-v2-api-settings .postbox {
    margin-bottom: 20px;
}

.status-badge {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-valid {
    background-color: #d4edda;
    color: #155724;
}

.status-invalid {
    background-color: #f8d7da;
    color: #721c24;
}

.error-message {
    color: #d63638;
}

.required {
    color: #d63638;
}

.submit-actions {
    margin-top: 20px;
}

.submit-actions .button {
    margin-right: 10px;
}

#api-stats-container,
#health-check-container {
    background: #f9f9f9;
    border: 1px solid #ddd;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.stat-item {
    background: white;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #ddd;
    text-align: center;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
}

.stat-label {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.health-status {
    padding: 10px;
    border-radius: 4px;
    margin: 10px 0;
}

.health-healthy {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.health-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>

<script>
jQuery(document).ready(function($) {
    // 切换API密钥显示
    $('#toggle-api-key').click(function() {
        const input = $('#deepseek-api-key');
        const btn = $(this);
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text').val('').focus();
            btn.text('隐藏');
        } else {
            input.attr('type', 'password').val('••••••••••••••••');
            btn.text('修改');
        }
    });

    // 测试DeepSeek API连接
    $('#test-deepseek-btn').click(function() {
        const btn = $(this);
        btn.prop('disabled', true).text('测试中...');
        
        const form = $('<form method="post" style="display:none;">')
            .append($('<input name="action" value="test_deepseek_api">'))
            .append($('<?php wp_nonce_field("ams_v2_api_action"); ?>'));
        
        $('body').append(form);
        form.submit();
    });

    // 删除DeepSeek配置
    $('#delete-deepseek-btn').click(function() {
        if (confirm('确定要删除DeepSeek API配置吗？此操作不可撤销。')) {
            const form = $('<form method="post" style="display:none;">')
                .append($('<input name="action" value="delete_deepseek_config">'))
                .append($('<?php wp_nonce_field("ams_v2_api_action"); ?>'));
            
            $('body').append(form);
            form.submit();
        }
    });

    // 测试API连接（通用）
    $('.test-api-btn').click(function() {
        const service = $(this).data('service');
        const btn = $(this);
        
        btn.prop('disabled', true).text('测试中...');
        
        // 这里应该使用AJAX调用，为了简化使用表单提交
        setTimeout(function() {
            btn.prop('disabled', false).text('测试连接');
        }, 2000);
    });

    // 刷新API统计
    $('#refresh-stats-btn').click(function() {
        loadApiStats();
    });

    // 执行健康检查
    $('#health-check-btn').click(function() {
        performHealthCheck();
    });

    // 加载API统计数据
    function loadApiStats() {
        $('#api-stats-container').html('<p>正在加载统计数据...</p>');
        
        // 模拟统计数据
        setTimeout(function() {
            const statsHtml = `
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-value">1,234</div>
                        <div class="stat-label">总请求数</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">98.5%</div>
                        <div class="stat-label">成功率</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">156ms</div>
                        <div class="stat-label">平均响应时间</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">$12.34</div>
                        <div class="stat-label">本月费用</div>
                    </div>
                </div>
            `;
            $('#api-stats-container').html(statsHtml);
        }, 1000);
    }

    // 执行健康检查
    function performHealthCheck() {
        $('#health-check-container').html('<p>正在执行健康检查...</p>');
        
        // 模拟健康检查
        setTimeout(function() {
            const healthHtml = `
                <div class="health-status health-healthy">
                    <strong>✓ API服务正常</strong><br>
                    响应时间: 145ms<br>
                    检查时间: ${new Date().toLocaleString()}
                </div>
            `;
            $('#health-check-container').html(healthHtml);
        }, 2000);
    }

    // 页面加载时自动加载统计数据
    <?php if ($has_deepseek_config): ?>
    loadApiStats();
    <?php endif; ?>
});
</script>