<?php
/**
 * 角色模型类
 *
 * @package Article_Management_V2
 * @subpackage Models
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 角色模型类
 */
class AMS_V2_Role {

    /**
     * 角色ID
     *
     * @var int
     */
    private $id;

    /**
     * 角色名称
     *
     * @var string
     */
    private $name;

    /**
     * 显示名称
     *
     * @var string
     */
    private $display_name;

    /**
     * 角色描述
     *
     * @var string
     */
    private $description;

    /**
     * 权限列表
     *
     * @var array
     */
    private $capabilities = array();

    /**
     * 是否为系统角色
     *
     * @var bool
     */
    private $is_system;

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
     * 构造函数
     *
     * @param array $data 角色数据
     */
    public function __construct($data = array()) {
        if (!empty($data)) {
            $this->populate($data);
        }
    }

    /**
     * 填充角色数据
     *
     * @param array $data 角色数据
     */
    public function populate($data) {
        $this->id = isset($data['id']) ? (int) $data['id'] : 0;
        $this->name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
        $this->display_name = isset($data['display_name']) ? sanitize_text_field($data['display_name']) : '';
        $this->description = isset($data['description']) ? sanitize_textarea_field($data['description']) : '';
        $this->is_system = isset($data['is_system']) ? (bool) $data['is_system'] : false;
        
        // 处理权限列表
        if (isset($data['capabilities'])) {
            if (is_string($data['capabilities'])) {
                $this->capabilities = json_decode($data['capabilities'], true) ?: array();
            } elseif (is_array($data['capabilities'])) {
                $this->capabilities = $data['capabilities'];
            }
        }
        
        // 处理日期时间
        if (isset($data['created_at'])) {
            $this->created_at = new DateTime($data['created_at']);
        }
        if (isset($data['updated_at'])) {
            $this->updated_at = new DateTime($data['updated_at']);
        }
    }

    /**
     * 获取角色ID
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * 设置角色ID
     *
     * @param int $id 角色ID
     */
    public function set_id($id) {
        $this->id = (int) $id;
    }

    /**
     * 获取角色名称
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * 设置角色名称
     *
     * @param string $name 角色名称
     */
    public function set_name($name) {
        $this->name = sanitize_text_field($name);
    }

    /**
     * 获取显示名称
     *
     * @return string
     */
    public function get_display_name() {
        return $this->display_name;
    }

    /**
     * 设置显示名称
     *
     * @param string $display_name 显示名称
     */
    public function set_display_name($display_name) {
        $this->display_name = sanitize_text_field($display_name);
    }

    /**
     * 获取角色描述
     *
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * 设置角色描述
     *
     * @param string $description 角色描述
     */
    public function set_description($description) {
        $this->description = sanitize_textarea_field($description);
    }

    /**
     * 获取权限列表
     *
     * @return array
     */
    public function get_capabilities() {
        return $this->capabilities;
    }

    /**
     * 设置权限列表
     *
     * @param array $capabilities 权限列表
     */
    public function set_capabilities($capabilities) {
        $this->capabilities = is_array($capabilities) ? $capabilities : array();
    }

    /**
     * 添加权限
     *
     * @param string $capability 权限名称
     */
    public function add_capability($capability) {
        if (!in_array($capability, $this->capabilities)) {
            $this->capabilities[] = sanitize_text_field($capability);
        }
    }

    /**
     * 移除权限
     *
     * @param string $capability 权限名称
     */
    public function remove_capability($capability) {
        $key = array_search($capability, $this->capabilities);
        if ($key !== false) {
            unset($this->capabilities[$key]);
            $this->capabilities = array_values($this->capabilities);
        }
    }

    /**
     * 检查是否有指定权限
     *
     * @param string $capability 权限名称
     * @return bool
     */
    public function has_capability($capability) {
        return in_array($capability, $this->capabilities);
    }

    /**
     * 是否为系统角色
     *
     * @return bool
     */
    public function is_system() {
        return $this->is_system;
    }

    /**
     * 设置系统角色标识
     *
     * @param bool $is_system 是否为系统角色
     */
    public function set_system($is_system) {
        $this->is_system = (bool) $is_system;
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
     * 转换为数组
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'capabilities' => $this->capabilities,
            'is_system' => $this->is_system,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null
        );
    }

    /**
     * 获取权限的JSON字符串
     *
     * @return string
     */
    public function get_capabilities_json() {
        return json_encode($this->capabilities);
    }
}