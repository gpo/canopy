<?php

class RenderFormTest extends WP_UnitTestCase {

	private $fake_manifest = [
		'src/webComponents/qomonForm.js'     => [
			'file' => 'assets/qomonForm-abc123.js',
			'css'  => [ 'assets/qomonForm-abc123.css' ],
		],
		'src/webComponents/qomonPetition.js' => [
			'file' => 'assets/qomonPetition-def456.js',
			'css'  => [ 'assets/qomonPetition-def456.css' ],
		],
	];

	public function set_up() {
		parent::set_up();
		// Pre-populate a fresh cache so wpqomon_get_manifest returns without HTTP.
		$base_id    = 'render-test-form';
		$option_key = 'qomon_manifest_cache_' . md5( $base_id . 'cdn-form.qomon.org' );
		update_option(
			$option_key,
			[
				'manifest'   => $this->fake_manifest,
				'fetched_at' => time(),
			]
		);
	}

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		delete_option( 'qomon_registered_forms' );
		parent::tear_down();
	}

	public function test_renders_form_html() {
		$html = wpqomon_render_form( 'render-test-form' );

		$this->assertStringContainsString( 'class="qomon-form"', $html );
		$this->assertStringContainsString( 'data-base_id="render-test-form"', $html );
		$this->assertStringContainsString( 'data-style-link="https://cdn-form.qomon.org/render-test-form/assets/qomonForm-abc123.css"', $html );
	}

	public function test_renders_petition_variant() {
		$html = wpqomon_render_form( 'render-test-form', 'petition' );

		$this->assertStringContainsString( 'data-style-link="https://cdn-form.qomon.org/render-test-form/assets/qomonPetition-def456.css"', $html );
	}

	public function test_enqueues_script() {
		wpqomon_render_form( 'render-test-form' );

		$this->assertTrue( wp_script_is( 'qomon-form-render-test-form', 'enqueued' ) );
	}

	public function test_missing_manifest_returns_placeholder() {
		add_filter(
			'pre_http_request',
			function () {
				return new WP_Error( 'http_request_failed', 'CDN unreachable' );
			}
		);

		$html = wpqomon_render_form( 'nonexistent-form' );
		$this->assertStringContainsString( '<!-- Qomon: could not load manifest -->', $html );
	}

	public function test_missing_component_in_manifest_returns_placeholder() {
		$option_key = 'qomon_manifest_cache_' . md5( 'partial-manifest' . 'cdn-form.qomon.org' );
		update_option(
			$option_key,
			[
				'manifest'   => [ 'src/webComponents/otherComponent.js' => [] ],
				'fetched_at' => time(),
			]
		);

		$html = wpqomon_render_form( 'partial-manifest' );
		$this->assertStringContainsString( '<!-- Qomon: component not in manifest -->', $html );
	}

	public function test_registers_form_as_side_effect() {
		wpqomon_render_form( 'render-test-form' );

		$forms = get_option( 'qomon_registered_forms', [] );
		$key   = md5( 'render-test-form' . 'cdn-form.qomon.org' );
		$this->assertArrayHasKey( $key, $forms );
	}
}
