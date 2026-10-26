#!/bin/sh
set -e

cd /var/www/html

echo "[start] Ensuring writable directories exist..."
# storage/app/{public,private,backups} too: on Railway a volume is mounted
# over storage/app (photos, logos, backups) and starts out empty.
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data \
         storage/app/public storage/app/private storage/app/backups storage/logs
chown -R www-data:www-data storage
chmod -R 775 storage

echo "[start] Creating storage symlink..."
php artisan storage:link --force 2>/dev/null || true

echo "[start] Running database migrations..."
php artisan migrate --force

# Errors go to Railway → Deploy Logs (stderr) AND storage/logs/laravel.log.
export LOG_CHANNEL=stack
export LOG_STACK=single,stderr
export LOG_LEVEL=${LOG_LEVEL:-info}

echo "[start] Caching config, routes and views..."
php artisan optimize

# Railway tells us which port to serve on.
sed -i -E "s/listen [0-9]+;/listen ${PORT:-8000};/" /etc/nginx/nginx.conf

# The artisan commands above ran as root; make sure PHP-FPM (www-data) can
# still write the log file and compiled views/cache they created.
touch storage/logs/laravel.log
chown -R www-data:www-data storage/logs storage/framework bootstrap/cache

echo "[start] Starting PHP-FPM, Nginx, Reverb, the queue worker and the scheduler..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
