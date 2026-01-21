<?php
/**
 * 仪表板页面
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

// 获取统计数据
global $wpdb;
$db_manager = $ams_v2_container->get('database_manager');

$users_table = $db_manager->get_table_name('users');
$roles_table = $db_manager->get_table_name('roles');

$total_users = $wpdb->get_var("SELECT COUNT(*) FROM $users_table");
$active_users = $wpdb->get_var("SELECT COUNT(*) FROM $users_table WHERE is_active = 1");
$total_roles = $wpdb->get_var("SELECT COUNT(*) FROM $roles_table");

// 获取最近注册的用户
$recent_users = $auth_manager->get_users(array(
    'limit' => 5,
    'orderby' => 'created_at',
    'order' => 'DESC'
));

// 获取当前用户信息
$current_user = $auth_manager->get_current_user();
?>

<div class="wrap">
    <h1>仪表板</h1>
    <p>欢迎使用文章管理系统 V2.0</p>

    <?php if ($current_user): ?>
        <div class="ams-notice notice-info">
            <p>
                <strong>当前用户：</strong><?php echo esc_html($current_user->get_display_name()); ?> 
                (<?php echo esc_html($current_user->get_username()); ?>)
                <?php if ($current_user->is_administrator()): ?>
                    <span class="ams-tag tag-admin">管理员</span>
                <?php else: ?>
                    <span class="ams-tag tag-user">普通用户</span>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- 统计小部件 -->
    <div class="ams-dashboard-widgets">
        <div class="ams-dashboard-widget">
            <h3>用户统计</h3>
            <div class="ams-stat-number"><?php echo number_format($total_users); ?></div>
            <div class="ams-stat-label">总用户数</div>
            <p style="margin-top: 10px;">
                <small>
                    激活用户：<?php echo number_format($active_users); ?> | 
                    停用用户：<?php echo number_format($total_users - $active_users); ?>
                </small>
            </p>
        </div>

        <div class="ams-dashboard-widget">
            <h3>角色统计</h3>
            <div class="ams-stat-number"><?php echo number_format($total_roles); ?></div>
            <div class="ams-stat-label">总角色数</div>
        </div>

        <div class="ams-dashboard-widget">
            <h3>系统状态</h3>
            <div class="ams-stat-number" style="color: #46b450;">正常</div>
            <div class="ams-stat-label">运行状态</div>
            <p style="margin-top: 10px;">
                <small>
                    版本：<?php echo AMS_V2_VERSION; ?><br>
                    PHP版本：<?php echo PHP_VERSION; ?><br>
                    WordPress版本：<?php echo get_bloginfo('version'); ?>
                </small>
            </p>
        </div>

        <div class="ams-dashboard-widget">
            <h3>快速操作</h3>
            <p>
                <a href="<?php echo admin_url('admin.php?page=article-management-v2-users'); ?>" class="button button-primary">
                    管理用户
                </a>
            </p>
            <p>
                <a href="<?php echo admin_url('admin.php?page=article-management-v2-articles'); ?>" class="button">
                    管理文章
                </a>
            </p>
            <p>
                <a href="<?php echo admin_url('admin.php?page=article-management-v2-settings'); ?>" class="button">
                    系统设置
                </a>
            </p>
        </div>
    </div>

    <!-- 最近用户 -->
    <?php if (!empty($recent_users)): ?>
        <div class="ams-dashboard-widget" style="margin-top: 20px;">
            <h3>最近注册用户</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>用户名</th>
                        <th>显示名称</th>
                        <th>邮箱</th>
                        <th>注册时间</th>
                        <th>状态</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_users as $user): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($user->get_username()); ?></strong>
                            </td>
                            <td><?php echo esc_html($user->get_display_name()); ?></td>
                            <td><?php echo esc_html($user->get_email()); ?></td>
                            <td>
                                <?php 
                                $created_at = $user->get_created_at();
                                echo $created_at ? $created_at->format('Y-m-d H:i:s') : '-';
                                ?>
                            </td>
                            <td>
                                <span class="status-<?php echo $user->is_active() ? 'active' : 'inactive'; ?>">
                                    <?php echo $user->is_active() ? '激活' : '停用'; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- 系统信息 -->
    <div class="ams-dashboard-widget" style="margin-top: 20px;">
        <h3>系统信息</h3>
        <table class="form-table">
            <tr>
                <th>插件版本</th>
                <td><?php echo AMS_V2_VERSION; ?></td>
            </tr>
            <tr>
                <th>数据库版本</th>
                <td><?php echo $wpdb->db_version(); ?></td>
            </tr>
            <tr>
                <th>PHP版本</th>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <th>WordPress版本</th>
                <td><?php echo get_bloginfo('version'); ?></td>
            </tr>
            <tr>
                <th>服务器软件</th>
                <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
            </tr>
            <tr>
                <th>内存限制</th>
                <td><?php echo ini_get('memory_limit'); ?></td>
            </tr>
            <tr>
                <th>最大执行时间</th>
                <td><?php echo ini_get('max_execution_time'); ?>秒</td>
            </tr>
        </table>
    </div>

    <!-- 帮助信息 -->
    <div class="ams-dashboard-widget" style="margin-top: 20px;">
        <h3>帮助信息</h3>
        <p>文章管理系统 V2.0 是一个企业级多用户内容管理系统，支持以下功能：</p>
        <ul>
            <li><strong>用户权限管理</strong>：支持多角色用户管理和权限控制</li>
            <li><strong>文章管理</strong>：支持文章创建、编辑、审核和发布</li>
            <li><strong>多站点管理</strong>：支持多个WordPress站点的内容发布</li>
            <li><strong>内容审核</strong>：支持敏感词检测和内容审核</li>
            <li><strong>统计分析</strong>：提供详细的用户和内容统计报表</li>
            <li><strong>自动化运维</strong>：支持自动清理和定时任务</li>
        </ul>
        
        <p>
            <strong>快速开始：</strong>
        </p>
        <ol>
            <li>首先在<a href="<?php echo admin_url('admin.php?page=article-management-v2-users'); ?>">用户管理</a>中创建用户账户</li>
            <li>在<a href="<?php echo admin_url('admin.php?page=article-management-v2-settings'); ?>">系统设置</a>中配置基本参数</li>
            <li>开始使用<a href="<?php echo admin_url('admin.php?page=article-management-v2-articles'); ?>">文章管理</a>功能</li>
        </ol>
    </div>
</div>