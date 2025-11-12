# Deploy Laravel to Fly.io

Fly.io is another excellent option for Laravel.

## Quick Start

### 1. Install Fly CLI
```bash
# Windows (PowerShell)
iwr https://fly.io/install.ps1 -useb | iex

# Mac/Linux
curl -L https://fly.io/install.sh | sh
```

### 2. Login
```bash
fly auth login
```

### 3. Launch App
```bash
fly launch
```

This will create a `fly.toml` configuration file.

### 4. Update `fly.toml`:
```toml
app = "your-app-name"
primary_region = "iad"

[build]

[env]
  APP_ENV = "production"
  APP_DEBUG = "false"

[http_service]
  internal_port = 8000
  force_https = true
  auto_stop_machines = true
  auto_start_machines = true
  min_machines_running = 0
  processes = ["app"]

[[services]]
  protocol = "tcp"
  internal_port = 8000
  processes = ["app"]

  [[services.ports]]
    port = 80
    handlers = ["http"]
    force_https = true

  [[services.ports]]
    port = 443
    handlers = ["tls", "http"]
```

### 5. Set Secrets
```bash
fly secrets set APP_KEY=your-app-key
fly secrets set DB_HOST=your-db-host
fly secrets set DB_DATABASE=your-database
fly secrets set DB_USERNAME=your-username
fly secrets set DB_PASSWORD=your-password
fly secrets set PYTHON_SERVER_URL=http://your-python-server:5000
```

### 6. Deploy
```bash
fly deploy
```

## Deploy Python Server

Create separate Fly app for Python server:

```bash
cd /path/to/server
fly launch --name your-python-server
fly deploy
```

## Benefits:
- ✅ Full Laravel support
- ✅ Global edge network
- ✅ Persistent volumes
- ✅ Background workers
- ✅ Python support

