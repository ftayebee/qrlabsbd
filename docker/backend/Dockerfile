# syntax=docker/dockerfile:1.7
# Laravel API image. One image runs three roles (php-fpm, queue worker,
# scheduler); docker-compose picks the role with `command`.

FROM composer:2.8 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist
COPY backend/ ./
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

FROM php:8.3-fpm-alpine AS runtime
ARG UID=1000
ARG GID=1000

RUN apk add --no-cache icu-libs libzip fcgi \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl opcache zip pcntl \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps \
 && addgroup -g ${GID} app && adduser -D -u ${UID} -G app app

COPY docker/backend/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/backend/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

WORKDIR /var/www/html
COPY --from=vendor --chown=app:app /app /var/www/html
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R app:app storage bootstrap/cache

USER app
EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

CMD ["php-fpm", "-F"]
