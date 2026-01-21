<?php
/**
 * 站点管理页面
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
$site_manager = $ams_v2_container->get('site_manager');
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
    if (!wp_verify_nonce($_POST['_wpnonce'], 'ams_v2_site_action')) {
        $message = '安全验证失败，请重试。';
        $message_type = 'error';
    } else {
        switch ($_POST['action']) {
            case 'create_site':
                $result = $site_manager->create_site($_POST);
                if (is_wp_error($result)) {
                    $message = '创建站点失败: ' . $result->get_error_message();
                    $message_type = 'error';
                } else {
                    $message = '站点创建成功！';
                    $message_type = 'success';
                }
                break;

            case 'update_site':
                $site = $site_manager->get_site((int) $_POST['site_id']);
                if ($site) {
                    $site->populate($_POST);
                    $result = $site_manager->update_site($site);
                    if (is_wp_error($result)) {
                        $message = '更新站点失败: ' . $result->get_error_message();
                        $message_type = 'error';
                    } else {
                        $message = '站点更新成功！';
                        $message_type = 'success';
                    }
                } else {
                    $message = '站点不存在。';
                    $message_type = 'error';
                }
                break;

            case 'delete_site':
                $result = $site_manager->delete_site((int) $_POST['site_id']);
                if (is_wp_error($result)) {
                    $message = '删除站点失败: ' . $result->get_error_message();
                    $message_type = 'error';
                } else {
                    $message = '站点删除成功！';
                    $message_type = 'success';
                }
                break;

            case 'test_connection':
                $site = $site_manager->get_site((int) $_POST['site_id']);
                if ($site) {
                    $result = $site_manager->test_site_connection($site);
                    if (is_wp_error($result)) {
                        $message = '连接测试失败: ' . $result->get_error_message();
                        $message_type = 'error';
                    } else {
                        $message = '连接测试成功！';
                        $message_type = 'success';
                        // 更新站点状态
                        $site_manager->update_site($site);
                    }
                } else {
                    $message = '站点不存在。';
                    $message_type = 'error';
                }
                break;
        }
    }
}

// 获取站点列表
$sites = $site_manager->get_sites();
?>

<div class="wrap">
    <h1>站点管理</h1>
    
    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="ams-v2-sites-container">
        <!-- 添加新站点按钮 -->
        <div class="tablenav top">
            <div class="alignleft actions">
                <button type="button" class="button button-primary" id="add-site-btn">
                    添加新站点
                </button>
            </div>
        </div>

        <!-- 站点列表表格 -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column">站点名称</th>
                    <th scope="col" class="manage-column">URL</th>
                    <th scope="col" class="manage-column">用户名</th>
                    <th scope="col" class="manage-column">连接状态</th>
                    <th scope="col" class="manage-column">最后同步</th>
                    <th scope="col" class="manage-column">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sites)): ?>
                    <tr>
                        <td colspan="6" class="no-items">暂无站点配置</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sites as $site): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($site->get_name()); ?></strong>
                                <?php if (!$site->is_active()): ?>
                                    <span class="dashicons dashicons-hidden" title="已禁用"></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($site->get_url()); ?>" target="_blank">
                                    <?php echo esc_html($site->get_url()); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($site->get_credentials() ? $site->get_credentials()->get_username() : ''); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($site->get_sync_status()); ?>">
                                    <?php echo esc_html($site->get_sync_status_label()); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $last_sync = $site->get_last_sync_at();
                                echo $last_sync ? esc_html($last_sync->format('Y-m-d H:i:s')) : '从未同步';
                                ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small test-connection-btn" 
                                        data-site-id="<?php echo esc_attr($site->get_id()); ?>">
                                    测试连接
                                </button>
                                <button type="button" class="button button-small edit-site-btn" 
                                        data-site-id="<?php echo esc_attr($site->get_id()); ?>">
                                    编辑
                                </button>
                                <button type="button" class="button button-small button-link-delete delete-site-btn" 
                                        data-site-id="<?php echo esc_attr($site->get_id()); ?>"
                                        data-site-name="<?php echo esc_attr($site->get_name()); ?>">
                                    删除
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 添加/编辑站点模态框 -->
<div id="site-modal" class="ams-v2-modal" style="display: none;">
    <div class="ams-v2-modal-content">
        <div class="ams-v2-modal-header">
            <h2 id="modal-title">添加站点</h2>
            <span class="ams-v2-modal-close">&times;</span>
        </div>
        <div class="ams-v2-modal-body">
            <form id="site-form" method="post">
                <?php wp_nonce_field('ams_v2_site_action'); ?>
                <input type="hidden" name="action" id="form-action" value="create_site">
                <input type="hidden" name="site_id" id="site-id" value="">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="site-name">站点名称 <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="site-name" name="name" class="regular-text" required>
                            <p class="description">站点的显示名称</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="site-url">站点URL <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="url" id="site-url" name="url" class="regular-text" required>
                            <p class="description">WordPress站点的完整URL，例如: https://example.com</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="site-username">用户名 <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" id="site-username" name="username" class="regular-text" required>
                            <p class="description">WordPress管理员用户名</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="site-password">密码 <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="password" id="site-password" name="password" class="regular-text" required>
                            <p class="description">WordPress用户密码或应用程序密码</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="default-category-id">默认分类ID</label>
                        </th>
                        <td>
                            <input type="number" id="default-category-id" name="default_category_id" 
                                   class="small-text" value="1" min="1">
                            <p class="description">发布文章时使用的默认分类ID</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="site-active">启用状态</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="site-active" name="is_active" value="1" checked>
                                启用此站点
                            </label>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <div class="ams-v2-modal-footer">
            <button type="button" class="button button-secondary" id="cancel-btn">取消</button>
            <button type="submit" form="site-form" class="button button-primary" id="save-btn">保存</button>
        </div>
    </div>
</div>

<style>
.ams-v2-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.ams-v2-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    border: 1px solid #888;
    width: 80%;
    max-width: 600px;
    border-radius: 4px;
}

.ams-v2-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    position: relative;
}

.ams-v2-modal-header h2 {
    margin: 0;
}

.ams-v2-modal-close {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.ams-v2-modal-body {
    padding: 20px;
}

.ams-v2-modal-footer {
    padding: 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}

.status-badge {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-connected {
    background-color: #d4edda;
    color: #155724;
}

.status-failed {
    background-color: #f8d7da;
    color: #721c24;
}

.status-unknown {
    background-color: #fff3cd;
    color: #856404;
}

.status-testing {
    background-color: #d1ecf1;
    color: #0c5460;
}

.required {
    color: #d63638;
}
</style>

<script>
jQuery(document).ready(function($) {
    // 模态框控制
    const modal = $('#site-modal');
    const form = $('#site-form');

    // 显示添加站点模态框
    $('#add-site-btn').click(function() {
        $('#modal-title').text('添加站点');
        $('#form-action').val('create_site');
        $('#site-id').val('');
        form[0].reset();
        $('#site-active').prop('checked', true);
        modal.show();
    });

    // 显示编辑站点模态框
    $('.edit-site-btn').click(function() {
        const siteId = $(this).data('site-id');
        $('#modal-title').text('编辑站点');
        $('#form-action').val('update_site');
        $('#site-id').val(siteId);
        
        // 这里应该通过AJAX获取站点数据并填充表单
        // 为了简化，暂时使用页面刷新方式
        modal.show();
    });

    // 关闭模态框
    $('.ams-v2-modal-close, #cancel-btn').click(function() {
        modal.hide();
    });

    // 点击模态框外部关闭
    $(window).click(function(event) {
        if (event.target === modal[0]) {
            modal.hide();
        }
    });

    // 测试连接
    $('.test-connection-btn').click(function() {
        const siteId = $(this).data('site-id');
        const btn = $(this);
        
        btn.prop('disabled', true).text('测试中...');
        
        // 创建隐藏表单提交测试请求
        const testForm = $('<form method="post" style="display:none;">')
            .append($('<input name="action" value="test_connection">'))
            .append($('<input name="site_id">').val(siteId))
            .append($('<?php wp_nonce_field("ams_v2_site_action"); ?>'));
        
        $('body').append(testForm);
        testForm.submit();
    });

    // 删除站点
    $('.delete-site-btn').click(function() {
        const siteId = $(this).data('site-id');
        const siteName = $(this).data('site-name');
        
        if (confirm('确定要删除站点 "' + siteName + '" 吗？此操作不可撤销。')) {
            const deleteForm = $('<form method="post" style="display:none;">')
                .append($('<input name="action" value="delete_site">'))
                .append($('<input name="site_id">').val(siteId))
                .append($('<?php wp_nonce_field("ams_v2_site_action"); ?>'));
            
            $('body').append(deleteForm);
            deleteForm.submit();
        }
    });
});
</script>