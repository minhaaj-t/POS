# Local Server Troubleshooting Guide

## 404 Error - "Not Found"

If you're getting a 404 error from the local server, follow these steps:

### Step 1: Verify the Server is Running

1. **Check if the server is running:**
   ```bash
   python local-server.py
   ```

2. **You should see output like:**
   ```
   ============================================================
   Starting Local Server on 0.0.0.0:5001
   Device Name: DESKTOP-3DVN5FM
   LAN IP: 192.168.61.55
   ============================================================
   API Endpoints:
     - GET http://localhost:5001/ - API information
     - GET http://localhost:5001/api/server-info - Get both device name and LAN IP
     - GET http://localhost:5001/api/device-name - Get device name only
     - GET http://localhost:5001/api/lan-ip - Get LAN IP only
     - GET http://localhost:5001/health - Health check
   ============================================================
   Server is running. Press Ctrl+C to stop.
   ============================================================
   ```

### Step 2: Test the Server

1. **Open a browser and test the root endpoint:**
   ```
   http://localhost:5001/
   ```

2. **Or test the health endpoint:**
   ```
   http://localhost:5001/health
   ```

3. **Or use the test script:**
   ```bash
   python test-local-server.py
   ```

### Step 3: Check the URL Being Accessed

Make sure you're using the correct endpoints:

- ✅ `http://localhost:5001/api/server-info`
- ✅ `http://localhost:5001/api/device-name`
- ✅ `http://localhost:5001/api/lan-ip`
- ✅ `http://localhost:5001/health`
- ✅ `http://localhost:5001/`

- ❌ `http://localhost:5001/api` (missing `/server-info`)
- ❌ `http://localhost:5001/server-info` (missing `/api/`)

### Step 4: Check Port Conflicts

If port 5001 is already in use:

1. **Check what's using the port:**
   ```bash
   netstat -ano | findstr :5001
   ```

2. **Use a different port:**
   ```bash
   set LOCAL_SERVER_PORT=8080
   python local-server.py
   ```

3. **Update your `.env` file:**
   ```env
   LOCAL_SERVER_URL=http://localhost:8080
   ```

### Step 5: Check Firewall/Antivirus

- Windows Firewall or antivirus might be blocking the connection
- Try temporarily disabling firewall to test
- Add Python to firewall exceptions if needed

### Step 6: Check Browser Console

If accessing from a web browser:

1. Open Developer Tools (F12)
2. Check the Console tab for errors
3. Check the Network tab to see the actual request URL

### Step 7: Check Server Logs

The server now logs all requests. When you make a request, you should see:
```
[2024-01-01 12:00:00] GET /api/server-info
```

If you don't see this, the request isn't reaching the server.

### Common Issues

#### Issue: "Connection refused"
**Solution:** Server is not running. Start it with `python local-server.py`

#### Issue: "404 on /api/server-info"
**Solution:** Make sure you're using the full path: `/api/server-info` (not just `/server-info`)

#### Issue: "CORS error"
**Solution:** The server has CORS enabled. If you still get CORS errors, check that `flask-cors` is installed:
```bash
pip install flask-cors
```

#### Issue: "Module not found: flask"
**Solution:** Install dependencies:
```bash
pip install -r requirements.txt
```

### Quick Test Commands

**Test with curl (if available):**
```bash
curl http://localhost:5001/health
curl http://localhost:5001/api/server-info
```

**Test with PowerShell:**
```powershell
Invoke-WebRequest -Uri http://localhost:5001/health
Invoke-WebRequest -Uri http://localhost:5001/api/server-info
```

### Still Having Issues?

1. Check that Python 3.6+ is installed: `python --version`
2. Check that Flask is installed: `pip list | findstr Flask`
3. Check server output for error messages
4. Try restarting the server
5. Check if another application is using port 5001

