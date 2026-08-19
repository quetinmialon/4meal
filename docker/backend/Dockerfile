FROM composer:2 AS composer

WORKDIR /app

COPY backend/composer.json backend/composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist

FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libpq-dev \
        unzip \
    && docker-php-ext-install \
        pcntl \
        pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY docker/php/local.ini /usr/local/etc/php/conf.d/99-4meal.ini
COPY docker/backend/entrypoint.sh /usr/local/bin/backend-entrypoint
COPY backend/ ./

RUN chmod +x /usr/local/bin/backend-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views bootstrap/cache \
    && composer install --no-dev --no-interaction --no-scripts --prefer-dist

EXPOSE 8000

CMD ["backend-entrypoint"]
