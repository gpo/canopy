import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';

import Edit from './edit';
import metadata from './block.json';
import type { AnnouncementBannerAttributes } from './attributes';

registerBlockType(
	metadata as BlockConfiguration< AnnouncementBannerAttributes >,
	{
		edit: Edit,
		save: () => null,
	}
);
