<?php

class RestApiTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// Ensure REST routes are registered.
		do_action( 'rest_api_init' );

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
	}

	public function tear_down() {
		remove_all_filters( 'pre_http_request' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_unauthenticated_request_denied() {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/qomon/v1/refresh-manifest' );
		$request->set_param( 'base_id', 'test-form' );
		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_subscriber_denied() {
		$user_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST', '/qomon/v1/refresh-manifest' );
		$request->set_param( 'base_id', 'test-form' );
		$response = rest_do_request( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_editor_can_refresh() {
		$user_id = $this->factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST', '/qomon/v1/refresh-manifest' );
		$request->set_param( 'base_id', 'test-form' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	public function test_cdn_failure_returns_502() {
		$user_id = $this->factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $user_id );

		// Override CDN mock to fail.
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			function () {
				return new WP_Error( 'http_request_failed', 'CDN unreachable' );
			}
		);

		$request = new WP_REST_Request( 'POST', '/qomon/v1/refresh-manifest' );
		$request->set_param( 'base_id', 'test-form' );
		$response = rest_do_request( $request );

		$this->assertSame( 502, $response->get_status() );
	}

	public function test_missing_base_id_returns_error() {
		$user_id = $this->factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'POST', '/qomon/v1/refresh-manifest' );
		$response = rest_do_request( $request );

		$this->assertTrue( $response->get_status() >= 400 );
	}
}
