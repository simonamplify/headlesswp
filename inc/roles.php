<?php
/**
 * Role-Based Restrictions
 *
 * Applies WordPress UI and capability restrictions for every role beneath
 * Administrator. Administrators are explicitly excluded from all restrictions
 * so their experience remains identical to a standard WordPress install.
 *
 * Restrictions applied to non-admin roles:
 *  - Remove the default Posts admin menu (optional toggle via filter).
 *  - Remove Comments, Appearance, and Tools from the admin menu.
 *  - Remove Settings for roles that cannot manage categories.
 *  - Remove the "New Page" item from the toolbar + button.
 *  - Remove "Visit Site" and Comments from the toolbar.
 *  - Clean up the dashboard by removing widgets that are irrelevant for a
 *    headless workflow.
 *
 * All hooks run on `admin_menu` / `wp_before_admin_bar_render` /
 * `wp_dashboard_setup` so no capabilities are permanently removed from
 * roles — restrictions apply only to the current request session.
 *
 * @package HeadlessWP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Helper ────────────────────────────────────────────────────────────────────

/**
 * Returns true when the current user is an Administrator.
 * Uses the `manage_options` capability as a reliable proxy.
 */
function headlesswp_is_admin_user(): bool {
    return current_user_can( 'manage_options' );
}

// ── 1. Admin menu cleanup ─────────────────────────────────────────────────────

add_action( 'admin_menu', 'headlesswp_restrict_admin_menu', 999 );

/**
 * Remove admin menu items that are not relevant for a headless workflow
 * from the sessions of non-administrator users.
 */
function headlesswp_restrict_admin_menu() {
    if ( headlesswp_is_admin_user() ) {
        return;
    }

    // Comments — headless sites typically manage comments externally.
    remove_menu_page( 'edit-comments.php' );

    // Appearance — no front-end theme to configure.
    remove_menu_page( 'themes.php' );

    // Tools — not relevant for any non-admin role in a headless setup.
    remove_menu_page( 'tools.php' );

    // Settings — only hide for roles that cannot manage site-wide options.
    if ( ! current_user_can( 'manage_categories' ) ) {
        remove_menu_page( 'options-general.php' );
    }

    /**
     * Filter: headlesswp_hide_posts_menu
     *
     * Set to true to hide the built-in Posts menu for non-admin users.
     * Defaults to false so standard blog posts remain accessible.
     *
     * Example:
     *   add_filter( 'headlesswp_hide_posts_menu', '__return_true' );
     */
    if ( apply_filters( 'headlesswp_hide_posts_menu', false ) ) {
        remove_menu_page( 'edit.php' );
    }
}

// ── 2. Toolbar cleanup ────────────────────────────────────────────────────────

add_action( 'wp_before_admin_bar_render', 'headlesswp_restrict_toolbar', 999 );

/**
 * Remove the "Visit Site" toolbar node for non-admin users.
 * The decoupled front end lives on a different domain/URL, so this link
 * would point to the WordPress PHP theme (which just redirects) and is
 * therefore misleading.
 */
function headlesswp_restrict_toolbar() {
    if ( headlesswp_is_admin_user() ) {
        return;
    }

    global $wp_admin_bar;
    $wp_admin_bar->remove_node( 'view-site' );
    $wp_admin_bar->remove_node( 'comments' );

    // Remove the "New Page" item from the "+" (new-content) toolbar dropdown.
    // Pages are disabled for non-admin roles so this entry is misleading.
    $wp_admin_bar->remove_node( 'new-page' );
}

// ── 3. Dashboard widget cleanup ───────────────────────────────────────────────

add_action( 'wp_dashboard_setup', 'headlesswp_restrict_dashboard_widgets', 999 );

/**
 * Remove default dashboard widgets that are not useful in a headless context
 * for non-administrator users.
 */
function headlesswp_restrict_dashboard_widgets() {
    if ( headlesswp_is_admin_user() ) {
        return;
    }

    // "At a Glance" widget — includes page/comment counts that aren't relevant.
    remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );

    // Activity feed (recent comments, posts pending review).
    remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );

    // "Quick Draft" — encourages creating posts via the dashboard shortcut.
    remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );

    // WordPress news feed.
    remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
}

// ── 4. Disable comments site-wide for non-admin roles ─────────────────────────

add_action( 'admin_init', 'headlesswp_disable_comments_for_non_admins' );

/**
 * Close comments and remove comment-related screens for non-admin users.
 * Administrators retain full comment management capabilities.
 */
function headlesswp_disable_comments_for_non_admins() {
    if ( headlesswp_is_admin_user() ) {
        return;
    }

    // Disable comment support on all post types for this session.
    foreach ( get_post_types() as $post_type ) {
        if ( post_type_supports( $post_type, 'comments' ) ) {
            remove_post_type_support( $post_type, 'comments' );
            remove_post_type_support( $post_type, 'trackbacks' );
        }
    }
}
