<?php
/**
 * 文章模型类
 *
 * @package Article_Management_V2
 * @subpackage Models
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 文章状态枚举
 */
class AMS_V2_Article_Status {
    const DRAFT = 'draft';
    const PENDING_REVIEW = 'pending_review';
    const APPROVED = 'approved';
    const PUBLISHED = 'published';
    const REJECTED = 'rejected';
    const ARCHIVED = 'archived';

    /**
     * 获取所有状态
     *
     * @return array
     */
    public static function get_all_statuses() {
        return array(
            self::DRAFT => '草稿',
            self::PENDING_REVIEW => '待审核',
            self::APPROVED => '已批准',
            self::PUBLISHED => '已发布',
            self::REJECTED => '已拒绝',
            self::ARCHIVED => '已归档'
        );
    }

    /**
     * 获取状态显示名称
     *
     * @param string $status 状态值
     * @return string
     */
    public static function get_status_label($status) {
        $statuses = self::get_all_statuses();
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }

    /**
     * 验证状态是否有效
     *
     * @param string $status 状态值
     * @return bool
     */
    public static function is_valid_status($status) {
        return array_key_exists($status, self::get_all_statuses());
    }
}

/**
 * 文章模型类
 */
class AMS_V2_Article {

    /**
     * 文章ID
     *
     * @var int
     */
    private $id;

    /**
     * 文章标题
     *
     * @var string
     */
    private $title;

    /**
     * 文章内容
     *
     * @var string
     */
    private $content;

    /**
     * 文章摘要
     *
     * @var string
     */
    private $excerpt;

    /**
     * 作者ID
     *
     * @var int
     */
    private $author_id;

    /**
     * 作者对象
     *
     * @var AMS_V2_User|null
     */
    private $author;

    /**
     * 文章状态
     *
     * @var string
     */
    private $status;

    /**
     * 标签
     *
     * @var array
     */
    private $tags = array();

    /**
     * 创建时间
     *
     * @var DateTime
     */
    private $created_at;

    /**
     * 更新时间
     *
     * @var DateTime
     */
    private $updated_at;

    /**
     * 发布时间
     *
     * @var DateTime|null
     */
    private $published_at;

    /**
     * 审核时间
     *
     * @var DateTime|null
     */
    private $moderated_at;

    /**
     * 审核人ID
     *
     * @var int|null
     */
    private $moderated_by;

    /**
     * 审核备注
     *
     * @var string
     */
    private $moderation_notes;

    /**
     * 构造函数
     *
     * @param array $data 文章数据
     */
    public function __construct($data = array()) {
        if (!empty($data)) {
            $this->populate($data);
        }
    }

    /**
     * 填充文章数据
     *
     * @param array $data 文章数据
     */
    public function populate($data) {
        $this->id = isset($data['id']) ? (int) $data['id'] : 0;
        $this->title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
        $this->content = isset($data['content']) ? wp_kses_post($data['content']) : '';
        $this->excerpt = isset($data['excerpt']) ? sanitize_textarea_field($data['excerpt']) : '';
        $this->author_id = isset($data['author_id']) ? (int) $data['author_id'] : 0;
        $this->status = isset($data['status']) ? sanitize_text_field($data['status']) : AMS_V2_Article_Status::DRAFT;
        $this->moderated_by = isset($data['moderated_by']) ? (int) $data['moderated_by'] : null;
        $this->moderation_notes = isset($data['moderation_notes']) ? sanitize_textarea_field($data['moderation_notes']) : '';
        
        // 处理标签
        if (isset($data['tags'])) {
            if (is_string($data['tags'])) {
                $this->tags = json_decode($data['tags'], true) ?: array();
            } elseif (is_array($data['tags'])) {
                $this->tags = $data['tags'];
            }
        }
        
        // 处理日期时间
        if (isset($data['created_at'])) {
            $this->created_at = new DateTime($data['created_at']);
        }
        if (isset($data['updated_at'])) {
            $this->updated_at = new DateTime($data['updated_at']);
        }
        if (isset($data['published_at']) && !empty($data['published_at'])) {
            $this->published_at = new DateTime($data['published_at']);
        }
        if (isset($data['moderated_at']) && !empty($data['moderated_at'])) {
            $this->moderated_at = new DateTime($data['moderated_at']);
        }
    }

