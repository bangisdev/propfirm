# syntax=docker/dockerfile:1

FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    icu-dev \
    oniguruma-dev \
    supervisor \
    && docker-php-ext-install pdo pdo_pgsql zip bcmath intl opcache pcntl \
    && pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./

# --- development stage: keep dev deps, mount source at runtime ---
FROM base AS development
RUN composer install --no-interaction --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize
CMD ["php-fpm"]

# --- production stage: no dev deps, immutable image ---
FROM base AS production
ENV APP_ENV=production
RUN composer install --no-interaction --no-dev --no-scripts --no-autoloader --optimize-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
USER www-data
CMD ["php-fpm"]
