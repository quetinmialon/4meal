#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
  bootstrap/cache \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/testing \
  storage/framework/views \
  storage/app/public/cookbooks \
  storage/logs

# Keep the persistent vendor volume aligned with composer.lock only when it
# actually changed since the last installation.
find bootstrap/cache -maxdepth 1 -type f -name '*.php' -delete

if [ ! -f vendor/autoload.php ] || [ ! -f vendor/.4meal-composer.lock ] || ! cmp -s composer.lock vendor/.4meal-composer.lock; then
  composer install --no-dev --no-interaction --no-scripts --prefer-dist
  cp composer.lock vendor/.4meal-composer.lock
fi

if [ "$#" -gt 0 ]; then
  exec "$@"
fi

php artisan package:discover --ansi
php artisan storage:link --force --no-interaction

if [ -z "${APP_KEY:-}" ]; then
  export APP_KEY="$(php artisan key:generate --show --no-interaction)"
fi

# JWT tokens need a secret as well. Generate one at container startup when it
# is not provided through the environment or the mounted .env file.
if [ -z "${JWT_SECRET:-}" ]; then
  export JWT_SECRET="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
fi

if [ "${BROADCAST_CONNECTION:-null}" = "reverb" ]; then
  if [ -z "${REVERB_APP_ID:-}" ] || [ -z "${REVERB_APP_KEY:-}" ] || [ -z "${REVERB_APP_SECRET:-}" ]; then
    echo "REVERB_APP_ID, REVERB_APP_KEY and REVERB_APP_SECRET must be set when BROADCAST_CONNECTION=reverb." >&2
    exit 1
  fi
fi

if [ "${BACKEND_CONFIG_CACHE:-false}" = "true" ]; then
  php artisan config:cache --no-interaction
fi

exec php artisan serve --no-reload --host=0.0.0.0 --port=8000
