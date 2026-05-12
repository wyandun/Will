#!/bin/sh
# entrypoint.sh — SM Portal backend container startup script
#
# Runs on every container start (not at build time), so it catches the case
# where the volume mount from the Windows/WSL2 host resets ownership and
# permissions on storage/ and bootstrap/cache/ after a rebuild.
#
# The script fixes permissions and then hands off to whatever CMD was passed
# (php-fpm for the web container, php artisan queue:work for the queue worker).

set -e

chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

php /var/www/html/artisan storage:link --force 2>/dev/null || true

exec "$@"
