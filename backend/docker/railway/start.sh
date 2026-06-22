#!/bin/sh
# start.sh — SM Portal Railway container startup
#
# Responsibilities:
#   1. Ensure all required Laravel storage subdirectories exist
#   2. Create the public/storage symlink (idempotent)
#   3. Clear stale Laravel framework caches
#   4. Generate the Swagger/OpenAPI docs (storage is ephemeral on Railway)
#   5. Launch PHP-FPM as a background daemon
#   6. Substitute $PORT into the Nginx config and launch Nginx in the foreground

set -e

WORKDIR="/var/www/html"

# ---------------------------------------------------------------------------
# 1. Ensure storage directory structure exists and is writable.
#    On Railway the image filesystem is writable but the directories baked
#    into the image may have www-data ownership. Railway runs containers as
#    root or a fixed UID — chmod is safer than chown here.
#
#    PERSISTENT VOLUME: storage/app/public must be mounted as a Railway
#    volume so uploaded files (process documents, avatars, feed attachments)
#    survive redeploys. Railway mounts volumes owned by root, so the chmod
#    below is REQUIRED to keep the mounted path writable by PHP-FPM (www-data).
#    The mkdir is idempotent and harmless whether or not the volume is mounted;
#    if it is, mkdir/chmod operate on the volume itself. See railway.toml.
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

echo "[railway-start] storage/app/public ready (persistent volume mount point)"

# ---------------------------------------------------------------------------
# 2. Create the public/storage symlink so uploaded files are web-accessible.
#    --relative makes the symlink portable inside the container.
# ---------------------------------------------------------------------------
php "${WORKDIR}/artisan" storage:link --force 2>/dev/null || true

# ---------------------------------------------------------------------------
# 3. Clear stale Laravel framework caches.
#    Route cache, config cache, and view cache from a previous build can cause
#    routes (including l5-swagger's /api/documentation) to resolve incorrectly.
# ---------------------------------------------------------------------------
echo "[railway-start] Clearing framework caches..."
php "${WORKDIR}/artisan" config:clear  2>/dev/null || true
php "${WORKDIR}/artisan" route:clear   2>/dev/null || true
php "${WORKDIR}/artisan" view:clear    2>/dev/null || true
php "${WORKDIR}/artisan" cache:clear   2>/dev/null || true

# ---------------------------------------------------------------------------
# 4. Generate Swagger/OpenAPI documentation.
#    storage/api-docs/api-docs.json is NOT committed to the repo — it is an
#    ephemeral artifact regenerated on every deploy from the source annotations.
#    Allow failure with || true: a broken annotation must not abort the deploy;
#    the rest of the API remains reachable and the issue can be fixed separately.
# ---------------------------------------------------------------------------
echo "[railway-start] Generating Swagger docs..."
php "${WORKDIR}/artisan" l5-swagger:generate || echo "[railway-start] WARNING: Swagger generation failed — /api/documentation may return 404 until fixed"

# ---------------------------------------------------------------------------
# 5. Start PHP-FPM as a background daemon (TCP on 127.0.0.1:9000).
# ---------------------------------------------------------------------------
echo "[railway-start] Starting PHP-FPM..."
php-fpm -D

# ---------------------------------------------------------------------------
# 6. Inject $PORT into the Nginx config and start in the foreground.
#    Nginx does not read env vars directly — envsubst replaces $PORT before launch.
#    Single quotes around '$PORT' are intentional: they prevent the shell from
#    expanding $PORT, passing the literal string to envsubst so it scopes
#    substitution to PORT only — leaving $uri, $query_string, etc. untouched.
# ---------------------------------------------------------------------------
export PORT="${PORT:-8080}"
envsubst '$PORT' < /etc/nginx/sites-enabled/default > /tmp/nginx-railway.conf
cp /tmp/nginx-railway.conf /etc/nginx/sites-enabled/default

echo "[railway-start] Starting Nginx on port ${PORT}..."
exec nginx -g "daemon off;"
