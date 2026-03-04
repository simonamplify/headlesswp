<?php
/**
 * REST API Configuration
 *
 * Hardens and extends the WordPress REST API for headless use:
 *
 * - Requires authentication on ALL REST requests (read and write) via Application Password.
 * - Disables the /wp/v2/users endpoint (leaks usernames; author data is on each post instead).
 * - Exposes posts, categories, tags, projects, and project-categories to authenticated clients.
 * - Exposes featured image URLs directly on post/project objects.
 * - Exposes author name, slug, and avatar directly on post/project objects.
 * - Adds ACF field data to REST responses when ACF is active.
 * - Registers a /wp-json/headlesswp/v1/health liveness probe.
 *
 * @package HeadlessWP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── 1. Require authentication on all REST requests ──────────────────────────────

add_filter( 'rest_authentication_errors', 'headlesswp_require_rest_authentication' );

/**
 * Block all unauthenticated REST API requests — reads and writes alike.
 *
 * Authentication is handled by WordPress core via HTTP Basic Auth using an
 * Application Password. The front-end application should include the header:
 *
 *   Authorization: Basic base64(username:application_password)
 *
 * Application Passwords are generated per-user under Users → Profile
 * → Application Passwords in the WordPress admin.
 *
 * The health-check endpoint (/headlesswp/v1/health) is explicitly exempted
 * so CI pipelines can probe liveness without credentials.
 *
 * Passes through any error already set by a previous authentication handler
 * (e.g. a plugin) so as not to mask it.
 */
function headlesswp_require_rest_authentication( $result ) {
    // Preserve errors set by earlier auth handlers.
    if ( is_wp_error( $result ) ) {
        return $result;
    }

    // Already authenticated (session cookie, nonce, or Application Password).
    if ( is_user_logged_in() ) {
        return $result;
    }

    // Allow the liveness probe through unauthenticated.
    $route = isset( $GLOBALS['wp']->query_vars['rest_route'] )
        ? $GLOBALS['wp']->query_vars['rest_route']
        : '';

    if ( str_starts_with( $route, '/headlesswp/v1/health' ) ) {
        return $result;
    }

    return new WP_Error(
        'rest_forbidden',
        __( 'REST API requests require authentication. Use an Application Password.', 'headlesswp' ),
        [ 'status' => 401 ]
    );
}

// ── 2. Disable the users endpoint ─────────────────────────────────────────────

add_filter( 'rest_endpoints', 'headlesswp_remove_rest_endpoints' );

/**
 * Remove endpoints that should not be publicly accessible.
 *
 * /wp/v2/users — exposes login usernames; not needed by the front end since
 *                author data (ID, name, slug) is already embedded inside every
 *                post and project REST response.
 *
 * Posts (/wp/v2/posts), categories (/wp/v2/categories), and tags
 * (/wp/v2/tags) are intentionally left available — they are part of the
 * site's content model and read by the decoupled front end.
 */
function headlesswp_remove_rest_endpoints( array $endpoints ): array {
    unset( $endpoints['/wp/v2/users'] );
    unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
    unset( $endpoints['/wp/v2/users/me'] );

    return $endpoints;
}

// ── 3. Expose the full featured image URL on all REST responses ───────────────

add_action( 'rest_api_init', 'headlesswp_register_featured_image_url_field' );

/**
 * Add a `featured_image_url` field to posts, pages, and the project CPT.
 * Returns an array keyed by image size ('full', 'large', 'medium', 'thumbnail').
 */
function headlesswp_register_featured_image_url_field() {
    $post_types = [ 'post', 'page', 'project' ];

    foreach ( $post_types as $post_type ) {
        register_rest_field(
            $post_type,
            'featured_image_url',
            [
                'get_callback'    => 'headlesswp_get_featured_image_url',
                'update_callback' => null,
                'schema'          => [
                    'description' => __( 'Featured image URLs keyed by size.', 'headlesswp' ),
                    'type'        => 'object',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => true,
                ],
            ]
        );
    }
}

/**
 * Callback: return an array of image URLs for each registered size.
 *
 * @param array $post REST post object array.
 * @return array|null
 */
function headlesswp_get_featured_image_url( array $post ): ?array {
    $thumbnail_id = get_post_thumbnail_id( $post['id'] );

    if ( ! $thumbnail_id ) {
        return null;
    }

    $sizes = [ 'thumbnail', 'medium', 'large', 'full' ];
    $urls  = [];

    foreach ( $sizes as $size ) {
        $image = wp_get_attachment_image_src( $thumbnail_id, $size );
        if ( $image ) {
            $urls[ $size ] = [
                'url'    => $image[0],
                'width'  => $image[1],
                'height' => $image[2],
            ];
        }
    }

    return $urls ?: null;
}

// ── 4. Client logo URL on project REST responses ────────────────────────────────

add_action( 'rest_api_init', 'headlesswp_register_client_logo_url_field' );

/**
 * Add a `client_logo_url` field to project REST responses.
 * Returns a src string at 400px wide (or the closest registered size),
 * plus the raw attachment ID for cases where the client wants more control.
 */
