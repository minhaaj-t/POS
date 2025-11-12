# Koyeb Deployment Guide

This guide will help you deploy your Laravel application to [Koyeb](https://app.koyeb.com/).

## Prerequisites

1. A GitHub account with your project repository
2. A Koyeb account (sign up at [https://app.koyeb.com/](https://app.koyeb.com/))
3. Your Laravel project pushed to GitHub

## Deployment Steps

### 1. Prepare Your Repository

Ensure all your code is committed and pushed to GitHub:

```bash
git add .
git commit -m "Prepare for Koyeb deployment"
git push origin main
```

### 2. Deploy to Koyeb

1. **Sign in to Koyeb**
   - Go to [https://app.koyeb.com/](https://app.koyeb.com/)
   - Sign in or create a new account

2. **Create a New App**
   - Click on **"Create App"** button
   - Select **"GitHub"** as your deployment method
   - Authorize Koyeb to access your GitHub repositories if prompted

3. **Select Your Repository**
   - Choose your repository from the list
   - Select the branch (usually `main` or `master`)
   - Click **"Continue"**

4. **Configure Build Settings**
   - **Builder**: Select "Buildpack" (Koyeb will auto-detect PHP/Laravel)
   - **Build Command**: Leave empty (Koyeb will use default Laravel build)
   - **Run Command**: `vendor/bin/heroku-php-apache2 public/`
   - Or leave empty to use the Procfile

5. **Set Environment Variables**
   
   Click on **"Environment Variables"** and add the following required variables:
   
   ```
   APP_NAME=Laravel
   APP_ENV=production
   APP_KEY=base64:YOUR_APP_KEY_HERE
   APP_DEBUG=false
   APP_URL=https://your-app-name.koyeb.app
   
   LOG_CHANNEL=stderr
   LOG_LEVEL=error
   
   DB_CONNECTION=sqlite
   DB_DATABASE=/tmp/database.sqlite
   ```
   
   **Important Notes:**
   - Replace `YOUR_APP_KEY_HERE` with your actual APP_KEY from `.env` file
   - For production, consider using a managed database (PostgreSQL, MySQL) instead of SQLite
   - Update `APP_URL` with your actual Koyeb app URL after deployment

6. **Advanced Settings (Optional)**
   - **Instance Type**: Choose based on your needs (Starter is free)
   - **Region**: Select the closest region to your users
   - **Scaling**: Configure auto-scaling if needed

7. **Deploy**
   - Click **"Deploy"** button
   - Koyeb will build and deploy your application
   - Wait for the deployment to complete (usually 2-5 minutes)

### 3. Post-Deployment Setup

After deployment, you may need to:

1. **Run Migrations**
   - Go to your app's dashboard in Koyeb
   - Open the "Shell" or "Console" tab
   - Run: `php artisan migrate --force`

2. **Generate Application Key** (if not set)
   - In the shell: `php artisan key:generate`

3. **Clear Cache**
   - In the shell: `php artisan config:cache`
   - In the shell: `php artisan route:cache`
   - In the shell: `php artisan view:cache`

4. **Set Storage Link** (if using public storage)
   - In the shell: `php artisan storage:link`

### 4. Using a Production Database

For production, SQLite is not recommended. Set up a managed database:

**Option 1: Koyeb Managed Database**
- Create a PostgreSQL or MySQL database in Koyeb
- Update environment variables:
  ```
  DB_CONNECTION=pgsql
  DB_HOST=your-db-host
  DB_PORT=5432
  DB_DATABASE=your-db-name
  DB_USERNAME=your-db-user
  DB_PASSWORD=your-db-password
  ```

**Option 2: External Database**
- Use services like PlanetScale, Supabase, or Railway
- Update environment variables accordingly

### 5. Environment Variables Reference

Here's a complete list of environment variables you might need:

```bash
# Application
APP_NAME=Laravel
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://your-app.koyeb.app

# Logging
LOG_CHANNEL=stderr
LOG_LEVEL=error

# Database (SQLite - for development only)
DB_CONNECTION=sqlite
DB_DATABASE=/tmp/database.sqlite

# Database (PostgreSQL - recommended for production)
# DB_CONNECTION=pgsql
# DB_HOST=your-host
# DB_PORT=5432
# DB_DATABASE=your-database
# DB_USERNAME=your-username
# DB_PASSWORD=your-password

# Cache
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# Mail (if using)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=null
MAIL_FROM_NAME="${APP_NAME}"
```

### 6. Continuous Deployment

Koyeb automatically deploys when you push to your connected branch:
- Every push to `main` (or your selected branch) triggers a new deployment
- You can view deployment history in the Koyeb dashboard
- Rollback to previous deployments if needed

### 7. Monitoring and Logs

- **Logs**: View real-time logs in the Koyeb dashboard
- **Metrics**: Monitor CPU, memory, and request metrics
- **Alerts**: Set up alerts for errors or performance issues

## Troubleshooting

### Build Fails
- Check build logs in Koyeb dashboard
- Ensure `composer.json` is valid
- Verify PHP version compatibility (requires PHP 8.2+)

### Application Errors
- Check application logs in Koyeb dashboard
- Verify all environment variables are set correctly
- Ensure database migrations have run

### 500 Errors
- Check if `APP_KEY` is set
- Verify database connection
- Check file permissions (storage, bootstrap/cache)

### Database Issues
- SQLite files are ephemeral on Koyeb (use managed database for production)
- Ensure database migrations have run
- Check database connection credentials

## Additional Resources

- [Koyeb Documentation](https://www.koyeb.com/docs)
- [Laravel Deployment Documentation](https://laravel.com/docs/deployment)
- [Koyeb Community](https://www.koyeb.com/community)

## Support

If you encounter issues:
1. Check the Koyeb deployment logs
2. Review Laravel logs in the Koyeb dashboard
3. Consult Koyeb documentation
4. Reach out to Koyeb support

