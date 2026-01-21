<?php
/**
 * 清理系统测试
 *
 * @package Article_Management_V2
 * @subpackage Tests
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 清理系统测试类
 */
class AMS_V2_Cleanup_System_Test {

    /**
     * 依赖注入容器
     *
     * @var AMS_V2_Container
     */
    private $container;

    /**
     * 清理调度器
     *
     * @var AMS_V2_Cleanup_Scheduler
     */
    private $cleanup_scheduler;

    /**
     * 文章管理器
     *
     * @var AMS_V2_Article_Manager
     */
    private $article_manager;

    /**
     * 认证管理器
     *
     * @var AMS_V2_Authentication_Manager
     */
    private $auth_manager;

    /**
     * 测试结果
     *
     * @var array
     */
    private $results = array();

    /**
     * 构造函数
     *
     * @param AMS_V2_Container $container 依赖注入容器
     */
    public function __construct($container) {
        $this->container = $container;
        $this->cleanup_scheduler = $container->get('cleanup_scheduler');
        $this->article_manager = $container->get('article_manager');
        $this->auth_manager = $container->get('authentication_manager');
    }

    /**
     * 运行所有测试
     *
     * @return array 测试结果
     */
    public function run_all_tests() {
        $this->results = array();

        // 基础功能测试
        $this->test_threshold_settings();
        $this->test_auto_cleanup_toggle();
        $this->test_article_identification();
        $this->test_cleanup_execution();
        $this->test_cleanup_logging();
        $this->test_cleanup_statistics();
        $this->test_cron_scheduling();

        return $this->results;
    }

