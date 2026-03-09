<?php
/**
 * Project Meta Fields
 *
 * Registers and renders three meta boxes on the `project` post type:
 *
 * 1. "Project Details" — context: `normal`, priority: `high`    → first below the canvas.
 * 2. "Client Details"  — context: `normal`, priority: `default` → below Project Details.
 * 3. "SEO"             — context: `normal`, priority: `low`     → last below the canvas.
 *
 * All fields are registered with register_post_meta() and exposed via the
 * REST API so the decoupled front end can consume them without extra queries.
 * The client logo is stored as an attachment ID; a resolved URL is added to
 * the REST response separately in inc/api.php.
 *
 * @package HeadlessWP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Constants ─────────────────────────────────────────────────────────────────

/** Available services for the checklist. */
if ( ! function_exists( 'headlesswp_get_services' ) ) {
    function headlesswp_get_services(): array {
        return [
            'web_design'    => 'Web Design',
            'web_dev'       => 'Web Dev',
            'graphic_design'=> 'Graphic Design',
            'print'         => 'Print',
            'ux_research'   => 'UX Research',
            'wp_dev'        => 'WordPress Dev',
            'seo'           => 'SEO',
            'consultancy'   => 'Consultancy',
            'podcast'       => 'Podcast',
            'multimedia'    => 'Multimedia',
            'animation'     => 'Animation',
        ];
    }
}

// ── 1. Register meta keys ─────────────────────────────────────────────────────

add_action( 'init', 'headlesswp_register_project_meta' );

function headlesswp_register_project_meta(): void {

    // ── Client fields ──────────────────────────────────────────────────────

    register_post_meta( 'project', '_client_name', [
        'type'              => 'string',
        'description'       => 'Client name.',
        'single'            => true,
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => 'headlesswp_meta_auth',
        'show_in_rest'      => true,
    ] );

    register_post_meta( 'project', '_client_description', [
        'type'              => 'string',
        'description'       => 'Short description of the client.',
        'single'            => true,
        'default'           => '',
        'sanitize_callback' => 'sanitize_textarea_field',
        'auth_callback'     => 'headlesswp_meta_auth',
        'show_in_rest'      => true,
    ] );

    register_post_meta( 'project', '_client_website', [
        'type'              => 'string',
        'description'       => 'Client website URL.',
        'single'            => true,
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
        'auth_callback'     => 'headlesswp_meta_auth',
        'show_in_rest'      => [
            'schema' => [
                'type'   => 'string',
                'format' => 'uri',
            ],
        ],
    ] );

    // Stores the attachment ID; URL resolved in REST via api.php.
    register_post_meta( 'project', '_client_logo_id', [
        'type'              => 'integer',
        'description'       => 'Attachment ID of the client logo.',
        'single'            => true,
        'default'           => 0,
        'sanitize_callback' => 'absint',
        'auth_callback'     => 'headlesswp_meta_auth',
        'show_in_rest'      => true,
    ] );

    register_post_meta( 'project', '_client_testimonial', [
        'type'              => 'string',
        'description'       => 'Testimonial quote from the client.',
        'single'            => true,
        'default'           => '',
        'sanitize_callback' => 'sanitize_textarea_field',
        'auth_callback'     => 'headlesswp_meta_auth',
        'show_in_rest'      => true,
    ] );

    // ── Project fields ─────────────────────────────────────────────────────

    register_post_meta( 'project', '_project_challenge', [
        'type'              => 'string',
        'description'       => 'Short description of the challenge addressed.',
        'single'            => true,
        'default'           => '',
        'sanitize_callback' => 'sanitize_textarea_field',
        'auth_callback'     => 'headlesswp_meta_auth',
        'show_in_rest'      => true,
    ] );

    // Stored as a JSON-encoded array of service keys.
    register_post_meta( 'project', '_project_services', [
        'type'          => 'array',
        'description'   => 'Services employed on the project.',
        'single'        => true,
        'default'       => [],
        'auth_callback' => 'headlesswp_meta_auth',
        'show_in_rest'  => [
            'schema' => [
                'type'  => 'array',
                'items' => [ 'type' => 'string' ],
            ],
        ],
    ] );

    register_post_meta( 'project', '_project_skills_tech', [
        'type'              => 'string',
        'description'       => 'Comma-separated list of skills and technologies used.',
        'single'            => true,
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => 'headlesswp_meta_auth',
        'show_in_rest'      => true,
    ] );

    register_post_meta( 'project', '_project_url', [
        'type'              => 'string',
        'description'       => 'Live project URL.',
        'single'            => true,
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
        'auth_callback'     => 'headlesswp_meta_auth',
        'show_in_rest'      => [
            'schema' => [
                'type'   => 'string',
                'format' => 'uri',
            ],
        ],
    ] );

    register_post_meta( 'project', '_project_comment', [
        'type'              => 'string',
        'description'       => 'Internal comment or note about the project.',
        'single'            => true,
        'default'           => '',
        'sanitize_callback' => 'sanitize_textarea_field',
        'auth_callback'     => 'headlesswp_meta_auth',
        'show_in_rest'      => true,
    ] );

    // ── SEO fields ──────────────────────────────────────────────────────────

    register_post_meta( 'project', '_seo_title', [
        'type'              => 'string',
        'description'       => 'SEO meta title (max 60 characters).',
        'single'            => true,
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => 'headlesswp_meta_auth',
        'show_in_rest'      => true,
    ] );

    register_post_meta( 'project', '_seo_description', [
        'type'              => 'string',
        'description'       => 'SEO meta description (max 160 characters).',
        'single'            => true,
        'default'           => '',
        'sanitize_callback' => 'sanitize_textarea_field',
        'auth_callback'     => 'headlesswp_meta_auth',
        'show_in_rest'      => true,
    ] );
}

