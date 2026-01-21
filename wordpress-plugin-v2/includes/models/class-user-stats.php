<?php
/**
 * 用户统计模型类
 *
 * @package Article_Management_V2
 * @subpackage Models
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 用户统计模型类
 */
class AMS_V2_User_Stats {

    /**
     * 用户ID
     *
     * @var int
     */
    private $user_id;

    /**
     * 统计日期
     *
     * @var string
     */
    private $date;

    /**
     * 统计周期
     *
     * @var string
     */
    private $period;

    /**
     * 总提交数
     *
     * @var int
     */
    private $total_submissions;

    /**
     * 审核通过数
     *
     * @var int
     */
    private $approved_count;

    /**
     * 审核拒绝数
     *
     * @var int
     */
    private $rejected_count;

    /**
     * 已发布数
     *
     * @var int
     */
    private $published_count;

    /**
     * 敏感词拦截数
     *
     * @var int
     */
    private $blocked_count;

    /**
     * 草稿数
     *
     * @var int
     */
    private $draft_count;

    /**
     * 平均审核时间（小时）
     *
     * @var float
     */
    private $avg_review_time;

    /**
     * 构造函数
     *
     * @param array $data 统计数据
     */
    public function __construct($data = array()) {
        $this->user_id = isset($data['user_id']) ? (int) $data['user_id'] : 0;
        $this->date = isset($data['date']) ? $data['date'] : date('Y-m-d');
        $this->period = isset($data['period']) ? $data['period'] : 'daily';
        $this->total_submissions = isset($data['total_submissions']) ? (int) $data['total_submissions'] : 0;
        $this->approved_count = isset($data['approved_count']) ? (int) $data['approved_count'] : 0;
        $this->rejected_count = isset($data['rejected_count']) ? (int) $data['rejected_count'] : 0;
        $this->published_count = isset($data['published_count']) ? (int) $data['published_count'] : 0;
        $this->blocked_count = isset($data['blocked_count']) ? (int) $data['blocked_count'] : 0;
        $this->draft_count = isset($data['draft_count']) ? (int) $data['draft_count'] : 0;
        $this->avg_review_time = isset($data['avg_review_time']) ? (float) $data['avg_review_time'] : 0.0;
    }

    /**
     * 获取用户ID
     *
     * @return int
     */
    public function get_user_id() {
        return $this->user_id;
    }

    /**
     * 获取统计日期
     *
     * @return string
     */
    public function get_date() {
        return $this->date;
    }

    /**
     * 获取统计周期
     *
     * @return string
     */
    public function get_period() {
        return $this->period;
    }

    /**
     * 获取总提交数
     *
     * @return int
     */
    public function get_total_submissions() {
        return $this->total_submissions;
    }

    /**
     * 获取审核通过数
     *
     * @return int
     */
    public function get_approved_count() {
        return $this->approved_count;
    }

    /**
     * 获取审核拒绝数
     *
     * @return int
     */
    public function get_rejected_count() {
        return $this->rejected_count;
    }

    /**
     * 获取已发布数
     *
     * @return int
     */
    public function get_published_count() {
        return $this->published_count;
    }

    /**
     * 获取敏感词拦截数
     *
     * @return int
     */
    public function get_blocked_count() {
        return $this->blocked_count;
    }

    /**
     * 获取草稿数
     *
     * @return int
     */
    public function get_draft_count() {
        return $this->draft_count;
    }

    /**
     * 获取平均审核时间
     *
     * @return float
     */
    public function get_avg_review_time() {
        return $this->avg_review_time;
    }

    /**
     * 计算通过率
     *
     * @return float
     */
    public function get_approval_rate() {
        if ($this->total_submissions == 0) {
            return 0.0;
        }
        return round(($this->approved_count / $this->total_submissions) * 100, 2);
    }

    /**
     * 计算拒绝率
     *
     * @return float
     */
    public function get_rejection_rate() {
        if ($this->total_submissions == 0) {
            return 0.0;
        }
        return round(($this->rejected_count / $this->total_submissions) * 100, 2);
    }

    /**
     * 计算拦截率
     *
     * @return float
     */
    public function get_block_rate() {
        if ($this->total_submissions == 0) {
            return 0.0;
        }
        return round(($this->blocked_count / $this->total_submissions) * 100, 2);
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function to_array() {
        return array(
            'user_id' => $this->user_id,
            'date' => $this->date,
            'period' => $this->period,
            'total_submissions' => $this->total_submissions,
            'approved_count' => $this->approved_count,
            'rejected_count' => $this->rejected_count,
            'published_count' => $this->published_count,
            'blocked_count' => $this->blocked_count,
            'draft_count' => $this->draft_count,
            'avg_review_time' => $this->avg_review_time,
            'approval_rate' => $this->get_approval_rate(),
            'rejection_rate' => $this->get_rejection_rate(),
            'block_rate' => $this->get_block_rate()
        );
    }

    /**
     * 验证统计数据
     *
     * @return bool|WP_Error
     */
    public function validate() {
        if ($this->user_id <= 0) {
            return new WP_Error('invalid_user_id', '用户ID无效');
        }

        if (empty($this->date)) {
            return new WP_Error('invalid_date', '统计日期不能为空');
        }

        if (!in_array($this->period, array('daily', 'weekly', 'monthly', 'yearly'))) {
            return new WP_Error('invalid_period', '统计周期无效');
        }

        if ($this->total_submissions < 0) {
            return new WP_Error('invalid_submissions', '总提交数不能为负数');
        }

        return true;
    }
}