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

    return maps_fbh_normalize_attachment_image($attachment_id);
}

function maps_fbh_normalize_attachment_image($attachment_id) {
    $attachment_id = (int) $attachment_id;

    if (!$attachment_id) {
        return null;
    }

    $metadata = wp_get_attachment_metadata($attachment_id);
    $full_image = wp_get_attachment_image_src($attachment_id, 'full');
    $sizes = array();

    if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
        foreach (array_keys($metadata['sizes']) as $size_name) {
            $size_image = wp_get_attachment_image_src($attachment_id, $size_name);

            if ($size_image) {
                $sizes[$size_name] = $size_image[0];
                $sizes[$size_name . '-width'] = (int) $size_image[1];
                $sizes[$size_name . '-height'] = (int) $size_image[2];
            }
        }
    }

    return array(
        'id' => $attachment_id,
        'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
        'width' => $full_image ? (int) $full_image[1] : null,
        'height' => $full_image ? (int) $full_image[2] : null,
        'url' => $full_image ? $full_image[0] : wp_get_attachment_url($attachment_id),
        'sizes' => $sizes,
    );
}

function maps_fbh_is_woocommerce_active() {
    return class_exists('WooCommerce') && function_exists('wc_get_product');
}

function maps_fbh_get_product_purchase_url($product) {
    if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
        return '';
    }

    if ($product->is_type('external')) {
        return $product->add_to_cart_url();
    }

    if (function_exists('wc_get_checkout_url') && $product->is_type('simple')) {
        return add_query_arg('add-to-cart', $product->get_id(), wc_get_checkout_url());
    }

    return get_permalink($product->get_id());
}

function maps_fbh_map_woocommerce_product($product, $post) {
    $post_id = $product->get_id();
    $image_id = $product->get_image_id();
    $legacy_image = maps_fbh_normalize_image(maps_fbh_get_acf_value($post_id, 'production_image'));
    $short_description = $product->get_short_description() ?: wp_strip_all_tags(get_the_excerpt($post_id));
    $description = $product->get_description() ?: $post->post_content;

    return array(
        'id' => $post_id,
        'slug' => $post->post_name,
        'title' => $product->get_name() ?: get_the_title($post_id),
        'description' => wp_strip_all_tags($short_description),
        'content' => apply_filters('the_content', $description),
        'excerpt' => get_the_excerpt($post_id),
        'featured_image' => $image_id ? maps_fbh_normalize_attachment_image($image_id) : $legacy_image,
        'link' => get_permalink($post_id),
        'price' => $product->get_price(),
        'regular_price' => $product->get_regular_price(),
        'sale_price' => $product->get_sale_price(),
        'price_html' => $product->get_price_html(),
        'currency_code' => get_woocommerce_currency(),
        'currency_symbol' => html_entity_decode(get_woocommerce_currency_symbol()),
        'purchasable' => $product->is_purchasable(),
        'stock_status' => $product->get_stock_status(),
        'purchase_url' => maps_fbh_get_product_purchase_url($product),
    );
}

