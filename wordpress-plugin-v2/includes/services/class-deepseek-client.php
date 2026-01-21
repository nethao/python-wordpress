<?php
/**
 * DeepSeek API客户端
 *
 * @package Article_Management_V2
 * @subpackage Services
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * DeepSeek API客户端类
 */
class AMS_V2_DeepSeek_Client {

    /**
     * API基础URL
     */
    const API_BASE_URL = 'https://api.deepseek.com/v1';

    /**
     * API密钥
     *
     * @var string
     */
    private $api_key;

    /**
     * 请求超时时间（秒）
     *
     * @var int
     */
    private $timeout;

    /**
     * 重试次数
     *
     * @var int
     */
    private $retry_attempts;

    /**
     * 日志记录器
     *
     * @var AMS_V2_Logger
     */
    private $logger;

    /**
     * 构造函数
     *
     * @param string $api_key API密钥
     * @param array $config 配置参数
     * @param AMS_V2_Logger $logger 日志记录器
     */
    public function __construct($api_key, $config = array(), $logger = null) {
        $this->api_key = $api_key;
        $this->timeout = isset($config['timeout']) ? (int) $config['timeout'] : 30;
        $this->retry_attempts = isset($config['retry_attempts']) ? (int) $config['retry_attempts'] : 3;
        $this->logger = $logger ?: new AMS_V2_Logger();
    }

    /**
     * 内容审核
     *
     * @param string $content 要审核的内容
     * @param array $options 审核选项
     * @return array|WP_Error 审核结果
     */
    public function moderate_content($content, $options = array()) {
        if (empty($content)) {
            return array(
                'passed' => true,
                'score' => 0.0,
                'risk_level' => 'low',
                'reasons' => array(),
                'suggestions' => array(),
                'flagged_keywords' => array()
            );
        }

        $defaults = array(
            'strict_level' => 2, // 1:宽松 2:中等 3:严格
            'check_types' => array('political', 'violence', 'sexual', 'hate', 'spam', 'privacy'),
            'language' => 'zh'
        );
        $options = wp_parse_args($options, $defaults);

        try {
            $prompt = $this->build_moderation_prompt($content, $options);
            $response = $this->chat_completion($prompt, array(
                'temperature' => 0.1,
                'max_tokens' => 1000
            ));

            if (is_wp_error($response)) {
                return $response;
            }

            return $this->parse_moderation_response($response, $content);

        } catch (Exception $e) {
            $this->logger->error('内容审核异常', array(
                'error' => $e->getMessage(),
                'content_length' => strlen($content)
            ));
            return new WP_Error('moderation_exception', '内容审核时发生异常: ' . $e->getMessage());
        }
    }

    /**
     * 聊天完成API调用
     *
     * @param string $prompt 提示词
     * @param array $options 选项
     * @return array|WP_Error API响应
     */
    public function chat_completion($prompt, $options = array()) {
        $defaults = array(
            'model' => 'deepseek-chat',
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'stream' => false
        );
        $options = wp_parse_args($options, $defaults);

        $data = array(
            'model' => $options['model'],
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => $options['temperature'],
            'max_tokens' => $options['max_tokens'],
            'stream' => $options['stream']
        );

        return $this->make_request('/chat/completions', $data, 'POST');
    }

    /**
     * 获取模型列表
     *
     * @return array|WP_Error 模型列表
     */
    public function get_models() {
        return $this->make_request('/models', null, 'GET');
    }

    /**
     * 检查API健康状态
     *
     * @return array|WP_Error 健康状态
     */
    public function health_check() {
        try {
            $start_time = microtime(true);
            $response = $this->get_models();
            $end_time = microtime(true);

            if (is_wp_error($response)) {
                return array(
                    'status' => 'error',
                    'message' => $response->get_error_message(),
                    'response_time' => null,
                    'timestamp' => current_time('mysql')
                );
            }

            return array(
                'status' => 'healthy',
                'message' => 'API连接正常',
                'response_time' => round(($end_time - $start_time) * 1000, 2), // 毫秒
                'models_count' => isset($response['data']) ? count($response['data']) : 0,
                'timestamp' => current_time('mysql')
            );

        } catch (Exception $e) {
            return array(
                'status' => 'error',
                'message' => 'API健康检查异常: ' . $e->getMessage(),
                'response_time' => null,
                'timestamp' => current_time('mysql')
            );
        }
    }

