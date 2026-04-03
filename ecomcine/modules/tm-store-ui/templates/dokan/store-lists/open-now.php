<?php
/**
 * The template for displaying verified filter in store lists filter
 *
 * Overridden to change "Open Now" to "Verified" vendors filter
 *
 * @package Dokan/Templates
 * @version 3.0.0
 */

defined( 'ABSPATH' ) || exit;

// Check if verified filter is active
$is_verified = isset( $_GET['verified'] ) && $_GET['verified'] === 'yes';
?>

<div class="open-now item">
    <label for="verified">
        <?php esc_html_e( 'Verified', 'tm-store-ui' ); ?>:
    </label>
    <input type="checkbox" class="dokan-toogle-checkbox" id="verified" name="verified" value="yes" <?php checked( $is_verified, true ); ?>>
</div>

<?php
// Profile Level dropdown — shown to the right of the Verified toggle.
$_dir = defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR . 'templates/dokan/store-lists/profile-level-filter.php' : get_stylesheet_directory() . '/dokan/store-lists/profile-level-filter.php';
if ( file_exists( $_dir ) ) {
    include $_dir;
}
?>
