<?php
/**
 * 用户角色管理测试
 *
 * @package Article_Management_V2
 * @subpackage Tests
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 用户角色管理测试类
 */
class AMS_V2_User_Role_Management_Test {

    /**
     * 容器实例
     *
     * @var AMS_V2_Container
     */
    private $container;

    /**
     * 角色管理器
     *
     * @var AMS_V2_Role_Manager
     */
    private $role_manager;

    /**
     * 认证管理器
     *
     * @var AMS_V2_Authentication_Manager
     */
    private $auth_manager;

    /**
     * 构造函数
     *
     * @param AMS_V2_Container $container 容器实例
     */
    public function __construct($container) {
        $this->container = $container;
        $this->role_manager = $container->get('role_manager');
        $this->auth_manager = $container->get('authentication_manager');
    }

    /**
     * 运行所有测试
     *
     * @return array 测试结果
     */
    public function run_all_tests() {
        $results = array();
        
        $results['test_role_creation'] = $this->test_role_creation();
        $results['test_user_creation'] = $this->test_user_creation();
        $results['test_role_assignment'] = $this->test_role_assignment();
        $results['test_permission_check'] = $this->test_permission_check();
        $results['test_authentication'] = $this->test_authentication();
        
        return $results;
    }

    /**
     * 测试角色创建
     *
     * @return array 测试结果
     */
    public function test_role_creation() {
        $result = array(
            'name' => '角色创建测试',
            'passed' => false,
            'message' => ''
        );

        try {
            // 创建测试角色
            $role = $this->role_manager->create_role(
                'test_role',
                array('test_capability'),
                '测试角色',
                '这是一个测试角色'
            );

            if ($role instanceof AMS_V2_Role) {
                $result['passed'] = true;
                $result['message'] = '角色创建成功';
                
                // 清理测试数据
                $this->role_manager->delete_role($role->get_id());
            } else {
                $result['message'] = '角色创建失败: ' . $role->get_error_message();
            }
        } catch (Exception $e) {
            $result['message'] = '测试异常: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * 测试用户创建
     *
     * @return array 测试结果
     */
    public function test_user_creation() {
        $result = array(
            'name' => '用户创建测试',
            'passed' => false,
            'message' => ''
        );

        try {
            // 创建测试用户
            $user_data = array(
                'username' => 'test_user_' . time(),
                'email' => 'test_' . time() . '@example.com',
                'password' => 'test_password_123',
                'display_name' => '测试用户',
                'role' => 'user'
            );

            $user = $this->auth_manager->create_user($user_data);

            if ($user instanceof AMS_V2_User) {
                $result['passed'] = true;
                $result['message'] = '用户创建成功';
                
                // 清理测试数据
                $this->auth_manager->delete_user($user->get_id());
            } else {
                $result['message'] = '用户创建失败: ' . $user->get_error_message();
            }
        } catch (Exception $e) {
            $result['message'] = '测试异常: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * 测试角色分配
     *
     * @return array 测试结果
     */
    public function test_role_assignment() {
        $result = array(
            'name' => '角色分配测试',
            'passed' => false,
            'message' => ''
        );

        try {
            // 创建测试用户
            $user_data = array(
                'username' => 'test_user_role_' . time(),
                'email' => 'test_role_' . time() . '@example.com',
                'password' => 'test_password_123'
            );

            $user = $this->auth_manager->create_user($user_data);
            
            if (!$user instanceof AMS_V2_User) {
                $result['message'] = '创建测试用户失败';
                return $result;
            }

            // 获取管理员角色
            $admin_role = $this->role_manager->get_role('administrator');
            
            if (!$admin_role) {
                $result['message'] = '获取管理员角色失败';
                $this->auth_manager->delete_user($user->get_id());
                return $result;
            }

            // 分配角色
            $assign_result = $this->role_manager->assign_role($user, $admin_role);
            
            if ($assign_result === true) {
                // 检查角色是否分配成功
                if ($this->role_manager->has_role($user, 'administrator')) {
                    $result['passed'] = true;
                    $result['message'] = '角色分配成功';
                } else {
                    $result['message'] = '角色分配验证失败';
                }
            } else {
                $result['message'] = '角色分配失败: ' . $assign_result->get_error_message();
            }
            
            // 清理测试数据
            $this->auth_manager->delete_user($user->get_id());
            
        } catch (Exception $e) {
            $result['message'] = '测试异常: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * 测试权限检查
     *
     * @return array 测试结果
     */
    public function test_permission_check() {
        $result = array(
            'name' => '权限检查测试',
            'passed' => false,
            'message' => ''
        );

        try {
            // 创建测试用户
            $user_data = array(
                'username' => 'test_user_perm_' . time(),
                'email' => 'test_perm_' . time() . '@example.com',
                'password' => 'test_password_123',
                'role' => 'user'
            );

            $user = $this->auth_manager->create_user($user_data);
            
            if (!$user instanceof AMS_V2_User) {
                $result['message'] = '创建测试用户失败';
                return $result;
            }

            // 加载用户角色
            $roles = $this->role_manager->get_user_roles($user);
            $user->set_roles($roles);

            // 测试普通用户权限
            $has_create_articles = $this->role_manager->has_capability($user, 'create_articles');
            $has_manage_users = $this->role_manager->has_capability($user, 'manage_users');

            if ($has_create_articles && !$has_manage_users) {
                $result['passed'] = true;
                $result['message'] = '权限检查正确';
            } else {
                $result['message'] = '权限检查失败: create_articles=' . 
                    ($has_create_articles ? 'true' : 'false') . 
                    ', manage_users=' . ($has_manage_users ? 'true' : 'false');
            }
            
            // 清理测试数据
            $this->auth_manager->delete_user($user->get_id());
            
        } catch (Exception $e) {
            $result['message'] = '测试异常: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * 测试用户认证
     *
     * @return array 测试结果
     */
    public function test_authentication() {
        $result = array(
            'name' => '用户认证测试',
            'passed' => false,
            'message' => ''
        );

        try {
            // 创建测试用户
            $username = 'test_auth_' . time();
            $password = 'test_password_123';
            
            $user_data = array(
                'username' => $username,
                'email' => 'test_auth_' . time() . '@example.com',
                'password' => $password,
                'role' => 'user'
            );

            $user = $this->auth_manager->create_user($user_data);
            
            if (!$user instanceof AMS_V2_User) {
                $result['message'] = '创建测试用户失败';
                return $result;
            }

            // 测试正确的认证
            $auth_result = $this->auth_manager->authenticate($username, $password);
            
            if ($auth_result instanceof AMS_V2_User) {
                // 测试错误的密码
                $wrong_auth = $this->auth_manager->authenticate($username, 'wrong_password');
                
                if (is_wp_error($wrong_auth)) {
                    $result['passed'] = true;
                    $result['message'] = '用户认证测试通过';
                } else {
                    $result['message'] = '错误密码认证应该失败';
                }
            } else {
                $result['message'] = '正确密码认证失败: ' . $auth_result->get_error_message();
            }
            
            // 清理测试数据
            $this->auth_manager->delete_user($user->get_id());
            
        } catch (Exception $e) {
            $result['message'] = '测试异常: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * 输出测试结果
     *
     * @param array $results 测试结果
     */
    public function output_results($results) {
        echo "<div style='margin: 20px; padding: 20px; border: 1px solid #ccc;'>";
        echo "<h3>用户角色管理系统测试结果</h3>";
        
        $total_tests = count($results);
        $passed_tests = 0;
        
        foreach ($results as $test_result) {
            $status_color = $test_result['passed'] ? 'green' : 'red';
            $status_text = $test_result['passed'] ? '通过' : '失败';
            
            if ($test_result['passed']) {
                $passed_tests++;
            }
            
            echo "<p style='color: {$status_color};'>";
            echo "<strong>{$test_result['name']}</strong>: {$status_text} - {$test_result['message']}";
            echo "</p>";
        }
        
        echo "<hr>";
        echo "<p><strong>总计: {$passed_tests}/{$total_tests} 测试通过</strong></p>";
        echo "</div>";
    }
}