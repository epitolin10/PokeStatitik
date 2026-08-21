#!/bin/sh
set -e

# Render assigns the listen port via $PORT at runtime; Apache's image
# defaults to 80, so rewrite both the port listener and the vhost.
PORT="${PORT:-80}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

if [ -n "${DATABASE_URL:-}" ]; then
    DATABASE_URL="$(printf '%s' "$DATABASE_URL" | sed 's/serverVersion=&/serverVersion=15.0.0\&/; s/serverVersion=$/serverVersion=15.0.0/')"
    export DATABASE_URL
fi

# Apply pending Doctrine migrations (no-op if none are configured).
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Cache needs env-dependent config (DATABASE_URL, APP_SECRET, ...) which is
# only available at runtime on Render, so it's warmed here instead of at
# build time.
php bin/console cache:clear --env="${APP_ENV:-prod}"
php bin/console cache:warmup --env="${APP_ENV:-prod}"

# cache:clear/warmup above run as root; hand the resulting files back to
# www-data so Apache/PHP can write logs and cache during requests.
chown -R www-data:www-data var public/uploads

exec "$@"
