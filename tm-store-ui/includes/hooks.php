<?php
/**
 * All WordPress hook registrations extracted from the legacy theme layer.
 *
 * These fire regardless of which theme is active.
 *
 * @package TM_Store_UI
 */

defined( 'ABSPATH' ) || exit;

// =============================================================================
// Admin bar: hide on preonboard / admin-editing store pages.
// =============================================================================
add_filter( 'show_admin_bar', function( $show ) {
	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) { return $show; }
	if ( ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) { return $show; }
	$vendor_id     = absint( get_query_var( 'author' ) );
	if ( ! $vendor_id ) { return $show; }
	$is_preonboard = (bool) get_user_meta( $vendor_id, 'tm_preonboard', true );
	$is_admin_editing = function_exists( 'tm_can_edit_vendor_profile' )
		? tm_can_edit_vendor_profile( $vendor_id ) : false;
	return ( $is_preonboard || $is_admin_editing ) ? false : $show;
} );

// =============================================================================
// Frontend HTML cleanup.
// =============================================================================
add_action( 'template_redirect', function() {
	if ( is_admin() || is_feed() || is_customize_preview() ) { return; }
	if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) { return; }
	ob_start( 'tm_cleanup_frontend_shell_markup' );
}, 0 );

// =============================================================================
// Suppress bundled theme header when tm-store-ui renders the cinematic header.
// =============================================================================
add_action( 'template_redirect', function() {
	if (
		( function_exists( 'tm_store_lists_is_listing_page' ) && tm_store_lists_is_listing_page() )
		|| ( function_exists( 'ecomcine_is_person_page' ) && ecomcine_is_person_page() )
		|| ( function_exists( 'tm_is_showcase_page' ) && tm_is_showcase_page() )
		|| ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() )
	) {
		$GLOBALS['ecomcine_suppress_site_header'] = true;
		$GLOBALS['ecomcine_suppress_header'] = true;
	}
}, 1 );

