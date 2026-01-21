<?php
/**
 * 站点管理器实现类
 *
 * @package Article_Management_V2
 * @subpackage Managers
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 站点管理器实现类
 */
class AMS_V2_Site_Manager implements AMS_V2_Site_Manager_Interface {

    /**
     * 数据库管理器
     *
     * @var AMS_V2_Database_Manager
     */
    private $db_manager;

    /**
     * 加密服务
     *
     * @var AMS_V2_Encryption_Service
     */
    private $encryption_service;

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
     * @param AMS_V2_Encryption_Service $encryption_service 加密服务
     * @param AMS_V2_Logger $logger 日志记录器
     */
    public function __construct($db_manager, $encryption_service, $logger) {
        $this->db_manager = $db_manager;
        $this->encryption_service = $encryption_service;
        $this->logger = $logger;
    }

    /**
     * 创建站点
     *
     * @param array $site_data 站点数据
     * @return AMS_V2_Site|WP_Error 创建的站点对象或错误
     */
    public function create_site($site_data) {
        try {
            // 验证输入数据
            $validation_errors = $this->validate_site_data($site_data);
            if (!empty($validation_errors)) {
                return new WP_Error('validation_failed', '数据验证失败', $validation_errors);
            }

            // 创建站点对象
            $site = new AMS_V2_Site($site_data);
            
            // 验证站点对象
            $site_errors = $site->validate();
            if (!empty($site_errors)) {
                return new WP_Error('site_validation_failed', '站点验证失败', $site_errors);
            }

            // 测试站点连接
            $connection_test = $this->test_site_connection($site);
            if (is_wp_error($connection_test)) {
                $site->set_sync_status('failed');
                $this->logger->warning('站点连接测试失败', array(
                    'site_url' => $site->get_url(),
                    'error' => $connection_test->get_error_message()
                ));
            } else {
                $site->set_sync_status('connected');
            }

            // 保存到数据库
            global $wpdb;
            $table_name = $this->db_manager->get_table_name('sites');
            
            $data = $site->to_database_array();
            $data['created_at'] = current_time('mysql');
            $data['updated_at'] = current_time('mysql');

            $result = $wpdb->insert($table_name, $data);
            
            if ($result === false) {
                return new WP_Error('database_error', '数据库保存失败: ' . $wpdb->last_error);
            }

            $site->set_id($wpdb->insert_id);
            
            $this->logger->info('站点创建成功', array(
                'site_id' => $site->get_id(),
                'site_name' => $site->get_name(),
                'site_url' => $site->get_url()
            ));

            return $site;

        } catch (Exception $e) {
            $this->logger->error('创建站点异常', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return new WP_Error('exception', '创建站点时发生异常: ' . $e->getMessage());
        }
    }

    /**
     * 获取站点
     *
     * @param int $site_id 站点ID
     * @return AMS_V2_Site|null 站点对象或null
     */
    public function get_site($site_id) {
        try {
            global $wpdb;
            $table_name = $this->db_manager->get_table_name('sites');
            
            $site_data = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $site_id),
                ARRAY_A
            );

            if (!$site_data) {
                return null;
            }

            return new AMS_V2_Site($site_data);

        } catch (Exception $e) {
            $this->logger->error('获取站点异常', array(
                'site_id' => $site_id,
                'error' => $e->getMessage()
            ));
            return null;
        }
    }

    /**
     * 获取所有站点
     *
     * @param array $args 查询参数
     * @return array 站点数组
     */
    public function get_sites($args = array()) {
        try {
            global $wpdb;
            $table_name = $this->db_manager->get_table_name('sites');
            
            $defaults = array(
                'active_only' => false,
                'limit' => 50,
                'offset' => 0,
                'orderby' => 'name',
                'order' => 'ASC'
            );
            $args = wp_parse_args($args, $defaults);

            $where_clauses = array();
            $where_values = array();

            if ($args['active_only']) {
                $where_clauses[] = 'is_active = %d';
                $where_values[] = 1;
            }

            $where_sql = '';
            if (!empty($where_clauses)) {
                $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
            }

            $order_sql = sprintf(
                'ORDER BY %s %s',
                sanitize_sql_orderby($args['orderby']),
                $args['order'] === 'DESC' ? 'DESC' : 'ASC'
            );

            $limit_sql = $wpdb->prepare('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);

            $query = "SELECT * FROM {$table_name} {$where_sql} {$order_sql} {$limit_sql}";
            
            if (!empty($where_values)) {
                $query = $wpdb->prepare($query, $where_values);
            }

            $results = $wpdb->get_results($query, ARRAY_A);
            
            $sites = array();
            foreach ($results as $site_data) {
                $sites[] = new AMS_V2_Site($site_data);
            }

            return $sites;

        } catch (Exception $e) {
            $this->logger->error('获取站点列表异常', array(
                'error' => $e->getMessage()
            ));
            return array();
        }
    }

    /**
     * 更新站点
     *
     * @param AMS_V2_Site $site 站点对象
     * @return bool|WP_Error 更新结果
     */
    public function update_site($site) {
        try {
            if (!$site instanceof AMS_V2_Site) {
                return new WP_Error('invalid_site', '无效的站点对象');
            }

            if ($site->get_id() <= 0) {
                return new WP_Error('invalid_site_id', '无效的站点ID');
            }

            // 验证站点数据
            $errors = $site->validate();
            if (!empty($errors)) {
                return new WP_Error('validation_failed', '站点验证失败', $errors);
            }

            global $wpdb;
            $table_name = $this->db_manager->get_table_name('sites');
            
            $data = $site->to_database_array();
            $data['updated_at'] = current_time('mysql');
            
            // 移除ID字段
            unset($data['id']);

            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $site->get_id()),
                null,
                array('%d')
            );

            if ($result === false) {
                return new WP_Error('database_error', '数据库更新失败: ' . $wpdb->last_error);
            }

            $this->logger->info('站点更新成功', array(
                'site_id' => $site->get_id(),
                'site_name' => $site->get_name()
            ));

            return true;

        } catch (Exception $e) {
            $this->logger->error('更新站点异常', array(
                'site_id' => $site ? $site->get_id() : 'unknown',
                'error' => $e->getMessage()
            ));
            return new WP_Error('exception', '更新站点时发生异常: ' . $e->getMessage());
        }
    }

    /**
     * 删除站点
     *
     * @param int $site_id 站点ID
     * @return bool|WP_Error 删除结果
     */
    public function delete_site($site_id) {
        try {
            $site = $this->get_site($site_id);
            if (!$site) {
                return new WP_Error('site_not_found', '站点不存在');
            }

            // 检查是否有关联的文章
            $article_count = $this->get_site_article_count($site_id);
            if ($article_count > 0) {
                return new WP_Error('site_has_articles', 
                    sprintf('无法删除站点，还有 %d 篇文章关联到此站点', $article_count));
            }

            global $wpdb;
            $table_name = $this->db_manager->get_table_name('sites');
            
            $result = $wpdb->delete(
                $table_name,
                array('id' => $site_id),
                array('%d')
            );

            if ($result === false) {
                return new WP_Error('database_error', '数据库删除失败: ' . $wpdb->last_error);
            }

            $this->logger->info('站点删除成功', array(
                'site_id' => $site_id,
                'site_name' => $site->get_name()
            ));

            return true;

        } catch (Exception $e) {
            $this->logger->error('删除站点异常', array(
                'site_id' => $site_id,
                'error' => $e->getMessage()
            ));
            return new WP_Error('exception', '删除站点时发生异常: ' . $e->getMessage());
        }
    }

    /**
     * 测试站点连接
     *
     * @param AMS_V2_Site $site 站点对象
     * @return bool|WP_Error 连接测试结果
     */
    public function test_site_connection($site) {
        try {
            if (!$site instanceof AMS_V2_Site) {
                return new WP_Error('invalid_site', '无效的站点对象');
            }

            $credentials = $site->get_credentials();
            if (!$credentials) {
                return new WP_Error('no_credentials', '站点认证信息缺失');
            }

            // 构建XML-RPC URL
            $xmlrpc_url = rtrim($site->get_url(), '/') . '/xmlrpc.php';
            
            // 准备认证信息
            $username = $credentials->get_username();
            $password = $credentials->get_decrypted_password($this->encryption_service);
            
            if (empty($username) || empty($password)) {
                return new WP_Error('invalid_credentials', '用户名或密码为空');
            }

            // 测试连接
            $client = new IXR_Client($xmlrpc_url);
            $client->timeout = 10; // 10秒超时
            
            // 调用wp.getProfile方法测试连接
            $result = $client->query('wp.getProfile', $username, $password);
            
            if (!$result) {
                return new WP_Error('connection_failed', 
                    '连接失败: ' . ($client->getErrorMessage() ?: '未知错误'));
            }

            // 更新站点同步状态
            $site->set_sync_status('connected');
            $site->set_last_sync_at(new DateTime());

            $this->logger->info('站点连接测试成功', array(
                'site_id' => $site->get_id(),
                'site_url' => $site->get_url()
            ));

            return true;

        } catch (Exception $e) {
            $this->logger->error('站点连接测试异常', array(
                'site_url' => $site ? $site->get_url() : 'unknown',
                'error' => $e->getMessage()
            ));
            return new WP_Error('exception', '连接测试时发生异常: ' . $e->getMessage());
        }
    }

    /**
     * 获取站点关联的文章数量
     *
     * @param int $site_id 站点ID
     * @return int 文章数量
     */
    private function get_site_article_count($site_id) {
        try {
            global $wpdb;
            $table_name = $this->db_manager->get_table_name('articles');
            
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE site_id = %d",
                    $site_id
                )
            );

            return (int) $count;

        } catch (Exception $e) {
            $this->logger->error('获取站点文章数量异常', array(
                'site_id' => $site_id,
                'error' => $e->getMessage()
            ));
            return 0;
        }
    }

    /**
     * 验证站点数据
     *
     * @param array $site_data 站点数据
     * @return array 验证错误数组
     */
    private function validate_site_data($site_data) {
        $errors = array();

        if (empty($site_data['name'])) {
            $errors[] = '站点名称不能为空';
        }

        if (empty($site_data['url'])) {
            $errors[] = '站点URL不能为空';
        } elseif (!filter_var($site_data['url'], FILTER_VALIDATE_URL)) {
            $errors[] = '站点URL格式无效';
        }

        if (empty($site_data['username'])) {
            $errors[] = '用户名不能为空';
        }

        if (empty($site_data['password'])) {
            $errors[] = '密码不能为空';
        }

        return $errors;
    }
}