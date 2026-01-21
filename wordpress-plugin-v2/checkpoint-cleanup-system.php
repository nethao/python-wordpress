<?php
/**
 * 清理系统检查点
 *
 * @package Article_Management_V2
 * @subpackage Tests
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 清理系统检查点类
 */
class AMS_V2_Cleanup_System_Checkpoint {

    /**
     * 检查点列表
     *
     * @var array
     */
    private $checkpoints = array();

    /**
     * 构造函数
     */
    public function __construct() {
        $this->init_checkpoints();
    }

    /**
     * 初始化检查点
     */
    private function init_checkpoints() {
        $this->checkpoints = array(
            // 文件存在性检查
            'cleanup_interface_exists' => array(
                'name' => '清理调度器接口文件存在',
                'type' => 'file_exists',
                'path' => 'includes/interfaces/interface-cleanup-scheduler.php'
            ),
            'cleanup_manager_exists' => array(
                'name' => '清理调度器实现文件存在',
                'type' => 'file_exists',
                'path' => 'includes/managers/class-cleanup-scheduler.php'
            ),
            'cleanup_settings_page_exists' => array(
                'name' => '清理设置页面文件存在',
                'type' => 'file_exists',
                'path' => 'admin/partials/cleanup-settings.php'
            ),
            'cleanup_test_exists' => array(
                'name' => '清理系统测试文件存在',
                'type' => 'file_exists',
                'path' => 'test-cleanup-system.php'
            ),
            
            // 类存在性检查
            'cleanup_interface_class' => array(
                'name' => '清理调度器接口类存在',
                'type' => 'interface_exists',
                'class' => 'AMS_V2_Cleanup_Scheduler_Interface'
            ),
            'cleanup_manager_class' => array(
                'name' => '清理调度器实现类存在',
                'type' => 'class_exists',
                'class' => 'AMS_V2_Cleanup_Scheduler'
            ),
            
            // 方法存在性检查
            'cleanup_methods' => array(
                'name' => '清理调度器核心方法存在',
                'type' => 'methods_exist',
                'class' => 'AMS_V2_Cleanup_Scheduler',
                'methods' => array(
                    'set_threshold_days',
                    'get_threshold_days',
                    'enable_auto_cleanup',
                    'disable_auto_cleanup',
                    'is_auto_cleanup_enabled',
                    'identify_articles_for_cleanup',
                    'execute_cleanup',
                    'trigger_manual_cleanup',
                    'get_cleanup_logs',
                    'get_cleanup_statistics',
                    'schedule_cleanup_task',
                    'unschedule_cleanup_task'
                )
            ),
            
            // 功能性检查
            'container_registration' => array(
                'name' => '清理调度器已在容器中注册',
                'type' => 'container_service',
                'service' => 'cleanup_scheduler'
            ),
            'admin_menu_integration' => array(
                'name' => '清理设置菜单已集成',
                'type' => 'admin_menu',
                'menu_slug' => 'article-management-v2-cleanup-settings'
            ),
            'cron_hook_registered' => array(
                'name' => '自动清理cron钩子已注册',
                'type' => 'cron_hook',
                'hook' => 'ams_v2_auto_cleanup'
            ),
            
            // 数据库表检查
            'cleanup_logs_table' => array(
                'name' => '清理日志表存在',
                'type' => 'table_exists',
                'table' => 'cleanup_logs'
            ),
            
            // 配置选项检查
            'cleanup_options' => array(
                'name' => '清理配置选项存在',
                'type' => 'options_exist',
                'options' => array(
                    'ams_v2_cleanup_threshold_days',
                    'ams_v2_auto_cleanup_enabled'
                )
            )
        );
    }

    /**
     * 运行所有检查点
     *
     * @return array 检查结果
     */
    public function run_all_checks() {
        $results = array();
        $total_checks = count($this->checkpoints);
        $passed_checks = 0;

        foreach ($this->checkpoints as $key => $checkpoint) {
            $result = $this->run_single_check($checkpoint);
            $results[$key] = $result;
            
            if ($result['success']) {
                $passed_checks++;
            }
        }

        return array(
            'results' => $results,
            'summary' => array(
                'total' => $total_checks,
                'passed' => $passed_checks,
                'failed' => $total_checks - $passed_checks,
                'success_rate' => $total_checks > 0 ? round(($passed_checks / $total_checks) * 100, 2) : 0
            )
        );
    }

