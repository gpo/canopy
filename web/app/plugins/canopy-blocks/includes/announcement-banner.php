<?php

namespace Canopy\Blocks;

function render_announcement_banner(array $attributes): string
{
    $enabled = ! empty($attributes['enabled']);
    $message = trim((string) ($attributes['message'] ?? ''));

    if (! $enabled || $message === '') {
        return '';
    }

    $link_text = trim((string) ($attributes['linkText'] ?? ''));
    $link_url = trim((string) ($attributes['linkUrl'] ?? ''));

    $link_html = '';
    if ($link_text !== '' && $link_url !== '') {
        $link_html = sprintf(
            ' <a href="%s">%s</a>',
            esc_url($link_url),
            esc_html($link_text)
        );
    }

    return sprintf(
        '<div class="wp-block-canopy-announcement-banner"><p>%s%s</p></div>',
        wp_kses_post($message),
        $link_html
    );
}
