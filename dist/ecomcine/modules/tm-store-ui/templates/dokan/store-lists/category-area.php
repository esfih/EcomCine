/**
 * Store listing category-area template — EcomCine override.
 *
 * Uses ecomcine_person_category GET param and reads categories from the
 * EcomCine Person Category Registry (zero Dokan dependency).
 *
 * @package EcomCine
 */

defined( 'ABSPATH' ) || exit;

$_selected_slug = isset( $_GET['ecomcine_person_category'] )
	? sanitize_text_field( wp_unslash( $_GET['ecomcine_person_category'] ) )
	: '';

// Populate $categories from EcomCine registry; fall back to Dokan-provided $categories variable.
if ( class_exists( 'EcomCine_Person_Category_Registry', false ) ) {
	$categories = EcomCine_Person_Category_Registry::get_all();
}
// $categories may also be passed from the parent Dokan template — keep it if set and registry is empty.
if ( empty( $categories ) ) {
	$categories = array();
}

$_selected_label = '';
if ( $_selected_slug && ! empty( $categories ) ) {
	foreach ( $categories as $_cat ) {
		if ( $_cat['slug'] === $_selected_slug ) {
			$_selected_label = $_cat['name'];
			break;
		}
	}
}

$_display_text = $_selected_label ?: esc_html__( 'All Categories', 'ecomcine' );
?>

<div class="store-lists-other-filter-wrap">
    <?php do_action( 'ecomcine_before_person_filter_category' ); ?>

    <?php if ( ! empty( $categories ) ) : ?>
        <div class="store-lists-category item">
            <div class="category-input">
                <span class="category-label">
                    <?php esc_html_e( 'Category:', 'ecomcine' ); ?>
                </span>
                <span class="category-items">
                    <?php echo esc_html( $_display_text ); ?>
                </span>

                <span class="ecomcine-icon dashicons dashicons-arrow-down-alt2"></span>
            </div>

            <div class="category-box ecomcine-person-category" style="display: none">
                <ul>
                    <?php foreach ( $categories as $category ) : ?>
                        <li data-slug="<?php echo esc_attr( $category['slug'] ); ?>"
                            <?php if ( $category['slug'] === $_selected_slug ) echo 'class="selected ecomcine-btn-primary"'; ?>>
                            <?php echo esc_html( $category['name'] ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <?php do_action( 'ecomcine_after_person_filter_category' ); ?>
</div>
