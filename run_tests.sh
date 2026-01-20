#!/bin/bash

# 系统测试脚本
echo "🧪 开始系统测试..."

# 检查Python环境
echo "🐍 检查Python环境..."
python3 --version
if [ $? -ne 0 ]; then
    echo "❌ Python3 未安装"
    exit 1
fi

# 检查虚拟环境
if [ -d "venv" ]; then
    echo "✓ 发现虚拟环境"
    source venv/bin/activate
else
    echo "⚠️  未发现虚拟环境，建议创建:"
    echo "   python3 -m venv venv"
    echo "   source venv/bin/activate"
fi

# 检查依赖
echo "📦 检查依赖包..."
pip install -r requirements.txt
if [ $? -ne 0 ]; then
    echo "❌ 依赖安装失败"
    exit 1
fi

# 运行系统测试
echo "🔍 运行系统测试..."
python3 test_system.py

# 检测测试结果
if [ $? -eq 0 ]; then
    echo ""
    echo "🎉 系统测试通过！"
    echo ""
    echo "📋 下一步操作:"
    echo "1. 初始化数据库: python3 init_db.py"
    echo "2. 启动应用: python3 app.py"
    echo "3. 或使用快速启动: python3 start.py"
    echo ""
    echo "🌐 访问地址: http://localhost:5000"
    echo "👤 默认账号: admin / admin123"
else
    echo "❌ 系统测试失败，请检查错误信息"
    exit 1
fi