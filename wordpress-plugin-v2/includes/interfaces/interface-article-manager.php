<?php
/**
 * 文章管理器接口
 *
 * @package Article_Management_V2
 * @subpackage Interfaces
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 文章数据传输对象
 */
class AMS_V2_Article_Data {
    public $title;
    public $content;
    public $excerpt;
    public $tags;
    public $status;

    public function __construct($data = array()) {
        $this->title = isset($data['title']) ? $data['title'] : '';
        $this->content = isset($data['content']) ? $data['content'] : '';
        $this->excerpt = isset($data['excerpt']) ? $data['excerpt'] : '';
        $this->tags = isset($data['tags']) ? $data['tags'] : array();
        $this->status = isset($data['status']) ? $data['status'] : AMS_V2_Article_Status::DRAFT;
    }
}

/**
 * 文章管理器接口
 */
interface AMS_V2_Article_Manager_Interface {

    /**
     * 创建文章
     *
     * @param AMS_V2_User $author 作者
     * @param AMS_V2_Article_Data $data 文章数据
     * @return AMS_V2_Article|WP_Error 文章对象或错误
     */
    public function create_article($author, $data);

    /**
     * 更新文章
     *
     * @param AMS_V2_Article $article 文章对象
     * @param AMS_V2_Article_Data $data 文章数据
     * @param AMS_V2_User $user 操作用户
     * @return AMS_V2_Article|WP_Error 更新后的文章对象或错误
     */
    public function update_article($article, $data, $user);

    /**
     * 删除文章
     *
     * @param AMS_V2_Article $article 文章对象
     * @param AMS_V2_User $user 操作用户
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function delete_article($article, $user);

    /**
     * 根据用户获取文章列表
     *
     * @param AMS_V2_User $user 用户对象
     * @param array $args 查询参数
     * @return AMS_V2_Article[] 文章数组
     */
    public function get_articles_by_user($user, $args = array());

    /**
     * 获取所有文章（管理员权限）
     *
     * @param array $args 查询参数
     * @return AMS_V2_Article[] 文章数组
     */
    public function get_all_articles($args = array());

    /**
     * 根据ID获取文章
     *
     * @param int $article_id 文章ID
     * @return AMS_V2_Article|null 文章对象或null
     */
    public function get_article_by_id($article_id);

    /**
     * 更改文章状态
     *
     * @param AMS_V2_Article $article 文章对象
     * @param string $status 新状态
     * @param AMS_V2_User $user 操作用户
     * @param string $notes 审核备注
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function change_status($article, $status, $user, $notes = '');

    /**
     * 检查用户是否可以编辑文章
     *
     * @param AMS_V2_User $user 用户对象
     * @param AMS_V2_Article $article 文章对象
     * @return bool
     */
    public function can_edit_article($user, $article);

    /**
     * 检查用户是否可以删除文章
     *
     * @param AMS_V2_User $user 用户对象
     * @param AMS_V2_Article $article 文章对象
     * @return bool
     */
    public function can_delete_article($user, $article);

    /**
     * 检查用户是否可以查看文章
     *
     * @param AMS_V2_User $user 用户对象
     * @param AMS_V2_Article $article 文章对象
     * @return bool
     */
    public function can_view_article($user, $article);

    /**
     * 检查用户是否可以审核文章
     *
     * @param AMS_V2_User $user 用户对象
     * @param AMS_V2_Article $article 文章对象
     * @return bool
     */
    public function can_moderate_article($user, $article);

    /**
     * 获取待审核文章列表
     *
     * @param array $args 查询参数
     * @return AMS_V2_Article[] 文章数组
     */
    public function get_pending_articles($args = array());

    /**
     * 根据状态获取文章列表
     *
     * @param string $status 文章状态
     * @param array $args 查询参数
     * @return AMS_V2_Article[] 文章数组
     */
    public function get_articles_by_status($status, $args = array());

    /**
     * 搜索文章
     *
     * @param string $keyword 搜索关键词
     * @param array $args 查询参数
     * @return AMS_V2_Article[] 文章数组
     */
    public function search_articles($keyword, $args = array());

    /**
     * 获取文章统计信息
     *
     * @param AMS_V2_User|null $user 用户对象，null表示获取全局统计
     * @return array 统计信息数组
     */
    public function get_article_stats($user = null);

    /**
     * 批量更新文章状态
     *
     * @param array $article_ids 文章ID数组
     * @param string $status 新状态
     * @param AMS_V2_User $user 操作用户
     * @param string $notes 审核备注
     * @return array 操作结果数组
     */
    public function batch_update_status($article_ids, $status, $user, $notes = '');

    /**
     * 获取文章历史记录
     *
     * @param AMS_V2_Article $article 文章对象
     * @return array 历史记录数组
     */
    public function get_article_history($article);
}