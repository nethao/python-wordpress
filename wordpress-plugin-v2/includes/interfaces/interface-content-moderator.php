<?php
/**
 * 内容审核器接口
 *
 * @package Article_Management_V2
 * @subpackage Interfaces
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 内容审核器接口
 */
interface AMS_V2_Content_Moderator_Interface {

    /**
     * 审核文本内容
     *
     * @param string $text 待审核文本
     * @param string $moderation_level 审核等级
     * @return array 审核结果
     */
    public function moderate_text($text, $moderation_level = null);

    /**
     * 审核文章内容
     *
     * @param array $article_data 文章数据
     * @param string $moderation_level 审核等级
     * @return array 审核结果
     */
    public function moderate_article($article_data, $moderation_level = null);

    /**
     * 获取当前审核等级
     *
     * @return string
     */
    public function get_current_moderation_level();

    /**
     * 设置审核等级
     *
     * @param string $level 审核等级
     * @return bool|WP_Error
     */
    public function set_moderation_level($level);

    /**
     * 获取审核配置
     *
     * @return array
     */
    public function get_moderation_config();

    /**
     * 更新审核配置
     *
     * @param array $config 配置数据
     * @return bool|WP_Error
     */
    public function update_moderation_config($config);

    /**
     * 重新加载审核配置（热更新）
     *
     * @return bool
     */
    public function reload_config();

    /**
     * 获取审核统计信息
     *
     * @param array $filters 过滤条件
     * @return array
     */
    public function get_moderation_statistics($filters = array());

    /**
     * 记录审核日志
     *
     * @param array $log_data 日志数据
     * @return bool
     */
    public function log_moderation_action($log_data);

    /**
     * 获取审核日志
     *
     * @param array $filters 过滤条件
     * @return array
     */
    public function get_moderation_logs($filters = array());

    /**
     * 预览审核结果
     *
     * @param string $text 待审核文本
     * @param string $moderation_level 审核等级
     * @return array 预览结果
     */
    public function preview_moderation($text, $moderation_level = null);

    /**
     * 批量审核文本
     *
     * @param array $texts 文本数组
     * @param string $moderation_level 审核等级
     * @return array 批量审核结果
     */
    public function batch_moderate($texts, $moderation_level = null);

    /**
     * 获取审核规则
     *
     * @param string $moderation_level 审核等级
     * @return array 审核规则
     */
    public function get_moderation_rules($moderation_level = null);

    /**
     * 验证审核配置
     *
     * @param array $config 配置数据
     * @return array 验证结果
     */
    public function validate_config($config);
}