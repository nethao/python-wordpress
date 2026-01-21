<?php
/**
 * 文章管理页面
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
$auth_middleware = $ams_v2_container->get('auth_middleware');
$article_manager = $ams_v2_container->get('article_manager');

// 检查用户登录状态
$current_user = $auth_middleware->get_current_user();
if (!$current_user) {
    wp_die('您需要登录才能访问此页面。', '未登录', array('response' => 401));
}

// 检查用户是否激活
if (!$current_user->is_active()) {
    wp_die('您的账户已被禁用，无法访问此页面。', '账户被禁用', array('response' => 403));
}

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ams_v2_nonce']) && wp_verify_nonce($_POST['ams_v2_nonce'], 'ams_v2_article_action')) {
    $action = sanitize_text_field($_POST['action']);
    
    switch ($action) {
        case 'create_article':
            // 检查创建权限
            if (!$current_user->has_capability('create_articles')) {
                $message = '您没有创建文章的权限';
                $message_type = 'error';
                break;
            }
            
            $article_data = new AMS_V2_Article_Data();
            $article_data->title = sanitize_text_field($_POST['title']);
            $article_data->content = wp_kses_post($_POST['content']);
            $article_data->excerpt = sanitize_textarea_field($_POST['excerpt']);
            $article_data->tags = array_map('sanitize_text_field', explode(',', $_POST['tags']));
            
            // 管理员可以设置状态，普通用户默认为待审核
            if ($current_user->is_administrator() && !empty($_POST['status'])) {
                $article_data->status = sanitize_text_field($_POST['status']);
            }
            
            $result = $article_manager->create_article($current_user, $article_data);
            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                $message_type = 'error';
            } else {
                $message = '文章创建成功';
                $message_type = 'success';
            }
            break;
            
        case 'update_article':
            $article_id = (int) $_POST['article_id'];
            $article = $article_manager->get_article_by_id($article_id);
            
            if (!$article) {
                $message = '文章不存在';
                $message_type = 'error';
                break;
            }
            
            // 检查编辑权限
            if (!$article_manager->can_edit_article($current_user, $article)) {
                $message = '您没有编辑此文章的权限';
                $message_type = 'error';
                break;
            }
            
            $article_data = new AMS_V2_Article_Data();
            $article_data->title = sanitize_text_field($_POST['title']);
            $article_data->content = wp_kses_post($_POST['content']);
            $article_data->excerpt = sanitize_textarea_field($_POST['excerpt']);
            $article_data->tags = array_map('sanitize_text_field', explode(',', $_POST['tags']));
            
            // 管理员可以设置状态
            if ($current_user->is_administrator() && !empty($_POST['status'])) {
                $article_data->status = sanitize_text_field($_POST['status']);
            }
            
            $result = $article_manager->update_article($article, $article_data, $current_user);
            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                $message_type = 'error';
            } else {
                $message = '文章更新成功';
                $message_type = 'success';
            }
            break;
            
        case 'moderate_article':
            // 只有管理员可以审核
            if (!$current_user->is_administrator()) {
                $message = '您没有审核文章的权限';
                $message_type = 'error';
                break;
            }
            
            $article_id = (int) $_POST['article_id'];
            $new_status = sanitize_text_field($_POST['new_status']);
            $notes = sanitize_textarea_field($_POST['moderation_notes']);
            
            $article = $article_manager->get_article_by_id($article_id);
            if (!$article) {
                $message = '文章不存在';
                $message_type = 'error';
                break;
            }
            
            $result = $article_manager->change_status($article, $new_status, $current_user, $notes);
            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                $message_type = 'error';
            } else {
                $message = '文章状态更新成功';
                $message_type = 'success';
            }
            break;
            
        case 'delete_article':
            $article_id = (int) $_POST['article_id'];
            $article = $article_manager->get_article_by_id($article_id);
            
            if (!$article) {
                $message = '文章不存在';
                $message_type = 'error';
                break;
            }
            
            // 检查删除权限
            if (!$article_manager->can_delete_article($current_user, $article)) {
                $message = '您没有删除此文章的权限';
                $message_type = 'error';
                break;
            }
            
            $result = $article_manager->delete_article($article, $current_user);
            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                $message_type = 'error';
            } else {
                $message = '文章删除成功';
                $message_type = 'success';
            }
            break;
    }
}

// 获取筛选参数
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$author_filter = isset($_GET['author']) ? (int) $_GET['author'] : 0;
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// 分页参数
$page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 构建查询参数
$query_args = array(
    'limit' => $per_page,
    'offset' => $offset,
    'order_by' => 'created_at',
    'order' => 'DESC'
);

if ($status_filter) {
    $query_args['status'] = $status_filter;
}

if ($author_filter) {
    $query_args['author_id'] = $author_filter;
}

// 根据用户角色获取文章列表
if ($current_user->is_administrator()) {
    // 管理员可以查看所有文章
    if ($search_query) {
        $articles = $article_manager->search_articles($search_query, $query_args);
    } else {
        $articles = $article_manager->get_all_articles($query_args);
    }
    
    // 获取总文章数
    global $wpdb;
    $db_manager = $ams_v2_container->get('database_manager');
    $articles_table = $db_manager->get_table_name('articles');
    
    $count_where = array();
    $count_values = array();
    
    if ($status_filter) {
        $count_where[] = 'status = %s';
        $count_values[] = $status_filter;
    }
    
    if ($author_filter) {
        $count_where[] = 'author_id = %d';
        $count_values[] = $author_filter;
    }
    
    if ($search_query) {
        $count_where[] = '(title LIKE %s OR content LIKE %s)';
        $count_values[] = '%' . $search_query . '%';
        $count_values[] = '%' . $search_query . '%';
    }
    
    $count_where_clause = !empty($count_where) ? 'WHERE ' . implode(' AND ', $count_where) : '';
    
    if (!empty($count_values)) {
        $total_articles = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $articles_table $count_where_clause",
            $count_values
        ));
    } else {
        $total_articles = $wpdb->get_var("SELECT COUNT(*) FROM $articles_table $count_where_clause");
    }
    
} else {
    // 普通用户只能查看自己的文章
    if ($search_query) {
        $query_args['author_id'] = $current_user->get_id();
        $articles = $article_manager->search_articles($search_query, $query_args);
    } else {
        $articles = $article_manager->get_articles_by_user($current_user, $query_args);
    }
    
    // 获取用户文章总数
    global $wpdb;
    $db_manager = $ams_v2_container->get('database_manager');
    $articles_table = $db_manager->get_table_name('articles');
    
    $count_where = array('author_id = %d');
    $count_values = array($current_user->get_id());
    
    if ($status_filter) {
        $count_where[] = 'status = %s';
        $count_values[] = $status_filter;
    }
    
    if ($search_query) {
        $count_where[] = '(title LIKE %s OR content LIKE %s)';
        $count_values[] = '%' . $search_query . '%';
        $count_values[] = '%' . $search_query . '%';
    }
    
    $count_where_clause = 'WHERE ' . implode(' AND ', $count_where);
    $total_articles = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $articles_table $count_where_clause",
        $count_values
    ));
}

$total_pages = ceil($total_articles / $per_page);

// 获取所有用户（用于筛选）
$all_users = array();
if ($current_user->is_administrator()) {
    $all_users = $auth_manager->get_users(array('limit' => 100));
}

// 获取文章统计
$article_stats = $article_manager->get_article_stats($current_user->is_administrator() ? null : $current_user);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">文章管理</h1>
    <?php if ($current_user->has_capability('create_articles')): ?>
        <a href="#" class="page-title-action" id="add-new-article">添加新文章</a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <!-- 用户信息提示 -->
    <div class="ams-notice notice-info">
        <p>
            <strong>当前用户：</strong><?php echo esc_html($current_user->get_display_name()); ?> 
            (<?php echo esc_html($current_user->get_username()); ?>)
            <?php if ($current_user->is_administrator()): ?>
                <span class="ams-tag tag-admin">管理员</span>
                <span class="description">- 您可以查看和管理所有用户的文章</span>
            <?php else: ?>
                <span class="ams-tag tag-user">普通用户</span>
                <span class="description">- 您只能查看和管理自己的文章</span>
            <?php endif; ?>
        </p>
    </div>

    <!-- 文章统计 -->
    <div class="ams-dashboard-widgets" style="margin: 20px 0;">
        <div class="ams-dashboard-widget" style="max-width: 300px;">
            <h3>文章统计</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 14px;">
                <div>总计: <strong><?php echo $article_stats['total']; ?></strong></div>
                <div>草稿: <strong><?php echo $article_stats['draft']; ?></strong></div>
                <div>待审核: <strong><?php echo $article_stats['pending_review']; ?></strong></div>
                <div>已批准: <strong><?php echo $article_stats['approved']; ?></strong></div>
                <div>已发布: <strong><?php echo $article_stats['published']; ?></strong></div>
                <div>已拒绝: <strong><?php echo $article_stats['rejected']; ?></strong></div>
            </div>
        </div>
    </div>

    <!-- 筛选和搜索 -->
    <div class="ams-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
            
            <div class="form-field">
                <label for="status-filter">状态筛选</label>
                <select name="status" id="status-filter">
                    <option value="">所有状态</option>
                    <option value="draft" <?php selected($status_filter, 'draft'); ?>>草稿</option>
                    <option value="pending_review" <?php selected($status_filter, 'pending_review'); ?>>待审核</option>
                    <option value="approved" <?php selected($status_filter, 'approved'); ?>>已批准</option>
                    <option value="published" <?php selected($status_filter, 'published'); ?>>已发布</option>
                    <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>已拒绝</option>
                    <option value="archived" <?php selected($status_filter, 'archived'); ?>>已归档</option>
                </select>
            </div>

            <?php if ($current_user->is_administrator() && !empty($all_users)): ?>
                <div class="form-field">
                    <label for="author-filter">作者筛选</label>
                    <select name="author" id="author-filter">
                        <option value="">所有作者</option>
                        <?php foreach ($all_users as $user): ?>
                            <option value="<?php echo esc_attr($user->get_id()); ?>" <?php selected($author_filter, $user->get_id()); ?>>
                                <?php echo esc_html($user->get_display_name() . ' (' . $user->get_username() . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-field">
                <label for="search-articles">搜索文章</label>
                <input type="text" name="s" id="search-articles" value="<?php echo esc_attr($search_query); ?>" placeholder="搜索标题或内容...">
            </div>

            <div class="form-field">
                <button type="submit" class="button">筛选</button>
                <a href="<?php echo admin_url('admin.php?page=' . $_GET['page']); ?>" class="button">重置</a>
            </div>
        </form>
    </div>

    <!-- 文章列表 -->
    <div class="tablenav top">
        <div class="alignleft actions bulkactions">
            <label for="bulk-action-selector-top" class="screen-reader-text">选择批量操作</label>
            <select name="action" id="bulk-action-selector-top">
                <option value="-1">批量操作</option>
                <?php if ($current_user->is_administrator()): ?>
                    <option value="approve">批准</option>
                    <option value="reject">拒绝</option>
                    <option value="publish">发布</option>
                    <option value="archive">归档</option>
                <?php endif; ?>
                <option value="delete">删除</option>
            </select>
            <input type="submit" id="doaction" class="button action ams-bulk-apply" value="应用">
        </div>
        
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo $total_articles; ?> 篇文章</span>
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

    <table class="wp-list-table widefat fixed striped articles">
        <thead>
            <tr>
                <td id="cb" class="manage-column column-cb check-column">
                    <label class="screen-reader-text" for="cb-select-all-1">全选</label>
                    <input id="cb-select-all-1" type="checkbox">
                </td>
                <th scope="col" class="manage-column column-title column-primary">标题</th>
                <th scope="col" class="manage-column column-author">作者</th>
                <th scope="col" class="manage-column column-status">状态</th>
                <th scope="col" class="manage-column column-date">创建时间</th>
                <th scope="col" class="manage-column column-updated">更新时间</th>
                <?php if ($current_user->is_administrator()): ?>
                    <th scope="col" class="manage-column column-moderation">审核操作</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody id="the-list">
            <?php if (empty($articles)): ?>
                <tr class="no-items">
                    <td class="colspanchange" colspan="<?php echo $current_user->is_administrator() ? '7' : '6'; ?>">
                        <?php if ($search_query || $status_filter || $author_filter): ?>
                            未找到符合条件的文章。
                        <?php else: ?>
                            还没有文章。<?php if ($current_user->has_capability('create_articles')): ?><a href="#" id="add-first-article">创建第一篇文章</a><?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($articles as $article): ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="articles[]" value="<?php echo esc_attr($article->get_id()); ?>">
                        </th>
                        <td class="title column-title column-primary">
                            <strong>
                                <a href="#" class="view-article" data-article-id="<?php echo esc_attr($article->get_id()); ?>">
                                    <?php echo esc_html($article->get_title()); ?>
                                </a>
                            </strong>
                            <?php if ($article->get_excerpt()): ?>
                                <p class="description"><?php echo esc_html(wp_trim_words($article->get_excerpt(), 15)); ?></p>
                            <?php endif; ?>
                            <div class="row-actions">
                                <span class="view">
                                    <a href="#" class="view-article" data-article-id="<?php echo esc_attr($article->get_id()); ?>">查看</a>
                                </span>
                                <?php if ($article_manager->can_edit_article($current_user, $article)): ?>
                                    | <span class="edit">
                                        <a href="#" class="edit-article" data-article-id="<?php echo esc_attr($article->get_id()); ?>">编辑</a>
                                    </span>
                                <?php endif; ?>
                                <?php if ($article_manager->can_delete_article($current_user, $article)): ?>
                                    | <span class="delete">
                                        <a href="#" class="delete-article ams-confirm-delete" 
                                           data-article-id="<?php echo esc_attr($article->get_id()); ?>"
                                           data-confirm="确定要删除文章 '<?php echo esc_attr($article->get_title()); ?>' 吗？此操作不可撤销。">删除</a>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="author column-author">
                            <?php 
                            $author = $article->get_author();
                            if ($author) {
                                echo esc_html($author->get_display_name());
                                echo '<br><small>(' . esc_html($author->get_username()) . ')</small>';
                            } else {
                                echo '未知作者';
                            }
                            ?>
                        </td>
                        <td class="status column-status">
                            <?php
                            $status = $article->get_status();
                            $status_labels = array(
                                'draft' => '草稿',
                                'pending_review' => '待审核',
                                'approved' => '已批准',
                                'published' => '已发布',
                                'rejected' => '已拒绝',
                                'archived' => '已归档'
                            );
                            $status_classes = array(
                                'draft' => 'status-draft',
                                'pending_review' => 'status-pending',
                                'approved' => 'status-approved',
                                'published' => 'status-published',
                                'rejected' => 'status-rejected',
                                'archived' => 'status-archived'
                            );
                            ?>
                            <span class="<?php echo esc_attr($status_classes[$status] ?? 'status-unknown'); ?>">
                                <?php echo esc_html($status_labels[$status] ?? $status); ?>
                            </span>
                        </td>
                        <td class="date column-date">
                            <?php 
                            $created_at = $article->get_created_at();
                            echo $created_at ? $created_at->format('Y-m-d H:i:s') : '-';
                            ?>
                        </td>
                        <td class="updated column-updated">
                            <?php 
                            $updated_at = $article->get_updated_at();
                            echo $updated_at ? $updated_at->format('Y-m-d H:i:s') : '-';
                            ?>
                        </td>
                        <?php if ($current_user->is_administrator()): ?>
                            <td class="moderation column-moderation">
                                <?php if ($article->get_status() === 'pending_review'): ?>
                                    <a href="#" class="button button-small moderate-article" 
                                       data-article-id="<?php echo esc_attr($article->get_id()); ?>"
                                       data-action="approve">批准</a>
                                    <a href="#" class="button button-small moderate-article" 
                                       data-article-id="<?php echo esc_attr($article->get_id()); ?>"
                                       data-action="reject">拒绝</a>
                                <?php elseif ($article->get_status() === 'approved'): ?>
                                    <a href="#" class="button button-small button-primary moderate-article" 
                                       data-article-id="<?php echo esc_attr($article->get_id()); ?>"
                                       data-action="publish">发布</a>
                                <?php else: ?>
                                    <span class="description">无需审核</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
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
                <?php if ($current_user->is_administrator()): ?>
                    <option value="approve">批准</option>
                    <option value="reject">拒绝</option>
                    <option value="publish">发布</option>
                    <option value="archive">归档</option>
                <?php endif; ?>
                <option value="delete">删除</option>
            </select>
            <input type="submit" id="doaction2" class="button action ams-bulk-apply" value="应用">
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

<!-- 添加/编辑文章模态框 -->
<div id="article-modal" class="ams-modal" style="display: none;">
    <div class="ams-modal-content" style="width: 800px; max-width: 95%;">
        <div class="ams-modal-header">
            <h2 id="article-modal-title">添加新文章</h2>
            <span class="ams-modal-close">&times;</span>
        </div>
        <div class="ams-modal-body">
            <form id="article-form" method="post">
                <?php wp_nonce_field('ams_v2_article_action', 'ams_v2_nonce'); ?>
                <input type="hidden" name="action" id="article-form-action" value="create_article">
                <input type="hidden" name="article_id" id="article-id" value="">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="article-title">标题 <span class="description">(必填)</span></label>
                        </th>
                        <td>
                            <input name="title" type="text" id="article-title" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="article-excerpt">摘要</label>
                        </th>
                        <td>
                            <textarea name="excerpt" id="article-excerpt" rows="3" class="large-text"></textarea>
                            <p class="description">文章的简短描述</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="article-content">内容 <span class="description">(必填)</span></label>
                        </th>
                        <td>
                            <textarea name="content" id="article-content" rows="10" class="large-text" required></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="article-tags">标签</label>
                        </th>
                        <td>
                            <input name="tags" type="text" id="article-tags" class="regular-text">
                            <p class="description">用逗号分隔多个标签</p>
                        </td>
                    </tr>
                    <?php if ($current_user->is_administrator()): ?>
                        <tr>
                            <th scope="row">
                                <label for="article-status">状态</label>
                            </th>
                            <td>
                                <select name="status" id="article-status">
                                    <option value="draft">草稿</option>
                                    <option value="pending_review">待审核</option>
                                    <option value="approved">已批准</option>
                                    <option value="published">已发布</option>
                                    <option value="rejected">已拒绝</option>
                                    <option value="archived">已归档</option>
                                </select>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="2">
                                <div class="ams-notice notice-info">
                                    <p><strong>注意：</strong>作为普通用户，您创建的文章将自动设置为"待审核"状态，需要管理员审核后才能发布。</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </form>
        </div>
        <div class="ams-modal-footer">
            <button type="button" class="button ams-modal-cancel">取消</button>
            <button type="submit" form="article-form" class="button button-primary" id="save-article">保存文章</button>
        </div>
    </div>
</div>

<!-- 查看文章模态框 -->
<div id="view-article-modal" class="ams-modal" style="display: none;">
    <div class="ams-modal-content" style="width: 800px; max-width: 95%;">
        <div class="ams-modal-header">
            <h2 id="view-article-title">查看文章</h2>
            <span class="ams-modal-close">&times;</span>
        </div>
        <div class="ams-modal-body">
            <div id="view-article-content">
                <!-- 文章内容将通过JavaScript加载 -->
            </div>
        </div>
        <div class="ams-modal-footer">
            <button type="button" class="button ams-modal-close">关闭</button>
        </div>
    </div>
</div>

<!-- 审核文章模态框（仅管理员可见） -->
<?php if ($current_user->is_administrator()): ?>
<div id="moderate-article-modal" class="ams-modal" style="display: none;">
    <div class="ams-modal-content">
        <div class="ams-modal-header">
            <h2 id="moderate-article-title">审核文章</h2>
            <span class="ams-modal-close">&times;</span>
        </div>
        <div class="ams-modal-body">
            <form id="moderate-article-form" method="post">
                <?php wp_nonce_field('ams_v2_article_action', 'ams_v2_nonce'); ?>
                <input type="hidden" name="action" value="moderate_article">
                <input type="hidden" name="article_id" id="moderate-article-id" value="">
                <input type="hidden" name="new_status" id="moderate-new-status" value="">
                
                <div id="moderate-article-info">
                    <!-- 文章信息将通过JavaScript加载 -->
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="moderation-notes">审核备注</label>
                        </th>
                        <td>
                            <textarea name="moderation_notes" id="moderation-notes" rows="4" class="large-text"></textarea>
                            <p class="description">可选：添加审核说明或反馈</p>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <div class="ams-modal-footer">
            <button type="button" class="button ams-modal-cancel">取消</button>
            <button type="submit" form="moderate-article-form" class="button button-primary" id="confirm-moderation">确认审核</button>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* 文章状态样式 */
