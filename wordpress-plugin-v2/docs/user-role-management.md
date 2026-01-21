# 用户角色管理系统文档

## 概述

用户角色管理系统是WordPress插件V2.0升级项目的核心组件，提供了完整的用户认证、角色管理和权限控制功能。

## 核心组件

### 1. 模型类

#### AMS_V2_User
用户模型类，包含以下主要功能：
- 用户基本信息管理（用户名、邮箱、显示名称等）
- 密码设置和验证
- 用户状态管理（激活/禁用）
- 角色关联管理
- 权限检查

**主要方法：**
- `get_id()` / `set_id($id)` - 用户ID管理
- `get_username()` / `set_username($username)` - 用户名管理
- `get_email()` / `set_email($email)` - 邮箱管理
- `verify_password($password)` - 密码验证
- `set_password($password)` - 设置密码
- `has_role($role_name)` - 检查是否有指定角色
- `has_capability($capability)` - 检查是否有指定权限
- `is_administrator()` - 检查是否为管理员
- `is_user()` - 检查是否为普通用户

#### AMS_V2_Role
角色模型类，包含以下主要功能：
- 角色基本信息管理
- 权限列表管理
- 系统角色标识

**主要方法：**
- `get_name()` / `set_name($name)` - 角色名称管理
- `get_display_name()` / `set_display_name($display_name)` - 显示名称管理
- `get_capabilities()` / `set_capabilities($capabilities)` - 权限列表管理
- `add_capability($capability)` - 添加权限
- `remove_capability($capability)` - 移除权限
- `has_capability($capability)` - 检查是否有指定权限
- `is_system()` - 检查是否为系统角色

### 2. 接口定义

#### AMS_V2_Role_Manager_Interface
角色管理器接口，定义了角色管理的标准操作：
- 角色CRUD操作
- 用户角色分配和移除
- 权限检查和管理

#### AMS_V2_Authentication_Manager_Interface
认证管理器接口，定义了用户认证和管理的标准操作：
- 用户认证和登录/登出
- 用户CRUD操作
- 权限验证
- 密码管理

### 3. 实现类

#### AMS_V2_Role_Manager
角色管理器实现类，提供完整的角色管理功能：

**主要功能：**
- 创建、更新、删除角色
- 为用户分配和移除角色
- 检查用户角色和权限
- 管理角色权限

**使用示例：**
```php
// 获取角色管理器
$role_manager = $container->get('role_manager');

// 创建新角色
$role = $role_manager->create_role(
    'editor',
    array('edit_articles', 'publish_articles'),
    '编辑者',
    '可以编辑和发布文章的用户'
);

// 为用户分配角色
$role_manager->assign_role($user, $role);

// 检查用户权限
if ($role_manager->has_capability($user, 'edit_articles')) {
    // 用户有编辑文章的权限
}
```

#### AMS_V2_Authentication_Manager
认证管理器实现类，提供完整的用户认证和管理功能：

**主要功能：**
- 用户认证和会话管理
- 用户CRUD操作
- 权限检查和操作权限验证
- 密码管理和安全功能

**使用示例：**
```php
// 获取认证管理器
$auth_manager = $container->get('authentication_manager');

// 用户认证
$user = $auth_manager->authenticate($username, $password);
if (!is_wp_error($user)) {
    // 认证成功，登录用户
    $auth_manager->login($user);
}

// 创建新用户
$user_data = array(
    'username' => 'newuser',
    'email' => 'newuser@example.com',
    'password' => 'secure_password',
    'role' => 'user'
);
$user = $auth_manager->create_user($user_data);

// 检查操作权限
if ($auth_manager->check_permission($user, 'edit_article', $article)) {
    // 用户有权限编辑这篇文章
}
```

## 权限系统

### 默认角色

系统预定义了两个默认角色：

#### 管理员 (administrator)
拥有系统所有权限：
- `manage_users` - 管理用户
- `manage_roles` - 管理角色
- `manage_articles` - 管理文章
- `manage_sites` - 管理站点
- `manage_sensitive_words` - 管理敏感词
- `manage_api_keys` - 管理API密钥
- `view_audit_logs` - 查看审计日志
- `manage_cleanup` - 管理清理
- `view_statistics` - 查看统计
- `moderate_content` - 审核内容

