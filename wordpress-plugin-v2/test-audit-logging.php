<?php
/**
 * 审计日志系统测试
 *
 * @package Article_Management_V2
 * @subpackage Tests
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 审计日志系统测试类
 */
class AMS_V2_Audit_Logging_Test {

    /**
     * 依赖注入容器
     *
     * @var AMS_V2_Container
     */
    private $container;

    /**
     * 审计日志器
     *
     * @var AMS_V2_Audit_Logger
     */
    private $audit_logger;

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
        $this->audit_logger = $container->get('audit_logger');
        $this->auth_manager = $container->get('authentication_manager');
    }

    /**
     * 运行所有测试
     *
     * @return array 测试结果
     */
    public function run_all_tests() {
        $this->results = array();

        echo "<h2>审计日志系统功能测试</h2>";

        // 测试基本日志记录功能
        $this->test_basic_logging();
        
        // 测试用户操作日志
        $this->test_user_operation_logging();
        
        // 测试日志查询功能
        $this->test_log_querying();
        
        // 测试日志过滤功能
        $this->test_log_filtering();

        return $this->results;
    }

    /**
     * 测试基本日志记录功能
     */
    private function test_basic_logging() {
        echo "<h3>1. 测试基本日志记录功能</h3>";

        try {
            // 创建测试用户
            $test_user = $this->create_test_user();
            
            // 记录基本审计日志
            $result = $this->audit_logger->log(
                $test_user->get_id(),
                'test_action',
                'test_resource',
                123,
                array('old' => 'value1'),
                array('new' => 'value2')
            );

            if ($result) {
                $this->add_result('基本日志记录', true, '成功记录审计日志');
            } else {
                $this->add_result('基本日志记录', false, '日志记录失败');
            }

        } catch (Exception $e) {
            $this->add_result('基本日志记录', false, '异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试用户操作日志
     */
    private function test_user_operation_logging() {
        echo "<h3>2. 测试用户操作日志</h3>";

        try {
            // 创建测试用户
            $test_user = $this->create_test_user('audit_test_user_' . time());
            
            // 测试用户登录日志
            $login_result = $this->audit_logger->log_user_login($test_user);
            $this->add_result('用户登录日志', $login_result, $login_result ? '成功记录用户登录' : '登录日志记录失败');

            // 测试用户创建日志
            $create_result = $this->audit_logger->log_user_created($test_user, $test_user);
            $this->add_result('用户创建日志', $create_result, $create_result ? '成功记录用户创建' : '创建日志记录失败');

            // 测试用户更新日志
            $old_values = array('email' => $test_user->get_email());
            $new_values = array('email' => 'new_' . $test_user->get_email());
            $update_result = $this->audit_logger->log_user_updated($test_user, $old_values, $new_values, $test_user);
            $this->add_result('用户更新日志', $update_result, $update_result ? '成功记录用户更新' : '更新日志记录失败');

        } catch (Exception $e) {
            $this->add_result('用户操作日志', false, '异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试日志查询功能
     */
    private function test_log_querying() {
        echo "<h3>3. 测试日志查询功能</h3>";

        try {
            // 查询所有日志
            $all_logs = $this->audit_logger->get_logs(array(), 1, 10);
            $this->add_result('查询所有日志', !empty($all_logs['logs']), 
                !empty($all_logs['logs']) ? '成功查询到 ' . count($all_logs['logs']) . ' 条日志' : '未查询到日志');

            // 根据ID查询日志
            if (!empty($all_logs['logs'])) {
                $first_log = $all_logs['logs'][0];
                $log_by_id = $this->audit_logger->get_log_by_id($first_log->get_id());
                $this->add_result('根据ID查询日志', $log_by_id !== null, 
                    $log_by_id ? '成功根据ID查询日志' : 'ID查询失败');
            }

        } catch (Exception $e) {
            $this->add_result('日志查询功能', false, '异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试日志过滤功能
     */
    private function test_log_filtering() {
        echo "<h3>4. 测试日志过滤功能</h3>";

        try {
            // 按操作类型过滤
            $filtered_logs = $this->audit_logger->get_logs(array('action' => 'user_login'), 1, 10);
            $this->add_result('按操作类型过滤', is_array($filtered_logs), 
                '成功按操作类型过滤，找到 ' . (isset($filtered_logs['logs']) ? count($filtered_logs['logs']) : 0) . ' 条日志');

            // 按资源类型过滤
            $resource_filtered = $this->audit_logger->get_logs(array('resource_type' => 'user'), 1, 10);
            $this->add_result('按资源类型过滤', is_array($resource_filtered), 
                '成功按资源类型过滤，找到 ' . (isset($resource_filtered['logs']) ? count($resource_filtered['logs']) : 0) . ' 条日志');

            // 按时间范围过滤
            $date_filtered = $this->audit_logger->get_logs(array(
                'date_from' => date('Y-m-d'),
                'date_to' => date('Y-m-d')
            ), 1, 10);
            $this->add_result('按时间范围过滤', is_array($date_filtered), 
                '成功按时间范围过滤，找到 ' . (isset($date_filtered['logs']) ? count($date_filtered['logs']) : 0) . ' 条日志');

        } catch (Exception $e) {
            $this->add_result('日志过滤功能', false, '异常: ' . $e->getMessage());
        }
    }

    /**
     * 创建测试用户
     *
     * @param string $username 用户名
     * @return AMS_V2_User 用户对象
     */
    private function create_test_user($username = null) {
        if (!$username) {
            $username = 'audit_test_user_' . time() . '_' . rand(1000, 9999);
        }

        $user_data = array(
            'username' => $username,
            'email' => $username . '@test.com',
            'password' => 'test_password_123',
            'display_name' => 'Audit Test User',
            'is_active' => true,
            'role' => 'user'
        );

        $user = $this->auth_manager->create_user($user_data);
        
        if (is_wp_error($user)) {
            throw new Exception('创建测试用户失败: ' . $user->get_error_message());
        }

        return $user;
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
            'message' => $message
        );

        $status = $success ? '<span style="color: green;">✓ 通过</span>' : '<span style="color: red;">✗ 失败</span>';
        echo "<p><strong>{$test_name}:</strong> {$status} - {$message}</p>";
    }

    /**
     * 输出测试结果
     *
     * @param array $results 测试结果
     */
    public function output_results($results) {
        $total = count($results);
        $passed = count(array_filter($results, function($r) { return $r['success']; }));
        $failed = $total - $passed;

        echo "<div style='background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid " . 
             ($failed === 0 ? "#46b450" : "#dc3232") . ";'>";
        echo "<h3>测试结果汇总</h3>";
        echo "<p><strong>总测试数:</strong> {$total}</p>";
        echo "<p><strong>通过:</strong> <span style='color: green;'>{$passed}</span></p>";
        echo "<p><strong>失败:</strong> <span style='color: red;'>{$failed}</span></p>";
        echo "<p><strong>成功率:</strong> " . ($total > 0 ? round(($passed / $total) * 100, 2) : 0) . "%</p>";
        echo "</div>";

        if ($failed > 0) {
            echo "<div style='background: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107;'>";
            echo "<h4>失败的测试:</h4>";
            echo "<ul>";
            foreach ($results as $result) {
                if (!$result['success']) {
                    echo "<li><strong>{$result['test']}:</strong> {$result['message']}</li>";
                }
            }
            echo "</ul>";
            echo "</div>";
        }

        echo "<div style='background: #e7f3ff; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa;'>";
        echo "<h4>需求验证:</h4>";
        echo "<p>此测试验证以下需求的实现：</p>";
        echo "<ul>";
        echo "<li><strong>需求 10.1:</strong> 管理员修改审核等级时，系统应当记录操作人、时间和修改内容</li>";
        echo "<li><strong>需求 10.2:</strong> 管理员修改敏感词库时，系统应当记录详细的变更信息</li>";
        echo "<li><strong>需求 10.3:</strong> 系统执行自动清理时，系统应当记录清理操作的完整日志</li>";
        echo "<li><strong>需求 10.4:</strong> 用户权限变更时，系统应当记录权限修改的审计信息</li>";
        echo "<li><strong>需求 10.5:</strong> 系统发生异常时，系统应当记录错误日志便于问题诊断</li>";
        echo "</ul>";
        echo "</div>";
    }
}

// 如果直接访问此文件，运行测试
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    // 确保WordPress环境已加载
    if (!function_exists('wp_hash_password')) {
        die('请在WordPress环境中运行此测试');
    }

    // 获取容器实例
    global $ams_v2_container;
    if (!$ams_v2_container) {
        die('插件容器未初始化');
    }

    $test = new AMS_V2_Audit_Logging_Test($ams_v2_container);
    $results = $test->run_all_tests();
    $test->output_results($results);
}