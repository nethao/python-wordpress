from flask_sqlalchemy import SQLAlchemy
from flask_login import UserMixin
from datetime import datetime
from werkzeug.security import generate_password_hash, check_password_hash

db = SQLAlchemy()

class User(UserMixin, db.Model):
    __tablename__ = 'users'
    
    id = db.Column(db.Integer, primary_key=True)
    username = db.Column(db.String(80), unique=True, nullable=False)
    password_hash = db.Column(db.String(120), nullable=False)
    email = db.Column(db.String(120), unique=True, nullable=True)
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    is_active = db.Column(db.Boolean, default=True)
    
    # 关联文章
    articles = db.relationship('Article', backref='author', lazy=True, cascade='all, delete-orphan')
    
    def set_password(self, password):
        """设置密码"""
        self.password_hash = generate_password_hash(password)
    
    def check_password(self, password):
        """检查密码"""
        return check_password_hash(self.password_hash, password)
    
    def __repr__(self):
        return f'<User {self.username}>'

class Article(db.Model):
    __tablename__ = 'articles'
    
    id = db.Column(db.Integer, primary_key=True)
    title = db.Column(db.String(200), nullable=False)
    content = db.Column(db.Text, nullable=False)
    summary = db.Column(db.String(500), nullable=True)  # 文章摘要
    
    # 时间戳
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    updated_at = db.Column(db.DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    published_at = db.Column(db.DateTime, nullable=True)
    
    # 状态
    status = db.Column(db.String(20), default='draft')  # draft, published, archived
    is_published = db.Column(db.Boolean, default=False)
    
    # 审核信息
    audit_score = db.Column(db.Float, default=0.0)
    risk_level = db.Column(db.String(20), default='low')  # low, medium, high
    audit_reasons = db.Column(db.Text, nullable=True)  # JSON格式存储审核原因
    audit_suggestions = db.Column(db.Text, nullable=True)  # JSON格式存储修改建议
    flagged_keywords = db.Column(db.Text, nullable=True)  # JSON格式存储违规关键词
    
    # WordPress信息
    wp_post_id = db.Column(db.Integer, nullable=True)
    wp_url = db.Column(db.String(500), nullable=True)
    wp_category_id = db.Column(db.Integer, nullable=True)
    
    # 统计信息
    view_count = db.Column(db.Integer, default=0)
    word_count = db.Column(db.Integer, default=0)
    
    # 外键
    user_id = db.Column(db.Integer, db.ForeignKey('users.id'), nullable=False)
    
    # 标签关联
    tags = db.relationship('Tag', secondary='article_tags', backref='articles', lazy='subquery')
    
    def __repr__(self):
        return f'<Article {self.title}>'
    
    def to_dict(self):
        """转换为字典格式"""
        return {
            'id': self.id,
            'title': self.title,
            'content': self.content,
            'summary': self.summary,
            'created_at': self.created_at.strftime('%Y-%m-%d %H:%M:%S') if self.created_at else None,
            'updated_at': self.updated_at.strftime('%Y-%m-%d %H:%M:%S') if self.updated_at else None,
            'published_at': self.published_at.strftime('%Y-%m-%d %H:%M:%S') if self.published_at else None,
            'status': self.status,
            'is_published': self.is_published,
            'audit_score': self.audit_score,
            'risk_level': self.risk_level,
            'wp_post_id': self.wp_post_id,
            'wp_url': self.wp_url,
            'view_count': self.view_count,
            'word_count': self.word_count,
            'author': self.author.username if self.author else None,
            'tags': [tag.name for tag in self.tags]
        }
    
    def update_word_count(self):
        """更新字数统计"""
        import re
        # 移除HTML标签和Markdown语法，计算纯文本字数
        clean_text = re.sub(r'<[^>]+>', '', self.content)
        clean_text = re.sub(r'[#*`\[\]()_~]', '', clean_text)
        self.word_count = len(clean_text.replace(' ', '').replace('\n', ''))
    
    def generate_summary(self, length=200):
        """生成文章摘要"""
        import re
        # 移除HTML标签和Markdown语法
        clean_text = re.sub(r'<[^>]+>', '', self.content)
        clean_text = re.sub(r'[#*`\[\]()_~]', '', clean_text)
        clean_text = clean_text.strip()
        
        if len(clean_text) <= length:
            self.summary = clean_text
        else:
            self.summary = clean_text[:length] + '...'

class Tag(db.Model):
    __tablename__ = 'tags'
    
    id = db.Column(db.Integer, primary_key=True)
    name = db.Column(db.String(50), unique=True, nullable=False)
    color = db.Column(db.String(7), default='#007bff')  # 标签颜色
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    
    def __repr__(self):
        return f'<Tag {self.name}>'

# 文章标签关联表
article_tags = db.Table('article_tags',
    db.Column('article_id', db.Integer, db.ForeignKey('articles.id'), primary_key=True),
    db.Column('tag_id', db.Integer, db.ForeignKey('tags.id'), primary_key=True)
)

class AuditLog(db.Model):
    __tablename__ = 'audit_logs'
    
    id = db.Column(db.Integer, primary_key=True)
    article_id = db.Column(db.Integer, db.ForeignKey('articles.id'), nullable=False)
    user_id = db.Column(db.Integer, db.ForeignKey('users.id'), nullable=False)
    
    # 审核结果
    passed = db.Column(db.Boolean, nullable=False)
    score = db.Column(db.Float, nullable=False)
    risk_level = db.Column(db.String(20), nullable=False)
    reasons = db.Column(db.Text, nullable=True)  # JSON格式
    suggestions = db.Column(db.Text, nullable=True)  # JSON格式
    flagged_keywords = db.Column(db.Text, nullable=True)  # JSON格式
    
    # 审核配置
    strict_level = db.Column(db.Integer, default=2)
    audit_type = db.Column(db.String(20), default='manual')  # manual, auto
    
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    
    # 关联
    article = db.relationship('Article', backref='audit_logs')
    user = db.relationship('User', backref='audit_logs')
    
    def __repr__(self):
        return f'<AuditLog {self.id}>'

class PublishLog(db.Model):
    __tablename__ = 'publish_logs'
    
    id = db.Column(db.Integer, primary_key=True)
    article_id = db.Column(db.Integer, db.ForeignKey('articles.id'), nullable=False)
    user_id = db.Column(db.Integer, db.ForeignKey('users.id'), nullable=False)
    
    # 发布信息
    wp_post_id = db.Column(db.Integer, nullable=True)
    wp_url = db.Column(db.String(500), nullable=True)
    wp_category_id = db.Column(db.Integer, nullable=True)
    
    # 发布状态
    status = db.Column(db.String(20), nullable=False)  # success, failed, pending
    error_message = db.Column(db.Text, nullable=True)
    
    created_at = db.Column(db.DateTime, default=datetime.utcnow)
    
    # 关联
    article = db.relationship('Article', backref='publish_logs')
    user = db.relationship('User', backref='publish_logs')
    
    def __repr__(self):
        return f'<PublishLog {self.id}>'