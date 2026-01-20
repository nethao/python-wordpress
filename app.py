from flask import Flask, render_template, request, redirect, url_for, flash, jsonify
from flask_login import LoginManager, UserMixin, login_user, logout_user, login_required, current_user
from flask_sqlalchemy import SQLAlchemy
from flask_migrate import Migrate
import json
import os
from datetime import datetime
from wordpress_xmlrpc import Client, WordPressPost
from wordpress_xmlrpc.methods.posts import NewPost
from deepseek_audit import init_audit_service, get_audit_service
from config import Config, DEEPSEEK_CONFIG
from models import db, User, Article, Tag, AuditLog, PublishLog

app = Flask(__name__)
app.config.from_object(Config)

# 初始化扩展
db.init_app(app)
migrate = Migrate(app, db)

# Flask-Login设置
login_manager = LoginManager()
login_manager.init_app(app)
login_manager.login_view = 'login'

# 初始化DeepSeek审核服务
audit_service = init_audit_service(DEEPSEEK_CONFIG.get('api_key'))

# 简单的用户数据存储（用于初始化）
def init_default_user():
    """初始化默认用户"""
    with app.app_context():
        if not User.query.filter_by(username='admin').first():
            admin_user = User(username='admin')
            admin_user.set_password('admin123')
            db.session.add(admin_user)
            db.session.commit()
            print("默认管理员用户已创建: admin/admin123")

@login_manager.user_loader
def load_user(user_id):
    return User.query.get(int(user_id))

class WordPressPublisher:
    def __init__(self, wp_url, username, password):
        self.wp_url = wp_url
        self.username = username
        self.password = password
        self.client = None
        
    def connect(self):
        """连接到WordPress"""
        try:
            self.client = Client(f"{self.wp_url}/xmlrpc.php", self.username, self.password)
            return True
        except Exception as e:
            print(f"WordPress连接失败: {e}")
            return False
    
    def publish_post(self, title, content, category_id=None):
        """发布文章到WordPress"""
        if not self.client and not self.connect():
            return False
            
        try:
            post = WordPressPost()
            post.title = title
            post.content = content
            post.post_status = 'publish'
            
            if category_id:
                post.terms_names = {'category': [category_id]}
            
            post_id = self.client.call(NewPost(post))
            return post_id
        except Exception as e:
            print(f"发布失败: {e}")
            return False

@app.route('/')
def index():
    return redirect(url_for('login'))

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        username = request.form['username']
        password = request.form['password']
        
        user = User.query.filter_by(username=username).first()
        
        if user and user.check_password(password):
            login_user(user)
            return redirect(url_for('dashboard'))
        else:
            flash('用户名或密码错误')
    
    return render_template('login.html')

@app.route('/logout')
@login_required
def logout():
    logout_user()
    return redirect(url_for('login'))

@app.route('/dashboard')
@login_required
def dashboard():
    # 获取用户的文章，支持分页和筛选
    page = request.args.get('page', 1, type=int)
    status = request.args.get('status', 'all')
    search = request.args.get('search', '')
    
    # 构建查询
    query = Article.query.filter_by(user_id=current_user.id)
    
    # 状态筛选
    if status != 'all':
        query = query.filter_by(status=status)
    
    # 搜索筛选
    if search:
        query = query.filter(Article.title.contains(search))
    
    # 排序和分页
    articles = query.order_by(Article.updated_at.desc()).paginate(
        page=page, per_page=12, error_out=False
    )
    
    # 统计信息
    stats = {
        'total': Article.query.filter_by(user_id=current_user.id).count(),
        'published': Article.query.filter_by(user_id=current_user.id, status='published').count(),
        'draft': Article.query.filter_by(user_id=current_user.id, status='draft').count(),
        'archived': Article.query.filter_by(user_id=current_user.id, status='archived').count(),
    }
    
    return render_template('dashboard.html', articles=articles, stats=stats, 
                         current_status=status, search=search)

@app.route('/editor')
@app.route('/editor/<int:article_id>')
@login_required
def editor(article_id=None):
    article = None
    if article_id:
        article = Article.query.filter_by(id=article_id, user_id=current_user.id).first()
        if not article:
            flash('文章不存在或无权限访问')
            return redirect(url_for('dashboard'))
    
    # 获取所有标签
    tags = Tag.query.all()
    
    return render_template('editor.html', article=article, tags=tags)

@app.route('/api/check_sensitive', methods=['POST'])
@login_required
def check_sensitive():
    """检查敏感词API - 使用DeepSeek审核"""
    data = request.get_json()
    content = data.get('content', '')
    strict_level = data.get('strict_level', DEEPSEEK_CONFIG.get('default_strict_level', 2))
    
    if not content.strip():
        return jsonify({
            'has_sensitive': False,
            'sensitive_words': [],
            'suggestions': [],
            'risk_level': 'low',
            'score': 0.0
        })
    
    # 使用DeepSeek审核
    audit_result = audit_service.audit_content(content, strict_level)
    
    return jsonify({
        'has_sensitive': not audit_result['passed'],
        'sensitive_words': audit_result['flagged_keywords'],
        'suggestions': audit_result['suggestions'],
        'risk_level': audit_result['risk_level'],
        'score': audit_result['score'],
        'reasons': audit_result['reasons'],
        'sanitized_content': audit_result.get('sanitized_content', content)
    })

