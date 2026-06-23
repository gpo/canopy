<?php

class CronTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		wp_clear_scheduled_hook( 'qomon_refresh_manifests' );
	}

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		wp_clear_scheduled_hook( 'qomon_refresh_manifests' );
		delete_option( 'qomon_registered_forms' );
		parent::tear_down();
	}

	public function test_activation_schedules_cron() {
		do_action( 'activate_qomon/qomon.php' );

		$this->assertNotFalse( wp_next_scheduled( 'qomon_refresh_manifests' ) );
	}

	public function test_deactivation_clears_cron() {
		wp_schedule_event( time(), 'hourly', 'qomon_refresh_manifests' );
		$this->assertNotFalse( wp_next_scheduled( 'qomon_refresh_manifests' ) );

		do_action( 'deactivate_qomon/qomon.php' );

		$this->assertFalse( wp_next_scheduled( 'qomon_refresh_manifests' ) );
	}

	public function test_cron_action_refreshes_manifests() {
		$base_id = 'cron-test-form';
		wpqomon_register_form( $base_id );

		add_filter(
			'pre_http_request',
			function () {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode(
						[
							'src/webComponents/qomonForm.js' => [
								'file' => 'assets/qomonForm-abc123.js',
								'css'  => [ 'assets/qomonForm-abc123.css' ],
							],
						]
					),
				];
			}
		);

		do_action( 'qomon_refresh_manifests' );

		$option_key = 'qomon_manifest_cache_' . md5( $base_id . 'cdn-form.qomon.org' );
		$cached     = get_option( $option_key );
		$this->assertIsArray( $cached );
		$this->assertArrayHasKey( 'manifest', $cached );
		$this->assertGreaterThan( time() - 5, $cached['fetched_at'] );
	}
}
