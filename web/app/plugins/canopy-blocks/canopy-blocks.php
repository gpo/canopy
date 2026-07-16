<?php

/**
 * Plugin Name: Canopy Blocks
 * Description: Custom Gutenberg blocks shared across the Canopy network.
 * Version: 0.1.0
 * License: GNU Affero General Public License v3.0
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 * Text Domain: canopy-blocks
 */

namespace Canopy\Blocks;

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__.'/includes/announcement-banner.php';

add_action('init', function () {
    wp_register_script(
        'canopy-blocks-announcement-banner-editor',
        plugins_url('src/announcement-banner/edit.js', __FILE__),
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        filemtime(__DIR__.'/src/announcement-banner/edit.js')
    );

    register_block_type(__DIR__.'/src/announcement-banner', [
        'editor_script' => 'canopy-blocks-announcement-banner-editor',
    ]);
});
