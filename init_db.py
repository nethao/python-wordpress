#!/usr/bin/env python3
"""
数据库初始化脚本
用于创建数据库表和初始化数据
"""

from app import app
from models import db, User, Tag
from werkzeug.security import generate_password_hash

def init_database():
    """初始化数据库"""
    with app.app_context():
        # 删除所有表（谨慎使用）
        # db.drop_all()
        
        # 创建所有表
        db.create_all()
        print("数据库表创建完成")
        
        # 创建默认管理员用户
        admin_user = User.query.filter_by(username='admin').first()
        if not admin_user:
            admin_user = User(
                username='admin',
                email='admin@example.com'
            )
            admin_user.set_password('admin123')
            db.session.add(admin_user)
            print("默认管理员用户已创建: admin/admin123")
        else:
            print("管理员用户已存在")
        
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
                print(f"创建标签: {tag_data['name']}")
        
        # 提交所有更改
        db.session.commit()
        print("数据库初始化完成！")

if __name__ == '__main__':
    init_database()