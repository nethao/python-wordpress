#!/usr/bin/env python3
"""
数据库初始化脚本
用于创建数据库表和初始化数据
"""

import os
import sys

# 设置环境变量，避免DeepSeek API初始化错误
os.environ.setdefault('DEEPSEEK_API_KEY', '')

from flask import Flask
from models import db, User, Tag
from config import Config

def create_app():
    """创建Flask应用"""
    app = Flask(__name__)
    app.config.from_object(Config)
    db.init_app(app)
    return app

def init_database():
    """初始化数据库"""
    app = create_app()
    
    with app.app_context():
        try:
            # 创建所有表
            db.create_all()
            print("✓ 数据库表创建完成")
            
            # 创建默认管理员用户
            admin_user = User.query.filter_by(username='admin').first()
            if not admin_user:
                admin_user = User(
                    username='admin',
                    email='admin@example.com'
                )
                admin_user.set_password('admin123')
                db.session.add(admin_user)
                print("✓ 默认管理员用户已创建: admin/admin123")
            else:
                print("✓ 管理员用户已存在")
            
            # 创建默认标签
            default_tags = [
                {'name': '技术', 'color': '#007bff'},
                {'name': '生活', 'color': '#28a745'},
                {'name': '随笔', 'color': '#ffc107'},
                {'name': '教程', 'color': '#dc3545'},
                {'name': '思考', 'color': '#6f42c1'},
            ]
            
            for tag_data in default_tags:
                existing_tag = Tag.query.filter_by(name=tag_data['name']).first()
                if not existing_tag:
                    tag = Tag(name=tag_data['name'], color=tag_data['color'])
                    db.session.add(tag)
                    print(f"✓ 创建标签: {tag_data['name']}")
            
            # 提交所有更改
            db.session.commit()
            print("\n🎉 数据库初始化完成！")
            print("\n📝 登录信息:")
            print("   用户名: admin")
            print("   密码: admin123")
            print("\n🚀 现在可以运行: python app.py")
            
        except Exception as e:
            print(f"❌ 数据库初始化失败: {e}")
            db.session.rollback()
            sys.exit(1)

if __name__ == '__main__':
    init_database()