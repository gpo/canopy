<?php
/**
 * Qomon
 *
 * @author            Qomon
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Qomon
 * Description:       Easily insert your Qomon form in your site. By adding a shortcode [qomon-form] or even by adding a custom block created by Qomon.
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           2.1.0
 * Author:            Qomon
 * Author URI:        https://qomon.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       qomon
 */


/**
 * Exit if plugin accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) exit;


// ---------------------------------------------------------------------------
// Manifest cache
// ---------------------------------------------------------------------------

/**
 * Returns the manifest for a given form, fetching from the CDN when needed.
 *
 * The manifest is stored in wp_options as { manifest: [...], fetched_at: int }.
 * Resolution order:
 *   1. Cached copy < 2h old  → return immediately.
 *   2. Live CDN fetch        → store + return on success.
 *   3. Stale copy < 4h old   → return with an error_log warning (CDN unreachable).
 *   4. No usable copy        → return false.
 */
if ( ! function_exists( 'wpqomon_get_manifest' ) ) {
	function wpqomon_get_manifest( $base_id, $bucket = 'cdn-form.qomon.org' ) {
		$option_key = 'qomon_manifest_cache_' . md5( $base_id . $bucket );
		$cached     = get_option( $option_key, null );
		$age        = $cached ? ( time() - $cached['fetched_at'] ) : PHP_INT_MAX;

		if ( $cached && $age < 2 * HOUR_IN_SECONDS ) {
			return $cached['manifest'];
		}

		$response = wp_remote_get( "https://{$bucket}/{$base_id}/manifest.json" );
		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$manifest = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $manifest ) ) {
				update_option( $option_key, [ 'manifest' => $manifest, 'fetched_at' => time() ], false );
				return $manifest;
			}
		}

		if ( $cached && $age < 4 * HOUR_IN_SECONDS ) {
			error_log( sprintf( 'Qomon: CDN fetch failed for %s, serving stale manifest (%d min old)', $base_id, round( $age / 60 ) ) );
			return $cached['manifest'];
		}

		return false;
	}
}


// ---------------------------------------------------------------------------
// Form registry
// ---------------------------------------------------------------------------

/**
 * Registers a form ID so the hourly cron can keep its manifest cached.
 */
if ( ! function_exists( 'wpqomon_register_form' ) ) {
	function wpqomon_register_form( $base_id, $form_type = '', $bucket = 'cdn-form.qomon.org' ) {
		$forms       = get_option( 'qomon_registered_forms', [] );
		$key         = md5( $base_id . $bucket );
		$forms[$key] = compact( 'base_id', 'form_type', 'bucket' );
		update_option( 'qomon_registered_forms', $forms, false );
	}
}

/**
 * Force-refreshes the manifest cache for every registered form.
 * Called by the hourly cron and the admin refresh action.
 */
if ( ! function_exists( 'wpqomon_refresh_manifests' ) ) {
	function wpqomon_refresh_manifests() {
		$forms = get_option( 'qomon_registered_forms', [] );
		foreach ( $forms as $form ) {
			delete_option( 'qomon_manifest_cache_' . md5( $form['base_id'] . $form['bucket'] ) );
			wpqomon_get_manifest( $form['base_id'], $form['bucket'] );
		}
	}
}


// ---------------------------------------------------------------------------
// save_post — register forms and prime the cache at publish time
// ---------------------------------------------------------------------------

if ( ! function_exists( 'wpqomon_extract_blocks' ) ) {
	function wpqomon_extract_blocks( array $blocks ) {
		$found = [];
		foreach ( $blocks as $block ) {
			if ( $block['blockName'] === 'create-block/qomon-form' ) {
				$found[] = $block['attrs'];
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = array_merge( $found, wpqomon_extract_blocks( $block['innerBlocks'] ) );
			}
		}
		return $found;
	}
}

