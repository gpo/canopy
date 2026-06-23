<?php

class ScriptLoaderTagTest extends WP_UnitTestCase {

	public function test_qomon_handle_gets_type_module() {
		$tag = '<script src="https://cdn-form.qomon.org/test/assets/qomonForm-abc123.js"></script>';

		$filtered = apply_filters( 'script_loader_tag', $tag, 'qomon-form-test-form', 'https://cdn-form.qomon.org/test/assets/qomonForm-abc123.js' );

		$this->assertStringContainsString( 'type="module"', $filtered );
	}

	public function test_non_qomon_handle_unchanged() {
		$tag = '<script src="https://example.com/jquery.js"></script>';

		$filtered = apply_filters( 'script_loader_tag', $tag, 'jquery-core', 'https://example.com/jquery.js' );

		$this->assertStringNotContainsString( 'type="module"', $filtered );
		$this->assertSame( $tag, $filtered );
	}
}
