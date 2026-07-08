# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.4
ARG WP_CLI_VERSION=2.11.0

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
FROM php:${PHP_VERSION}-fpm-bookworm AS runtime
ARG WP_CLI_VERSION

# Bedrock/WordPress required extensions.
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

# Pinned wp-cli release (not the gh-pages "latest" build) so the image is
# reproducible from its tag alone.
RUN curl -fsSL -o /usr/local/bin/wp \
      "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar" \
    && chmod +x /usr/local/bin/wp

# Append (not replace) the stock pool config, so pool sizing (pm.max_children
# etc.) stays whatever upstream ships until we have real GKE Autopilot
# resource requests and load data to size it against — a rebuild-required
# guess now is worse than upstream's neutral default. Only the two things
# that are actually load-bearing in a container get added:
#   - clear_env=no: FPM's default (clear_env=yes) strips inherited env vars
#     from workers, which would silently break every env()-driven
#     Config::define() call once config comes from a k8s ConfigMap/Secret.
#   - worker output routed to stderr, so PHP warnings/var_dump reach
#     container logs instead of being discarded.
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

# The image ships no .env; Bedrock config is fed entirely by env vars from
# the pod's ConfigMap/Secret. Uploads go to GCS via WP-Stateless, so no
# writable content directory is needed — the root filesystem is expected to
# run read-only via the pod's securityContext (readOnlyRootFilesystem: true),
# with an emptyDir mounted at /tmp for PHP/OPcache scratch space.
USER www-data

EXPOSE 9000

# No Docker HEALTHCHECK here: this container only speaks FastCGI on 9000, so
# there's no cheap HTTP probe to run. Liveness/readiness are handled by the
# k8s probes hitting the nginx sidecar's /healthz, which proxies to this
# container over FastCGI and so exercises both.
CMD ["php-fpm"]
