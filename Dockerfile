# Use PHP 8.2 CLI for Laravel (better for php artisan serve)
# Note: PHP 8.2 is already installed in this base image - we don't need to install it via apt
FROM php:8.2-cli

# Set working directory
WORKDIR /var/www/html

# Install system dependencies (NOT PHP itself - PHP is already in the base image)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    sqlite3 \
    libsqlite3-dev \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions using docker-php-ext-install (the correct way for PHP Docker images)
RUN docker-php-ext-install pdo_mysql pdo_sqlite pdo_pgsql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html

# Set permissions for Laravel storage and cache directories
RUN chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Build assets (if you have npm/node)
# Uncomment if you need to build frontend assets
# RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
#     && apt-get install -y nodejs \
#     && npm install && npm run build \
#     && apt-get remove -y nodejs \
#     && rm -rf /var/lib/apt/lists/*

# Expose port 8000
EXPOSE 8000

# Start PHP built-in server (for Fly.io)
CMD php artisan serve --host=0.0.0.0 --port=8000

