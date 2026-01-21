<?php
/**
 * 用户管理页面
 *
 * @package Article_Management_V2
 * @subpackage Admin/Partials
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 获取依赖注入容器
global $ams_v2_container;
if (!$ams_v2_container) {
    $ams_v2_container = $this->container;
}

$auth_manager = $ams_v2_container->get('authentication_manager');
$role_manager = $ams_v2_container->get('role_manager');

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ams_v2_nonce']) && wp_verify_nonce($_POST['ams_v2_nonce'], 'ams_v2_user_action')) {
    $action = sanitize_text_field($_POST['action']);
    
    switch ($action) {
        case 'create_user':
            $result = $auth_manager->create_user($_POST);
            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                $message_type = 'error';
            } else {
                $message = '用户创建成功';
                $message_type = 'success';
            }
            break;
            
        case 'update_user':
            $user_id = (int) $_POST['user_id'];
            $user = $auth_manager->get_user_by_id($user_id);
            if ($user) {
                $user->set_email(sanitize_email($_POST['email']));
                $user->set_display_name(sanitize_text_field($_POST['display_name']));
                $user->set_active(isset($_POST['is_active']));
                
                $result = $auth_manager->update_user($user);
                if (is_wp_error($result)) {
                    $message = $result->get_error_message();
                    $message_type = 'error';
                } else {
                    $message = '用户更新成功';
                    $message_type = 'success';
                }
            }
            break;
            
        case 'delete_user':
            $user_id = (int) $_POST['user_id'];
            $result = $auth_manager->delete_user($user_id);
            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                $message_type = 'error';
            } else {
                $message = '用户删除成功';
                $message_type = 'success';
            }
            break;
    }
}

// 获取用户列表
$page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$users = $auth_manager->get_users(array(
    'limit' => $per_page,
    'offset' => $offset,
    'orderby' => 'created_at',
    'order' => 'DESC'
));

// 获取所有角色
$roles = $role_manager->get_all_roles();

// 获取总用户数（用于分页）
global $wpdb;
$db_manager = $ams_v2_container->get('database_manager');
$users_table = $db_manager->get_table_name('users');
$total_users = $wpdb->get_var("SELECT COUNT(*) FROM $users_table");
$total_pages = ceil($total_users / $per_page);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">用户管理</h1>
    <a href="#" class="page-title-action" id="add-new-user">添加新用户</a>
    <hr class="wp-header-end">

    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <!-- 用户列表 -->
    <div class="tablenav top">
        <div class="alignleft actions bulkactions">
            <label for="bulk-action-selector-top" class="screen-reader-text">选择批量操作</label>
            <select name="action" id="bulk-action-selector-top">
                <option value="-1">批量操作</option>
                <option value="activate">激活</option>
                <option value="deactivate">停用</option>
            </select>
            <input type="submit" id="doaction" class="button action" value="应用">
        </div>
        
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo $total_users; ?> 个用户</span>
            <?php if ($total_pages > 1): ?>
                <span class="pagination-links">
                    <?php
                    $page_links = paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $page,
                        'type' => 'array'
                    ));
                    
                    if ($page_links) {
                        echo implode("\n", $page_links);
                    }
                    ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped users">
        <thead>
            <tr>
                <td id="cb" class="manage-column column-cb check-column">
                    <label class="screen-reader-text" for="cb-select-all-1">全选</label>
                    <input id="cb-select-all-1" type="checkbox">
                </td>
                <th scope="col" class="manage-column column-username column-primary">用户名</th>
                <th scope="col" class="manage-column column-name">显示名称</th>
                <th scope="col" class="manage-column column-email">邮箱</th>
                <th scope="col" class="manage-column column-role">角色</th>
                <th scope="col" class="manage-column column-status">状态</th>
                <th scope="col" class="manage-column column-date">注册时间</th>
                <th scope="col" class="manage-column column-last-login">最后登录</th>
            </tr>
        </thead>
        <tbody id="the-list">
            <?php if (empty($users)): ?>
                <tr class="no-items">
                    <td class="colspanchange" colspan="8">未找到用户。</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <?php
                    $user_roles = $role_manager->get_user_roles($user);
                    $role_names = array_map(function($role) {
                        return $role->get_display_name();
                    }, $user_roles);
                    ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="users[]" value="<?php echo esc_attr($user->get_id()); ?>">
                        </th>
                        <td class="username column-username column-primary">
                            <strong><?php echo esc_html($user->get_username()); ?></strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="#" class="edit-user" data-user-id="<?php echo esc_attr($user->get_id()); ?>">编辑</a> |
                                </span>
                                <span class="delete">
                                    <a href="#" class="delete-user" data-user-id="<?php echo esc_attr($user->get_id()); ?>" data-username="<?php echo esc_attr($user->get_username()); ?>">删除</a>
                                </span>
                            </div>
                        </td>
                        <td class="name column-name">
                            <?php echo esc_html($user->get_display_name()); ?>
                        </td>
                        <td class="email column-email">
                            <a href="mailto:<?php echo esc_attr($user->get_email()); ?>">
                                <?php echo esc_html($user->get_email()); ?>
                            </a>
                        </td>
                        <td class="role column-role">
                            <?php echo esc_html(implode(', ', $role_names)); ?>
                        </td>
                        <td class="status column-status">
                            <span class="status-<?php echo $user->is_active() ? 'active' : 'inactive'; ?>">
                                <?php echo $user->is_active() ? '激活' : '停用'; ?>
                            </span>
                        </td>
                        <td class="date column-date">
                            <?php 
                            $created_at = $user->get_created_at();
                            echo $created_at ? $created_at->format('Y-m-d H:i:s') : '-';
                            ?>
                        </td>
                        <td class="last-login column-last-login">
                            <?php 
                            $last_login = $user->get_last_login_at();
                            echo $last_login ? $last_login->format('Y-m-d H:i:s') : '从未登录';
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="tablenav bottom">
        <div class="alignleft actions bulkactions">
            <label for="bulk-action-selector-bottom" class="screen-reader-text">选择批量操作</label>
            <select name="action2" id="bulk-action-selector-bottom">
                <option value="-1">批量操作</option>
                <option value="activate">激活</option>
                <option value="deactivate">停用</option>
            </select>
            <input type="submit" id="doaction2" class="button action" value="应用">
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="tablenav-pages">
                <span class="pagination-links">
                    <?php
                    if ($page_links) {
                        echo implode("\n", $page_links);
                    }
                    ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 添加/编辑用户模态框 -->
<div id="user-modal" class="ams-modal" style="display: none;">
    <div class="ams-modal-content">
        <div class="ams-modal-header">
            <h2 id="modal-title">添加新用户</h2>
            <span class="ams-modal-close">&times;</span>
        </div>
        <div class="ams-modal-body">
            <form id="user-form" method="post">
                <?php wp_nonce_field('ams_v2_user_action', 'ams_v2_nonce'); ?>
                <input type="hidden" name="action" id="form-action" value="create_user">
                <input type="hidden" name="user_id" id="user-id" value="">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="username">用户名 <span class="description">(必填)</span></label>
                        </th>
                        <td>
                            <input name="username" type="text" id="username" class="regular-text" required>
                            <p class="description">用户名不能修改</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="email">邮箱 <span class="description">(必填)</span></label>
                        </th>
                        <td>
                            <input name="email" type="email" id="email" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="display_name">显示名称</label>
                        </th>
                        <td>
                            <input name="display_name" type="text" id="display_name" class="regular-text">
                        </td>
                    </tr>
                    <tr id="password-row">
                        <th scope="row">
                            <label for="password">密码 <span class="description">(必填)</span></label>
                        </th>
                        <td>
                            <input name="password" type="password" id="password" class="regular-text" required>
                            <p class="description">密码长度至少6位</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="role">角色</label>
                        </th>
                        <td>
                            <select name="role" id="role">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo esc_attr($role->get_name()); ?>">
                                        <?php echo esc_html($role->get_display_name()); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">状态</th>
                        <td>
                            <label for="is_active">
                                <input name="is_active" type="checkbox" id="is_active" value="1" checked>
                                激活用户
                            </label>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <div class="ams-modal-footer">
            <button type="button" class="button" id="cancel-user">取消</button>
            <button type="submit" form="user-form" class="button button-primary" id="save-user">保存用户</button>
        </div>
    </div>
</div>

<style>
.ams-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.ams-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    border: 1px solid #888;
    width: 600px;
    max-width: 90%;
    border-radius: 4px;
}

.ams-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    position: relative;
}

.ams-modal-header h2 {
    margin: 0;
}

.ams-modal-close {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.ams-modal-close:hover {
    color: #999;
}

.ams-modal-body {
    padding: 20px;
}

.ams-modal-footer {
    padding: 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}

.ams-modal-footer .button {
    margin-left: 10px;
}

.status-active {
    color: #46b450;
    font-weight: bold;
}

.status-inactive {
    color: #dc3232;
    font-weight: bold;
}
</style>

<script>
jQuery(document).ready(function($) {
    // 添加新用户
    $('#add-new-user').on('click', function(e) {
        e.preventDefault();
        $('#modal-title').text('添加新用户');
        $('#form-action').val('create_user');
        $('#user-id').val('');
        $('#user-form')[0].reset();
        $('#username').prop('readonly', false);
        $('#password-row').show();
        $('#password').prop('required', true);
        $('#is_active').prop('checked', true);
        $('#user-modal').show();
    });

    // 编辑用户
    $('.edit-user').on('click', function(e) {
        e.preventDefault();
        var userId = $(this).data('user-id');
        
        // 通过AJAX获取用户数据
        $.post(ajaxurl, {
            action: 'ams_v2_get_user',
            user_id: userId,
            nonce: '<?php echo wp_create_nonce('ams_v2_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                var user = response.data;
                $('#modal-title').text('编辑用户');
                $('#form-action').val('update_user');
                $('#user-id').val(user.id);
                $('#username').val(user.username).prop('readonly', true);
                $('#email').val(user.email);
                $('#display_name').val(user.display_name);
                $('#is_active').prop('checked', user.is_active);
                $('#password-row').hide();
                $('#password').prop('required', false);
                $('#user-modal').show();
            } else {
                alert('获取用户信息失败：' + response.data);
            }
        });
    });

    // 删除用户
    $('.delete-user').on('click', function(e) {
        e.preventDefault();
        var userId = $(this).data('user-id');
        var username = $(this).data('username');
        
        if (confirm('确定要删除用户 "' + username + '" 吗？此操作不可撤销。')) {
            $.post(ajaxurl, {
                action: 'ams_v2_delete_user',
                user_id: userId,
                nonce: '<?php echo wp_create_nonce('ams_v2_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('删除用户失败：' + response.data);
                }
            });
        }
    });

    // 关闭模态框
    $('.ams-modal-close, #cancel-user').on('click', function() {
        $('#user-modal').hide();
    });

    // 点击模态框外部关闭
    $(window).on('click', function(e) {
        if (e.target.id === 'user-modal') {
            $('#user-modal').hide();
        }
    });

    // 表单验证
    $('#user-form').on('submit', function(e) {
        var password = $('#password').val();
        if ($('#password').prop('required') && password.length < 6) {
            e.preventDefault();
            alert('密码长度至少6位');
            return false;
        }
    });
});
</script>