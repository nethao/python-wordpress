#!/usr/bin/env python3
"""
系统测试脚本
测试各个组件是否正常工作
"""

import os
import sys
import tempfile
import shutil
from pathlib import Path

def test_imports():
    """测试模块导入"""
    print("🔍 测试模块导入...")
    
    try:
        # 设置临时环境变量避免API初始化错误
        os.environ.setdefault('DEEPSEEK_API_KEY', '')
        
        from models import db, User, Article, Tag
        print("✓ 数据库模型导入成功")
        
        from deepseek_audit import DeepSeekAudit, init_audit_service
        print("✓ DeepSeek审核模块导入成功")
        
        from config import Config, DEEPSEEK_CONFIG, WORDPRESS_CONFIG
        print("✓ 配置模块导入成功")
        
        return True
    except Exception as e:
        print(f"❌ 模块导入失败: {e}")
        return False

def test_database():
    """测试数据库功能"""
    print("\n🗄️ 测试数据库功能...")
    
    try:
        # 创建临时数据库
        temp_dir = tempfile.mkdtemp()
        temp_db = os.path.join(temp_dir, 'test.db')
        
        from flask import Flask
        from models import db, User, Tag, Article
        from config import Config
        
        # 创建测试应用
        app = Flask(__name__)
        app.config['SQLALCHEMY_DATABASE_URI'] = f'sqlite:///{temp_db}'
        app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False
        app.config['SECRET_KEY'] = 'test-key'
        
        db.init_app(app)
        
        with app.app_context():
            # 创建表
            db.create_all()
            print("✓ 数据库表创建成功")
            
            # 测试用户创建
            user = User(username='testuser', email='test@example.com')
            user.set_password('testpass')
            db.session.add(user)
            db.session.commit()
            print("✓ 用户创建成功")
            
            # 测试文章创建
            article = Article(
                title='测试文章',
                content='这是一篇测试文章的内容',
                user_id=user.id
            )
            article.update_word_count()
            article.generate_summary()
            db.session.add(article)
            db.session.commit()
            print("✓ 文章创建成功")
            
            # 测试标签创建
            tag = Tag(name='测试标签', color='#007bff')
            db.session.add(tag)
            article.tags.append(tag)
            db.session.commit()
            print("✓ 标签创建和关联成功")
            
            # 验证数据
            assert User.query.count() == 1
            assert Article.query.count() == 1
            assert Tag.query.count() == 1
            print("✓ 数据验证成功")
        
        # 清理临时文件
        shutil.rmtree(temp_dir)
        return True
        
    except Exception as e:
        print(f"❌ 数据库测试失败: {e}")
        return False

def test_audit_service():
    """测试审核服务"""
    print("\n🛡️ 测试审核服务...")
    
    try:
        from deepseek_audit import DeepSeekAudit
        
        # 测试基础审核（不需要API密钥）
        audit = DeepSeekAudit('')
        
        # 测试正常内容
        result = audit.audit_content("这是一篇正常的文章内容", 2)
        assert 'passed' in result
        assert 'score' in result
        assert 'risk_level' in result
        print("✓ 正常内容审核成功")
        
        # 测试可疑内容
        result = audit.audit_content("这篇文章包含敏感词内容", 2)
        assert 'passed' in result
        print("✓ 可疑内容审核成功")
        
        return True
        
    except Exception as e:
        print(f"❌ 审核服务测试失败: {e}")
        return False

def test_flask_app():
    """测试Flask应用"""
    print("\n🌐 测试Flask应用...")
    
    try:
        # 设置测试环境
        os.environ['DEEPSEEK_API_KEY'] = ''
        
        from flask import Flask
        from models import db, User
        from config import Config
        
        # 创建测试应用
        app = Flask(__name__)
        app.config.from_object(Config)
        app.config['TESTING'] = True
        app.config['SQLALCHEMY_DATABASE_URI'] = 'sqlite:///:memory:'
        
        db.init_app(app)
        
        with app.app_context():
            db.create_all()
            
            # 创建测试用户
            user = User(username='admin')
            user.set_password('admin123')
            db.session.add(user)
            db.session.commit()
            
            print("✓ Flask应用初始化成功")
        
        # 测试应用创建
        with app.test_client() as client:
            # 测试登录页面
            response = client.get('/login')
            assert response.status_code == 200
            print("✓ 登录页面访问成功")
            
            # 测试登录功能
            response = client.post('/login', data={
                'username': 'admin',
                'password': 'admin123'
            }, follow_redirects=True)
            assert response.status_code == 200
            print("✓ 用户登录成功")
        
        return True
        
    except Exception as e:
        print(f"❌ Flask应用测试失败: {e}")
        return False

def main():
    """主测试函数"""
    print("🧪 系统测试开始")
    print("=" * 50)
    
    tests = [
        ("模块导入", test_imports),
        ("数据库功能", test_database),
        ("审核服务", test_audit_service),
        ("Flask应用", test_flask_app),
    ]
    
    passed = 0
    total = len(tests)
    
    for test_name, test_func in tests:
        try:
            if test_func():
                passed += 1
                print(f"✅ {test_name} 测试通过")
            else:
                print(f"❌ {test_name} 测试失败")
        except Exception as e:
            print(f"❌ {test_name} 测试异常: {e}")
        
        print("-" * 30)
    
    print(f"\n📊 测试结果: {passed}/{total} 通过")
    
    if passed == total:
        print("🎉 所有测试通过！系统可以正常运行")
        print("\n🚀 运行命令:")
        print("   python init_db.py    # 初始化数据库")
        print("   python app.py        # 启动应用")
        print("   python start.py      # 快速启动")
        return True
    else:
        print("⚠️  部分测试失败，请检查错误信息")
        return False

if __name__ == '__main__':
    success = main()
    sys.exit(0 if success else 1)