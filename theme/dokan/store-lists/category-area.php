<?php
/**
 * Store listing category-area template — Astra Child theme override.
 *
 * Overrides: dokan-pro/templates/store-lists/category-area.php (v3.0.0)
 *
 * Changes from original:
 *   – Reads $_GET['dokan_seller_category'] server-side so the selected category
 *     name is rendered in .category-items immediately (no JS-on-load flash from
 *     "All Categories" → actual name).
 *   – Adds `selected` class to the active <li> in PHP so state is correct
 *     before JS runs.
 *
 * @package Dokan/Templates
 * @version 3.0.0
 */

defined( 'ABSPATH' ) || exit;

$_selected_slug = isset( $_GET['dokan_seller_category'] )
	? sanitize_text_field( $_GET['dokan_seller_category'] )
	: '';

// Resolve the human-readable label from the category list passed by Dokan Pro.
$_selected_label = '';
if ( $_selected_slug && ! empty( $categories ) ) {
	foreach ( $categories as $_cat ) {
		if ( $_cat['slug'] === $_selected_slug ) {
			$_selected_label = $_cat['name'];
			break;
		}
	}
}

$_display_text = $_selected_label ?: esc_html__( 'All Categories', 'dokan' );
?>

<div class="store-lists-other-filter-wrap">
    <?php do_action( 'dokan_before_store_lists_filter_category', $stores ); ?>

    <?php if ( ! empty( $categories ) ) : ?>
        <div class="store-lists-category item">
            <div class="category-input">
                <span class="category-label">
                    <?php esc_html_e( 'Category:', 'dokan' ); ?>
                </span>
                <span class="category-items">
                    <?php echo esc_html( $_display_text ); ?>
                </span>

                <span class="dokan-icon dashicons dashicons-arrow-down-alt2"></span>
            </div>

            <div class="category-box store_category" style="display: none">
                <ul>
                    <?php foreach ( $categories as $category ) : ?>
                        <li data-slug="<?php echo esc_attr( $category['slug'] ); ?>"
                            <?php if ( $category['slug'] === $_selected_slug ) echo 'class="selected dokan-btn-theme"'; ?>>
                            <?php echo esc_html( $category['name'] ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <?php do_action( 'dokan_after_store_lists_filter_category', $stores ); ?>
</div>
