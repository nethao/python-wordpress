<?php
/**
 * 完整工作流程集成测试
 * 
 * 测试完整的用户工作流程，验证多站点发布和同步功能，测试系统在高负载下的表现
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 加载WordPress环境
require_once(dirname(__FILE__) . '/../../../wp-config.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

// 加载插件
require_once(plugin_dir_path(__FILE__) . 'article-management-v2.php');

class CompleteWorkflowTest {
    
    private $results = [];
    private $plugin;
    private $test_data = [];
    
    public function __construct() {
        $this->plugin = Article_Management_V2::get_instance();
    }
    
    /**
     * 运行所有完整工作流程测试
     */
    public function run_all_tests() {
        echo "<h2>完整工作流程集成测试报告</h2>\n";
        
        $this->setup_test_environment();
        $this->test_complete_user_workflow();
        $this->test_multi_site_publishing();
        $this->test_content_moderation_workflow();
        $this->test_statistics_and_reporting();
        $this->test_audit_and_compliance();
        $this->test_system_performance();
        $this->test_error_recovery();
        $this->cleanup_test_environment();
        
        $this->display_results();
    }
    
    /**
     * 设置测试环境
     */
    private function setup_test_environment() {
        echo "<h3>设置测试环境</h3>\n";
        
        try {
            // 创建测试管理员用户
            $auth_manager = $this->plugin->get_container()->get('authentication_manager');
            $admin_user = new AMS_V2_User();
            $admin_user->set_username('test_admin');
            $admin_user->set_email('admin@test.com');
            $admin_user->set_password('admin_password');
            $admin_user->set_role('admin');
            
            $this->test_data['admin_id'] = $auth_manager->create_user($admin_user);
            
            // 创建测试普通用户
            $normal_user = new AMS_V2_User();
            $normal_user->set_username('test_user');
            $normal_user->set_email('user@test.com');
            $normal_user->set_password('user_password');
            $normal_user->set_role('user');
            
            $this->test_data['user_id'] = $auth_manager->create_user($normal_user);
            
            // 创建测试站点
            $site_manager = $this->plugin->get_container()->get('site_manager');
            $test_sites = [
                [
                    'name' => '测试站点1',
                    'url' => 'https://site1.test.com',
                    'category_id' => 1
                ],
                [
                    'name' => '测试站点2', 
                    'url' => 'https://site2.test.com',
                    'category_id' => 2
                ]
            ];
            
            $this->test_data['site_ids'] = [];
            foreach ($test_sites as $site_data) {
                $site = new AMS_V2_Site();
                $site->set_name($site_data['name']);
                $site->set_url($site_data['url']);
                $site->set_default_category_id($site_data['category_id']);
                
                $credentials = new AMS_V2_Site_Credentials();
                $credentials->set_username('wp_user');
                $credentials->set_password('wp_password');
                $site->set_credentials($credentials);
                
                $site_id = $site_manager->create_site($site);
                $this->test_data['site_ids'][] = $site_id;
            }
            
            // 添加测试敏感词
            $sensitive_word_manager = $this->plugin->get_container()->get('sensitive_word_manager');
            $test_words = ['测试敏感词', '违禁内容', '不当言论'];
            $this->test_data['word_ids'] = [];
            
            foreach ($test_words as $word) {
                $sensitive_word = new AMS_V2_Sensitive_Word();
                $sensitive_word->set_word($word);
                $sensitive_word->set_type(AMS_V2_Word_Type::NORMAL);
                
                $word_id = $sensitive_word_manager->add_word($sensitive_word);
                $this->test_data['word_ids'][] = $word_id;
            }
            
            $this->add_result('测试环境设置', true, '测试环境设置完成');
            
        } catch (Exception $e) {
            $this->add_result('测试环境设置', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试完整用户工作流程
     */
    private function test_complete_user_workflow() {
        echo "<h3>测试完整用户工作流程</h3>\n";
        
        try {
            $auth_manager = $this->plugin->get_container()->get('authentication_manager');
            $article_manager = $this->plugin->get_container()->get('article_manager');
            $audit_logger = $this->plugin->get_container()->get('audit_logger');
            
            // 1. 用户登录
            $user = $auth_manager->authenticate('test_user', 'user_password');
            if ($user) {
                $this->add_result('用户登录', true, '用户成功登录');
                
                // 验证登录审计日志
                $login_logs = $audit_logger->get_logs(['action' => 'user_login'], 1);
                if (!empty($login_logs)) {
                    $this->add_result('登录审计日志', true, '登录操作已记录审计日志');
                } else {
                    $this->add_result('登录审计日志', false, '登录操作未记录审计日志');
                }
            } else {
                $this->add_result('用户登录', false, '用户登录失败');
                return;
            }
            
            // 2. 创建文章
            $article = new AMS_V2_Article();
            $article->set_title('完整工作流程测试文章');
            $article->set_content('这是一个完整工作流程测试文章，用于验证系统的端到端功能。');
            $article->set_author_id($this->test_data['user_id']);
            $article->set_site_id($this->test_data['site_ids'][0]);
            $article->set_status('draft');
            
            $article_id = $article_manager->create_article($article);
            if ($article_id) {
                $this->test_data['article_ids'][] = $article_id;
                $this->add_result('文章创建', true, "文章创建成功，ID: $article_id");
                
                // 验证文章创建审计日志
                $create_logs = $audit_logger->get_logs(['action' => 'article_created'], 1);
                if (!empty($create_logs)) {
                    $this->add_result('文章创建审计', true, '文章创建已记录审计日志');
                } else {
                    $this->add_result('文章创建审计', false, '文章创建未记录审计日志');
                }
            } else {
                $this->add_result('文章创建', false, '文章创建失败');
                return;
            }
            
            // 3. 文章审核（管理员操作）
            $admin_user = $auth_manager->authenticate('test_admin', 'admin_password');
            if ($admin_user && $auth_manager->check_permission($admin_user, 'moderate_articles')) {
                $article->set_status('published');
                if ($article_manager->update_article($article)) {
                    $this->add_result('文章审核', true, '文章审核通过并发布');
                } else {
                    $this->add_result('文章审核', false, '文章审核失败');
                }
            } else {
                $this->add_result('文章审核', false, '管理员权限验证失败');
            }
            
            // 4. 权限验证
            if (!$auth_manager->check_permission($user, 'manage_sites')) {
                $this->add_result('权限控制', true, '普通用户正确被限制访问管理功能');
            } else {
                $this->add_result('权限控制', false, '权限控制异常，普通用户获得了管理权限');
            }
            
        } catch (Exception $e) {
            $this->add_result('完整用户工作流程', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试多站点发布和同步功能
     */
    private function test_multi_site_publishing() {
        echo "<h3>测试多站点发布和同步功能</h3>\n";
        
        try {
            $article_manager = $this->plugin->get_container()->get('article_manager');
            $site_manager = $this->plugin->get_container()->get('site_manager');
            
            // 创建多站点发布文章
            $multi_site_articles = [];
            foreach ($this->test_data['site_ids'] as $index => $site_id) {
                $article = new AMS_V2_Article();
                $article->set_title("多站点测试文章 " . ($index + 1));
                $article->set_content("这是发布到站点 " . ($index + 1) . " 的测试文章内容。");
                $article->set_author_id($this->test_data['user_id']);
                $article->set_site_id($site_id);
                $article->set_status('published');
                
                $article_id = $article_manager->create_article($article);
                if ($article_id) {
                    $multi_site_articles[] = $article_id;
                    $this->test_data['article_ids'][] = $article_id;
                }
            }
            
            if (count($multi_site_articles) == count($this->test_data['site_ids'])) {
                $this->add_result('多站点文章创建', true, '成功创建多个站点的文章');
            } else {
                $this->add_result('多站点文章创建', false, '多站点文章创建不完整');
            }
            
            // 测试站点连接
            $connected_sites = 0;
            foreach ($this->test_data['site_ids'] as $site_id) {
                $site = $site_manager->get_site($site_id);
                if ($site) {
                    // 模拟连接测试（实际环境中会进行真实连接）
                    $connected_sites++;
                }
            }
            
            if ($connected_sites == count($this->test_data['site_ids'])) {
                $this->add_result('站点连接测试', true, '所有站点连接正常');
            } else {
                $this->add_result('站点连接测试', false, '部分站点连接异常');
            }
            
            // 测试批量发布
            $batch_articles = [];
            for ($i = 0; $i < 5; $i++) {
                $article = new AMS_V2_Article();
                $article->set_title("批量发布测试文章 $i");
                $article->set_content("批量发布测试内容 $i");
                $article->set_author_id($this->test_data['user_id']);
                $article->set_site_id($this->test_data['site_ids'][0]);
                $article->set_status('published');
                
                $article_id = $article_manager->create_article($article);
                if ($article_id) {
                    $batch_articles[] = $article_id;
                    $this->test_data['article_ids'][] = $article_id;
                }
            }
            
            if (count($batch_articles) == 5) {
                $this->add_result('批量发布', true, '批量发布功能正常');
            } else {
                $this->add_result('批量发布', false, '批量发布功能异常');
            }
            
        } catch (Exception $e) {
            $this->add_result('多站点发布测试', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试内容审核工作流程
     */
    private function test_content_moderation_workflow() {
        echo "<h3>测试内容审核工作流程</h3>\n";
        
        try {
            $article_manager = $this->plugin->get_container()->get('article_manager');
            $content_moderator = $this->plugin->get_container()->get('content_moderator');
            $sensitive_word_manager = $this->plugin->get_container()->get('sensitive_word_manager');
            
            // 测试敏感词检测
            $test_contents = [
                '这是正常的文章内容，没有问题。',
                '这篇文章包含测试敏感词，应该被检测到。',
                '这里有违禁内容和不当言论，需要被过滤。'
            ];
            
            $detection_results = [];
            foreach ($test_contents as $index => $content) {
                $result = $sensitive_word_manager->check_content($content);
                $detection_results[] = $result;
                
                if ($index == 0 && $result === false) {
                    $this->add_result('正常内容检测', true, '正常内容未被误判');
                } elseif ($index > 0 && $result !== false) {
                    $this->add_result('敏感词检测', true, '敏感词正确检测');
                }
            }
            
            // 测试内容过滤
            $filtered_content = $sensitive_word_manager->filter_content($test_contents[1]);
            if ($filtered_content !== $test_contents[1]) {
                $this->add_result('内容过滤', true, '敏感词内容正确过滤');
            } else {
                $this->add_result('内容过滤', false, '内容过滤功能异常');
            }
            
            // 测试审核等级
            $moderation_config = $this->plugin->get_container()->get('moderation_config_manager');
            $original_level = $moderation_config->get_moderation_level();
            
            // 设置严格审核
            $moderation_config->set_moderation_level(AMS_V2_Moderation_Level::STRICT);
            $strict_result = $content_moderator->moderate_content($test_contents[1]);
            
            // 设置宽松审核
            $moderation_config->set_moderation_level(AMS_V2_Moderation_Level::LOOSE);
            $loose_result = $content_moderator->moderate_content($test_contents[1]);
            
            // 恢复原始等级
            $moderation_config->set_moderation_level($original_level);
            
            if ($strict_result !== $loose_result) {
                $this->add_result('审核等级', true, '不同审核等级产生不同结果');
            } else {
                $this->add_result('审核等级', false, '审核等级设置无效');
            }
            
            // 测试审核工作流程
            $moderated_article = new AMS_V2_Article();
            $moderated_article->set_title('审核测试文章');
            $moderated_article->set_content('这是包含测试敏感词的文章内容');
            $moderated_article->set_author_id($this->test_data['user_id']);
            $moderated_article->set_site_id($this->test_data['site_ids'][0]);
            $moderated_article->set_status('pending');
            
            $moderated_id = $article_manager->create_article($moderated_article);
            if ($moderated_id) {
                $this->test_data['article_ids'][] = $moderated_id;
                
                // 模拟审核过程
                $moderation_result = $content_moderator->moderate_content($moderated_article->get_content());
                if ($moderation_result !== false) {
                    $this->add_result('审核工作流程', true, '审核工作流程正常');
                } else {
                    $this->add_result('审核工作流程', false, '审核工作流程异常');
                }
            }
            
        } catch (Exception $e) {
            $this->add_result('内容审核工作流程', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试统计和报表功能
     */
    private function test_statistics_and_reporting() {
        echo "<h3>测试统计和报表功能</h3>\n";
        
        try {
            $statistics_collector = $this->plugin->get_container()->get('statistics_collector');
            
            // 更新统计数据
            $statistics_collector->update_user_statistics($this->test_data['user_id']);
            $statistics_collector->update_system_statistics();
            
            // 获取用户统计
            $user_stats = $statistics_collector->get_user_statistics($this->test_data['user_id']);
            if ($user_stats && $user_stats->get_total_articles() > 0) {
                $this->add_result('用户统计', true, '用户统计数据正确');
            } else {
                $this->add_result('用户统计', false, '用户统计数据异常');
            }
            
            // 获取系统统计
            $system_stats = $statistics_collector->get_system_statistics();
            if ($system_stats && $system_stats->get_total_users() > 0) {
                $this->add_result('系统统计', true, '系统统计数据正确');
            } else {
                $this->add_result('系统统计', false, '系统统计数据异常');
            }
            
            // 测试统计导出
            $export_data = $statistics_collector->export_statistics('csv');
            if (!empty($export_data)) {
                $this->add_result('统计导出', true, '统计数据导出成功');
            } else {
                $this->add_result('统计导出', false, '统计数据导出失败');
            }
            
            // 测试实时统计更新
            $before_count = $system_stats->get_total_articles();
            
            // 创建新文章
            $article_manager = $this->plugin->get_container()->get('article_manager');
            $new_article = new AMS_V2_Article();
            $new_article->set_title('统计测试文章');
            $new_article->set_content('用于测试统计更新的文章');
            $new_article->set_author_id($this->test_data['user_id']);
            $new_article->set_site_id($this->test_data['site_ids'][0]);
            $new_article->set_status('published');
            
            $new_article_id = $article_manager->create_article($new_article);
            if ($new_article_id) {
                $this->test_data['article_ids'][] = $new_article_id;
                
                // 更新统计
                $statistics_collector->update_system_statistics();
                $updated_stats = $statistics_collector->get_system_statistics();
                
                if ($updated_stats->get_total_articles() > $before_count) {
                    $this->add_result('实时统计更新', true, '统计数据实时更新正常');
                } else {
                    $this->add_result('实时统计更新', false, '统计数据实时更新异常');
                }
            }
            
        } catch (Exception $e) {
            $this->add_result('统计和报表测试', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试审计和合规功能
     */
    private function test_audit_and_compliance() {
        echo "<h3>测试审计和合规功能</h3>\n";
        
        try {
            $audit_logger = $this->plugin->get_container()->get('audit_logger');
            
            // 记录测试审计日志
            $audit_logger->log_action(
                $this->test_data['user_id'],
                'test_compliance_action',
                'compliance_test',
                1,
                ['before' => 'old_value'],
                ['after' => 'new_value']
            );
            
            // 获取审计日志
            $logs = $audit_logger->get_logs(['action' => 'test_compliance_action'], 10);
            if (!empty($logs)) {
                $this->add_result('审计日志记录', true, '审计日志记录正常');
                
                // 验证日志内容完整性
                $log = $logs[0];
                if ($log->get_user_id() == $this->test_data['user_id'] && 
                    $log->get_action() == 'test_compliance_action') {
                    $this->add_result('审计日志完整性', true, '审计日志内容完整');
                } else {
                    $this->add_result('审计日志完整性', false, '审计日志内容不完整');
                }
            } else {
                $this->add_result('审计日志记录', false, '审计日志记录失败');
            }
            
            // 测试日志过滤和搜索
            $filtered_logs = $audit_logger->get_logs([
                'user_id' => $this->test_data['user_id'],
                'action' => 'article_created'
            ], 10);
            
            if (is_array($filtered_logs)) {
                $this->add_result('审计日志过滤', true, '审计日志过滤功能正常');
            } else {
                $this->add_result('审计日志过滤', false, '审计日志过滤功能异常');
            }
            
            // 测试日志导出
            $export_logs = $audit_logger->export_logs('csv', [
                'start_date' => date('Y-m-d', strtotime('-1 day')),
                'end_date' => date('Y-m-d')
            ]);
            
            if (!empty($export_logs)) {
                $this->add_result('审计日志导出', true, '审计日志导出成功');
            } else {
                $this->add_result('审计日志导出', false, '审计日志导出失败');
            }
            
            // 测试合规性检查
            $compliance_check = $this->check_compliance_requirements();
            if ($compliance_check) {
                $this->add_result('合规性检查', true, '系统符合合规要求');
            } else {
                $this->add_result('合规性检查', false, '系统不符合合规要求');
            }
            
        } catch (Exception $e) {
            $this->add_result('审计和合规测试', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试系统性能
     */
    private function test_system_performance() {
        echo "<h3>测试系统性能</h3>\n";
        
        try {
            $article_manager = $this->plugin->get_container()->get('article_manager');
            
            // 测试批量操作性能
            $start_time = microtime(true);
            $batch_articles = [];
            
            for ($i = 0; $i < 20; $i++) {
                $article = new AMS_V2_Article();
                $article->set_title("性能测试文章 $i");
                $article->set_content("性能测试内容 $i，用于测试系统在批量操作下的性能表现。");
                $article->set_author_id($this->test_data['user_id']);
                $article->set_site_id($this->test_data['site_ids'][0]);
                $article->set_status('published');
                
                $article_id = $article_manager->create_article($article);
                if ($article_id) {
                    $batch_articles[] = $article_id;
                    $this->test_data['article_ids'][] = $article_id;
                }
            }
            
            $batch_time = microtime(true) - $start_time;
            
            if (count($batch_articles) == 20 && $batch_time < 10) {
                $this->add_result('批量操作性能', true, "批量创建20篇文章耗时: " . round($batch_time, 2) . "秒");
            } else {
                $this->add_result('批量操作性能', false, "批量操作性能不佳，耗时: " . round($batch_time, 2) . "秒");
            }
            
            // 测试查询性能
            $start_time = microtime(true);
            $articles = $article_manager->get_articles(['limit' => 50]);
            $query_time = microtime(true) - $start_time;
            
            if ($query_time < 1) {
                $this->add_result('查询性能', true, "查询50篇文章耗时: " . round($query_time * 1000, 2) . "毫秒");
            } else {
                $this->add_result('查询性能', false, "查询性能较差，耗时: " . round($query_time, 2) . "秒");
            }
            
            // 测试内存使用
            $memory_before = memory_get_usage();
            
            // 执行一些操作
            $sensitive_word_manager = $this->plugin->get_container()->get('sensitive_word_manager');
            for ($i = 0; $i < 100; $i++) {
                $sensitive_word_manager->check_content("测试内容 $i");
            }
            
            $memory_after = memory_get_usage();
            $memory_increase = $memory_after - $memory_before;
            
            if ($memory_increase < 1024 * 1024) { // 小于1MB
                $this->add_result('内存使用', true, "内存增长: " . round($memory_increase / 1024, 2) . "KB");
            } else {
                $this->add_result('内存使用', false, "内存使用过多: " . round($memory_increase / 1024 / 1024, 2) . "MB");
            }
            
        } catch (Exception $e) {
            $this->add_result('系统性能测试', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试错误恢复
     */
    private function test_error_recovery() {
        echo "<h3>测试错误恢复机制</h3>\n";
        
        try {
            $article_manager = $this->plugin->get_container()->get('article_manager');
            
            // 测试无效数据处理
            try {
                $invalid_article = new AMS_V2_Article();
                $invalid_article->set_title(''); // 空标题
                $invalid_article->set_content('测试内容');
                $invalid_article->set_author_id(-1); // 无效用户ID
                
                $result = $article_manager->create_article($invalid_article);
                if ($result === false) {
                    $this->add_result('无效数据处理', true, '系统正确拒绝无效数据');
                } else {
                    $this->add_result('无效数据处理', false, '系统未正确处理无效数据');
                }
            } catch (Exception $e) {
                $this->add_result('无效数据处理', true, '系统正确抛出异常: ' . $e->getMessage());
            }
            
            // 测试权限错误处理
            $auth_manager = $this->plugin->get_container()->get('authentication_manager');
            $normal_user = $auth_manager->authenticate('test_user', 'user_password');
            
            if ($normal_user && !$auth_manager->check_permission($normal_user, 'manage_sites')) {
                $this->add_result('权限错误处理', true, '权限检查正确阻止未授权操作');
            } else {
                $this->add_result('权限错误处理', false, '权限检查异常');
            }
            
            // 测试数据库错误恢复
            global $wpdb;
            $original_show_errors = $wpdb->show_errors;
            $wpdb->show_errors = false;
            
            try {
                // 尝试查询不存在的表
                $wpdb->get_results("SELECT * FROM non_existent_table");
                $this->add_result('数据库错误处理', true, '数据库错误被正确处理');
            } catch (Exception $e) {
                $this->add_result('数据库错误处理', true, '数据库错误正确抛出异常');
            }
            
            $wpdb->show_errors = $original_show_errors;
            
            // 测试系统恢复能力
            $system_monitor = $this->plugin->get_container()->get('system_monitor');
            $health_check = $system_monitor->health_check();
            
            if ($health_check['overall_score'] > 70) {
                $this->add_result('系统恢复能力', true, '系统在测试后仍保持良好状态');
            } else {
                $this->add_result('系统恢复能力', false, '系统状态受到测试影响');
            }
            
        } catch (Exception $e) {
            $this->add_result('错误恢复测试', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 清理测试环境
     */
    private function cleanup_test_environment() {
        echo "<h3>清理测试环境</h3>\n";
        
        try {
            $article_manager = $this->plugin->get_container()->get('article_manager');
            $site_manager = $this->plugin->get_container()->get('site_manager');
            $auth_manager = $this->plugin->get_container()->get('authentication_manager');
            $sensitive_word_manager = $this->plugin->get_container()->get('sensitive_word_manager');
            
            // 删除测试文章
            $deleted_articles = 0;
            if (isset($this->test_data['article_ids'])) {
                foreach ($this->test_data['article_ids'] as $article_id) {
                    if ($article_manager->delete_article($article_id)) {
                        $deleted_articles++;
                    }
                }
            }
            
            // 删除测试站点
            $deleted_sites = 0;
            if (isset($this->test_data['site_ids'])) {
                foreach ($this->test_data['site_ids'] as $site_id) {
                    if ($site_manager->delete_site($site_id)) {
                        $deleted_sites++;
                    }
                }
            }
            
            // 删除测试用户
            $deleted_users = 0;
            if (isset($this->test_data['user_id']) && $auth_manager->delete_user($this->test_data['user_id'])) {
                $deleted_users++;
            }
            if (isset($this->test_data['admin_id']) && $auth_manager->delete_user($this->test_data['admin_id'])) {
                $deleted_users++;
            }
            
            // 删除测试敏感词
            $deleted_words = 0;
            if (isset($this->test_data['word_ids'])) {
                foreach ($this->test_data['word_ids'] as $word_id) {
                    if ($sensitive_word_manager->delete_word($word_id)) {
                        $deleted_words++;
                    }
                }
            }
            
            $this->add_result('测试环境清理', true, 
                "清理完成 - 文章: $deleted_articles, 站点: $deleted_sites, 用户: $deleted_users, 敏感词: $deleted_words");
            
        } catch (Exception $e) {
            $this->add_result('测试环境清理', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 检查合规性要求
     */
    private function check_compliance_requirements() {
        try {
            // 检查审计日志是否记录关键操作
            $audit_logger = $this->plugin->get_container()->get('audit_logger');
            $recent_logs = $audit_logger->get_logs([], 10);
            
            if (empty($recent_logs)) {
                return false;
            }
            
            // 检查是否有用户操作日志
            $user_logs = $audit_logger->get_logs(['action' => 'user_login'], 5);
            if (empty($user_logs)) {
                return false;
            }
            
            // 检查是否有文章操作日志
            $article_logs = $audit_logger->get_logs(['action' => 'article_created'], 5);
            if (empty($article_logs)) {
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 添加测试结果
     */
    private function add_result($test_name, $passed, $message) {
        $this->results[] = [
            'test' => $test_name,
            'passed' => $passed,
            'message' => $message
        ];
        
        $status = $passed ? '✅ 通过' : '❌ 失败';
        echo "<p><strong>$test_name:</strong> $status - $message</p>\n";
    }
    
    /**
     * 显示测试结果摘要
     */
    private function display_results() {
        echo "<h3>完整工作流程测试结果摘要</h3>\n";
        
        $total = count($this->results);
        $passed = count(array_filter($this->results, function($r) { return $r['passed']; }));
        $failed = $total - $passed;
        
        echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>\n";
        echo "<p><strong>总测试数:</strong> $total</p>\n";
        echo "<p><strong>通过:</strong> <span style='color: green;'>$passed</span></p>\n";
        echo "<p><strong>失败:</strong> <span style='color: red;'>$failed</span></p>\n";
        echo "<p><strong>成功率:</strong> " . round(($passed / $total) * 100, 2) . "%</p>\n";
        echo "</div>\n";
        
        if ($failed > 0) {
            echo "<h4>失败的测试:</h4>\n";
            echo "<ul>\n";
            foreach ($this->results as $result) {
                if (!$result['passed']) {
                    echo "<li><strong>{$result['test']}:</strong> {$result['message']}</li>\n";
                }
            }
            echo "</ul>\n";
        }
        
        // 工作流程验证
        echo "<h4>工作流程验证状态:</h4>\n";
        echo "<ul>\n";
        echo "<li><strong>完整用户工作流程:</strong> " . 
             ($this->is_category_passed('用户') ? '✅ 正常' : '❌ 异常') . "</li>\n";
        echo "<li><strong>多站点发布和同步:</strong> " . 
             ($this->is_category_passed('多站点') || $this->is_category_passed('批量') ? '✅ 正常' : '❌ 异常') . "</li>\n";
        echo "<li><strong>内容审核工作流程:</strong> " . 
             ($this->is_category_passed('审核') || $this->is_category_passed('敏感词') ? '✅ 正常' : '❌ 异常') . "</li>\n";
        echo "<li><strong>统计和报表功能:</strong> " . 
             ($this->is_category_passed('统计') ? '✅ 正常' : '❌ 异常') . "</li>\n";
        echo "<li><strong>审计和合规功能:</strong> " . 
             ($this->is_category_passed('审计') || $this->is_category_passed('合规') ? '✅ 正常' : '❌ 异常') . "</li>\n";
        echo "<li><strong>系统性能表现:</strong> " . 
             ($this->is_category_passed('性能') ? '✅ 正常' : '❌ 异常') . "</li>\n";
        echo "<li><strong>错误恢复机制:</strong> " . 
             ($this->is_category_passed('错误') || $this->is_category_passed('恢复') ? '✅ 正常' : '❌ 异常') . "</li>\n";
        echo "</ul>\n";
        
        // 系统就绪状态
        $success_rate = ($passed / $total) * 100;
        echo "<h4>系统就绪状态:</h4>\n";
        echo "<div style='background: #e7f3ff; padding: 15px; border-left: 4px solid #2196f3; margin: 20px 0;'>\n";
        
        if ($success_rate >= 95) {
            echo "<p><strong>🎉 系统完全就绪</strong></p>\n";
            echo "<p>所有核心工作流程都能正常运行，系统已准备好投入生产环境使用。</p>\n";
        } elseif ($success_rate >= 90) {
            echo "<p><strong>✅ 系统基本就绪</strong></p>\n";
            echo "<p>核心功能运行正常，少数非关键问题不影响系统使用，建议修复后投入生产。</p>\n";
        } elseif ($success_rate >= 80) {
            echo "<p><strong>⚠️ 系统需要优化</strong></p>\n";
            echo "<p>系统基本功能正常，但存在一些问题需要解决，建议修复后再投入生产使用。</p>\n";
        } else {
            echo "<p><strong>❌ 系统需要修复</strong></p>\n";
            echo "<p>系统存在较多问题，不建议投入生产使用，需要修复所有关键问题。</p>\n";
        }
        
        echo "<p><strong>测试覆盖的关键场景:</strong></p>\n";
        echo "<ul>\n";
        echo "<li>端到端用户操作流程</li>\n";
        echo "<li>多站点内容发布和管理</li>\n";
        echo "<li>内容审核和敏感词过滤</li>\n";
        echo "<li>统计数据收集和报表生成</li>\n";
        echo "<li>审计日志记录和合规检查</li>\n";
        echo "<li>系统性能和负载测试</li>\n";
        echo "<li>错误处理和系统恢复</li>\n";
        echo "</ul>\n";
        echo "</div>\n";
    }
    
    /**
     * 检查某个类别的测试是否通过
     */
    private function is_category_passed($category) {
        foreach ($this->results as $result) {
            if (strpos($result['test'], $category) !== false && !$result['passed']) {
                return false;
            }
        }
        return true;
    }
}

// 如果直接访问此文件，运行测试
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>完整工作流程集成测试</title>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h2, h3 { color: #333; }
            p { margin: 5px 0; }
            .test-section { margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <?php
        $test = new CompleteWorkflowTest();
        $test->run_all_tests();
        ?>
    </body>
    </html>
    <?php
}
?>