    /**
     * 查询账户余额
     *
     * @return array|WP_Error 余额信息
     */
    public function get_balance() {
        // DeepSeek API可能没有直接的余额查询接口
        // 这里通过一个小的测试请求来检查API可用性
        try {
            $test_response = $this->chat_completion('测试', array(
                'max_tokens' => 1,
                'temperature' => 0
            ));

            if (is_wp_error($test_response)) {
                return array(
                    'status' => 'error',
                    'message' => $test_response->get_error_message(),
                    'balance' => null,
                    'timestamp' => current_time('mysql')
                );
            }

            return array(
                'status' => 'available',
                'message' => 'API可用',
                'balance' => 'N/A', // DeepSeek可能不提供余额查询
                'timestamp' => current_time('mysql')
            );

        } catch (Exception $e) {
            return array(
                'status' => 'error',
                'message' => '余额查询异常: ' . $e->getMessage(),
                'balance' => null,
                'timestamp' => current_time('mysql')
            );
        }
    }

    /**
     * 执行API请求
     *
     * @param string $endpoint API端点
     * @param array|null $data 请求数据
     * @param string $method HTTP方法
     * @return array|WP_Error API响应
     */
    private function make_request($endpoint, $data = null, $method = 'GET') {
        $url = self::API_BASE_URL . $endpoint;
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'User-Agent' => 'AMS-V2-WordPress-Plugin/2.0'
        );

        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => $this->timeout,
            'sslverify' => true
        );

        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }

        $attempt = 0;
        while ($attempt < $this->retry_attempts) {
            $attempt++;
            
            $this->logger->debug('DeepSeek API请求', array(
                'url' => $url,
                'method' => $method,
                'attempt' => $attempt,
                'data_size' => $data ? strlen(json_encode($data)) : 0
            ));

            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                $this->logger->warning('DeepSeek API请求失败', array(
                    'attempt' => $attempt,
                    'error' => $response->get_error_message()
                ));
                
                if ($attempt >= $this->retry_attempts) {
                    return new WP_Error('api_request_failed', 
                        'API请求失败: ' . $response->get_error_message());
                }
                
                // 等待后重试
                sleep(pow(2, $attempt - 1)); // 指数退避
                continue;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            $this->logger->debug('DeepSeek API响应', array(
                'status_code' => $status_code,
                'response_size' => strlen($body)
            ));

            if ($status_code >= 200 && $status_code < 300) {
                $decoded = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                } else {
                    return new WP_Error('json_decode_error', 'JSON解析失败');
                }
            } elseif ($status_code === 429) {
                // 速率限制，等待后重试
                $this->logger->warning('DeepSeek API速率限制', array('attempt' => $attempt));
                if ($attempt < $this->retry_attempts) {
                    sleep(pow(2, $attempt)); // 更长的等待时间
                    continue;
                }
            } elseif ($status_code >= 500) {
                // 服务器错误，可以重试
                $this->logger->warning('DeepSeek API服务器错误', array(
                    'status_code' => $status_code,
                    'attempt' => $attempt
                ));
                if ($attempt < $this->retry_attempts) {
                    sleep(pow(2, $attempt - 1));
                    continue;
                }
            }

            // 客户端错误或最终失败
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : "HTTP {$status_code}";

            return new WP_Error('api_error', $error_message, array(
                'status_code' => $status_code,
                'response_body' => $body
            ));
        }

        return new WP_Error('max_retries_exceeded', '超过最大重试次数');
    }

    /**
     * 构建内容审核提示词
     *
     * @param string $content 内容
     * @param array $options 选项
     * @return string 提示词
     */
    private function build_moderation_prompt($content, $options) {
        $strict_levels = array(
            1 => '宽松',
            2 => '中等',
            3 => '严格'
        );

        $strict_level_name = isset($strict_levels[$options['strict_level']]) 
            ? $strict_levels[$options['strict_level']] 
            : '中等';

        $check_types_desc = array(
            'political' => '政治敏感内容',
            'violence' => '暴力内容',
            'sexual' => '色情内容',
            'hate' => '仇恨言论',
            'spam' => '垃圾信息',
            'privacy' => '隐私泄露'
        );

        $enabled_checks = array();
        foreach ($options['check_types'] as $type) {
            if (isset($check_types_desc[$type])) {
                $enabled_checks[] = $check_types_desc[$type];
            }
        }

        $prompt = "请对以下内容进行审核，审核等级为：{$strict_level_name}。\n\n";
        $prompt .= "需要检查的内容类型：" . implode('、', $enabled_checks) . "\n\n";
        $prompt .= "请按照以下JSON格式返回审核结果：\n";
        $prompt .= "{\n";
        $prompt .= '  "passed": true/false,  // 是否通过审核' . "\n";
        $prompt .= '  "score": 0.0-1.0,      // 风险评分，0为无风险，1为高风险' . "\n";
        $prompt .= '  "risk_level": "low/medium/high",  // 风险等级' . "\n";
        $prompt .= '  "reasons": ["原因1", "原因2"],     // 不通过的具体原因' . "\n";
        $prompt .= '  "suggestions": ["建议1", "建议2"], // 修改建议' . "\n";
        $prompt .= '  "flagged_keywords": ["词1", "词2"] // 触发的关键词' . "\n";
        $prompt .= "}\n\n";
        $prompt .= "待审核内容：\n{$content}";

        return $prompt;
    }

    /**
     * 解析审核响应
     *
     * @param array $response API响应
     * @param string $original_content 原始内容
     * @return array 解析后的审核结果
     */
    private function parse_moderation_response($response, $original_content) {
        $default_result = array(
            'passed' => true,
            'score' => 0.0,
            'risk_level' => 'low',
            'reasons' => array(),
            'suggestions' => array(),
            'flagged_keywords' => array()
        );

        if (!isset($response['choices'][0]['message']['content'])) {
            return $default_result;
        }

        $content = $response['choices'][0]['message']['content'];
        
        // 尝试提取JSON
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $json_str = $matches[0];
            $result = json_decode($json_str, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
                // 验证和标准化结果
                $parsed_result = array_merge($default_result, $result);
                
                // 确保数据类型正确
                $parsed_result['passed'] = (bool) $parsed_result['passed'];
                $parsed_result['score'] = (float) $parsed_result['score'];
                $parsed_result['score'] = max(0.0, min(1.0, $parsed_result['score'])); // 限制在0-1之间
                
                if (!in_array($parsed_result['risk_level'], array('low', 'medium', 'high'))) {
                    $parsed_result['risk_level'] = $parsed_result['score'] > 0.7 ? 'high' : 
                        ($parsed_result['score'] > 0.3 ? 'medium' : 'low');
                }
                
                $parsed_result['reasons'] = is_array($parsed_result['reasons']) ? $parsed_result['reasons'] : array();
                $parsed_result['suggestions'] = is_array($parsed_result['suggestions']) ? $parsed_result['suggestions'] : array();
                $parsed_result['flagged_keywords'] = is_array($parsed_result['flagged_keywords']) ? $parsed_result['flagged_keywords'] : array();
                
                return $parsed_result;
            }
        }

        // 如果JSON解析失败，尝试从文本中提取信息
        $text_result = $default_result;
        
        if (strpos($content, '不通过') !== false || strpos($content, '违规') !== false || strpos($content, '敏感') !== false) {
            $text_result['passed'] = false;
            $text_result['score'] = 0.8;
            $text_result['risk_level'] = 'high';
            $text_result['reasons'] = array('内容可能包含敏感信息');
            $text_result['suggestions'] = array('请修改相关内容后重新提交');
        }

        return $text_result;
    }
}