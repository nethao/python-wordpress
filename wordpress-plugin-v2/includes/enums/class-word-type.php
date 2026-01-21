<?php
/**
 * 敏感词类型枚举类
 *
 * @package Article_Management_V2
 * @subpackage Enums
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 敏感词类型枚举类
 */
class AMS_V2_Word_Type {

    /**
     * 普通敏感词
     */
    const NORMAL = 'normal';

    /**
     * 高危敏感词
     */
    const HIGH_RISK = 'high_risk';

    /**
     * 获取所有类型
     *
     * @return array
     */
    public static function get_all_types() {
        return array(
            self::NORMAL,
            self::HIGH_RISK
        );
    }

    /**
     * 获取类型显示名称映射
     *
     * @return array
     */
    public static function get_type_names() {
        return array(
            self::NORMAL => '普通敏感词',
            self::HIGH_RISK => '高危敏感词'
        );
    }

    /**
     * 获取类型显示名称
     *
     * @param string $type 类型
     * @return string
     */
    public static function get_type_name($type) {
        $names = self::get_type_names();
        return isset($names[$type]) ? $names[$type] : $type;
    }

    /**
     * 验证类型是否有效
     *
     * @param string $type 类型
     * @return bool
     */
    public static function is_valid_type($type) {
        return in_array($type, self::get_all_types());
    }

    /**
     * 获取类型的默认替换词
     *
     * @param string $type 类型
     * @return string
     */
    public static function get_default_replacement($type) {
        switch ($type) {
            case self::HIGH_RISK:
                return '***';
            case self::NORMAL:
                return '**';
            default:
                return '*';
        }
    }

    /**
     * 获取类型的CSS类名
     *
     * @param string $type 类型
     * @return string
     */
    public static function get_css_class($type) {
        switch ($type) {
            case self::HIGH_RISK:
                return 'word-type-high-risk';
            case self::NORMAL:
                return 'word-type-normal';
            default:
                return 'word-type-unknown';
        }
    }

    /**
     * 获取类型的颜色标签
     *
     * @param string $type 类型
     * @return string
     */
    public static function get_color_label($type) {
        switch ($type) {
            case self::HIGH_RISK:
                return 'danger';
            case self::NORMAL:
                return 'warning';
            default:
                return 'secondary';
        }
    }
}