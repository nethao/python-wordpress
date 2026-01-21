<?php
/**
 * 文章管理功能测试
 *
 * @package Article_Management_V2
 * @subpackage Tests
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 文章管理功能测试类
 */
class AMS_V2_Article_Management_Test {

    /**
     * 运行所有测试
     */
    public static function run_tests() {
        echo "<h2>文章管理功能测试</h2>\n";
        
        try {
            self::test_article_model();
            self::test_article_status();
            self::test_article_manager_creation();
            
            echo "<p style='color: green;'><strong>所有测试通过！</strong></p>\n";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'><strong>测试失败：</strong> " . esc_html($e->getMessage()) . "</p>\n";
            echo "<pre>" . esc_html($e->getTraceAsString()) . "</pre>\n";
        }
    }

    /**
     * 测试文章模型
     */
    private static function test_article_model() {
        echo "<h3>测试文章模型</h3>\n";
        
        // 测试创建文章对象
        $article_data = array(
            'id' => 1,
            'title' => '测试文章标题',
            'content' => '这是测试文章的内容',
            'excerpt' => '文章摘要',
            'author_id' => 1,
            'status' => AMS_V2_Article_Status::DRAFT,
            'tags' => json_encode(array('测试', 'PHP')),
            'created_at' => '2024-01-01 10:00:00',
            'updated_at' => '2024-01-01 10:00:00'
        );
        
        $article = new AMS_V2_Article($article_data);
        
        // 验证基本属性
        if ($article->get_id() !== 1) {
            throw new Exception('文章ID设置失败');
        }
        
        if ($article->get_title() !== '测试文章标题') {
            throw new Exception('文章标题设置失败');
        }
        
        if ($article->get_author_id() !== 1) {
            throw new Exception('作者ID设置失败');
        }
        
        if ($article->get_status() !== AMS_V2_Article_Status::DRAFT) {
            throw new Exception('文章状态设置失败');
        }
        
        // 测试标签功能
        $tags = $article->get_tags();
        if (!is_array($tags) || count($tags) !== 2) {
            throw new Exception('标签解析失败');
        }
        
        // 测试状态检查方法
        if (!$article->is_draft()) {
            throw new Exception('is_draft()方法失败');
        }
        
        if ($article->is_published()) {
            throw new Exception('is_published()方法失败');
        }
        
        // 测试数据验证
        $article->set_title('');
        $errors = $article->validate();
        if (empty($errors)) {
            throw new Exception('数据验证应该失败但没有失败');
        }
        
        echo "<p style='color: green;'>✓ 文章模型测试通过</p>\n";
    }

    /**
     * 测试文章状态
     */
    private static function test_article_status() {
        echo "<h3>测试文章状态</h3>\n";
        
        // 测试状态常量
        $expected_statuses = array(
            AMS_V2_Article_Status::DRAFT,
            AMS_V2_Article_Status::PENDING_REVIEW,
            AMS_V2_Article_Status::APPROVED,
            AMS_V2_Article_Status::PUBLISHED,
            AMS_V2_Article_Status::REJECTED,
            AMS_V2_Article_Status::ARCHIVED
        );
        
        foreach ($expected_statuses as $status) {
            if (!AMS_V2_Article_Status::is_valid_status($status)) {
                throw new Exception("状态 {$status} 应该是有效的");
            }
        }
        
        // 测试无效状态
        if (AMS_V2_Article_Status::is_valid_status('invalid_status')) {
            throw new Exception('无效状态应该被拒绝');
        }
        
        // 测试状态标签
        $label = AMS_V2_Article_Status::get_status_label(AMS_V2_Article_Status::DRAFT);
        if (empty($label)) {
            throw new Exception('状态标签获取失败');
        }
        
        echo "<p style='color: green;'>✓ 文章状态测试通过</p>\n";
    }

    /**
     * 测试文章管理器创建
     */
    private static function test_article_manager_creation() {
        echo "<h3>测试文章管理器创建</h3>\n";
        
        // 获取全局容器
        global $ams_v2_container;
        
        if (!$ams_v2_container) {
            throw new Exception('全局容器未初始化');
        }
        
        // 检查服务是否已注册
        if (!$ams_v2_container->has('article_manager')) {
            throw new Exception('ArticleManager服务未注册');
        }
        
        // 尝试获取服务实例
        $article_manager = $ams_v2_container->get('article_manager');
        
        if (!$article_manager instanceof AMS_V2_Article_Manager) {
            throw new Exception('ArticleManager实例创建失败');
        }
        
        // 测试接口方法是否存在
        $required_methods = array(
            'create_article',
            'update_article',
            'delete_article',
            'get_articles_by_user',
            'get_all_articles',
            'get_article_by_id',
            'change_status',
            'can_edit_article',
            'can_delete_article',
            'can_view_article',
            'can_moderate_article'
        );
        
        foreach ($required_methods as $method) {
            if (!method_exists($article_manager, $method)) {
                throw new Exception("ArticleManager缺少方法: {$method}");
            }
        }
        
        echo "<p style='color: green;'>✓ 文章管理器创建测试通过</p>\n";
    }
}

// 如果直接访问此文件，运行测试
if (isset($_GET['run_article_tests']) && $_GET['run_article_tests'] === '1') {
    AMS_V2_Article_Management_Test::run_tests();
}