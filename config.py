import os

# WordPress配置
WORDPRESS_CONFIG = {
    'url': 'https://your-wordpress-site.com',  # 替换为你的WordPress网站URL
    'username': 'chanyezixun',               # 替换为你的WordPress用户名
    'password': 'TQB!9ostEEjxUobC*)U91FQY',           # 替换为你的WordPress应用密码
    'default_category_id': 16035                   # 默认分类ID
}

# DeepSeek内容审核配置
DEEPSEEK_CONFIG = {
    'api_key': os.getenv('DEEPSEEK_API_KEY', ''),  # 从环境变量获取，如果没有则为空
    'default_strict_level': 2,                     # 默认审核严格级别 1:宽松 2:中等 3:严格
    'cache_expire': 3600,                          # 缓存过期时间（秒）
    'enable_prefilter': True,                      # 是否启用快速预过滤
    'fallback_mode': True                          # API不可用时是否启用降级审核
}

# 服务器配置
SERVER_CONFIG = {
    'host': os.getenv('FLASK_HOST', '0.0.0.0'),   # 监听地址，0.0.0.0允许外网访问
    'port': int(os.getenv('FLASK_PORT', 5000)),   # 监听端口
    'debug': os.getenv('FLASK_DEBUG', 'True').lower() == 'true'  # 调试模式
}

# Flask配置
class Config:
    SECRET_KEY = 'your-secret-key-change-this-in-production'
    DEBUG = True
    
    # 数据库配置
    SQLALCHEMY_DATABASE_URI = 'sqlite:///articles.db'
    SQLALCHEMY_TRACK_MODIFICATIONS = False