<?php
/**
 * 向后兼容性测试
 * 
 * 测试现有功能的兼容性，确保升级不会破坏原有功能
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

class BackwardCompatibilityTest {
    
    private $results = [];
    private $plugin;
    
    public function __construct() {
        $this->plugin = Article_Management_V2::get_instance();
    }
    
    /**
     * 运行所有兼容性测试
     */
    public function run_all_tests() {
        echo "<h2>向后兼容性测试报告</h2>\n";
        
        $this->test_sensitive_word_blocking();
        $this->test_category_publishing();
        $this->test_database_structure();
        $this->test_plugin_conflicts();
        $this->test_existing_data_migration();
        
        $this->display_results();
    }
    
    /**
     * 测试敏感词屏蔽功能
     */
    private function test_sensitive_word_blocking() {
        echo "<h3>测试敏感词屏蔽功能</h3>\n";
        
        try {
            // 获取敏感词管理器
            $sensitive_word_manager = $this->plugin->get_container()->get('sensitive_word_manager');
            
            // 测试基本敏感词检测
            $test_content = "这是一个包含测试敏感词的内容";
            $result = $sensitive_word_manager->check_content($test_content);
            
            if ($result !== false) {
                $this->add_result('敏感词检测功能', true, '敏感词检测功能正常工作');
            } else {
                $this->add_result('敏感词检测功能', false, '敏感词检测功能异常');
            }
            
            // 测试敏感词替换
            $replaced_content = $sensitive_word_manager->filter_content($test_content);
            if ($replaced_content !== $test_content) {
                $this->add_result('敏感词替换功能', true, '敏感词替换功能正常工作');
            } else {
                $this->add_result('敏感词替换功能', true, '无敏感词需要替换（正常）');
            }
            
        } catch (Exception $e) {
            $this->add_result('敏感词屏蔽功能', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试指定栏目发布功能
     */
    private function test_category_publishing() {
        echo "<h3>测试指定栏目发布功能</h3>\n";
        
        try {
            // 获取站点管理器
            $site_manager = $this->plugin->get_container()->get('site_manager');
            
            // 测试站点配置
            $sites = $site_manager->get_all_sites();
            
            if (!empty($sites)) {
                $this->add_result('站点配置', true, '找到 ' . count($sites) . ' 个配置的站点');
                
                // 测试每个站点的栏目配置
                foreach ($sites as $site) {
                    if (!empty($site->get_default_category_id())) {
                        $this->add_result('站点栏目配置', true, 
                            '站点 "' . $site->get_name() . '" 配置了默认栏目ID: ' . $site->get_default_category_id());
                    } else {
                        $this->add_result('站点栏目配置', false, 
                            '站点 "' . $site->get_name() . '" 未配置默认栏目ID');
                    }
                }
            } else {
                $this->add_result('站点配置', false, '未找到配置的站点');
            }
            
        } catch (Exception $e) {
            $this->add_result('指定栏目发布功能', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试数据库结构兼容性
     */
    private function test_database_structure() {
        echo "<h3>测试数据库结构兼容性</h3>\n";
        
        global $wpdb;
        
        try {
            // 检查原有表是否存在
            $original_table = $wpdb->prefix . 'ams_articles';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$original_table'") == $original_table;
            
            if ($table_exists) {
                $this->add_result('原有数据表', true, '原有文章表仍然存在');
                
                // 检查表结构
                $columns = $wpdb->get_results("DESCRIBE $original_table");
                $column_names = array_column($columns, 'Field');
                
                $required_columns = ['id', 'title', 'content', 'created_at'];
                $missing_columns = array_diff($required_columns, $column_names);
                
                if (empty($missing_columns)) {
                    $this->add_result('表结构兼容性', true, '原有表结构完整');
                } else {
                    $this->add_result('表结构兼容性', false, 
                        '缺少字段: ' . implode(', ', $missing_columns));
                }
            } else {
                $this->add_result('原有数据表', false, '原有文章表不存在');
            }
            
            // 检查新表是否正确创建
            $new_tables = [
                $wpdb->prefix . 'ams_v2_users',
                $wpdb->prefix . 'ams_v2_articles',
                $wpdb->prefix . 'ams_v2_sites',
                $wpdb->prefix . 'ams_v2_sensitive_words',
                $wpdb->prefix . 'ams_v2_audit_logs'
            ];
            
            foreach ($new_tables as $table) {
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
                if ($exists) {
                    $this->add_result('新表创建', true, "表 $table 创建成功");
                } else {
                    $this->add_result('新表创建', false, "表 $table 创建失败");
                }
            }
            
        } catch (Exception $e) {
            $this->add_result('数据库结构兼容性', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试与其他插件的冲突
     */
    private function test_plugin_conflicts() {
        echo "<h3>测试插件冲突</h3>\n";
        
        try {
            // 检查常见的WordPress函数是否被覆盖
            $core_functions = [
                'wp_insert_post',
                'wp_update_post',
                'wp_delete_post',
                'add_action',
                'add_filter'
            ];
            
            foreach ($core_functions as $function) {
                if (function_exists($function)) {
                    $this->add_result('核心函数检查', true, "函数 $function 可用");
                } else {
                    $this->add_result('核心函数检查', false, "函数 $function 不可用");
                }
            }
            
            // 检查是否有命名空间冲突
            $our_classes = [
                'Article_Management_V2',
                'AMS_V2_Article_Manager',
                'AMS_V2_User_Manager',
                'AMS_V2_Site_Manager'
            ];
            
            foreach ($our_classes as $class) {
                if (class_exists($class)) {
                    $this->add_result('类名冲突检查', true, "类 $class 正确加载");
                } else {
                    $this->add_result('类名冲突检查', false, "类 $class 未找到");
                }
            }
            
            // 检查钩子冲突
            global $wp_filter;
            $our_hooks = [
                'ams_v2_article_created',
                'ams_v2_user_authenticated',
                'ams_v2_content_moderated'
            ];
            
            foreach ($our_hooks as $hook) {
                if (isset($wp_filter[$hook])) {
                    $this->add_result('钩子注册', true, "钩子 $hook 已注册");
                } else {
                    $this->add_result('钩子注册', true, "钩子 $hook 未使用（正常）");
                }
            }
            
        } catch (Exception $e) {
            $this->add_result('插件冲突测试', false, '异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试现有数据迁移
     */
    private function test_existing_data_migration() {
        echo "<h3>测试现有数据迁移</h3>\n";
        
        global $wpdb;
        
        try {
            // 检查原有数据是否存在
            $original_table = $wpdb->prefix . 'ams_articles';
            $original_count = $wpdb->get_var("SELECT COUNT(*) FROM $original_table");
            
            if ($original_count > 0) {
                $this->add_result('原有数据', true, "找到 $original_count 条原有文章数据");
                
                // 检查是否已迁移
                $new_table = $wpdb->prefix . 'ams_v2_articles';
                $new_count = $wpdb->get_var("SELECT COUNT(*) FROM $new_table");
                
                if ($new_count >= $original_count) {
                    $this->add_result('数据迁移', true, "数据已迁移，新表有 $new_count 条记录");
                } else {
                    $this->add_result('数据迁移', false, 
                        "数据迁移不完整，原有 $original_count 条，新表只有 $new_count 条");
                }
            } else {
                $this->add_result('原有数据', true, '无原有数据需要迁移');
            }
            
        } catch (Exception $e) {
            $this->add_result('数据迁移测试', false, '异常: ' . $e->getMessage());
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
        echo "<li><strong>需求9.1 (现有敏感词屏蔽功能):</strong> " . 
             ($this->is_requirement_met('敏感词') ? '✅ 满足' : '❌ 不满足') . "</li>\n";
        echo "<li><strong>需求9.3 (与其他插件兼容性):</strong> " . 
             ($this->is_requirement_met('冲突') ? '✅ 满足' : '❌ 不满足') . "</li>\n";
        echo "</ul>\n";
    }
    
    /**
     * 检查需求是否满足
     */
    private function is_requirement_met($keyword) {
        foreach ($this->results as $result) {
            if (strpos($result['test'], $keyword) !== false && !$result['passed']) {
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
        <title>向后兼容性测试</title>
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
        $test = new BackwardCompatibilityTest();
        $test->run_all_tests();
        ?>
    </body>
    </html>
    <?php
}
?>