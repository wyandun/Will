# SM Portal — Backend Dockerfile
# Multi-stage build:
#   builder  — installs Composer dependencies
#   production — lean PHP-FPM runtime image

# ---------------------------------------------------------------------------
# Stage 1: builder
# Install Composer dependencies without dev packages.
# ---------------------------------------------------------------------------
FROM composer:2.8 AS builder

WORKDIR /app

# Copy only the manifests first to exploit Docker layer caching.
# The vendor directory is only rebuilt when composer.json / composer.lock change.
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

# Copy the full application source after dependencies are resolved
COPY . .

# Run post-install scripts now that the full codebase is present
RUN composer run-script post-autoload-dump 2>/dev/null || true

# ---------------------------------------------------------------------------
# Stage 2: production
# Lean PHP 8.4-FPM image with only the extensions required by Laravel +
# the SM Portal stack (pgsql, redis, gd, zip, bcmath, opcache, intl).
# ---------------------------------------------------------------------------
FROM php:8.4-fpm AS production

# Install system dependencies required to compile PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    unzip \
    git \
    # Tesseract for OCR (used by sm-portal document processing)
    tesseract-ocr \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        zip \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl

# Install the Redis extension via PECL (not bundled with PHP source)
RUN pecl install redis \
    && docker-php-ext-enable redis

# Copy the custom PHP-FPM and OPcache configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Copy the entrypoint script that fixes volume-mount permissions at startup
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# Copy vendor directory from the builder stage
COPY --from=builder /app/vendor ./vendor

# Copy the application source
COPY . .

# Set correct ownership for storage and cache directories.
# www-data is the user PHP-FPM runs as inside the official image.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Expose the PHP-FPM port (Nginx proxies to this)
EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Stage 3: railway
# Production HTTP server for Railway deployment.
# Uses Nginx as a reverse proxy in front of PHP-FPM — same as local Docker.
#
# Start sequence (railway-start.sh):
#   1. Ensure all storage subdirectories exist and are writable
#   2. Run php artisan storage:link (idempotent, safe to repeat)
#   3. Generate Swagger docs (ephemeral filesystem — must run every deploy)
#   4. Start PHP-FPM as a background daemon
#   5. Start Nginx in the foreground (PID 1 signal target)
# ---------------------------------------------------------------------------
FROM production AS railway

# Install Nginx
RUN apt-get update && apt-get install -y nginx \
    && rm -rf /var/lib/apt/lists/*

# Nginx config — proxies to PHP-FPM on 127.0.0.1:9000
COPY docker/railway/nginx.conf /etc/nginx/sites-enabled/default

# Start script that wires up the filesystem and launches both processes
COPY docker/railway/start.sh /usr/local/bin/railway-start.sh
RUN chmod +x /usr/local/bin/railway-start.sh

# Drop the production entrypoint — permissions are handled in start.sh
ENTRYPOINT []

EXPOSE 8080

CMD ["/usr/local/bin/railway-start.sh"]


