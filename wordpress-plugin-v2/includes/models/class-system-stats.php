<?php
/**
 * 系统统计模型类
 *
 * @package Article_Management_V2
 * @subpackage Models
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 系统统计模型类
 */
class AMS_V2_System_Stats {

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
     * 活跃用户数
     *
     * @var int
     */
    private $active_users;

    /**
     * 新注册用户数
     *
     * @var int
     */
    private $new_users;

    /**
     * 总文章数
     *
     * @var int
     */
    private $total_articles;

    /**
     * 新增文章数
     *
     * @var int
     */
    private $new_articles;

    /**
     * 已发布文章数
     *
     * @var int
     */
    private $published_articles;

    /**
     * 待审核文章数
     *
     * @var int
     */
    private $pending_articles;

    /**
     * 敏感词拦截次数
     *
     * @var int
     */
    private $blocked_submissions;

    /**
     * 活跃站点数
     *
     * @var int
     */
    private $active_sites;

    /**
     * API调用次数
     *
     * @var int
     */
    private $api_calls;

    /**
     * 系统错误数
     *
     * @var int
     */
    private $system_errors;

    /**
     * 构造函数
     *
     * @param array $data 统计数据
     */
    public function __construct($data = array()) {
        $this->date = isset($data['date']) ? $data['date'] : date('Y-m-d');
        $this->period = isset($data['period']) ? $data['period'] : 'daily';
        $this->active_users = isset($data['active_users']) ? (int) $data['active_users'] : 0;
        $this->new_users = isset($data['new_users']) ? (int) $data['new_users'] : 0;
        $this->total_articles = isset($data['total_articles']) ? (int) $data['total_articles'] : 0;
        $this->new_articles = isset($data['new_articles']) ? (int) $data['new_articles'] : 0;
        $this->published_articles = isset($data['published_articles']) ? (int) $data['published_articles'] : 0;
        $this->pending_articles = isset($data['pending_articles']) ? (int) $data['pending_articles'] : 0;
        $this->blocked_submissions = isset($data['blocked_submissions']) ? (int) $data['blocked_submissions'] : 0;
        $this->active_sites = isset($data['active_sites']) ? (int) $data['active_sites'] : 0;
        $this->api_calls = isset($data['api_calls']) ? (int) $data['api_calls'] : 0;
        $this->system_errors = isset($data['system_errors']) ? (int) $data['system_errors'] : 0;
    }

    // Getter方法
    public function get_date() { return $this->date; }
    public function get_period() { return $this->period; }
    public function get_active_users() { return $this->active_users; }
    public function get_new_users() { return $this->new_users; }
    public function get_total_articles() { return $this->total_articles; }
    public function get_new_articles() { return $this->new_articles; }
    public function get_published_articles() { return $this->published_articles; }
    public function get_pending_articles() { return $this->pending_articles; }
    public function get_blocked_submissions() { return $this->blocked_submissions; }
    public function get_active_sites() { return $this->active_sites; }
    public function get_api_calls() { return $this->api_calls; }
    public function get_system_errors() { return $this->system_errors; }

    /**
     * 计算发布率
     *
     * @return float
     */
    public function get_publish_rate() {
        if ($this->new_articles == 0) {
            return 0.0;
        }
        return round(($this->published_articles / $this->new_articles) * 100, 2);
    }

    /**
     * 计算用户活跃度
     *
     * @return float
     */
    public function get_user_activity_rate() {
        $total_users = $this->active_users + $this->new_users;
        if ($total_users == 0) {
            return 0.0;
        }
        return round(($this->active_users / $total_users) * 100, 2);
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function to_array() {
        return array(
            'date' => $this->date,
            'period' => $this->period,
            'active_users' => $this->active_users,
            'new_users' => $this->new_users,
            'total_articles' => $this->total_articles,
            'new_articles' => $this->new_articles,
            'published_articles' => $this->published_articles,
            'pending_articles' => $this->pending_articles,
            'blocked_submissions' => $this->blocked_submissions,
            'active_sites' => $this->active_sites,
            'api_calls' => $this->api_calls,
            'system_errors' => $this->system_errors,
            'publish_rate' => $this->get_publish_rate(),
            'user_activity_rate' => $this->get_user_activity_rate()
        );
    }
}