/**
 * Auth callback — only users who can edit the post may read/write meta.
 */
function headlesswp_meta_auth( bool $allowed, string $meta_key, int $post_id ): bool {
    return current_user_can( 'edit_post', $post_id );
}

// ── 2. Register meta boxes ────────────────────────────────────────────────────

add_action( 'add_meta_boxes', 'headlesswp_register_meta_boxes' );

function headlesswp_register_meta_boxes(): void {
    // Project Details → first below the canvas.
    add_meta_box(
        'headlesswp_project_details',
        __( 'Project Details', 'headlesswp' ),
        'headlesswp_render_project_meta_box',
        'project',
        'normal',
        'high'
    );

    // Client Details → below Project Details.
    add_meta_box(
        'headlesswp_client_details',
        __( 'Client Details', 'headlesswp' ),
        'headlesswp_render_client_meta_box',
        'project',
        'normal',
        'default'
    );

    // SEO → last, below Client Details.
    add_meta_box(
        'headlesswp_seo',
        __( 'SEO', 'headlesswp' ),
        'headlesswp_render_seo_meta_box',
        'project',
        'normal',
        'low'
    );
}

// ── 3. Enqueue media uploader for the logo picker ─────────────────────────────

add_action( 'admin_enqueue_scripts', 'headlesswp_enqueue_meta_box_assets' );

function headlesswp_enqueue_meta_box_assets( string $hook ): void {
    // Only load on the project post edit screens.
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || 'project' !== $screen->post_type ) {
        return;
    }

    wp_enqueue_media();

    // Enqueue admin meta box styles.
    wp_enqueue_style(
        'headlesswp-admin-meta-boxes',
        HEADLESSWP_URI . '/assets/css/admin-meta-boxes.css',
        [],
        HEADLESSWP_VERSION
    );

    // Inline script: open the WP media library when the "Select Logo" button
    // is clicked, restrict to images, and echo the chosen attachment ID/URL
    // back into the hidden input and preview element.
    wp_add_inline_script( 'media-upload', "
(function() {
    var frame;

    document.addEventListener('click', function(e) {
        // Open picker
        if (e.target && e.target.id === 'headlesswp-logo-btn') {
            e.preventDefault();

            if (frame) { frame.open(); return; }

            frame = wp.media({
                title: 'Select Client Logo',
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                var url = (attachment.sizes && attachment.sizes.medium)
                    ? attachment.sizes.medium.url
                    : attachment.url;

                document.getElementById('_client_logo_id').value = attachment.id;
                document.getElementById('headlesswp-logo-preview').innerHTML =
                    '<img src=\"' + url + '\" style=\"max-width:200px;height:auto;margin-top:8px;border-radius:3px;\">';
                document.getElementById('headlesswp-logo-remove').style.display = 'inline-block';
            });

            frame.open();
        }

        // Remove logo
        if (e.target && e.target.id === 'headlesswp-logo-remove') {
            e.preventDefault();
            document.getElementById('_client_logo_id').value = '0';
            document.getElementById('headlesswp-logo-preview').innerHTML = '';
            document.getElementById('headlesswp-logo-remove').style.display = 'none';
        }
    });
}());
" );
}

