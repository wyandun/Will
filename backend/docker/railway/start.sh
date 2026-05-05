#!/bin/sh
# start.sh — SM Portal Railway container startup
#
# Responsibilities:
#   1. Ensure all required Laravel storage subdirectories exist
#   2. Create the public/storage symlink (idempotent)
#   3. Generate the Swagger/OpenAPI docs (storage is ephemeral on Railway)
#   4. Launch PHP-FPM as a background daemon
#   5. Launch Caddy in the foreground so it receives Railway's SIGTERM

set -e

WORKDIR="/var/www/html"

# ---------------------------------------------------------------------------
# 1. Ensure storage directory structure exists and is writable.
#    On Railway the image filesystem is writable but the directories baked
#    into the image may have www-data ownership. Railway runs containers as
#    root or a fixed UID — chmod is safer than chown here.
# ---------------------------------------------------------------------------
mkdir -p \
    "${WORKDIR}/storage/app/public" \
    "${WORKDIR}/storage/framework/cache/data" \
    "${WORKDIR}/storage/framework/sessions" \
    "${WORKDIR}/storage/framework/testing" \
    "${WORKDIR}/storage/framework/views" \
    "${WORKDIR}/storage/logs" \
    "${WORKDIR}/storage/api-docs" \
    "${WORKDIR}/bootstrap/cache"

chmod -R 777 \
    "${WORKDIR}/storage" \
    "${WORKDIR}/bootstrap/cache"

# ---------------------------------------------------------------------------
# 2. Create the public/storage symlink so uploaded files are web-accessible.
#    --relative makes the symlink portable inside the container.
# ---------------------------------------------------------------------------
php "${WORKDIR}/artisan" storage:link --force 2>/dev/null || true

# ---------------------------------------------------------------------------
# 3. Generate Swagger/OpenAPI documentation.
#    storage/api-docs/api-docs.json is NOT committed to the repo — it is an
#    ephemeral artifact regenerated on every deploy from the source annotations.
# ---------------------------------------------------------------------------
echo "[railway-start] Generating Swagger docs..."
php "${WORKDIR}/artisan" l5-swagger:generate

# ---------------------------------------------------------------------------
# 4. Start PHP-FPM as a background daemon (TCP on 127.0.0.1:9000).
# ---------------------------------------------------------------------------
echo "[railway-start] Starting PHP-FPM..."
php-fpm -D

# ---------------------------------------------------------------------------
# 5. Start Nginx in the foreground so Railway's SIGTERM reaches it cleanly.
# ---------------------------------------------------------------------------
echo "[railway-start] Starting Nginx on port ${PORT:-8080}..."
exec nginx -g "daemon off;"
