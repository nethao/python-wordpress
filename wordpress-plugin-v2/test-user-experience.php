<?php
/**
 * 用户体验优化测试
 * 
 * 测试二次确认对话框、错误信息显示和操作指引功能
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

class UserExperienceTest {
    
    private $results = [];
    private $plugin;
    
    public function __construct() {
        $this->plugin = Article_Management_V2::get_instance();
    }
    
    /**
     * 运行所有用户体验测试
     */
    public function run_all_tests() {
        echo "<h2>用户体验优化测试报告</h2>\n";
        
        $this->test_confirmation_dialogs();
        $this->test_error_handling();
        $this->test_form_validation();
        $this->test_navigation_help();
        $this->test_accessibility_features();
        
        $this->display_results();
    }
    
    /**
     * 测试确认对话框功能
     */
    private function test_confirmation_dialogs() {
        echo "<h3>测试确认对话框功能</h3>\n";
        
        try {
            // 测试JavaScript文件是否存在
            $js_file = plugin_dir_path(__FILE__) . 'admin/js/user-experience.js';
            if (file_exists($js_file)) {
                $this->add_result('用户体验JS文件', true, '用户体验JavaScript文件存在');
                
                // 检查文件内容
                $js_content = file_get_contents($js_file);
                if (strpos($js_content, 'showConfirmDialog') !== false) {
                    $this->add_result('确认对话框功能', true, '确认对话框函数已定义');
                } else {
                    $this->add_result('确认对话框功能', false, '确认对话框函数未找到');
                }
                
                if (strpos($js_content, 'ams-confirm-action') !== false) {
                    $this->add_result('确认操作类', true, '确认操作CSS类已定义');
                } else {
                    $this->add_result('确认操作类', false, '确认操作CSS类未找到');
                }
            } else {
                $this->add_result('用户体验JS文件', false, '用户体验JavaScript文件不存在');
            }
            
            // 测试CSS文件是否存在
            $css_file = plugin_dir_path(__FILE__) . 'admin/css/user-experience.css';
            if (file_exists($css_file)) {
                $this->add_result('用户体验CSS文件', true, '用户体验CSS文件存在');
                
                // 检查CSS内容
                $css_content = file_get_contents($css_file);
                if (strpos($css_content, '.ams-modal') !== false) {
                    $this->add_result('模态框样式', true, '模态框样式已定义');
                } else {
                    $this->add_result('模态框样式', false, '模态框样式未找到');
                }
            } else {
                $this->add_result('用户体验CSS文件', false, '用户体验CSS文件不存在');
            }
            
        } catch (Exception $e) {
            $this->add_result('确认对话框测试', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试错误处理功能
     */
    private function test_error_handling() {
        echo "<h3>测试错误处理功能</h3>\n";
        
        try {
            // 检查JavaScript错误处理函数
            $js_file = plugin_dir_path(__FILE__) . 'admin/js/user-experience.js';
            if (file_exists($js_file)) {
                $js_content = file_get_contents($js_file);
                
                if (strpos($js_content, 'showError') !== false) {
                    $this->add_result('错误显示函数', true, '错误显示函数已定义');
                } else {
                    $this->add_result('错误显示函数', false, '错误显示函数未找到');
                }
                
                if (strpos($js_content, 'showSuccess') !== false) {
                    $this->add_result('成功消息函数', true, '成功消息函数已定义');
                } else {
                    $this->add_result('成功消息函数', false, '成功消息函数未找到');
                }
                
                if (strpos($js_content, 'ajaxError') !== false) {
                    $this->add_result('AJAX错误处理', true, 'AJAX错误处理已配置');
                } else {
                    $this->add_result('AJAX错误处理', false, 'AJAX错误处理未配置');
                }
            }
            
            // 检查CSS通知样式
            $css_file = plugin_dir_path(__FILE__) . 'admin/css/user-experience.css';
            if (file_exists($css_file)) {
                $css_content = file_get_contents($css_file);
                
                if (strpos($css_content, '.ams-notification') !== false) {
                    $this->add_result('通知样式', true, '通知样式已定义');
                } else {
                    $this->add_result('通知样式', false, '通知样式未找到');
                }
                
                if (strpos($css_content, '.ams-notification-error') !== false) {
                    $this->add_result('错误通知样式', true, '错误通知样式已定义');
                } else {
                    $this->add_result('错误通知样式', false, '错误通知样式未找到');
                }
            }
            
        } catch (Exception $e) {
            $this->add_result('错误处理测试', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试表单验证功能
     */
    private function test_form_validation() {
        echo "<h3>测试表单验证功能</h3>\n";
        
        try {
            $js_file = plugin_dir_path(__FILE__) . 'admin/js/user-experience.js';
            if (file_exists($js_file)) {
                $js_content = file_get_contents($js_file);
                
                if (strpos($js_content, 'isValidEmail') !== false) {
                    $this->add_result('邮箱验证函数', true, '邮箱验证函数已定义');
                } else {
                    $this->add_result('邮箱验证函数', false, '邮箱验证函数未找到');
                }
                
                if (strpos($js_content, 'isValidUrl') !== false) {
                    $this->add_result('URL验证函数', true, 'URL验证函数已定义');
                } else {
                    $this->add_result('URL验证函数', false, 'URL验证函数未找到');
                }
                
                if (strpos($js_content, 'showFieldError') !== false) {
                    $this->add_result('字段错误显示', true, '字段错误显示函数已定义');
                } else {
                    $this->add_result('字段错误显示', false, '字段错误显示函数未找到');
                }
            }
            
            // 检查表单错误样式
            $css_file = plugin_dir_path(__FILE__) . 'admin/css/user-experience.css';
            if (file_exists($css_file)) {
                $css_content = file_get_contents($css_file);
                
                if (strpos($css_content, '.ams-error') !== false) {
                    $this->add_result('表单错误样式', true, '表单错误样式已定义');
                } else {
                    $this->add_result('表单错误样式', false, '表单错误样式未找到');
                }
            }
            
        } catch (Exception $e) {
            $this->add_result('表单验证测试', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试导航帮助功能
     */
    private function test_navigation_help() {
        echo "<h3>测试导航帮助功能</h3>\n";
        
        try {
            $js_file = plugin_dir_path(__FILE__) . 'admin/js/user-experience.js';
            if (file_exists($js_file)) {
                $js_content = file_get_contents($js_file);
                
                if (strpos($js_content, 'showHelp') !== false) {
                    $this->add_result('帮助显示函数', true, '帮助显示函数已定义');
                } else {
                    $this->add_result('帮助显示函数', false, '帮助显示函数未找到');
                }
                
                if (strpos($js_content, 'getHelpContent') !== false) {
                    $this->add_result('帮助内容函数', true, '帮助内容函数已定义');
                } else {
                    $this->add_result('帮助内容函数', false, '帮助内容函数未找到');
                }
                
                if (strpos($js_content, 'addBreadcrumb') !== false) {
                    $this->add_result('面包屑导航', true, '面包屑导航函数已定义');
                } else {
                    $this->add_result('面包屑导航', false, '面包屑导航函数未找到');
                }
            }
            
            // 检查帮助按钮样式
            $css_file = plugin_dir_path(__FILE__) . 'admin/css/user-experience.css';
            if (file_exists($css_file)) {
                $css_content = file_get_contents($css_file);
                
                if (strpos($css_content, '.ams-help-button') !== false) {
                    $this->add_result('帮助按钮样式', true, '帮助按钮样式已定义');
                } else {
                    $this->add_result('帮助按钮样式', false, '帮助按钮样式未找到');
                }
                
                if (strpos($css_content, '.ams-breadcrumb') !== false) {
                    $this->add_result('面包屑样式', true, '面包屑样式已定义');
                } else {
                    $this->add_result('面包屑样式', false, '面包屑样式未找到');
                }
            }
            
        } catch (Exception $e) {
            $this->add_result('导航帮助测试', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试无障碍功能
     */
    private function test_accessibility_features() {
        echo "<h3>测试无障碍功能</h3>\n";
        
        try {
            $css_file = plugin_dir_path(__FILE__) . 'admin/css/user-experience.css';
            if (file_exists($css_file)) {
                $css_content = file_get_contents($css_file);
                
                // 检查高对比度支持
                if (strpos($css_content, 'prefers-contrast: high') !== false) {
                    $this->add_result('高对比度支持', true, '高对比度模式支持已配置');
                } else {
                    $this->add_result('高对比度支持', false, '高对比度模式支持未配置');
                }
                
                // 检查减少动画支持
                if (strpos($css_content, 'prefers-reduced-motion') !== false) {
                    $this->add_result('减少动画支持', true, '减少动画模式支持已配置');
                } else {
                    $this->add_result('减少动画支持', false, '减少动画模式支持未配置');
                }
                
                // 检查响应式设计
                if (strpos($css_content, '@media (max-width:') !== false) {
                    $this->add_result('响应式设计', true, '响应式设计已配置');
                } else {
                    $this->add_result('响应式设计', false, '响应式设计未配置');
                }
            }
            
            // 检查工具提示功能
            $js_file = plugin_dir_path(__FILE__) . 'admin/js/user-experience.js';
            if (file_exists($js_file)) {
                $js_content = file_get_contents($js_file);
                
                if (strpos($js_content, 'data-tooltip') !== false) {
                    $this->add_result('工具提示功能', true, '工具提示功能已定义');
                } else {
                    $this->add_result('工具提示功能', false, '工具提示功能未找到');
                }
            }
            
        } catch (Exception $e) {
            $this->add_result('无障碍功能测试', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试批量操作功能
     */
    private function test_bulk_operations() {
        echo "<h3>测试批量操作功能</h3>\n";
        
        try {
            // 检查管理员类是否有批量操作处理器
            $admin_file = plugin_dir_path(__FILE__) . 'admin/class-admin.php';
            if (file_exists($admin_file)) {
                $admin_content = file_get_contents($admin_file);
                
                if (strpos($admin_content, 'ajax_bulk_action') !== false) {
                    $this->add_result('批量操作处理器', true, '批量操作AJAX处理器已定义');
                } else {
                    $this->add_result('批量操作处理器', false, '批量操作AJAX处理器未找到');
                }
                
                if (strpos($admin_content, 'ams_v2_bulk_action') !== false) {
                    $this->add_result('批量操作钩子', true, '批量操作钩子已注册');
                } else {
                    $this->add_result('批量操作钩子', false, '批量操作钩子未注册');
                }
            }
            
            // 检查JavaScript批量操作功能
            $js_file = plugin_dir_path(__FILE__) . 'admin/js/user-experience.js';
            if (file_exists($js_file)) {
                $js_content = file_get_contents($js_file);
                
                if (strpos($js_content, 'executeBulkAction') !== false) {
                    $this->add_result('批量操作JS函数', true, '批量操作JavaScript函数已定义');
                } else {
                    $this->add_result('批量操作JS函数', false, '批量操作JavaScript函数未找到');
                }
            }
            
        } catch (Exception $e) {
            $this->add_result('批量操作测试', false, '异常: ' . $e->getMessage());
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
        echo "<h3>测试结果摘要</h3>\n";
        
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
        
        // 需求验证
        echo "<h4>需求验证状态:</h4>\n";
        echo "<ul>\n";
        echo "<li><strong>需求9.2 (二次确认对话框):</strong> " . 
             ($this->is_requirement_met('确认') ? '✅ 满足' : '❌ 不满足') . "</li>\n";
        echo "<li><strong>需求9.4 (功能导航和操作指引):</strong> " . 
             ($this->is_requirement_met('导航') || $this->is_requirement_met('帮助') ? '✅ 满足' : '❌ 不满足') . "</li>\n";
        echo "<li><strong>需求9.5 (明确的错误信息):</strong> " . 
             ($this->is_requirement_met('错误') ? '✅ 满足' : '❌ 不满足') . "</li>\n";
        echo "</ul>\n";
        
        // 功能演示
        echo "<h4>功能演示:</h4>\n";
        echo "<div style='background: #e7f3ff; padding: 15px; border-left: 4px solid #2196f3; margin: 20px 0;'>\n";
        echo "<p>以下是用户体验优化功能的使用示例：</p>\n";
        echo "<ol>\n";
        echo "<li><strong>确认对话框:</strong> 为危险操作添加 <code>class='ams-confirm-action'</code> 和 <code>data-confirm-message='确认信息'</code></li>\n";
        echo "<li><strong>工具提示:</strong> 为元素添加 <code>data-tooltip='提示信息'</code></li>\n";
        echo "<li><strong>错误显示:</strong> 使用 <code>AMS_V2_UX.showError('错误信息')</code></li>\n";
        echo "<li><strong>成功消息:</strong> 使用 <code>AMS_V2_UX.showSuccess('成功信息')</code></li>\n";
        echo "<li><strong>帮助按钮:</strong> 页面会自动添加帮助按钮</li>\n";
        echo "</ol>\n";
        echo "</div>\n";
    }
    
    /**
     * 检查需求是否满足
     */
    private function is_requirement_met($keyword) {
        foreach ($this->results as $result) {
            if (strpos($result['test'], $keyword) !== false && $result['passed']) {
                return true;
            }
        }
        return false;
    }
}

// 如果直接访问此文件，运行测试
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>用户体验优化测试</title>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h2, h3 { color: #333; }
            p { margin: 5px 0; }
            .test-section { margin-bottom: 20px; }
            code { background: #f5f5f5; padding: 2px 4px; border-radius: 3px; }
        </style>
    </head>
    <body>
        <?php
        $test = new UserExperienceTest();
        $test->run_all_tests();
        ?>
    </body>
    </html>
    <?php
}
?>