    /**
     * 运行单个检查点
     *
     * @param array $checkpoint 检查点配置
     * @return array 检查结果
     */
    private function run_single_check($checkpoint) {
        try {
            switch ($checkpoint['type']) {
                case 'file_exists':
                    return $this->check_file_exists($checkpoint);
                    
                case 'class_exists':
                    return $this->check_class_exists($checkpoint);
                    
                case 'interface_exists':
                    return $this->check_interface_exists($checkpoint);
                    
                case 'methods_exist':
                    return $this->check_methods_exist($checkpoint);
                    
                case 'container_service':
                    return $this->check_container_service($checkpoint);
                    
                case 'admin_menu':
                    return $this->check_admin_menu($checkpoint);
                    
                case 'cron_hook':
                    return $this->check_cron_hook($checkpoint);
                    
                case 'table_exists':
                    return $this->check_table_exists($checkpoint);
                    
                case 'options_exist':
                    return $this->check_options_exist($checkpoint);
                    
                default:
                    return array(
                        'success' => false,
                        'message' => '未知的检查类型: ' . $checkpoint['type']
                    );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => '检查异常: ' . $e->getMessage()
            );
        }
    }
    /**
     * 检查文件是否存在
     */
    private function check_file_exists($checkpoint) {
        $file_path = AMS_V2_PLUGIN_DIR . $checkpoint['path'];
        
        if (file_exists($file_path)) {
            return array(
                'success' => true,
                'message' => '文件存在: ' . $checkpoint['path']
            );
        } else {
            return array(
                'success' => false,
                'message' => '文件不存在: ' . $checkpoint['path']
            );
        }
    }

    /**
     * 检查类是否存在
     */
    private function check_class_exists($checkpoint) {
        if (class_exists($checkpoint['class'])) {
            return array(
                'success' => true,
                'message' => '类存在: ' . $checkpoint['class']
            );
        } else {
            return array(
                'success' => false,
                'message' => '类不存在: ' . $checkpoint['class']
            );
        }
    }

    /**
     * 检查接口是否存在
     */
    private function check_interface_exists($checkpoint) {
        if (interface_exists($checkpoint['class'])) {
            return array(
                'success' => true,
                'message' => '接口存在: ' . $checkpoint['class']
            );
        } else {
            return array(
                'success' => false,
                'message' => '接口不存在: ' . $checkpoint['class']
            );
        }
    }

    /**
     * 检查方法是否存在
     */
    private function check_methods_exist($checkpoint) {
        if (!class_exists($checkpoint['class'])) {
            return array(
                'success' => false,
                'message' => '类不存在: ' . $checkpoint['class']
            );
        }

        $missing_methods = array();
        foreach ($checkpoint['methods'] as $method) {
            if (!method_exists($checkpoint['class'], $method)) {
                $missing_methods[] = $method;
            }
        }

        if (empty($missing_methods)) {
            return array(
                'success' => true,
                'message' => '所有方法都存在 (' . count($checkpoint['methods']) . '个)'
            );
        } else {
            return array(
                'success' => false,
                'message' => '缺少方法: ' . implode(', ', $missing_methods)
            );
        }
    }

    /**
     * 检查容器服务
     */
    private function check_container_service($checkpoint) {
        global $ams_v2_container;
        
        if (!$ams_v2_container) {
            return array(
                'success' => false,
                'message' => '容器未初始化'
            );
        }

        if ($ams_v2_container->has($checkpoint['service'])) {
            try {
                $service = $ams_v2_container->get($checkpoint['service']);
                return array(
                    'success' => true,
                    'message' => '服务已注册且可实例化: ' . $checkpoint['service']
                );
            } catch (Exception $e) {
                return array(
                    'success' => false,
                    'message' => '服务注册但实例化失败: ' . $e->getMessage()
                );
            }
        } else {
            return array(
                'success' => false,
                'message' => '服务未注册: ' . $checkpoint['service']
            );
        }
    }

    /**
     * 检查管理菜单
     */
    private function check_admin_menu($checkpoint) {
        global $submenu;
        
        $found = false;
        if (isset($submenu['article-management-v2'])) {
            foreach ($submenu['article-management-v2'] as $menu_item) {
                if (isset($menu_item[2]) && $menu_item[2] === $checkpoint['menu_slug']) {
                    $found = true;
                    break;
                }
            }
        }

        if ($found) {
            return array(
                'success' => true,
                'message' => '管理菜单已注册: ' . $checkpoint['menu_slug']
            );
        } else {
            return array(
                'success' => false,
                'message' => '管理菜单未注册: ' . $checkpoint['menu_slug']
            );
        }
    }

    /**
     * 检查cron钩子
     */
    private function check_cron_hook($checkpoint) {
        if (has_action($checkpoint['hook'])) {
            return array(
                'success' => true,
                'message' => 'Cron钩子已注册: ' . $checkpoint['hook']
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Cron钩子未注册: ' . $checkpoint['hook']
            );
        }
    }

