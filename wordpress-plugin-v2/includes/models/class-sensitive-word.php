<?php
/**
 * 敏感词模型类
 *
 * @package Article_Management_V2
 * @subpackage Models
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 敏感词模型类
 */
class AMS_V2_Sensitive_Word {

    /**
     * 敏感词ID
     *
     * @var int
     */
    public $id;

    /**
     * 敏感词内容
     *
     * @var string
     */
    public $word;

    /**
     * 敏感词类型
     *
     * @var string
     */
    public $type;

    /**
     * 替换词
     *
     * @var string
     */
    public $replacement;

    /**
     * 是否为内置词汇
     *
     * @var bool
     */
    public $is_builtin;

    /**
     * 是否激活
     *
     * @var bool
     */
    public $is_active;

    /**
     * 创建时间
     *
     * @var string
     */
    public $created_at;

    /**
     * 更新时间
     *
     * @var string
     */
    public $updated_at;

    /**
     * 创建者ID
     *
     * @var int
     */
    public $created_by;

    /**
     * 构造函数
     *
     * @param array $data 敏感词数据
     */
    public function __construct($data = array()) {
        if (!empty($data)) {
            $this->fill($data);
        }
    }

    /**
     * 填充数据
     *
     * @param array $data 数据数组
     */
    public function fill($data) {
        $this->id = isset($data['id']) ? (int) $data['id'] : 0;
        $this->word = isset($data['word']) ? sanitize_text_field($data['word']) : '';
        $this->type = isset($data['type']) ? sanitize_text_field($data['type']) : 'normal';
        $this->replacement = isset($data['replacement']) ? sanitize_text_field($data['replacement']) : '';
        $this->is_builtin = isset($data['is_builtin']) ? (bool) $data['is_builtin'] : false;
        $this->is_active = isset($data['is_active']) ? (bool) $data['is_active'] : true;
        $this->created_at = isset($data['created_at']) ? $data['created_at'] : '';
        $this->updated_at = isset($data['updated_at']) ? $data['updated_at'] : '';
        $this->created_by = isset($data['created_by']) ? (int) $data['created_by'] : 0;
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id' => $this->id,
            'word' => $this->word,
            'type' => $this->type,
            'replacement' => $this->replacement,
            'is_builtin' => $this->is_builtin,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_by' => $this->created_by
        );
    }

    /**
     * 验证数据
     *
     * @return array 验证错误数组
     */
    public function validate() {
        $errors = array();

        if (empty($this->word)) {
            $errors[] = '敏感词内容不能为空';
        }

        if (mb_strlen($this->word) > 100) {
            $errors[] = '敏感词内容不能超过100个字符';
        }

        if (!in_array($this->type, array('normal', 'high_risk'))) {
            $errors[] = '敏感词类型必须是 normal 或 high_risk';
        }

        if (!empty($this->replacement) && mb_strlen($this->replacement) > 100) {
            $errors[] = '替换词不能超过100个字符';
        }

        return $errors;
    }

    /**
     * 获取类型显示名称
     *
     * @return string
     */
    public function get_type_display_name() {
        $type_names = array(
            'normal' => '普通敏感词',
            'high_risk' => '高危敏感词'
        );

        return isset($type_names[$this->type]) ? $type_names[$this->type] : $this->type;
    }

    /**
     * 是否为高危敏感词
     *
     * @return bool
     */
    public function is_high_risk() {
        return $this->type === 'high_risk';
    }

    /**
     * 是否为普通敏感词
     *
     * @return bool
     */
    public function is_normal() {
        return $this->type === 'normal';
    }

    /**
     * 获取默认替换词
     *
     * @return string
     */
    public function get_default_replacement() {
        if (!empty($this->replacement)) {
            return $this->replacement;
        }

        return $this->is_high_risk() ? '***' : '**';
    }
}