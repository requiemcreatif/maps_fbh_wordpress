<?php
ini_set('error_reporting', E_STRICT);
ini_set('memory_limit', -1);

require_once __DIR__ . '/../helpers/contents.php';

function register_routes()
{

    // Homepage Content API endpoint
    // register_rest_route('maps-fbh.fr/v1', 'homepage-content', array(
    //     'methods' => WP_REST_Server::READABLE,
    //     'callback' => 'get_homepage_content',
    //     'permission_callback' => '__return_true' // Public endpoint
    // ));

    // Privacy Policy API endpoint
    
    error_log('Routes registered successfully');
}

add_action('rest_api_init', 'register_routes');

// Function to get homepage content
function get_homepage_content($request) {


    return new WP_REST_Response($response_data, 200);
}


// Products API endpoint
function get_products($request) {


    return new WP_REST_Response($products, 200);
}

// Services API endpoint
function get_services($request) {


    return new WP_REST_Response($services, 200);
}

