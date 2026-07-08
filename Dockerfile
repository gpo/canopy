# syntax=docker/dockerfile:1

# ---- Stage 1: Composer dependencies -----------------------------------
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

# ---- Stage 2: runtime ---------------------------------------------------
FROM php:8.3-fpm-bookworm AS runtime

# Bedrock/WordPress required extensions.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
      bcmath \
      exif \
      gd \
      intl \
      mysqli \
      opcache \
      redis \
      zip

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
      echo 'opcache.memory_consumption=192'; \
      echo 'opcache.max_accelerated_files=20000'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.interned_strings_buffer=16'; \
    } > "$PHP_INI_DIR/conf.d/opcache-recommended.ini"

# wp-cli, used for migrations, cron, and network/site management.
RUN curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

WORKDIR /app

COPY --from=vendor --chown=www-data:www-data /app /app

# The image ships no .env; Bedrock config is fed entirely by env vars from
# the pod's ConfigMap/Secret. Uploads go to GCS via WP-Stateless, so no
# writable content directory is needed — the root filesystem is expected to
# run read-only via the pod's securityContext (readOnlyRootFilesystem: true),
# with an emptyDir mounted at /tmp for PHP/OPcache scratch space.
USER www-data

EXPOSE 9000

CMD ["php-fpm"]
