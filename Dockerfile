# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1: PHP dependencies (composer install against the real app source,
# so package:discover - triggered by composer.json's post-autoload-dump
# script - has the full Laravel bootstrap available).
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app
COPY . .
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# ---------------------------------------------------------------------------
# Stage 2: frontend build (Tailwind v4 + Vite, storefront + admin glass CSS).
# ---------------------------------------------------------------------------
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 3: runtime image - Apache + PHP 8.3, serving public/ directly (no
# separate nginx/php-fpm split, so there's no cross-container static-file
# volume to keep in sync - the whole app lives in one image).
# ---------------------------------------------------------------------------
FROM php:8.3-apache AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libxml2-dev libicu-dev \
        unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views \
        storage/app/public storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/scheduler.sh /usr/local/bin/scheduler.sh
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/scheduler.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
