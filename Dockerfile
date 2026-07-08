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

# Pinned release, not the gh-pages "latest" build, so the image is
# reproducible from its tag alone.
RUN curl -fsSL -o /usr/local/bin/wp \
      "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar" \
    && chmod +x /usr/local/bin/wp

# Append to (not replace) the stock pool config, so pool sizing like
# pm.max_children stays at upstream's default until real GKE Autopilot
# resource/load data exists to size it against. clear_env=no is required —
# FPM's default strips inherited env vars from workers, which would break
# every env()-driven Config::define() call once config comes from a k8s
# ConfigMap/Secret.
RUN { \
      echo ''; \
      echo 'clear_env = no'; \
      echo 'catch_workers_output = yes'; \
      echo 'decorate_workers_output = no'; \
      echo 'php_admin_flag[log_errors] = on'; \
      echo 'php_admin_value[error_log] = /proc/self/fd/2'; \
    } >> /usr/local/etc/php-fpm.d/www.conf

WORKDIR /app

COPY --from=vendor --chown=www-data:www-data /app /app

# No .env is shipped — Bedrock config comes entirely from the pod's
# ConfigMap/Secret, and the root filesystem is expected to run read-only via
# securityContext (readOnlyRootFilesystem: true) with an emptyDir at /tmp.
USER www-data

EXPOSE 9000

CMD ["php-fpm"]