// ── 4. Render: Client Details meta box ───────────────────────────────────────

function headlesswp_render_client_meta_box( WP_Post $post ): void {
    wp_nonce_field( 'headlesswp_client_meta_nonce', 'headlesswp_client_meta_nonce' );

    $name         = get_post_meta( $post->ID, '_client_name', true );
    $description  = get_post_meta( $post->ID, '_client_description', true );
    $website      = get_post_meta( $post->ID, '_client_website', true );
    $logo_id      = (int) get_post_meta( $post->ID, '_client_logo_id', true );
    $testimonial  = get_post_meta( $post->ID, '_client_testimonial', true );

    // Get existing logo preview URL (max 400px wide).
    $logo_preview_html = '';
    $logo_remove_display = 'none';
    if ( $logo_id > 0 ) {
        $logo_src = wp_get_attachment_image_src( $logo_id, [ 400, 9999 ] );
        if ( $logo_src ) {
            $logo_preview_html = '<img src="' . esc_url( $logo_src[0] ) . '" style="max-width:200px;height:auto;margin-top:8px;border-radius:3px;">';
            $logo_remove_display = 'inline-block';
        }
    }
    ?>
    <div class="hwp-field">
        <label for="_client_name"><?php esc_html_e( 'Client Name', 'headlesswp' ); ?></label>
        <input type="text" id="_client_name" name="_client_name"
               value="<?php echo esc_attr( $name ); ?>" placeholder="Company name">
    </div>

    <div class="hwp-field">
        <label for="_client_description"><?php esc_html_e( 'Client Description', 'headlesswp' ); ?></label>
        <textarea id="_client_description" name="_client_description"
                  placeholder="A short bio of the client…"><?php echo esc_textarea( $description ); ?></textarea>
    </div>

    <div class="hwp-field">
        <label for="_client_website"><?php esc_html_e( 'Client Website', 'headlesswp' ); ?></label>
        <input type="url" id="_client_website" name="_client_website"
               value="<?php echo esc_url( $website ); ?>" placeholder="https://example.com">
    </div>

    <div class="hwp-field">
        <label><?php esc_html_e( 'Client Logo', 'headlesswp' ); ?></label>
        <input type="hidden" id="_client_logo_id" name="_client_logo_id"
               value="<?php echo esc_attr( $logo_id ); ?>">
        <div>
            <button type="button" id="headlesswp-logo-btn" class="button button-secondary">
                <?php esc_html_e( $logo_id > 0 ? 'Change Logo' : 'Select Logo', 'headlesswp' ); ?>
            </button>
            <button type="button" id="headlesswp-logo-remove" class="button button-link-delete"
                    style="display:<?php echo esc_attr( $logo_remove_display ); ?>;margin-left:8px;">
                <?php esc_html_e( 'Remove', 'headlesswp' ); ?>
            </button>
        </div>
        <div id="headlesswp-logo-preview"><?php echo $logo_preview_html; // Already escaped above. ?></div>
    </div>

    <div class="hwp-field">
        <label for="_client_testimonial"><?php esc_html_e( 'Client Testimonial', 'headlesswp' ); ?></label>
        <textarea id="_client_testimonial" name="_client_testimonial"
                  placeholder="Working with you was fantastic…"><?php echo esc_textarea( $testimonial ); ?></textarea>
    </div>
    <?php
}

// ── 5. Render: Project Details meta box ──────────────────────────────────────

