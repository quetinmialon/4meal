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

if [ ! -f vendor/autoload.php ]; then
  composer install --no-dev --no-interaction --no-scripts --prefer-dist
fi

if [ -z "${APP_KEY:-}" ]; then
  export APP_KEY="$(php artisan key:generate --show --no-interaction)"
fi

exec php artisan serve --host=0.0.0.0 --port=8000
