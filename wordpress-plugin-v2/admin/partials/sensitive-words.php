<?php
/**
 * 敏感词管理页面
 *
 * @package Article_Management_V2
 * @subpackage Admin/Partials
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 获取敏感词管理器
$word_manager = new AMS_V2_Sensitive_Word_Manager();
$config_manager = new AMS_V2_Moderation_Config_Manager();
$content_moderator = new AMS_V2_Content_Moderator();

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'ams_v2_sensitive_words_action')) {
        $message = '安全验证失败';
        $message_type = 'error';
    } else {
        switch ($_POST['action']) {
            case 'add_word':
                $result = $word_manager->create_word(array(
                    'word' => sanitize_text_field($_POST['word']),
                    'type' => sanitize_text_field($_POST['type']),
                    'replacement' => sanitize_text_field($_POST['replacement']),
                    'is_builtin' => false,
                    'is_active' => isset($_POST['is_active']),
                    'created_by' => get_current_user_id()
                ));
                
                if (is_wp_error($result)) {
                    $message = $result->get_error_message();
                    $message_type = 'error';
                } else {
                    $message = '敏感词添加成功';
                    $message_type = 'success';
                }
                break;
                
            case 'update_moderation_level':
                $result = $config_manager->set_level(
                    sanitize_text_field($_POST['moderation_level']),
                    get_current_user_id()
                );
                
                if (is_wp_error($result)) {
                    $message = $result->get_error_message();
                    $message_type = 'error';
                } else {
                    $message = '审核等级更新成功';
                    $message_type = 'success';
                }
                break;
                
            case 'import_preset':
                $initializer = new AMS_V2_Sensitive_Words_Initializer();
                $result = $initializer->apply_preset_library(sanitize_text_field($_POST['preset_library']));
                
                if ($result['success']) {
                    $message = $result['message'];
                    $message_type = 'success';
                } else {
                    $message = $result['message'];
                    $message_type = 'error';
                }
                break;
        }
    }
}

// 获取当前数据
$current_config = $config_manager->get_config();
$current_level = $current_config['current_level'];
$word_statistics = $word_manager->get_word_statistics();

// 获取敏感词列表
$search_keyword = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : '';
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;

$search_args = array(
    'limit' => $per_page,
    'offset' => ($page - 1) * $per_page,
    'type' => $filter_type,
    'orderby' => 'created_at',
    'order' => 'DESC'
);

if (!empty($search_keyword)) {
    $words_data = $word_manager->search_words($search_keyword, $search_args);
} else {
    $words_data = $word_manager->get_words($search_args);
}

$total_pages = $words_data['pages'];
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (!empty($message)): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <!-- 审核等级配置 -->
    <div class="postbox">
        <div class="postbox-header">
            <h2 class="hndle">审核等级配置</h2>
        </div>
        <div class="inside">
            <form method="post" action="">
                <?php wp_nonce_field('ams_v2_sensitive_words_action'); ?>
                <input type="hidden" name="action" value="update_moderation_level">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">当前审核等级</th>
                        <td>
                            <fieldset>
                                <?php foreach (AMS_V2_Moderation_Level::get_all_levels() as $level): ?>
                                    <label>
                                        <input type="radio" name="moderation_level" value="<?php echo esc_attr($level); ?>" 
                                               <?php checked($current_level, $level); ?>>
                                        <span class="moderation-level-<?php echo esc_attr($level); ?>">
                                            <?php echo esc_html(AMS_V2_Moderation_Level::get_level_name($level)); ?>
                                        </span>
                                        <br>
                                        <small><?php echo esc_html(AMS_V2_Moderation_Level::get_level_description($level)); ?></small>
                                    </label>
                                    <br><br>
                                <?php endforeach; ?>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('更新审核等级', 'primary', 'submit', false); ?>
            </form>
        </div>
    </div>

    <!-- 统计信息 -->
    <div class="postbox">
        <div class="postbox-header">
            <h2 class="hndle">敏感词统计</h2>
        </div>
        <div class="inside">
            <div class="ams-statistics-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo esc_html($word_statistics['total']); ?></div>
                    <div class="stat-label">总敏感词数</div>
                </div>
                
                <?php if (isset($word_statistics['by_type'])): ?>
                    <?php foreach ($word_statistics['by_type'] as $type => $count): ?>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo esc_html($count); ?></div>
                            <div class="stat-label"><?php echo esc_html(AMS_V2_Word_Type::get_type_name($type)); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (isset($word_statistics['by_status'])): ?>
                    <?php foreach ($word_statistics['by_status'] as $status => $count): ?>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo esc_html($count); ?></div>
                            <div class="stat-label"><?php echo $status === 'active' ? '激活' : '停用'; ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 预置词库导入 -->
    <div class="postbox">
        <div class="postbox-header">
            <h2 class="hndle">预置词库导入</h2>
        </div>
        <div class="inside">
            <form method="post" action="">
                <?php wp_nonce_field('ams_v2_sensitive_words_action'); ?>
                <input type="hidden" name="action" value="import_preset">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">选择预置词库</th>
                        <td>
                            <?php 
                            $initializer = new AMS_V2_Sensitive_Words_Initializer();
                            $preset_libraries = $initializer->get_preset_word_libraries();
                            ?>
                            <select name="preset_library" required>
                                <option value="">请选择词库</option>
                                <?php foreach ($preset_libraries as $key => $library): ?>
                                    <option value="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($library['name']); ?> 
                                        (高危: <?php echo count($library['high_risk_words']); ?>个, 
                                         普通: <?php echo count($library['normal_words']); ?>个)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">导入预置敏感词库，已存在的词汇将被跳过</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('导入词库', 'secondary', 'submit', false); ?>
            </form>
        </div>
    </div>

    <!-- 添加敏感词 -->
    <div class="postbox">
        <div class="postbox-header">
            <h2 class="hndle">添加敏感词</h2>
        </div>
        <div class="inside">
            <form method="post" action="">
                <?php wp_nonce_field('ams_v2_sensitive_words_action'); ?>
                <input type="hidden" name="action" value="add_word">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="word">敏感词</label></th>
                        <td>
                            <input type="text" id="word" name="word" class="regular-text" required>
                            <p class="description">要添加的敏感词内容</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="type">词汇类型</label></th>
                        <td>
                            <select id="type" name="type" required>
                                <?php foreach (AMS_V2_Word_Type::get_all_types() as $type): ?>
                                    <option value="<?php echo esc_attr($type); ?>">
                                        <?php echo esc_html(AMS_V2_Word_Type::get_type_name($type)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="replacement">替换词</label></th>
                        <td>
                            <input type="text" id="replacement" name="replacement" class="regular-text" placeholder="留空使用默认替换">
                            <p class="description">用于替换敏感词的内容，留空将使用默认替换词</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">状态</th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" value="1" checked>
                                激活此敏感词
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('添加敏感词', 'primary', 'submit', false); ?>
            </form>
        </div>
    </div>

    <!-- 敏感词列表 -->
    <div class="postbox">
        <div class="postbox-header">
            <h2 class="hndle">敏感词管理</h2>
        </div>
        <div class="inside">
            <!-- 搜索和过滤 -->
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get" action="">
                        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                        
                        <input type="text" name="search" value="<?php echo esc_attr($search_keyword); ?>" 
                               placeholder="搜索敏感词..." class="regular-text">
                        
                        <select name="filter_type">
                            <option value="">所有类型</option>
                            <?php foreach (AMS_V2_Word_Type::get_all_types() as $type): ?>
                                <option value="<?php echo esc_attr($type); ?>" <?php selected($filter_type, $type); ?>>
                                    <?php echo esc_html(AMS_V2_Word_Type::get_type_name($type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php submit_button('搜索', 'secondary', 'submit', false, array('id' => 'search-submit')); ?>
                        
                        <?php if (!empty($search_keyword) || !empty($filter_type)): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=' . $_GET['page'])); ?>" class="button">清除</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="alignright actions">
                    <button type="button" class="button" id="bulk-delete-btn" disabled>批量删除</button>
                    <button type="button" class="button" id="export-words-btn">导出敏感词</button>
                    <button type="button" class="button" id="import-words-btn">批量导入</button>
                </div>
            </div>

            <!-- 敏感词表格 -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all">
                        </td>
                        <th class="manage-column">敏感词</th>
                        <th class="manage-column">类型</th>
                        <th class="manage-column">替换词</th>
                        <th class="manage-column">来源</th>
                        <th class="manage-column">状态</th>
                        <th class="manage-column">创建时间</th>
                        <th class="manage-column">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($words_data['words'])): ?>
                        <?php foreach ($words_data['words'] as $word): ?>
                            <tr data-word-id="<?php echo esc_attr($word->id); ?>">
                                <th class="check-column">
                                    <input type="checkbox" name="word_ids[]" value="<?php echo esc_attr($word->id); ?>" 
                                           class="word-checkbox" <?php echo $word->is_builtin ? 'disabled' : ''; ?>>
                                </th>
                                <td class="word-content">
                                    <strong><?php echo esc_html($word->word); ?></strong>
                                </td>
                                <td>
                                    <span class="word-type-badge word-type-<?php echo esc_attr($word->type); ?>">
                                        <?php echo esc_html($word->get_type_display_name()); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($word->replacement ?: $word->get_default_replacement()); ?></td>
                                <td>
                                    <?php if ($word->is_builtin): ?>
                                        <span class="builtin-badge">内置</span>
                                    <?php else: ?>
                                        <span class="custom-badge">自定义</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $word->is_active ? 'active' : 'inactive'; ?>">
                                        <?php echo $word->is_active ? '激活' : '停用'; ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($word->created_at))); ?></td>
                                <td class="actions">
                                    <?php if (!$word->is_builtin): ?>
                                        <button type="button" class="button-link edit-word-btn" 
                                                data-word-id="<?php echo esc_attr($word->id); ?>">编辑</button>
                                        |
                                        <button type="button" class="button-link delete-word-btn" 
                                                data-word-id="<?php echo esc_attr($word->id); ?>">删除</button>
                                        |
                                    <?php endif; ?>
                                    <button type="button" class="button-link toggle-status-btn" 
                                            data-word-id="<?php echo esc_attr($word->id); ?>"
                                            data-current-status="<?php echo $word->is_active ? '1' : '0'; ?>">
                                        <?php echo $word->is_active ? '停用' : '激活'; ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-items">没有找到敏感词</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $pagination_args = array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $page
                        );
                        echo paginate_links($pagination_args);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 编辑敏感词模态框 -->
<div id="edit-word-modal" class="ams-modal" style="display: none;">
    <div class="ams-modal-content">
        <div class="ams-modal-header">
            <h3>编辑敏感词</h3>
            <span class="ams-modal-close">&times;</span>
        </div>
        <div class="ams-modal-body">
            <form id="edit-word-form">
                <input type="hidden" id="edit-word-id" name="word_id">
                
                <table class="form-table">
                    <tr>
                        <th><label for="edit-word">敏感词</label></th>
                        <td><input type="text" id="edit-word" name="word" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="edit-type">类型</label></th>
                        <td>
                            <select id="edit-type" name="type" required>
                                <?php foreach (AMS_V2_Word_Type::get_all_types() as $type): ?>
                                    <option value="<?php echo esc_attr($type); ?>">
                                        <?php echo esc_html(AMS_V2_Word_Type::get_type_name($type)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="edit-replacement">替换词</label></th>
                        <td><input type="text" id="edit-replacement" name="replacement" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>状态</th>
                        <td>
                            <label>
                                <input type="checkbox" id="edit-is-active" name="is_active" value="1">
                                激活此敏感词
                            </label>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <div class="ams-modal-footer">
            <button type="button" class="button button-primary" id="save-word-btn">保存</button>
            <button type="button" class="button" id="cancel-edit-btn">取消</button>
        </div>
    </div>
</div>

<!-- 批量导入模态框 -->
<div id="import-modal" class="ams-modal" style="display: none;">
    <div class="ams-modal-content">
        <div class="ams-modal-header">
            <h3>批量导入敏感词</h3>
            <span class="ams-modal-close">&times;</span>
        </div>
        <div class="ams-modal-body">
            <form id="import-form" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <th><label for="import-file">选择文件</label></th>
                        <td>
                            <input type="file" id="import-file" name="import_file" accept=".txt,.csv,.json" required>
                            <p class="description">支持 TXT、CSV、JSON 格式文件</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="import-type">词汇类型</label></th>
                        <td>
                            <select id="import-type" name="import_type" required>
                                <?php foreach (AMS_V2_Word_Type::get_all_types() as $type): ?>
                                    <option value="<?php echo esc_attr($type); ?>">
                                        <?php echo esc_html(AMS_V2_Word_Type::get_type_name($type)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <div class="import-format-help">
                    <h4>文件格式说明：</h4>
                    <ul>
                        <li><strong>TXT格式</strong>: 每行一个敏感词</li>
                        <li><strong>CSV格式</strong>: 词汇,替换词（第一行为标题可选）</li>
                        <li><strong>JSON格式</strong>: [{"word":"词汇","replacement":"替换词"}]</li>
                    </ul>
                </div>
            </form>
        </div>
        <div class="ams-modal-footer">
            <button type="button" class="button button-primary" id="start-import-btn">开始导入</button>
            <button type="button" class="button" id="cancel-import-btn">取消</button>
        </div>
    </div>
</div>

<style>
.ams-statistics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-item {
    text-align: center;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 4px;
}

.stat-number {
    font-size: 2em;
    font-weight: bold;
    color: #0073aa;
}

.stat-label {
    margin-top: 5px;
    color: #666;
}

.word-type-badge {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}

.word-type-normal {
    background: #fff3cd;
    color: #856404;
}

.word-type-high_risk {
    background: #f8d7da;
    color: #721c24;
}

.builtin-badge {
    padding: 2px 6px;
    background: #d1ecf1;
    color: #0c5460;
    border-radius: 3px;
    font-size: 11px;
}

.custom-badge {
    padding: 2px 6px;
    background: #d4edda;
    color: #155724;
    border-radius: 3px;
    font-size: 11px;
}

.status-badge {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

.status-active {
    background: #d4edda;
    color: #155724;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.moderation-level-lenient {
    color: #28a745;
}

.moderation-level-default {
    color: #ffc107;
}

.moderation-level-strict {
    color: #dc3545;
}

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
    padding: 0;
    border: 1px solid #888;
    width: 80%;
    max-width: 600px;
    border-radius: 4px;
}

.ams-modal-header {
    padding: 15px 20px;
    background: #f1f1f1;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.ams-modal-header h3 {
    margin: 0;
}

.ams-modal-close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.ams-modal-close:hover {
    color: #000;
}

.ams-modal-body {
    padding: 20px;
}

.ams-modal-footer {
    padding: 15px 20px;
    background: #f1f1f1;
    border-top: 1px solid #ddd;
    text-align: right;
}

.ams-modal-footer .button {
    margin-left: 10px;
}

.import-format-help {
    margin-top: 20px;
    padding: 15px;
    background: #f9f9f9;
    border-left: 4px solid #0073aa;
}

.import-format-help h4 {
    margin-top: 0;
}

.import-format-help ul {
    margin-bottom: 0;
}
</style>