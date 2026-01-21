<?php
/**
 * 错误处理器类
 *
 * @package Article_Management_V2
 * @subpackage Includes
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 错误处理器类
 */
class AMS_V2_Error_Handler {

    /**
     * 日志记录器
     *
     * @var AMS_V2_Logger
     */
    private $logger;

    /**
     * 是否已初始化
     *
     * @var bool
     */
    private $initialized = false;

    /**
     * 构造函数
     *
     * @param AMS_V2_Logger $logger 日志记录器
     */
    public function __construct($logger) {
        $this->logger = $logger;
    }

    /**
     * 初始化错误处理
     */
    public function init() {
        if ($this->initialized) {
            return;
        }

        // 设置错误处理器
        set_error_handler(array($this, 'handle_error'));
        set_exception_handler(array($this, 'handle_exception'));
        register_shutdown_function(array($this, 'handle_fatal_error'));

        $this->initialized = true;
    }

    /**
     * 处理PHP错误
     *
     * @param int $severity 错误级别
     * @param string $message 错误消息
     * @param string $file 文件路径
     * @param int $line 行号
     * @return bool
     */
    public function handle_error($severity, $message, $file, $line) {
        // 检查错误报告级别
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $error_type = $this->get_error_type($severity);
        $context = array(
            'file' => $file,
            'line' => $line,
            'severity' => $severity,
            'type' => $error_type
        );

        $this->logger->error("PHP {$error_type}: {$message}", $context);

        // 在开发环境显示错误
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return false; // 让PHP显示错误
        }

        return true; // 阻止PHP显示错误
    }

    /**
     * 处理未捕获的异常
     *
     * @param Throwable $exception 异常对象
     */
    public function handle_exception($exception) {
        $context = array(
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'code' => $exception->getCode()
        );

        $this->logger->critical(
            "Uncaught Exception: " . $exception->getMessage(),
            $context
        );

        // 在开发环境显示异常
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<div style="background: #f00; color: #fff; padding: 10px; margin: 10px;">';
            echo '<h3>Uncaught Exception</h3>';
            echo '<p><strong>Message:</strong> ' . esc_html($exception->getMessage()) . '</p>';
            echo '<p><strong>File:</strong> ' . esc_html($exception->getFile()) . ':' . $exception->getLine() . '</p>';
            echo '<pre>' . esc_html($exception->getTraceAsString()) . '</pre>';
            echo '</div>';
        } else {
            // 生产环境显示友好错误页面
            $this->show_error_page();
        }
    }

    /**
     * 处理致命错误
     */
    public function handle_fatal_error() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            $context = array(
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $this->get_error_type($error['type'])
            );

            $this->logger->critical("Fatal Error: " . $error['message'], $context);

            // 在开发环境显示错误
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo '<div style="background: #f00; color: #fff; padding: 10px; margin: 10px;">';
                echo '<h3>Fatal Error</h3>';
                echo '<p><strong>Message:</strong> ' . esc_html($error['message']) . '</p>';
                echo '<p><strong>File:</strong> ' . esc_html($error['file']) . ':' . $error['line'] . '</p>';
                echo '</div>';
            }
        }
    }

    /**
     * 记录插件异常
     *
     * @param Exception $exception 异常对象
     * @param array $context 上下文信息
     */
    public function log_exception($exception, $context = array()) {
        $default_context = array(
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'code' => $exception->getCode()
        );

        $context = array_merge($default_context, $context);

        $this->logger->error(
            get_class($exception) . ": " . $exception->getMessage(),
            $context
        );
    }

    /**
     * 获取错误类型名称
     *
     * @param int $type 错误类型
     * @return string
     */
    private function get_error_type($type) {
        $types = array(
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Standards',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        );

        return isset($types[$type]) ? $types[$type] : 'Unknown Error';
    }

    /**
     * 显示错误页面
     */
    private function show_error_page() {
        if (!headers_sent()) {
            status_header(500);
        }

        echo '<div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">';
        echo '<h1>系统错误</h1>';
        echo '<p>抱歉，系统遇到了一个错误。请稍后再试。</p>';
        echo '<p>如果问题持续存在，请联系管理员。</p>';
        echo '</div>';
        
        exit;
    }
}