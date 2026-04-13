<?php
ini_set('error_reporting', E_STRICT);

// Include necessary files
require_once 'inc/custom-post-types.php';
require_once 'inc/rest-api.php';
require_once 'inc/admin.php';
require_once 'inc/hooks.php';

// Helper functions
require_once 'helpers/service.php';
require_once 'helpers/contents.php';
require_once 'helpers/blocks.php';

/**
 * Maps FBH functions and definitions
 *
 * @package Maps_FBH
 * @since 1.0.0
 */

if (!function_exists('maps_fbh_setup')) :
    function maps_fbh_setup() {
        add_theme_support('automatic-feed-links');
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('align-wide');
        add_theme_support('responsive-embeds');
    }
endif;
add_action('after_setup_theme', 'maps_fbh_setup');

define('ALL_POST_TYPES', array(
    'page',
    'post',
));


/**
 * Flush rewrite rules on theme activation
 */
function maps_fbh_flush_rewrite_rules() {
    // Call this function only on theme activation
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'maps_fbh_flush_rewrite_rules');

// CORS Headers
function add_cors_http_header() {
    $allowed_origins = array(

        'http://localhost:3000'
    );

    if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
        header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        header('Access-Control-Allow-Credentials: true');
    }

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        status_header(200);
        exit();
    }
}
add_action('init', 'add_cors_http_header');
add_action('rest_api_init', 'add_cors_http_header');


function allow_cors_for_images($headers) {
    $allowed_origins = array(

        'http://localhost:3000'
    );

    if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
        $headers['Access-Control-Allow-Origin'] = $_SERVER['HTTP_ORIGIN'];
    }
    return $headers;
}
add_filter('wp_headers', 'allow_cors_for_images');

/**
 * Canonical URL redirects for SEO
 * Redirects CMS URLs to frontend domain for public content
 */
function maps_fbh_canonical_redirect() {
    // Only apply to frontend requests (not admin, API, or logged-in users)
    if (is_admin() || wp_doing_ajax() || is_user_logged_in()) {
        return;
    }

    // Skip for REST API requests
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    $frontend_domain = 'https://www.maps-fbh.fr';
    
    // Handle blog posts
    if (is_single() && get_post_type() === 'post') {
        $post_slug = get_post_field('post_name', get_the_ID());
        $frontend_url = $frontend_domain . '/blog/' . $post_slug;
        
        wp_redirect($frontend_url, 301);
        exit;
    }
    
    // Handle category pages
    if (is_category()) {
        wp_redirect($frontend_domain . '/blog', 301);
        exit;
    }
    
    // Handle tag pages
    if (is_tag()) {
        wp_redirect($frontend_domain . '/blog', 301);
        exit;
    }
    
    // Handle main blog page
    if (is_home() || is_front_page()) {
        wp_redirect($frontend_domain . '/blog', 301);
        exit;
    }
}
// Temporarily disabled to troubleshoot admin access
// add_action('template_redirect', 'maps_fbh_canonical_redirect');

/**
 * Add canonical meta tags for SEO
 */
function maps_fbh_add_canonical_meta() {
    if (is_admin()) {
        return;
    }
    
    $frontend_domain = 'https://www.maps-fbh.fr';
    $canonical_url = '';
    
    if (is_single() && get_post_type() === 'post') {
        $post_slug = get_post_field('post_name', get_the_ID());
        $canonical_url = $frontend_domain . '/blog/' . $post_slug;
    } elseif (is_category() || is_tag() || is_home() || is_front_page()) {
        $canonical_url = $frontend_domain . '/blog';
    }
    
    if ($canonical_url) {
        echo '<link rel="canonical" href="' . esc_url($canonical_url) . '" />' . "\n";
        echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
    }
}
add_action('wp_head', 'maps_fbh_add_canonical_meta');

/**
 * Prevent search engines from indexing CMS domain
 */
function maps_fbh_add_noindex_meta() {
    // Add noindex meta tag to prevent search engine indexing of CMS
    if (!is_admin() && !wp_doing_ajax()) {
        echo '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />' . "\n";
        echo '<meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet" />' . "\n";
    }
}
add_action('wp_head', 'maps_fbh_add_noindex_meta', 1);

