<?php

class ShortcodeTest extends WP_UnitTestCase {

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

		$option_key = 'qomon_manifest_cache_' . md5( 'shortcode-test' . 'cdn-form.qomon.org' );
		update_option(
			$option_key,
			[
				'manifest'   => $this->fake_manifest,
				'fetched_at' => time(),
			]
		);

		// Prevent real HTTP requests.
		add_filter(
			'pre_http_request',
			function () {
				return new WP_Error( 'http_request_failed', 'Blocked in test' );
			}
		);
	}

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		delete_option( 'qomon_registered_forms' );
		parent::tear_down();
	}

	public function test_valid_shortcode_renders_form() {
		$html = do_shortcode( '[qomon-form id=shortcode-test]' );

		$this->assertStringContainsString( 'class="qomon-form"', $html );
		$this->assertStringContainsString( 'data-base_id="shortcode-test"', $html );
	}

	public function test_missing_id_returns_empty() {
		$html = do_shortcode( '[qomon-form]' );
		$this->assertEmpty( $html );
	}

	public function test_petition_type_attribute() {
		$html = do_shortcode( '[qomon-form id=shortcode-test type=petition]' );

		$this->assertStringContainsString( 'qomonPetition-def456.css', $html );
	}
}
