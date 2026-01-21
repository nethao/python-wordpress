<?php
/**
 * 审核等级配置页面
 *
 * @package Article_Management_V2
 * @subpackage Admin/Partials
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 获取管理器
$config_manager = new AMS_V2_Moderation_Config_Manager();
$content_moderator = new AMS_V2_Content_Moderator();

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'ams_v2_moderation_settings')) {
        $message = '安全验证失败';
        $message_type = 'error';
    } else {
        switch ($_POST['action']) {
            case 'update_config':
                $new_config = array(
                    'current_level' => sanitize_text_field($_POST['current_level']),
                    'auto_moderate' => isset($_POST['auto_moderate']),
                    'log_all_actions' => isset($_POST['log_all_actions']),
                    'notify_on_block' => isset($_POST['notify_on_block']),
                    'whitelist_words' => array_filter(array_map('trim', explode("\n", sanitize_textarea_field($_POST['whitelist_words'])))),
                    'blacklist_words' => array_filter(array_map('trim', explode("\n", sanitize_textarea_field($_POST['blacklist_words'])))),
                    'bypass_users' => array_filter(array_map('intval', explode(',', sanitize_text_field($_POST['bypass_users'])))),
                    'bypass_roles' => array_filter(array_map('trim', explode(',', sanitize_text_field($_POST['bypass_roles']))))
                );
                
                $result = $config_manager->update_config($new_config, get_current_user_id());
                
                if (is_wp_error($result)) {
                    $message = $result->get_error_message();
                    $message_type = 'error';
                } else {
                    $message = '审核配置更新成功';
                    $message_type = 'success';
                }
                break;
                
            case 'reset_config':
                $result = $config_manager->reset_to_default(get_current_user_id());
                
                if (is_wp_error($result)) {
                    $message = $result->get_error_message();
                    $message_type = 'error';
                } else {
                    $message = '审核配置已重置为默认值';
                    $message_type = 'success';
                }
                break;
                
            case 'test_moderation':
                $test_text = sanitize_textarea_field($_POST['test_text']);
                $test_level = sanitize_text_field($_POST['test_level']);
                
                if (!empty($test_text)) {
                    $test_result = $content_moderator->preview_moderation($test_text, $test_level);
                    
                    // 将测试结果存储在会话中以便显示
                    if (!session_id()) {
                        session_start();
                    }
                    $_SESSION['moderation_test_result'] = $test_result;
                    $_SESSION['moderation_test_text'] = $test_text;
                    $_SESSION['moderation_test_level'] = $test_level;
                }
                break;
        }
    }
}

// 获取当前配置
$current_config = $config_manager->get_config();

// 获取测试结果
$test_result = null;
$test_text = '';
$test_level = '';
if (session_id() && isset($_SESSION['moderation_test_result'])) {
    $test_result = $_SESSION['moderation_test_result'];
    $test_text = $_SESSION['moderation_test_text'];
    $test_level = $_SESSION['moderation_test_level'];
    
    // 清除会话数据
    unset($_SESSION['moderation_test_result']);
    unset($_SESSION['moderation_test_text']);
    unset($_SESSION['moderation_test_level']);
}
?>

<div class="wrap">
    <h1>审核等级配置</h1>
    
    <?php if (!empty($message)): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="postbox-container" style="width: 70%; float: left;">
        <!-- 基本配置 -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">基本审核配置</h2>
            </div>
            <div class="inside">
                <form method="post" action="">
                    <?php wp_nonce_field('ams_v2_moderation_settings'); ?>
                    <input type="hidden" name="action" value="update_config">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">审核等级</th>
                            <td>
                                <fieldset>
                                    <?php foreach (AMS_V2_Moderation_Level::get_all_levels() as $level): ?>
                                        <label style="display: block; margin-bottom: 10px;">
                                            <input type="radio" name="current_level" value="<?php echo esc_attr($level); ?>" 
                                                   <?php checked($current_config['current_level'], $level); ?>>
                                            <strong class="moderation-level-<?php echo esc_attr($level); ?>">
                                                <?php echo esc_html(AMS_V2_Moderation_Level::get_level_name($level)); ?>
                                            </strong>
                                            <br>
                                            <span style="margin-left: 25px; color: #666; font-size: 13px;">
                                                <?php echo esc_html(AMS_V2_Moderation_Level::get_level_description($level)); ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">自动审核</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="auto_moderate" value="1" 
                                           <?php checked($current_config['auto_moderate']); ?>>
                                    启用自动内容审核
                                </label>
                                <p class="description">开启后，系统将自动对提交的内容进行审核</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">日志记录</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="log_all_actions" value="1" 
                                           <?php checked($current_config['log_all_actions']); ?>>
                                    记录所有审核操作
                                </label>
                                <p class="description">记录详细的审核日志，用于统计和分析</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">拦截通知</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="notify_on_block" value="1" 
                                           <?php checked($current_config['notify_on_block']); ?>>
                                    内容被拦截时通知用户
                                </label>
                                <p class="description">当内容包含敏感词被拦截时，向用户显示提示信息</p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('保存基本配置', 'primary'); ?>
                </form>
            </div>
        </div>

        <!-- 高级配置 -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">高级配置</h2>
            </div>
            <div class="inside">
                <form method="post" action="">
                    <?php wp_nonce_field('ams_v2_moderation_settings'); ?>
                    <input type="hidden" name="action" value="update_config">
                    
                    <!-- 复制基本配置的隐藏字段 -->
                    <input type="hidden" name="current_level" value="<?php echo esc_attr($current_config['current_level']); ?>">
                    <input type="hidden" name="auto_moderate" value="<?php echo $current_config['auto_moderate'] ? '1' : '0'; ?>">
                    <input type="hidden" name="log_all_actions" value="<?php echo $current_config['log_all_actions'] ? '1' : '0'; ?>">
                    <input type="hidden" name="notify_on_block" value="<?php echo $current_config['notify_on_block'] ? '1' : '0'; ?>">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="whitelist_words">白名单词汇</label></th>
                            <td>
                                <textarea id="whitelist_words" name="whitelist_words" rows="5" cols="50" class="large-text"><?php 
                                    echo esc_textarea(implode("\n", $current_config['whitelist_words'])); 
                                ?></textarea>
                                <p class="description">每行一个词汇。包含这些词汇的内容将直接通过审核</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="blacklist_words">黑名单词汇</label></th>
                            <td>
                                <textarea id="blacklist_words" name="blacklist_words" rows="5" cols="50" class="large-text"><?php 
                                    echo esc_textarea(implode("\n", $current_config['blacklist_words'])); 
                                ?></textarea>
                                <p class="description">每行一个词汇。包含这些词汇的内容将直接被拦截</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="bypass_users">绕过审核的用户ID</label></th>
                            <td>
                                <input type="text" id="bypass_users" name="bypass_users" class="regular-text" 
                                       value="<?php echo esc_attr(implode(',', $current_config['bypass_users'])); ?>">
                                <p class="description">用逗号分隔多个用户ID。这些用户的内容将跳过审核</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="bypass_roles">绕过审核的用户角色</label></th>
                            <td>
                                <input type="text" id="bypass_roles" name="bypass_roles" class="regular-text" 
                                       value="<?php echo esc_attr(implode(',', $current_config['bypass_roles'])); ?>">
                                <p class="description">用逗号分隔多个角色名称，如：administrator,editor</p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('保存高级配置', 'primary'); ?>
                </form>
            </div>
        </div>

        <!-- 审核测试 -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">审核测试</h2>
            </div>
            <div class="inside">
                <form method="post" action="">
                    <?php wp_nonce_field('ams_v2_moderation_settings'); ?>
                    <input type="hidden" name="action" value="test_moderation">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="test_text">测试文本</label></th>
                            <td>
                                <textarea id="test_text" name="test_text" rows="4" cols="50" class="large-text" 
                                          placeholder="输入要测试的文本内容..."><?php echo esc_textarea($test_text); ?></textarea>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="test_level">测试等级</label></th>
                            <td>
                                <select id="test_level" name="test_level">
                                    <?php foreach (AMS_V2_Moderation_Level::get_all_levels() as $level): ?>
                                        <option value="<?php echo esc_attr($level); ?>" <?php selected($test_level, $level); ?>>
                                            <?php echo esc_html(AMS_V2_Moderation_Level::get_level_name($level)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('测试审核', 'secondary'); ?>
                </form>
                
                <?php if ($test_result): ?>
                    <div class="test-result" style="margin-top: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
                        <h4>测试结果</h4>
                        
                        <div class="result-summary">
                            <p><strong>审核结果：</strong> 
                                <span class="result-<?php echo $test_result['approved'] ? 'approved' : 'blocked'; ?>">
                                    <?php echo $test_result['approved'] ? '通过' : '拦截'; ?>
                                </span>
                            </p>
                            
                            <p><strong>处理动作：</strong> <?php echo esc_html($test_result['action_taken']); ?></p>
                            
                            <?php if (!empty($test_result['message'])): ?>
                                <p><strong>说明：</strong> <?php echo esc_html($test_result['message']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($test_result['found_words'])): ?>
                            <div class="found-words">
                                <h5>发现的敏感词：</h5>
                                <ul>
                                    <?php foreach ($test_result['found_words'] as $found_word): ?>
                                        <li>
                                            <strong><?php echo esc_html($found_word['word']); ?></strong>
                                            (<?php echo esc_html(AMS_V2_Word_Type::get_type_name($found_word['type'])); ?>)
                                            → <?php echo esc_html($found_word['replacement']); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($test_result['modified_text'] !== $test_text): ?>
                            <div class="modified-text">
                                <h5>处理后的文本：</h5>
                                <div style="padding: 10px; background: white; border: 1px solid #ccc;">
                                    <?php echo esc_html($test_result['modified_text']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="postbox-container" style="width: 28%; float: right;">
        <!-- 配置信息 -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">当前配置信息</h2>
            </div>
            <div class="inside">
                <table class="widefat">
                    <tr>
                        <td><strong>当前等级</strong></td>
                        <td>
                            <span class="moderation-level-<?php echo esc_attr($current_config['current_level']); ?>">
                                <?php echo esc_html(AMS_V2_Moderation_Level::get_level_name($current_config['current_level'])); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>自动审核</strong></td>
                        <td><?php echo $current_config['auto_moderate'] ? '启用' : '禁用'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>日志记录</strong></td>
                        <td><?php echo $current_config['log_all_actions'] ? '启用' : '禁用'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>拦截通知</strong></td>
                        <td><?php echo $current_config['notify_on_block'] ? '启用' : '禁用'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>白名单词汇</strong></td>
                        <td><?php echo count($current_config['whitelist_words']); ?> 个</td>
                    </tr>
                    <tr>
                        <td><strong>黑名单词汇</strong></td>
                        <td><?php echo count($current_config['blacklist_words']); ?> 个</td>
                    </tr>
                    <tr>
                        <td><strong>绕过用户</strong></td>
                        <td><?php echo count($current_config['bypass_users']); ?> 个</td>
                    </tr>
                    <tr>
                        <td><strong>绕过角色</strong></td>
                        <td><?php echo count($current_config['bypass_roles']); ?> 个</td>
                    </tr>
                    <tr>
                        <td><strong>最后更新</strong></td>
                        <td><?php echo $current_config['updated_at'] ? date('Y-m-d H:i', strtotime($current_config['updated_at'])) : '未知'; ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- 操作 -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">配置操作</h2>
            </div>
            <div class="inside">
                <p>
                    <button type="button" class="button" onclick="location.reload();">刷新配置</button>
                </p>
                
                <p>
                    <form method="post" action="" style="display: inline;">
                        <?php wp_nonce_field('ams_v2_moderation_settings'); ?>
                        <input type="hidden" name="action" value="reset_config">
                        <button type="submit" class="button" 
                                onclick="return confirm('确定要重置配置为默认值吗？此操作不可撤销。');">
                            重置为默认
                        </button>
                    </form>
                </p>
                
                <p>
                    <a href="<?php echo admin_url('admin.php?page=article-management-v2-sensitive-words'); ?>" class="button">
                        管理敏感词
                    </a>
                </p>
            </div>
        </div>

        <!-- 帮助信息 -->
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">配置说明</h2>
            </div>
            <div class="inside">
                <h4>审核等级说明：</h4>
                <ul style="margin-left: 20px;">
                    <li><strong>宽松：</strong>仅拦截高危敏感词</li>
                    <li><strong>默认：</strong>拦截高危词，替换普通敏感词</li>
                    <li><strong>严格：</strong>拦截所有敏感词</li>
                </ul>
                
                <h4>白名单和黑名单：</h4>
                <ul style="margin-left: 20px;">
                    <li>白名单优先级高于敏感词库</li>
                    <li>黑名单优先级高于白名单</li>
                    <li>支持部分匹配</li>
                </ul>
                
                <h4>绕过设置：</h4>
                <ul style="margin-left: 20px;">
                    <li>指定用户或角色可跳过审核</li>
                    <li>通常用于管理员或编辑</li>
                    <li>谨慎使用，避免安全风险</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div style="clear: both;"></div>
</div>

<style>
.result-approved {
    color: #46b450;
    font-weight: bold;
}

.result-blocked {
    color: #dc3232;
    font-weight: bold;
}

.moderation-level-lenient {
    color: #28a745;
    font-weight: bold;
}

.moderation-level-default {
    color: #ffc107;
    font-weight: bold;
}

.moderation-level-strict {
    color: #dc3545;
    font-weight: bold;
}

.test-result {
    border-radius: 4px;
}

.found-words ul {
    margin-left: 20px;
}

.modified-text {
    margin-top: 15px;
}

.postbox-container {
    margin-right: 20px;
}

.postbox-container:last-child {
    margin-right: 0;
}
</style>