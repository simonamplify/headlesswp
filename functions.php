<?php
/**
 * HeadlessWP Theme Functions
 *
 * Bootstraps all theme includes. Each concern is isolated in its own file
 * under /inc so the codebase stays easy to navigate and extend.
 *
 * @package HeadlessWP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Constants ─────────────────────────────────────────────────────────────────
define( 'HEADLESSWP_VERSION', '1.0.0' );
define( 'HEADLESSWP_DIR', get_template_directory() );
define( 'HEADLESSWP_URI', get_template_directory_uri() );

// ── Includes ──────────────────────────────────────────────────────────────────
$includes = [
    '/inc/headless.php',    // Headless mode: frontend redirect, CORS, API hardening
    '/inc/roles.php',       // Role-based capability and UI restrictions
    '/inc/post-types.php',  // Custom post types (Projects) + disable Pages for non-admins
    '/inc/taxonomies.php',  // Custom taxonomies (Project Category)
    '/inc/meta-fields.php', // Project + Client custom meta fields and meta boxes
    '/inc/media.php',       // Image sizes optimised for headless (1280px content, 1920px hero)
    '/inc/api.php',         // REST API configuration and enhancements
];

foreach ( $includes as $file ) {
    $path = HEADLESSWP_DIR . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}
