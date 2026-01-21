<?php
/**
 * 审核等级枚举类
 *
 * @package Article_Management_V2
 * @subpackage Enums
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 审核等级枚举类
 */
class AMS_V2_Moderation_Level {

    /**
     * 宽松审核等级
     * 仅拦截高危敏感词
     */
    const LENIENT = 'lenient';

    /**
     * 默认审核等级
     * 拦截高危词并替换普通敏感词
     */
    const DEFAULT_LEVEL = 'default';

    /**
     * 严格审核等级
     * 拦截所有敏感词并拒绝提交
     */
    const STRICT = 'strict';

    /**
     * 获取所有审核等级
     *
     * @return array
     */
    public static function get_all_levels() {
        return array(
            self::LENIENT,
            self::DEFAULT_LEVEL,
            self::STRICT
        );
    }

    /**
     * 获取审核等级显示名称映射
     *
     * @return array
     */
    public static function get_level_names() {
        return array(
            self::LENIENT => '宽松',
            self::DEFAULT_LEVEL => '默认',
            self::STRICT => '严格'
        );
    }

    /**
     * 获取审核等级描述映射
     *
     * @return array
     */
    public static function get_level_descriptions() {
        return array(
            self::LENIENT => '仅拦截高危敏感词，允许普通敏感词通过',
            self::DEFAULT_LEVEL => '拦截高危敏感词，替换普通敏感词后允许通过',
            self::STRICT => '拦截所有敏感词，包含敏感词的内容将被拒绝'
        );
    }

    /**
     * 获取审核等级显示名称
     *
     * @param string $level 审核等级
     * @return string
     */
    public static function get_level_name($level) {
        $names = self::get_level_names();
        return isset($names[$level]) ? $names[$level] : $level;
    }

    /**
     * 获取审核等级描述
     *
     * @param string $level 审核等级
     * @return string
     */
    public static function get_level_description($level) {
        $descriptions = self::get_level_descriptions();
        return isset($descriptions[$level]) ? $descriptions[$level] : '';
    }

    /**
     * 验证审核等级是否有效
     *
     * @param string $level 审核等级
     * @return bool
     */
    public static function is_valid_level($level) {
        return in_array($level, self::get_all_levels());
    }

    /**
     * 获取审核等级的处理策略
     *
     * @param string $level 审核等级
     * @return array 处理策略配置
     */
    public static function get_level_strategy($level) {
        switch ($level) {
            case self::LENIENT:
                return array(
                    'block_high_risk' => true,
                    'block_normal' => false,
                    'replace_high_risk' => false,
                    'replace_normal' => false,
                    'allow_submission' => true
                );

            case self::DEFAULT_LEVEL:
                return array(
                    'block_high_risk' => true,
                    'block_normal' => false,
                    'replace_high_risk' => false,
                    'replace_normal' => true,
                    'allow_submission' => true
                );

            case self::STRICT:
                return array(
                    'block_high_risk' => true,
                    'block_normal' => true,
                    'replace_high_risk' => false,
                    'replace_normal' => false,
                    'allow_submission' => false
                );

            default:
                return self::get_level_strategy(self::DEFAULT_LEVEL);
        }
    }

    /**
     * 获取审核等级的CSS类名
     *
     * @param string $level 审核等级
     * @return string
     */
    public static function get_css_class($level) {
        switch ($level) {
            case self::LENIENT:
                return 'moderation-level-lenient';
            case self::DEFAULT_LEVEL:
                return 'moderation-level-default';
            case self::STRICT:
                return 'moderation-level-strict';
            default:
                return 'moderation-level-unknown';
        }
    }

    /**
     * 获取审核等级的颜色标签
     *
     * @param string $level 审核等级
     * @return string
     */
    public static function get_color_label($level) {
        switch ($level) {
            case self::LENIENT:
                return 'success';
            case self::DEFAULT_LEVEL:
                return 'warning';
            case self::STRICT:
                return 'danger';
            default:
                return 'secondary';
        }
    }

    /**
     * 获取审核等级的图标
     *
     * @param string $level 审核等级
     * @return string
     */
    public static function get_icon($level) {
        switch ($level) {
            case self::LENIENT:
                return 'dashicons-yes-alt';
            case self::DEFAULT_LEVEL:
                return 'dashicons-warning';
            case self::STRICT:
                return 'dashicons-dismiss';
            default:
                return 'dashicons-admin-generic';
        }
    }

    /**
     * 获取默认审核等级
     *
     * @return string
     */
    public static function get_default_level() {
        return self::DEFAULT_LEVEL;
    }

    /**
     * 比较两个审核等级的严格程度
     *
     * @param string $level1 审核等级1
     * @param string $level2 审核等级2
     * @return int -1: level1更宽松, 0: 相同, 1: level1更严格
     */
    public static function compare_strictness($level1, $level2) {
        $levels = array(
            self::LENIENT => 1,
            self::DEFAULT_LEVEL => 2,
            self::STRICT => 3
        );

        $value1 = isset($levels[$level1]) ? $levels[$level1] : 2;
        $value2 = isset($levels[$level2]) ? $levels[$level2] : 2;

        if ($value1 < $value2) {
            return -1;
        } elseif ($value1 > $value2) {
            return 1;
        } else {
            return 0;
        }
    }
}