function headlesswp_register_client_logo_url_field(): void {
    register_rest_field(
        'project',
        'client_logo_url',
        [
            'get_callback'    => function( array $post ): ?array {
                $logo_id = (int) get_post_meta( $post['id'], '_client_logo_id', true );

                if ( $logo_id <= 0 ) {
                    return null;
                }

                // Request 400px wide; WordPress picks the closest registered size.
                $image = wp_get_attachment_image_src( $logo_id, [ 400, 9999 ] );

                if ( ! $image ) {
                    return null;
                }

                return [
                    'id'     => $logo_id,
                    'url'    => $image[0],
                    'width'  => $image[1],
                    'height' => $image[2],
                ];
            },
            'update_callback' => null,
            'schema'          => [
                'description' => __( 'Client logo resolved from the stored attachment ID.', 'headlesswp' ),
                'type'        => 'object',
                'context'     => [ 'view', 'edit' ],
                'readonly'    => true,
                'properties'  => [
                    'id'     => [ 'type' => 'integer' ],
                    'url'    => [ 'type' => 'string', 'format' => 'uri' ],
                    'width'  => [ 'type' => 'integer' ],
                    'height' => [ 'type' => 'integer' ],
                ],
            ],
        ]
    );
}

// ── 5. Author data on post and project REST responses ───────────────────────────

add_action( 'rest_api_init', 'headlesswp_register_author_data_field' );

/**
 * Add an `author_data` field to post and project REST responses.
 *
 * The /wp/v2/users endpoint is intentionally disabled to prevent username
 * enumeration, so author details are embedded directly here instead.
 *
 * Returns: id, name (display name), slug (user_nicename), and avatar_url.
 */
function headlesswp_register_author_data_field(): void {
    foreach ( [ 'post', 'project' ] as $post_type ) {
        register_rest_field(
            $post_type,
            'author_data',
            [
                'get_callback'    => 'headlesswp_get_author_data',
                'update_callback' => null,
                'schema'          => [
                    'description' => __( 'Author display name, slug, and avatar.', 'headlesswp' ),
                    'type'        => 'object',
                    'context'     => [ 'view', 'edit' ],
                    'readonly'    => true,
                    'properties'  => [
                        'id'         => [ 'type' => 'integer' ],
                        'name'       => [ 'type' => 'string' ],
                        'slug'       => [ 'type' => 'string' ],
                        'avatar_url' => [ 'type' => 'string', 'format' => 'uri' ],
                    ],
                ],
            ]
        );
    }
}

/**
 * Callback: return display name, slug, and avatar for the post author.
 *
 * @param array $post REST post object array.
 * @return array|null Null if no author is set.
 */
function headlesswp_get_author_data( array $post ): ?array {
    $author_id = (int) ( $post['author'] ?? 0 );

    if ( $author_id <= 0 ) {
        return null;
    }

    $user = get_userdata( $author_id );

    if ( ! $user ) {
        return null;
    }

    return [
        'id'         => $author_id,
        'name'       => $user->display_name,
        'slug'       => $user->user_nicename,
        'avatar_url' => get_avatar_url( $author_id, [ 'size' => 96 ] ),
    ];
}

// ── 6. ACF fields in REST responses (graceful no-op when ACF is absent) ────────

add_action( 'rest_api_init', 'headlesswp_register_acf_rest_fields' );

/**
 * When Advanced Custom Fields (ACF) is active, add a `acf` property to
 * project REST responses containing all field values for that post.
 */
function headlesswp_register_acf_rest_fields() {
    if ( ! function_exists( 'get_fields' ) ) {
        return;
    }

    register_rest_field(
        'project',
        'acf',
        [
            'get_callback'    => function( array $post ) {
                $fields = get_fields( $post['id'] );
                return $fields ?: new stdClass(); // Return empty object, not false.
            },
            'update_callback' => null,
            'schema'          => [
                'description' => __( 'ACF custom field values.', 'headlesswp' ),
                'type'        => 'object',
                'context'     => [ 'view', 'edit' ],
                'readonly'    => true,
            ],
        ]
    );
}

// ── 7. Health-check endpoint ────────────────────────────────────────────────────

add_action( 'rest_api_init', 'headlesswp_register_health_endpoint' );

/**
 * Register GET /wp-json/headlesswp/v1/health
 *
 * Returns a JSON object with the current timestamp and theme version.
 * Useful as a liveness probe from the front-end build pipeline.
 * No authentication required.
 */
function headlesswp_register_health_endpoint() {
    register_rest_route(
        'headlesswp/v1',
        '/health',
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'headlesswp_health_check_response',
            'permission_callback' => '__return_true',
        ]
    );
}

/**
 * Callback for the health-check endpoint.
 *
 * @return WP_REST_Response
 */
function headlesswp_health_check_response(): WP_REST_Response {
    return new WP_REST_Response(
        [
            'status'    => 'ok',
            'timestamp' => current_time( 'c' ),
            'version'   => HEADLESSWP_VERSION,
            'wp'        => get_bloginfo( 'version' ),
        ],
        200
    );
}

// ── 8. Strip namespace and route listing from the REST index ─────────────────

add_filter( 'rest_index', 'headlesswp_filter_rest_index' );

/**
 * Remove namespaces and routes from the REST index response.
 * Since all requests require authentication, unauthenticated callers are
 * already rejected before reaching this filter. This reduces information
 * exposure for authenticated clients that request the root index.
 */
function headlesswp_filter_rest_index( WP_REST_Response $response ): WP_REST_Response {
    if ( is_user_logged_in() ) {
        return $response;
    }

    $data = $response->get_data();
    unset( $data['namespaces'], $data['routes'] );
    $response->set_data( $data );

    return $response;
}
