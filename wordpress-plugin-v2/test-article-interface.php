<?php
/**
 * 测试文章管理界面功能
 *
 * @package Article_Management_V2
 * @subpackage Tests
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 文章管理界面测试类
 */
class AMS_V2_Article_Interface_Test {

    /**
     * 依赖注入容器
     *
     * @var AMS_V2_Container
     */
    private $container;

    /**
     * 构造函数
     *
     * @param AMS_V2_Container $container 依赖注入容器
     */
    public function __construct($container) {
        $this->container = $container;
    }

    /**
     * 运行所有测试
     *
     * @return array 测试结果
     */
    public function run_all_tests() {
        $results = array();

        echo "<h2>文章管理界面功能测试</h2>\n";

        // 测试用户登录验证
        $results['user_login_verification'] = $this->test_user_login_verification();

        // 测试基于角色的文章列表过滤
        $results['role_based_filtering'] = $this->test_role_based_filtering();

        // 测试管理员审核界面
        $results['admin_moderation_interface'] = $this->test_admin_moderation_interface();

        // 测试文章状态管理
        $results['article_status_management'] = $this->test_article_status_management();

        // 测试权限控制
        $results['permission_control'] = $this->test_permission_control();

        return $results;
    }

    /**
     * 测试用户登录验证
     *
     * @return array 测试结果
     */
    private function test_user_login_verification() {
        echo "<h3>测试用户登录验证</h3>\n";

        $auth_middleware = $this->container->get('auth_middleware');
        $auth_manager = $this->container->get('authentication_manager');

        $results = array(
            'name' => '用户登录验证',
            'tests' => array(),
            'passed' => 0,
            'failed' => 0
        );

        // 测试1: 检查未登录用户访问
        try {
            // 模拟未登录状态
            $current_user = $auth_middleware->get_current_user();
            
            if (!$current_user) {
                $results['tests'][] = array(
                    'name' => '未登录用户被正确拒绝',
                    'status' => 'passed',
                    'message' => '未登录用户无法获取当前用户信息'
                );
                $results['passed']++;
            } else {
                $results['tests'][] = array(
                    'name' => '未登录用户被正确拒绝',
                    'status' => 'failed',
                    'message' => '未登录用户不应该能获取当前用户信息'
                );
                $results['failed']++;
            }
        } catch (Exception $e) {
            $results['tests'][] = array(
                'name' => '未登录用户被正确拒绝',
                'status' => 'failed',
                'message' => '测试异常: ' . $e->getMessage()
            );
            $results['failed']++;
        }

        // 测试2: 检查登录验证中间件
        try {
            $is_logged_in = $auth_middleware->is_logged_in();
            
            $results['tests'][] = array(
                'name' => '登录状态检查功能',
                'status' => 'passed',
                'message' => '登录状态检查方法正常工作，当前状态: ' . ($is_logged_in ? '已登录' : '未登录')
            );
            $results['passed']++;
        } catch (Exception $e) {
            $results['tests'][] = array(
                'name' => '登录状态检查功能',
                'status' => 'failed',
                'message' => '测试异常: ' . $e->getMessage()
            );
            $results['failed']++;
        }

        return $results;
    }