@app.route('/api/save_article', methods=['POST'])
@login_required
def save_article():
    """保存文章API - 集成数据库和DeepSeek审核"""
    data = request.get_json()
    title = data.get('title', '').strip()
    content = data.get('content', '').strip()
    article_id = data.get('article_id')
    tags_data = data.get('tags', [])
    strict_level = data.get('strict_level', DEEPSEEK_CONFIG.get('default_strict_level', 2))
    
    if not title or not content:
        return jsonify({
            'success': False,
            'message': '标题和内容不能为空'
        })
    
    # 使用DeepSeek审核标题和内容
    full_content = f"{title} {content}"
    audit_result = audit_service.audit_content(full_content, strict_level)
    
    try:
        if article_id:
            # 更新现有文章
            article = Article.query.filter_by(id=article_id, user_id=current_user.id).first()
            if not article:
                return jsonify({
                    'success': False,
                    'message': '文章不存在或无权限访问'
                })
            
            article.title = title
            article.content = content
            article.updated_at = datetime.utcnow()
        else:
            # 创建新文章
            article = Article(
                title=title,
                content=content,
                user_id=current_user.id,
                status='draft'
            )
            db.session.add(article)
        
        # 更新审核信息
        article.audit_score = audit_result['score']
        article.risk_level = audit_result['risk_level']
        article.audit_reasons = json.dumps(audit_result['reasons'], ensure_ascii=False)
        article.audit_suggestions = json.dumps(audit_result['suggestions'], ensure_ascii=False)
        article.flagged_keywords = json.dumps(audit_result['flagged_keywords'], ensure_ascii=False)
        
        # 更新字数和摘要
        article.update_word_count()
        article.generate_summary()
        
        # 处理标签
        article.tags.clear()
        for tag_name in tags_data:
            tag_name = tag_name.strip()
            if tag_name:
                tag = Tag.query.filter_by(name=tag_name).first()
                if not tag:
                    tag = Tag(name=tag_name)
                    db.session.add(tag)
                article.tags.append(tag)
        
        # 记录审核日志
        audit_log = AuditLog(
            article_id=article.id if article.id else None,
            user_id=current_user.id,
            passed=audit_result['passed'],
            score=audit_result['score'],
            risk_level=audit_result['risk_level'],
            reasons=json.dumps(audit_result['reasons'], ensure_ascii=False),
            suggestions=json.dumps(audit_result['suggestions'], ensure_ascii=False),
            flagged_keywords=json.dumps(audit_result['flagged_keywords'], ensure_ascii=False),
            strict_level=strict_level,
            audit_type='auto'
        )
        
        db.session.commit()
        
        # 如果是新文章，需要更新审核日志的article_id
        if not article_id:
            audit_log.article_id = article.id
            db.session.commit()
        
        if not audit_result['passed']:
            return jsonify({
                'success': False,
                'message': '文章审核未通过，已保存为草稿',
                'reasons': audit_result['reasons'],
                'sensitive_words': audit_result['flagged_keywords'],
                'suggestions': audit_result['suggestions'],
                'risk_level': audit_result['risk_level'],
                'score': audit_result['score'],
                'article_id': article.id
            })
        
        return jsonify({
            'success': True,
            'message': '文章保存成功',
            'article_id': article.id,
            'audit_info': {
                'score': audit_result['score'],
                'risk_level': audit_result['risk_level']
            }
        })
        
    except Exception as e:
        db.session.rollback()
        return jsonify({
            'success': False,
            'message': f'保存失败: {str(e)}'
        })

