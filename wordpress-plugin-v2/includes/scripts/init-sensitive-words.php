<?php
/**
 * 敏感词库初始化脚本
 *
 * @package Article_Management_V2
 * @subpackage Scripts
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 敏感词库初始化类
 */
class AMS_V2_Sensitive_Words_Initializer {

    /**
     * 敏感词管理器
     *
     * @var AMS_V2_Sensitive_Word_Manager
     */
    private $word_manager;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->word_manager = new AMS_V2_Sensitive_Word_Manager();
    }

    /**
     * 初始化敏感词库
     *
     * @return array 初始化结果
     */
    public function initialize() {
        $results = array(
            'success' => true,
            'message' => '',
            'statistics' => array()
        );

        try {
            // 初始化内置敏感词
            $builtin_result = $this->word_manager->initialize_builtin_words();
            
            if ($builtin_result) {
                $results['message'] = '敏感词库初始化成功';
            } else {
                $results['message'] = '敏感词库已存在，无需重复初始化';
            }

            // 获取统计信息
            $results['statistics'] = $this->word_manager->get_word_statistics();

        } catch (Exception $e) {
            $results['success'] = false;
            $results['message'] = '敏感词库初始化失败: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * 从文件导入敏感词
     *
     * @param string $file_path 文件路径
     * @param string $type 词汇类型
     * @return array 导入结果
     */
    public function import_from_file($file_path, $type = AMS_V2_Word_Type::NORMAL) {
        if (!file_exists($file_path)) {
            return array(
                'success' => false,
                'message' => '文件不存在: ' . $file_path
            );
        }

        $content = file_get_contents($file_path);
        if ($content === false) {
            return array(
                'success' => false,
                'message' => '无法读取文件: ' . $file_path
            );
        }

        // 解析文件内容
        $words = $this->parse_words_from_content($content);
        
        if (empty($words)) {
            return array(
                'success' => false,
                'message' => '文件中没有找到有效的敏感词'
            );
        }

        // 批量导入
        return $this->word_manager->import_words($words, $type, get_current_user_id());
    }

    /**
     * 从内容中解析敏感词
     *
     * @param string $content 文件内容
     * @return array 敏感词数组
     */
    private function parse_words_from_content($content) {
        $words = array();
        
        // 尝试解析JSON格式
        $json_data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
            foreach ($json_data as $item) {
                if (is_string($item)) {
                    $words[] = trim($item);
                } elseif (is_array($item) && isset($item['word'])) {
                    $words[] = array(
                        'word' => trim($item['word']),
                        'replacement' => isset($item['replacement']) ? trim($item['replacement']) : ''
                    );
                }
            }
            return $words;
        }

        // 尝试解析CSV格式
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') { // 跳过空行和注释
                continue;
            }

            $parts = str_getcsv($line);
            if (count($parts) >= 1) {
                $word_data = array('word' => trim($parts[0]));
                if (count($parts) >= 2) {
                    $word_data['replacement'] = trim($parts[1]);
                }
                $words[] = $word_data;
            }
        }

        // 如果CSV解析失败，尝试按行分割
        if (empty($words)) {
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && $line[0] !== '#') {
                    $words[] = $line;
                }
            }
        }

        return array_filter($words);
    }

    /**
     * 导出敏感词到文件
     *
     * @param string $file_path 文件路径
     * @param string $format 导出格式 (json|csv|txt)
     * @param array $filters 过滤条件
     * @return array 导出结果
     */
    public function export_to_file($file_path, $format = 'json', $filters = array()) {
        $words_data = $this->word_manager->get_words(array_merge(array(
            'limit' => 10000 // 导出大量数据
        ), $filters));

        if (empty($words_data['words'])) {
            return array(
                'success' => false,
                'message' => '没有找到符合条件的敏感词'
            );
        }

        $content = '';
        switch ($format) {
            case 'json':
                $export_data = array();
                foreach ($words_data['words'] as $word) {
                    $export_data[] = array(
                        'word' => $word->word,
                        'type' => $word->type,
                        'replacement' => $word->replacement,
                        'is_builtin' => $word->is_builtin
                    );
                }
                $content = json_encode($export_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;

            case 'csv':
                $content = "词汇,类型,替换词,是否内置\n";
                foreach ($words_data['words'] as $word) {
                    $content .= sprintf(
                        "%s,%s,%s,%s\n",
                        $word->word,
                        $word->get_type_display_name(),
                        $word->replacement,
                        $word->is_builtin ? '是' : '否'
                    );
                }
                break;

            case 'txt':
                foreach ($words_data['words'] as $word) {
                    $content .= $word->word . "\n";
                }
                break;

            default:
                return array(
                    'success' => false,
                    'message' => '不支持的导出格式: ' . $format
                );
        }

        $result = file_put_contents($file_path, $content);
        
        if ($result === false) {
            return array(
                'success' => false,
                'message' => '无法写入文件: ' . $file_path
            );
        }

        return array(
            'success' => true,
            'message' => sprintf('成功导出 %d 个敏感词到 %s', count($words_data['words']), $file_path),
            'exported_count' => count($words_data['words'])
        );
    }

    /**
     * 获取预置敏感词库列表
     *
     * @return array 预置敏感词库
     */
    public function get_preset_word_libraries() {
        return array(
            'basic' => array(
                'name' => '基础敏感词库',
                'description' => '包含常见的不当言论和违规内容',
                'high_risk_words' => array(
                    '暴力', '恐怖', '极端', '仇恨', '歧视', '诈骗', '赌博', '毒品',
                    '色情', '淫秽', '反动', '分裂', '邪教', '恐怖主义', '暴力革命',
                    '推翻政府', '武装起义', '血腥', '杀戮', '屠杀', '自杀', '自残'
                ),
                'normal_words' => array(
                    '垃圾', '废物', '愚蠢', '白痴', '傻瓜', '混蛋', '笨蛋', '蠢货',
                    '死鬼', '该死', '滚蛋', '闭嘴', '放屁', '胡说', '鬼话', '扯淡'
                )
            ),
            'extended' => array(
                'name' => '扩展敏感词库',
                'description' => '包含更全面的敏感词汇',
                'high_risk_words' => array(
                    '暴力', '恐怖', '极端', '仇恨', '歧视', '诈骗', '赌博', '毒品',
                    '色情', '淫秽', '反动', '分裂', '邪教', '恐怖主义', '暴力革命',
                    '推翻政府', '武装起义', '血腥', '杀戮', '屠杀', '自杀', '自残',
                    '炸弹', '爆炸', '枪支', '武器', '毒气', '生化武器', '核武器',
                    '绑架', '劫持', '暗杀', '谋杀', '强奸', '性侵', '虐待', '酷刑'
                ),
                'normal_words' => array(
                    '垃圾', '废物', '愚蠢', '白痴', '傻瓜', '混蛋', '笨蛋', '蠢货',
                    '死鬼', '该死', '滚蛋', '闭嘴', '放屁', '胡说', '鬼话', '扯淡',
                    '脑残', '智障', '弱智', '神经病', '疯子', '变态', '恶心', '讨厌',
                    '烦人', '无聊', '幼稚', '可笑', '荒谬', '离谱', '过分', '太过'
                )
            )
        );
    }

    /**
     * 应用预置敏感词库
     *
     * @param string $library_name 词库名称
     * @return array 应用结果
     */
    public function apply_preset_library($library_name) {
        $libraries = $this->get_preset_word_libraries();
        
        if (!isset($libraries[$library_name])) {
            return array(
                'success' => false,
                'message' => '未找到指定的预置词库: ' . $library_name
            );
        }

        $library = $libraries[$library_name];
        $results = array(
            'success' => true,
            'message' => '',
            'high_risk_imported' => 0,
            'normal_imported' => 0,
            'errors' => array()
        );

        // 导入高危敏感词
        if (!empty($library['high_risk_words'])) {
            $high_risk_result = $this->word_manager->import_words(
                $library['high_risk_words'],
                AMS_V2_Word_Type::HIGH_RISK,
                get_current_user_id()
            );
            $results['high_risk_imported'] = $high_risk_result['success'];
            $results['errors'] = array_merge($results['errors'], $high_risk_result['errors']);
        }

        // 导入普通敏感词
        if (!empty($library['normal_words'])) {
            $normal_result = $this->word_manager->import_words(
                $library['normal_words'],
                AMS_V2_Word_Type::NORMAL,
                get_current_user_id()
            );
            $results['normal_imported'] = $normal_result['success'];
            $results['errors'] = array_merge($results['errors'], $normal_result['errors']);
        }

        $total_imported = $results['high_risk_imported'] + $results['normal_imported'];
        $results['message'] = sprintf(
            '成功应用预置词库 "%s"，共导入 %d 个敏感词（高危: %d，普通: %d）',
            $library['name'],
            $total_imported,
            $results['high_risk_imported'],
            $results['normal_imported']
        );

        return $results;
    }
}