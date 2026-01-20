#!/usr/bin/env python3
"""
数据库迁移脚本
用于从内存存储迁移到数据库存储
"""

import json
from datetime import datetime
from app import app
from models import db, User, Article, Tag

def migrate_from_memory():
    """从内存数据迁移到数据库"""
    with app.app_context():
        # 这里可以添加从旧数据文件导入的逻辑
        # 例如从JSON文件导入文章数据
        
        # 示例：从articles.json导入数据
        try:
            with open('articles_backup.json', 'r', encoding='utf-8') as f:
                articles_data = json.load(f)
                
            admin_user = User.query.filter_by(username='admin').first()
            if not admin_user:
                print("请先创建管理员用户")
                return
            
            for article_data in articles_data:
                # 检查文章是否已存在
                existing_article = Article.query.filter_by(
                    title=article_data['title'],
                    user_id=admin_user.id
                ).first()
                
                if not existing_article:
                    article = Article(
                        title=article_data['title'],
                        content=article_data['content'],
                        user_id=admin_user.id,
                        status='published' if article_data.get('published', False) else 'draft',
                        is_published=article_data.get('published', False),
                        created_at=datetime.strptime(article_data['created_at'], '%Y-%m-%d %H:%M:%S'),
                        updated_at=datetime.strptime(article_data['updated_at'], '%Y-%m-%d %H:%M:%S'),
                        audit_score=article_data.get('audit_score', 0.0),
                        risk_level=article_data.get('risk_level', 'low'),
                        wp_post_id=article_data.get('wp_post_id')
                    )
                    
                    # 更新字数和摘要
                    article.update_word_count()
                    article.generate_summary()
                    
                    db.session.add(article)
                    print(f"导入文章: {article.title}")
            
            db.session.commit()
            print("数据迁移完成！")
            
        except FileNotFoundError:
            print("未找到备份文件 articles_backup.json")
        except Exception as e:
            print(f"迁移失败: {e}")
            db.session.rollback()

def backup_to_json():
    """备份数据库数据到JSON文件"""
    with app.app_context():
        articles = Article.query.all()
        articles_data = []
        
        for article in articles:
            articles_data.append({
                'id': article.id,
                'title': article.title,
                'content': article.content,
                'summary': article.summary,
                'created_at': article.created_at.strftime('%Y-%m-%d %H:%M:%S'),
                'updated_at': article.updated_at.strftime('%Y-%m-%d %H:%M:%S'),
                'published_at': article.published_at.strftime('%Y-%m-%d %H:%M:%S') if article.published_at else None,
                'status': article.status,
                'is_published': article.is_published,
                'audit_score': article.audit_score,
                'risk_level': article.risk_level,
                'wp_post_id': article.wp_post_id,
                'wp_url': article.wp_url,
                'word_count': article.word_count,
                'author': article.author.username,
                'tags': [tag.name for tag in article.tags]
            })
        
        with open(f'articles_backup_{datetime.now().strftime("%Y%m%d_%H%M%S")}.json', 'w', encoding='utf-8') as f:
            json.dump(articles_data, f, ensure_ascii=False, indent=2)
        
        print(f"已备份 {len(articles_data)} 篇文章到JSON文件")

if __name__ == '__main__':
    import sys
    
    if len(sys.argv) > 1:
        if sys.argv[1] == 'backup':
            backup_to_json()
        elif sys.argv[1] == 'migrate':
            migrate_from_memory()
        else:
            print("用法: python migrate_db.py [backup|migrate]")
    else:
        print("用法: python migrate_db.py [backup|migrate]")
        print("  backup  - 备份当前数据库到JSON文件")
        print("  migrate - 从JSON文件迁移数据到数据库")