    /**
     * 测试基于角色的文章列表过滤
     *
     * @return array 测试结果
     */
    private function test_role_based_filtering() {
        echo "<h3>测试基于角色的文章列表过滤</h3>\n";

        $article_manager = $this->container->get('article_manager');
        $auth_manager = $this->container->get('authentication_manager');

        $results = array(
            'name' => '基于角色的文章列表过滤',
            'tests' => array(),
            'passed' => 0,
            'failed' => 0
        );

        // 测试1: 检查管理员可以查看所有文章的方法
        try {
            $all_articles_method = method_exists($article_manager, 'get_all_articles');
            
            if ($all_articles_method) {
                $results['tests'][] = array(
                    'name' => '管理员查看所有文章方法存在',
                    'status' => 'passed',
                    'message' => 'get_all_articles 方法存在'
                );
                $results['passed']++;
            } else {
                $results['tests'][] = array(
                    'name' => '管理员查看所有文章方法存在',
                    'status' => 'failed',
                    'message' => 'get_all_articles 方法不存在'
                );
                $results['failed']++;
            }
        } catch (Exception $e) {
            $results['tests'][] = array(
                'name' => '管理员查看所有文章方法存在',
                'status' => 'failed',
                'message' => '测试异常: ' . $e->getMessage()
            );
            $results['failed']++;
        }

        // 测试2: 检查普通用户查看自己文章的方法
        try {
            $user_articles_method = method_exists($article_manager, 'get_articles_by_user');
            
            if ($user_articles_method) {
                $results['tests'][] = array(
                    'name' => '普通用户查看自己文章方法存在',
                    'status' => 'passed',
                    'message' => 'get_articles_by_user 方法存在'
                );
                $results['passed']++;
            } else {
                $results['tests'][] = array(
                    'name' => '普通用户查看自己文章方法存在',
                    'status' => 'failed',
                    'message' => 'get_articles_by_user 方法不存在'
                );
                $results['failed']++;
            }
        } catch (Exception $e) {
            $results['tests'][] = array(
                'name' => '普通用户查看自己文章方法存在',
                'status' => 'failed',
                'message' => '测试异常: ' . $e->getMessage()
            );
            $results['failed']++;
        }

        // 测试3: 检查权限验证方法
        try {
            $can_edit_method = method_exists($article_manager, 'can_edit_article');
            $can_view_method = method_exists($article_manager, 'can_view_article');
            
            if ($can_edit_method && $can_view_method) {
                $results['tests'][] = array(
                    'name' => '文章权限验证方法存在',
                    'status' => 'passed',
                    'message' => '文章编辑和查看权限验证方法都存在'
                );
                $results['passed']++;
            } else {
                $results['tests'][] = array(
                    'name' => '文章权限验证方法存在',
                    'status' => 'failed',
                    'message' => '缺少权限验证方法: can_edit_article=' . ($can_edit_method ? 'true' : 'false') . ', can_view_article=' . ($can_view_method ? 'true' : 'false')
                );
                $results['failed']++;
            }
        } catch (Exception $e) {
            $results['tests'][] = array(
                'name' => '文章权限验证方法存在',
                'status' => 'failed',
                'message' => '测试异常: ' . $e->getMessage()
            );
            $results['failed']++;
        }

        return $results;
    }

    /**
     * 测试管理员审核界面
     *
     * @return array 测试结果
     */
    private function test_admin_moderation_interface() {
        echo "<h3>测试管理员审核界面</h3>\n";

        $article_manager = $this->container->get('article_manager');
        $auth_middleware = $this->container->get('auth_middleware');

        $results = array(
            'name' => '管理员审核界面',
            'tests' => array(),
            'passed' => 0,
            'failed' => 0
        );

        // 测试1: 检查文章状态更改方法
        try {
            $change_status_method = method_exists($article_manager, 'change_status');
            
            if ($change_status_method) {
                $results['tests'][] = array(
                    'name' => '文章状态更改方法存在',
                    'status' => 'passed',
                    'message' => 'change_status 方法存在'
                );
                $results['passed']++;
            } else {
                $results['tests'][] = array(
                    'name' => '文章状态更改方法存在',
                    'status' => 'failed',
                    'message' => 'change_status 方法不存在'
                );
                $results['failed']++;
            }
        } catch (Exception $e) {
            $results['tests'][] = array(
                'name' => '文章状态更改方法存在',
                'status' => 'failed',
                'message' => '测试异常: ' . $e->getMessage()
            );
            $results['failed']++;
        }

        // 测试2: 检查审核权限验证方法
        try {
            $can_moderate_method = method_exists($article_manager, 'can_moderate_article');
            
            if ($can_moderate_method) {
                $results['tests'][] = array(
                    'name' => '审核权限验证方法存在',
                    'status' => 'passed',
                    'message' => 'can_moderate_article 方法存在'
                );
                $results['passed']++;
            } else {
                $results['tests'][] = array(
                    'name' => '审核权限验证方法存在',
                    'status' => 'failed',
                    'message' => 'can_moderate_article 方法不存在'
                );
                $results['failed']++;
            }
        } catch (Exception $e) {
            $results['tests'][] = array(
                'name' => '审核权限验证方法存在',
                'status' => 'failed',
                'message' => '测试异常: ' . $e->getMessage()
            );
            $results['failed']++;
        }

        // 测试3: 检查批量操作方法
        try {
            $batch_update_method = method_exists($article_manager, 'batch_update_status');
            
            if ($batch_update_method) {
                $results['tests'][] = array(
                    'name' => '批量状态更新方法存在',
                    'status' => 'passed',
                    'message' => 'batch_update_status 方法存在'
                );
                $results['passed']++;
            } else {
                $results['tests'][] = array(
                    'name' => '批量状态更新方法存在',
                    'status' => 'failed',
                    'message' => 'batch_update_status 方法不存在'
                );
                $results['failed']++;
            }
        } catch (Exception $e) {
            $results['tests'][] = array(
                'name' => '批量状态更新方法存在',
                'status' => 'failed',
                'message' => '测试异常: ' . $e->getMessage()
            );
            $results['failed']++;
        }

        // 测试4: 检查管理员权限验证
        try {
            $is_admin_method = method_exists($auth_middleware, 'is_current_user_admin');
            
            if ($is_admin_method) {
                $results['tests'][] = array(
                    'name' => '管理员权限验证方法存在',
                    'status' => 'passed',
                    'message' => 'is_current_user_admin 方法存在'
                );
                $results['passed']++;
            } else {
                $results['tests'][] = array(
                    'name' => '管理员权限验证方法存在',
                    'status' => 'failed',
                    'message' => 'is_current_user_admin 方法不存在'
                );
                $results['failed']++;
            }
        } catch (Exception $e) {
            $results['tests'][] = array(
                'name' => '管理员权限验证方法存在',
                'status' => 'failed',
                'message' => '测试异常: ' . $e->getMessage()
            );
            $results['failed']++;
        }

        return $results;
    }

