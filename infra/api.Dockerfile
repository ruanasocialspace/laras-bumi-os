FROM composer:2 AS composer
FROM php:8.5-fpm-bookworm

WORKDIR /app
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pcntl \
    && rm -rf /var/lib/apt/lists/*

ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist
COPY . .
RUN mkdir -p storage/app/private \
    && composer dump-autoload --no-interaction --optimize \
    && composer check-platform-reqs \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
CMD ["php-fpm"]
