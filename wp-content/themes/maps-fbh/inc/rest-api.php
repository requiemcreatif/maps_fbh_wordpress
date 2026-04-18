<?php
ini_set('error_reporting', E_STRICT);
ini_set('memory_limit', -1);

require_once __DIR__ . '/../helpers/contents.php';

function maps_fbh_get_acf_value($post_id, $field_name) {
    if (function_exists('get_field')) {
        return get_field($field_name, $post_id);
    }

    return get_post_meta($post_id, $field_name, true);
}

function maps_fbh_normalize_image($image_field) {
    if (empty($image_field)) {
        return null;
    }

    if (is_array($image_field)) {
        return array(
            'id' => isset($image_field['ID']) ? (int) $image_field['ID'] : null,
            'alt' => $image_field['alt'] ?? '',
            'width' => isset($image_field['width']) ? (int) $image_field['width'] : null,
            'height' => isset($image_field['height']) ? (int) $image_field['height'] : null,
            'url' => $image_field['url'] ?? '',
            'sizes' => $image_field['sizes'] ?? array(),
        );
    }

    $attachment_id = is_numeric($image_field) ? (int) $image_field : 0;
    if (!$attachment_id) {
        return null;
    }

    return array(
        'id' => $attachment_id,
        'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
        'width' => null,
        'height' => null,
        'url' => wp_get_attachment_url($attachment_id),
        'sizes' => array(),
    );
}

function maps_fbh_map_product($post) {
    $post_id = $post->ID;
    $image = maps_fbh_normalize_image(maps_fbh_get_acf_value($post_id, 'production_image'));

    return array(
        'id' => $post_id,
        'slug' => $post->post_name,
        'title' => maps_fbh_get_acf_value($post_id, 'product_title') ?: get_the_title($post_id),
        'description' => maps_fbh_get_acf_value($post_id, 'product_description') ?: wp_strip_all_tags(get_the_excerpt($post_id)),
        'content' => apply_filters('the_content', $post->post_content),
        'excerpt' => get_the_excerpt($post_id),
        'featured_image' => $image,
        'link' => get_permalink($post_id),
    );
}

function maps_fbh_map_service($post) {
    $post_id = $post->ID;

    return array(
        'id' => $post_id,
        'slug' => $post->post_name,
        'title' => maps_fbh_get_acf_value($post_id, 'service_title') ?: get_the_title($post_id),
        'description' => maps_fbh_get_acf_value($post_id, 'service_description') ?: wp_strip_all_tags(get_the_excerpt($post_id)),
        'time' => maps_fbh_get_acf_value($post_id, 'service_time'),
        'details' => maps_fbh_get_acf_value($post_id, 'service_details') ?: '',
        'content' => apply_filters('the_content', $post->post_content),
        'excerpt' => get_the_excerpt($post_id),
        'link' => get_permalink($post_id),
    );
}

function maps_fbh_get_homepage_post() {
    $front_page_id = (int) get_option('page_on_front');

    if ($front_page_id) {
        $post = get_post($front_page_id);

        if ($post && $post->post_type === 'page') {
            return $post;
        }
    }

    $post = get_page_by_path('homepage', OBJECT, 'page');

    if ($post && $post->post_type === 'page') {
        return $post;
    }

    return null;
}

function maps_fbh_get_legacy_section_post($slug) {
    return get_page_by_path($slug, OBJECT, 'site_section');
}

function maps_fbh_get_acf_value_with_legacy($post_id, $field_name, $legacy_post = null) {
    $value = maps_fbh_get_acf_value($post_id, $field_name);

    if (($value === null || $value === '' || $value === false) && $legacy_post) {
        return maps_fbh_get_acf_value($legacy_post->ID, $field_name);
    }

    return $value;
}

