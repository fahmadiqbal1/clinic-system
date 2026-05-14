#!/bin/sh
set -e

# Fix ownership of Docker-created volumes (root-owned on first mount)
chown -R www-data:www-data /var/www/html/public
chown -R www-data:www-data /var/www/html/storage

su-exec www-data php artisan migrate --force
php artisan storage:link --force 2>/dev/null || true

# Start php-fpm as root; it drops to www-data for workers per www.conf
exec php-fpm
