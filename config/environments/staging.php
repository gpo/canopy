<?php

/**
 * Configuration overrides for WP_ENV === 'staging'
 */

use Roots\WPConfig\Config;

use function Env\env;

/**
 * You should try to keep staging as close to production as possible. However,
 * should you need to, you can always override production configuration values
 * with `Config::define`.
 *
 * Example: `Config::define('WP_DEBUG', true);`
 * Example: `Config::define('DISALLOW_FILE_MODS', false);`
 */
Config::define('DISALLOW_INDEXING', true);

/**
 * WP-Stateless media offload to GCS. The service account key is mounted from
 * Secret Manager; pods have no writable local uploads directory.
 */
Config::define('WP_STATELESS_MEDIA_BUCKET', env('WP_STATELESS_MEDIA_BUCKET'));
Config::define('WP_STATELESS_MEDIA_KEY_FILE_PATH', env('WP_STATELESS_MEDIA_KEY_FILE_PATH'));
Config::define('WP_STATELESS_MEDIA_MODE', env('WP_STATELESS_MEDIA_MODE') ?: 'stateless');
