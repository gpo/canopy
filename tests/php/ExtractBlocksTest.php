<?php

class ExtractBlocksTest extends WP_UnitTestCase {

	public function test_extracts_flat_block() {
		$blocks = [
			[
				'blockName'   => 'create-block/qomon-form',
				'attrs'       => [ 'base_id' => 'abc-123' ],
				'innerBlocks' => [],
			],
		];

		$result = wpqomon_extract_blocks( $blocks );
		$this->assertCount( 1, $result );
		$this->assertSame( 'abc-123', $result[0]['base_id'] );
	}

	public function test_extracts_nested_block() {
		$blocks = [
			[
				'blockName'   => 'core/group',
				'attrs'       => [],
				'innerBlocks' => [
					[
						'blockName'   => 'create-block/qomon-form',
						'attrs'       => [ 'base_id' => 'nested-form' ],
						'innerBlocks' => [],
					],
				],
			],
		];

		$result = wpqomon_extract_blocks( $blocks );
		$this->assertCount( 1, $result );
		$this->assertSame( 'nested-form', $result[0]['base_id'] );
	}

	public function test_returns_empty_for_no_matches() {
		$blocks = [
			[
				'blockName'   => 'core/paragraph',
				'attrs'       => [],
				'innerBlocks' => [],
			],
		];

		$result = wpqomon_extract_blocks( $blocks );
		$this->assertEmpty( $result );
	}

	public function test_extracts_deeply_nested_blocks() {
		$blocks = [
			[
				'blockName'   => 'core/group',
				'attrs'       => [],
				'innerBlocks' => [
					[
						'blockName'   => 'core/columns',
						'attrs'       => [],
						'innerBlocks' => [
							[
								'blockName'   => 'create-block/qomon-form',
								'attrs'       => [ 'base_id' => 'deep-form' ],
								'innerBlocks' => [],
							],
						],
					],
				],
			],
		];

		$result = wpqomon_extract_blocks( $blocks );
		$this->assertCount( 1, $result );
		$this->assertSame( 'deep-form', $result[0]['base_id'] );
	}

	public function test_extracts_multiple_blocks_at_different_levels() {
		$blocks = [
			[
				'blockName'   => 'create-block/qomon-form',
				'attrs'       => [ 'base_id' => 'top-level' ],
				'innerBlocks' => [],
			],
			[
				'blockName'   => 'core/group',
				'attrs'       => [],
				'innerBlocks' => [
					[
						'blockName'   => 'create-block/qomon-form',
						'attrs'       => [ 'base_id' => 'nested' ],
						'innerBlocks' => [],
					],
				],
			],
		];

		$result = wpqomon_extract_blocks( $blocks );
		$this->assertCount( 2, $result );
		$this->assertSame( 'top-level', $result[0]['base_id'] );
		$this->assertSame( 'nested', $result[1]['base_id'] );
	}
}
