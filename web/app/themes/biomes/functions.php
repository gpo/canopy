<?php

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'biomes',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get('Version')
    );
});

add_action('after_setup_theme', function () {
    remove_theme_support('core-block-patterns');
});
