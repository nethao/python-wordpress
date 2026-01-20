#!/usr/bin/env python3
"""
快速启动脚本
自动检查并初始化数据库，然后启动应用
"""

import os
import sys
from pathlib import Path

def check_database():
    """检查数据库是否存在"""
    db_file = Path('articles.db')
    return db_file.exists()

def init_database():
    """初始化数据库"""
    print("🔧 正在初始化数据库...")
    os.system('python init_db.py')

def start_app():
    """启动应用"""
    print("🚀 启动应用...")
    os.system('python app.py')

def main():
    print("📝 文章管理系统启动器")
    print("=" * 40)
    
    # 检查数据库
    if not check_database():
        print("📊 未找到数据库文件，正在初始化...")
        init_database()
    else:
        print("✓ 数据库文件已存在")
    
    print("\n" + "=" * 40)
    
    # 检查DeepSeek API密钥
    api_key = os.getenv('DEEPSEEK_API_KEY', '')
    if not api_key or api_key == 'your-deepseek-api-key':
        print("⚠️  未配置DeepSeek API密钥")
        print("   系统将使用基础审核模式")
        print("   如需AI审核功能，请设置环境变量:")
        print("   export DEEPSEEK_API_KEY=your-api-key")
    else:
        print("✓ DeepSeek API密钥已配置")
    
    print("\n" + "=" * 40)
    print("🌐 应用启动地址:")
    print("   本地访问: http://localhost:5000")
    print("   外网访问: http://0.0.0.0:5000")
    print("📋 默认登录信息: admin / admin123")
    print("⚠️  外网访问已开启，请注意网络安全")
    print("=" * 40)
    
    # 启动应用
    start_app()

if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        print("\n👋 应用已停止")
    except Exception as e:
        print(f"\n❌ 启动失败: {e}")
        sys.exit(1)