    /**
     * 获取文章ID
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * 设置文章ID
     *
     * @param int $id 文章ID
     */
    public function set_id($id) {
        $this->id = (int) $id;
    }

    /**
     * 获取文章标题
     *
     * @return string
     */
    public function get_title() {
        return $this->title;
    }

    /**
     * 设置文章标题
     *
     * @param string $title 文章标题
     */
    public function set_title($title) {
        $this->title = sanitize_text_field($title);
    }

    /**
     * 获取文章内容
     *
     * @return string
     */
    public function get_content() {
        return $this->content;
    }

    /**
     * 设置文章内容
     *
     * @param string $content 文章内容
     */
    public function set_content($content) {
        $this->content = wp_kses_post($content);
    }

    /**
     * 获取文章摘要
     *
     * @return string
     */
    public function get_excerpt() {
        return $this->excerpt;
    }

    /**
     * 设置文章摘要
     *
     * @param string $excerpt 文章摘要
     */
    public function set_excerpt($excerpt) {
        $this->excerpt = sanitize_textarea_field($excerpt);
    }

    /**
     * 获取作者ID
     *
     * @return int
     */
    public function get_author_id() {
        return $this->author_id;
    }

    /**
     * 设置作者ID
     *
     * @param int $author_id 作者ID
     */
    public function set_author_id($author_id) {
        $this->author_id = (int) $author_id;
    }

    /**
     * 获取作者对象
     *
     * @return AMS_V2_User|null
     */
    public function get_author() {
        return $this->author;
    }

    /**
     * 设置作者对象
     *
     * @param AMS_V2_User $author 作者对象
     */
    public function set_author($author) {
        if ($author instanceof AMS_V2_User) {
            $this->author = $author;
            $this->author_id = $author->get_id();
        }
    }

    /**
     * 获取文章状态
     *
     * @return string
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * 设置文章状态
     *
     * @param string $status 文章状态
     */
    public function set_status($status) {
        if (AMS_V2_Article_Status::is_valid_status($status)) {
            $this->status = $status;
        }
    }

    /**
     * 获取标签
     *
     * @return array
     */
    public function get_tags() {
        return $this->tags;
    }

    /**
     * 设置标签
     *
     * @param array $tags 标签数组
     */
    public function set_tags($tags) {
        $this->tags = is_array($tags) ? array_map('sanitize_text_field', $tags) : array();
    }

