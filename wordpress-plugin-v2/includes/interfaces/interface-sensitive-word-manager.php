<?php
/**
 * 敏感词管理器接口
 *
 * @package Article_Management_V2
 * @subpackage Interfaces
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 敏感词管理器接口
 */
interface AMS_V2_Sensitive_Word_Manager_Interface {

    /**
     * 创建敏感词
     *
     * @param array $data 敏感词数据
     * @return AMS_V2_Sensitive_Word|WP_Error
     */
    public function create_word($data);

    /**
     * 更新敏感词
     *
     * @param int $id 敏感词ID
     * @param array $data 更新数据
     * @return AMS_V2_Sensitive_Word|WP_Error
     */
    public function update_word($id, $data);

    /**
     * 删除敏感词
     *
     * @param int $id 敏感词ID
     * @return bool|WP_Error
     */
    public function delete_word($id);

    /**
     * 根据ID获取敏感词
     *
     * @param int $id 敏感词ID
     * @return AMS_V2_Sensitive_Word|null
     */
    public function get_word_by_id($id);

    /**
     * 根据词汇内容获取敏感词
     *
     * @param string $word 词汇内容
     * @param string $type 词汇类型（可选）
     * @return AMS_V2_Sensitive_Word|null
     */
    public function get_word_by_content($word, $type = null);

    /**
     * 获取敏感词列表
     *
     * @param array $args 查询参数
     * @return array
     */
    public function get_words($args = array());

    /**
     * 搜索敏感词
     *
     * @param string $keyword 搜索关键词
     * @param array $args 查询参数
     * @return array
     */
    public function search_words($keyword, $args = array());

    /**
     * 批量导入敏感词
     *
     * @param array $words 敏感词数组
     * @param string $type 词汇类型
     * @param int $created_by 创建者ID
     * @return array 导入结果
     */
    public function import_words($words, $type, $created_by);

    /**
     * 批量删除敏感词
     *
     * @param array $ids 敏感词ID数组
     * @return array 删除结果
     */
    public function delete_words($ids);

    /**
     * 激活/停用敏感词
     *
     * @param int $id 敏感词ID
     * @param bool $is_active 是否激活
     * @return bool|WP_Error
     */
    public function toggle_word_status($id, $is_active);

    /**
     * 获取敏感词统计信息
     *
     * @return array
     */
    public function get_word_statistics();

    /**
     * 检查文本中的敏感词
     *
     * @param string $text 待检查文本
     * @param array $types 检查的词汇类型
     * @return array 检查结果
     */
    public function check_text($text, $types = array());

    /**
     * 替换文本中的敏感词
     *
     * @param string $text 待处理文本
     * @param array $types 处理的词汇类型
     * @return string 处理后的文本
     */
    public function replace_text($text, $types = array());

    /**
     * 初始化内置敏感词库
     *
     * @return bool
     */
    public function initialize_builtin_words();

    /**
     * 重置敏感词库到默认状态
     *
     * @return bool
     */
    public function reset_to_default();
}