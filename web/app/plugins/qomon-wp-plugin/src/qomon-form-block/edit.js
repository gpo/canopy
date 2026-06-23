import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { TextControl, RadioControl, Button } from '@wordpress/components';
import { useBlockProps, BlockControls } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';

export default function Edit( { attributes, setAttributes } ) {
	const [ refreshState, setRefreshState ] = useState( 'idle' );

	const handleRefresh = () => {
		setRefreshState( 'loading' );
		apiFetch( {
			path: '/qomon/v1/refresh-manifest',
			method: 'POST',
			data: { base_id: attributes.base_id },
		} )
			.then( () => {
				setRefreshState( 'success' );
				setTimeout( () => setRefreshState( 'idle' ), 3000 );
			} )
			.catch( () => {
				setRefreshState( 'error' );
				setTimeout( () => setRefreshState( 'idle' ), 3000 );
			} );
	};

	const refreshLabel = {
		idle: __( 'Refresh cache', 'qomon' ),
		loading: __( 'Refreshing…', 'qomon' ),
		success: __( 'Cache refreshed', 'qomon' ),
		error: __( 'Refresh failed — check CDN', 'qomon' ),
	}[ refreshState ];

	return (
		<div { ...useBlockProps() }>
			{ <BlockControls /> }
			<TextControl
				label={ __( 'Qomon form ID', 'qomon' ) }
				value={ attributes.base_id }
				onChange={ ( val ) => setAttributes( { base_id: val } ) }
			/>
			<RadioControl
				label={ __( 'Form Type', 'qomon' ) }
				selected={ attributes.form_type }
				options={ [
					{ label: 'Form', value: '' },
					{ label: 'Petition', value: 'petition' },
				] }
				onChange={ ( val ) => setAttributes( { form_type: val } ) }
			/>
			{ attributes.base_id && (
				<Button
					variant="secondary"
					onClick={ handleRefresh }
					disabled={ refreshState === 'loading' }
					isDestructive={ refreshState === 'error' }
				>
					{ refreshLabel }
				</Button>
			) }
		</div>
	);
}
