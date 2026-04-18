<?php

// Custom menu order
function custom_menu_order($menu_ord) {
    if (!$menu_ord) return true;
    return array(
        'index.php', // Dashboard
        'edit.php?post_type=page', // Pages
        'edit.php', // Posts
        'upload.php', // Media
        //'site-options', // ACF Site Options
        'plugins.php', // Plugins
        'users.php', // Users
        'tools.php', // Tools
        'options-general.php', // Settings
        //'separator-last', // Last separator
    );
}
add_filter('custom_menu_order', 'custom_menu_order');
add_filter('menu_order', 'custom_menu_order');

// Make ACF fields readonly
function readonly_field($field) {
    $field['readonly'] = '1';
    return $field;
}
add_filter('acf/load_field/name=user_ratings_counter', 'readonly_field');
add_filter('acf/load_field/name=user_ratings_sum', 'readonly_field');
add_filter('acf/load_field/name=user_ratings_last_updated', 'readonly_field');
add_filter('acf/load_field/name=user_ratings_last_rating', 'readonly_field');

// ACF JSON sync
function acf_json_save_point($path) {
    $path = get_template_directory() . '/acf-json';
    return $path;
}
add_filter('acf/settings/save_json', 'acf_json_save_point');

function acf_json_load_point($paths) {
    unset($paths[0]);
    $paths[] = get_template_directory() . '/acf-json';
    return $paths;
}
add_filter('acf/settings/load_json', 'acf_json_load_point');

// Disable live preview
add_action('customize_preview_init', function () {
    die("<h2>⚠️ The customizer is disabled. Please save and preview your site on the frontend ⚠️</h2>");
}, 1);

// Remove default dashboard widgets
function remove_dashboard_widgets() {
    global $wp_meta_boxes;
    unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']);
    unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_comments']);
    unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_incoming_links']);
    unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_plugins']);
    unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']);
    unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_secondary']);
    unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press']);
    unset($wp_meta_boxes['dashboard']['normal']['core']['yoast_db_widget']);
}
add_action('wp_dashboard_setup', 'remove_dashboard_widgets');

// Add custom dashboard widget
function my_custom_dashboard_widgets() {
    wp_add_dashboard_widget('cb_notifications', 'Notifications', 'cb_notifications_dashboard_widget');
}
add_action('wp_dashboard_setup', 'my_custom_dashboard_widgets');


function add_featured_image_support() {
    add_theme_support('post-thumbnails', apply_filters('pagelines_post-thumbnails', array('post')));
}
add_action('after_setup_theme', 'add_featured_image_support');

function maps_fbh_get_or_create_homepage_id() {
    $front_page_id = (int) get_option('page_on_front');

    if ($front_page_id && get_post($front_page_id)) {
        return $front_page_id;
    }

    $homepage = get_page_by_path('homepage', OBJECT, 'page');

    if (!$homepage) {
        $homepage_id = wp_insert_post(array(
            'post_title' => 'Homepage',
            'post_name' => 'homepage',
            'post_type' => 'page',
            'post_status' => 'publish',
        ));
    } else {
        $homepage_id = $homepage->ID;
    }

    if ($homepage_id && !is_wp_error($homepage_id)) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $homepage_id);

        return (int) $homepage_id;
    }

    return 0;
}

function maps_fbh_add_homepage_admin_menu() {
    add_menu_page(
        'Homepage',
        'Homepage',
        'edit_pages',
        'maps-fbh-homepage',
        'maps_fbh_redirect_to_homepage_editor',
        'dashicons-admin-home',
        21
    );
}
add_action('admin_menu', 'maps_fbh_add_homepage_admin_menu');

function maps_fbh_redirect_to_homepage_editor() {
    $homepage_id = maps_fbh_get_or_create_homepage_id();

    if ($homepage_id) {
        wp_safe_redirect(admin_url('post.php?post=' . $homepage_id . '&action=edit'));
        exit;
    }

    wp_die('Unable to create or locate the Homepage page.');
}
