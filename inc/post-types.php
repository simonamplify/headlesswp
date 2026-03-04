<?php
/**
 * Post Types
 *
 * - Registers the `project` custom post type.
 * - Hides the built-in `page` post type from the admin menu and blocks
 *   direct screen access for non-administrator roles.
 * - Removes the `page` post type from the REST API entirely (all roles).
 *
 * @package HeadlessWP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── 1. Register the Project CPT ───────────────────────────────────────────────

add_action( 'init', 'headlesswp_register_project_cpt', 0 );

/**
 * Register the `project` post type.
 *
 * show_in_rest must be true for the post type to be accessible via the
 * REST API (required for all headless front-end queries).
 */
function headlesswp_register_project_cpt() {
    $labels = [
        'name'                  => _x( 'Projects', 'Post type general name', 'headlesswp' ),
        'singular_name'         => _x( 'Project', 'Post type singular name', 'headlesswp' ),
        'menu_name'             => _x( 'Projects', 'Admin Menu text', 'headlesswp' ),
        'name_admin_bar'        => _x( 'Project', 'Add New on Toolbar', 'headlesswp' ),
        'add_new'               => __( 'Add New', 'headlesswp' ),
        'add_new_item'          => __( 'Add New Project', 'headlesswp' ),
        'new_item'              => __( 'New Project', 'headlesswp' ),
        'edit_item'             => __( 'Edit Project', 'headlesswp' ),
        'view_item'             => __( 'View Project', 'headlesswp' ),
        'all_items'             => __( 'All Projects', 'headlesswp' ),
        'search_items'          => __( 'Search Projects', 'headlesswp' ),
        'parent_item_colon'     => __( 'Parent Projects:', 'headlesswp' ),
        'not_found'             => __( 'No projects found.', 'headlesswp' ),
        'not_found_in_trash'    => __( 'No projects found in Trash.', 'headlesswp' ),
        'featured_image'        => _x( 'Project Cover Image', 'Overrides the "Featured Image" phrase', 'headlesswp' ),
        'set_featured_image'    => _x( 'Set cover image', 'Overrides the "Set featured image" phrase', 'headlesswp' ),
        'remove_featured_image' => _x( 'Remove cover image', 'Overrides the "Remove featured image" phrase', 'headlesswp' ),
        'use_featured_image'    => _x( 'Use as cover image', 'Overrides the "Use as featured image" phrase', 'headlesswp' ),
        'archives'              => _x( 'Project archives', 'The post type archive label used in nav menus', 'headlesswp' ),
        'insert_into_item'      => _x( 'Insert into project', 'Overrides the "Insert into post" phrase', 'headlesswp' ),
        'uploaded_to_this_item' => _x( 'Uploaded to this project', 'Overrides the "Uploaded to this post" phrase', 'headlesswp' ),
        'filter_items_list'     => _x( 'Filter projects list', 'Screen reader text for the filter links', 'headlesswp' ),
        'items_list_navigation' => _x( 'Projects list navigation', 'Screen reader text for the pagination', 'headlesswp' ),
        'items_list'            => _x( 'Projects list', 'Screen reader text for the items list', 'headlesswp' ),
    ];

    $args = [
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => [ 'slug' => 'projects' ],
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-portfolio',
        'supports'           => [
            'title',
            'editor',
            'author',
            'thumbnail',
            'excerpt',
            'custom-fields',
            'revisions',
        ],
        // REST API — essential for headless.
        'show_in_rest'       => true,
        'rest_base'          => 'projects',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
    ];

    register_post_type( 'project', $args );
}

// ── 2. Disable the built-in Page post type for non-administrators ─────────────

/**
 * Hide the Pages admin menu item from all roles below Administrator.
 * Runs on admin_menu because the menu is only built on admin requests.
 */
add_action( 'admin_menu', 'headlesswp_hide_pages_menu' );

function headlesswp_hide_pages_menu() {
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }
    remove_menu_page( 'edit.php?post_type=page' );
}

/**
 * Remove the Pages post type from the REST API unconditionally.
 * Pages are managed entirely in the admin UI; nothing in the headless
 * front end should query them via the REST API. Administrators still
 * have full access to the admin edit screens.
 */
add_filter( 'register_post_type_args', 'headlesswp_disable_pages_rest', 10, 2 );

function headlesswp_disable_pages_rest( array $args, string $post_type ): array {
    if ( 'page' === $post_type ) {
        $args['show_in_rest'] = false;
    }
    return $args;
}

/**
 * Prevent non-admin users from directly accessing the page edit screen.
 * Redirects to the dashboard if someone navigates to
 * /wp-admin/edit.php?post_type=page or /wp-admin/post-new.php?post_type=page.
 */
add_action( 'current_screen', 'headlesswp_block_pages_screen' );

function headlesswp_block_pages_screen() {
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }

    $screen = get_current_screen();

    if ( $screen && 'page' === $screen->post_type ) {
        wp_redirect( admin_url( 'index.php' ), 302 );
        exit;
    }
}
