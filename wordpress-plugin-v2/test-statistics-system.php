<?php
/**
 * 统计系统测试
 *
 * @package Article_Management_V2
 * @subpackage Tests
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 统计系统测试类
 */
class AMS_V2_Statistics_System_Test {

    /**
     * 依赖注入容器
     *
     * @var AMS_V2_Container
     */
    private $container;

    /**
     * 统计收集器
     *
     * @var AMS_V2_Statistics_Collector
     */
    private $statistics_collector;

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
        $this->statistics_collector = $container->get('statistics_collector');
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
        $this->test_user_statistics_collection();
        $this->test_system_statistics_collection();
        $this->test_moderation_statistics();
        $this->test_realtime_statistics();
        $this->test_user_publish_stats();
        $this->test_statistics_caching();
        $this->test_csv_export();

        return $this->results;
    }

    /**
     * 测试用户统计收集
     */
    private function test_user_statistics_collection() {
        $test_name = '用户统计收集测试';
        
        try {
            // 创建测试用户和文章
            $user = $this->create_test_user();
            $article1 = $this->create_test_article($user, 'published', 1);
            $article2 = $this->create_test_article($user, 'approved', 2);
            $article3 = $this->create_test_article($user, 'rejected', 3);
            
            // 收集用户统计
            $stats = $this->statistics_collector->collect_user_statistics(
                $user->get_id(), 
                'daily', 
                date('Y-m-d', strtotime('-7 days')), 
                date('Y-m-d')
            );
            
            $this->assert_true(is_array($stats), '应该返回数组格式的统计数据');
            
            if (!empty($stats)) {
                $user_stat = $stats[0];
                $this->assert_true(isset($user_stat['user_id']), '统计数据应该包含用户ID');
                $this->assert_true(isset($user_stat['total_submissions']), '统计数据应该包含总提交数');
                $this->assert_true(isset($user_stat['approved_count']), '统计数据应该包含审核通过数');
                $this->assert_true(isset($user_stat['published_count']), '统计数据应该包含发布数');
                $this->assert_equals($user->get_id(), $user_stat['user_id'], '用户ID应该匹配');
            }
            
            // 清理测试数据
            $this->cleanup_test_data(array($article1, $article2, $article3), array($user));
            
            $this->add_result($test_name, true, '用户统计收集功能正常');
            
        } catch (Exception $e) {
            $this->add_result($test_name, false, '测试异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试系统统计收集
     */
    private function test_system_statistics_collection() {
        $test_name = '系统统计收集测试';
        
        try {
            // 收集系统统计
            $stats = $this->statistics_collector->collect_system_statistics(
                'daily', 
                date('Y-m-d', strtotime('-7 days')), 
                date('Y-m-d')
            );
            
            $this->assert_true(is_array($stats), '应该返回数组格式的统计数据');
            
            if (!empty($stats)) {
                $system_stat = $stats[0];
                $this->assert_true(isset($system_stat['date']), '统计数据应该包含日期');
                $this->assert_true(isset($system_stat['active_users']), '统计数据应该包含活跃用户数');
                $this->assert_true(isset($system_stat['new_articles']), '统计数据应该包含新增文章数');
                $this->assert_true(isset($system_stat['published_articles']), '统计数据应该包含发布文章数');
                $this->assert_true(isset($system_stat['publish_rate']), '统计数据应该包含发布率');
            }
            
            $this->add_result($test_name, true, '系统统计收集功能正常');
            
        } catch (Exception $e) {
            $this->add_result($test_name, false, '测试异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试审核统计
     */
    private function test_moderation_statistics() {
        $test_name = '审核统计测试';
        
        try {
            // 收集审核统计
            $stats = $this->statistics_collector->get_moderation_statistics(
                'daily', 
                date('Y-m-d', strtotime('-7 days')), 
                date('Y-m-d')
            );
            
            $this->assert_true(is_array($stats), '应该返回数组格式的审核统计');
            
            // 测试不同周期的统计
            $weekly_stats = $this->statistics_collector->get_moderation_statistics('weekly');
            $this->assert_true(is_array($weekly_stats), '周统计应该正常工作');
            
            $monthly_stats = $this->statistics_collector->get_moderation_statistics('monthly');
            $this->assert_true(is_array($monthly_stats), '月统计应该正常工作');
            
            $this->add_result($test_name, true, '审核统计功能正常');
            
        } catch (Exception $e) {
            $this->add_result($test_name, false, '测试异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试实时统计
     */
    private function test_realtime_statistics() {
        $test_name = '实时统计测试';
        
        try {
            // 获取实时统计
            $stats = $this->statistics_collector->get_realtime_statistics();
            
            $this->assert_true(is_array($stats), '应该返回数组格式的实时统计');
            $this->assert_true(isset($stats['today']), '应该包含今日统计');
            $this->assert_true(isset($stats['totals']), '应该包含总体统计');
            $this->assert_true(isset($stats['recent_activity']), '应该包含最近活动');
            
            // 验证今日统计结构
            if (isset($stats['today'])) {
                $today_stats = $stats['today'];
                $this->assert_true(isset($today_stats['new_articles']), '今日统计应该包含新增文章数');
                $this->assert_true(isset($today_stats['published_articles']), '今日统计应该包含发布文章数');
                $this->assert_true(isset($today_stats['active_users']), '今日统计应该包含活跃用户数');
            }
            
            // 验证总体统计结构
            if (isset($stats['totals'])) {
                $totals = $stats['totals'];
                $this->assert_true(isset($totals['total_articles']), '总体统计应该包含总文章数');
                $this->assert_true(isset($totals['total_users']), '总体统计应该包含总用户数');
            }
            
            $this->add_result($test_name, true, '实时统计功能正常');
            
        } catch (Exception $e) {
            $this->add_result($test_name, false, '测试异常: ' . $e->getMessage());
        }
    }
    /**
     * 测试用户发布统计
     */
    private function test_user_publish_stats() {
        $test_name = '用户发布统计测试';
        
        try {
            // 创建测试用户
            $user = $this->create_test_user();
            
            // 获取用户发布统计
            $stats = $this->statistics_collector->get_user_publish_stats($user->get_id(), 'daily', 30);
            
            $this->assert_true(is_array($stats), '应该返回数组格式的发布统计');
            
            // 测试不同周期
            $weekly_stats = $this->statistics_collector->get_user_publish_stats($user->get_id(), 'weekly', 12);
            $this->assert_true(is_array($weekly_stats), '周发布统计应该正常工作');
            
            // 清理测试数据
            $this->cleanup_test_data(array(), array($user));
            
            $this->add_result($test_name, true, '用户发布统计功能正常');
            
        } catch (Exception $e) {
            $this->add_result($test_name, false, '测试异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试统计缓存
     */
    private function test_statistics_caching() {
        $test_name = '统计缓存测试';
        
        try {
            $cache_key = 'test_cache_key';
            $test_data = array('test' => 'data', 'timestamp' => time());
            
            // 测试缓存设置
            $result = $this->statistics_collector->update_statistics_cache($cache_key, $test_data, 300);
            $this->assert_true($result, '缓存设置应该成功');
            
            // 测试缓存获取
            $cached_data = $this->statistics_collector->get_statistics_cache($cache_key);
            $this->assert_true($cached_data !== false, '应该能够获取缓存数据');
            $this->assert_equals($test_data['test'], $cached_data['test'], '缓存数据应该匹配');
            
            // 测试缓存清除
            $clear_result = $this->statistics_collector->clear_statistics_cache();
            $this->assert_true($clear_result, '缓存清除应该成功');
            
            $this->add_result($test_name, true, '统计缓存功能正常');
            
        } catch (Exception $e) {
            $this->add_result($test_name, false, '测试异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试CSV导出
     */
    private function test_csv_export() {
        $test_name = 'CSV导出测试';
        
        try {
            // 准备测试数据
            $test_data = array(
                array('date' => '2024-01-01', 'articles' => 10, 'users' => 5),
                array('date' => '2024-01-02', 'articles' => 15, 'users' => 8),
                array('date' => '2024-01-03', 'articles' => 12, 'users' => 6)
            );
            
            // 测试导出功能（不实际输出文件）
            ob_start();
            $result = $this->statistics_collector->export_statistics_csv($test_data, 'test_export.csv');
            $output = ob_get_clean();
            
            // 验证输出包含CSV内容
            $this->assert_true(!empty($output), 'CSV导出应该产生输出');
            $this->assert_true(strpos($output, '2024-01-01') !== false, 'CSV应该包含测试数据');
            
            $this->add_result($test_name, true, 'CSV导出功能正常');
            
        } catch (Exception $e) {
            $this->add_result($test_name, false, '测试异常: ' . $e->getMessage());
        }
    }

    /**
     * 创建测试用户
     *
     * @return AMS_V2_User
     */
    private function create_test_user() {
        $user_data = array(
            'username' => 'test_stats_user_' . time(),
            'email' => 'test_stats_' . time() . '@example.com',
            'password' => 'test_password',
            'display_name' => 'Test Stats User',
            'role' => 'user'
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
            'title' => 'Test Stats Article ' . time() . rand(1000, 9999),
            'content' => 'This is a test article for statistics.',
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
            
            // 如果是已审核状态，也设置审核时间
            if (in_array($status, array('approved', 'rejected', 'published'))) {
                $moderated_at = date('Y-m-d H:i:s', strtotime("-" . ($days_ago - 1) . " days"));
                $wpdb->update(
                    $articles_table,
                    array('moderated_at' => $moderated_at),
                    array('id' => $article->get_id()),
                    array('%s'),
                    array('%d')
                );
            }
            
            // 重新加载文章
            $article = $this->article_manager->get_article_by_id($article->get_id());
        }

        return $article;
    }

    /**
     * 清理测试数据
     *
     * @param array $articles 文章数组
     * @param array $users 用户数组
     */
    private function cleanup_test_data($articles = array(), $users = array()) {
        // 清理文章
        foreach ($articles as $article) {
            try {
                $this->article_manager->delete_article($article);
            } catch (Exception $e) {
                // 忽略删除错误
            }
        }
        
        // 清理用户
        foreach ($users as $user) {
            try {
                $this->auth_manager->delete_user($user->get_id());
            } catch (Exception $e) {
                // 忽略删除错误
            }
        }
    }

    /**
     * 断言为真
     */
    private function assert_true($value, $message) {
        if (!$value) {
            throw new Exception("断言失败: {$message}");
        }
    }

    /**
     * 断言为假
     */
    private function assert_false($value, $message) {
        if ($value) {
            throw new Exception("断言失败: {$message}");
        }
    }

    /**
     * 断言相等
     */
    private function assert_equals($expected, $actual, $message) {
        if ($expected !== $actual) {
            throw new Exception("断言失败: {$message}. 期望: {$expected}, 实际: {$actual}");
        }
    }

    /**
     * 添加测试结果
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
     */
    public function output_results($results) {
        $total_tests = count($results);
        $passed_tests = count(array_filter($results, function($result) {
            return $result['success'];
        }));
        $failed_tests = $total_tests - $passed_tests;

        echo '<div class="ams-v2-test-results">';
        echo '<h2>统计系统测试结果</h2>';
        
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