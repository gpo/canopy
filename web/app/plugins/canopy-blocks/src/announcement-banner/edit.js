( function ( blocks, element, blockEditor, components, i18n ) {
	var el = element.createElement;
	var RichText = blockEditor.RichText;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var ToggleControl = components.ToggleControl;
	var TextControl = components.TextControl;
	var __ = i18n.__;

	blocks.registerBlockType( 'canopy/announcement-banner', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return el(
				element.Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Banner Settings', 'canopy-blocks' ) },
						el( ToggleControl, {
							label: __( 'Show banner', 'canopy-blocks' ),
							checked: attributes.enabled,
							onChange: function ( value ) {
								setAttributes( { enabled: value } );
							},
						} ),
						el( TextControl, {
							label: __( 'Link text', 'canopy-blocks' ),
							value: attributes.linkText,
							onChange: function ( value ) {
								setAttributes( { linkText: value } );
							},
						} ),
						el( TextControl, {
							label: __( 'Link URL', 'canopy-blocks' ),
							value: attributes.linkUrl,
							onChange: function ( value ) {
								setAttributes( { linkUrl: value } );
							},
						} )
					)
				),
				el(
					'div',
					{ className: 'canopy-announcement-banner-editor' },
					el( RichText, {
						tagName: 'p',
						placeholder: __( 'Announcement message…', 'canopy-blocks' ),
						value: attributes.message,
						onChange: function ( value ) {
							setAttributes( { message: value } );
						},
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );
