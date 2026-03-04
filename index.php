<?php
/**
 * index.php
 *
 * WordPress requires at least one template file. In headless mode all
 * public-facing requests are intercepted and redirected to the WP admin.
 * This file is the last-resort fallback and should never be reached in
 * normal operation.
 *
 * @package HeadlessWP
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Fallback redirect to admin — the headless.php hook should have already
// handled this earlier in the request lifecycle.
wp_redirect( admin_url(), 302 );
exit;
