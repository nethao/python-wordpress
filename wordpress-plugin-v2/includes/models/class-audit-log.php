<?php
/**
 * 审计日志模型类
 *
 * @package Article_Management_V2
 * @subpackage Models
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 审计日志模型类
 */
class AMS_V2_Audit_Log {

    /**
     * 日志ID
     *
     * @var int
     */
    private $id;

    /**
     * 操作类型
     *
     * @var string
     */
    private $action;

    /**
     * 操作用户ID
     *
     * @var int
     */
    private $user_id;

    /**
     * 对象类型
     *
     * @var string
     */
    private $object_type;

    /**
     * 对象ID
     *
     * @var int
     */
    private $object_id;

    /**
     * 旧值
     *
     * @var array
     */
    private $old_values;

    /**
     * 新值
     *
     * @var array
     */
    private $new_values;

    /**
     * IP地址
     *
     * @var string
     */
    private $ip_address;

    /**
     * 用户代理
     *
     * @var string
     */
    private $user_agent;

    /**
     * 创建时间
     *
     * @var string
     */
    private $created_at;

    /**
     * 构造函数
     *
     * @param array $data 日志数据
     */
    public function __construct($data = []) {
        $this->id = $data['id'] ?? 0;
        $this->action = $data['action'] ?? '';
        $this->user_id = $data['user_id'] ?? 0;
        $this->object_type = $data['object_type'] ?? null;
        $this->object_id = $data['object_id'] ?? null;
        $this->old_values = $data['old_values'] ?? null;
        $this->new_values = $data['new_values'] ?? null;
        $this->ip_address = $data['ip_address'] ?? null;
        $this->user_agent = $data['user_agent'] ?? null;
        $this->created_at = $data['created_at'] ?? current_time('mysql');
    }

    /**
     * 获取日志ID
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * 设置日志ID
     *
     * @param int $id 日志ID
     */
    public function set_id($id) {
        $this->id = (int) $id;
    }

    /**
     * 获取操作类型
     *
     * @return string
     */
    public function get_action() {
        return $this->action;
    }

    /**
     * 设置操作类型
     *
     * @param string $action 操作类型
     */
    public function set_action($action) {
        $this->action = sanitize_text_field($action);
    }

    /**
     * 获取操作用户ID
     *
     * @return int
     */
    public function get_user_id() {
        return $this->user_id;
    }

    /**
     * 设置操作用户ID
     *
     * @param int $user_id 用户ID
     */
    public function set_user_id($user_id) {
        $this->user_id = (int) $user_id;
    }

    /**
     * 获取对象类型
     *
     * @return string|null
     */
    public function get_object_type() {
        return $this->object_type;
    }

    /**
     * 设置对象类型
     *
     * @param string $object_type 对象类型
     */
    public function set_object_type($object_type) {
        $this->object_type = $object_type ? sanitize_text_field($object_type) : null;
    }

    /**
     * 获取对象ID
     *
     * @return int|null
     */
    public function get_object_id() {
        return $this->object_id;
    }

    /**
     * 设置对象ID
     *
     * @param int $object_id 对象ID
     */
    public function set_object_id($object_id) {
        $this->object_id = $object_id ? (int) $object_id : null;
    }

    /**
     * 获取旧值
     *
     * @return array|null
     */
    public function get_old_values() {
        return $this->old_values;
    }

    /**
     * 设置旧值
     *
     * @param array $old_values 旧值
     */
    public function set_old_values($old_values) {
        $this->old_values = is_array($old_values) ? $old_values : null;
    }

    /**
     * 获取新值
     *
     * @return array|null
     */
    public function get_new_values() {
        return $this->new_values;
    }

    /**
     * 设置新值
     *
     * @param array $new_values 新值
     */
    public function set_new_values($new_values) {
        $this->new_values = is_array($new_values) ? $new_values : null;
    }

    /**
     * 获取IP地址
     *
     * @return string|null
     */
    public function get_ip_address() {
        return $this->ip_address;
    }

