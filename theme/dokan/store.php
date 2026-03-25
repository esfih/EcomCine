<?php
/**
 * Child-theme override: streamlined Dokan store page.
 * Removes default Dokan tabs/all-store-content/vendor products output; keeps header and custom frame hook.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$store_user   = dokan()->vendor->get( get_query_var( 'author' ) );
$store_info   = $store_user->get_shop_info();
$map_location = $store_user->get_location();
$layout       = 'full'; // force full-width layout (no sidebar)

get_header( 'shop' );
?>

<div class="dokan-store-wrap layout-<?php echo esc_attr( $layout ); ?>">
    <div id="dokan-primary" class="dokan-single-store dokan-store-full-width">
        <?php dokan_get_template_part( 'store-header' ); ?>
    </div>
</div>

<?php get_footer( 'shop' ); ?>