add_action( 'save_post', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;

	$content = $post->post_content;
	$changed = false;

	if ( has_blocks( $content ) ) {
		foreach ( wpqomon_extract_blocks( parse_blocks( $content ) ) as $attrs ) {
			$base_id   = $attrs['base_id'] ?? '';
			$form_type = $attrs['form_type'] ?? '';
			$bucket    = ! empty( $attrs['env'] ) ? $attrs['env'] : 'cdn-form.qomon.org';
			if ( $base_id ) {
				wpqomon_register_form( $base_id, $form_type, $bucket );
				$changed = true;
			}
		}
	}

	preg_match_all( '/\[qomon-form\b[^\]]*\bid=["\']?([^\s"\'\/\]]+)/i', $content, $matches );
	foreach ( $matches[1] as $base_id ) {
		wpqomon_register_form( $base_id );
		$changed = true;
	}

	if ( $changed ) {
		wpqomon_refresh_manifests();
	}
}, 10, 2 );


// ---------------------------------------------------------------------------
// Hourly cron
// ---------------------------------------------------------------------------

register_activation_hook( __FILE__, function () {
	if ( ! wp_next_scheduled( 'qomon_refresh_manifests' ) ) {
		wp_schedule_event( time(), 'hourly', 'qomon_refresh_manifests' );
	}
} );

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'qomon_refresh_manifests' );
} );

add_action( 'qomon_refresh_manifests', 'wpqomon_refresh_manifests' );


// ---------------------------------------------------------------------------
// REST API — single-form cache refresh (used by the block editor button)
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', function () {
	register_rest_route( 'qomon/v1', '/refresh-manifest', [
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => function ( WP_REST_Request $request ) {
			$base_id = sanitize_text_field( $request->get_param( 'base_id' ) );
			$bucket  = sanitize_text_field( $request->get_param( 'bucket' ) ?: 'cdn-form.qomon.org' );

			delete_option( 'qomon_manifest_cache_' . md5( $base_id . $bucket ) );

			$manifest = wpqomon_get_manifest( $base_id, $bucket );
			if ( $manifest === false ) {
				return new WP_Error( 'fetch_failed', 'Could not fetch manifest from Qomon CDN', [ 'status' => 502 ] );
			}

			return rest_ensure_response( [ 'success' => true ] );
		},
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'args'                => [
			'base_id' => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'bucket'  => [ 'required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
		],
	] );
} );


// ---------------------------------------------------------------------------
// Frontend rendering
// ---------------------------------------------------------------------------

if ( ! function_exists( 'wpqomon_render_form' ) ) {
	function wpqomon_render_form( $base_id, $form_type = '', $bucket = 'cdn-form.qomon.org' ) {
		wpqomon_register_form( $base_id, $form_type, $bucket );

		$manifest = wpqomon_get_manifest( $base_id, $bucket );
		if ( ! $manifest ) return '<!-- Qomon: could not load manifest -->';

		$component    = $form_type === 'petition' ? 'qomonPetition' : 'qomonForm';
		$manifest_key = "src/webComponents/{$component}.js";
		if ( ! isset( $manifest[$manifest_key] ) ) return '<!-- Qomon: component not in manifest -->';

		$entry      = $manifest[$manifest_key];
		$script_url = "https://{$bucket}/{$base_id}/" . $entry['file'];

		$css_file  = is_array( $entry['css'] ) ? $entry['css'][0] : $entry['css'];
		$style_url = "https://{$bucket}/{$base_id}/" . $css_file;

		wp_enqueue_script( 'qomon-form-' . sanitize_key( $base_id ), $script_url, [], null, false );

		return '<div class="qomon-form" data-base_id="' . esc_attr( $base_id ) . '" data-style-link="' . esc_attr( $style_url ) . '"></div>';
	}
}

add_filter( 'script_loader_tag', function ( $tag, $handle ) {
	if ( strpos( $handle, 'qomon-form-' ) === 0 ) {
		return str_replace( '<script ', '<script type="module" ', $tag );
	}
	return $tag;
}, 10, 2 );


// ---------------------------------------------------------------------------
// Block registration
// ---------------------------------------------------------------------------

