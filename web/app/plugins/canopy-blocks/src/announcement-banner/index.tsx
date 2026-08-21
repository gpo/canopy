import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';

import Edit, { type AnnouncementBannerAttributes } from './edit';
import metadata from './block.json';

registerBlockType(
	metadata as BlockConfiguration< AnnouncementBannerAttributes >,
	{
		edit: Edit,
		save: () => null,
	}
);
