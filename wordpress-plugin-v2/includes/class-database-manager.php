<?php
/**
 * 数据库管理器类
 *
 * @package Article_Management_V2
 * @subpackage Includes
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 数据库管理器类
 */
class AMS_V2_Database_Manager {

    /**
     * WordPress数据库对象
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * 表前缀
     *
     * @var string
     */
    private $table_prefix;

    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix . 'ams_v2_';
    }

    /**
     * 创建所有数据库表
     */
    public function create_tables() {
        $this->create_users_table();
        $this->create_roles_table();
        $this->create_user_roles_table();
        $this->create_articles_table();
        $this->create_sites_table();
        $this->create_sensitive_words_table();
        $this->create_audit_logs_table();
        $this->create_publish_logs_table();
        $this->create_cleanup_logs_table();
        $this->create_api_keys_table();
        
        // 插入默认数据
        $this->insert_default_data();
    }

    /**
     * 创建用户表
     */
    private function create_users_table() {
        $table_name = $this->table_prefix . 'users';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            username varchar(60) NOT NULL,
            email varchar(100) NOT NULL,
            password_hash varchar(255) NOT NULL,
            display_name varchar(250) NOT NULL DEFAULT '',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY username (username),
            UNIQUE KEY email (email),
            KEY is_active (is_active),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }

    /**
     * 创建角色表
     */
    private function create_roles_table() {
        $table_name = $this->table_prefix . 'roles';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(50) NOT NULL,
            display_name varchar(100) NOT NULL,
            description text,
            capabilities longtext,
            is_system tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY is_system (is_system)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }

    /**
     * 创建用户角色关联表
     */
    private function create_user_roles_table() {
        $table_name = $this->table_prefix . 'user_roles';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            role_id bigint(20) unsigned NOT NULL,
            assigned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            assigned_by bigint(20) unsigned NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_role (user_id, role_id),
            KEY user_id (user_id),
            KEY role_id (role_id),
            KEY assigned_at (assigned_at)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }

    /**
     * 创建文章表
     */
    private function create_articles_table() {
        $table_name = $this->table_prefix . 'articles';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            content longtext NOT NULL,
            excerpt text,
            author_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            tags text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            published_at datetime NULL,
            moderated_at datetime NULL,
            moderated_by bigint(20) unsigned NULL,
            moderation_notes text,
            PRIMARY KEY (id),
            KEY author_id (author_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY published_at (published_at),
            KEY moderated_at (moderated_at),
            FULLTEXT KEY title_content (title, content)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }

    /**
     * 创建站点表
     */
    private function create_sites_table() {
        $table_name = $this->table_prefix . 'sites';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            url varchar(255) NOT NULL,
            username varchar(60) NOT NULL,
            encrypted_password text NOT NULL,
            encrypted_api_key text,
            auth_method varchar(20) NOT NULL DEFAULT 'password',
            default_category_id int(11) NOT NULL DEFAULT 1,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_sync_at datetime NULL,
            sync_status varchar(20) DEFAULT 'unknown',
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY url (url),
            KEY is_active (is_active),
            KEY auth_method (auth_method),
            KEY sync_status (sync_status)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }

    /**
     * 创建敏感词表
     */
    private function create_sensitive_words_table() {
        $table_name = $this->table_prefix . 'sensitive_words';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            word varchar(100) NOT NULL,
            type varchar(20) NOT NULL DEFAULT 'normal',
            replacement varchar(100) DEFAULT '',
            is_builtin tinyint(1) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned NULL,
            PRIMARY KEY (id),
            UNIQUE KEY word_type (word, type),
            KEY type (type),
            KEY is_builtin (is_builtin),
            KEY is_active (is_active),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }

    /**
     * 创建审计日志表
     */
    private function create_audit_logs_table() {
        $table_name = $this->table_prefix . 'audit_logs';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NULL,
            action varchar(50) NOT NULL,
            resource_type varchar(50) NOT NULL,
            resource_id bigint(20) unsigned NULL,
            old_values longtext,
            new_values longtext,
            ip_address varchar(45),
            user_agent text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY resource_type (resource_type),
            KEY resource_id (resource_id),
            KEY created_at (created_at),
            KEY ip_address (ip_address)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }

    /**
     * 创建发布日志表
     */
    private function create_publish_logs_table() {
        $table_name = $this->table_prefix . 'publish_logs';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            article_id bigint(20) unsigned NOT NULL,
            site_id bigint(20) unsigned NOT NULL,
            wp_post_id bigint(20) unsigned NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            error_message text,
            published_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY article_id (article_id),
            KEY site_id (site_id),
            KEY wp_post_id (wp_post_id),
            KEY status (status),
            KEY published_at (published_at),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }

    /**
     * 创建清理日志表
     */
    private function create_cleanup_logs_table() {
        $table_name = $this->table_prefix . 'cleanup_logs';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cleanup_type varchar(20) NOT NULL DEFAULT 'auto',
            articles_deleted int(11) NOT NULL DEFAULT 0,
            threshold_days int(11) NOT NULL,
            started_at datetime NOT NULL,
            completed_at datetime NULL,
            status varchar(20) NOT NULL DEFAULT 'running',
            error_message text,
            deleted_articles longtext,
            triggered_by bigint(20) unsigned NULL,
            PRIMARY KEY (id),
            KEY cleanup_type (cleanup_type),
            KEY started_at (started_at),
            KEY completed_at (completed_at),
            KEY status (status),
            KEY triggered_by (triggered_by)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }

    /**
     * 创建API密钥表
     */
    private function create_api_keys_table() {
        $table_name = $this->table_prefix . 'api_keys';
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            service_name varchar(50) NOT NULL,
            encrypted_key text NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            last_used_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY service_name (service_name),
            KEY is_active (is_active),
            KEY last_used_at (last_used_at),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        $this->execute_sql($sql);
    }

    /**
     * 插入默认数据
     */
    private function insert_default_data() {
        $this->insert_default_roles();
        $this->insert_default_sensitive_words();
    }

    /**
     * 插入默认角色
     */
    private function insert_default_roles() {
        $roles_table = $this->table_prefix . 'roles';
        
        $default_roles = array(
            array(
                'name' => 'administrator',
                'display_name' => '管理员',
                'description' => '拥有系统所有权限的管理员角色',
                'capabilities' => json_encode(array(
                    'manage_users', 'manage_roles', 'manage_articles', 'manage_sites',
                    'manage_sensitive_words', 'manage_api_keys', 'view_audit_logs',
                    'manage_cleanup', 'view_statistics', 'moderate_content'
                )),
                'is_system' => 1
            ),
            array(
                'name' => 'user',
                'display_name' => '普通用户',
                'description' => '具有基本文章管理权限的普通用户角色',
                'capabilities' => json_encode(array(
                    'create_articles', 'edit_own_articles', 'delete_own_articles',
                    'view_own_articles', 'publish_articles'
                )),
                'is_system' => 1
            )
        );

        foreach ($default_roles as $role) {
            $existing = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM $roles_table WHERE name = %s",
                    $role['name']
                )
            );

            if (!$existing) {
                $this->wpdb->insert($roles_table, $role);
            }
        }
    }

    /**
     * 插入默认敏感词
     */
    private function insert_default_sensitive_words() {
        $words_table = $this->table_prefix . 'sensitive_words';
        
        // 高危敏感词
        $high_risk_words = array(
            '暴力', '恐怖', '极端', '仇恨', '歧视', '诈骗', '赌博', '毒品'
        );
        
        // 普通敏感词
        $normal_words = array(
            '垃圾', '废物', '愚蠢', '白痴', '傻瓜', '混蛋'
        );

        foreach ($high_risk_words as $word) {
            $existing = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM $words_table WHERE word = %s AND type = %s",
                    $word, 'high_risk'
                )
            );

            if (!$existing) {
                $this->wpdb->insert($words_table, array(
                    'word' => $word,
                    'type' => 'high_risk',
                    'replacement' => '***',
                    'is_builtin' => 1,
                    'is_active' => 1
                ));
            }
        }

        foreach ($normal_words as $word) {
            $existing = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM $words_table WHERE word = %s AND type = %s",
                    $word, 'normal'
                )
            );

            if (!$existing) {
                $this->wpdb->insert($words_table, array(
                    'word' => $word,
                    'type' => 'normal',
                    'replacement' => '**',
                    'is_builtin' => 1,
                    'is_active' => 1
                ));
            }
        }
    }

    /**
     * 执行SQL语句
     *
     * @param string $sql SQL语句
     */
    private function execute_sql($sql) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * 删除所有表
     */
    public function drop_tables() {
        $tables = array(
            'api_keys',
            'cleanup_logs',
            'publish_logs',
            'audit_logs',
            'sensitive_words',
            'sites',
            'articles',
            'user_roles',
            'roles',
            'users'
        );

        foreach ($tables as $table) {
            $table_name = $this->table_prefix . $table;
            $this->wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
    }

    /**
     * 获取表名
     *
     * @param string $table 表名（不含前缀）
     * @return string 完整表名
     */
    public function get_table_name($table) {
        return $this->table_prefix . $table;
    }

    /**
     * 检查表是否存在
     *
     * @param string $table 表名（不含前缀）
     * @return bool
     */
    public function table_exists($table) {
        $table_name = $this->table_prefix . $table;
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )
        );
        return $result === $table_name;
    }
}