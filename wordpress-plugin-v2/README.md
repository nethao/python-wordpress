# Article Management System V2.0

企业级多用户内容管理系统WordPress插件，支持多站点发布、分级审核、权限管理和自动化运维功能。

## 功能特性

### 🔐 用户权限管理
- 多角色用户系统（管理员、普通用户）
- 细粒度权限控制
- 用户操作审计日志
- 安全的用户认证机制

### 📝 文章管理
- 文章创建、编辑、删除
- 多状态管理（草稿、待审核、已发布等）
- 文章权限隔离
- 批量操作支持

### 🌐 多站点管理
- 支持多个WordPress站点配置
- 加密存储站点认证信息
- 站点连接状态监控
- 一键发布到多个站点

### 🛡️ 内容审核系统
- 分层敏感词库管理
- 可调节的审核等级（宽松/默认/严格）
- DeepSeek AI内容审核集成
- 自定义敏感词导入/导出

### 🤖 自动化运维
- 定时文章清理
- 自动日志轮转
- API健康检查
- 系统状态监控

### 📊 统计分析
- 用户发布统计
- 系统使用情况分析
- 数据导出功能
- 实时统计更新

## 系统要求

- WordPress 5.0 或更高版本
- PHP 7.4 或更高版本
- MySQL 5.6 或更高版本
- 必需的PHP扩展：
  - cURL
  - JSON
  - OpenSSL
  - Multibyte String

## 安装说明

### 方法一：通过WordPress管理后台安装

1. 下载插件压缩包
2. 登录WordPress管理后台
3. 进入"插件" > "安装插件"
4. 点击"上传插件"
5. 选择下载的压缩包并安装
6. 激活插件

### 方法二：手动安装

1. 下载插件文件
2. 解压到WordPress的 `wp-content/plugins/` 目录
3. 登录WordPress管理后台
4. 进入"插件"页面
5. 找到"Article Management System V2.0"并激活

## 配置指南

### 初始设置

1. **激活插件后**，系统会自动：
   - 创建必要的数据库表
   - 设置默认配置选项
   - 创建默认管理员用户
   - 初始化敏感词库

2. **访问插件管理页面**：
   - 在WordPress管理后台左侧菜单中找到"Article Mgmt V2"
   - 点击进入插件仪表板

### 基本配置

#### 1. 用户管理
- 进入"用户"页面创建和管理用户
- 为用户分配适当的角色（管理员/普通用户）
- 设置用户权限和访问控制

#### 2. 站点配置
- 进入"站点"页面添加WordPress站点
- 配置站点URL、用户名和密码
- 测试站点连接确保配置正确

#### 3. API配置
- 进入"设置" > "API设置"
- 配置DeepSeek API密钥
- 设置API调用参数和重试策略

#### 4. 审核设置
- 进入"敏感词"页面管理敏感词库
- 设置审核等级（宽松/默认/严格）
- 导入自定义敏感词列表

### 高级配置

#### 定时任务设置
插件会自动设置以下定时任务：
- 自动清理：每天凌晨2点执行
- 日志清理：每周执行
- 统计更新：每小时执行
- API健康检查：每6小时执行

#### 安全设置
- 所有敏感数据使用AES-256-GCM加密
- API密钥通过环境变量或wp-config.php管理
- 完整的操作审计日志

## 使用说明

### 管理员操作

#### 用户管理
1. 创建新用户：填写用户信息并分配角色
2. 编辑用户：修改用户信息和权限
3. 删除用户：删除不需要的用户账户

#### 文章审核
1. 查看待审核文章列表
2. 审核文章内容和敏感词检测结果
3. 批准或拒绝文章发布

#### 系统监控
1. 查看系统统计数据
2. 监控API使用情况
3. 检查站点连接状态
4. 查看操作审计日志

### 普通用户操作

#### 文章管理
1. 创建新文章：使用编辑器创建文章
2. 编辑文章：修改已创建的文章
3. 删除文章：删除不需要的文章
4. 查看文章状态：了解文章审核进度

#### 发布文章
1. 选择目标站点
2. 设置发布参数
3. 提交发布请求
4. 查看发布状态

## 短代码使用

插件提供以下短代码供前端使用：

### 文章表单
```
[ams_v2_article_form]
[ams_v2_article_form article_id="123" redirect_url="/success/"]
```

### 文章列表
```
[ams_v2_article_list]
[ams_v2_article_list per_page="5" status="published" author="current"]
```

