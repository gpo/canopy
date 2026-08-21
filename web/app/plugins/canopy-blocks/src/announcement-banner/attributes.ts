export interface AnnouncementBannerAttributes
	extends Record< string, unknown > {
	enabled: boolean;
	message: string;
	linkText: string;
	linkUrl: string;
}
