<?php
/**
 * 基础单元测试
 *
 * @package Article_Management_V2
 * @subpackage Tests
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 基础单元测试类
 */
class AMS_V2_Basic_Unit_Test {

    /**
     * 测试用户模型基本功能
     *
     * @return array 测试结果
     */
    public function test_user_model() {
        $result = array(
            'name' => '用户模型基本功能测试',
            'passed' => false,
            'message' => ''
        );

        try {
            // 创建用户对象
            $user_data = array(
                'id' => 1,
                'username' => 'test_user',
                'email' => 'test@example.com',
                'display_name' => '测试用户',
                'is_active' => true,
                'created_at' => '2024-01-01 00:00:00'
            );

            $user = new AMS_V2_User($user_data);

            // 测试基本属性
            if ($user->get_id() === 1 &&
                $user->get_username() === 'test_user' &&
                $user->get_email() === 'test@example.com' &&
                $user->get_display_name() === '测试用户' &&
                $user->is_active() === true) {
                
                $result['passed'] = true;
                $result['message'] = '用户模型基本功能正常';
            } else {
                $result['message'] = '用户模型属性设置或获取失败';
            }
        } catch (Exception $e) {
            $result['message'] = '测试异常: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * 测试角色模型基本功能
     *
     * @return array 测试结果
     */
    public function test_role_model() {
        $result = array(
            'name' => '角色模型基本功能测试',
            'passed' => false,
            'message' => ''
        );

        try {
            // 创建角色对象
            $role_data = array(
                'id' => 1,
                'name' => 'test_role',
                'display_name' => '测试角色',
                'description' => '这是一个测试角色',
                'capabilities' => array('test_capability', 'another_capability'),
                'is_system' => false,
                'created_at' => '2024-01-01 00:00:00'
            );

            $role = new AMS_V2_Role($role_data);

            // 测试基本属性
            if ($role->get_id() === 1 &&
                $role->get_name() === 'test_role' &&
                $role->get_display_name() === '测试角色' &&
                $role->get_description() === '这是一个测试角色' &&
                $role->has_capability('test_capability') &&
                !$role->is_system()) {
                
                $result['passed'] = true;
                $result['message'] = '角色模型基本功能正常';
            } else {
                $result['message'] = '角色模型属性设置或获取失败';
            }
        } catch (Exception $e) {
            $result['message'] = '测试异常: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * 测试用户角色关联
     *
     * @return array 测试结果
     */
    public function test_user_role_association() {
        $result = array(
            'name' => '用户角色关联测试',
            'passed' => false,
            'message' => ''
        );

        try {
            // 创建用户
            $user = new AMS_V2_User(array(
                'id' => 1,
                'username' => 'test_user',
                'email' => 'test@example.com'
            ));

            // 创建角色
            $role = new AMS_V2_Role(array(
                'id' => 1,
                'name' => 'test_role',
                'capabilities' => array('test_capability')
            ));

            // 为用户添加角色
            $user->add_role($role);

            // 测试角色关联
            if ($user->has_role('test_role') && $user->has_capability('test_capability')) {
                $result['passed'] = true;
                $result['message'] = '用户角色关联功能正常';
            } else {
                $result['message'] = '用户角色关联失败';
            }
        } catch (Exception $e) {
            $result['message'] = '测试异常: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * 测试密码功能
     *
     * @return array 测试结果
     */
    public function test_password_functionality() {
        $result = array(
            'name' => '密码功能测试',
            'passed' => false,
            'message' => ''
        );

        try {
            $user = new AMS_V2_User();
            $password = 'test_password_123';
            
            // 设置密码
            $user->set_password($password);
            
            // 验证密码
            if ($user->verify_password($password) && !$user->verify_password('wrong_password')) {
                $result['passed'] = true;
                $result['message'] = '密码功能正常';
            } else {
                $result['message'] = '密码验证失败';
            }
        } catch (Exception $e) {
            $result['message'] = '测试异常: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * 运行所有基础测试
     *
     * @return array 测试结果
     */
    public function run_all_tests() {
        $results = array();
        
        $results['test_user_model'] = $this->test_user_model();
        $results['test_role_model'] = $this->test_role_model();
        $results['test_user_role_association'] = $this->test_user_role_association();
        $results['test_password_functionality'] = $this->test_password_functionality();
        
        return $results;
    }

    /**
     * 输出测试结果
     *
     * @param array $results 测试结果
     */
    public function output_results($results) {
        echo "<div style='margin: 20px; padding: 20px; border: 1px solid #ccc;'>";
        echo "<h3>基础单元测试结果</h3>";
        
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