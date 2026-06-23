<?php

class ManifestCacheTest extends WP_UnitTestCase {

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

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		parent::tear_down();
	}

	private function option_key( $base_id, $bucket = 'cdn-form.qomon.org' ) {
		return 'qomon_manifest_cache_' . md5( $base_id . $bucket );
	}

	private function mock_cdn_success() {
		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( $this->fake_manifest ),
				];
			}
		);
	}

	private function mock_cdn_failure() {
		add_filter(
			'pre_http_request',
			function () {
				return new WP_Error( 'http_request_failed', 'CDN unreachable' );
			}
		);
	}

	public function test_fresh_cache_hit_returns_cached_manifest() {
		$base_id = 'test-form-fresh';
		update_option(
			$this->option_key( $base_id ),
			[
				'manifest'   => $this->fake_manifest,
				'fetched_at' => time() - 60,
			]
		);

		// CDN should not be called.
		add_filter(
			'pre_http_request',
			function () {
				$this->fail( 'CDN should not be called for a fresh cache hit.' );
			}
		);

		$result = wpqomon_get_manifest( $base_id );
		$this->assertSame( $this->fake_manifest, $result );
	}

	public function test_cache_miss_fetches_from_cdn() {
		$base_id = 'test-form-miss';
		$this->mock_cdn_success();

		$result = wpqomon_get_manifest( $base_id );

		$this->assertSame( $this->fake_manifest, $result );

		$cached = get_option( $this->option_key( $base_id ) );
		$this->assertSame( $this->fake_manifest, $cached['manifest'] );
		$this->assertGreaterThan( time() - 5, $cached['fetched_at'] );
	}

	public function test_stale_cache_served_when_cdn_fails() {
		$base_id = 'test-form-stale';
		update_option(
			$this->option_key( $base_id ),
			[
				'manifest'   => $this->fake_manifest,
				'fetched_at' => time() - ( 3 * HOUR_IN_SECONDS ),
			]
		);

		$this->mock_cdn_failure();

		$result = wpqomon_get_manifest( $base_id );
		$this->assertSame( $this->fake_manifest, $result );
	}

	public function test_expired_cache_returns_false_when_cdn_fails() {
		$base_id = 'test-form-expired';
		update_option(
			$this->option_key( $base_id ),
			[
				'manifest'   => $this->fake_manifest,
				'fetched_at' => time() - ( 5 * HOUR_IN_SECONDS ),
			]
		);

		$this->mock_cdn_failure();

		$result = wpqomon_get_manifest( $base_id );
		$this->assertFalse( $result );
	}

	public function test_invalid_json_from_cdn_falls_through() {
		$base_id = 'test-form-bad-json';

		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => 'not valid json',
				];
			}
		);

		$result = wpqomon_get_manifest( $base_id );
		$this->assertFalse( $result );
	}

	public function test_option_key_uses_base_id_and_bucket_hash() {
		$key_a = $this->option_key( 'form-a', 'cdn-form.qomon.org' );
		$key_b = $this->option_key( 'form-b', 'cdn-form.qomon.org' );
		$key_c = $this->option_key( 'form-a', 'custom-bucket.example.com' );

		$this->assertNotEquals( $key_a, $key_b );
		$this->assertNotEquals( $key_a, $key_c );
	}
}
