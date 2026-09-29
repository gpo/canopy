<?php

require_once dirname(__DIR__, 2).'/web/app/plugins/canopy-blocks/includes/announcement-banner.php';

use function Canopy\Blocks\render_announcement_banner;

beforeEach(function () {
    Brain\Monkey\setUp();

    Brain\Monkey\Functions\stubs([
        'esc_url' => fn ($url) => $url,
        'esc_html' => fn ($text) => htmlspecialchars($text, ENT_QUOTES),
        'wp_kses_post' => fn ($content) => $content,
    ]);
});

afterEach(function () {
    Brain\Monkey\tearDown();
});

test('renders nothing when disabled', function () {
    expect(render_announcement_banner([
        'enabled' => false,
        'message' => 'Hello',
    ]))->toBe('');
});

test('renders nothing when the message is blank', function () {
    expect(render_announcement_banner([
        'enabled' => true,
        'message' => '   ',
    ]))->toBe('');
});

test('renders the message when enabled', function () {
    $html = render_announcement_banner([
        'enabled' => true,
        'message' => 'Join us this weekend',
    ]);

    expect($html)
        ->toContain('wp-block-canopy-announcement-banner')
        ->toContain('Join us this weekend')
        ->not->toContain('<a ');
});

test('renders a link when link text and url are both set', function () {
    $html = render_announcement_banner([
        'enabled' => true,
        'message' => 'Join us this weekend',
        'linkText' => 'RSVP',
        'linkUrl' => 'https://example.org/rsvp',
    ]);

    expect($html)->toContain('<a href="https://example.org/rsvp">RSVP</a>');
});

test('omits the link when only the link text is set', function () {
    $html = render_announcement_banner([
        'enabled' => true,
        'message' => 'Join us this weekend',
        'linkText' => 'RSVP',
    ]);

    expect($html)->not->toContain('<a ');
});

test('omits the link when only the link url is set', function () {
    $html = render_announcement_banner([
        'enabled' => true,
        'message' => 'Join us this weekend',
        'linkUrl' => 'https://example.org/rsvp',
    ]);

    expect($html)->not->toContain('<a ');
});
