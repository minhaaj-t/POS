# Vercel Deployment Guide

This Laravel application is configured for deployment on Vercel.

## Prerequisites

1. A Vercel account (sign up at [vercel.com](https://vercel.com))
2. Your project connected to a Git repository (GitHub, GitLab, or Bitbucket)

## Deployment Steps

### Option 1: Deploy via Vercel Dashboard

1. Go to [vercel.com/new](https://vercel.com/new)
2. Import your Git repository
3. Vercel will automatically detect the `vercel.json` configuration
4. Configure environment variables (see below)
5. Click "Deploy"

### Option 2: Deploy via Vercel CLI

1. Install Vercel CLI:
   ```bash
   npm i -g vercel
   ```

2. Login to Vercel:
   ```bash
   vercel login
   ```

3. Deploy:
   ```bash
   vercel
   ```

4. For production deployment:
   ```bash
   vercel --prod
   ```

## Environment Variables

Set the following environment variables in your Vercel project settings:

### Required Variables

- `APP_NAME` - Your application name
- `APP_ENV` - Set to `production`
- `APP_KEY` - Laravel application key (generate with `php artisan key:generate`)
- `APP_DEBUG` - Set to `false` for production
- `APP_URL` - Your Vercel deployment URL (e.g., `https://your-app.vercel.app`)

### Database Configuration

- `DB_CONNECTION` - Database type (e.g., `sqlite`, `mysql`, `pgsql`)
- `DB_DATABASE` - Database name or path
- `DB_HOST` - Database host (if using MySQL/PostgreSQL)
- `DB_PORT` - Database port (if using MySQL/PostgreSQL)
- `DB_USERNAME` - Database username (if using MySQL/PostgreSQL)
- `DB_PASSWORD` - Database password (if using MySQL/PostgreSQL)

### Additional Variables

Add any other environment variables your application requires.

## Important Notes

1. **Storage**: Vercel's serverless environment is read-only for the filesystem. If you need file storage, consider using:
   - Vercel Blob Storage
   - AWS S3
   - Cloudinary
   - Other cloud storage services

2. **Sessions**: For sessions to work properly, configure a session driver that doesn't rely on the filesystem (e.g., `database`, `redis`, or `cookie`)

3. **Cache**: Configure cache to use a service like Redis or Memcached instead of file-based caching

4. **Database**: SQLite files won't persist on Vercel. Use a managed database service like:
   - Vercel Postgres
   - PlanetScale (MySQL)
   - Supabase (PostgreSQL)
   - Other cloud database providers

5. **Build Time**: The build process may take several minutes due to Composer and npm installations

## Troubleshooting

- If deployment fails, check the build logs in the Vercel dashboard
- Ensure all environment variables are set correctly
- Verify that `composer.json` and `package.json` are valid
- Check that PHP 8.2+ extensions required by Laravel are available

## Project Structure

- `api/index.php` - Entry point for Vercel's PHP runtime
- `vercel.json` - Vercel configuration
- `.vercelignore` - Files excluded from deployment

