# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.4
ARG WP_CLI_VERSION=2.11.0

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
COPY config ./config

RUN --mount=type=secret,id=composer_auth,dst=/app/auth.json \
    composer install \
      --no-dev \
      --no-scripts \
      --no-interaction \
      --optimize-autoloader \
      --no-progress

COPY . .

FROM php:${PHP_VERSION}-fpm-bookworm AS runtime
ARG WP_CLI_VERSION

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
      bcmath \
      curl \
      exif \
      gd \
      intl \
      mbstring \
      mysqli \
      opcache \
      pdo_mysql \
      redis \
      zip

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
      echo 'opcache.memory_consumption=192'; \
      echo 'opcache.max_accelerated_files=20000'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.interned_strings_buffer=16'; \
    } > "$PHP_INI_DIR/conf.d/opcache-recommended.ini"

RUN curl -fsSL -o /usr/local/bin/wp \
      "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar" \
    && chmod +x /usr/local/bin/wp

RUN { \
      echo ''; \
      echo '; required'; \
      echo 'clear_env = no'; \
      echo 'catch_workers_output = yes'; \
      echo 'decorate_workers_output = no'; \
      echo 'php_admin_flag[log_errors] = on'; \
      echo 'php_admin_value[error_log] = /proc/self/fd/2'; \
    } >> /usr/local/etc/php-fpm.d/www.conf

WORKDIR /app

COPY --from=vendor --chown=www-data:www-data /app /app

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
