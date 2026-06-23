<?php

class FormRegistryTest extends WP_UnitTestCase {

	public function tear_down() {
		delete_option( 'qomon_registered_forms' );
		parent::tear_down();
	}

	public function test_registers_a_new_form() {
		wpqomon_register_form( 'form-abc' );

		$forms = get_option( 'qomon_registered_forms', [] );
		$key   = md5( 'form-abc' . 'cdn-form.qomon.org' );

		$this->assertArrayHasKey( $key, $forms );
		$this->assertSame( 'form-abc', $forms[ $key ]['base_id'] );
		$this->assertSame( 'cdn-form.qomon.org', $forms[ $key ]['bucket'] );
	}

	public function test_idempotent_registration() {
		wpqomon_register_form( 'form-abc' );
		wpqomon_register_form( 'form-abc' );

		$forms = get_option( 'qomon_registered_forms', [] );
		$this->assertCount( 1, $forms );
	}

	public function test_registers_multiple_forms() {
		wpqomon_register_form( 'form-one' );
		wpqomon_register_form( 'form-two' );

		$forms = get_option( 'qomon_registered_forms', [] );
		$this->assertCount( 2, $forms );
	}

	public function test_custom_bucket_uses_different_key() {
		wpqomon_register_form( 'form-abc', '', 'custom-bucket.example.com' );

		$forms = get_option( 'qomon_registered_forms', [] );
		$key   = md5( 'form-abc' . 'custom-bucket.example.com' );

		$this->assertArrayHasKey( $key, $forms );
		$this->assertSame( 'custom-bucket.example.com', $forms[ $key ]['bucket'] );
	}
}
