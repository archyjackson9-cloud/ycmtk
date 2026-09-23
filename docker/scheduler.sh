#!/bin/sh
# Runs Laravel's scheduler once a minute (routes/console.php: expired stock
# reservations, abandoned carts, stale-order escalation - TOR §11), without
# needing a real cron daemon inside the container.
set -e
cd /var/www/html

while true; do
    php artisan schedule:run --no-interaction --verbose
    sleep 60
done
