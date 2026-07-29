# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Base image: PHP 8.4 FPM (Alpine) + extensions required by the platform.
# Shared by app (php-fpm), queue (Horizon), scheduler and Reverb services.
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-alpine AS base

# System deps + PHP extensions via the mlocati helper (fast, reliable).
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        pdo_mysql \
        redis \
        gd \
        intl \
        bcmath \
        zip \
        exif \
        pcntl \
        opcache \
    && apk add --no-cache fcgi

WORKDIR /var/www/html

# Sensible PHP + OPcache defaults (performance-first).
COPY docker/php/php.ini      /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/opcache.ini  /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf

# Composer binary from the official image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Healthcheck helper for php-fpm.
COPY --chmod=0755 docker/php/php-fpm-healthcheck /usr/local/bin/php-fpm-healthcheck

# ---------------------------------------------------------------------------
# Dev target: source is bind-mounted via docker-compose; deps installed on up.
# ---------------------------------------------------------------------------
FROM base AS dev

ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} app 2>/dev/null || true \
    && adduser -D -u ${UID} -G www-data app 2>/dev/null || true

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Prod target: self-contained image with vendor + built assets baked in.
# ---------------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=production

# Install PHP deps first (better layer caching).
COPY composer.json composer.lock ./
COPY Modules ./Modules
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# App source + optimized autoloader.
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
CMD ["php-fpm"]
