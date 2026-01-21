<?php
/**
 * 日志记录器类
 *
 * @package Article_Management_V2
 * @subpackage Includes
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 日志记录器类
 */
class AMS_V2_Logger {

    /**
     * 日志级别
     */
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';

    /**
     * 日志级别优先级
     *
     * @var array
     */
    private $levels = array(
        self::EMERGENCY => 0,
        self::ALERT => 1,
        self::CRITICAL => 2,
        self::ERROR => 3,
        self::WARNING => 4,
        self::NOTICE => 5,
        self::INFO => 6,
        self::DEBUG => 7
    );

    /**
     * 日志文件路径
     *
     * @var string
     */
    private $log_file;

    /**
     * 最小日志级别
     *
     * @var string
     */
    private $min_level;

    /**
     * 构造函数
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/ams-v2-logs';
        
        // 创建日志目录
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $this->log_file = $log_dir . '/ams-v2-' . date('Y-m-d') . '.log';
        $this->min_level = defined('WP_DEBUG') && WP_DEBUG ? self::DEBUG : self::ERROR;
    }

    /**
     * 初始化日志记录器
     */
    public function init() {
        // 创建.htaccess文件保护日志目录
        $this->protect_log_directory();
    }

    /**
     * 记录紧急日志
     *
     * @param string $message 消息
     * @param array $context 上下文
     */
    public function emergency($message, array $context = array()) {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * 记录警报日志
     *
     * @param string $message 消息
     * @param array $context 上下文
     */
    public function alert($message, array $context = array()) {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     * 记录严重错误日志
     *
     * @param string $message 消息
     * @param array $context 上下文
     */
    public function critical($message, array $context = array()) {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * 记录错误日志
     *
     * @param string $message 消息
     * @param array $context 上下文
     */
    public function error($message, array $context = array()) {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * 记录警告日志
     *
     * @param string $message 消息
     * @param array $context 上下文
     */
    public function warning($message, array $context = array()) {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * 记录通知日志
     *
     * @param string $message 消息
     * @param array $context 上下文
     */
    public function notice($message, array $context = array()) {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * 记录信息日志
     *
     * @param string $message 消息
     * @param array $context 上下文
     */
    public function info($message, array $context = array()) {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * 记录调试日志
     *
     * @param string $message 消息
     * @param array $context 上下文
     */
    public function debug($message, array $context = array()) {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * 记录日志
     *
     * @param string $level 日志级别
     * @param string $message 消息
     * @param array $context 上下文
     */
    public function log($level, $message, array $context = array()) {
        // 检查日志级别
        if (!$this->should_log($level)) {
            return;
        }

        // 格式化消息
        $formatted_message = $this->format_message($level, $message, $context);

        // 写入日志文件
        $this->write_to_file($formatted_message);

        // 如果是严重错误，同时写入WordPress错误日志
        if (in_array($level, array(self::EMERGENCY, self::ALERT, self::CRITICAL, self::ERROR))) {
            error_log($formatted_message);
        }
    }

    /**
     * 检查是否应该记录日志
     *
     * @param string $level 日志级别
     * @return bool
     */
    private function should_log($level) {
        if (!isset($this->levels[$level]) || !isset($this->levels[$this->min_level])) {
            return false;
        }

        return $this->levels[$level] <= $this->levels[$this->min_level];
    }

    /**
     * 格式化日志消息
     *
     * @param string $level 日志级别
     * @param string $message 消息
     * @param array $context 上下文
     * @return string
     */
    private function format_message($level, $message, array $context = array()) {
        $timestamp = date('Y-m-d H:i:s');
        $level = strtoupper($level);
        
        // 替换消息中的占位符
        $message = $this->interpolate($message, $context);
        
        $formatted = "[{$timestamp}] AMS_V2.{$level}: {$message}";
        
        // 添加上下文信息
        if (!empty($context)) {
            $formatted .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        return $formatted . PHP_EOL;
    }

    /**
     * 替换消息中的占位符
     *
     * @param string $message 消息
     * @param array $context 上下文
     * @return string
     */
    private function interpolate($message, array $context = array()) {
        $replace = array();
        
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        
        return strtr($message, $replace);
    }

    /**
     * 写入日志文件
     *
     * @param string $message 格式化的消息
     */
    private function write_to_file($message) {
        if (!$this->log_file) {
            return;
        }

        // 使用文件锁确保并发安全
        file_put_contents($this->log_file, $message, FILE_APPEND | LOCK_EX);
    }

    /**
     * 保护日志目录
     */
    private function protect_log_directory() {
        $log_dir = dirname($this->log_file);
        $htaccess_file = $log_dir . '/.htaccess';
        
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }
        
        // 创建index.php文件
        $index_file = $log_dir . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
    }

    /**
     * 清理旧日志文件
     *
     * @param int $days 保留天数
     */
    public function cleanup_old_logs($days = 30) {
        $log_dir = dirname($this->log_file);
        $files = glob($log_dir . '/ams-v2-*.log');
        
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }

    /**
     * 获取日志文件路径
     *
     * @return string
     */
    public function get_log_file() {
        return $this->log_file;
    }

    /**
     * 设置最小日志级别
     *
     * @param string $level 日志级别
     */
    public function set_min_level($level) {
        if (isset($this->levels[$level])) {
            $this->min_level = $level;
        }
    }
}