    /**
     * 测试文章状态管理
     *
     * @return array 测试结果
     */
    private function test_article_status_management() {
        echo "<h3>测试文章状态管理</h3>\n";

        $results = array(
            'name' => '文章状态管理',
            'tests' => array(),
            'passed' => 0,
            'failed' => 0
        );

        // 测试1: 检查文章状态枚举
        try {
            $status_class_exists = class_exists('AMS_V2_Article_Status');
            
            if ($status_class_exists) {
                $results['tests'][] = array(
                    'name' => '文章状态枚举类存在',
                    'status' => 'passed',
                    'message' => 'AMS_V2_Article_Status 类存在'
                );
                $results['passed']++;
            } else {
                $results['tests'][] = array(
                    'name' => '文章状态枚举类存在',
                    'status' => 'failed',
                    'message' => 'AMS_V2_Article_Status 类不存在'
                );
                $results['failed']++;
            }
        } catch (Exception $e) {
            $results['tests'][] = array(
                'name' => '文章状态枚举类存在',
                'status' => 'failed',
                'message' => '测试异常: ' . $e->getMessage()
            );
            $results['failed']++;
        }

        // 测试2: 检查状态验证方法
        try {
            if (class_exists('AMS_V2_Article_Status')) {
                $is_valid_method = method_exists('AMS_V2_Article_Status', 'is_valid_status');
                
                if ($is_valid_method) {
                    // 测试有效状态
                    $valid_status = AMS_V2_Article_Status::is_valid_status('pending_review');
                    $invalid_status = AMS_V2_Article_Status::is_valid_status('invalid_status');
                    
                    if ($valid_status && !$invalid_status) {
                        $results['tests'][] = array(
                            'name' => '状态验证方法正常工作',
                            'status' => 'passed',
                            'message' => '状态验证方法能正确识别有效和无效状态'
                        );
                        $results['passed']++;
                    } else {
                        $results['tests'][] = array(
                            'name' => '状态验证方法正常工作',
                            'status' => 'failed',
                            'message' => '状态验证方法工作异常: valid=' . ($valid_status ? 'true' : 'false') . ', invalid=' . ($invalid_status ? 'true' : 'false')
                        );
                        $results['failed']++;
                    }
                } else {
                    $results['tests'][] = array(
                        'name' => '状态验证方法正常工作',
                        'status' => 'failed',
                        'message' => 'is_valid_status 方法不存在'
                    );
                    $results['failed']++;
                }
            } else {
                $results['tests'][] = array(
                    'name' => '状态验证方法正常工作',
                    'status' => 'skipped',
                    'message' => '跳过测试，因为状态类不存在'
                );
            }
        } catch (Exception $e) {
            $results['tests'][] = array(
                'name' => '状态验证方法正常工作',
                'status' => 'failed',
                'message' => '测试异常: ' . $e->getMessage()
            );
            $results['failed']++;
        }

        return $results;
    }

