# Deployment Guide - Render.com

This guide will help you deploy your Laravel application to Render.com using GitHub integration.

## Prerequisites

1. A GitHub account with your repository pushed
2. A Render.com account (free tier available)

## Step-by-Step Deployment

### 1. Create Render Account
- Go to [render.com](https://render.com)
- Sign up with your GitHub account (recommended for easy integration)

### 2. Create New Web Service
1. Click "New +" button in your Render dashboard
2. Select "Web Service"
3. Connect your GitHub account if not already connected
4. Select your repository: `minhaaj-t/POS`
5. Configure the service:
   - **Name**: `laravel-app` (or any name you prefer)
   - **Environment**: `PHP`
   - **Build Command**: 
     ```
     composer install --no-dev --optimize-autoloader && php artisan config:cache && php artisan route:cache && php artisan view:cache
     ```
   - **Start Command**: 
     ```
     php artisan serve --host=0.0.0.0 --port=$PORT
     ```

### 3. Environment Variables
Add these environment variables in Render dashboard:

**Required:**
- `APP_ENV` = `production`
- `APP_DEBUG` = `false`
- `APP_KEY` = Generate using: `php artisan key:generate --show` (or let Render generate it)
- `APP_URL` = Your Render URL (will be provided after deployment)

**Database (if using SQLite):**
- `DB_CONNECTION` = `sqlite`
- `DB_DATABASE` = `/opt/render/project/src/database/database.sqlite`

**Optional (recommended):**
- `LOG_CHANNEL` = `stderr`
- `LOG_LEVEL` = `error`
- `CACHE_DRIVER` = `file`
- `SESSION_DRIVER` = `file`
- `QUEUE_CONNECTION` = `sync`

### 4. Database Setup
If using SQLite:
1. The database file will be created automatically
2. Run migrations after first deployment:
   - Go to Render Shell
   - Run: `php artisan migrate --force`

### 5. Storage Link
After deployment, create storage link:
1. Go to Render Shell
2. Run: `php artisan storage:link`

### 6. Deploy
1. Click "Create Web Service"
2. Render will automatically:
   - Clone your repository
   - Install dependencies
   - Build your application
   - Deploy it

### 7. Your Live URL
After successful deployment, you'll get a URL like:
`https://laravel-app.onrender.com`

## Automatic Deployments
- Render automatically deploys when you push to your main branch
- You can also manually trigger deployments from the dashboard

## Troubleshooting

### Build Fails
- Check build logs in Render dashboard
- Ensure all dependencies are in `composer.json`
- Verify PHP version compatibility (requires PHP 8.2+)

### Application Errors
- Check application logs in Render dashboard
- Verify all environment variables are set correctly
- Ensure database migrations have run

### 500 Errors
- Check if `APP_KEY` is set
- Verify storage permissions
- Check if migrations have run

## Alternative: Railway.app

If you prefer Railway, you can also use:
1. Go to [railway.app](https://railway.app)
2. Connect GitHub
3. Create new project from GitHub repo
4. Railway will auto-detect Laravel and configure it

## Notes
- Free tier has limitations (spins down after inactivity)
- For production, consider paid plans
- Consider using PostgreSQL instead of SQLite for production

