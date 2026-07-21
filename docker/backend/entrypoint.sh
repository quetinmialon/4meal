#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
  bootstrap/cache \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/testing \
  storage/framework/views \
  storage/logs

# Keep the persistent vendor volume and Laravel package manifests aligned
# with the current source tree on every container start.
find bootstrap/cache -maxdepth 1 -type f -name '*.php' -delete

composer install --no-dev --no-interaction --no-scripts --prefer-dist

php artisan package:discover --ansi

# Freeze the configuration after Docker has injected its environment variables.
# The generated cache is ignored by Git and is rebuilt on every container start.
php artisan config:cache --no-interaction

if [ -z "${APP_KEY:-}" ]; then
  export APP_KEY="$(php artisan key:generate --show --no-interaction)"
fi

exec php artisan serve --host=0.0.0.0 --port=8000