.status-draft { color: #666; }
.status-pending { color: #d54e21; font-weight: bold; }
.status-approved { color: #46b450; font-weight: bold; }
.status-published { color: #0073aa; font-weight: bold; }
.status-rejected { color: #dc3232; font-weight: bold; }
.status-archived { color: #999; }

/* 表格列宽 */
.wp-list-table .column-title { width: 30%; }
.wp-list-table .column-author { width: 15%; }
.wp-list-table .column-status { width: 10%; }
.wp-list-table .column-date { width: 12%; }
.wp-list-table .column-updated { width: 12%; }
.wp-list-table .column-moderation { width: 15%; }

/* 审核按钮样式 */
.moderate-article {
    margin-right: 5px;
}

/* 文章内容预览 */
#view-article-content {
    max-height: 60vh;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid #ddd;
    background: #f9f9f9;
}

#view-article-content h3 {
    margin-top: 0;
    color: #0073aa;
}

#view-article-content .article-meta {
    background: #fff;
    padding: 10px;
    margin-bottom: 15px;
    border-left: 4px solid #0073aa;
}

#view-article-content .article-body {
    background: #fff;
    padding: 15px;
    line-height: 1.6;
}

/* 审核信息样式 */
#moderate-article-info {
    background: #f9f9f9;
    padding: 15px;
    margin-bottom: 20px;
    border-left: 4px solid #0073aa;
}

#moderate-article-info h4 {
    margin-top: 0;
    color: #0073aa;
}

