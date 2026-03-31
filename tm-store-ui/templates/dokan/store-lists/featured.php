<?php
/**
 * Store listing featured filter — Astra Child theme override.
 *
 * Overrides: dokan-pro/templates/store-lists/featured.php (v3.0.0)
 *
 * Changes from original:
 *   – Reads $_GET['featured'] server-side so the toggle is pre-checked
 *     on filtered page loads (the original template never restored state).
 *
 * @package Dokan/Templates
 * @version 3.0.0
 */

defined( 'ABSPATH' ) || exit;

$is_featured = isset( $_GET['featured'] ) && $_GET['featured'] === 'yes';
?>

<div class="featured item">
    <label for="featured">
        <?php esc_html_e( 'Featured', 'dokan' ); ?>
    </label>
    <input type="checkbox" class="dokan-toogle-checkbox" id="featured" name="featured" value="yes" <?php checked( $is_featured, true ); ?>>
</div>
