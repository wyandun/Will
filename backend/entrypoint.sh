#!/bin/sh
# entrypoint.sh — SM Portal backend container startup script
#
# Runs on every container start (not at build time), so it catches the case
# where the volume mount from the Windows/WSL2 host resets ownership and
# permissions on storage/ and bootstrap/cache/ after a rebuild.
#
# Also auto-installs composer dependencies when the vendor volume is empty
# (happens on fresh containers or after `docker compose down -v`).
#
# Requires: composer binary must be in the image (COPY --from=composer:2 in Dockerfile).

set -e

# --- Auto composer install ---------------------------------------------------
# The /var/www/html/vendor anonymous volume can be empty on first start.
# We check for autoload.php as a reliable signal that install has run.
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "[entrypoint] vendor/ vacío — ejecutando composer install..."
    cd /var/www/html
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress
    echo "[entrypoint] composer install completado."
fi

# --- Permissions ------------------------------------------------------------
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

php /var/www/html/artisan storage:link --force 2>/dev/null || true

exec "$@"
