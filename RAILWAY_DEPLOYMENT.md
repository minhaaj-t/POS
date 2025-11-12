# Deploy Laravel to Railway.app

Railway is a better alternative for Laravel applications.

## Quick Start

### 1. Install Railway CLI
```bash
npm i -g @railway/cli
```

### 2. Login
```bash
railway login
```

### 3. Initialize Project
```bash
railway init
```

### 4. Create `railway.json` (optional)
```json
{
  "$schema": "https://railway.app/railway.schema.json",
  "build": {
    "builder": "NIXPACKS"
  },
  "deploy": {
    "startCommand": "php artisan serve --host=0.0.0.0 --port=$PORT",
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 10
  }
}
```

### 5. Set Environment Variables in Railway Dashboard:
```
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY_HERE
APP_DEBUG=false
APP_URL=https://your-app.railway.app

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=your-database
DB_USERNAME=your-username
DB_PASSWORD=your-password

SESSION_DRIVER=database
CACHE_DRIVER=database
QUEUE_CONNECTION=database

PYTHON_SERVER_URL=http://your-python-server-url:5000
```

### 6. Deploy
```bash
railway up
```

## Deploy Python Server Separately

Create a new service in Railway for your Python server:

1. **Add Service** → **Empty Service**
2. **Settings** → **Generate** → **Nixpacks**
3. **Add Environment Variables**:
   ```
   PORT=5000
   ```
4. **Deploy** `server.py` and `requirements.txt`

Railway will automatically detect Python and install dependencies.

## Benefits:
- ✅ Full PHP/Laravel support
- ✅ Persistent storage
- ✅ Background jobs
- ✅ Python support
- ✅ Database support
- ✅ Free tier available

