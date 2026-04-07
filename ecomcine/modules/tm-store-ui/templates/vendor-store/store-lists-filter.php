<?php
/**
 * Store listing filter template migrated from the legacy theme layer.
 *
 * Overrides: dokan-lite/templates/store-lists-filter.php (v2.9.30)
 *
 * Changes from original:
 *   – "View all filters" + "Clear all filters" links rendered in PHP
 *     inside .apply-filter (were previously JS-injected on document.ready,
 *     causing a ~2-3 s visible delay).
 *
 * @package Dokan/Templates
 * @version 2.9.30
 */

defined( 'ABSPATH' ) || exit;
?>

<?php do_action( 'dokan_before_store_lists_filter', $stores ); ?>

<div id="dokan-store-listing-filter-wrap">
    <?php do_action( 'dokan_before_store_lists_filter_left', $stores ); ?>
    <div class="left">
        <p class="item store-count">
            <?php
            // translators: 1) number of stores
            printf( esc_html( _n( 'Total store showing: %s', 'Total stores showing: %s', $number_of_store, 'dokan-lite' ) ), esc_html( number_format_i18n( $number_of_store ) ) );
            ?>
        </p>
    </div>

    <?php do_action( 'dokan_before_store_lists_filter_right', $stores ); ?>
    <div class="right">
        <div class="item">
            <div class="dokan-icons">
                <div class="dokan-icon-div"></div>
                <div class="dokan-icon-div"></div>
                <div class="dokan-icon-div"></div>
            </div>

            <button class="dokan-store-list-filter-button dokan-btn dokan-btn-theme">
                <?php esc_html_e( 'Filter', 'dokan-lite' ); ?>
            </button>
        </div>

        <form name="stores_sorting" class="sort-by item" method="get">
            <label><?php esc_html_e( 'Sort by', 'dokan-lite' ); ?>:</label>

            <select name="stores_orderby" id="stores_orderby" aria-label="<?php esc_html_e( 'Sort by', 'dokan-lite' ); ?>">
                <?php foreach ( $sort_filters as $key => $filter ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $sort_by, $key ); ?> ><?php echo esc_html( $filter ); ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="toggle-view item">
            <span class="dashicons dashicons-screenoptions" data-view="grid-view"></span>
            <span class="dashicons dashicons-menu-alt" data-view="list-view"></span>
        </div>
    </div>
</div>

<?php do_action( 'dokan_before_store_lists_filter_form', $stores ); ?>

<form role="store-list-filter" method="get" name="dokan_store_lists_filter_form" id="dokan-store-listing-filter-form-wrap">

    <?php
    do_action( 'dokan_before_store_lists_filter_search', $stores );

    if ( apply_filters( 'dokan_load_store_lists_filter_search_bar', true ) ) :
        ?>
        <div class="store-search grid-item">
            <input type="search" class="store-search-input" name="dokan_seller_search" placeholder="<?php esc_attr_e( 'Search Vendors', 'dokan-lite' ); ?>">
        </div>
        <?php
    endif;

    do_action( 'dokan_before_store_lists_filter_apply_button', $stores );
    ?>

    <div class="apply-filter" style="display:none">
        <?php /* Cancel and Apply are now in #filter-actions-cell (6th grid column).
               * This stub is kept so Dokan's JS can bind to #cancel-filter-btn
               * without throwing errors; both buttons are hidden here.          */ ?>
        <button id="cancel-filter-btn" class="dokan-btn dokan-btn-theme"><?php esc_html_e( 'Cancel', 'dokan-lite' ); ?></button>
    </div>

    <?php do_action( 'dokan_after_store_lists_filter_apply_button', $stores ); ?>
    <?php wp_nonce_field( 'dokan_store_lists_filter_nonce', '_store_filter_nonce', false ); ?>
</form>