#### 普通用户 (user)
拥有基本的文章管理权限：
- `create_articles` - 创建文章
- `edit_own_articles` - 编辑自己的文章
- `delete_own_articles` - 删除自己的文章
- `view_own_articles` - 查看自己的文章
- `publish_articles` - 发布文章

### 权限检查机制

系统提供了多层次的权限检查：

1. **基础权限检查** - 检查用户是否有指定权限
2. **操作权限检查** - 根据操作类型和资源进行权限验证
3. **资源所有权检查** - 确保用户只能操作自己的资源

## 数据库结构

### 用户表 (ams_v2_users)
- `id` - 用户ID
- `username` - 用户名
- `email` - 邮箱
- `password_hash` - 密码哈希
- `display_name` - 显示名称
- `is_active` - 是否激活
- `created_at` - 创建时间
- `updated_at` - 更新时间
- `last_login_at` - 最后登录时间

### 角色表 (ams_v2_roles)
- `id` - 角色ID
- `name` - 角色名称
- `display_name` - 显示名称
- `description` - 角色描述
- `capabilities` - 权限列表（JSON格式）
- `is_system` - 是否为系统角色
- `created_at` - 创建时间
- `updated_at` - 更新时间

### 用户角色关联表 (ams_v2_user_roles)
- `id` - 关联ID
- `user_id` - 用户ID
- `role_id` - 角色ID
- `assigned_at` - 分配时间
- `assigned_by` - 分配者ID

## 安全特性

1. **密码安全** - 使用WordPress内置的密码哈希函数
2. **会话管理** - 安全的会话处理和cookie管理
3. **权限验证** - 多层次的权限检查机制
4. **审计日志** - 记录关键操作的审计信息
5. **输入验证** - 严格的输入数据验证和清理

## 测试

系统提供了两种类型的测试：

### 基础单元测试
测试模型类的基本功能，包括：
- 用户模型基本功能
- 角色模型基本功能
- 用户角色关联
- 密码功能

### 集成测试
测试完整的用户角色管理功能，包括：
- 角色创建
- 用户创建
- 角色分配
- 权限检查
- 用户认证

## 使用指南

### 1. 获取服务实例
```php
// 通过容器获取服务
$container = $plugin->get_container();
$role_manager = $container->get('role_manager');
$auth_manager = $container->get('authentication_manager');
```

### 2. 用户管理
```php
// 创建用户
$user_data = array(
    'username' => 'username',
    'email' => 'email@example.com',
    'password' => 'password',
    'role' => 'user'
);
$user = $auth_manager->create_user($user_data);

// 更新用户
$user->set_display_name('新显示名称');
$auth_manager->update_user($user);

// 删除用户
$auth_manager->delete_user($user_id);
```

### 3. 角色管理
```php
// 创建角色
$role = $role_manager->create_role(
    'custom_role',
    array('custom_capability'),
    '自定义角色'
);

// 分配角色
$role_manager->assign_role($user, $role);

// 检查权限
if ($role_manager->has_capability($user, 'custom_capability')) {
    // 执行需要权限的操作
}
```

### 4. 认证流程
```php
// 用户登录
$user = $auth_manager->authenticate($username, $password);
if (!is_wp_error($user)) {
    $auth_manager->login($user);
    // 登录成功
}

// 检查登录状态
if ($auth_manager->is_logged_in()) {
    $current_user = $auth_manager->get_current_user();
}

// 用户登出
$auth_manager->logout();
```

## 扩展性

系统采用接口驱动的设计，便于扩展：

1. **自定义角色** - 可以创建自定义角色和权限
2. **权限扩展** - 可以添加新的权限类型
3. **认证扩展** - 可以扩展认证方式
4. **存储扩展** - 可以替换数据存储实现

## 注意事项

1. 系统角色（administrator和user）不能被删除或修改权限
2. 删除用户前需要确保没有关联的文章
3. 删除角色前需要确保没有用户使用该角色
4. 所有敏感操作都会记录审计日志
5. 密码修改会立即生效，用户需要重新登录