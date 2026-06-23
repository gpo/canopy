import { registerBlockType } from '@wordpress/blocks';

jest.mock( '@wordpress/blocks', () => ( {
	registerBlockType: jest.fn(),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

jest.mock( '@wordpress/element', () => ( {
	...jest.requireActual( 'react' ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	TextControl: () => null,
	RadioControl: () => null,
	Button: () => null,
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: () => ( {} ),
	BlockControls: () => null,
} ) );

jest.mock( '@wordpress/api-fetch', () => jest.fn() );

// Import triggers the side-effect registration.
require( './index' );

test( 'registers the qomon-form block', () => {
	expect( registerBlockType ).toHaveBeenCalledWith(
		'create-block/qomon-form',
		expect.objectContaining( {
			edit: expect.any( Function ),
			save: expect.any( Function ),
		} )
	);
} );
