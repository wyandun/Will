# SM Portal — Backend Dockerfile
# Multi-stage build:
#   builder      — installs Composer dependencies (no dev)
#   builder-dev  — installs Composer dependencies (with dev, for local Docker)
#   production   — lean PHP-FPM runtime image (Railway + local)
#   development  — same runtime but with dev vendor (local Docker only)
#   railway      — production + Nginx for Railway deployment

# ---------------------------------------------------------------------------
# Stage 1a: builder — production vendor (no dev packages)
# ---------------------------------------------------------------------------
FROM composer:2.8 AS builder

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

COPY . .

RUN composer run-script post-autoload-dump 2>/dev/null || true

# ---------------------------------------------------------------------------
# Stage 1b: builder-dev — dev vendor (local Docker only)
# ---------------------------------------------------------------------------
FROM composer:2.8 AS builder-dev

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-interaction \
    --no-scripts \
    --prefer-dist

COPY . .

RUN composer run-script post-autoload-dump 2>/dev/null || true

# ---------------------------------------------------------------------------
# Stage 2: runtime-base
# Shared PHP-FPM runtime. Both production and development extend this.
# ---------------------------------------------------------------------------
FROM php:8.4-fpm AS runtime-base

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

# ---------------------------------------------------------------------------
# Stage 3: production
# Uses production vendor (no dev packages). Used by Railway and local default.
# ---------------------------------------------------------------------------
FROM runtime-base AS production

COPY --from=builder /app/vendor ./vendor
COPY . .

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Stage 4: development
# Uses dev vendor (includes phpunit, collision, etc). Local Docker only.
# ---------------------------------------------------------------------------
FROM runtime-base AS development

COPY --from=builder-dev /app/vendor ./vendor
COPY . .

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Stage 5: railway
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

# Install Nginx and gettext-base (provides envsubst for $PORT substitution)
RUN apt-get update && apt-get install -y nginx gettext-base \
    && rm -rf /var/lib/apt/lists/*

# Nginx config — proxies to PHP-FPM on 127.0.0.1:9000
COPY docker/railway/nginx.conf /etc/nginx/sites-enabled/default

# Start script for the web service (Nginx + PHP-FPM)
COPY docker/railway/start.sh /usr/local/bin/railway-start.sh
RUN chmod +x /usr/local/bin/railway-start.sh

# Start script for the queue worker service (php artisan queue:work)
COPY docker/railway/worker.sh /usr/local/bin/railway-worker.sh
RUN chmod +x /usr/local/bin/railway-worker.sh

# Drop the production entrypoint — permissions are handled in start.sh
ENTRYPOINT []

EXPOSE 8080

CMD ["/usr/local/bin/railway-start.sh"]