function maps_fbh_map_product($post) {
    $post_id = $post->ID;

    if (maps_fbh_is_woocommerce_active()) {
        $product = wc_get_product($post_id);

        if ($product) {
            return maps_fbh_map_woocommerce_product($product, $post);
        }
    }

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
        'price' => '',
        'regular_price' => '',
        'sale_price' => '',
        'price_html' => '',
        'currency_code' => maps_fbh_is_woocommerce_active() ? get_woocommerce_currency() : '',
        'currency_symbol' => maps_fbh_is_woocommerce_active() ? html_entity_decode(get_woocommerce_currency_symbol()) : '',
        'purchasable' => false,
        'stock_status' => '',
        'purchase_url' => '',
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

function maps_fbh_get_shop_settings($request) {
    $settings = array(
        'shop_url' => home_url('/'),
        'cart_url' => '',
        'checkout_url' => '',
        'myaccount_url' => '',
    );

    if (maps_fbh_is_woocommerce_active()) {
        $settings['shop_url'] = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : get_permalink(wc_get_page_id('shop'));
        $settings['cart_url'] = function_exists('wc_get_cart_url') ? wc_get_cart_url() : get_permalink(wc_get_page_id('cart'));
        $settings['checkout_url'] = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : get_permalink(wc_get_page_id('checkout'));
        $settings['myaccount_url'] = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : get_permalink(wc_get_page_id('myaccount'));
    }

    return new WP_REST_Response($settings, 200);
}

function maps_fbh_map_customer_user($user) {
    $first_name = get_user_meta($user->ID, 'first_name', true);
    $last_name = get_user_meta($user->ID, 'last_name', true);
    $customer = maps_fbh_is_woocommerce_active() ? new WC_Customer($user->ID) : null;

    return array(
        'id' => (int) $user->ID,
        'email' => $user->user_email,
        'display_name' => $user->display_name,
        'first_name' => $first_name ?: ($customer ? $customer->get_first_name() : ''),
        'last_name' => $last_name ?: ($customer ? $customer->get_last_name() : ''),
        'billing' => $customer ? array(
            'first_name' => $customer->get_billing_first_name(),
            'last_name' => $customer->get_billing_last_name(),
            'company' => $customer->get_billing_company(),
            'address_1' => $customer->get_billing_address_1(),
            'address_2' => $customer->get_billing_address_2(),
            'postcode' => $customer->get_billing_postcode(),
            'city' => $customer->get_billing_city(),
            'country' => $customer->get_billing_country(),
            'email' => $customer->get_billing_email(),
            'phone' => $customer->get_billing_phone(),
        ) : null,
        'shipping' => $customer ? array(
            'first_name' => $customer->get_shipping_first_name(),
            'last_name' => $customer->get_shipping_last_name(),
            'company' => $customer->get_shipping_company(),
            'address_1' => $customer->get_shipping_address_1(),
            'address_2' => $customer->get_shipping_address_2(),
            'postcode' => $customer->get_shipping_postcode(),
            'city' => $customer->get_shipping_city(),
            'country' => $customer->get_shipping_country(),
            'phone' => $customer->get_shipping_phone(),
        ) : null,
    );
}

function maps_fbh_get_authenticated_customer($request) {
    $user_id = get_current_user_id();

    if (!$user_id) {
        return new WP_Error(
            'maps_fbh_auth_required',
            'Authentication is required.',
            array('status' => 401)
        );
    }

    $user = get_user_by('id', $user_id);

    if (!$user) {
        return new WP_Error(
            'maps_fbh_auth_invalid_user',
            'The account session user could not be found.',
            array('status' => 401)
        );
    }

    return $user;
}

function maps_fbh_customer_register($request) {
    if (!maps_fbh_is_woocommerce_active() || !function_exists('wc_create_new_customer')) {
        return new WP_Error(
            'maps_fbh_woocommerce_required',
            'WooCommerce is required to create customer accounts.',
            array('status' => 501)
        );
    }

    $email = sanitize_email($request->get_param('email'));
    $password = (string) $request->get_param('password');
    $first_name = sanitize_text_field($request->get_param('first_name'));
    $last_name = sanitize_text_field($request->get_param('last_name'));

    if (!$email || !$password) {
        return new WP_Error(
            'maps_fbh_register_missing_fields',
            'Email and password are required.',
            array('status' => 400)
        );
    }

    $user_id = wc_create_new_customer($email, '', $password);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    if ($first_name || $last_name) {
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name) ?: $email,
        ));
    }

    $user = get_user_by('id', $user_id);

    return new WP_REST_Response(array(
        'user' => maps_fbh_map_customer_user($user),
    ), 201);
}

function maps_fbh_customer_me($request) {
    $user = maps_fbh_get_authenticated_customer($request);

    if (is_wp_error($user)) {
        return $user;
    }

    return new WP_REST_Response(array(
        'user' => maps_fbh_map_customer_user($user),
    ), 200);
}

function maps_fbh_map_customer_order($order) {
    $items = array();

    foreach ($order->get_items() as $item) {
        $items[] = array(
            'name' => $item->get_name(),
            'quantity' => (int) $item->get_quantity(),
            'total' => html_entity_decode(wp_strip_all_tags(wc_price($item->get_total(), array('currency' => $order->get_currency())))),
        );
    }

    return array(
        'id' => (int) $order->get_id(),
        'number' => $order->get_order_number(),
        'status' => $order->get_status(),
        'status_label' => wc_get_order_status_name($order->get_status()),
        'date_created' => $order->get_date_created() ? $order->get_date_created()->date('c') : null,
        'total' => html_entity_decode(wp_strip_all_tags($order->get_formatted_order_total())),
        'currency' => $order->get_currency(),
        'item_count' => (int) $order->get_item_count(),
        'items' => $items,
    );
}

function maps_fbh_customer_orders($request) {
    $user = maps_fbh_get_authenticated_customer($request);

    if (is_wp_error($user)) {
        return $user;
    }

    if (!maps_fbh_is_woocommerce_active() || !function_exists('wc_get_orders')) {
        return new WP_REST_Response(array('orders' => array()), 200);
    }

    $orders = wc_get_orders(array(
        'customer_id' => $user->ID,
        'limit' => 20,
        'orderby' => 'date',
        'order' => 'DESC',
    ));

    return new WP_REST_Response(array(
        'orders' => array_map('maps_fbh_map_customer_order', $orders),
    ), 200);
}

function maps_fbh_customer_permission_callback($request) {
    if (is_user_logged_in()) {
        return true;
    }

    return new WP_Error(
        'maps_fbh_auth_required',
        'Authentication is required.',
        array('status' => 401)
    );
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

    register_rest_route('maps-fbh/v1', '/shop-settings', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'maps_fbh_get_shop_settings',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('maps-fbh/v1', '/customer/register', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'maps_fbh_customer_register',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('maps-fbh/v1', '/customer/me', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'maps_fbh_customer_me',
        'permission_callback' => 'maps_fbh_customer_permission_callback',
    ));

    register_rest_route('maps-fbh/v1', '/customer/orders', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'maps_fbh_customer_orders',
        'permission_callback' => 'maps_fbh_customer_permission_callback',
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
