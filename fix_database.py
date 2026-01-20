#!/usr/bin/env python3
"""
数据库修复脚本
修复外键约束和级联删除问题
"""

import os
import sqlite3
from app import app
from models import db

def backup_database():
    """备份数据库"""
    if os.path.exists('articles.db'):
        import shutil
        backup_name = f'articles_backup_{int(time.time())}.db'
        shutil.copy2('articles.db', backup_name)
        print(f"✓ 数据库已备份为: {backup_name}")
        return backup_name
    return None

def fix_foreign_keys():
    """修复外键约束"""
    print("🔧 修复数据库外键约束...")
    
    # 连接到数据库
    conn = sqlite3.connect('articles.db')
    cursor = conn.cursor()
    
    try:
        # 启用外键约束
        cursor.execute("PRAGMA foreign_keys = ON")
        
        # 检查现有的表结构
        cursor.execute("SELECT sql FROM sqlite_master WHERE type='table' AND name='audit_logs'")
        audit_logs_sql = cursor.fetchone()
        
        cursor.execute("SELECT sql FROM sqlite_master WHERE type='table' AND name='publish_logs'")
        publish_logs_sql = cursor.fetchone()
        
        print("当前表结构:")
        if audit_logs_sql:
            print(f"audit_logs: {audit_logs_sql[0]}")
        if publish_logs_sql:
            print(f"publish_logs: {publish_logs_sql[0]}")
        
        # 检查是否需要重建表
        need_rebuild = False
        if audit_logs_sql and 'ON DELETE CASCADE' not in audit_logs_sql[0]:
            need_rebuild = True
            print("⚠️  audit_logs表需要重建以支持级联删除")
        
        if publish_logs_sql and 'ON DELETE CASCADE' not in publish_logs_sql[0]:
            need_rebuild = True
            print("⚠️  publish_logs表需要重建以支持级联删除")
        
        if need_rebuild:
            print("🔄 重建表结构...")
            
            # 备份数据
            cursor.execute("SELECT * FROM audit_logs")
            audit_data = cursor.fetchall()
            
            cursor.execute("SELECT * FROM publish_logs")
            publish_data = cursor.fetchall()
            
            # 删除旧表
            cursor.execute("DROP TABLE IF EXISTS audit_logs")
            cursor.execute("DROP TABLE IF EXISTS publish_logs")
            
            # 创建新表（带级联删除）
            cursor.execute("""
                CREATE TABLE audit_logs (
                    id INTEGER PRIMARY KEY,
                    article_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    passed BOOLEAN NOT NULL,
                    score FLOAT NOT NULL,
                    risk_level VARCHAR(20) NOT NULL,
                    reasons TEXT,
                    suggestions TEXT,
                    flagged_keywords TEXT,
                    strict_level INTEGER DEFAULT 2,
                    audit_type VARCHAR(20) DEFAULT 'manual',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            """)
            
            cursor.execute("""
                CREATE TABLE publish_logs (
                    id INTEGER PRIMARY KEY,
                    article_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    wp_post_id INTEGER,
                    wp_url VARCHAR(500),
                    wp_category_id INTEGER,
                    status VARCHAR(20) NOT NULL,
                    error_message TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            """)
            
            # 恢复数据
            if audit_data:
                cursor.executemany("""
                    INSERT INTO audit_logs 
                    (id, article_id, user_id, passed, score, risk_level, reasons, suggestions, 
                     flagged_keywords, strict_level, audit_type, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """, audit_data)
                print(f"✓ 恢复了 {len(audit_data)} 条审核日志")
            
            if publish_data:
                cursor.executemany("""
                    INSERT INTO publish_logs 
                    (id, article_id, user_id, wp_post_id, wp_url, wp_category_id, 
                     status, error_message, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                """, publish_data)
                print(f"✓ 恢复了 {len(publish_data)} 条发布日志")
        
        conn.commit()
        print("✅ 数据库修复完成")
        
    except Exception as e:
        print(f"❌ 修复失败: {e}")
        conn.rollback()
        raise
    finally:
        conn.close()

def clean_orphaned_records():
    """清理孤立记录"""
    print("🧹 清理孤立记录...")
    
    with app.app_context():
        # 清理没有对应文章的审核日志
        orphaned_audits = db.session.execute("""
            DELETE FROM audit_logs 
            WHERE article_id NOT IN (SELECT id FROM articles)
        """)
        
        # 清理没有对应文章的发布日志
        orphaned_publishes = db.session.execute("""
            DELETE FROM publish_logs 
            WHERE article_id NOT IN (SELECT id FROM articles)
        """)
        
        db.session.commit()
        print(f"✓ 清理了孤立的审核日志和发布日志")

def main():
    """主函数"""
    print("🔧 数据库修复工具")
    print("=" * 40)
    
    if not os.path.exists('articles.db'):
        print("❌ 未找到数据库文件 articles.db")
        return
    
    # 备份数据库
    import time
    backup_file = backup_database()
    
    try:
        # 修复外键约束
        fix_foreign_keys()
        
        # 清理孤立记录
        clean_orphaned_records()
        
        print("\n🎉 数据库修复完成！")
        print("现在可以正常删除文章了")
        
    except Exception as e:
        print(f"\n❌ 修复失败: {e}")
        if backup_file:
            print(f"可以从备份文件恢复: {backup_file}")

if __name__ == '__main__':
    main()