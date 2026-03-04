<?php
/**
 * Taxonomies
 *
 * Registers custom taxonomies used by the theme.
 *
 * - `project_category` — hierarchical (like built-in categories) taxonomy
 *   attached to the `project` post type. Fully exposed to the REST API so
 *   the decoupled front end can filter/query projects by category.
 * - Renames the built-in "Uncategorized" category to "News" on theme activation.
 *
 * @package HeadlessWP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Register Project Category taxonomy ────────────────────────────────────────

add_action( 'init', 'headlesswp_register_project_category_taxonomy', 0 );

/**
 * Register the `project_category` taxonomy.
 *
 * Hierarchical = true gives it category-like behaviour (parent/child terms,
 * checkbox UI in the editor) rather than tag-like behaviour.
 */
function headlesswp_register_project_category_taxonomy() {
    $labels = [
        'name'                       => _x( 'Project Categories', 'Taxonomy general name', 'headlesswp' ),
        'singular_name'              => _x( 'Project Category', 'Taxonomy singular name', 'headlesswp' ),
        'search_items'               => __( 'Search Project Categories', 'headlesswp' ),
        'all_items'                  => __( 'All Project Categories', 'headlesswp' ),
        'parent_item'                => __( 'Parent Project Category', 'headlesswp' ),
        'parent_item_colon'          => __( 'Parent Project Category:', 'headlesswp' ),
        'edit_item'                  => __( 'Edit Project Category', 'headlesswp' ),
        'update_item'                => __( 'Update Project Category', 'headlesswp' ),
        'add_new_item'               => __( 'Add New Project Category', 'headlesswp' ),
        'new_item_name'              => __( 'New Project Category Name', 'headlesswp' ),
        'menu_name'                  => __( 'Categories', 'headlesswp' ),
        'not_found'                  => __( 'No project categories found.', 'headlesswp' ),
        'no_terms'                   => __( 'No project categories', 'headlesswp' ),
        'items_list_navigation'      => __( 'Project categories list navigation', 'headlesswp' ),
        'items_list'                 => __( 'Project categories list', 'headlesswp' ),
        'back_to_items'              => __( '&larr; Go to Project Categories', 'headlesswp' ),
        'item_link'                  => _x( 'Project Category Link', 'navigation link block title', 'headlesswp' ),
        'item_link_description'      => _x( 'A link to a project category.', 'navigation link block description', 'headlesswp' ),
    ];

    $args = [
        'labels'            => $labels,
        'hierarchical'      => true,   // Category-like (not tag-like).
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,   // Show a category column on the Projects list table.
        'show_in_nav_menus' => false,  // Not needed for headless.
        'show_tagcloud'     => false,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'project-category' ],
        // REST API — essential for headless.
        'show_in_rest'      => true,
        'rest_base'         => 'project-categories',
        'rest_controller_class' => 'WP_REST_Terms_Controller',
    ];

    register_taxonomy( 'project_category', [ 'project' ], $args );
}

// ── Rename default category to "News" on theme activation ─────────────────────

add_action( 'after_switch_theme', 'headlesswp_rename_default_category' );

/**
 * Rename the built-in "Uncategorized" category to "News" and update its slug.
 *
 * WordPress requires at least one category and won't let you delete the
 * default (ID 1). Renaming it is the cleanest approach.
 * Runs only on theme activation — safe to re-run if the name has been
 * manually changed back.
 */
function headlesswp_rename_default_category(): void {
    $default_id = (int) get_option( 'default_category', 1 );

    $term = get_term( $default_id, 'category' );

    if ( ! $term || is_wp_error( $term ) ) {
        return;
    }

    wp_update_term( $default_id, 'category', [
        'name' => 'News',
        'slug' => 'news',
    ] );
}