    /**
     * 检查数据库表
     */
    private function check_table_exists($checkpoint) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ams_v2_' . $checkpoint['table'];
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        
        if ($result === $table_name) {
            return array(
                'success' => true,
                'message' => '数据库表存在: ' . $table_name
            );
        } else {
            return array(
                'success' => false,
                'message' => '数据库表不存在: ' . $table_name
            );
        }
    }

    /**
     * 检查配置选项
     */
    private function check_options_exist($checkpoint) {
        $missing_options = array();
        
        foreach ($checkpoint['options'] as $option) {
            if (get_option($option) === false) {
                $missing_options[] = $option;
            }
        }

        if (empty($missing_options)) {
            return array(
                'success' => true,
                'message' => '所有配置选项都存在 (' . count($checkpoint['options']) . '个)'
            );
        } else {
            return array(
                'success' => false,
                'message' => '缺少配置选项: ' . implode(', ', $missing_options)
            );
        }
    }

    /**
     * 输出检查结果
     */
    public function output_results($check_results) {
        $results = $check_results['results'];
        $summary = $check_results['summary'];

        echo '<div class="ams-v2-checkpoint-results">';
        echo '<h2>清理系统检查点结果</h2>';
        
        // 摘要信息
        echo '<div class="ams-v2-checkpoint-summary">';
        echo '<h3>检查摘要</h3>';
        echo '<div class="ams-v2-stats-grid">';
        echo '<div class="ams-v2-stat-item">';
        echo '<div class="ams-v2-stat-number">' . $summary['total'] . '</div>';
        echo '<div class="ams-v2-stat-label">总检查项</div>';
        echo '</div>';
        echo '<div class="ams-v2-stat-item success">';
        echo '<div class="ams-v2-stat-number">' . $summary['passed'] . '</div>';
        echo '<div class="ams-v2-stat-label">通过</div>';
        echo '</div>';
        echo '<div class="ams-v2-stat-item error">';
        echo '<div class="ams-v2-stat-number">' . $summary['failed'] . '</div>';
        echo '<div class="ams-v2-stat-label">失败</div>';
        echo '</div>';
        echo '<div class="ams-v2-stat-item">';
        echo '<div class="ams-v2-stat-number">' . $summary['success_rate'] . '%</div>';
        echo '<div class="ams-v2-stat-label">成功率</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // 详细结果
        echo '<div class="ams-v2-checkpoint-details">';
        echo '<h3>详细结果</h3>';
        
        foreach ($results as $key => $result) {
            $checkpoint = $this->checkpoints[$key];
            $status_class = $result['success'] ? 'success' : 'error';
            $status_icon = $result['success'] ? '✓' : '✗';
            
            echo '<div class="ams-v2-checkpoint-item ' . $status_class . '">';
            echo '<div class="ams-v2-checkpoint-header">';
            echo '<span class="ams-v2-checkpoint-icon">' . $status_icon . '</span>';
            echo '<span class="ams-v2-checkpoint-name">' . esc_html($checkpoint['name']) . '</span>';
            echo '</div>';
            echo '<div class="ams-v2-checkpoint-message">' . esc_html($result['message']) . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';

        // 添加样式
        echo '<style>
        .ams-v2-checkpoint-results {
            max-width: 1000px;
            margin: 20px 0;
        }
        .ams-v2-checkpoint-summary {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .ams-v2-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .ams-v2-stat-item {
            background: white;
            padding: 15px;
            text-align: center;
            border-radius: 6px;
            border: 2px solid #ddd;
        }
        .ams-v2-stat-item.success {
            border-color: #28a745;
        }
        .ams-v2-stat-item.error {
            border-color: #dc3545;
        }
        .ams-v2-stat-number {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .ams-v2-stat-item.success .ams-v2-stat-number {
            color: #28a745;
        }
        .ams-v2-stat-item.error .ams-v2-stat-number {
            color: #dc3545;
        }
        .ams-v2-checkpoint-item {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 6px;
            border-left: 4px solid;
        }
        .ams-v2-checkpoint-item.success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .ams-v2-checkpoint-item.error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .ams-v2-checkpoint-header {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        .ams-v2-checkpoint-icon {
            font-size: 18px;
            font-weight: bold;
            margin-right: 10px;
            width: 20px;
        }
        .ams-v2-checkpoint-item.success .ams-v2-checkpoint-icon {
            color: #28a745;
        }
        .ams-v2-checkpoint-item.error .ams-v2-checkpoint-icon {
            color: #dc3545;
        }
        .ams-v2-checkpoint-name {
            font-weight: 600;
            font-size: 14px;
        }
        .ams-v2-checkpoint-message {
            font-size: 13px;
            color: #666;
            margin-left: 30px;
        }
        </style>';
    }
}

// 如果直接访问此文件，运行检查点
if (basename($_SERVER['PHP_SELF']) === 'checkpoint-cleanup-system.php') {
    $checkpoint = new AMS_V2_Cleanup_System_Checkpoint();
    $results = $checkpoint->run_all_checks();
    $checkpoint->output_results($results);
}
?>