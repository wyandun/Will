#!/bin/sh
# worker.sh — SM Portal Railway queue worker startup
#
# This script is the PID 1 process for the "worker" Railway service.
# It runs instead of start.sh (which launches Nginx+FPM for the web service).
#
# Responsibilities:
#   1. Ensure all required Laravel storage subdirectories exist and are writable
#   2. Clear stale config/route/view caches (ephemeral filesystem — safe to repeat)
#   3. Launch php artisan queue:work in the foreground as PID 1
#
# Why this file exists instead of inlining commands in railway-worker.toml:
#   The railway Dockerfile stage sets ENTRYPOINT [] (empty exec-form array).
#   Railway passes startCommand as the Docker CMD in exec form — no shell wrapper.
#   A bare && chain in startCommand is NOT parsed; it runs as a literal argument.
#   Using an explicit script avoids that trap entirely and is easier to debug.

set -e

WORKDIR="/var/www/html"

echo "[worker] Ensuring storage directories exist..."
mkdir -p \
    "${WORKDIR}/storage/app/public" \
    "${WORKDIR}/storage/framework/cache/data" \
    "${WORKDIR}/storage/framework/sessions" \
    "${WORKDIR}/storage/framework/testing" \
    "${WORKDIR}/storage/framework/views" \
    "${WORKDIR}/storage/logs" \
    "${WORKDIR}/bootstrap/cache"

chmod -R 777 \
    "${WORKDIR}/storage" \
    "${WORKDIR}/bootstrap/cache"

echo "[worker] Clearing framework caches..."
php "${WORKDIR}/artisan" config:clear  2>/dev/null || true
php "${WORKDIR}/artisan" route:clear   2>/dev/null || true
php "${WORKDIR}/artisan" view:clear    2>/dev/null || true
php "${WORKDIR}/artisan" cache:clear   2>/dev/null || true

echo "[worker] Starting queue worker..."
exec php "${WORKDIR}/artisan" queue:work \
    --sleep=3 \
    --tries=3 \
    --max-time=3600 \
    --verbose
