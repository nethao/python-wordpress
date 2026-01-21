<?php
/**
 * 审计日志管理页面
 *
 * @package Article_Management_V2
 * @subpackage Admin/Partials
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 获取容器实例
global $ams_v2_container;
$audit_logger = $ams_v2_container->get('audit_logger');
$auth_manager = $ams_v2_container->get('authentication_manager');

// 获取所有用户用于过滤
$all_users = $auth_manager->get_all_users();

// 获取可用的操作类型
$available_actions = array(
    'user_created' => '用户创建',
    'user_updated' => '用户更新',
    'user_deleted' => '用户删除',
    'user_login' => '用户登录',
    'user_logout' => '用户登出',
    'role_assigned' => '角色分配',
    'role_removed' => '角色移除',
    'article_created' => '文章创建',
    'article_updated' => '文章更新',
    'article_deleted' => '文章删除',
    'article_published' => '文章发布',
    'article_moderated' => '文章审核',
    'site_created' => '站点创建',
    'site_updated' => '站点更新',
    'site_deleted' => '站点删除',
    'sensitive_word_added' => '敏感词添加',
    'sensitive_word_updated' => '敏感词更新',
    'sensitive_word_deleted' => '敏感词删除',
    'moderation_level_changed' => '审核等级修改',
    'cleanup_executed' => '清理执行',
    'api_key_created' => 'API密钥创建',
    'api_key_updated' => 'API密钥更新',
    'api_key_deleted' => 'API密钥删除',
    'system_error' => '系统错误'
);

// 获取可用的资源类型
$available_resource_types = array(
    'user' => '用户',
    'role' => '角色',
    'article' => '文章',
    'site' => '站点',
    'sensitive_word' => '敏感词',
    'moderation_config' => '审核配置',
    'cleanup_config' => '清理配置',
    'api_key' => 'API密钥',
    'system' => '系统'
);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="audit-logs-container">
        <!-- 过滤器 -->
        <div class="audit-filters" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3>过滤条件</h3>
            <form id="audit-filter-form" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row">用户</th>
                        <td>
                            <select name="filter_user_id" id="filter_user_id">
                                <option value="">所有用户</option>
                                <?php foreach ($all_users as $user): ?>
                                    <option value="<?php echo $user->get_id(); ?>" <?php selected(isset($_GET['filter_user_id']) ? $_GET['filter_user_id'] : '', $user->get_id()); ?>>
                                        <?php echo esc_html($user->get_display_name() . ' (' . $user->get_username() . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">操作类型</th>
                        <td>
                            <select name="filter_action" id="filter_action">
                                <option value="">所有操作</option>
                                <?php foreach ($available_actions as $action => $label): ?>
                                    <option value="<?php echo esc_attr($action); ?>" <?php selected(isset($_GET['filter_action']) ? $_GET['filter_action'] : '', $action); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">资源类型</th>
                        <td>
                            <select name="filter_resource_type" id="filter_resource_type">
                                <option value="">所有资源</option>
                                <?php foreach ($available_resource_types as $type => $label): ?>
                                    <option value="<?php echo esc_attr($type); ?>" <?php selected(isset($_GET['filter_resource_type']) ? $_GET['filter_resource_type'] : '', $type); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">时间范围</th>
                        <td>
                            <input type="date" name="filter_date_from" id="filter_date_from" value="<?php echo esc_attr(isset($_GET['filter_date_from']) ? $_GET['filter_date_from'] : ''); ?>" />
                            至
                            <input type="date" name="filter_date_to" id="filter_date_to" value="<?php echo esc_attr(isset($_GET['filter_date_to']) ? $_GET['filter_date_to'] : ''); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">搜索</th>
                        <td>
                            <input type="text" name="filter_search" id="filter_search" value="<?php echo esc_attr(isset($_GET['filter_search']) ? $_GET['filter_search'] : ''); ?>" placeholder="搜索用户名、IP地址或操作内容..." class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" id="apply-filters" class="button button-primary">应用过滤</button>
                    <button type="button" id="clear-filters" class="button">清除过滤</button>
                    <button type="button" id="export-logs" class="button">导出日志</button>
                </p>
            </form>
        </div>

        <!-- 日志列表 -->
        <div class="audit-logs-list" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="per-page-select">
                        <option value="20">每页20条</option>
                        <option value="50">每页50条</option>
                        <option value="100">每页100条</option>
                    </select>
                </div>
                <div class="tablenav-pages">
                    <span class="displaying-num" id="displaying-num">正在加载...</span>
                    <span class="pagination-links" id="pagination-links"></span>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped" id="audit-logs-table">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-id">ID</th>
                        <th scope="col" class="manage-column column-user">用户</th>
                        <th scope="col" class="manage-column column-action">操作</th>
                        <th scope="col" class="manage-column column-resource">资源</th>
                        <th scope="col" class="manage-column column-ip">IP地址</th>
                        <th scope="col" class="manage-column column-time">时间</th>
                        <th scope="col" class="manage-column column-actions">操作</th>
                    </tr>
                </thead>
                <tbody id="audit-logs-tbody">
                    <tr>
                        <td colspan="7" class="loading-message">正在加载审计日志...</td>
                    </tr>
                </tbody>
            </table>

            <div class="tablenav bottom">
                <div class="tablenav-pages" id="pagination-links-bottom"></div>
            </div>
        </div>
    </div>
</div>

<!-- 日志详情模态框 -->
<div id="audit-log-details-modal" class="audit-modal" style="display: none;">
    <div class="audit-modal-content">
        <div class="audit-modal-header">
            <h3>审计日志详情</h3>
            <span class="audit-modal-close">&times;</span>
        </div>
        <div class="audit-modal-body" id="audit-log-details-content">
            正在加载...
        </div>
    </div>
</div>

<style>
.audit-logs-container {
    max-width: 100%;
}

.audit-filters .form-table th {
    width: 120px;
    padding: 10px 0;
}

.audit-filters .form-table td {
    padding: 10px 0;
}

.audit-filters select,
.audit-filters input[type="text"],
.audit-filters input[type="date"] {
    min-width: 200px;
}

#audit-logs-table {
    margin-top: 10px;
}

#audit-logs-table .column-id {
    width: 60px;
}

#audit-logs-table .column-user {
    width: 120px;
}

#audit-logs-table .column-action {
    width: 120px;
}

#audit-logs-table .column-resource {
    width: 120px;
}

#audit-logs-table .column-ip {
    width: 120px;
}

#audit-logs-table .column-time {
    width: 150px;
}

#audit-logs-table .column-actions {
    width: 80px;
}

.loading-message {
    text-align: center;
    padding: 20px;
    color: #666;
}

.error-message {
    text-align: center;
    padding: 20px;
    color: #d63638;
}

.audit-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.audit-modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #ccd0d4;
    width: 80%;
    max-width: 800px;
    max-height: 80%;
    overflow-y: auto;
    box-shadow: 0 3px 6px rgba(0,0,0,0.3);
}

.audit-modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    background: #f9f9f9;
    position: relative;
}

.audit-modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.audit-modal-close {
    position: absolute;
    right: 15px;
    top: 15px;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    color: #666;
}

.audit-modal-close:hover {
    color: #000;
}

.audit-modal-body {
    padding: 20px;
}

.audit-log-details .form-table th {
    width: 150px;
    font-weight: bold;
}

.audit-values {
    background: #f6f7f7;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 3px;
    font-family: Consolas, Monaco, monospace;
    font-size: 12px;
    max-height: 200px;
    overflow-y: auto;
}

.user-agent {
    background: #f6f7f7;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 3px;
    font-family: Consolas, Monaco, monospace;
    font-size: 12px;
    word-break: break-all;
}

.pagination-links a,
.pagination-links span {
    display: inline-block;
    padding: 3px 5px;
    margin: 0 2px;
    text-decoration: none;
    border: 1px solid #ddd;
    background: #f7f7f7;
}

.pagination-links a:hover {
    background: #0073aa;
    color: #fff;
}

.pagination-links .current {
    background: #0073aa;
    color: #fff;
}
</style>

<script>
jQuery(document).ready(function($) {
    let currentPage = 1;
    let perPage = 20;
    let currentFilters = {};

    // 加载审计日志
    function loadAuditLogs(page = 1, filters = {}) {
        currentPage = page;
        currentFilters = filters;

        const data = {
            action: 'ams_v2_get_audit_logs',
            nonce: ams_v2_admin.nonce,
            page: page,
            per_page: perPage,
            ...filters
        };

        $('#audit-logs-tbody').html('<tr><td colspan="7" class="loading-message">正在加载审计日志...</td></tr>');

        $.post(ams_v2_admin.ajax_url, data, function(response) {
            if (response.success) {
                displayAuditLogs(response.data);
            } else {
                $('#audit-logs-tbody').html('<tr><td colspan="7" class="error-message">加载失败: ' + response.data + '</td></tr>');
            }
        }).fail(function() {
            $('#audit-logs-tbody').html('<tr><td colspan="7" class="error-message">网络错误，请重试</td></tr>');
        });
    }

    // 显示审计日志
    function displayAuditLogs(data) {
        let html = '';
        
        if (data.logs && data.logs.length > 0) {
            data.logs.forEach(function(log) {
                html += '<tr>';
                html += '<td>' + log.id + '</td>';
                html += '<td>' + (log.username || 'N/A') + '</td>';
                html += '<td>' + escapeHtml(log.action) + '</td>';
                html += '<td>' + escapeHtml(log.resource_type) + (log.resource_id ? ' #' + log.resource_id : '') + '</td>';
                html += '<td>' + escapeHtml(log.ip_address || 'N/A') + '</td>';
                html += '<td>' + escapeHtml(log.created_at) + '</td>';
                html += '<td><button type="button" class="button button-small view-details" data-log-id="' + log.id + '">查看详情</button></td>';
                html += '</tr>';
            });
        } else {
            html = '<tr><td colspan="7" class="loading-message">没有找到审计日志</td></tr>';
        }

        $('#audit-logs-tbody').html(html);

        // 更新分页信息
        updatePagination(data);
    }

    // 更新分页
    function updatePagination(data) {
        const total = data.total || 0;
        const totalPages = data.total_pages || 1;
        
        $('#displaying-num').text('共 ' + total + ' 条记录');

        let paginationHtml = '';
        
        if (totalPages > 1) {
            // 上一页
            if (currentPage > 1) {
                paginationHtml += '<a href="#" class="pagination-link" data-page="' + (currentPage - 1) + '">&laquo; 上一页</a>';
            }

            // 页码
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, currentPage + 2);

            if (startPage > 1) {
                paginationHtml += '<a href="#" class="pagination-link" data-page="1">1</a>';
                if (startPage > 2) {
                    paginationHtml += '<span>...</span>';
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    paginationHtml += '<span class="current">' + i + '</span>';
                } else {
                    paginationHtml += '<a href="#" class="pagination-link" data-page="' + i + '">' + i + '</a>';
                }
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationHtml += '<span>...</span>';
                }
                paginationHtml += '<a href="#" class="pagination-link" data-page="' + totalPages + '">' + totalPages + '</a>';
            }

            // 下一页
            if (currentPage < totalPages) {
                paginationHtml += '<a href="#" class="pagination-link" data-page="' + (currentPage + 1) + '">下一页 &raquo;</a>';
            }
        }

        $('#pagination-links, #pagination-links-bottom').html(paginationHtml);
    }

    // 分页点击事件
    $(document).on('click', '.pagination-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        loadAuditLogs(page, currentFilters);
    });

    // 每页条数变更
    $('#per-page-select').on('change', function() {
        perPage = parseInt($(this).val());
        loadAuditLogs(1, currentFilters);
    });

    // 应用过滤
    $('#apply-filters').on('click', function() {
        const filters = {
            user_id: $('#filter_user_id').val(),
            action: $('#filter_action').val(),
            resource_type: $('#filter_resource_type').val(),
            date_from: $('#filter_date_from').val(),
            date_to: $('#filter_date_to').val(),
            search: $('#filter_search').val()
        };

        // 移除空值
        Object.keys(filters).forEach(key => {
            if (!filters[key]) {
                delete filters[key];
            }
        });

        loadAuditLogs(1, filters);
    });

    // 清除过滤
    $('#clear-filters').on('click', function() {
        $('#audit-filter-form')[0].reset();
        loadAuditLogs(1, {});
    });

    // 导出日志
    $('#export-logs').on('click', function() {
        const filters = {
            user_id: $('#filter_user_id').val(),
            action: $('#filter_action').val(),
            resource_type: $('#filter_resource_type').val(),
            date_from: $('#filter_date_from').val(),
            date_to: $('#filter_date_to').val()
        };

        // 构建导出URL
        let exportUrl = ams_v2_admin.ajax_url + '?action=ams_v2_export_audit_logs&nonce=' + ams_v2_admin.nonce;
        
        Object.keys(filters).forEach(key => {
            if (filters[key]) {
                exportUrl += '&' + key + '=' + encodeURIComponent(filters[key]);
            }
        });

        window.location.href = exportUrl;
    });

    // 查看详情
    $(document).on('click', '.view-details', function() {
        const logId = $(this).data('log-id');
        
        $('#audit-log-details-content').html('正在加载...');
        $('#audit-log-details-modal').show();

        $.post(ams_v2_admin.ajax_url, {
            action: 'ams_v2_get_audit_log_details',
            nonce: ams_v2_admin.nonce,
            log_id: logId
        }, function(response) {
            if (response.success) {
                $('#audit-log-details-content').html(response.data.html);
            } else {
                $('#audit-log-details-content').html('<p class="error">加载失败: ' + response.data + '</p>');
            }
        }).fail(function() {
            $('#audit-log-details-content').html('<p class="error">网络错误，请重试</p>');
        });
    });

    // 关闭模态框
    $('.audit-modal-close, .audit-modal').on('click', function(e) {
        if (e.target === this) {
            $('#audit-log-details-modal').hide();
        }
    });

    // ESC键关闭模态框
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) {
            $('#audit-log-details-modal').hide();
        }
    });

    // HTML转义函数
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.replace(/[&<>"']/g, function(m) { return map[m]; }) : '';
    }

    // 初始加载
    loadAuditLogs();
});
</script>