function headlesswp_render_project_meta_box( WP_Post $post ): void {
    wp_nonce_field( 'headlesswp_project_meta_nonce', 'headlesswp_project_meta_nonce' );

    $challenge   = get_post_meta( $post->ID, '_project_challenge', true );
    $services    = get_post_meta( $post->ID, '_project_services', true );
    $services    = is_array( $services ) ? $services : [];
    $skills_tech = get_post_meta( $post->ID, '_project_skills_tech', true );
    $project_url = get_post_meta( $post->ID, '_project_url', true );
    $comment     = get_post_meta( $post->ID, '_project_comment', true );
    ?>
    <div class="hwp-grid">

        <div class="hwp-field hwp-full">
            <label for="_project_challenge"><?php esc_html_e( 'The Challenge', 'headlesswp' ); ?></label>
            <textarea id="_project_challenge" name="_project_challenge"
                      style="width:100%;min-height:70px;resize:vertical;"
                      placeholder="Describe the core challenge this project solved…"><?php echo esc_textarea( $challenge ); ?></textarea>
        </div>

        <div class="hwp-field hwp-full">
            <label><?php esc_html_e( 'Services Employed', 'headlesswp' ); ?></label>
            <div class="hwp-services">
                <?php foreach ( headlesswp_get_services() as $key => $label ) : ?>
                    <label>
                        <input type="checkbox"
                               name="_project_services[]"
                               value="<?php echo esc_attr( $key ); ?>"
                               <?php checked( in_array( $key, $services, true ) ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="hwp-field">
            <label for="_project_skills_tech"><?php esc_html_e( 'Skills &amp; Tech', 'headlesswp' ); ?></label>
            <input type="text" id="_project_skills_tech" name="_project_skills_tech"
                   style="width:100%;"
                   value="<?php echo esc_attr( $skills_tech ); ?>"
                   placeholder="React, TypeScript, GSAP, Figma">
            <p class="description" style="margin-top:4px;"><?php esc_html_e( 'Comma-separated.', 'headlesswp' ); ?></p>
        </div>

        <div class="hwp-field">
            <label for="_project_url"><?php esc_html_e( 'Project URL', 'headlesswp' ); ?></label>
            <input type="url" id="_project_url" name="_project_url"
                   style="width:100%;"
                   value="<?php echo esc_url( $project_url ); ?>"
                   placeholder="https://client-site.com">
        </div>

        <div class="hwp-field hwp-full">
            <label for="_project_comment"><?php esc_html_e( 'Our Comment', 'headlesswp' ); ?></label>
            <textarea id="_project_comment" name="_project_comment"
                      style="width:100%;min-height:70px;resize:vertical;"
                      placeholder="Internal notes or a public-facing quote about the work…"><?php echo esc_textarea( $comment ); ?></textarea>
        </div>

    </div>
    <?php
}

// ── 6. Render: SEO meta box ──────────────────────────────────────────────────

function headlesswp_render_seo_meta_box( WP_Post $post ): void {
    wp_nonce_field( 'headlesswp_seo_meta_nonce', 'headlesswp_seo_meta_nonce' );

    $seo_title       = get_post_meta( $post->ID, '_seo_title', true );
    $seo_description = get_post_meta( $post->ID, '_seo_description', true );
    ?>
    <div class="hwp-seo-field">
        <label for="_seo_title">
            <?php esc_html_e( 'Meta Title', 'headlesswp' ); ?>
            <span class="hwp-char-count" id="hwp-title-count">
                <?php echo esc_html( strlen( $seo_title ) ); ?>/60
            </span>
        </label>
        <input type="text"
               id="_seo_title"
               name="_seo_title"
               maxlength="60"
               value="<?php echo esc_attr( $seo_title ); ?>"
               placeholder="<?php echo esc_attr( get_the_title( $post ) ); ?>">
        <p class="description"><?php esc_html_e( 'Recommended: 50–60 characters. Longer titles are cut off in search results.', 'headlesswp' ); ?></p>
    </div>

    <div class="hwp-seo-field">
        <label for="_seo_description">
            <?php esc_html_e( 'Meta Description', 'headlesswp' ); ?>
            <span class="hwp-char-count" id="hwp-desc-count">
                <?php echo esc_html( strlen( $seo_description ) ); ?>/160
            </span>
        </label>
        <textarea id="_seo_description"
                  name="_seo_description"
                  maxlength="160"
                  placeholder="A concise summary of the project for search engines…"><?php echo esc_textarea( $seo_description ); ?></textarea>
        <p class="description"><?php esc_html_e( 'Recommended: 120–160 characters. Longer descriptions are truncated in search results.', 'headlesswp' ); ?></p>
    </div>

    <script>
    (function() {
        var titleInput = document.getElementById('_seo_title');
        var descInput  = document.getElementById('_seo_description');
        var titleCount = document.getElementById('hwp-title-count');
        var descCount  = document.getElementById('hwp-desc-count');

        function updateTitle() {
            var len = titleInput.value.length;
            titleCount.textContent = len + '/60';
            titleCount.classList.toggle('hwp-over', len >= 60);
        }

        function updateDesc() {
            var len = descInput.value.length;
            descCount.textContent = len + '/160';
            descCount.classList.toggle('hwp-over', len >= 160);
        }

        titleInput.addEventListener('input', updateTitle);
        descInput.addEventListener('input', updateDesc);
    }());
    </script>
    <?php
}

// ── 7. Save meta ──────────────────────────────────────────────────────────────

add_action( 'save_post_project', 'headlesswp_save_project_meta', 10, 2 );

function headlesswp_save_project_meta( int $post_id, WP_Post $post ): void {
    // Bail on autosave, revisions, and AJAX bulk-edit.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) )                  return;
    if ( ! current_user_can( 'edit_post', $post_id ) )      return;

    // ── Client fields ──────────────────────────────────────────────────────

    if (
        isset( $_POST['headlesswp_client_meta_nonce'] ) &&
        wp_verify_nonce( sanitize_key( $_POST['headlesswp_client_meta_nonce'] ), 'headlesswp_client_meta_nonce' )
    ) {
        update_post_meta( $post_id, '_client_name',
            sanitize_text_field( $_POST['_client_name'] ?? '' ) );

        update_post_meta( $post_id, '_client_description',
            sanitize_textarea_field( $_POST['_client_description'] ?? '' ) );

        update_post_meta( $post_id, '_client_website',
            esc_url_raw( $_POST['_client_website'] ?? '' ) );

        update_post_meta( $post_id, '_client_logo_id',
            absint( $_POST['_client_logo_id'] ?? 0 ) );

        update_post_meta( $post_id, '_client_testimonial',
            sanitize_textarea_field( $_POST['_client_testimonial'] ?? '' ) );
    }

    // ── Project fields ─────────────────────────────────────────────────────

    if (
        isset( $_POST['headlesswp_project_meta_nonce'] ) &&
        wp_verify_nonce( sanitize_key( $_POST['headlesswp_project_meta_nonce'] ), 'headlesswp_project_meta_nonce' )
    ) {
        update_post_meta( $post_id, '_project_challenge',
            sanitize_textarea_field( $_POST['_project_challenge'] ?? '' ) );

        // Validate submitted service keys against the known constant list.
        $submitted_services = (array) ( $_POST['_project_services'] ?? [] );
        $valid_services     = array_keys( headlesswp_get_services() );
        $clean_services     = array_values(
            array_intersect( $submitted_services, $valid_services )
        );
        update_post_meta( $post_id, '_project_services', $clean_services );

        update_post_meta( $post_id, '_project_skills_tech',
            sanitize_text_field( $_POST['_project_skills_tech'] ?? '' ) );

        update_post_meta( $post_id, '_project_url',
            esc_url_raw( $_POST['_project_url'] ?? '' ) );

        update_post_meta( $post_id, '_project_comment',
            sanitize_textarea_field( $_POST['_project_comment'] ?? '' ) );
    }

    // ── SEO fields ─────────────────────────────────────────────────────────

    if (
        isset( $_POST['headlesswp_seo_meta_nonce'] ) &&
        wp_verify_nonce( sanitize_key( $_POST['headlesswp_seo_meta_nonce'] ), 'headlesswp_seo_meta_nonce' )
    ) {
        update_post_meta( $post_id, '_seo_title',
            substr( sanitize_text_field( $_POST['_seo_title'] ?? '' ), 0, 60 ) );

        update_post_meta( $post_id, '_seo_description',
            substr( sanitize_textarea_field( $_POST['_seo_description'] ?? '' ), 0, 160 ) );
    }
}