### 用户仪表板
```
[ams_v2_user_dashboard]
[ams_v2_user_dashboard show_stats="true" show_recent="true"]
```

### 登录表单
```
[ams_v2_login_form]
[ams_v2_login_form redirect_url="/dashboard/"]
```

## API接口

插件提供REST API接口供第三方应用使用：

### 认证
所有API请求需要包含有效的认证令牌。

### 端点列表

#### 文章管理
- `GET /wp-json/ams-v2/v1/articles` - 获取文章列表
- `POST /wp-json/ams-v2/v1/articles` - 创建文章
- `PUT /wp-json/ams-v2/v1/articles/{id}` - 更新文章
- `DELETE /wp-json/ams-v2/v1/articles/{id}` - 删除文章

#### 用户管理
- `GET /wp-json/ams-v2/v1/users` - 获取用户列表
- `POST /wp-json/ams-v2/v1/users` - 创建用户
- `PUT /wp-json/ams-v2/v1/users/{id}` - 更新用户
- `DELETE /wp-json/ams-v2/v1/users/{id}` - 删除用户

#### 统计数据
- `GET /wp-json/ams-v2/v1/statistics` - 获取统计数据
- `GET /wp-json/ams-v2/v1/statistics/export` - 导出统计数据

## 故障排除

### 常见问题

#### 1. 插件激活失败
- 检查WordPress和PHP版本是否满足要求
- 确认必需的PHP扩展已安装
- 检查文件权限设置

#### 2. 数据库连接错误
- 检查数据库配置
- 确认数据库用户权限
- 查看错误日志获取详细信息

#### 3. API调用失败
- 检查API密钥配置
- 确认网络连接正常
- 查看API调用日志

#### 4. 站点连接失败
- 检查站点URL和认证信息
- 确认目标站点XML-RPC功能已启用
- 测试网络连通性

### 日志查看

插件日志文件位置：
- 主日志：`wp-content/uploads/ams-v2-logs/ams-v2-YYYY-MM-DD.log`
- 错误日志：WordPress标准错误日志
- 审计日志：数据库中的audit_logs表

### 性能优化

1. **数据库优化**：
   - 定期清理过期数据
   - 优化数据库索引
   - 使用缓存机制

2. **文件优化**：
   - 启用文件压缩
   - 使用CDN加速
   - 优化图片大小

3. **缓存设置**：
   - 启用对象缓存
   - 配置页面缓存
   - 使用瞬态数据

## 开发指南

### 目录结构
```
wordpress-plugin-v2/
├── admin/                  # 管理后台文件
│   ├── css/               # 管理后台样式
│   ├── js/                # 管理后台脚本
│   └── partials/          # 管理后台模板
├── includes/              # 核心类文件
│   ├── models/            # 数据模型
│   ├── services/          # 业务服务
│   ├── controllers/       # 控制器
│   └── repositories/      # 数据仓储
├── public/                # 公共前端文件
│   ├── css/               # 前端样式
│   ├── js/                # 前端脚本
│   └── templates/         # 前端模板
├── languages/             # 语言文件
└── tests/                 # 测试文件
```

### 扩展开发

#### 添加新功能
1. 创建相应的模型类
2. 实现业务服务逻辑
3. 添加控制器处理请求
4. 创建前端界面
5. 编写单元测试

#### 自定义钩子
插件提供以下钩子供开发者使用：

**动作钩子**：
- `ams_v2_article_created` - 文章创建后
- `ams_v2_article_published` - 文章发布后
- `ams_v2_user_created` - 用户创建后
- `ams_v2_cleanup_completed` - 清理完成后

**过滤器钩子**：
- `ams_v2_article_content` - 过滤文章内容
- `ams_v2_sensitive_words` - 过滤敏感词列表
- `ams_v2_moderation_result` - 过滤审核结果
- `ams_v2_statistics_data` - 过滤统计数据

## 更新日志

### 版本 2.0.0
- 初始版本发布
- 实现核心功能模块
- 添加用户权限管理
- 支持多站点发布
- 集成内容审核系统
- 提供统计分析功能

## 支持与反馈

如果您在使用过程中遇到问题或有改进建议，请通过以下方式联系我们：

- 邮箱：support@example.com
- GitHub：https://github.com/your-repo/article-management-v2
- 文档：https://docs.example.com/article-management-v2

## 许可证

本插件基于GPL v2或更高版本许可证发布。详情请参阅LICENSE文件。

## 贡献

欢迎贡献代码和改进建议！请查看CONTRIBUTING.md了解贡献指南。