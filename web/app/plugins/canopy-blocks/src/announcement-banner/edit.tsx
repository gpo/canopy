import { Fragment } from '@wordpress/element';
import { InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { BlockEditProps } from '@wordpress/blocks';

import type { AnnouncementBannerAttributes } from './attributes';

export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< AnnouncementBannerAttributes > ) {
	const { enabled, message, linkText, linkUrl } = attributes;

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody title={ __( 'Banner Settings', 'canopy-blocks' ) }>
					<ToggleControl
						label={ __( 'Show banner', 'canopy-blocks' ) }
						checked={ enabled }
						onChange={ ( value ) =>
							setAttributes( { enabled: value } )
						}
					/>
					<TextControl
						label={ __( 'Link text', 'canopy-blocks' ) }
						value={ linkText }
						onChange={ ( value ) =>
							setAttributes( { linkText: value } )
						}
					/>
					<TextControl
						label={ __( 'Link URL', 'canopy-blocks' ) }
						value={ linkUrl }
						onChange={ ( value ) =>
							setAttributes( { linkUrl: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div className="canopy-announcement-banner-editor">
				<RichText
					tagName="p"
					placeholder={ __(
						'Announcement message…',
						'canopy-blocks'
					) }
					value={ message }
					onChange={ ( value: string ) =>
						setAttributes( { message: value } )
					}
				/>
			</div>
		</Fragment>
	);
}
