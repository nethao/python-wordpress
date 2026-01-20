#!/usr/bin/env python3
"""
安全配置脚本
帮助用户配置外网访问的安全设置
"""

import os
import sys
import subprocess
import platform

def check_firewall_status():
    """检查防火墙状态"""
    system = platform.system().lower()
    
    if system == 'linux':
        try:
            # 检查ufw状态
            result = subprocess.run(['ufw', 'status'], capture_output=True, text=True)
            if result.returncode == 0:
                print("🔥 UFW防火墙状态:")
                print(result.stdout)
                return True
        except FileNotFoundError:
            try:
                # 检查iptables
                result = subprocess.run(['iptables', '-L'], capture_output=True, text=True)
                if result.returncode == 0:
                    print("🔥 iptables防火墙已安装")
                    return True
            except FileNotFoundError:
                print("⚠️  未检测到防火墙")
                return False
    
    elif system == 'windows':
        try:
            result = subprocess.run(['netsh', 'advfirewall', 'show', 'allprofiles'], 
                                  capture_output=True, text=True)
            if result.returncode == 0:
                print("🔥 Windows防火墙状态:")
                print("防火墙已启用" if "ON" in result.stdout else "防火墙已禁用")
                return True
        except Exception:
            print("⚠️  无法检查Windows防火墙状态")
            return False
    
    return False

def setup_firewall_rules(port=5000):
    """设置防火墙规则"""
    system = platform.system().lower()
    
    print(f"\n🔧 配置防火墙规则 (端口 {port})...")
    
    if system == 'linux':
        try:
            # 尝试使用ufw
            commands = [
                f"ufw allow {port}/tcp",
                "ufw --force enable"
            ]
            
            for cmd in commands:
                print(f"执行: {cmd}")
                result = subprocess.run(cmd.split(), capture_output=True, text=True)
                if result.returncode == 0:
                    print("✓ 成功")
                else:
                    print(f"✗ 失败: {result.stderr}")
                    
        except FileNotFoundError:
            print("⚠️  ufw未安装，请手动配置防火墙")
            print(f"   允许端口 {port}/tcp 的入站连接")
    
    elif system == 'windows':
        try:
            cmd = f'netsh advfirewall firewall add rule name="Flask App Port {port}" dir=in action=allow protocol=TCP localport={port}'
            print(f"执行: {cmd}")
            result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
            if result.returncode == 0:
                print("✓ Windows防火墙规则添加成功")
            else:
                print(f"✗ 失败: {result.stderr}")
        except Exception as e:
            print(f"⚠️  无法自动配置Windows防火墙: {e}")
            print(f"   请手动在Windows防火墙中允许端口 {port}")

def get_network_info():
    """获取网络信息"""
    print("\n🌐 网络信息:")
    
    try:
        import socket
        hostname = socket.gethostname()
        local_ip = socket.gethostbyname(hostname)
        print(f"   主机名: {hostname}")
        print(f"   本地IP: {local_ip}")
        
        # 尝试获取公网IP
        try:
            import requests
            public_ip = requests.get('https://api.ipify.org', timeout=5).text
            print(f"   公网IP: {public_ip}")
        except:
            print("   公网IP: 无法获取")
            
    except Exception as e:
        print(f"   获取网络信息失败: {e}")

def show_security_tips():
    """显示安全建议"""
    print("\n🔒 安全建议:")
    print("1. 修改默认管理员密码 (admin/admin123)")
    print("2. 使用HTTPS (考虑使用nginx反向代理)")
    print("3. 限制访问IP范围")
    print("4. 定期备份数据库文件")
    print("5. 监控访问日志")
    print("6. 考虑使用VPN或内网访问")
    print("7. 定期更新系统和依赖包")

def main():
    """主函数"""
    print("🛡️  外网访问安全配置向导")
    print("=" * 50)
    
    # 检查当前配置
    port = int(os.getenv('FLASK_PORT', 5000))
    host = os.getenv('FLASK_HOST', '0.0.0.0')
    
    print(f"当前配置:")
    print(f"   监听地址: {host}")
    print(f"   监听端口: {port}")
    
    if host == '0.0.0.0':
        print("✓ 外网访问已启用")
    else:
        print("⚠️  当前仅允许本地访问")
        
    # 检查防火墙
    print("\n" + "=" * 30)
    check_firewall_status()
    
    # 获取网络信息
    get_network_info()
    
    # 询问是否配置防火墙
    print("\n" + "=" * 30)
    if input("是否配置防火墙规则? (y/N): ").lower().startswith('y'):
        setup_firewall_rules(port)
    
    # 显示安全建议
    show_security_tips()
    
    print("\n" + "=" * 50)
    print("🚀 配置完成！")
    print(f"应用将在以下地址可访问:")
    print(f"   http://localhost:{port}")
    if host == '0.0.0.0':
        print(f"   http://your-server-ip:{port}")

if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        print("\n👋 配置已取消")
    except Exception as e:
        print(f"\n❌ 配置失败: {e}")
        sys.exit(1)