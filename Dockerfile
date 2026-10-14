# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Étape 1 — Compilation des ressources frontend (Vite + Tailwind)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY . .

RUN npm ci --no-audit --no-fund && npm run build


# ---------------------------------------------------------------------------
# Étape 2 — Application Laravel (PHP-FPM + Nginx)
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        git \
        unzip \
        gettext-base \
        curl \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
        libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_pgsql mbstring zip gd intl bcmath opcache pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dépendances PHP (couche mise en cache tant que composer.json/lock ne changent pas)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# Code de l'application
COPY . .

# Ressources compilées depuis l'étape 1
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-interaction \
    && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Configuration nginx (le port ${PORT} est substitué au démarrage), supervisor, php.ini
COPY docker/nginx.conf.template /etc/nginx/templates/laravel.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-epsv.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf

# Render fournit le port d'écoute via $PORT (10000 par défaut)
EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint"]
