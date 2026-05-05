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

chmod -R 775 \
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
# 4. Configure PHP-FPM to use a unix socket instead of the default TCP port.
#    This avoids a loopback round-trip for every request.
# ---------------------------------------------------------------------------
cat > /usr/local/etc/php-fpm.d/zz-railway.conf <<'FPMCONF'
[www]
listen = /run/php-fpm.sock
listen.mode = 0666
FPMCONF

echo "[railway-start] Starting PHP-FPM..."
php-fpm --daemonize

# Give FPM a moment to create the socket before Caddy tries to connect
sleep 1

# ---------------------------------------------------------------------------
# 5. Start Caddy in the foreground.
#    Caddy becomes PID 1's effective signal target — Railway's SIGTERM reaches
#    it cleanly and it drains in-flight requests before exiting.
# ---------------------------------------------------------------------------
echo "[railway-start] Starting Caddy on port ${PORT:-8080}..."
exec caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
