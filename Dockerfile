# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.4
ARG WP_CLI_VERSION=2.11.0

FROM composer:2 AS vendor

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN --mount=type=secret,id=composer_auth,target=/var/www/html/auth.json \
    composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --optimize-autoloader

COPY . .

FROM php:${PHP_VERSION}-fpm-alpine AS runtime
ARG WP_CLI_VERSION

RUN set -eux; \
    apk add --no-cache nginx supervisor curl mysql-client \
        icu-libs libzip libpng libjpeg-turbo freetype oniguruma; \
    apk add --no-cache --virtual .build-deps \
        icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev curl-dev; \
    docker-php-ext-configure gd --with-jpeg --with-freetype; \
    docker-php-ext-install -j"$(nproc)" \
        mysqli pdo_mysql gd intl zip exif bcmath mbstring curl opcache; \
    apk del .build-deps; \
    curl -fsSL -o /usr/local/bin/wp \
        "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar"; \
    chmod +x /usr/local/bin/wp; \
    mkdir -p /tmp/nginx/client_temp /tmp/nginx/proxy_temp /tmp/nginx/fastcgi_temp \
             /tmp/nginx/uwsgi_temp /tmp/nginx/scgi_temp; \
    chown -R www-data:www-data /tmp/nginx

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisord.conf /etc/supervisord.conf

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /var/www/html .

RUN chown www-data:www-data web/app/uploads

USER www-data
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --start-period=20s \
    CMD wget -q -O- http://127.0.0.1:8080/healthz || exit 1

CMD ["supervisord", "-c", "/etc/supervisord.conf"]
