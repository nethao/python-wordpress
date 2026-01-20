@echo off
chcp 65001 >nul

echo 🧪 开始系统测试...

REM 检查Python环境
echo 🐍 检查Python环境...
python --version >nul 2>&1
if errorlevel 1 (
    echo ❌ Python 未安装或未添加到PATH
    pause
    exit /b 1
)

REM 检查虚拟环境
if exist "venv" (
    echo ✓ 发现虚拟环境
    call venv\Scripts\activate.bat
) else (
    echo ⚠️  未发现虚拟环境，建议创建:
    echo    python -m venv venv
    echo    venv\Scripts\activate.bat
)

REM 检查依赖
echo 📦 检查依赖包...
pip install -r requirements.txt
if errorlevel 1 (
    echo ❌ 依赖安装失败
    pause
    exit /b 1
)

REM 运行系统测试
echo 🔍 运行系统测试...
python test_system.py

REM 检测测试结果
if errorlevel 1 (
    echo ❌ 系统测试失败，请检查错误信息
    pause
    exit /b 1
) else (
    echo.
    echo 🎉 系统测试通过！
    echo.
    echo 📋 下一步操作:
    echo 1. 初始化数据库: python init_db.py
    echo 2. 启动应用: python app.py
    echo 3. 或使用快速启动: python start.py
    echo.
    echo 🌐 访问地址: http://localhost:5000
    echo 👤 默认账号: admin / admin123
    echo.
    pause
)