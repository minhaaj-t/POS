# Quick Fix for HTTP 500 Error on Vercel

## Immediate Steps to Fix

### 1. Enable Debug Mode (Temporarily)

In Vercel Dashboard → Settings → Environment Variables, add:

```
APP_DEBUG=true
APP_ENV=local
```

This will show you the actual error message instead of a generic 500 error.

### 2. Generate and Add APP_KEY

**Run locally:**
```bash
php artisan key:generate --show
```

**Copy the output** (starts with `base64:`) and add to Vercel:
```
APP_KEY=base64:your-generated-key-here
```

### 3. Add Minimum Required Variables

Add these in Vercel Dashboard:

```
APP_NAME=Laravel
APP_ENV=production
APP_KEY=base64:YOUR_KEY_HERE
APP_DEBUG=false
APP_URL=https://pos-9t3b.vercel.app
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

### 4. Set Up Database

SQLite won't work on Vercel. You need a cloud database:

**Option 1: Vercel Postgres (Easiest)**
1. Go to Vercel Dashboard → Storage → Create Database
2. Choose Postgres
3. Copy connection string and add as environment variables

**Option 2: Quick Test Database**
Use a free service like:
- [Supabase](https://supabase.com) (Free tier)
- [PlanetScale](https://planetscale.com) (Free tier)

### 5. Run Migrations

After setting up database, run migrations:
```bash
php artisan migrate
```

Or if you have database access, run migrations locally pointing to your cloud database.

### 6. Redeploy

After adding environment variables:
1. Go to Vercel Dashboard → Deployments
2. Click "Redeploy" on the latest deployment
3. Or push a new commit to trigger redeploy

## Check Error Logs

1. Go to Vercel Dashboard → Your Project → Functions
2. Click on `api/index.php`
3. Check the "Logs" tab to see detailed error messages

## Common Issues

### Missing APP_KEY
**Error:** "No application encryption key has been specified"
**Fix:** Generate and add APP_KEY (see step 2 above)

### Database Connection Failed
**Error:** "SQLSTATE[HY000] [2002] Connection refused"
**Fix:** Set up cloud database and add DB_* environment variables

### Storage Permission Denied
**Error:** "The stream or file could not be opened"
**Fix:** Set CACHE_STORE=database and SESSION_DRIVER=database

## After Fixing

Once it works, set:
```
APP_DEBUG=false
APP_ENV=production
```

This will hide error details from users.

