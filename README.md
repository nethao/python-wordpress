# 文章管理系统 - DeepSeek内容审核版

一个基于Flask的Web文章管理系统，集成DeepSeek AI内容审核，支持用户登录、文章编辑、智能内容审核和WordPress自动发布功能。

## 功能特性

- **用户登录系统**: 安全的用户认证
- **文章管理**: 创建、编辑、删除文章
- **双模式编辑器**: 
  - Markdown编辑模式
  - HTML代码编辑模式
  - 实时预览功能
- **DeepSeek AI内容审核**: 
  - 智能内容审核，替代传统敏感词库
  - 多级审核严格度设置
  - 详细的审核报告和修改建议
  - 风险评分和等级评估
  - 内容净化功能
- **WordPress集成**: 自动发布文章到指定WordPress站点

## 安装和运行

### 1. 安装依赖

```bash
pip install -r requirements.txt
```

### 2. 初始化数据库

```bash
# 初始化数据库和创建默认用户
python init_db.py
```

### 3. 配置DeepSeek API

1. 复制环境变量配置文件：
```bash
cp .env.example .env
```

2. 编辑 `.env` 文件，设置你的DeepSeek API密钥：
```
DEEPSEEK_API_KEY=your-deepseek-api-key-here
```

3. 或者直接在 `config.py` 中配置：
```python
DEEPSEEK_CONFIG = {
    'api_key': 'your-deepseek-api-key',
    'default_strict_level': 2,  # 1:宽松 2:中等 3:严格
}
```

### 4. 配置WordPress（可选）

编辑 `config.py` 文件，设置你的WordPress信息：

```python
WORDPRESS_CONFIG = {
    'url': 'https://your-wordpress-site.com',
    'username': 'your-username',
    'password': 'your-app-password',
    'default_category_id': 1
}
```

### 5. 运行应用

```bash
python app.py
```

默认情况下，应用将在所有网络接口上监听，允许外网访问：
- 本地访问: `http://localhost:5000`
- 外网访问: `http://your-server-ip:5000`

### 6. 配置网络访问

**仅本地访问（更安全）:**
```bash
export FLASK_HOST=127.0.0.1
python app.py
```

**外网访问（默认）:**
```bash
export FLASK_HOST=0.0.0.0
python app.py
```

**自定义端口:**
```bash
export FLASK_PORT=8080
python app.py
```

### 7. 安全配置

如果启用外网访问，建议运行安全配置脚本：
```bash
python setup_security.py
```

该脚本将帮助你：
- 检查防火墙状态
- 配置防火墙规则
- 获取网络信息
- 提供安全建议

## 默认登录信息

- 用户名: `admin`
- 密码: `admin123`

## DeepSeek内容审核功能

### 审核级别

- **级别1 (宽松)**: 仅检查明显违规内容
- **级别2 (中等)**: 标准审核，平衡准确性和严格度
- **级别3 (严格)**: 严格审核，对可疑内容零容忍

### 审核维度

1. **政治敏感**: 涉及国家领导人、政治事件、敏感历史
2. **违法违规**: 暴力、色情、赌博、毒品、诈骗
3. **人身攻击**: 侮辱、诽谤、歧视、仇恨言论
4. **广告营销**: 联系方式、引流信息、商业推广
5. **隐私泄露**: 身份证号、手机号、住址、银行卡号
6. **虚假信息**: 谣言、不实信息、误导性内容
7. **不良价值观**: 拜金主义、极端言论

### 审核结果

- **通过状态**: 是否通过审核
- **风险评分**: 0-100%的风险评分
- **风险等级**: 低风险/中风险/高风险
- **违规关键词**: 具体的问题词汇
- **审核原因**: 详细的不通过原因
- **修改建议**: AI提供的具体修改建议
- **净化内容**: 敏感词替换后的内容预览

## 数据管理功能

### 文章管理

- **状态管理**: 草稿、已发布、已归档三种状态
- **搜索筛选**: 按标题搜索，按状态筛选
- **分页显示**: 支持大量文章的分页浏览
- **批量操作**: 批量归档、恢复、删除文章
- **统计信息**: 文章数量、字数、浏览量统计

### 标签系统

- **标签管理**: 创建、编辑、删除标签
- **颜色标识**: 为标签设置不同颜色
- **文章分类**: 为文章添加多个标签进行分类
- **标签筛选**: 按标签筛选文章

