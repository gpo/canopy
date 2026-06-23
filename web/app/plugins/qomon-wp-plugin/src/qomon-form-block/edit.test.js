import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import userEvent from '@testing-library/user-event';

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

jest.mock( '@wordpress/element', () => ( {
	...jest.requireActual( 'react' ),
} ) );

jest.mock( '@wordpress/components', () => {
	const React = require( 'react' );
	return {
		TextControl: ( { label, value, onChange } ) =>
			React.createElement(
				'label',
				null,
				label,
				React.createElement( 'input', {
					type: 'text',
					value: value || '',
					onChange: ( e ) => onChange( e.target.value ),
				} )
			),
		RadioControl: ( { label, selected, options, onChange } ) =>
			React.createElement(
				'fieldset',
				null,
				React.createElement( 'legend', null, label ),
				options.map( ( opt ) =>
					React.createElement(
						'label',
						{ key: opt.value },
						React.createElement( 'input', {
							type: 'radio',
							value: opt.value,
							checked: selected === opt.value,
							onChange: () => onChange( opt.value ),
						} ),
						opt.label
					)
				)
			),
		Button: ( { children, variant, isDestructive, isBusy, ...props } ) =>
			React.createElement( 'button', props, children ),
	};
} );

jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: () => ( {} ),
	BlockControls: () => null,
} ) );

jest.mock( '@wordpress/api-fetch', () =>
	jest.fn( () => Promise.resolve( {} ) )
);

import Edit from './edit';

const defaultProps = {
	attributes: { base_id: '', form_type: '' },
	setAttributes: jest.fn(),
};

beforeEach( () => {
	defaultProps.setAttributes.mockClear();
} );

test( 'renders form ID text input', () => {
	render( <Edit { ...defaultProps } /> );
	expect( screen.getByLabelText( /qomon form id/i ) ).toBeInTheDocument();
} );

test( 'renders form type radio options', () => {
	render( <Edit { ...defaultProps } /> );
	expect( screen.getByLabelText( 'Form' ) ).toBeInTheDocument();
	expect( screen.getByLabelText( 'Petition' ) ).toBeInTheDocument();
} );

test( 'refresh button hidden when base_id is empty', () => {
	render( <Edit { ...defaultProps } /> );
	expect(
		screen.queryByRole( 'button', { name: /refresh cache/i } )
	).not.toBeInTheDocument();
} );

test( 'refresh button visible when base_id is set', () => {
	const props = {
		...defaultProps,
		attributes: { base_id: 'abc-123', form_type: '' },
	};
	render( <Edit { ...props } /> );
	expect(
		screen.getByRole( 'button', { name: /refresh cache/i } )
	).toBeInTheDocument();
} );

test( 'setAttributes called on form ID change', async () => {
	const user = userEvent.setup();
	render( <Edit { ...defaultProps } /> );

	const input = screen.getByLabelText( /qomon form id/i );
	await user.type( input, 'x' );

	expect( defaultProps.setAttributes ).toHaveBeenCalledWith(
		expect.objectContaining( { base_id: expect.any( String ) } )
	);
} );