if ( ! function_exists( 'wpqomon_create_form_block' ) ) {
	function wpqomon_create_form_block() {
		register_block_type( __DIR__ . '/build', [
			'render_callback' => function ( $attributes ) {
				$base_id   = $attributes['base_id'] ?? '';
				$form_type = $attributes['form_type'] ?? '';
				$bucket    = ! empty( $attributes['env'] ) ? $attributes['env'] : 'cdn-form.qomon.org';
				if ( empty( $base_id ) ) return '';
				return wpqomon_render_form( $base_id, $form_type, $bucket );
			},
		] );
	}
}
add_action( 'init', 'wpqomon_create_form_block' );


// ---------------------------------------------------------------------------
// Admin
// ---------------------------------------------------------------------------

if ( is_admin() ) {

	if ( ! function_exists( 'wpqomon_load_translations' ) ) {
		function wpqomon_load_translations() {
			load_plugin_textdomain( 'qomon', '', 'qomon/languages/' );
		}
	}
	add_action( 'plugins_loaded', 'wpqomon_load_translations' );


	add_action( 'admin_post_wpqomon_refresh_all', function () {
		if ( ! current_user_can( 'edit_themes' ) || ! check_admin_referer( 'wpqomon_refresh_all' ) ) {
			wp_die( 'Unauthorized' );
		}
		wpqomon_refresh_manifests();
		wp_redirect( add_query_arg( [ 'page' => 'qomon-plugin', 'refreshed' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	} );


	if ( ! function_exists( 'wpqomon_admin_page_contents' ) ) {
		function wpqomon_admin_page_contents() {
			$forms        = get_option( 'qomon_registered_forms', [] );
			$form_count   = count( $forms );
			$just_refresh = isset( $_GET['refreshed'] ) && $_GET['refreshed'] === '1';

			echo '<article style="padding: 12px; max-width: 1000px;">';
			echo '<h1>';
			echo '<img style="width:40px; margin-right: 16px; vertical-align: middle;" src="' . esc_url( plugin_dir_url( __FILE__ ) . 'public/images/qomon-pink-shorted.svg' ) . '">';
			echo esc_html( __( 'Welcome to Qomon for WordPress', 'qomon' ) );
			echo '</h1>';

			if ( $just_refresh ) {
				echo '<div class="notice notice-success inline"><p>' . esc_html( sprintf( _n( 'Refreshed %d form manifest.', 'Refreshed %d form manifests.', $form_count, 'qomon' ), $form_count ) ) . '</p></div>';
			}

			echo '<h2 style="margin-top: 32px; font-size: 18px;">' . esc_html( __( 'Manifest Cache', 'qomon' ) ) . '</h2>';
			echo '<p>' . esc_html( sprintf( _n( '%d form registered.', '%d forms registered.', $form_count, 'qomon' ), $form_count ) ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="wpqomon_refresh_all">';
			wp_nonce_field( 'wpqomon_refresh_all' );
			echo '<button type="submit" class="button button-secondary">' . esc_html( __( 'Refresh all form caches', 'qomon' ) ) . '</button>';
			echo '</form>';

			echo '<h2 style="margin-top: 32px; font-size: 18px;">' . esc_html( __( 'Using the plugin', 'qomon' ) ) . '</h2>';
			echo '<p>' . esc_html( __( 'To add a Qomon form to your page you have 2 options: ', 'qomon' ) ) . '</p>';

			echo '<h3 style="margin-top: 24px;">' . esc_html( __( 'I. Adding through the Qomon Form Block', 'qomon' ) ) . '</h3>';
			echo '<p>' . esc_html( __( 'Once activated you will be able to add a form to your page using a Qomon Form Block: ', 'qomon' ) ) . '</p>';
			echo '<img style="width:244px" src="' . esc_url( plugin_dir_url( __FILE__ ) . 'public/images/qomon-form/block-search.png' ) . '">';
			echo '<p>' . esc_html( __( 'The block will appear, allowing you to add the id of your form to it:', 'qomon' ) ) . '</p>';
			echo '<img style="width:424px" src="' . esc_url( plugin_dir_url( __FILE__ ) . 'public/images/qomon-form/block.png' ) . '">';
			echo '<p>' . esc_html( __( 'The published or previewed page will display the corresponding form:', 'qomon' ) ) . '</p>';
			echo '<img style="width:424px" src="' . esc_url( plugin_dir_url( __FILE__ ) . 'public/images/qomon-form/form-example.png' ) . '">';

			echo '<h3 style="margin-top: 24px;">' . esc_html( __( 'II. Adding through the shortcode [qomon-form]', 'qomon' ) ) . '</h3>';
			echo '<p>' . esc_html( __( 'In the same way you can add a shortcode block:', 'qomon' ) ) . '</p>';
			echo '<img style="width:244px" src="' . esc_url( plugin_dir_url( __FILE__ ) . 'public/images/qomon-form/shortcode.png' ) . '">';
			echo '<p>' . esc_html( __( 'Once this block is on the page it will be necessary to write this code [qomon-form id=my-form-id] in the block, my-form-id will be to replace by the id of your form:', 'qomon' ) ) . '</p>';
			echo '<img style="width:424px" src="' . esc_url( plugin_dir_url( __FILE__ ) . 'public/images/qomon-form/shortcode-filled.png' ) . '">';
			echo '<p>' . esc_html( __( 'The published or previewed page will display the corresponding form:', 'qomon' ) ) . '</p>';
			echo '<img style="width:424px" src="' . esc_url( plugin_dir_url( __FILE__ ) . 'public/images/qomon-form/form-example.png' ) . '">';

			echo '<p>' . esc_html( __( 'To go further in the customization, or for any help concerning the plugin, you can consult ', 'qomon' ) );
			echo '<a href="' . esc_url( 'https://help.qomon.com/en/articles/7439238-how-can-i-integrate-a-qomon-form-on-my-website' ) . '" target="_blank">';
			echo esc_html( __( 'this page', 'qomon' ) ) . '</a>.</p>';

			echo '</article>';
		}
	}

	if ( ! function_exists( 'wpqomon_add_qomon_admin_menu' ) ) {
		function wpqomon_add_qomon_admin_menu() {
			add_menu_page(
				'Qomon Plugin',
				'Qomon',
				'edit_themes',
				'qomon-plugin',
				'wpqomon_admin_page_contents',
				'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" xml:space="preserve" width="20" height="auto" viewBox="0 0 512 512" style="fill:#a7aaad" role="img" aria-hidden="true" focusable="false"><path d="M255.292 0C112.719 0 0 113.196 0 255.304C0 385.598 93.7125 491.672 218.269 509.382V385.268C190.126 376.747 165.485 359.37 148.01 335.721C130.535 312.071 121.16 283.413 121.279 254.007C121.279 179.628 181.223 115.884 256 115.884C329.338 115.884 390.743 179.557 390.743 254.007C390.747 269.977 387.937 285.823 382.443 300.819L328.748 247.923L245.223 334.164L295.639 384.56L387.23 476.178L423.073 512L509.31 425.782L473.938 390.927C498.982 350.136 512.162 303.171 511.998 255.304C511.998 113.055 398.525 0 255.292 0Z"/></svg>' ),
				null
			);
		}
	}
	add_action( 'admin_menu', 'wpqomon_add_qomon_admin_menu' );

} else {

	if ( ! function_exists( 'wpqomon_add_form_shortcode' ) ) {
		function wpqomon_add_form_shortcode( $atts = [] ) {
			$atts = shortcode_atts( [ 'id' => '', 'type' => '' ], $atts, 'qomon-form' );
			if ( empty( $atts['id'] ) ) return '';
			return wpqomon_render_form( $atts['id'], $atts['type'] );
		}
	}
	add_shortcode( 'qomon-form', 'wpqomon_add_form_shortcode' );

}
