from flask import Flask, jsonify, request
from flask_cors import CORS
import socket
import platform
import subprocess
import re
import os
from datetime import datetime

app = Flask(__name__)
CORS(app)  # Allow Laravel/website to call this API

# Add request logging
@app.before_request
def log_request_info():
    """Log incoming requests for debugging"""
    print(f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] {request.method} {request.path}")
    if request.args:
        print(f"  Query params: {request.args}")

def get_device_name():
    """Get the device/hostname name"""
    try:
        hostname = socket.gethostname()
        if hostname:
            return hostname
    except Exception as e:
        print(f"Error getting hostname: {e}")
    
    # Fallback methods
    try:
        if platform.system() == 'Windows':
            result = subprocess.run(['hostname'], capture_output=True, text=True, timeout=2)
            if result.returncode == 0:
                return result.stdout.strip()
        else:
            result = subprocess.run(['hostname'], capture_output=True, text=True, timeout=2)
            if result.returncode == 0:
                return result.stdout.strip()
    except Exception as e:
        print(f"Error getting hostname via command: {e}")
    
    return 'Unknown-Device'

def get_lan_ip_address():
    """Get the LAN IP address of the local machine"""
    try:
        # Method 1: Connect to a remote address to determine local IP
        # This doesn't actually send data, just determines the route
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        try:
            # Connect to a public DNS server (doesn't actually connect)
            s.connect(('8.8.8.8', 80))
            ip = s.getsockname()[0]
            s.close()
            
            # Check if it's a private IP
            if is_private_ip(ip):
                return ip
        except Exception:
            s.close()
    except Exception as e:
        print(f"Error with socket method: {e}")
    
    # Method 2: Try ipconfig/ifconfig
    try:
        if platform.system() == 'Windows':
            result = subprocess.run(['ipconfig'], capture_output=True, text=True, timeout=5)
            if result.returncode == 0:
                output = result.stdout
                # Look for IPv4 addresses in the output
                ip_pattern = r'IPv4 Address[.\s]*:\s*(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})'
                matches = re.findall(ip_pattern, output)
                for ip in matches:
                    if is_private_ip(ip) and ip != '127.0.0.1':
                        return ip
        else:
            # Linux/Mac
            result = subprocess.run(['ifconfig'], capture_output=True, text=True, timeout=5)
            if result.returncode == 0:
                output = result.stdout
                # Look for inet addresses (IPv4)
                ip_pattern = r'inet\s+(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})'
                matches = re.findall(ip_pattern, output)
                for ip in matches:
                    if is_private_ip(ip) and ip != '127.0.0.1':
                        return ip
    except Exception as e:
        print(f"Error with ipconfig/ifconfig: {e}")
    
    # Method 3: Try hostname -I (Linux)
    try:
        if platform.system() != 'Windows':
            result = subprocess.run(['hostname', '-I'], capture_output=True, text=True, timeout=2)
            if result.returncode == 0:
                ips = result.stdout.strip().split()
                for ip in ips:
                    if is_private_ip(ip) and ip != '127.0.0.1':
                        return ip
    except Exception:
        pass
    
    # Fallback: return localhost
    return '127.0.0.1'

def is_private_ip(ip):
    """Check if an IP address is in a private range"""
    try:
        parts = ip.split('.')
        if len(parts) != 4:
            return False
        
        first = int(parts[0])
        second = int(parts[1])
        
        # 10.0.0.0/8
        if first == 10:
            return True
        # 172.16.0.0/12
        if first == 172 and 16 <= second <= 31:
            return True
        # 192.168.0.0/16
        if first == 192 and second == 168:
            return True
        # 169.254.0.0/16 (link-local)
        if first == 169 and second == 254:
            return True
        
        return False
    except Exception:
        return False

@app.route('/api/server-info', methods=['GET'])
def get_server_info():
    """Get local server information (device name and LAN IP)"""
    try:
        device_name = get_device_name()
        lan_ip = get_lan_ip_address()
        
        return jsonify({
            'success': True,
            'device_name': device_name,
            'lan_ip': lan_ip,
            'platform': platform.system(),
            'platform_release': platform.release()
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'message': f'Error getting server info: {str(e)}'
        }), 500

@app.route('/api/device-name', methods=['GET'])
def get_device_name_endpoint():
    """Get device name only"""
    try:
        device_name = get_device_name()
        return jsonify({
            'success': True,
            'device_name': device_name
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'message': f'Error getting device name: {str(e)}'
        }), 500

@app.route('/api/lan-ip', methods=['GET'])
def get_lan_ip_endpoint():
    """Get LAN IP address only"""
    try:
        lan_ip = get_lan_ip_address()
        return jsonify({
            'success': True,
            'lan_ip': lan_ip
        })
    except Exception as e:
        return jsonify({
            'success': False,
            'message': f'Error getting LAN IP: {str(e)}'
        }), 500

@app.route('/', methods=['GET'])
def root():
    """Root endpoint - provides API information"""
    return jsonify({
        'service': 'local-server',
        'version': '1.0.0',
        'endpoints': {
            '/api/server-info': 'Get both device name and LAN IP address',
            '/api/device-name': 'Get device name only',
            '/api/lan-ip': 'Get LAN IP address only',
            '/health': 'Health check endpoint'
        },
        'device_name': get_device_name(),
        'lan_ip': get_lan_ip_address()
    })

@app.route('/health', methods=['GET'])
def health():
    """Health check endpoint"""
    return jsonify({
        'status': 'ok',
        'service': 'local-server',
        'device_name': get_device_name(),
        'lan_ip': get_lan_ip_address()
    })

@app.errorhandler(404)
def not_found(error):
    """Handle 404 errors with helpful message"""
    return jsonify({
        'success': False,
        'error': 'Not Found',
        'message': 'The requested URL was not found on the server.',
        'available_endpoints': [
            '/',
            '/api/server-info',
            '/api/device-name',
            '/api/lan-ip',
            '/health'
        ]
    }), 404

if __name__ == '__main__':
    port = int(os.environ.get('LOCAL_SERVER_PORT', 5001))
    host = os.environ.get('LOCAL_SERVER_HOST', '0.0.0.0')
    
    print(f"=" * 60)
    print(f"Starting Local Server on {host}:{port}")
    print(f"Device Name: {get_device_name()}")
    print(f"LAN IP: {get_lan_ip_address()}")
    print(f"=" * 60)
    print(f"API Endpoints:")
    print(f"  - GET http://localhost:{port}/ - API information")
    print(f"  - GET http://localhost:{port}/api/server-info - Get both device name and LAN IP")
    print(f"  - GET http://localhost:{port}/api/device-name - Get device name only")
    print(f"  - GET http://localhost:{port}/api/lan-ip - Get LAN IP only")
    print(f"  - GET http://localhost:{port}/health - Health check")
    print(f"=" * 60)
    print(f"Server is running. Press Ctrl+C to stop.")
    print(f"=" * 60)
    
    app.run(host=host, port=port, debug=True)