### 审核历史

- **审核记录**: 完整的审核历史记录
- **审核详情**: 详细的审核原因和建议
- **风险追踪**: 文章风险等级变化追踪

### 发布历史

- **发布记录**: WordPress发布历史
- **发布状态**: 成功、失败、待处理状态追踪
- **错误日志**: 详细的发布错误信息

## 数据库管理

### 初始化数据库

```bash
# 创建数据库表和默认数据
python init_db.py
```

### 数据备份和迁移

```bash
# 备份数据库到JSON文件
python migrate_db.py backup

# 从JSON文件恢复数据
python migrate_db.py migrate
```

### 数据库结构

- **users**: 用户表
- **articles**: 文章表
- **tags**: 标签表
- **article_tags**: 文章标签关联表
- **audit_logs**: 审核日志表
- **publish_logs**: 发布日志表

## 使用说明

1. 登录后点击"新建文章"
2. 输入文章标题
3. 在Markdown编辑器中编写内容
4. 实时预览功能会显示渲染效果
5. 可切换到HTML模式查看/编辑HTML代码

### 内容审核

1. 点击"检查敏感词"按钮进行AI审核
2. 系统会显示详细的审核报告
3. 如有问题，会提供具体的修改建议
4. 保存文章时会自动进行审核

### 发布到WordPress

1. 在文章列表中点击"发布"按钮
2. 输入WordPress站点信息
3. 选择发布分类（可选）
4. 确认发布

## 技术栈

- **后端**: Flask, Flask-Login, Flask-SQLAlchemy
- **数据库**: SQLite (可扩展到 MySQL/PostgreSQL)
- **前端**: Bootstrap 5, JavaScript
- **编辑器**: Marked.js (Markdown解析)
- **AI审核**: DeepSeek API
- **WordPress集成**: python-wordpress-xmlrpc

## 文件结构

```
├── app.py              # 主应用文件
├── models.py           # 数据库模型
├── deepseek_audit.py   # DeepSeek审核服务
├── config.py           # 配置文件
├── init_db.py          # 数据库初始化脚本
├── migrate_db.py       # 数据库迁移脚本
├── requirements.txt    # 依赖包列表
├── .env.example        # 环境变量示例
├── articles.db         # SQLite数据库文件（运行后生成）
├── templates/          # HTML模板
│   ├── base.html      # 基础模板
│   ├── login.html     # 登录页面
│   ├── dashboard.html # 文章管理页面
│   └── editor.html    # 文章编辑器
└── README.md          # 说明文档
```

## 高级配置

### 自定义审核规则

可以在 `deepseek_audit.py` 中修改审核提示词来自定义审核规则：

```python
def _get_audit_prompt(self, content: str, strict_level: int) -> str:
    # 自定义你的审核规则
    pass
```

### 缓存配置

审核结果会自动缓存1小时，可以在配置中调整：

```python
DEEPSEEK_CONFIG = {
    'cache_expire': 3600,  # 缓存时间（秒）
}
```

### 降级模式

当DeepSeek API不可用时，系统会自动降级到基础规则审核，确保服务可用性。

## 注意事项

1. 请确保DeepSeek API密钥有效且有足够额度
2. 首次运行前请执行 `python init_db.py` 初始化数据库
3. 数据库文件 `articles.db` 包含所有用户数据，请定期备份
4. WordPress应用密码需要在WordPress后台生成
5. 确保WordPress站点启用了XML-RPC功能
6. 审核结果会缓存，重复内容不会重复调用API
7. 建议定期使用 `migrate_db.py backup` 备份数据
8. **外网访问安全**: 如果启用外网访问，请：
   - 修改默认管理员密码
   - 配置防火墙规则
   - 考虑使用HTTPS
   - 限制访问IP范围
   - 定期监控访问日志

## 快捷键

- `Ctrl + S`: 保存文章
- `Ctrl + Shift + P`: 检查敏感词

## API接口

### 内容审核接口

```
POST /api/check_sensitive
Content-Type: application/json

{
    "content": "要审核的内容",
    "strict_level": 2
}
```

### 保存文章接口

```
POST /api/save_article
Content-Type: application/json

{
    "title": "文章标题",
    "content": "文章内容",
    "strict_level": 2,
    "article_id": 1  // 可选，更新现有文章时提供
}
```

## 许可证

MIT License