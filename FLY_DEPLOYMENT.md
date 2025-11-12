# Fly.io Deployment Guide

This guide will help you deploy your Laravel application to Fly.io.

## Prerequisites

1. Install the [Fly CLI](https://fly.io/docs/getting-started/installing-flyctl/)
2. Create a Fly.io account at [fly.io](https://fly.io)
3. Login to Fly.io: `fly auth login`

## Initial Setup

1. **Launch your app** (first time only):
   ```bash
   fly launch
   ```
   This will:
   - Create a new app on Fly.io
   - Generate a `fly.toml` configuration file
   - Ask you to set up a database (optional)

2. **Set environment variables**:
   ```bash
   fly secrets set APP_KEY="your-app-key-here"
   fly secrets set APP_ENV="production"
   fly secrets set APP_DEBUG="false"
   ```
   
   For database configuration (if using PostgreSQL):
   ```bash
   fly secrets set DB_CONNECTION="pgsql"
   fly secrets set DB_HOST="your-db-host"
   fly secrets set DB_DATABASE="your-db-name"
   fly secrets set DB_USERNAME="your-db-user"
   fly secrets set DB_PASSWORD="your-db-password"
   ```

3. **Generate application key** (if not set):
   ```bash
   fly ssh console -C "php artisan key:generate"
   ```

## Database Setup

### Option 1: Use Fly.io PostgreSQL (Recommended)

1. **Create a PostgreSQL database**:
   ```bash
   fly postgres create --name ip-test-db
   ```

2. **Attach the database to your app**:
   ```bash
   fly postgres attach ip-test-db
   ```

3. **Run migrations**:
   ```bash
   fly ssh console -C "php artisan migrate --force"
   ```

### Option 2: Use External Database

Set the database connection secrets as shown in the environment variables section above.

## Deployment

1. **Deploy your application**:
   ```bash
   fly deploy
   ```

2. **Check deployment status**:
   ```bash
   fly status
   ```

3. **View logs**:
   ```bash
   fly logs
   ```

## Post-Deployment

1. **Run migrations** (if not done already):
   ```bash
   fly ssh console -C "php artisan migrate --force"
   ```

2. **Clear and cache configuration**:
   ```bash
   fly ssh console -C "php artisan config:cache"
   fly ssh console -C "php artisan route:cache"
   fly ssh console -C "php artisan view:cache"
   ```

3. **Set up storage permissions**:
   ```bash
   fly ssh console -C "chmod -R 775 storage bootstrap/cache"
   ```

## Useful Commands

- **SSH into your app**: `fly ssh console`
- **View app info**: `fly info`
- **Scale your app**: `fly scale count 2` (for 2 instances)
- **View metrics**: `fly metrics`
- **Open your app**: `fly open`

## Troubleshooting

1. **Check application logs**:
   ```bash
   fly logs
   ```

2. **SSH into the container**:
   ```bash
   fly ssh console
   ```

3. **Check environment variables**:
   ```bash
   fly ssh console -C "env"
   ```

4. **Restart the app**:
   ```bash
   fly apps restart ip-test
   ```

## Notes

- The app is configured to run on port 8000 internally
- HTTPS is automatically enabled
- Auto-scaling is configured (machines start/stop based on traffic)
- Minimum memory is set to 512MB (adjust in `fly.toml` if needed)

## Custom Domain

To add a custom domain:

1. Add your domain in the Fly.io dashboard
2. Update DNS records as instructed
3. Fly.io will automatically provision SSL certificates

