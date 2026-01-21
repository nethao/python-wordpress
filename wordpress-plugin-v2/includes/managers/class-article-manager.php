<?php
/**
 * 文章管理器实现类
 *
 * @package Article_Management_V2
 * @subpackage Managers
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 文章管理器实现类
 */
class AMS_V2_Article_Manager implements AMS_V2_Article_Manager_Interface {

    /**
     * WordPress数据库对象
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * 数据库管理器
     *
     * @var AMS_V2_Database_Manager
     */
    private $db_manager;

    /**
     * 认证管理器
     *
     * @var AMS_V2_Authentication_Manager_Interface
     */
    private $auth_manager;

    /**
     * 日志记录器
     *
     * @var AMS_V2_Logger
     */
    private $logger;

    /**
     * 构造函数
     *
     * @param AMS_V2_Database_Manager $db_manager 数据库管理器
     * @param AMS_V2_Authentication_Manager_Interface $auth_manager 认证管理器
     * @param AMS_V2_Logger $logger 日志记录器
     */
    public function __construct($db_manager, $auth_manager, $logger) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->db_manager = $db_manager;
        $this->auth_manager = $auth_manager;
        $this->logger = $logger;
    }

    /**
     * 创建文章
     *
     * @param AMS_V2_User $author 作者
     * @param AMS_V2_Article_Data $data 文章数据
     * @return AMS_V2_Article|WP_Error 文章对象或错误
     */
    public function create_article($author, $data) {
        try {
            // 验证用户权限
            if (!$this->auth_manager->has_capability($author, 'create_articles')) {
                return new WP_Error('permission_denied', '您没有创建文章的权限');
            }

            // 创建文章对象
            $article = new AMS_V2_Article();
            $article->set_title($data->title);
            $article->set_content($data->content);
            $article->set_excerpt($data->excerpt);
            $article->set_author_id($author->get_id());
            $article->set_tags($data->tags);
            
            // 根据用户角色设置初始状态
            if ($author->is_administrator()) {
                $article->set_status($data->status ?: AMS_V2_Article_Status::DRAFT);
            } else {
                // 普通用户创建的文章默认为待审核状态
                $article->set_status(AMS_V2_Article_Status::PENDING_REVIEW);
            }

            $article->set_created_at(new DateTime());
            $article->set_updated_at(new DateTime());

            // 验证文章数据
            $validation_errors = $article->validate();
            if (!empty($validation_errors)) {
                return new WP_Error('validation_failed', '数据验证失败', $validation_errors);
            }

            // 保存到数据库
            $table_name = $this->db_manager->get_table_name('articles');
            $result = $this->wpdb->insert(
                $table_name,
                array(
                    'title' => $article->get_title(),
                    'content' => $article->get_content(),
                    'excerpt' => $article->get_excerpt(),
                    'author_id' => $article->get_author_id(),
                    'status' => $article->get_status(),
                    'tags' => $article->get_tags_json(),
                    'created_at' => $article->get_created_at()->format('Y-m-d H:i:s'),
                    'updated_at' => $article->get_updated_at()->format('Y-m-d H:i:s')
                ),
                array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
            );

            if ($result === false) {
                $this->logger->error('Failed to create article', array(
                    'author_id' => $author->get_id(),
                    'title' => $article->get_title(),
                    'error' => $this->wpdb->last_error
                ));
                return new WP_Error('database_error', '创建文章失败');
            }

            $article->set_id($this->wpdb->insert_id);
            $article->set_author($author);

            // 记录审计日志
            $this->log_article_action('create', $article, $author);

            $this->logger->info('Article created successfully', array(
                'article_id' => $article->get_id(),
                'author_id' => $author->get_id(),
                'status' => $article->get_status()
            ));

            return $article;

        } catch (Exception $e) {
            $this->logger->error('Exception in create_article', array(
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return new WP_Error('system_error', '系统错误，请稍后重试');
        }
    }

    /**
     * 更新文章
     *
     * @param AMS_V2_Article $article 文章对象
     * @param AMS_V2_Article_Data $data 文章数据
     * @param AMS_V2_User $user 操作用户
     * @return AMS_V2_Article|WP_Error 更新后的文章对象或错误
     */
    public function update_article($article, $data, $user) {
        try {
            // 检查编辑权限
            if (!$this->can_edit_article($user, $article)) {
                return new WP_Error('permission_denied', '您没有编辑此文章的权限');
            }

            // 保存原始数据用于审计日志
            $old_data = $article->to_array();

            // 更新文章数据
            $article->set_title($data->title);
            $article->set_content($data->content);
            $article->set_excerpt($data->excerpt);
            $article->set_tags($data->tags);
            $article->set_updated_at(new DateTime());

            // 管理员可以直接修改状态，普通用户修改后需要重新审核
            if ($user->is_administrator()) {
                if (!empty($data->status)) {
                    $article->set_status($data->status);
                }
            } else {
                // 普通用户编辑后，如果文章已发布，则改为待审核
                if ($article->is_published() || $article->is_approved()) {
                    $article->set_status(AMS_V2_Article_Status::PENDING_REVIEW);
                }
            }

            // 验证文章数据
            $validation_errors = $article->validate();
            if (!empty($validation_errors)) {
                return new WP_Error('validation_failed', '数据验证失败', $validation_errors);
            }

            // 更新数据库
            $table_name = $this->db_manager->get_table_name('articles');
            $result = $this->wpdb->update(
                $table_name,
                array(
                    'title' => $article->get_title(),
                    'content' => $article->get_content(),
                    'excerpt' => $article->get_excerpt(),
                    'status' => $article->get_status(),
                    'tags' => $article->get_tags_json(),
                    'updated_at' => $article->get_updated_at()->format('Y-m-d H:i:s')
                ),
                array('id' => $article->get_id()),
                array('%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );

            if ($result === false) {
                $this->logger->error('Failed to update article', array(
                    'article_id' => $article->get_id(),
                    'user_id' => $user->get_id(),
                    'error' => $this->wpdb->last_error
                ));
                return new WP_Error('database_error', '更新文章失败');
            }

            // 记录审计日志
            $this->log_article_action('update', $article, $user, $old_data);

            $this->logger->info('Article updated successfully', array(
                'article_id' => $article->get_id(),
                'user_id' => $user->get_id(),
                'status' => $article->get_status()
            ));

            return $article;

        } catch (Exception $e) {
            $this->logger->error('Exception in update_article', array(
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return new WP_Error('system_error', '系统错误，请稍后重试');
        }
    }

    /**
     * 删除文章
     *
     * @param AMS_V2_Article $article 文章对象
     * @param AMS_V2_User $user 操作用户
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function delete_article($article, $user) {
        try {
            // 检查删除权限
            if (!$this->can_delete_article($user, $article)) {
                return new WP_Error('permission_denied', '您没有删除此文章的权限');
            }

            // 保存文章数据用于审计日志
            $article_data = $article->to_array();

            // 删除数据库记录
            $table_name = $this->db_manager->get_table_name('articles');
            $result = $this->wpdb->delete(
                $table_name,
                array('id' => $article->get_id()),
                array('%d')
            );

            if ($result === false) {
                $this->logger->error('Failed to delete article', array(
                    'article_id' => $article->get_id(),
                    'user_id' => $user->get_id(),
                    'error' => $this->wpdb->last_error
                ));
                return new WP_Error('database_error', '删除文章失败');
            }

            // 记录审计日志
            $this->log_article_action('delete', $article, $user, $article_data);

            $this->logger->info('Article deleted successfully', array(
                'article_id' => $article->get_id(),
                'user_id' => $user->get_id(),
                'title' => $article->get_title()
            ));

            return true;

        } catch (Exception $e) {
            $this->logger->error('Exception in delete_article', array(
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return new WP_Error('system_error', '系统错误，请稍后重试');
        }
    }

    /**
     * 根据用户获取文章列表
     *
     * @param AMS_V2_User $user 用户对象
     * @param array $args 查询参数
     * @return AMS_V2_Article[] 文章数组
     */
    public function get_articles_by_user($user, $args = array()) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'status' => null,
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        $args = wp_parse_args($args, $defaults);

        $table_name = $this->db_manager->get_table_name('articles');
        $where_conditions = array('author_id = %d');
        $where_values = array($user->get_id());

        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $order_clause = sprintf('ORDER BY %s %s', $args['order_by'], $args['order']);
        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);

        $sql = $this->wpdb->prepare(
            "SELECT * FROM $table_name $where_clause $order_clause $limit_clause",
            $where_values
        );

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        return $this->convert_results_to_articles($results);
    }

    /**
     * 获取所有文章（管理员权限）
     *
     * @param array $args 查询参数
     * @return AMS_V2_Article[] 文章数组
     */
    public function get_all_articles($args = array()) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'status' => null,
            'author_id' => null,
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        $args = wp_parse_args($args, $defaults);

        $table_name = $this->db_manager->get_table_name('articles');
        $where_conditions = array();
        $where_values = array();

        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if (!empty($args['author_id'])) {
            $where_conditions[] = 'author_id = %d';
            $where_values[] = $args['author_id'];
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $order_clause = sprintf('ORDER BY %s %s', $args['order_by'], $args['order']);
        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);

        if (!empty($where_values)) {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM $table_name $where_clause $order_clause $limit_clause",
                $where_values
            );
        } else {
            $sql = "SELECT * FROM $table_name $where_clause $order_clause $limit_clause";
        }

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        return $this->convert_results_to_articles($results);
    }

    /**
     * 根据ID获取文章
     *
     * @param int $article_id 文章ID
     * @return AMS_V2_Article|null 文章对象或null
     */
    public function get_article_by_id($article_id) {
        $table_name = $this->db_manager->get_table_name('articles');
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $article_id
        );

        $result = $this->wpdb->get_row($sql, ARRAY_A);
        if (!$result) {
            return null;
        }

        $article = new AMS_V2_Article($result);
        
        // 加载作者信息
        $author = $this->auth_manager->get_user_by_id($result['author_id']);
        if ($author) {
            $article->set_author($author);
        }

        return $article;
    }

    /**
     * 更改文章状态
     *
     * @param AMS_V2_Article $article 文章对象
     * @param string $status 新状态
     * @param AMS_V2_User $user 操作用户
     * @param string $notes 审核备注
     * @return bool|WP_Error 成功返回true，失败返回错误
     */
    public function change_status($article, $status, $user, $notes = '') {
        try {
            // 检查审核权限
            if (!$this->can_moderate_article($user, $article)) {
                return new WP_Error('permission_denied', '您没有审核此文章的权限');
            }

            // 验证状态
            if (!AMS_V2_Article_Status::is_valid_status($status)) {
                return new WP_Error('invalid_status', '无效的文章状态');
            }

            $old_status = $article->get_status();
            $article->set_status($status);
            $article->set_moderated_by($user->get_id());
            $article->set_moderated_at(new DateTime());
            $article->set_moderation_notes($notes);
            $article->set_updated_at(new DateTime());

            // 如果状态改为已发布，设置发布时间
            if ($status === AMS_V2_Article_Status::PUBLISHED && !$article->get_published_at()) {
                $article->set_published_at(new DateTime());
            }

            // 更新数据库
            $table_name = $this->db_manager->get_table_name('articles');
            $update_data = array(
                'status' => $article->get_status(),
                'moderated_by' => $article->get_moderated_by(),
                'moderated_at' => $article->get_moderated_at()->format('Y-m-d H:i:s'),
                'moderation_notes' => $article->get_moderation_notes(),
                'updated_at' => $article->get_updated_at()->format('Y-m-d H:i:s')
            );

            if ($article->get_published_at()) {
                $update_data['published_at'] = $article->get_published_at()->format('Y-m-d H:i:s');
            }

            $result = $this->wpdb->update(
                $table_name,
                $update_data,
                array('id' => $article->get_id()),
                array('%s', '%d', '%s', '%s', '%s', '%s'),
                array('%d')
            );

            if ($result === false) {
                $this->logger->error('Failed to change article status', array(
                    'article_id' => $article->get_id(),
                    'user_id' => $user->get_id(),
                    'old_status' => $old_status,
                    'new_status' => $status,
                    'error' => $this->wpdb->last_error
                ));
                return new WP_Error('database_error', '更新文章状态失败');
            }

            // 记录审计日志
            $this->log_article_action('status_change', $article, $user, array(
                'old_status' => $old_status,
                'new_status' => $status,
                'notes' => $notes
            ));

            $this->logger->info('Article status changed successfully', array(
                'article_id' => $article->get_id(),
                'user_id' => $user->get_id(),
                'old_status' => $old_status,
                'new_status' => $status
            ));

            return true;

        } catch (Exception $e) {
            $this->logger->error('Exception in change_status', array(
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return new WP_Error('system_error', '系统错误，请稍后重试');
        }
    }

    /**
     * 检查用户是否可以编辑文章
     *
     * @param AMS_V2_User $user 用户对象
     * @param AMS_V2_Article $article 文章对象
     * @return bool
     */
    public function can_edit_article($user, $article) {
        // 管理员可以编辑所有文章
        if ($user->is_administrator()) {
            return true;
        }

        // 普通用户只能编辑自己的文章
        if ($user->has_capability('edit_own_articles') && $article->get_author_id() === $user->get_id()) {
            return true;
        }

        return false;
    }

    /**
     * 检查用户是否可以删除文章
     *
     * @param AMS_V2_User $user 用户对象
     * @param AMS_V2_Article $article 文章对象
     * @return bool
     */
    public function can_delete_article($user, $article) {
        // 管理员可以删除所有文章
        if ($user->is_administrator()) {
            return true;
        }

        // 普通用户只能删除自己的文章
        if ($user->has_capability('delete_own_articles') && $article->get_author_id() === $user->get_id()) {
            return true;
        }

        return false;
    }

    /**
     * 检查用户是否可以查看文章
     *
     * @param AMS_V2_User $user 用户对象
     * @param AMS_V2_Article $article 文章对象
     * @return bool
     */
    public function can_view_article($user, $article) {
        // 管理员可以查看所有文章
        if ($user->is_administrator()) {
            return true;
        }

        // 普通用户可以查看自己的文章
        if ($user->has_capability('view_own_articles') && $article->get_author_id() === $user->get_id()) {
            return true;
        }

        // 已发布的文章所有人都可以查看
        if ($article->is_published()) {
            return true;
        }

        return false;
    }

    /**
     * 检查用户是否可以审核文章
     *
     * @param AMS_V2_User $user 用户对象
     * @param AMS_V2_Article $article 文章对象
     * @return bool
     */
    public function can_moderate_article($user, $article) {
        // 只有管理员可以审核文章
        return $user->is_administrator() && $user->has_capability('moderate_content');
    }

    /**
     * 获取待审核文章列表
     *
     * @param array $args 查询参数
     * @return AMS_V2_Article[] 文章数组
     */
    public function get_pending_articles($args = array()) {
        $args['status'] = AMS_V2_Article_Status::PENDING_REVIEW;
        return $this->get_all_articles($args);
    }

    /**
     * 根据状态获取文章列表
     *
     * @param string $status 文章状态
     * @param array $args 查询参数
     * @return AMS_V2_Article[] 文章数组
     */
    public function get_articles_by_status($status, $args = array()) {
        $args['status'] = $status;
        return $this->get_all_articles($args);
    }

    /**
     * 搜索文章
     *
     * @param string $keyword 搜索关键词
     * @param array $args 查询参数
     * @return AMS_V2_Article[] 文章数组
     */
    public function search_articles($keyword, $args = array()) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'status' => null,
            'author_id' => null,
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        $args = wp_parse_args($args, $defaults);

        $table_name = $this->db_manager->get_table_name('articles');
        $where_conditions = array('(title LIKE %s OR content LIKE %s)');
        $where_values = array('%' . $keyword . '%', '%' . $keyword . '%');

        if (!empty($args['status'])) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $args['status'];
        }

        if (!empty($args['author_id'])) {
            $where_conditions[] = 'author_id = %d';
            $where_values[] = $args['author_id'];
        }

        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        $order_clause = sprintf('ORDER BY %s %s', $args['order_by'], $args['order']);
        $limit_clause = sprintf('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);

        $sql = $this->wpdb->prepare(
            "SELECT * FROM $table_name $where_clause $order_clause $limit_clause",
            $where_values
        );

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        return $this->convert_results_to_articles($results);
    }

    /**
     * 获取文章统计信息
     *
     * @param AMS_V2_User|null $user 用户对象，null表示获取全局统计
     * @return array 统计信息数组
     */
    public function get_article_stats($user = null) {
        $table_name = $this->db_manager->get_table_name('articles');
        $where_clause = '';
        $where_values = array();

        if ($user) {
            $where_clause = 'WHERE author_id = %d';
            $where_values[] = $user->get_id();
        }

        $sql = $this->wpdb->prepare(
            "SELECT 
                status,
                COUNT(*) as count
            FROM $table_name 
            $where_clause
            GROUP BY status",
            $where_values
        );

        $results = $this->wpdb->get_results($sql, ARRAY_A);
        
        $stats = array(
            'total' => 0,
            'draft' => 0,
            'pending_review' => 0,
            'approved' => 0,
            'published' => 0,
            'rejected' => 0,
            'archived' => 0
        );

        foreach ($results as $result) {
            $stats[$result['status']] = (int) $result['count'];
            $stats['total'] += (int) $result['count'];
        }

        return $stats;
    }

    /**
     * 批量更新文章状态
     *
     * @param array $article_ids 文章ID数组
     * @param string $status 新状态
     * @param AMS_V2_User $user 操作用户
     * @param string $notes 审核备注
     * @return array 操作结果数组
     */
    public function batch_update_status($article_ids, $status, $user, $notes = '') {
        $results = array(
            'success' => array(),
            'failed' => array()
        );

        foreach ($article_ids as $article_id) {
            $article = $this->get_article_by_id($article_id);
            if (!$article) {
                $results['failed'][] = array(
                    'id' => $article_id,
                    'error' => '文章不存在'
                );
                continue;
            }

            $result = $this->change_status($article, $status, $user, $notes);
            if (is_wp_error($result)) {
                $results['failed'][] = array(
                    'id' => $article_id,
                    'error' => $result->get_error_message()
                );
            } else {
                $results['success'][] = $article_id;
            }
        }

        return $results;
    }

    /**
     * 获取文章历史记录
     *
     * @param AMS_V2_Article $article 文章对象
     * @return array 历史记录数组
     */
    public function get_article_history($article) {
        $audit_table = $this->db_manager->get_table_name('audit_logs');
        $sql = $this->wpdb->prepare(
            "SELECT * FROM $audit_table 
            WHERE resource_type = 'article' AND resource_id = %d 
            ORDER BY created_at DESC",
            $article->get_id()
        );

        return $this->wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * 将数据库结果转换为文章对象数组
     *
     * @param array $results 数据库查询结果
     * @return AMS_V2_Article[] 文章对象数组
     */
    private function convert_results_to_articles($results) {
        $articles = array();
        
        foreach ($results as $result) {
            $article = new AMS_V2_Article($result);
            
            // 加载作者信息
            $author = $this->auth_manager->get_user_by_id($result['author_id']);
            if ($author) {
                $article->set_author($author);
            }
            
            $articles[] = $article;
        }

        return $articles;
    }

    /**
     * 记录文章操作的审计日志
     *
     * @param string $action 操作类型
     * @param AMS_V2_Article $article 文章对象
     * @param AMS_V2_User $user 操作用户
     * @param array $additional_data 额外数据
     */
    private function log_article_action($action, $article, $user, $additional_data = array()) {
        $audit_table = $this->db_manager->get_table_name('audit_logs');
        
        $log_data = array(
            'user_id' => $user->get_id(),
            'action' => $action,
            'resource_type' => 'article',
            'resource_id' => $article->get_id(),
            'new_values' => json_encode(array_merge($article->to_array(), $additional_data)),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'created_at' => current_time('mysql')
        );

        if (!empty($additional_data)) {
            $log_data['old_values'] = json_encode($additional_data);
        }

        $this->wpdb->insert($audit_table, $log_data);
    }

    /**
     * 获取客户端IP地址
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
}