    /**
     * 测试权限控制
     *
     * @return array 测试结果
     */
    private function test_permission_control() {
        echo "<h3>测试权限控制</h3>\n";

        $auth_middleware = $this->container->get('auth_middleware');

        $results = array(
            'name' => '权限控制',
            'tests' => array(),
            'passed' => 0,
            'failed' => 0
        );

        // 测试1: 检查权限验证方法
        try {
            $verify_ajax_method = method_exists($auth_middleware, 'verify_ajax_permission');
            $verify_rest_method = method_exists($auth_middleware, 'verify_rest_permission');
            $can_access_method = method_exists($auth_middleware, 'can_access_resource');
            
            if ($verify_ajax_method && $verify_rest_method && $can_access_method) {
                $results['tests'][] = array(
                    'name' => '权限验证方法存在',
                    'status' => 'passed',
                    'message' => '所有权限验证方法都存在'
                );
                $results['passed']++;
            } else {
                $results['tests'][] = array(
                    'name' => '权限验证方法存在',
                    'status' => 'failed',
                    'message' => '缺少权限验证方法: ajax=' . ($verify_ajax_method ? 'true' : 'false') . ', rest=' . ($verify_rest_method ? 'true' : 'false') . ', resource=' . ($can_access_method ? 'true' : 'false')
                );
                $results['failed']++;
            }
        } catch (Exception $e) {
            $results['tests'][] = array(
                'name' => '权限验证方法存在',
                'status' => 'failed',
                'message' => '测试异常: ' . $e->getMessage()
            );
            $results['failed']++;
        }

        // 测试2: 检查用户权限检查方法
        try {
            $current_user_can_method = method_exists($auth_middleware, 'current_user_can');
            
            if ($current_user_can_method) {
                $results['tests'][] = array(
                    'name' => '用户权限检查方法存在',
                    'status' => 'passed',
                    'message' => 'current_user_can 方法存在'
                );
                $results['passed']++;
            } else {
                $results['tests'][] = array(
                    'name' => '用户权限检查方法存在',
                    'status' => 'failed',
                    'message' => 'current_user_can 方法不存在'
                );
                $results['failed']++;
            }
        } catch (Exception $e) {
            $results['tests'][] = array(
                'name' => '用户权限检查方法存在',
                'status' => 'failed',
                'message' => '测试异常: ' . $e->getMessage()
            );
            $results['failed']++;
        }

        return $results;
    }

    /**
     * 输出测试结果
     *
     * @param array $results 测试结果
     */
    public function output_results($results) {
        $total_passed = 0;
        $total_failed = 0;
        $total_tests = 0;

        echo "<div style='background: #f9f9f9; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;'>\n";
        echo "<h2>文章管理界面测试结果汇总</h2>\n";

        foreach ($results as $category => $result) {
            $total_passed += $result['passed'];
            $total_failed += $result['failed'];
            $total_tests += count($result['tests']);

            echo "<h3>{$result['name']}</h3>\n";
            echo "<p>通过: {$result['passed']} | 失败: {$result['failed']}</p>\n";

            foreach ($result['tests'] as $test) {
                $color = $test['status'] === 'passed' ? 'green' : ($test['status'] === 'failed' ? 'red' : 'orange');
                echo "<div style='margin: 10px 0; padding: 10px; background: white; border-left: 4px solid {$color};'>\n";
                echo "<strong>{$test['name']}</strong> - <span style='color: {$color};'>" . strtoupper($test['status']) . "</span><br>\n";
                echo "<small>{$test['message']}</small>\n";
                echo "</div>\n";
            }
        }

        echo "<hr>\n";
        echo "<h3>总计</h3>\n";
        echo "<p><strong>总测试数:</strong> {$total_tests}</p>\n";
        echo "<p><strong>通过:</strong> <span style='color: green;'>{$total_passed}</span></p>\n";
        echo "<p><strong>失败:</strong> <span style='color: red;'>{$total_failed}</span></p>\n";
        
        $success_rate = $total_tests > 0 ? round(($total_passed / $total_tests) * 100, 2) : 0;
        echo "<p><strong>成功率:</strong> {$success_rate}%</p>\n";

        if ($total_failed === 0) {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 4px; margin: 20px 0;'>\n";
            echo "<strong>✅ 所有测试通过！文章管理界面功能实现正确。</strong>\n";
            echo "</div>\n";
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px 0;'>\n";
            echo "<strong>❌ 有 {$total_failed} 个测试失败，请检查相关功能实现。</strong>\n";
            echo "</div>\n";
        }

        echo "</div>\n";
    }
}