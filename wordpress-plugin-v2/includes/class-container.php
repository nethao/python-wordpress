<?php
/**
 * 依赖注入容器类
 *
 * @package Article_Management_V2
 * @subpackage Includes
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 依赖注入容器类
 */
class AMS_V2_Container {

    /**
     * 服务定义
     *
     * @var array
     */
    private $services = array();

    /**
     * 服务实例
     *
     * @var array
     */
    private $instances = array();

    /**
     * 单例服务
     *
     * @var array
     */
    private $singletons = array();

    /**
     * 注册服务
     *
     * @param string $name 服务名称
     * @param callable $factory 工厂函数
     * @param bool $singleton 是否为单例
     */
    public function register($name, $factory, $singleton = true) {
        $this->services[$name] = $factory;
        
        if ($singleton) {
            $this->singletons[$name] = true;
        }
    }

    /**
     * 获取服务实例
     *
     * @param string $name 服务名称
     * @return mixed 服务实例
     * @throws AMS_V2_Container_Exception
     */
    public function get($name) {
        // 检查是否已有实例且为单例
        if (isset($this->instances[$name]) && isset($this->singletons[$name])) {
            return $this->instances[$name];
        }

        // 检查服务是否已注册
        if (!isset($this->services[$name])) {
            throw new AMS_V2_Container_Exception("Service '{$name}' not found in container");
        }

        // 创建实例
        $factory = $this->services[$name];
        $instance = $factory($this);

        // 如果是单例，缓存实例
        if (isset($this->singletons[$name])) {
            $this->instances[$name] = $instance;
        }

        return $instance;
    }

    /**
     * 检查服务是否存在
     *
     * @param string $name 服务名称
     * @return bool
     */
    public function has($name) {
        return isset($this->services[$name]);
    }

    /**
     * 移除服务
     *
     * @param string $name 服务名称
     */
    public function remove($name) {
        unset($this->services[$name]);
        unset($this->instances[$name]);
        unset($this->singletons[$name]);
    }

    /**
     * 获取所有已注册的服务名称
     *
     * @return array
     */
    public function getServiceNames() {
        return array_keys($this->services);
    }

    /**
     * 清空容器
     */
    public function clear() {
        $this->services = array();
        $this->instances = array();
        $this->singletons = array();
    }
}

/**
 * 容器异常类
 */
class AMS_V2_Container_Exception extends Exception {
    
}