function maps_fbh_get_about_section_data() {
    $post = maps_fbh_get_homepage_post();
    $legacy_post = maps_fbh_get_legacy_section_post('about-section');

    if (!$legacy_post) {
        $legacy_posts = get_posts(array(
            'post_type' => 'site_section',
            'post_status' => 'publish',
            'numberposts' => 1,
            'orderby' => 'date',
            'order' => 'ASC',
        ));

        $legacy_post = !empty($legacy_posts) ? $legacy_posts[0] : null;
    }

    if (!$post && $legacy_post) {
        $post = $legacy_post;
    }

    if (!$post) {
        return array(
            'id' => null,
            'slug' => 'homepage',
            'configured' => false,
            'section_label' => '',
            'title' => '',
            'intro' => '',
            'body' => '',
            'cta_label' => '',
            'image' => null,
        );
    }

    $post_id = $post->ID;
    $value_post = $post->post_type === 'page' ? $post : null;
    $fallback_post = $value_post ? $legacy_post : null;

    return array(
        'id' => $post_id,
        'slug' => $post->post_name,
        'configured' => true,
        'section_label' => maps_fbh_get_acf_value_with_legacy($post_id, 'about_section_label', $fallback_post) ?: maps_fbh_get_acf_value_with_legacy($post_id, 'about_eyebrow', $fallback_post) ?: 'À propos',
        'title' => maps_fbh_get_acf_value_with_legacy($post_id, 'about_title', $fallback_post) ?: get_the_title($post_id),
        'intro' => maps_fbh_get_acf_value_with_legacy($post_id, 'about_intro', $fallback_post) ?: '',
        'body' => maps_fbh_get_acf_value_with_legacy($post_id, 'about_body', $fallback_post) ?: '',
        'cta_label' => maps_fbh_get_acf_value_with_legacy($post_id, 'about_cta_label', $fallback_post) ?: 'En savoir plus',
        'image' => maps_fbh_normalize_image(maps_fbh_get_acf_value_with_legacy($post_id, 'about_image', $fallback_post)),
    );
}

function maps_fbh_get_content_items($post_type, $mapper) {
    $posts = get_posts(array(
        'post_type' => $post_type,
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
    ));

    return array_map($mapper, $posts);
}

function maps_fbh_map_hero_slide($post_id, $slide_number, $legacy_post = null) {
    return array(
        'title' => maps_fbh_get_acf_value_with_legacy($post_id, 'hero_slide_' . $slide_number . '_title', $legacy_post) ?: '',
        'subtitle' => maps_fbh_get_acf_value_with_legacy($post_id, 'hero_slide_' . $slide_number . '_subtitle', $legacy_post) ?: '',
        'image' => maps_fbh_normalize_image(maps_fbh_get_acf_value_with_legacy($post_id, 'hero_slide_' . $slide_number . '_image', $legacy_post)),
    );
}

function maps_fbh_get_hero_section_data() {
    $post = maps_fbh_get_homepage_post();
    $legacy_post = maps_fbh_get_legacy_section_post('hero-section');

    if (!$post && $legacy_post) {
        $post = $legacy_post;
    }

    if (!$post) {
        return array(
            'id' => null,
            'slug' => 'homepage',
            'configured' => false,
            'cta_label' => '',
            'slides' => array(),
        );
    }

    $post_id = $post->ID;
    $slides = array();
    $fallback_post = $post->post_type === 'page' ? $legacy_post : null;

    for ($index = 1; $index <= 3; $index++) {
        $slide = maps_fbh_map_hero_slide($post_id, $index, $fallback_post);

        if (!empty($slide['image'])) {
            $slides[] = $slide;
        }
    }

    return array(
        'id' => $post_id,
        'slug' => $post->post_name,
        'configured' => count($slides) > 0,
        'cta_label' => maps_fbh_get_acf_value_with_legacy($post_id, 'hero_cta_label', $fallback_post) ?: 'Découvrir',
        'slides' => $slides,
    );
}

function maps_fbh_get_products($request) {
    $products = maps_fbh_get_content_items('product', 'maps_fbh_map_product');
    return new WP_REST_Response($products, 200);
}

function maps_fbh_get_services($request) {
    $services = maps_fbh_get_content_items('service', 'maps_fbh_map_service');
    return new WP_REST_Response($services, 200);
}

function maps_fbh_get_about_section($request) {
    $about_section = maps_fbh_get_about_section_data();
    return new WP_REST_Response($about_section, 200);
}

function maps_fbh_get_hero_section($request) {
    $hero_section = maps_fbh_get_hero_section_data();
    return new WP_REST_Response($hero_section, 200);
}

function register_routes() {
    register_rest_route('maps-fbh/v1', '/products', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'maps_fbh_get_products',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('maps-fbh/v1', '/services', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'maps_fbh_get_services',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('maps-fbh/v1', '/about-section', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'maps_fbh_get_about_section',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('maps-fbh/v1', '/hero-section', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'maps_fbh_get_hero_section',
        'permission_callback' => '__return_true',
    ));
}

add_action('rest_api_init', 'register_routes');
