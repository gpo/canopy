<?php

/**
 * Plugin Name: Admins Can Create Subsites
 */
add_filter('user_has_cap', function (array $allcaps, array $caps, array $args, WP_User $user) {
    if (in_array('create_sites', $caps, true) && user_can($user, 'administrator')) {
        $allcaps['create_sites'] = true;
    }

    return $allcaps;
}, 10, 4);