// =============================================================================
// Eliminate Dokan Mapbox assets on store pages (loaded on-demand instead).
// =============================================================================
if ( ! function_exists( 'tm_remove_dokan_mapbox_on_store_page' ) ) {
	function tm_remove_dokan_mapbox_on_store_page() {
		if ( ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) { return; }
		foreach ( [ 'dokan-mapbox-gl', 'dokan-mapbox-gl-geocoder' ] as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
		foreach ( [ 'dokan-mapbox-gl-geocoder', 'dokan-maps' ] as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
	}
}
add_action( 'dokan_enqueue_scripts', 'tm_remove_dokan_mapbox_on_store_page', 20 );

if ( ! function_exists( 'tm_strip_mapbox_resource_hints' ) ) {
	function tm_strip_mapbox_resource_hints( $urls, $relation_type ) {
		if ( ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) { return $urls; }
		if ( 'dns-prefetch' !== $relation_type && 'preconnect' !== $relation_type ) { return $urls; }
		return array_values( array_filter( $urls, function( $url ) {
			return false === strpos( $url, 'api.mapbox.com' );
		} ) );
	}
}
add_filter( 'wp_resource_hints', 'tm_strip_mapbox_resource_hints', 10, 2 );

// =============================================================================
// Remove Google-hosted assets on the frontend.
// =============================================================================
if ( ! function_exists( 'tm_remove_google_assets' ) ) {
	function tm_remove_google_assets() {
		if ( is_admin() ) { return; }
		if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
			wp_dequeue_style( 'jquery-ui-style' );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'tm_remove_google_assets', 30 );

if ( ! function_exists( 'tm_strip_google_resource_hints' ) ) {
	function tm_strip_google_resource_hints( $urls, $relation_type ) {
		if ( is_admin() ) { return $urls; }
		if ( 'dns-prefetch' !== $relation_type && 'preconnect' !== $relation_type ) { return $urls; }
		return array_values( array_filter( $urls, function( $url ) {
			return false === strpos( $url, 'fonts.googleapis.com' )
				&& false === strpos( $url, 'fonts.gstatic.com' )
				&& false === strpos( $url, 'ajax.googleapis.com' );
		} ) );
	}
}
add_filter( 'wp_resource_hints', 'tm_strip_google_resource_hints', 10, 2 );

// Minimal emoji cleanup.
add_action( 'init', function() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
} );

// =============================================================================
// Remove WooCommerce + block assets on vendor store pages.
// =============================================================================
if ( ! function_exists( 'tm_remove_woocommerce_assets_on_store_page' ) ) {
	function tm_remove_woocommerce_assets_on_store_page() {
		if ( is_admin() || ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) { return; }
		$vendor_id = absint( get_query_var( 'author' ) );
		$can_edit  = $vendor_id && function_exists( 'tm_can_edit_vendor_profile' )
			? tm_can_edit_vendor_profile( $vendor_id ) : false;
		$style_handles = [
			'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general',
			'woocommerce-inline', 'wc-blocks-style',
			'wp-block-library', 'global-styles', 'global-styles-inline', 'dashicons',
		];
		if ( $can_edit ) {
			$style_handles = array_values( array_diff( $style_handles, [ 'dashicons' ] ) );
		}
		foreach ( $style_handles as $handle ) { wp_dequeue_style( $handle ); }
		foreach ( [ 'woocommerce', 'wc-add-to-cart', 'wc-cart-fragments', 'wc-checkout',
		            'wc-country-select', 'wc-address-i18n', 'wc-jquery-blockui', 'js-cookie' ] as $handle ) {
			wp_dequeue_script( $handle );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'tm_remove_woocommerce_assets_on_store_page', 100 );

// Remove Gutenberg block-editor scripts from the store-listing page.
if ( ! function_exists( 'tm_remove_editor_assets_on_store_listing' ) ) {
	function tm_remove_editor_assets_on_store_listing() {
		if ( is_admin() || ! function_exists( 'dokan_is_store_listing' ) || ! dokan_is_store_listing() ) { return; }
		foreach ( [ 'wp-preferences', 'wp-preferences-persistence' ] as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'tm_remove_editor_assets_on_store_listing', 100 );

// =============================================================================
// Modal checkout: hide WooCommerce terms + privacy text.
// =============================================================================
if ( ! function_exists( 'tm_modal_hide_woo_terms' ) ) {
	function tm_modal_hide_woo_terms( $show ) {
		return ( defined( 'TM_BOOKING_MODAL_CHECKOUT' ) && TM_BOOKING_MODAL_CHECKOUT ) ? false : $show;
	}
}
add_filter( 'woocommerce_checkout_show_terms', 'tm_modal_hide_woo_terms', 20 );

if ( ! function_exists( 'tm_modal_privacy_text_filter' ) ) {
	function tm_modal_privacy_text_filter( $text ) {
		return ( defined( 'TM_BOOKING_MODAL_CHECKOUT' ) && TM_BOOKING_MODAL_CHECKOUT ) ? '' : $text;
	}
}
add_filter( 'woocommerce_get_privacy_policy_text',      'tm_modal_privacy_text_filter', 20 );
add_filter( 'woocommerce_checkout_privacy_policy_text', 'tm_modal_privacy_text_filter', 20 );

// =============================================================================
// Vendor store page: normalize store categories on save.
// =============================================================================
add_action( 'dokan_store_profile_saved', function( $store_id, $dokan_settings ) {
	$posted = isset( $_POST['dokan_store_categories'] ) ? wp_unslash( $_POST['dokan_store_categories'] ) : [];
	if ( ! is_array( $posted ) ) { $posted = $posted ? [ $posted ] : []; }
	$settings_categories = [];
	if ( isset( $dokan_settings['categories'] ) )      { $settings_categories = $dokan_settings['categories']; }
	elseif ( isset( $dokan_settings['dokan_category'] ) ) { $settings_categories = $dokan_settings['dokan_category']; }
	if ( ! is_array( $settings_categories ) ) { $settings_categories = $settings_categories ? [ $settings_categories ] : []; }
	$categories = array_filter( array_unique( array_map( 'intval', ! empty( $posted ) ? $posted : $settings_categories ) ) );
	if ( empty( $categories ) ) { return; }
	if ( class_exists( 'EcomCine_Person_Category_Registry', false ) ) {
		EcomCine_Person_Category_Registry::set_person_categories( (int) $store_id, $categories );
	}
}, 20, 2 );

// =============================================================================
// Admin: Physical Attributes CSS injection.
// =============================================================================
add_action( 'admin_enqueue_scripts', function() {
	wp_add_inline_style( 'wp-admin', '
		.physical-attributes-section h3 { color: #D4AF37 !important; }
		.physical-attributes-section + .dokan-form-group label { color: #C0C0C0 !important; }
		label[for^="talent_"] { color: #C0C0C0 !important; }
		label[for="camera_type"], label[for="experience_level"], label[for="editing_software"],
		label[for="specialization"], label[for="years_experience"], label[for="equipment_ownership"],
		label[for="lighting_equipment"], label[for="audio_equipment"], label[for="drone_capability"] {
			color: #333333 !important;
		}
	' );
} );

// =============================================================================
// Vendor dashboard: category JS field-reordering + force-select stored cats.
// =============================================================================
add_action( 'wp_footer', function() {
	if ( ! function_exists( 'dokan_is_seller_dashboard' ) || ! dokan_is_seller_dashboard() ) { return; }
	global $wp;
	$current_url = home_url( $wp->request );
	if ( false === strpos( $current_url, '/dashboard/settings' ) ) { return; }
	$user_id          = get_current_user_id();
	$stored_categories = [];
	if ( class_exists( 'EcomCine_Person_Category_Registry', false ) ) {
		$stored_categories = array_map( 'intval', wp_list_pluck( (array) EcomCine_Person_Category_Registry::get_for_person( $user_id ), 'id' ) );
	}
	if ( ! is_array( $stored_categories ) ) { $stored_categories = $stored_categories ? [ $stored_categories ] : []; }
	$stored_categories = array_values( array_filter( array_map( 'intval', $stored_categories ) ) );
	?>
	<script type="text/javascript">
	(function($) {
		'use strict';
		function updateCategoryFields() {
			var selectedCategories = [];
			var $categorySelect = $('select[name*="categor"]').first();
			if ( ! $categorySelect.length ) {
				$categorySelect = $('#store_category, #dokan_category, select.dokan_category').first();
			}
			if ( $categorySelect.length ) {
				$categorySelect.find('option:selected').each(function() {
					selectedCategories.push( $(this).text().trim().toLowerCase() );
				});
			}
			if ( selectedCategories.length === 0 ) {
				$('.select2-selection__choice').each(function() {
					var t = ($(this).attr('title') || $(this).text()).replace('×','').trim().toLowerCase();
					if (t) selectedCategories.push(t);
				});
			}
			var $categoryFields = $('[data-category]');
			$categoryFields.css('display','none');
			if ( selectedCategories.length === 0 ) { return; }
			$categoryFields.each(function() {
				var $field = $(this);
				var fieldCategories = $field.attr('data-category').split(',');
				var shouldShow = false;
				for (var i = 0; i < selectedCategories.length; i++) {
					for (var j = 0; j < fieldCategories.length; j++) {
						if (selectedCategories[i] === fieldCategories[j].trim()) { shouldShow = true; break; }
					}
					if (shouldShow) break;
				}
				if (shouldShow) $field.css('display','block');
			});
		}
		$(document).ready(function() {
			var storedCategories = <?php echo wp_json_encode( $stored_categories ); ?>;
			if ( storedCategories && storedCategories.length ) {
				setTimeout(function() {
					var $cat = $('#dokan_store_categories');
					if ( $cat.length ) { $cat.val(storedCategories).trigger('change'); }
				}, 400);
			}
			setTimeout(function() {
				updateCategoryFields();
				$('select[name*="categor"]').on('change select2:select select2:unselect', function() {
					setTimeout(updateCategoryFields, 100);
				});
			}, 500);
		});
	})(jQuery);
	</script>
	<?php
}, 100 );

// =============================================================================
// Vendor product page: vendor identity block (tho-004).
// =============================================================================
add_action( 'woocommerce_single_product_summary', function() {
	if ( ! is_product() ) { return; }
	global $product;
	if ( ! $product ) { return; }
	$vendor_id = (int) get_post_field( 'post_author', $product->get_id() );
	if ( ! $vendor_id ) { return; }
	if ( ! class_exists( 'THO_Adapter_Registry' ) ) { return; }
	$html = THO_Adapter_Registry::get_vendor_identity_projector()
		->render_vendor_identity_block( $vendor_id, 'product-summary' );
	if ( $html ) {
		echo '<div class="dokan-vendor-on-product" style="margin:8px 0;">' . $html . '</div>';
	}
}, 6 );

add_action( 'woocommerce_before_single_product_summary', function() {
	if ( ! is_product() ) { return; }
	global $product;
	if ( ! $product ) { return; }
	mp_print_vendor_avatar_badge( $product->get_id() );
}, 19 );

add_action( 'woocommerce_before_shop_loop_item_title', function() {
	global $product;
	if ( ! $product ) { return; }
	mp_print_vendor_avatar_badge( $product->get_id() );
}, 9 );

// =============================================================================
// Save Banner Video for Vendor Profile.
// =============================================================================
add_action( 'dokan_store_profile_saved', function( $store_id, $dokan_settings ) {
	if ( ! isset( $_POST['dokan_banner_video'] ) && ! isset( $_POST['banner_video_position'] ) ) { return; }
	if ( isset( $_POST['dokan_banner_video'] ) ) {
		update_user_meta( $store_id, 'ecomcine_banner_video', absint( $_POST['dokan_banner_video'] ) );
	}
	if ( isset( $_POST['banner_video_position'] ) ) {
		update_user_meta( $store_id, 'ecomcine_banner_video_position', sanitize_text_field( $_POST['banner_video_position'] ) );
	}
}, 10, 2 );

// Banner video upload JS on vendor dashboard.
add_action( 'wp_footer', function() {
	if ( is_admin() ) { return; }
	if ( ! function_exists( 'dokan_is_seller_dashboard' ) ) { return; }
	if ( ! dokan_is_seller_dashboard()
		&& ! ( isset( $_GET['page'] ) && false !== strpos( sanitize_text_field( $_GET['page'] ), 'dokan' ) )
		&& ! is_page( 'dashboard' ) ) {
		return;
	}
	?>
	<style>
		.dokan-banner-video { border: 4px dashed #d8d8d8; margin: 0 auto 35px; max-width: 850px; text-align: center; overflow: hidden; position: relative; min-height: 150px; padding: 20px; }
		.dokan-banner-video .video-wrap { position: relative; }
		.dokan-banner-video .dokan-remove-banner-video { position: absolute; top: 10px; right: 10px; width: 40px; height: 40px; background: #000; color: #f00; font-size: 30px; line-height: 40px; text-align: center; cursor: pointer; border-radius: 50%; opacity: 0.7; }
		.dokan-banner-video .dokan-remove-banner-video:hover { opacity: 1; }
		.dokan-banner-video .button-area i { font-size: 80px; color: #d8d8d8; display: block; margin-bottom: 10px; }
	</style>
	<script>
	jQuery(document).ready(function($) {
		var videoFrame;
		$('body').on('click', 'a.dokan-banner-video-drag', function(e) {
			e.preventDefault(); e.stopPropagation();
			var uploadBtn = $(this);
			if (videoFrame) { videoFrame.open(); return; }
			videoFrame = wp.media({ title: 'Select Banner Video', button: { text: 'Use this video' }, multiple: false, library: { type: 'video' } });
			videoFrame.on('select', function() {
				var attachment = videoFrame.state().get('selection').first().toJSON();
				var wrapper = uploadBtn.closest('.dokan-banner-video');
				wrapper.find('input.dokan-video-field').val(attachment.id);
				wrapper.find('video.dokan-banner-video-preview').attr('src', attachment.url);
				uploadBtn.parent().siblings('.video-wrap', wrapper).removeClass('dokan-hide');
				uploadBtn.parent('.button-area').addClass('dokan-hide');
			});
			videoFrame.open();
		});
		$('body').on('click', 'a.dokan-remove-banner-video', function(e) {
			e.preventDefault(); e.stopPropagation();
			var imageWrap = $(this).closest('.video-wrap');
			imageWrap.find('input.dokan-video-field').val('0');
			imageWrap.addClass('dokan-hide');
			imageWrap.siblings('.button-area').removeClass('dokan-hide');
		});
	});
	</script>
	<?php
}, 9999 );

// =============================================================================
// Remove Dokan store content sections we don't need.
// =============================================================================
add_action( 'init', function() {
	if ( ! function_exists( 'dokan_is_store_page' ) ) { return; }
	remove_action( 'woocommerce_before_shop_loop', 'dokan_product_listing_filter', 10 );
	remove_action( 'dokan_store_profile_frame_after', 'dokan_after_store_content', 10 );
	if ( dokan_is_store_page() ) {
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	}
}, 20 );

// =============================================================================
// Cinematic header overlay.
// =============================================================================
if ( ! function_exists( 'tm_store_ui_convert_menu_fa_to_svg' ) ) {
	function tm_store_ui_convert_menu_fa_to_svg( $menu_html ) {
		if ( ! is_string( $menu_html ) || '' === $menu_html || ! class_exists( 'TM_Icons' ) ) {
			return $menu_html;
		}

		return preg_replace_callback(
			'/<i[^>]*class="([^"]*\bfa-building\b[^"]*)"[^>]*>\s*<\/i>/i',
			function( $matches ) {
				$class_attr = isset( $matches[1] ) ? (string) $matches[1] : '';
				$classes = preg_split( '/\s+/', trim( $class_attr ) );
				$svg_classes = [];

				foreach ( (array) $classes as $class_name ) {
					$class_name = trim( (string) $class_name );
					if ( '' === $class_name ) {
						continue;
					}
					if ( preg_match( '/^fa[srlbd]?$/i', $class_name ) ) {
						continue;
					}
					if ( 0 === strpos( strtolower( $class_name ), 'fa-' ) ) {
						continue;
					}
					$svg_classes[] = $class_name;
				}

				return TM_Icons::svg( 'building', implode( ' ', $svg_classes ) );
			},
			$menu_html
		);
	}
}

add_action( 'wp_body_open', function() {
	$on_store_page    = function_exists( 'dokan_is_store_page' ) && dokan_is_store_page();
	$on_store_listing = function_exists( 'dokan_is_store_listing' ) && dokan_is_store_listing();
	$on_platform      = is_page_template( 'tm-store-ui/page-platform' )
	                 || is_page_template( 'page-platform.php' );
	if ( ! $on_store_page && ! $on_store_listing && ! tm_is_showcase_page() && ! $on_platform ) { return; }

	$menu_html = wp_nav_menu( [
		'theme_location' => 'primary',
		'container'      => false,
		'menu_class'     => 'tm-header-menu',
		'fallback_cb'    => false,
		'echo'           => false,
		'depth'          => 1,
	] );
	$account_icon = class_exists( 'TM_Icons' )
		? TM_Icons::svg( 'user', 'tm-header-account-icon' )
		: '<i class="fas fa-user tm-header-account-icon" aria-hidden="true"></i>';
	$header_account_html = '';

	if ( $menu_html ) {
		$home_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>';
		$company_icon = class_exists( 'TM_Icons' )
			? TM_Icons::svg( 'building', 'tm-menu-icon' )
			: '<i class="fas fa-building tm-menu-icon" aria-hidden="true"></i>';
		$home_li = '<li class="menu-item tm-header-home-item">'
			. '<a href="' . esc_url( home_url( '/' ) ) . '" class="menu-link tm-header-home-link" aria-label="Home">'
			. $home_icon_svg . '</a></li>';
		$menu_html = preg_replace( '/<ul([^>]*)>/', '<ul$1>' . $home_li, $menu_html, 1 );
		$menu_html = preg_replace_callback(
			'/(<li[^>]*id="menu-item-1091"[^>]*>\s*<a[^>]*>)/',
			function( $matches ) use ( $company_icon ) {
				return $matches[1] . $company_icon;
			},
			$menu_html,
			1
		);
		$menu_html = tm_store_ui_convert_menu_fa_to_svg( $menu_html );
	}

	if ( ! is_user_logged_in() ) {
		$header_account_html = '<div class="tm-header-account-slot tm-header-account-slot--guest">'
			. '<span class="tm-header-account-pill">'
			. $account_icon
			. '<a href="#" class="tm-header-account-link tm-open-signin">Sign in</a>'
			. '<span class="tm-header-account-sep" aria-hidden="true">/</span>'
			. '<a href="#" class="tm-header-account-link tm-open-signup">Sign up</a>'
			. '</span></div>';
	} else {
		$current_user = wp_get_current_user();
		$user_id      = (int) $current_user->ID;
		$full_name    = trim( (string) get_user_meta( $user_id, 'first_name', true ) . ' ' . (string) get_user_meta( $user_id, 'last_name', true ) );
		if ( '' === $full_name ) { $full_name = $current_user->display_name; }
		$display_name = preg_split( '/\s+/', trim( $full_name ) );
		$display_name = is_array( $display_name ) && ! empty( $display_name[0] ) ? $display_name[0] : $full_name;
		if ( function_exists( 'mb_substr' ) ) {
			$display_name = mb_substr( $display_name, 0, 15 );
		} else {
			$display_name = substr( $display_name, 0, 15 );
		}
		$is_seller = function_exists( 'dokan_is_user_seller' )
			? (bool) dokan_is_user_seller( $user_id )
			: user_can( $user_id, 'dokandar' );
		$avatar_url = '';
		if ( $is_seller && function_exists( 'mp_get_vendor_avatar_url' ) ) {
			$avatar_url = mp_get_vendor_avatar_url( $user_id, 80 );
		}
		if ( empty( $avatar_url ) ) { $avatar_url = get_avatar_url( $user_id, [ 'size' => 80 ] ); }
		$account_href    = $is_seller ? '#' : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ) );
		$account_classes = $is_seller ? 'tm-header-account-link tm-open-account-seller' : 'tm-header-account-link tm-open-account-customer';
		$account_aria    = $is_seller ? 'Open account panel' : 'Open customer dashboard';
		$header_account_html = '<div class="tm-header-account-slot tm-header-account-slot--logged-in ' . ( $is_seller ? 'tm-header-account-slot--seller' : 'tm-header-account-slot--customer' ) . '">'
			. '<a href="' . esc_url( $account_href ) . '" class="' . esc_attr( $account_classes ) . '" aria-label="' . esc_attr( $account_aria ) . '">'
			. '<span class="tm-header-account-avatar"><img src="' . esc_url( $avatar_url ) . '" alt="' . esc_attr( $full_name ) . '" /></span>'
			. '<span class="tm-header-account-name">' . esc_html( $display_name ) . '</span>'
			. '</a></div>';
	}

	$social_youtube = class_exists( 'TM_Icons' ) ? TM_Icons::svg( 'youtube', 'social-icon-svg' ) : '<i class="fab fa-youtube" aria-hidden="true"></i>';
	$social_facebook = class_exists( 'TM_Icons' ) ? TM_Icons::svg( 'facebook', 'social-icon-svg' ) : '<i class="fab fa-facebook" aria-hidden="true"></i>';
	$social_instagram = class_exists( 'TM_Icons' ) ? TM_Icons::svg( 'instagram', 'social-icon-svg' ) : '<i class="fab fa-instagram" aria-hidden="true"></i>';
	$social_x = class_exists( 'TM_Icons' ) ? TM_Icons::svg( 'x-twitter', 'social-icon-svg' ) : '<i class="fab fa-x-twitter" aria-hidden="true"></i>';
	$social_linkedin = class_exists( 'TM_Icons' ) ? TM_Icons::svg( 'linkedin', 'social-icon-svg' ) : '<i class="fab fa-linkedin" aria-hidden="true"></i>';
	?>
	<div class="tm-cinematic-header" role="banner">
		<div class="tm-cinematic-header__inner">
			<div class="tm-header-left">
				<?php echo get_custom_logo(); ?>
				<div class="tm-header-platforms" aria-label="Follow us on social media">
					<span class="tm-header-platforms__label">Follow us</span>
					<span class="tm-header-platforms__icons">
						<a class="social-icon" href="https://www.youtube.com/@castingagencyco" target="_blank" rel="noopener noreferrer" aria-label="YouTube"><?php echo $social_youtube; ?></a>
						<a class="social-icon" href="https://www.facebook.com/castingagencyco" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><?php echo $social_facebook; ?></a>
						<a class="social-icon" href="https://www.instagram.com/castingagencyco" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><?php echo $social_instagram; ?></a>
						<a class="social-icon" href="https://x.com/castingagencyco" target="_blank" rel="noopener noreferrer" aria-label="X"><?php echo $social_x; ?></a>
						<a class="social-icon" href="https://www.linkedin.com/company/castingagencyco" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><?php echo $social_linkedin; ?></a>
					</span>
				</div>
			</div>
			<?php if ( $menu_html ) { ?>
				<nav class="tm-header-nav" aria-label="Primary"><?php echo $menu_html; ?></nav>
			<?php } ?>
			<div class="tm-header-actions" aria-label="Header actions">
				<?php echo $header_account_html; ?>
			</div>
		</div>
	</div>
	<?php
}, 5 );

// =============================================================================
// Vendor dashboard: address map widget CSS + JS.
// =============================================================================
add_action( 'wp_head', function() {
	if ( ! function_exists( 'dokan_is_seller_dashboard' ) || ! dokan_is_seller_dashboard() ) { return; }
	?>
	<style>
		.dokan-form-group.dokan-address-fields, #dokan_address_country,
		input[name="dokan_address[city]"], input[name="dokan_address[zip]"],
		label[for="dokan_address[country]"], label[for="dokan_address[city]"],
		label[for="dokan_address[zip]"],
		.dokan-form-group:has(#dokan_address_country),
		.dokan-form-group:has(input[name="dokan_address[city]"]),
		.dokan-form-group:has(input[name="dokan_address[zip]"]) { display: none !important; }
		.dokan-form-group:has(#setting_map) { order: -1; margin-bottom: 30px; }
		.dokan-map-wrap { border: 2px solid #D4AF37; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
		.mapboxgl-ctrl-geocoder--input { background: white !important; border: 1px solid #D4AF37 !important; border-radius: 4px !important; padding: 10px 40px 10px 12px !important; font-size: 14px !important; }
		.mapboxgl-ctrl-geocoder--input:focus { outline: none !important; border-color: #b8941f !important; box-shadow: 0 0 0 3px rgba(212,175,55,0.1) !important; }
		.mapboxgl-marker { filter: hue-rotate(45deg) saturate(1.5); }
		label[for="setting_map"] { color: #D4AF37; font-weight: 600; font-size: 16px; display: flex; align-items: center; }
		.dokan-form-group:has(#setting_map) { display: flex; flex-direction: column; align-items: center; width: 100%; margin-bottom: 30px; }
		.dokan-form-group:has(#setting_map) .dokan-w3 { width: 100%; text-align: center; margin-bottom: 10px; }
		.dokan-form-group:has(#setting_map) .dokan-w6 { width: 100%; max-width: 800px; }
		.map-tooltip-icon { margin-left: 8px; font-size: 14px; color: #888; cursor: help; }
		.dokan-geolocation-location-filters, .store-lists-other-filter-wrap .dokan-geolocation-location-filters {
			display: none !important; visibility: hidden !important; height: 0 !important; overflow: hidden !important; opacity: 0 !important;
		}
	</style>
	<script>
	jQuery(document).ready(function($) {
		$('.dokan-geolocation-location-filters').remove();
		var $mapContainer = $('.dokan-form-group:has(#setting_map), .dokan-form-group:has(label[for="setting_map"])');
		var $demographicSection = $('.dokan-form-group.demographic-availability-section');
		if ($mapContainer.length && $demographicSection.length) {
			$mapContainer.insertBefore($demographicSection);
		}
		var tooltipText = 'Type your city name or address in the search box below, then click on the map to set your exact location.';
		$('label[for="setting_map"]').html(
			'📍 Store Location <i class="fas fa-question-circle map-tooltip-icon" title="' + tooltipText + '"></i>'
		);
		$mapContainer.css('display','flex');
	});
	</script>
	<?php
}, 999 );

// =============================================================================
// Dokan: remove unused address fields (street, state; hide but keep city/zip/country).
// =============================================================================
add_filter( 'dokan_seller_address_fields', function( $fields ) {
	unset( $fields['street_1'], $fields['street_2'], $fields['state'] );
	foreach ( [ 'zip', 'city', 'country' ] as $f ) {
		if ( isset( $fields[ $f ] ) ) { $fields[ $f ]['required'] = 0; }
	}
	return $fields;
}, 10 );

// =============================================================================
// Hide Dokan geolocation filters site-wide.
// =============================================================================
add_action( 'wp_footer', function() {
	?>
	<style>
		.dokan-geolocation-location-filters { display: none !important; visibility: hidden !important; height: 0 !important; overflow: hidden !important; opacity: 0 !important; }
	</style>
	<script>jQuery(document).ready(function($) { $('.dokan-geolocation-location-filters').remove(); });</script>
	<?php
}, 999 );

// =============================================================================
// Relabel "Address" → "Location" in Dokan strings.
// =============================================================================
add_filter( 'gettext', function( $translated_text, $text, $domain ) {
	if ( 'dokan-lite' === $domain || 'dokan' === $domain ) {
		if ( 'Address' === $text ) { return 'Location'; }
		if ( 'Store Address & Details' === $text ) { return 'Store Location & Details'; }
		if ( 'Provide your store locations to be displayed on the site.' === $text ) {
			return 'Provide your store location to be displayed on the site.';
		}
	}
	return $translated_text;
}, 10, 3 );

// =============================================================================
// Vendor store header address — use Dokan Geolocation data.
// =============================================================================
add_filter( 'dokan_store_header_adress', function( $formatted_address, $store_address, $short_address ) {
	$vendor_id = 0;
	if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
		$vendor_id = (int) get_query_var( 'author' );
	}
	if ( ! $vendor_id ) {
		global $post;
		$vendor_id = $post ? (int) get_post_field( 'post_author', $post->ID ) : 0;
	}
	$store_info  = $vendor_id ? dokan_get_store_info( $vendor_id ) : [];
	return tm_get_vendor_geo_location_display( $vendor_id, $store_info, $store_address );
}, 10, 3 );

// =============================================================================
// LiteSpeed cache bypass when vendor is editing their own profile.
// =============================================================================
add_action( 'template_redirect', function() {
	if ( ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) { return; }
	$current_user_id = get_current_user_id();
	if ( ! $current_user_id ) { return; }
	$store_user  = function_exists( 'dokan' ) ? dokan()->vendor->get( get_query_var( 'author' ) ) : null;
	if ( ! $store_user ) { return; }
	$vendor_id   = method_exists( $store_user, 'get_id' ) ? $store_user->get_id() : ( $store_user->ID ?? 0 );
	$can_edit    = function_exists( 'tm_can_edit_vendor_profile' )
		? tm_can_edit_vendor_profile( $vendor_id, $current_user_id )
		: ( (int) $current_user_id === (int) $vendor_id || current_user_can( 'manage_options' ) );
	if ( $can_edit && defined( 'LSCWP_V' ) ) {
		do_action( 'litespeed_control_set_nocache', 'vendor or admin editing profile' );
	}
}, 5 );

// =============================================================================
// LiteSpeed: purge showcase page cache once on next admin_init.
// =============================================================================
add_action( 'admin_init', function() {
	if ( ! defined( 'LSCWP_V' ) || get_transient( 'tm_showcase_cache_purged' ) ) { return; }
	do_action( 'litespeed_purge_post', 968 );
	do_action( 'litespeed_purge_all' );
	set_transient( 'tm_showcase_cache_purged', 1, WEEK_IN_SECONDS );
} );
