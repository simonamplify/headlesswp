<?php
/**
 * Headless Mode Configuration
 *
 * - Redirects all public/frontend requests to the WP admin so the site
 *   cannot be rendered as a traditional WordPress front end.
 * - Strips unnecessary head bloat (emoji, oEmbed discovery, generator tags).
 * - Adds permissive CORS headers to every REST API response so decoupled
 *   front-end applications can call the API from any origin.
 *
 * NOTE: Admin users are never redirected so they keep full access.
 *
 * @package HeadlessWP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── 1. Redirect all frontend requests to the admin ────────────────────────────

add_action( 'template_redirect', 'headlesswp_redirect_frontend', 1 );

/**
 * Redirect every public request to /wp-admin/.
 * REST API requests (/wp-json/) are allowed through for the decoupled client.
 * Admin users are never redirected.
 */
function headlesswp_redirect_frontend() {
    // Allow REST API requests (checked by WordPress core before templates).
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return;
    }

    // Allow admin users to visit the front end if they choose to.
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }

    wp_redirect( admin_url(), 302 );
    exit;
}

// ── 2. Strip unnecessary head bloat ───────────────────────────────────────────

add_action( 'init', 'headlesswp_remove_head_bloat' );

/**
 * Remove features that only serve a traditional rendered front end.
 */
function headlesswp_remove_head_bloat() {
    // Emoji scripts and styles.
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );

    // oEmbed discovery links in <head>.
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
    remove_action( 'wp_head', 'wp_oembed_add_host_js' );

    // RSD and Windows Live Writer links.
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wlwmanifest_link' );

    // WordPress version generator tag.
    remove_action( 'wp_head', 'wp_generator' );

    // Shortlink.
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );

    // Feed links — the API replaces these.
    remove_action( 'wp_head', 'feed_links', 2 );
    remove_action( 'wp_head', 'feed_links_extra', 3 );

    // Canonical / prev-next links are irrelevant for headless.
    remove_action( 'wp_head', 'rel_canonical' );
    remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
}

// ── 3. Disable the built-in XML-RPC interface ─────────────────────────────────

add_filter( 'xmlrpc_enabled', '__return_false' );

// ── 4. CORS headers on REST API responses ─────────────────────────────────────

add_action( 'rest_api_init', 'headlesswp_add_cors_headers', 15 );

/**
 * Add CORS headers so the decoupled front-end application can call the API.
 *
 * Adjust the allowed origins below to match your production front-end domain.
 * Using '*' is convenient for local development but should be tightened for
 * production deployments.
 */
function headlesswp_add_cors_headers() {
    remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

    add_filter( 'rest_pre_serve_request', function ( $value ) {
        $allowed_origins = apply_filters( 'headlesswp_cors_allowed_origins', [
            '*', // Replace with your front-end URL in production, e.g. 'https://yourfrontend.com'
        ] );

        $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( $_SERVER['HTTP_ORIGIN'] ) : '';

        if ( in_array( '*', $allowed_origins, true ) ) {
            header( 'Access-Control-Allow-Origin: *' );
            // Note: Access-Control-Allow-Credentials cannot be true with a wildcard
            // origin — browsers reject that combination. Switch to a specific origin
            // list in production so credentials (Authorization header) are accepted.
        } elseif ( in_array( $origin, $allowed_origins, true ) ) {
            header( 'Access-Control-Allow-Origin: ' . $origin );
            header( 'Access-Control-Allow-Credentials: true' );
            header( 'Vary: Origin' );
        }

        header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );

        return $value;
    } );
}

// ── 5. Theme setup — minimal, no enqueues needed for headless ─────────────────

add_action( 'after_setup_theme', 'headlesswp_theme_setup' );

/**
 * Minimal theme setup. Post thumbnails are kept so featured images are
 * available through the REST API.
 */
function headlesswp_theme_setup() {
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'title-tag' );
}
