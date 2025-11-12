# Vercel Deployment Troubleshooting

## HTTP 500 Error - Common Causes and Solutions

### 1. Missing APP_KEY Environment Variable

**Problem:** Laravel requires an application key for encryption.

**Solution:**
1. Generate an APP_KEY locally:
   ```bash
   php artisan key:generate --show
   ```
2. Copy the generated key
3. In Vercel Dashboard → Your Project → Settings → Environment Variables
4. Add: `APP_KEY` = `base64:your-generated-key-here`

### 2. Required Environment Variables

Add these **required** environment variables in Vercel:

```
APP_NAME=Laravel
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_KEY_HERE
APP_DEBUG=false
APP_URL=https://your-app.vercel.app
```

### 3. Database Configuration

**Problem:** SQLite won't work on Vercel (read-only filesystem).

**Solution:** Use a cloud database service:

**Option A: Vercel Postgres**
```
DB_CONNECTION=pgsql
DB_HOST=your-postgres-host
DB_PORT=5432
DB_DATABASE=your-database
DB_USERNAME=your-username
DB_PASSWORD=your-password
```

**Option B: PlanetScale (MySQL)**
```
DB_CONNECTION=mysql
DB_HOST=your-planetscale-host
DB_PORT=3306
DB_DATABASE=your-database
DB_USERNAME=your-username
DB_PASSWORD=your-password
```

**Option C: Supabase (PostgreSQL)**
```
DB_CONNECTION=pgsql
DB_HOST=your-supabase-host
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your-password
```

### 4. Cache and Session Configuration

**Problem:** File-based cache/sessions won't work (read-only filesystem).

**Solution:** Use database or Redis:

```
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

### 5. Storage Directory Issues

**Problem:** Storage directories are read-only on Vercel.

**Solution:**
- Use cloud storage (S3, Cloudinary, etc.) for file uploads
- Configure sessions to use `database` driver
- Configure cache to use `database` or `redis`

### 6. Check Vercel Function Logs

1. Go to Vercel Dashboard → Your Project → Functions
2. Click on the function that's failing
3. Check the "Logs" tab for detailed error messages

### 7. Enable Debug Mode (Temporarily)

To see detailed errors, temporarily set:
```
APP_DEBUG=true
APP_ENV=local
```

**⚠️ Remember to set back to:**
```
APP_DEBUG=false
APP_ENV=production
```

### 8. Verify Environment Variables

Check that all required environment variables are set:
- Go to Vercel Dashboard → Settings → Environment Variables
- Ensure variables are set for **Production**, **Preview**, and **Development** environments

### 9. Common Missing Variables

Make sure these are set:
- `APP_KEY` (most common cause of 500 errors)
- `APP_URL` (should match your Vercel domain)
- `DB_*` variables (if using database)
- `CACHE_STORE` (should be `database` or `redis`)
- `SESSION_DRIVER` (should be `database` or `cookie`)

### 10. Test Locally First

Before deploying, test with similar environment:
```bash
# Set environment variables
export APP_ENV=production
export APP_DEBUG=false
export APP_KEY=your-key-here

# Test the application
php artisan serve
```

## Quick Fix Checklist

- [ ] APP_KEY is set in Vercel environment variables
- [ ] APP_ENV is set to `production`
- [ ] APP_DEBUG is set to `false`
- [ ] APP_URL matches your Vercel domain
- [ ] Database connection is configured (not SQLite)
- [ ] CACHE_STORE is set to `database` or `redis`
- [ ] SESSION_DRIVER is set to `database` or `cookie`
- [ ] All migrations have been run on your database
- [ ] Check Vercel function logs for specific errors

## Getting Help

If the issue persists:
1. Check Vercel Function Logs for specific error messages
2. Enable APP_DEBUG=true temporarily to see detailed errors
3. Verify all environment variables are correctly set
4. Ensure your database is accessible from Vercel's servers

