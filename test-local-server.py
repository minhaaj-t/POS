"""
Simple test script to verify the local server is working
Run this after starting local-server.py
"""
import requests
import sys

SERVER_URL = 'http://localhost:5001'

def test_endpoint(endpoint, description):
    """Test an endpoint and print results"""
    try:
        url = f"{SERVER_URL}{endpoint}"
        print(f"\n{'='*60}")
        print(f"Testing: {description}")
        print(f"URL: {url}")
        print(f"{'='*60}")
        
        response = requests.get(url, timeout=5)
        print(f"Status Code: {response.status_code}")
        
        if response.status_code == 200:
            data = response.json()
            print(f"Response: {data}")
            print("✓ SUCCESS")
            return True
        else:
            print(f"Response: {response.text}")
            print("✗ FAILED")
            return False
            
    except requests.exceptions.ConnectionError:
        print(f"✗ ERROR: Could not connect to server at {SERVER_URL}")
        print("  Make sure local-server.py is running!")
        return False
    except Exception as e:
        print(f"✗ ERROR: {str(e)}")
        return False

def main():
    print("Local Server Test Script")
    print("="*60)
    print(f"Testing server at: {SERVER_URL}")
    print("="*60)
    
    # Test all endpoints
    results = []
    
    results.append(test_endpoint('/', 'Root endpoint'))
    results.append(test_endpoint('/health', 'Health check'))
    results.append(test_endpoint('/api/server-info', 'Server info (device name + LAN IP)'))
    results.append(test_endpoint('/api/device-name', 'Device name only'))
    results.append(test_endpoint('/api/lan-ip', 'LAN IP only'))
    
    # Summary
    print(f"\n{'='*60}")
    print("Test Summary")
    print(f"{'='*60}")
    passed = sum(results)
    total = len(results)
    print(f"Passed: {passed}/{total}")
    
    if passed == total:
        print("✓ All tests passed!")
        sys.exit(0)
    else:
        print("✗ Some tests failed. Check the server logs.")
        sys.exit(1)

if __name__ == '__main__':
    main()

