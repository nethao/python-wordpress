# WordPress配置
WORDPRESS_CONFIG = {
    'url': 'https://your-wordpress-site.com',  # 替换为你的WordPress网站URL
    'username': 'chanyezixun',               # 替换为你的WordPress用户名
    'password': 'TQB!9ostEEjxUobC*)U91FQY',           # 替换为你的WordPress应用密码
    'default_category_id': 16035                   # 默认分类ID
}

# DeepSeek内容审核配置
DEEPSEEK_CONFIG = {
    'api_key': 'your-deepseek-api-key',        # 替换为你的DeepSeek API密钥
    'default_strict_level': 2,                 # 默认审核严格级别 1:宽松 2:中等 3:严格
    'cache_expire': 3600,                      # 缓存过期时间（秒）
    'enable_prefilter': True,                  # 是否启用快速预过滤
    'fallback_mode': True                      # API不可用时是否启用降级审核
}

# Flask配置
class Config:
    SECRET_KEY = 'your-secret-key-change-this-in-production'
    DEBUG = True
    
    # 数据库配置
    SQLALCHEMY_DATABASE_URI = 'sqlite:///articles.db'
    SQLALCHEMY_TRACK_MODIFICATIONS = False