    /**
     * 测试阈值设置
     */
    private function test_threshold_settings() {
        $test_name = '清理阈值设置测试';
        
        try {
            // 测试设置有效阈值
            $result1 = $this->cleanup_scheduler->set_threshold_days(15);
            $this->assert_true($result1, '设置有效阈值应该成功');
            
            $threshold = $this->cleanup_scheduler->get_threshold_days();
            $this->assert_equals(15, $threshold, '获取的阈值应该等于设置的值');
            
            // 测试设置无效阈值
            $result2 = $this->cleanup_scheduler->set_threshold_days(0);
            $this->assert_false($result2, '设置无效阈值应该失败');
            
            $result3 = $this->cleanup_scheduler->set_threshold_days(-5);
            $this->assert_false($result3, '设置负数阈值应该失败');
            
            // 恢复默认值
            $this->cleanup_scheduler->set_threshold_days(30);
            
            $this->add_result($test_name, true, '阈值设置功能正常');
            
        } catch (Exception $e) {
            $this->add_result($test_name, false, '测试异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试自动清理开关
     */
    private function test_auto_cleanup_toggle() {
        $test_name = '自动清理开关测试';
        
        try {
            // 测试启用自动清理
            $result1 = $this->cleanup_scheduler->enable_auto_cleanup();
            $this->assert_true($result1, '启用自动清理应该成功');
            
            $enabled = $this->cleanup_scheduler->is_auto_cleanup_enabled();
            $this->assert_true($enabled, '自动清理应该处于启用状态');
            
            // 测试禁用自动清理
            $result2 = $this->cleanup_scheduler->disable_auto_cleanup();
            $this->assert_true($result2, '禁用自动清理应该成功');
            
            $disabled = $this->cleanup_scheduler->is_auto_cleanup_enabled();
            $this->assert_false($disabled, '自动清理应该处于禁用状态');
            
            $this->add_result($test_name, true, '自动清理开关功能正常');
            
        } catch (Exception $e) {
            $this->add_result($test_name, false, '测试异常: ' . $e->getMessage());
        }
    }
    /**
     * 测试文章识别功能
     */
    private function test_article_identification() {
        $test_name = '文章识别功能测试';
        
        try {
            // 创建测试用户
            $admin = $this->create_test_admin();
            
            // 创建测试文章
            $old_article = $this->create_test_article($admin, 'draft', 35); // 35天前
            $recent_article = $this->create_test_article($admin, 'draft', 5); // 5天前
            $published_article = $this->create_test_article($admin, 'published', 35); // 已发布不应被清理
            
            // 设置30天阈值
            $this->cleanup_scheduler->set_threshold_days(30);
            
            // 识别需要清理的文章
            $article_ids = $this->cleanup_scheduler->identify_articles_for_cleanup();
            
            $this->assert_true(in_array($old_article->get_id(), $article_ids), '超过阈值的草稿文章应该被识别');
            $this->assert_false(in_array($recent_article->get_id(), $article_ids), '未超过阈值的文章不应该被识别');
            $this->assert_false(in_array($published_article->get_id(), $article_ids), '已发布的文章不应该被识别');
            
            // 清理测试数据
            $this->cleanup_test_articles(array($old_article, $recent_article, $published_article));
            
            $this->add_result($test_name, true, '文章识别功能正常');
            
        } catch (Exception $e) {
            $this->add_result($test_name, false, '测试异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试清理执行功能
     */
    private function test_cleanup_execution() {
        $test_name = '清理执行功能测试';
        
        try {
            // 创建测试用户
            $admin = $this->create_test_admin();
            
            // 创建测试文章
            $article1 = $this->create_test_article($admin, 'draft', 35);
            $article2 = $this->create_test_article($admin, 'rejected', 40);
            
            $article_ids = array($article1->get_id(), $article2->get_id());
            
            // 执行清理
            $result = $this->cleanup_scheduler->execute_cleanup($article_ids, $admin, 'manual');
            
            $this->assert_true($result['success'], '清理执行应该成功');
            $this->assert_equals(2, $result['deleted_count'], '应该删除2篇文章');
            $this->assert_equals(0, $result['failed_count'], '不应该有失败的删除');
            
            // 验证文章已被删除
            $deleted_article1 = $this->article_manager->get_article_by_id($article1->get_id());
            $deleted_article2 = $this->article_manager->get_article_by_id($article2->get_id());
            
            $this->assert_null($deleted_article1, '文章1应该已被删除');
            $this->assert_null($deleted_article2, '文章2应该已被删除');
            
            $this->add_result($test_name, true, '清理执行功能正常');
            
        } catch (Exception $e) {
            $this->add_result($test_name, false, '测试异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试清理日志功能
     */
    private function test_cleanup_logging() {
        $test_name = '清理日志功能测试';
        
        try {
            // 获取清理前的日志数量
            $logs_before = $this->cleanup_scheduler->get_cleanup_logs();
            $count_before = $logs_before['total'];
            
            // 创建测试用户和文章
            $admin = $this->create_test_admin();
            $article = $this->create_test_article($admin, 'draft', 35);
            
            // 执行清理
            $result = $this->cleanup_scheduler->execute_cleanup(array($article->get_id()), $admin, 'manual');
            
            // 获取清理后的日志
            $logs_after = $this->cleanup_scheduler->get_cleanup_logs();
            $count_after = $logs_after['total'];
            
            $this->assert_equals($count_before + 1, $count_after, '应该新增一条清理日志');
            
            // 检查最新日志的内容
            if (!empty($logs_after['logs'])) {
                $latest_log = $logs_after['logs'][0];
                $this->assert_equals('manual', $latest_log->cleanup_type, '日志类型应该是manual');
                $this->assert_equals('completed', $latest_log->status, '日志状态应该是completed');
                $this->assert_equals(1, $latest_log->articles_deleted, '删除数量应该是1');
            }
            
            $this->add_result($test_name, true, '清理日志功能正常');
            
        } catch (Exception $e) {
            $this->add_result($test_name, false, '测试异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试清理统计功能
     */
    private function test_cleanup_statistics() {
        $test_name = '清理统计功能测试';
        
        try {
            // 获取统计信息
            $stats = $this->cleanup_scheduler->get_cleanup_statistics(30);
            
            $this->assert_true(isset($stats['total_stats']), '应该包含总体统计');
            $this->assert_true(isset($stats['type_stats']), '应该包含类型统计');
            $this->assert_true(isset($stats['daily_stats']), '应该包含日期统计');
            $this->assert_equals(30, $stats['period_days'], '统计周期应该是30天');
            
            $this->add_result($test_name, true, '清理统计功能正常');
            
        } catch (Exception $e) {
            $this->add_result($test_name, false, '测试异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试定时任务调度
     */
    private function test_cron_scheduling() {
        $test_name = '定时任务调度测试';
        
        try {
            // 测试调度任务
            $result1 = $this->cleanup_scheduler->schedule_cleanup_task();
            $this->assert_true($result1, '调度任务应该成功');
            
            // 检查任务是否已调度
            $next_run = wp_next_scheduled('ams_v2_auto_cleanup');
            $this->assert_true($next_run !== false, '应该有已调度的清理任务');
            
            // 测试取消任务
            $result2 = $this->cleanup_scheduler->unschedule_cleanup_task();
            $this->assert_true($result2, '取消任务应该成功');
            
            // 检查任务是否已取消
            $next_run_after = wp_next_scheduled('ams_v2_auto_cleanup');
            $this->assert_false($next_run_after, '清理任务应该已被取消');
            
            $this->add_result($test_name, true, '定时任务调度功能正常');
            
        } catch (Exception $e) {
            $this->add_result($test_name, false, '测试异常: ' . $e->getMessage());
        }
    }
    /**
     * 创建测试管理员用户
     *
     * @return AMS_V2_User
     */
    private function create_test_admin() {
        $user_data = array(
            'username' => 'test_admin_' . time(),
            'email' => 'test_admin_' . time() . '@example.com',
            'password' => 'test_password',
            'display_name' => 'Test Admin',
            'role' => 'administrator'
        );

        return $this->auth_manager->create_user($user_data);
    }

    /**
     * 创建测试文章
     *
     * @param AMS_V2_User $author 作者
     * @param string $status 状态
     * @param int $days_ago 创建于几天前
     * @return AMS_V2_Article
     */
    private function create_test_article($author, $status, $days_ago) {
        $article_data = array(
            'title' => 'Test Article ' . time() . rand(1000, 9999),
            'content' => 'This is a test article content.',
            'status' => $status,
            'author_id' => $author->get_id()
        );

        $article = $this->article_manager->create_article($article_data, $author);
        
        // 修改创建时间
        if ($days_ago > 0) {
            global $wpdb;
            $articles_table = $this->container->get('database_manager')->get_table_name('articles');
            $created_at = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
            
            $wpdb->update(
                $articles_table,
                array('created_at' => $created_at),
                array('id' => $article->get_id()),
                array('%s'),
                array('%d')
            );
            
            // 重新加载文章以获取更新的时间
            $article = $this->article_manager->get_article_by_id($article->get_id());
        }

        return $article;
    }

    /**
     * 清理测试文章
     *
     * @param array $articles 文章数组
     */
    private function cleanup_test_articles($articles) {
        foreach ($articles as $article) {
            try {
                $this->article_manager->delete_article($article);
            } catch (Exception $e) {
                // 忽略删除错误
            }
        }
    }

    /**
     * 断言为真
     *
     * @param mixed $value 值
     * @param string $message 消息
     */
    private function assert_true($value, $message) {
        if (!$value) {
            throw new Exception("断言失败: {$message}");
        }
    }

    /**
     * 断言为假
     *
     * @param mixed $value 值
     * @param string $message 消息
     */
    private function assert_false($value, $message) {
        if ($value) {
            throw new Exception("断言失败: {$message}");
        }
    }

    /**
     * 断言相等
     *
     * @param mixed $expected 期望值
     * @param mixed $actual 实际值
     * @param string $message 消息
     */
    private function assert_equals($expected, $actual, $message) {
        if ($expected !== $actual) {
            throw new Exception("断言失败: {$message}. 期望: {$expected}, 实际: {$actual}");
        }
    }

    /**
     * 断言为空
     *
     * @param mixed $value 值
     * @param string $message 消息
     */
    private function assert_null($value, $message) {
        if ($value !== null) {
            throw new Exception("断言失败: {$message}");
        }
    }

    /**
     * 添加测试结果
     *
     * @param string $test_name 测试名称
     * @param bool $success 是否成功
     * @param string $message 消息
     */
    private function add_result($test_name, $success, $message) {
        $this->results[] = array(
            'test' => $test_name,
            'success' => $success,
            'message' => $message,
            'timestamp' => current_time('mysql')
        );
    }

    /**
     * 输出测试结果
     *
     * @param array $results 测试结果
     */
    public function output_results($results) {
        $total_tests = count($results);
        $passed_tests = count(array_filter($results, function($result) {
            return $result['success'];
        }));
        $failed_tests = $total_tests - $passed_tests;

        echo '<div class="ams-v2-test-results">';
        echo '<h2>清理系统测试结果</h2>';
        
        echo '<div class="ams-v2-test-summary">';
        echo '<p><strong>总测试数:</strong> ' . $total_tests . '</p>';
        echo '<p><strong>通过:</strong> <span class="success">' . $passed_tests . '</span></p>';
        echo '<p><strong>失败:</strong> <span class="error">' . $failed_tests . '</span></p>';
        echo '<p><strong>成功率:</strong> ' . ($total_tests > 0 ? round(($passed_tests / $total_tests) * 100, 2) : 0) . '%</p>';
        echo '</div>';

        echo '<div class="ams-v2-test-details">';
        foreach ($results as $result) {
            $status_class = $result['success'] ? 'success' : 'error';
            $status_text = $result['success'] ? '✓ 通过' : '✗ 失败';
            
            echo '<div class="ams-v2-test-item ' . $status_class . '">';
            echo '<h4>' . esc_html($result['test']) . ' <span class="status">' . $status_text . '</span></h4>';
            echo '<p>' . esc_html($result['message']) . '</p>';
            echo '<small>时间: ' . esc_html($result['timestamp']) . '</small>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';

        // 添加样式
        echo '<style>
        .ams-v2-test-results {
            max-width: 800px;
            margin: 20px 0;
        }
        .ams-v2-test-summary {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .ams-v2-test-item {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            border-left: 4px solid;
        }
        .ams-v2-test-item.success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .ams-v2-test-item.error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .ams-v2-test-item h4 {
            margin: 0 0 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ams-v2-test-item .status {
            font-size: 14px;
            font-weight: normal;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        </style>';
    }
}