@app.route('/api/publish_article', methods=['POST'])
@login_required
def publish_article():
    """发布文章到WordPress"""
    data = request.get_json()
    article_id = data.get('article_id')
    wp_config = data.get('wp_config', {})
    
    article = Article.query.filter_by(id=article_id, user_id=current_user.id).first()
    if not article:
        return jsonify({'success': False, 'message': '文章不存在或无权限访问'})
    
    try:
        # WordPress发布
        wp_publisher = WordPressPublisher(
            wp_config.get('url', ''),
            wp_config.get('username', ''),
            wp_config.get('password', '')
        )
        
        post_id = wp_publisher.publish_post(
            article.title, 
            article.content,
            wp_config.get('category_id')
        )
        
        # 记录发布日志
        publish_log = PublishLog(
            article_id=article.id,
            user_id=current_user.id,
            wp_category_id=wp_config.get('category_id'),
            status='success' if post_id else 'failed'
        )
        
        if post_id:
            article.status = 'published'
            article.is_published = True
            article.published_at = datetime.utcnow()
            article.wp_post_id = post_id
            article.wp_url = f"{wp_config.get('url', '')}/wp-admin/post.php?post={post_id}&action=edit"
            
            publish_log.wp_post_id = post_id
            publish_log.wp_url = article.wp_url
            
            db.session.add(publish_log)
            db.session.commit()
            
            return jsonify({
                'success': True, 
                'message': '文章发布成功', 
                'wp_post_id': post_id,
                'wp_url': article.wp_url
            })
        else:
            publish_log.error_message = 'WordPress发布失败'
            db.session.add(publish_log)
            db.session.commit()
            
            return jsonify({'success': False, 'message': 'WordPress发布失败'})
            
    except Exception as e:
        # 记录失败日志
        publish_log = PublishLog(
            article_id=article.id,
            user_id=current_user.id,
            wp_category_id=wp_config.get('category_id'),
            status='failed',
            error_message=str(e)
        )
        db.session.add(publish_log)
        db.session.commit()
        
        return jsonify({'success': False, 'message': f'发布失败: {str(e)}'})

@app.route('/api/delete_article/<int:article_id>', methods=['DELETE'])
@login_required
def delete_article(article_id):
    """删除文章"""
    article = Article.query.filter_by(id=article_id, user_id=current_user.id).first()
    if not article:
        return jsonify({'success': False, 'message': '文章不存在或无权限访问'})
    
    try:
        db.session.delete(article)
        db.session.commit()
        return jsonify({'success': True, 'message': '文章删除成功'})
    except Exception as e:
        db.session.rollback()
        return jsonify({'success': False, 'message': f'删除失败: {str(e)}'})

@app.route('/api/archive_article/<int:article_id>', methods=['POST'])
@login_required
def archive_article(article_id):
    """归档文章"""
    article = Article.query.filter_by(id=article_id, user_id=current_user.id).first()
    if not article:
        return jsonify({'success': False, 'message': '文章不存在或无权限访问'})
    
    try:
        article.status = 'archived'
        db.session.commit()
        return jsonify({'success': True, 'message': '文章已归档'})
    except Exception as e:
        db.session.rollback()
        return jsonify({'success': False, 'message': f'归档失败: {str(e)}'})

@app.route('/api/restore_article/<int:article_id>', methods=['POST'])
@login_required
def restore_article(article_id):
    """恢复文章"""
    article = Article.query.filter_by(id=article_id, user_id=current_user.id).first()
    if not article:
        return jsonify({'success': False, 'message': '文章不存在或无权限访问'})
    
    try:
        article.status = 'draft' if not article.is_published else 'published'
        db.session.commit()
        return jsonify({'success': True, 'message': '文章已恢复'})
    except Exception as e:
        db.session.rollback()
        return jsonify({'success': False, 'message': f'恢复失败: {str(e)}'})

@app.route('/api/article_stats')
@login_required
def article_stats():
    """获取文章统计信息"""
    stats = {
        'total': Article.query.filter_by(user_id=current_user.id).count(),
        'published': Article.query.filter_by(user_id=current_user.id, status='published').count(),
        'draft': Article.query.filter_by(user_id=current_user.id, status='draft').count(),
        'archived': Article.query.filter_by(user_id=current_user.id, status='archived').count(),
        'total_words': db.session.query(db.func.sum(Article.word_count)).filter_by(user_id=current_user.id).scalar() or 0,
        'total_views': db.session.query(db.func.sum(Article.view_count)).filter_by(user_id=current_user.id).scalar() or 0
    }
    return jsonify(stats)

@app.route('/api/tags')
@login_required
def get_tags():
    """获取所有标签"""
    tags = Tag.query.all()
    return jsonify([{'id': tag.id, 'name': tag.name, 'color': tag.color} for tag in tags])

@app.route('/api/tags', methods=['POST'])
@login_required
def create_tag():
    """创建新标签"""
    data = request.get_json()
    name = data.get('name', '').strip()
    color = data.get('color', '#007bff')
    
    if not name:
        return jsonify({'success': False, 'message': '标签名不能为空'})
    
    existing_tag = Tag.query.filter_by(name=name).first()
    if existing_tag:
        return jsonify({'success': False, 'message': '标签已存在'})
    
    try:
        tag = Tag(name=name, color=color)
        db.session.add(tag)
        db.session.commit()
        return jsonify({
            'success': True, 
            'message': '标签创建成功',
            'tag': {'id': tag.id, 'name': tag.name, 'color': tag.color}
        })
    except Exception as e:
        db.session.rollback()
        return jsonify({'success': False, 'message': f'创建失败: {str(e)}'})

if __name__ == '__main__':
    with app.app_context():
        # 创建数据库表
        db.create_all()
        # 初始化默认用户
        init_default_user()
    
    app.run(debug=True)