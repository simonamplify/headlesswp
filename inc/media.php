<?php
/**
 * Media & Image Size Configuration
 *
 * Overrides WordPress default image sizes to better suit a headless site
 * with a content max-width of 1280px and hero images up to 1920px.
 *
 * Built-in size defaults are updated once via the `after_switch_theme` hook
 * (theme activation only), so they do not run on every request. Custom sizes
 * are registered on every `after_setup_theme` call as WordPress requires.
 *
 * Size strategy
 * ─────────────
 * thumbnail   150×150  hard-crop  — avatars, small grid thumbnails
 * medium       600px   soft       — cards, inline editorial images
 * medium_large 960px   soft       — mid-breakpoint responsive candidate
 * large       1280px   soft       — full content-width images
 * hero        1920px   soft       — hero banners, full-bleed sections (custom)
 *
 * WordPress automatically uses all registered sizes to populate `srcset` on
 * both classic <img> tags and the REST API `media` endpoint, giving the
 * decoupled front end responsive image candidates at no extra cost.
 *
 * @package HeadlessWP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── 1. Register custom image sizes (runs every request) ───────────────────────

add_action( 'after_setup_theme', 'headlesswp_register_image_sizes' );

/**
 * Register custom sizes beyond the built-in set.
 * The built-in thumbnail/medium/large dimensions are updated separately
 * via headlesswp_set_default_image_sizes() on theme activation.
 */
function headlesswp_register_image_sizes(): void {
    // Hero / full-bleed images — not a built-in size so add_image_size() is correct here.
    add_image_size( 'hero', 1920, 0, false );

    // medium_large is a built-in size managed by the `medium_large_size_w` database
    // option — add_image_size() is not called for it as WordPress ignores that for
    // built-in sizes. The correct width (960px) is written in headlesswp_set_default_image_sizes()
    // on theme activation.
}

// ── 2. Update built-in size options on theme activation (one-time) ────────────

add_action( 'after_switch_theme', 'headlesswp_set_default_image_sizes' );

/**
 * Update the stored options for WordPress built-in sizes.
 * Runs only when the theme is activated — not on every page load —
 * so there is no performance impact after initial setup.
 *
 * These values appear in Settings → Media and can still be adjusted by an
 * administrator. This function simply sets sensible headless-ready defaults.
 */
function headlesswp_set_default_image_sizes(): void {
    // Thumbnail — keep the hard-crop square, slightly larger is fine.
    update_option( 'thumbnail_size_w', 150 );
    update_option( 'thumbnail_size_h', 150 );
    update_option( 'thumbnail_crop',   1 );   // 1 = hard crop.

    // Medium — increase from the 300px default to suit modern card layouts.
    update_option( 'medium_size_w', 600 );
    update_option( 'medium_size_h', 0 );      // 0 = no height limit (proportional).

    // Medium Large — bring up to 960px (was 768px).
    update_option( 'medium_large_size_w', 960 );
    update_option( 'medium_large_size_h', 0 );

    // Large — raise to content max-width (was 1024px).
    update_option( 'large_size_w', 1280 );
    update_option( 'large_size_h', 0 );
}

// ── 3. Add custom sizes to the media insert UI ────────────────────────────────

add_filter( 'image_size_names_choose', 'headlesswp_add_image_sizes_to_ui' );

/**
 * Make custom sizes selectable in the media library "Insert into post" dialog
 * (useful when admins want to manually reference a specific size).
 *
 * @param  array $sizes Existing size labels.
 * @return array
 */
function headlesswp_add_image_sizes_to_ui( array $sizes ): array {
    return array_merge( $sizes, [
        'hero' => __( 'Hero (1920px)', 'headlesswp' ),
    ] );
}

// ── 4. Expose all registered sizes on REST media responses ────────────────────

add_action( 'rest_api_init', 'headlesswp_register_all_image_sizes_rest_field' );

/**
 * Add an `image_sizes` field to REST media (attachment) responses.
 *
 * Returns every registered image size available for the attachment, including
 * the custom `hero` size. This gives the front end a single object to read
 * rather than having to hard-code size names and guess URLs.
 *
 * Example response shape:
 * {
 *   "thumbnail":    { "url": "...", "width": 150,  "height": 150 },
 *   "medium":       { "url": "...", "width": 600,  "height": 400 },
 *   "medium_large": { "url": "...", "width": 960,  "height": 640 },
 *   "large":        { "url": "...", "width": 1280, "height": 853 },
 *   "hero":         { "url": "...", "width": 1920, "height": 1280 },
 *   "full":         { "url": "...", "width": 2400, "height": 1600 }
 * }
 */
function headlesswp_register_all_image_sizes_rest_field(): void {
    register_rest_field(
        'attachment',
        'image_sizes',
        [
            'get_callback'    => 'headlesswp_get_all_attachment_sizes',
            'update_callback' => null,
            'schema'          => [
                'description' => __( 'All available image sizes for this attachment.', 'headlesswp' ),
                'type'        => 'object',
                'context'     => [ 'view', 'edit' ],
                'readonly'    => true,
            ],
        ]
    );
}

/**
 * Build the image_sizes payload for an attachment REST response.
 *
 * @param  array $post REST attachment object.
 * @return array|null
 */
function headlesswp_get_all_attachment_sizes( array $post ): ?array {
    $attachment_id = (int) $post['id'];

    if ( ! wp_attachment_is_image( $attachment_id ) ) {
        return null;
    }

    $registered_sizes = array_merge(
        [ 'thumbnail', 'medium', 'medium_large', 'large', 'full' ],
        array_keys( wp_get_registered_image_subsizes() )
    );

    // Deduplicate while preserving a sensible display order.
    $size_names = array_unique( $registered_sizes );
    $output     = [];

    foreach ( $size_names as $size ) {
        $src = wp_get_attachment_image_src( $attachment_id, $size );

        if ( $src ) {
            $output[ $size ] = [
                'url'    => $src[0],
                'width'  => $src[1],
                'height' => $src[2],
            ];
        }
    }

    return $output ?: null;
}
