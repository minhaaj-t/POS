# Deploying Laravel to Vercel - Important Notes

## ⚠️ WARNING: Vercel is NOT Recommended for Laravel

Vercel is designed for serverless functions and static sites. Laravel has significant limitations on Vercel:

### Major Issues:

1. **No Persistent Storage**: 
   - `storage/` directory is read-only
   - File uploads won't work
   - Session files won't persist
   - Cache files won't persist

2. **No Background Jobs**:
   - Queue workers won't run
   - Scheduled tasks won't work

3. **Python Server Won't Work**:
   - Your `server.py` Oracle connector cannot run on Vercel
   - Vercel doesn't support long-running Python processes

4. **Database Limitations**:
   - SQLite won't work (no file system writes)
   - Need external database (MySQL/PostgreSQL)

5. **Session Storage**:
   - Must use database or Redis for sessions
   - File-based sessions won't work

### Required Changes for Vercel:

1. **Update `.env` for Vercel**:
   ```
   SESSION_DRIVER=database
   CACHE_DRIVER=database
   QUEUE_CONNECTION=sync
   ```

2. **Move Python Server**:
   - Deploy `server.py` separately (Railway, Fly.io, or Heroku)
   - Update `PYTHON_SERVER_URL` to point to external server

3. **Database**:
   - Use external MySQL/PostgreSQL (PlanetScale, Supabase, etc.)
   - Cannot use SQLite

4. **Storage**:
   - Use S3 or similar for file storage
   - Cannot use local storage

### Better Alternatives:

#### 1. **Railway.app** (Recommended)
```bash
# Install Railway CLI
npm i -g @railway/cli

# Login and deploy
railway login
railway init
railway up
```

#### 2. **Fly.io**
```bash
# Install Fly CLI
curl -L https://fly.io/install.sh | sh

# Launch app
fly launch
fly deploy
```

#### 3. **Render.com**
- Connect GitHub repo
- Select "Web Service"
- Choose PHP environment
- Add build command: `composer install --no-dev --optimize-autoloader`
- Add start command: `php artisan serve --host=0.0.0.0 --port=$PORT`

#### 4. **DigitalOcean App Platform**
- Connect GitHub repo
- Select PHP buildpack
- Configure environment variables
- Deploy

### If You Still Want to Try Vercel:

1. **Install Vercel CLI**:
   ```bash
   npm i -g vercel
   ```

2. **Deploy**:
   ```bash
   vercel
   ```

3. **Set Environment Variables** in Vercel dashboard:
   - `APP_KEY`
   - `DB_CONNECTION`
   - `DB_HOST`
   - `DB_DATABASE`
   - `DB_USERNAME`
   - `DB_PASSWORD`
   - `PYTHON_SERVER_URL` (external server URL)
   - `SESSION_DRIVER=database`
   - `CACHE_DRIVER=database`

4. **Expected Issues**:
   - File uploads won't work
   - Storage directory issues
   - Python server needs separate deployment
   - Limited functionality

### Recommended Setup:

**For Laravel App**: Use Railway.app or Fly.io
**For Python Server**: Deploy separately to:
- Railway.app (supports Python)
- Fly.io (supports Python)
- Heroku (supports Python)
- Or keep on your own server

This gives you:
- ✅ Full Laravel functionality
- ✅ Persistent storage
- ✅ Background jobs
- ✅ Python server running separately
- ✅ Better performance
- ✅ Lower costs