#moderate-article-info .article-excerpt {
    color: #666;
    font-style: italic;
    margin: 10px 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    // 添加新文章
    $('#add-new-article, #add-first-article').on('click', function(e) {
        e.preventDefault();
        $('#article-modal-title').text('添加新文章');
        $('#article-form-action').val('create_article');
        $('#article-id').val('');
        $('#article-form')[0].reset();
        $('#article-modal').show();
    });

    // 编辑文章
    $(document).on('click', '.edit-article', function(e) {
        e.preventDefault();
        var articleId = $(this).data('article-id');
        
        // 通过AJAX获取文章数据
        $.post(ajaxurl, {
            action: 'ams_v2_get_article',
            article_id: articleId,
            nonce: '<?php echo wp_create_nonce('ams_v2_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                var article = response.data;
                $('#article-modal-title').text('编辑文章');
                $('#article-form-action').val('update_article');
                $('#article-id').val(article.id);
                $('#article-title').val(article.title);
                $('#article-excerpt').val(article.excerpt);
                $('#article-content').val(article.content);
                $('#article-tags').val(article.tags ? article.tags.join(', ') : '');
                if ($('#article-status').length) {
                    $('#article-status').val(article.status);
                }
                $('#article-modal').show();
            } else {
                alert('获取文章信息失败：' + response.data);
            }
        });
    });

    // 查看文章
    $(document).on('click', '.view-article', function(e) {
        e.preventDefault();
        var articleId = $(this).data('article-id');
        
        // 通过AJAX获取文章详情
        $.post(ajaxurl, {
            action: 'ams_v2_get_article',
            article_id: articleId,
            nonce: '<?php echo wp_create_nonce('ams_v2_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                var article = response.data;
                var statusLabels = {
                    'draft': '草稿',
                    'pending_review': '待审核',
                    'approved': '已批准',
                    'published': '已发布',
                    'rejected': '已拒绝',
                    'archived': '已归档'
                };
                
                var content = '<div class="article-meta">' +
                    '<h3>' + article.title + '</h3>' +
                    '<p><strong>作者：</strong>' + (article.author ? article.author.display_name : '未知') + '</p>' +
                    '<p><strong>状态：</strong>' + (statusLabels[article.status] || article.status) + '</p>' +
                    '<p><strong>创建时间：</strong>' + article.created_at + '</p>' +
                    '<p><strong>更新时间：</strong>' + article.updated_at + '</p>';
                
                if (article.tags && article.tags.length > 0) {
                    content += '<p><strong>标签：</strong>' + article.tags.join(', ') + '</p>';
                }
                
                content += '</div>';
                
                if (article.excerpt) {
                    content += '<div class="article-excerpt"><strong>摘要：</strong>' + article.excerpt + '</div>';
                }
                
                content += '<div class="article-body">' + article.content + '</div>';
                
                $('#view-article-content').html(content);
                $('#view-article-modal').show();
            } else {
                alert('获取文章信息失败：' + response.data);
            }
        });
    });

    // 审核文章（仅管理员）
    <?php if ($current_user->is_administrator()): ?>
    $(document).on('click', '.moderate-article', function(e) {
        e.preventDefault();
        var articleId = $(this).data('article-id');
        var action = $(this).data('action');
        
        // 通过AJAX获取文章信息
        $.post(ajaxurl, {
            action: 'ams_v2_get_article',
            article_id: articleId,
            nonce: '<?php echo wp_create_nonce('ams_v2_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                var article = response.data;
                var actionLabels = {
                    'approve': '批准',
                    'reject': '拒绝',
                    'publish': '发布'
                };
                
                $('#moderate-article-title').text(actionLabels[action] + '文章');
                $('#moderate-article-id').val(article.id);
                $('#moderate-new-status').val(action === 'approve' ? 'approved' : (action === 'reject' ? 'rejected' : 'published'));
                
                var info = '<h4>' + article.title + '</h4>' +
                    '<p><strong>作者：</strong>' + (article.author ? article.author.display_name : '未知') + '</p>' +
                    '<p><strong>当前状态：</strong>' + article.status + '</p>';
                
                if (article.excerpt) {
                    info += '<div class="article-excerpt">' + article.excerpt + '</div>';
                }
                
                $('#moderate-article-info').html(info);
                $('#moderation-notes').val('');
                $('#moderate-article-modal').show();
            } else {
                alert('获取文章信息失败：' + response.data);
            }
        });
    });
    <?php endif; ?>

    // 删除文章确认
    $(document).on('click', '.delete-article', function(e) {
        e.preventDefault();
        var articleId = $(this).data('article-id');
        var confirmMessage = $(this).data('confirm');
        
        if (confirm(confirmMessage)) {
            $.post(ajaxurl, {
                action: 'ams_v2_delete_article',
                article_id: articleId,
                nonce: '<?php echo wp_create_nonce('ams_v2_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('删除文章失败：' + response.data);
                }
            });
        }
    });

    // 关闭模态框
    $('.ams-modal-close, .ams-modal-cancel').on('click', function() {
        $('.ams-modal').hide();
    });

    // 点击模态框外部关闭
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('ams-modal')) {
            $('.ams-modal').hide();
        }
    });

    // 表单验证
    $('#article-form').on('submit', function(e) {
        var title = $('#article-title').val().trim();
        var content = $('#article-content').val().trim();
        
        if (!title) {
            e.preventDefault();
            alert('请输入文章标题');
            $('#article-title').focus();
            return false;
        }
        
        if (!content) {
            e.preventDefault();
            alert('请输入文章内容');
            $('#article-content').focus();
            return false;
        }
    });
});
</script>