    /**
     * 设置IP地址
     *
     * @param string $ip_address IP地址
     */
    public function set_ip_address($ip_address) {
        $this->ip_address = $ip_address ? sanitize_text_field($ip_address) : null;
    }

    /**
     * 获取用户代理
     *
     * @return string|null
     */
    public function get_user_agent() {
        return $this->user_agent;
    }

    /**
     * 设置用户代理
     *
     * @param string $user_agent 用户代理
     */
    public function set_user_agent($user_agent) {
        $this->user_agent = $user_agent ? sanitize_text_field($user_agent) : null;
    }

    /**
     * 获取创建时间
     *
     * @return string
     */
    public function get_created_at() {
        return $this->created_at;
    }

    /**
     * 设置创建时间
     *
     * @param string $created_at 创建时间
     */
    public function set_created_at($created_at) {
        $this->created_at = $created_at;
    }

    /**
     * 获取操作描述
     *
     * @return string
     */
    public function get_action_description() {
        $descriptions = [
            'user_create' => '创建用户',
            'user_update' => '更新用户',
            'user_delete' => '删除用户',
            'user_role_change' => '变更用户角色',
            'sensitive_word_create' => '添加敏感词',
            'sensitive_word_update' => '更新敏感词',
            'sensitive_word_delete' => '删除敏感词',
            'sensitive_word_import' => '导入敏感词',
            'sensitive_word_export' => '导出敏感词',
            'moderation_level_change' => '修改审核等级',
            'cleanup_auto' => '自动清理',
            'cleanup_manual' => '手动清理',
            'system_error' => '系统错误',
            'api_call' => 'API调用',
            'login' => '用户登录',
            'logout' => '用户登出',
            'article_create' => '创建文章',
            'article_update' => '更新文章',
            'article_delete' => '删除文章',
            'article_moderate' => '审核文章',
            'site_create' => '添加站点',
            'site_update' => '更新站点',
            'site_delete' => '删除站点'
        ];

        return $descriptions[$this->action] ?? $this->action;
    }

    /**
     * 获取用户信息
     *
     * @return array|null
     */
    public function get_user_info() {
        if (!$this->user_id) {
            return null;
        }

        $user = get_user_by('id', $this->user_id);
        if (!$user) {
            return ['id' => $this->user_id, 'username' => '未知用户', 'display_name' => '未知用户'];
        }

        return [
            'id' => $user->ID,
            'username' => $user->user_login,
            'display_name' => $user->display_name ?: $user->user_login,
            'email' => $user->user_email
        ];
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function to_array() {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'action_description' => $this->get_action_description(),
            'user_id' => $this->user_id,
            'user_info' => $this->get_user_info(),
            'object_type' => $this->object_type,
            'object_id' => $this->object_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at
        ];
    }

    /**
     * 验证日志数据
     *
     * @return bool|WP_Error
     */
    public function validate() {
        if (empty($this->action)) {
            return new WP_Error('invalid_action', '操作类型不能为空');
        }

        if ($this->user_id <= 0) {
            return new WP_Error('invalid_user_id', '用户ID无效');
        }

        if (strlen($this->action) > 50) {
            return new WP_Error('action_too_long', '操作类型长度不能超过50个字符');
        }

        return true;
    }

    /**
     * 从数据库行创建实例
     *
     * @param object $row 数据库行
     * @return AMS_V2_Audit_Log
     */
    public static function from_db_row($row) {
        return new self([
            'id' => $row->id,
            'action' => $row->action,
            'user_id' => $row->user_id,
            'object_type' => $row->resource_type ?? null, // 映射数据库字段
            'object_id' => $row->resource_id ?? null,
            'old_values' => $row->old_values ? json_decode($row->old_values, true) : null,
            'new_values' => $row->new_values ? json_decode($row->new_values, true) : null,
            'ip_address' => $row->ip_address,
            'user_agent' => $row->user_agent,
            'created_at' => $row->created_at
        ]);
    }
}