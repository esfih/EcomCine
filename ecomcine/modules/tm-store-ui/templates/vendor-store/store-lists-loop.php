<div id="dokan-seller-listing-wrap" class="grid-view">
    <div class="seller-listing-content">
        <?php if ( $sellers['users'] ) : ?>
            <ul class="dokan-seller-wrap">
                <?php
                foreach ( $sellers['users'] as $seller ) {
                    $vendor                   = dokan()->vendor->get( $seller->ID );
                    $store_name               = $vendor->get_shop_name();
                    $store_url                = $vendor->get_shop_url();
                    $store_rating             = $vendor->get_rating();
                    $is_store_featured        = $vendor->is_featured();
                    $store_phone              = $vendor->get_phone();
                    $store_info               = dokan_get_store_info( $seller->ID );
                    $store_address            = dokan_get_seller_short_address( $seller->ID );
                    $store_banner_url         = $vendor->get_banner();
                    $show_store_open_close    = dokan_get_option( 'store_open_close', 'dokan_appearance', 'on' );
                    $dokan_store_time_enabled = isset( $store_info['dokan_store_time_enabled'] ) ? $store_info['dokan_store_time_enabled'] : '';
                    $store_open_is_on         = ( 'on' === $show_store_open_close && 'yes' === $dokan_store_time_enabled && ! $is_store_featured ) ? 'store_open_is_on' : '';
                    ?>

                    <li class="dokan-single-seller woocommerce coloum-<?php echo esc_attr( $per_row ); ?>">
                        <div class="store-wrapper">
                            <div class="store-header">
                                <div class="store-banner">
                                    <a href="<?php echo esc_url( $store_url ); ?>">
                                        <img src="<?php echo is_array( $store_banner_url ) ? esc_attr( $store_banner_url[0] ) : esc_attr( $store_banner_url ); ?>"
                                            loading="lazy"
                                            decoding="async"
                                            width="640" height="360">
                                    </a>
                                </div>
                                <!-- Featured label removed from header, will be rendered below vendor name -->
                            </div>

                            <div class="store-content">
                                <div class="store-data-container">
                                    <div class="featured-favourite">
                                        <?php do_action( 'dokan_seller_listing_after_featured', $seller, $store_info ); ?>
                                    </div>
                                    <?php if ( 'on' === $show_store_open_close && 'yes' === $dokan_store_time_enabled ) : ?>
                                        <?php if ( dokan_is_store_open( $seller->ID ) ) { ?>
                                            <span class="dokan-store-is-open-close-status dokan-store-is-open-status" title="<?php esc_attr_e( 'Store is Open', 'dokan-lite' ); ?>"><?php esc_html_e( 'Open', 'dokan-lite' ); ?></span>
                                        <?php } else { ?>
                                            <span class="dokan-store-is-open-close-status dokan-store-is-closed-status" title="<?php esc_attr_e( 'Store is Closed', 'dokan-lite' ); ?>"><?php esc_html_e( 'Closed', 'dokan-lite' ); ?></span>
                                        <?php } ?>
                                    <?php endif ?>

                                    <div class="store-data <?php echo esc_attr( $store_open_is_on ); ?>">
                                        <div class="vendor-name-featured" style="position:relative; text-align:left; display:inline-block;">
                                            <h2 style="text-align:left; margin-bottom:0; display:inline-block; vertical-align:top;">
                                                <a href="<?php echo esc_attr( $store_url ); ?>"><?php echo esc_html( $store_name ); ?></a> <?php do_action( 'dokan_store_list_loop_after_store_name', $vendor ); ?>
                                            </h2>
                                            <?php if ( $is_store_featured ) : ?>
                                                <div class="featured-label">
                                                    <?php esc_html_e( 'Featured', 'dokan-lite' ); ?>
                                                </div>
                                            <?php endif ?>
                                        </div>

                                        <?php if ( ! empty( $store_rating['count'] ) ) : ?>
                                            <div class="dokan-seller-rating"
                                                title="
                                                <?php
                                                    printf(
                                                        // translators: 1) seller rating
                                                        esc_attr__( 'Rated %s out of 5', 'dokan-lite' ), esc_html( number_format_i18n( $store_rating['rating'] ) )
                                                    );
                                                ?>
                                                ">
                                                <?php echo wp_kses_post( dokan_generate_ratings( $store_rating['rating'], 5 ) ); ?>
                                                <p class="rating">
                                                    <?php
                                                    echo esc_html(
                                                        // translators: 1) seller rating
                                                        sprintf( __( '%s out of 5', 'dokan-lite' ), number_format_i18n( $store_rating['rating'] ) )
                                                    );
                                                    ?>
                                                </p>
                                            </div>
                                        <?php endif ?>

                                        <?php
                                        $category_display = function_exists( 'tm_store_ui_get_person_category_label' )
                                            ? tm_store_ui_get_person_category_label( $seller->ID )
                                            : '';
                                        if ( '' !== $category_display ) {
                                            echo '<div class="store-categories-wrapper editable-field" style="text-align:left; margin-top:6px;"><div class="field-display" style="text-align:left;"><span class="store-categories-display" style="text-align:left;">' . esc_html( $category_display ) . '</span></div></div>';
                                        }
                                        ?>

                                        <?php
                                        // Geo Location (MapBox Country Icon City > State)
                                        if ( function_exists( 'tm_get_vendor_geo_location_display' ) ) {
                                            $geo_location_display = tm_get_vendor_geo_location_display( $seller->ID, $store_info, $store_address );
                                            if ( ! empty( $geo_location_display ) ) {
                                                echo '<div class="location-wrapper editable-field" style="text-align:left; margin-top:6px;"><div class="field-display" style="text-align:left;"><span class="location-display" style="text-align:left;">' . wp_kses_post( $geo_location_display ) . '</span></div></div>';
                                            }
                                        }
                                        ?>

                                        <?php if ( ! dokan_is_vendor_info_hidden( 'address' ) && $store_address ) : ?>
                                            <?php
                                            $allowed_tags = [
                                                'span' => [
                                                    'class' => [],
                                                ],
                                                'br'   => [],
                                            ];
                                            ?>
                                            <p class="store-address"><?php echo wp_kses( $store_address, $allowed_tags ); ?></p>
                                        <?php endif ?>

                                        <?php if ( ! dokan_is_vendor_info_hidden( 'phone' ) && $store_phone ) { ?>
                                            <p class="store-phone">
                                                <?php echo TM_Icons::svg( 'phone-alt' ); ?> <?php echo esc_html( $store_phone ); ?>
                                            </p>
                                        <?php } ?>

                                        <?php do_action( 'dokan_seller_listing_after_store_data', $seller, $store_info ); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="store-footer">
                                <div class="seller-avatar">
                                    <a href="<?php echo esc_url( $store_url ); ?>">
                                        <img src="<?php echo esc_url( $vendor->get_avatar() ); ?>"
                                            alt="<?php echo esc_attr( $vendor->get_shop_name() ); ?>"
                                            width="150" height="150"
                                            loading="lazy"
                                            decoding="async" />
                                    </a>
                                </div>

                                <?php do_action( 'dokan_seller_listing_footer_content', $seller, $store_info ); ?>
                            </div>
                        </div>
                    </li>

                <?php } ?>
                <div class="dokan-clearfix"></div>
            </ul> <!-- .dokan-seller-wrap -->

            <?php
            $user_count   = $sellers['count'];
            $num_of_pages = ceil( $user_count / $limit );

            if ( $num_of_pages > 1 ) {
                echo '<div class="pagination-container clearfix">';

                // Rebuild $pagination_base safely: Dokan's default uses str_replace($post->ID, '%#%', url)
                // which corrupts the port when $post->ID digits appear in the port number (e.g. ID=18, port=8180).
                // Instead, build the base from the page-1 URL + a path-only page/%#%/ suffix.
                $safe_base = trailingslashit( esc_url( get_pagenum_link( 1 ) ) ) . 'page/%#%/';

                $pagination_args = [
                    'current'   => $paged,
                    'total'     => $num_of_pages,
                    'base'      => $safe_base,
                    'type'      => 'array',
                    'prev_text' => __( '&larr; Previous', 'dokan-lite' ),
                    'next_text' => __( 'Next &rarr;', 'dokan-lite' ),
                ];

                if ( ! empty( $search_query ) ) {
                    $pagination_args['add_args'] = [
                        'dokan_seller_search' => $search_query,
                    ];
                }

                $page_links = paginate_links( $pagination_args );

                if ( $page_links ) {
                    $pagination_links = '<div class="pagination-wrap">';
                    $pagination_links .= '<ul class="pagination"><li>';
                    $pagination_links .= join( "</li>\n\t<li>", $page_links );
                    $pagination_links .= "</li>\n</ul>\n";
                    $pagination_links .= '</div>';

                    echo wp_kses_post( $pagination_links );
                }

                echo '</div>';
            }
            ?>

        <?php else : ?>
            <p class="dokan-error"><?php esc_html_e( 'No talent found!', 'dokan-lite' ); ?></p>
        <?php endif; ?>
    </div>
</div>
