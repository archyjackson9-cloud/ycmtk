#!/bin/sh
set -e

cd /var/www/html

# A fresh named volume mounted over storage/ starts empty, hiding the
# directory structure baked into the image at build time - recreate it
# (and fix ownership) on every container start, not just the first one.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views \
    storage/app/public storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ ! -e public/storage ]; then
    php artisan storage:link
fi

# Config/route/view caches must be built at container start (with real
# runtime env vars available), not baked into the image at build time.
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
