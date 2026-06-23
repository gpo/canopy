<?php

class SavePostHookTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		delete_option( 'qomon_registered_forms' );

		// Mock CDN so cache priming succeeds without real HTTP.
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
		delete_option( 'qomon_registered_forms' );
		parent::tear_down();
	}

	public function test_block_content_registers_form() {
		$content = '<!-- wp:create-block/qomon-form {"base_id":"block-form-id","form_type":"","env":""} /-->';

		$this->factory()->post->create( [ 'post_content' => $content ] );

		$forms = get_option( 'qomon_registered_forms', [] );
		$key   = md5( 'block-form-id' . 'cdn-form.qomon.org' );
		$this->assertArrayHasKey( $key, $forms );
	}

	public function test_shortcode_content_registers_form() {
		$content = '[qomon-form id=shortcode-form-id]';

		$this->factory()->post->create( [ 'post_content' => $content ] );

		$forms = get_option( 'qomon_registered_forms', [] );
		$key   = md5( 'shortcode-form-id' . 'cdn-form.qomon.org' );
		$this->assertArrayHasKey( $key, $forms );
	}

	public function test_revisions_are_ignored() {
		$post_id = $this->factory()->post->create( [ 'post_content' => '' ] );
		delete_option( 'qomon_registered_forms' );

		wp_save_post_revision( $post_id );

		$forms = get_option( 'qomon_registered_forms', [] );
		$this->assertEmpty( $forms );
	}

	public function test_mixed_content_registers_both() {
		$content  = '<!-- wp:create-block/qomon-form {"base_id":"block-id","form_type":""} /-->';
		$content .= "\n[qomon-form id=shortcode-id]";

		$this->factory()->post->create( [ 'post_content' => $content ] );

		$forms = get_option( 'qomon_registered_forms', [] );
		$this->assertCount( 2, $forms );
	}
}