    /**
     * 添加标签
     *
     * @param string $tag 标签
     */
    public function add_tag($tag) {
        $tag = sanitize_text_field($tag);
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
        }
    }

    /**
     * 移除标签
     *
     * @param string $tag 标签
     */
    public function remove_tag($tag) {
        $key = array_search($tag, $this->tags);
        if ($key !== false) {
            unset($this->tags[$key]);
            $this->tags = array_values($this->tags);
        }
    }

    /**
     * 获取创建时间
     *
     * @return DateTime|null
     */
    public function get_created_at() {
        return $this->created_at;
    }

    /**
     * 设置创建时间
     *
     * @param DateTime $created_at 创建时间
     */
    public function set_created_at($created_at) {
        $this->created_at = $created_at;
    }

    /**
     * 获取更新时间
     *
     * @return DateTime|null
     */
    public function get_updated_at() {
        return $this->updated_at;
    }

    /**
     * 设置更新时间
     *
     * @param DateTime $updated_at 更新时间
     */
    public function set_updated_at($updated_at) {
        $this->updated_at = $updated_at;
    }

    /**
     * 获取发布时间
     *
     * @return DateTime|null
     */
    public function get_published_at() {
        return $this->published_at;
    }

    /**
     * 设置发布时间
     *
     * @param DateTime|null $published_at 发布时间
     */
    public function set_published_at($published_at) {
        $this->published_at = $published_at;
    }

    /**
     * 获取审核时间
     *
     * @return DateTime|null
     */
    public function get_moderated_at() {
        return $this->moderated_at;
    }

    /**
     * 设置审核时间
     *
     * @param DateTime|null $moderated_at 审核时间
     */
    public function set_moderated_at($moderated_at) {
        $this->moderated_at = $moderated_at;
    }

    /**
     * 获取审核人ID
     *
     * @return int|null
     */
    public function get_moderated_by() {
        return $this->moderated_by;
    }

    /**
     * 设置审核人ID
     *
     * @param int|null $moderated_by 审核人ID
     */
    public function set_moderated_by($moderated_by) {
        $this->moderated_by = $moderated_by ? (int) $moderated_by : null;
    }

    /**
     * 获取审核备注
     *
     * @return string
     */
    public function get_moderation_notes() {
        return $this->moderation_notes;
    }

    /**
     * 设置审核备注
     *
     * @param string $moderation_notes 审核备注
     */
    public function set_moderation_notes($moderation_notes) {
        $this->moderation_notes = sanitize_textarea_field($moderation_notes);
    }

    /**
     * 检查是否为草稿
     *
     * @return bool
     */
    public function is_draft() {
        return $this->status === AMS_V2_Article_Status::DRAFT;
    }

    /**
     * 检查是否待审核
     *
     * @return bool
     */
    public function is_pending_review() {
        return $this->status === AMS_V2_Article_Status::PENDING_REVIEW;
    }

    /**
     * 检查是否已发布
     *
     * @return bool
     */
    public function is_published() {
        return $this->status === AMS_V2_Article_Status::PUBLISHED;
    }

    /**
     * 检查是否已拒绝
     *
     * @return bool
     */
    public function is_rejected() {
        return $this->status === AMS_V2_Article_Status::REJECTED;
    }

    /**
     * 检查是否已批准
     *
     * @return bool
     */
    public function is_approved() {
        return $this->status === AMS_V2_Article_Status::APPROVED;
    }

    /**
     * 检查是否已归档
     *
     * @return bool
     */
    public function is_archived() {
        return $this->status === AMS_V2_Article_Status::ARCHIVED;
    }

    /**
     * 获取状态显示名称
     *
     * @return string
     */
    public function get_status_label() {
        return AMS_V2_Article_Status::get_status_label($this->status);
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'author_id' => $this->author_id,
            'author' => $this->author ? $this->author->to_array() : null,
            'status' => $this->status,
            'status_label' => $this->get_status_label(),
            'tags' => $this->tags,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'published_at' => $this->published_at ? $this->published_at->format('Y-m-d H:i:s') : null,
            'moderated_at' => $this->moderated_at ? $this->moderated_at->format('Y-m-d H:i:s') : null,
            'moderated_by' => $this->moderated_by,
            'moderation_notes' => $this->moderation_notes
        );
    }

    /**
     * 获取标签的JSON字符串
     *
     * @return string
     */
    public function get_tags_json() {
        return json_encode($this->tags);
    }

    /**
     * 验证文章数据
     *
     * @return array 验证错误数组，空数组表示验证通过
     */
    public function validate() {
        $errors = array();

        if (empty($this->title)) {
            $errors[] = '文章标题不能为空';
        }

        if (empty($this->content)) {
            $errors[] = '文章内容不能为空';
        }

        if (empty($this->author_id)) {
            $errors[] = '文章作者不能为空';
        }

        if (!AMS_V2_Article_Status::is_valid_status($this->status)) {
            $errors[] = '文章状态无效';
        }

        return $errors;
    }
}