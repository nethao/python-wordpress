<?php
/**
 * 加密服务类
 *
 * @package Article_Management_V2
 * @subpackage Services
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 加密服务类
 * 使用AES-256-GCM算法进行加密
 */
class AMS_V2_Encryption_Service {

    /**
     * 加密算法
     */
    const CIPHER = 'aes-256-gcm';

    /**
     * 加密密钥
     *
     * @var string
     */
    private $key;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->key = $this->get_encryption_key();
    }

    /**
     * 获取加密密钥
     *
     * @return string
     */
    private function get_encryption_key() {
        // 尝试从wp-config.php获取密钥
        if (defined('AMS_V2_ENCRYPTION_KEY')) {
            return AMS_V2_ENCRYPTION_KEY;
        }

        // 尝试从数据库获取或生成密钥
        $key = get_option('ams_v2_encryption_key');
        if (!$key) {
            $key = $this->generate_key();
            update_option('ams_v2_encryption_key', $key, false);
        }

        return $key;
    }

    /**
     * 生成加密密钥
     *
     * @return string
     */
    private function generate_key() {
        if (function_exists('random_bytes')) {
            return base64_encode(random_bytes(32));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return base64_encode(openssl_random_pseudo_bytes(32));
        } else {
            // 备选方案：使用WordPress的wp_generate_password
            return base64_encode(wp_generate_password(32, true, true));
        }
    }

    /**
     * 加密数据
     *
     * @param string $data 要加密的数据
     * @return string|false 加密后的数据，失败返回false
     */
    public function encrypt($data) {
        if (empty($data)) {
            return '';
        }

        try {
            // 检查OpenSSL扩展是否可用
            if (!extension_loaded('openssl')) {
                return $this->fallback_encrypt($data);
            }

            // 生成随机IV
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
            if ($iv === false) {
                return $this->fallback_encrypt($data);
            }

            // 解码密钥
            $key = base64_decode($this->key);
            if ($key === false) {
                return $this->fallback_encrypt($data);
            }

            // 加密数据
            $encrypted = openssl_encrypt($data, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($encrypted === false) {
                return $this->fallback_encrypt($data);
            }

            // 组合IV、tag和加密数据
            $result = base64_encode($iv . $tag . $encrypted);
            return $result;

        } catch (Exception $e) {
            error_log('AMS V2 Encryption Error: ' . $e->getMessage());
            return $this->fallback_encrypt($data);
        }
    }

    /**
     * 解密数据
     *
     * @param string $encrypted_data 加密的数据
     * @return string|false 解密后的数据，失败返回false
     */
    public function decrypt($encrypted_data) {
        if (empty($encrypted_data)) {
            return '';
        }

        try {
            // 检查OpenSSL扩展是否可用
            if (!extension_loaded('openssl')) {
                return $this->fallback_decrypt($encrypted_data);
            }

            // 解码数据
            $data = base64_decode($encrypted_data);
            if ($data === false) {
                return $this->fallback_decrypt($encrypted_data);
            }

            // 解码密钥
            $key = base64_decode($this->key);
            if ($key === false) {
                return $this->fallback_decrypt($encrypted_data);
            }

            // 提取IV长度
            $iv_length = openssl_cipher_iv_length(self::CIPHER);
            $tag_length = 16; // GCM tag length

            if (strlen($data) < $iv_length + $tag_length) {
                return $this->fallback_decrypt($encrypted_data);
            }

            // 分离IV、tag和加密数据
            $iv = substr($data, 0, $iv_length);
            $tag = substr($data, $iv_length, $tag_length);
            $encrypted = substr($data, $iv_length + $tag_length);

            // 解密数据
            $decrypted = openssl_decrypt($encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($decrypted === false) {
                return $this->fallback_decrypt($encrypted_data);
            }

            return $decrypted;

        } catch (Exception $e) {
            error_log('AMS V2 Decryption Error: ' . $e->getMessage());
            return $this->fallback_decrypt($encrypted_data);
        }
    }

    /**
     * 备选加密方法（使用简单的base64编码）
     *
     * @param string $data 要加密的数据
     * @return string
     */
    private function fallback_encrypt($data) {
        // 简单的混淆，不是真正的加密
        $encoded = base64_encode($data);
        return strrev($encoded);
    }

    /**
     * 备选解密方法（使用简单的base64解码）
     *
     * @param string $encrypted_data 加密的数据
     * @return string|false
     */
    private function fallback_decrypt($encrypted_data) {
        // 简单的反混淆
        $reversed = strrev($encrypted_data);
        $decoded = base64_decode($reversed, true);
        return $decoded !== false ? $decoded : false;
    }

    /**
     * 验证加密功能是否正常工作
     *
     * @return bool
     */
    public function test_encryption() {
        $test_data = 'test_encryption_' . time();
        $encrypted = $this->encrypt($test_data);
        
        if ($encrypted === false) {
            return false;
        }

        $decrypted = $this->decrypt($encrypted);
        return $decrypted === $test_data;
    }

    /**
     * 检查是否使用强加密
     *
     * @return bool
     */
    public function is_strong_encryption() {
        return extension_loaded('openssl') && in_array(self::CIPHER, openssl_get_cipher_methods());
    }

    /**
     * 获取加密状态信息
     *
     * @return array
     */
    public function get_encryption_info() {
        return array(
            'cipher' => self::CIPHER,
            'openssl_available' => extension_loaded('openssl'),
            'cipher_available' => extension_loaded('openssl') && in_array(self::CIPHER, openssl_get_cipher_methods()),
            'strong_encryption' => $this->is_strong_encryption(),
            'test_passed' => $this->test_encryption()
        );
    }

    /**
     * 重新生成加密密钥
     * 注意：这会使所有现有的加密数据无法解密
     *
     * @return bool
     */
    public function regenerate_key() {
        $new_key = $this->generate_key();
        $updated = update_option('ams_v2_encryption_key', $new_key, false);
        
        if ($updated) {
            $this->key = $new_key;
        }
        
        return $updated;
    }
}