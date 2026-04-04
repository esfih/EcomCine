<?php
/**
 * Plugin Name: TM Account Panel
 * Description: Adds a right-side Account tab that opens a login/register modal.
 * Version:     1.1.0
 * Author:      TM
 * Requires PHP: 8.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TAP_DIR', plugin_dir_path( __FILE__ ) );

// ---------------------------------------------------------------------------
// Adapter layer (Phase 3 — Account Panel Adapters).
// Contracts.
require_once TAP_DIR . 'includes/contracts/interface-page-context-resolver.php';
require_once TAP_DIR . 'includes/contracts/interface-login-handler.php';
require_once TAP_DIR . 'includes/contracts/interface-onboarding-provider.php';
require_once TAP_DIR . 'includes/contracts/interface-account-data-provider.php';
// Compatibility adapters.
require_once TAP_DIR . 'includes/adapters/compatibility/class-compat-page-context-resolver.php';
require_once TAP_DIR . 'includes/adapters/compatibility/class-compat-login-handler.php';
require_once TAP_DIR . 'includes/adapters/compatibility/class-compat-onboarding-provider.php';
require_once TAP_DIR . 'includes/adapters/compatibility/class-compat-account-data-provider.php';
// Default-WP CPTs.
require_once TAP_DIR . 'includes/adapters/default-wp/class-wp-invitation-cpt.php';
require_once TAP_DIR . 'includes/adapters/default-wp/class-wp-order-cpt.php';
require_once TAP_DIR . 'includes/adapters/default-wp/class-wp-booking-cpt.php';
// Default-WP adapters.
require_once TAP_DIR . 'includes/adapters/default-wp/class-wp-page-context-resolver.php';
require_once TAP_DIR . 'includes/adapters/default-wp/class-wp-login-handler.php';
require_once TAP_DIR . 'includes/adapters/default-wp/class-wp-onboarding-provider.php';
require_once TAP_DIR . 'includes/adapters/default-wp/class-wp-account-data-provider.php';
// Registry.
require_once TAP_DIR . 'includes/adapters/class-adapter-registry.php';
// Parity check (loaded on-demand — not invoked at runtime).
require_once TAP_DIR . 'includes/parity/class-parity-check.php';
// ---------------------------------------------------------------------------

// Register CPTs before WordPress init completes.
add_action( 'init', [ 'TAP_WP_Invitation_CPT', 'register_post_type' ], 5 );
add_action( 'init', [ 'TAP_WP_Order_CPT',      'register_post_type' ], 5 );
add_action( 'init', [ 'TAP_WP_Booking_CPT',    'register_post_type' ], 5 );

function tm_account_panel_is_store_page() {
	return TAP_Adapter_Registry::get_context_resolver()->is_eligible_page();
}

function tm_account_panel_svg_icon( $name, $class = '', $title = '' ) {
	if ( class_exists( 'TM_Icons' ) ) {
		return TM_Icons::svg( $name, $class, $title );
	}

	$fallback_class = trim( 'tm-icon tm-icon-fallback' . ( $class ? ' ' . $class : '' ) );
	$attrs = 'class="' . esc_attr( $fallback_class ) . '" aria-hidden="true"';
	if ( $title ) {
		$attrs = 'class="' . esc_attr( $fallback_class ) . '" role="img" aria-label="' . esc_attr( $title ) . '"';
	}

	return '<span ' . $attrs . '>•</span>';
}

function tm_account_panel_enqueue_assets( $force = false ) {
	if ( is_admin() ) {
		return;
	}
	if ( ! $force && ! tm_account_panel_is_store_page() ) {
		return;
	}

	$plugin_url = plugin_dir_url( __FILE__ );
	wp_enqueue_style(
		'tm-account-panel-css',
		$plugin_url . 'assets/css/account-panel.css',
		array(),
		'1.1.17'
	);
	wp_enqueue_script(
		'tm-account-panel-js',
		$plugin_url . 'assets/js/account-panel.js',
		array( 'jquery' ),
		'1.1.17',
		true
	);
	$privacy_url = get_privacy_policy_url();
	if ( empty( $privacy_url ) ) {
		$privacy_url = trailingslashit( home_url() ) . 'privacy';
	}
	$talent_terms_url = home_url( '/talent-terms/' );
	$hirer_terms_url  = home_url( '/hirer-terms/' );
	wp_localize_script(
		'tm-account-panel-js',
		'tmAccountPanel',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tm_account_login' ),
			'adminNonce' => wp_create_nonce( 'tm_account_admin' ),
			'orderNonce' => wp_create_nonce( 'tm_account_orders' ),
			'privacyUrl' => esc_url_raw( $privacy_url ),
			'talentTermsUrl' => esc_url_raw( $talent_terms_url ),
			'hirerTermsUrl' => esc_url_raw( $hirer_terms_url ),
			'debug'   => defined( 'WP_DEBUG' ) && WP_DEBUG,
		)
	);
}
add_action( 'wp_enqueue_scripts', function() {
	tm_account_panel_enqueue_assets();
}, 20 );

add_action( 'wp_ajax_nopriv_tm_account_login_user', 'tm_account_panel_login_user' );
add_action( 'wp_ajax_nopriv_tm_account_ping', 'tm_account_panel_ping' );
add_action( 'wp_ajax_tm_account_create_talent', 'tm_account_panel_create_talent' );
add_action( 'wp_ajax_tm_onboard_share_link', 'tm_account_panel_share_link' );
add_action( 'wp_ajax_tm_account_panel_order_details', 'tm_account_panel_order_details' );
add_action( 'admin_post_tm_onboard_claim', 'tm_account_panel_handle_claim' );
add_action( 'admin_post_nopriv_tm_onboard_claim', 'tm_account_panel_handle_claim' );

function tm_account_panel_ping() {
	if ( ! check_ajax_referer( 'tm_account_login', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'invalid_nonce' ], 400 );
	}

	wp_send_json_success( [ 'message' => 'pong' ] );
}

function tm_account_panel_find_login_identifier( $identifier ) {
	global $wpdb;

	$identifier = trim( (string) $identifier );
	if ( '' === $identifier ) {
		return '';
	}

	if ( is_email( $identifier ) ) {
		$email = strtolower( $identifier );
		$user  = get_user_by( 'email', $email );
		if ( $user ) {
			return $user->user_login;
		}

		$login = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_login FROM {$wpdb->users} WHERE LOWER(user_email) = LOWER(%s) LIMIT 1",
				$email
			)
		);
		return $login ? $login : '';
	}

	$user = get_user_by( 'login', $identifier );
	if ( $user ) {
		return $user->user_login;
	}

	$login = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT user_login FROM {$wpdb->users} WHERE LOWER(user_login) = LOWER(%s) LIMIT 1",
			$identifier
		)
	);
	if ( $login ) {
		return $login;
	}

	if ( strpos( $identifier, '@' ) !== false ) {
		$login = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_login FROM {$wpdb->users} WHERE LOWER(user_email) = LOWER(%s) LIMIT 1",
				$identifier
			)
		);
		return $login ? $login : '';
	}

	return '';
}

function tm_account_panel_login_user() {
	if ( ! check_ajax_referer( 'tm_account_login', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Invalid session token. Please refresh and try again.', 'dokan-lite' ) ], 400 );
	}

	$form_data = [];
	if ( isset( $_POST['form_data'] ) ) {
		parse_str( wp_unslash( $_POST['form_data'] ), $form_data );
	}

	$user_login = '';
	$user_password = '';
	if ( ! empty( $form_data ) ) {
		$user_login    = isset( $form_data['dokan_login_form_username'] ) ? sanitize_text_field( $form_data['dokan_login_form_username'] ) : '';
		$user_password = isset( $form_data['dokan_login_form_password'] ) ? sanitize_text_field( $form_data['dokan_login_form_password'] ) : '';
	} else {
		$user_login    = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';
		$user_password = isset( $_POST['pass'] ) ? sanitize_text_field( wp_unslash( $_POST['pass'] ) ) : '';
	}

	if ( empty( $user_login ) || empty( $user_password ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Invalid username or password.', 'dokan-lite' ) ], 400 );
	}

	$resolved_login = tm_account_panel_find_login_identifier( $user_login );
	if ( $resolved_login ) {
		$user_login = $resolved_login;
	}

	$wp_user = wp_signon(
		[
			'user_login'    => $user_login,
			'user_password' => $user_password,
		],
		''
	);

	if ( is_wp_error( $wp_user ) ) {
		$response = [ 'message' => esc_html__( 'Wrong username or password.', 'dokan-lite' ) ];
		if ( ! empty( $_POST['debug'] ) ) {
			$response['debug'] = [
				'error_codes'    => $wp_user->get_error_codes(),
				'error_messages' => $wp_user->get_error_messages(),
				'input'          => $form_data['dokan_login_form_username'] ?? ( $_POST['login'] ?? '' ),
				'resolved_login' => $user_login,
			];
		}
		wp_send_json_error( $response, 400 );
	}

	wp_set_current_user( $wp_user->data->ID, $wp_user->data->user_login );

	$headers = headers_list();
	foreach ( $headers as $header ) {
		if ( 0 === strpos( $header, 'Set-Cookie: ' . LOGGED_IN_COOKIE ) ) {
			$value = str_replace( '&', rawurlencode( '&' ), substr( $header, 12 ) );
			parse_str( current( explode( ';', $value, 1 ) ), $pair );
			$_COOKIE[ LOGGED_IN_COOKIE ] = $pair[ LOGGED_IN_COOKIE ];
			break;
		}
	}

	wp_send_json_success( [ 'message' => esc_html__( 'User logged in successfully.', 'dokan-lite' ) ] );
}

function tm_account_panel_render_modal_markup( $force = false ) {
	if ( is_admin() ) {
		return;
	}
	if ( ! $force && ! tm_account_panel_is_store_page() ) {
		return;
	}

	$account_body = '';
	$account_header_meta = '';
	$account_header_actions = '';
	if ( is_user_logged_in() ) {
		$logout_redirect = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) );
		$logout_redirect = add_query_arg( 'tm_account_panel', '1', $logout_redirect );
		$logout_url = wp_logout_url( $logout_redirect );

		if ( class_exists( 'EcomCine_Runtime_Adapters', false ) ) {
			$commerce          = EcomCine_Runtime_Adapters::commerce();
			$current_vendor_id = get_current_user_id();
			$balance_html      = $commerce->get_vendor_balance_html( $current_vendor_id );
			$mrr_raw           = $commerce->get_vendor_mrr( $current_vendor_id );
			$arr_raw           = $commerce->get_vendor_arr( $current_vendor_id );
			$fmt_mrr           = $commerce->format_price( $mrr_raw );
			$fmt_arr           = $commerce->format_price( $arr_raw );

			$kpi_parts = '';
			if ( '' !== $balance_html ) {
				$kpi_parts .= '<span class="tm-account-balance">'
					. '<span class="tm-account-balance-label">Balance</span>'
					. '<span class="tm-account-balance-amount">' . wp_kses_post( $balance_html ) . '</span>'
					. '</span>';
			}
			$kpi_parts .= '<span class="tm-account-kpi">'
				. '<span class="tm-account-kpi-label">MRR:</span>'
				. '<span class="tm-account-kpi-amount">' . wp_kses_post( $fmt_mrr ) . '</span>'
				. '</span>';
			$kpi_parts .= '<span class="tm-account-kpi">'
				. '<span class="tm-account-kpi-label">ARR:</span>'
				. '<span class="tm-account-kpi-amount">' . wp_kses_post( $fmt_arr ) . '</span>'
				. '</span>';

			if ( $kpi_parts ) {
				$account_header_meta = '<div class="tm-account-header-meta">' . $kpi_parts . '</div>';
			}
		}

		$account_header_actions = '<a class="tm-account-logout" href="' . esc_url( $logout_url ) . '" aria-label="Log out" title="Log out">' . tm_account_panel_svg_icon( 'sign-out-alt' ) . '</a>';
		$orders_vendor_id    = get_current_user_id();
		$orders_table_markup = class_exists( 'EcomCine_Runtime_Adapters', false )
			? EcomCine_Runtime_Adapters::commerce()->get_orders_table_html( $orders_vendor_id )
			: tm_account_panel_get_vendor_orders_table_legacy();

		$account_body .= '<div class="tm-account-manage">';
		$account_body .= '<div class="tm-account-manage-tabs" role="tablist" aria-label="Account sections">';
		$account_body .= '<button class="tm-account-manage-tab is-active" type="button" role="tab" aria-selected="true" aria-controls="tm-account-panel-orders" data-tab="orders">Orders</button>';
		$account_body .= '</div>';
		$account_body .= '<div class="tm-account-manage-panels">';
		$account_body .= '<section id="tm-account-panel-orders" class="tm-account-manage-panel is-active" role="tabpanel">';
		$account_body .= '<div class="tm-account-section tm-account-section--orders">';
		$account_body .= '<div class="tm-account-orders-content">';
		$account_body .= '<div class="tm-account-orders-list">' . $orders_table_markup . '</div>';
		$account_body .= '<div class="tm-account-orders-detail" aria-hidden="true"></div>';
		$account_body .= '</div>';
		$account_body .= '</div>';
		$account_body .= '</section>';
		$account_body .= '</div>';
		$account_body .= '</div>';
	} else {
		$redirect_url = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) );
		$redirect_url = add_query_arg( 'tm_account_panel', '1', $redirect_url );
		$login_form   = '';
		if ( function_exists( 'woocommerce_login_form' ) ) {
			ob_start();
			woocommerce_login_form(
				array(
					'redirect' => $redirect_url,
				),
			);
			$login_form = ob_get_clean();
		} else {
			$login_form = wp_login_form(
				array(
					'echo'     => false,
					'redirect' => $redirect_url,
				),
			);
		}
		$registration_form = do_shortcode( '[dokan-vendor-registration]' );

		$account_body .= '<div class="tm-account-forms">';
		$account_body .= '<div class="tm-account-tabs" role="tablist" aria-label="Account">';
		$account_body .= '<button class="tm-account-tab-btn is-active" type="button" role="tab" aria-selected="true" aria-controls="tm-account-login-panel" data-tab="login">Log in</button>';
		$account_body .= '<button class="tm-account-tab-btn" type="button" role="tab" aria-selected="false" aria-controls="tm-account-register-panel" data-tab="register">Register</button>';
		$account_body .= '</div>';
		$account_body .= '<div class="tm-account-form tm-account-login">';
		$account_body .= '<div id="tm-account-login-panel" role="tabpanel">';
		$account_body .= '<h4>Log in</h4>';
		$account_body .= $login_form ? $login_form : wp_login_form( array( 'echo' => false ) );
		$account_body .= '</div>';
		$account_body .= '</div>';
		$account_body .= '<div class="tm-account-form tm-account-register">';
		$account_body .= '<div id="tm-account-register-panel" role="tabpanel">';
		$account_body .= '<h4>Register</h4>';
		$account_body .= $registration_form ? $registration_form : '<p>Registration form unavailable.</p>';
		$account_body .= '</div>';
		$account_body .= '</div>';
		$account_body .= '</div>';
	}
	?>
	<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<div class="tm-account-tab tm-account-tab--admin" role="button" tabindex="0" aria-label="Add Talent" title="Add Talent">
			<span class="tm-account-tab__icon"><?php echo tm_account_panel_svg_icon( 'user' ); ?></span>
			<span class="tm-account-tab__label">Add Talent</span>
		</div>
	<?php endif; ?>
	<div class="tm-account-tab" role="button" tabindex="0" aria-controls="tm-account-modal" aria-expanded="false">Account</div>

	<div id="tm-account-modal" class="tm-account-modal" aria-hidden="true" role="dialog" aria-modal="true">
		<div class="tm-account-backdrop"></div>
		<div class="tm-account-dialog" role="document">
			<div class="tm-account-header">
				<div class="tm-account-header-left">
					<h3 aria-label="Account"><?php echo tm_account_panel_svg_icon( 'user-circle' ); ?></h3>
				</div>
				<?php echo $account_header_meta; ?>
				<div class="tm-account-header-actions">
					<?php echo $account_header_actions; ?>
					<button class="tm-account-close" type="button" aria-label="Close account">
						<?php echo tm_account_panel_svg_icon( 'times' ); ?>
					</button>
				</div>
			</div>
			<div class="tm-account-body">
				<?php echo $account_body; ?>
			</div>
		</div>
	</div>
	<?php
}

add_action( 'wp_footer', function() {
	if ( function_exists( 'tm_is_showcase_takeover_page' ) && tm_is_showcase_takeover_page() ) {
		return;
	}
	tm_account_panel_render_modal_markup();
}, 20 );

function tm_account_panel_is_admin_user() {
	return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
}

function tm_account_panel_order_details() {
	if ( ! check_ajax_referer( 'tm_account_orders', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'invalid_nonce' ], 400 );
	}
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
	}

	$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
	if ( ! $order_id ) {
		wp_send_json_error( [ 'message' => 'invalid_order' ], 400 );
	}

	$current_user_id = get_current_user_id();
	if ( class_exists( 'EcomCine_Runtime_Adapters', false ) ) {
		if ( ! EcomCine_Runtime_Adapters::commerce()->can_view_order( $order_id, $current_user_id ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		$html = EcomCine_Runtime_Adapters::commerce()->get_order_detail_html( $order_id );
	} else {
		// Legacy Dokan fallback.
		if ( function_exists( 'dokan_get_seller_id_by_order' ) ) {
			$seller_id = (int) dokan_get_seller_id_by_order( $order_id );
			if ( $seller_id && $seller_id !== $current_user_id ) {
				wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
			}
		}
		if ( ! current_user_can( 'dokan_view_order' ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		ob_start();
		dokan_get_template_part( 'orders/details', '', [ 'order_id' => $order_id ] );
		$html = ob_get_clean();
	}

	if ( ! $html ) {
		wp_send_json_error( [ 'message' => 'empty' ], 500 );
	}

	wp_send_json_success( [ 'html' => $html ] );
}

function tm_account_panel_get_vendor_orders_table_legacy() {
	if ( ! function_exists( 'dokan' ) || ! function_exists( 'dokan_get_current_user_id' ) ) {
		return '<p class="tm-account-muted">Orders are unavailable.</p>';
	}

	$seller_id = dokan_get_current_user_id();
	$limit = 10;
	$page = 1;
	$query_args = [
		'seller_id' => $seller_id,
		'paged'     => $page,
		'limit'     => $limit,
		'return'    => 'objects',
	];
	$user_orders = dokan()->order->all( $query_args );

	$query_args['return'] = 'count';
	$total_order_count = (int) dokan()->order->all( $query_args );
	$num_of_pages = $limit > 0 ? (int) ceil( $total_order_count / $limit ) : 1;
	$page_links = [];
	$allow_shipment = dokan_get_option( 'enabled', 'dokan_shipping_status_setting', 'off' );
	$wc_shipping_enabled = get_option( 'woocommerce_calc_shipping' ) === 'yes';
	$bulk_order_statuses = apply_filters(
		'dokan_bulk_order_statuses',
		[
			'-1'            => __( 'Bulk Actions', 'dokan-lite' ),
			'wc-on-hold'    => __( 'Change status to on-hold', 'dokan-lite' ),
			'wc-processing' => __( 'Change status to processing', 'dokan-lite' ),
			'wc-completed'  => __( 'Change status to completed', 'dokan-lite' ),
		]
	);

	add_filter( 'post_date_column_time', 'tm_account_panel_format_order_date', 10, 2 );
	ob_start();
	dokan_get_template_part(
		'orders/listing',
		'',
		[
			'user_orders'        => $user_orders,
			'bulk_order_statuses'=> $bulk_order_statuses,
			'allow_shipment'     => $allow_shipment,
			'wc_shipping_enabled'=> $wc_shipping_enabled,
			'num_of_pages'       => $num_of_pages,
			'page_links'         => $page_links,
		]
	);
	$markup = ob_get_clean();
	remove_filter( 'post_date_column_time', 'tm_account_panel_format_order_date', 10 );
	$markup = preg_replace( '/<th[^>]*class="[^"]*column-cb[^"]*"[^>]*>.*?<\/th>/s', '', $markup );
	$markup = preg_replace( '/<th[^>]*class="[^"]*dokan-order-select[^"]*"[^>]*>.*?<\/th>/s', '', $markup );
	$markup = preg_replace( '/<th[^>]*>\s*Action\s*<\/th>/i', '', $markup );
	$markup = preg_replace( '/<td[^>]*class="[^"]*dokan-order-action[^"]*"[^>]*>.*?<\/td>/s', '', $markup );
	$markup = preg_replace( '/<div[^>]*class="[^"]*pagination-wrap[^"]*"[^>]*>.*?<\/div>/s', '', $markup );
	$markup = preg_replace(
		'/(<th[^>]*>)(\s*Order Total\s*)(<\/th>)/i',
		'$1Amount$3',
		$markup,
		1
	);
	$markup = preg_replace_callback(
		'/(<td[^>]*class="[^"]*dokan-order-id[^"]*"[^>]*>)(.*?)(<a[^>]*href="([^"]*order_id=\d+[^"]*)"[^>]*>)/s',
		function( $matches ) {
			$cell_start = $matches[1];
			$cell_body = $matches[2];
			$link_start = $matches[3];
			$href = $matches[4];
			$view_button = '<a class="dokan-btn dokan-btn-default dokan-btn-sm tips tm-account-order-view" href="' . esc_url( $href ) . '" data-toggle="tooltip" data-placement="top" title="View" aria-label="View">' . tm_account_panel_svg_icon( 'eye' ) . '</a> ';
			return $cell_start . $cell_body . $view_button . $link_start;
		},
		$markup
	);
	return $markup;
}

function tm_account_panel_format_order_date( $date, $order_id ) {
	if ( ! function_exists( 'wc_get_order' ) ) {
		return $date;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order || ! $order->get_date_created() ) {
		return $date;
	}
	return date_i18n( 'd/m/Y', $order->get_date_created()->getTimestamp() );
}

function tm_account_panel_unique_user_nicename( $base_slug, $user_id = 0 ) {
	$slug = sanitize_title( $base_slug );
	if ( '' === $slug ) {
		$slug = 'talent';
	}

	$try = $slug;
	$suffix = 1;
	while ( true ) {
		$user = get_user_by( 'slug', $try );
		if ( ! $user || ( $user_id && (int) $user->ID === (int) $user_id ) ) {
			return $try;
		}
		$try = $slug . '-' . $suffix;
		$suffix++;
	}
}

function tm_account_panel_create_talent() {
	if ( ! check_ajax_referer( 'tm_account_admin', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'invalid_nonce' ], 400 );
	}
	if ( ! tm_account_panel_is_admin_user() ) {
		wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
	}

	$unique = wp_generate_password( 8, false, false );
	$login_base = 'preonboard-' . gmdate( 'Ymd-His' ) . '-' . strtolower( $unique );
	$login = $login_base;
	$tries = 0;
	while ( username_exists( $login ) && $tries < 10 ) {
		$login = $login_base . '-' . wp_generate_password( 4, false, false );
		$tries++;
	}

	$placeholder_email = 'preonboard+' . $login . '@example.invalid';
	$user_id = wp_insert_user(
		[
			'user_login' => $login,
			'user_email' => $placeholder_email,
			'user_pass'  => wp_generate_password( 20, true, true ),
			'role'       => 'seller',
		]
	);

	if ( is_wp_error( $user_id ) ) {
		wp_send_json_error( [ 'message' => $user_id->get_error_message() ], 400 );
	}

	update_user_meta( $user_id, 'tm_preonboard', 1 );
	update_user_meta( $user_id, 'tm_preonboard_admin_id', get_current_user_id() );
	update_user_meta( $user_id, 'tm_preonboard_created_at', time() );

	if ( function_exists( 'dokan_get_store_url' ) ) {
		$store_url = dokan_get_store_url( $user_id );
	} else {
		$store_url = home_url( '/' );
	}

	wp_send_json_success( [
		'user_id' => $user_id,
		'store_url' => $store_url,
	] );
}

function tm_account_panel_load_qr_library() {
	if ( class_exists( '\\chillerlan\\QRCode\\QRCode' ) ) {
		return true;
	}

	$autoload_paths = [
		get_stylesheet_directory() . '/vendor/autoload.php',
		get_stylesheet_directory() . '/lib/php-qrcode/vendor/autoload.php',
		plugin_dir_path( __FILE__ ) . 'vendor/autoload.php',
	];

	foreach ( $autoload_paths as $autoload ) {
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
			break;
		}
	}

	return class_exists( '\\chillerlan\\QRCode\\QRCode' );
}

function tm_account_panel_get_qr_svg_markup( $url, $context = 'onboard' ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	$cache_key = 'tm_onboard_qr_svg_' . md5( $url . '|' . $context );
	$cached = get_transient( $cache_key );
	if ( $cached ) {
		return $cached;
	}

	if ( function_exists( 'tm_load_qr_library' ) && ! tm_load_qr_library() ) {
		return '';
	}
	if ( ! tm_account_panel_load_qr_library() ) {
		return '';
	}
	if ( ! class_exists( '\chillerlan\QRCode\QROptions' ) || ! class_exists( '\chillerlan\QRCode\QRCode' ) ) {
		return '';
	}

	$svg_markup = '';
	try {
		$options = new \chillerlan\QRCode\QROptions( [
			'eccLevel' => 3,
			'addQuietzone' => true,
			'quietzoneSize' => 4,
			'scale' => 8,
			'outputBase64' => false,
			'svgAddXmlHeader' => false,
		] );
		$svg_markup = ( new \chillerlan\QRCode\QRCode( $options ) )->render( $url );
	} catch ( Throwable $e ) {
		$svg_markup = '';
	}

	if ( ! empty( $svg_markup ) && is_string( $svg_markup ) ) {
		$trimmed_markup = ltrim( $svg_markup );
		if ( 0 === strpos( $trimmed_markup, 'data:image/' ) ) {
			$output = '<div class="qr-code-placeholder qr-code-live" aria-label="Scan to open onboarding"><img src="' . esc_attr( $trimmed_markup ) . '" alt="Scan to open onboarding" /></div>';
			set_transient( $cache_key, $output, DAY_IN_SECONDS );
			return $output;
		}

		if ( false !== strpos( $svg_markup, '<svg' ) ) {
			$svg_data_uri = 'data:image/svg+xml;base64,' . base64_encode( $svg_markup );
			$output = '<div class="qr-code-placeholder qr-code-live" aria-label="Scan to open onboarding"><img src="' . esc_attr( $svg_data_uri ) . '" alt="Scan to open onboarding" /></div>';
			set_transient( $cache_key, $output, DAY_IN_SECONDS );
			return $output;
		}
	}

	$png_markup = '';
	try {
		$png_options = new \chillerlan\QRCode\QROptions( [
			'eccLevel' => 3,
			'outputType' => 'png',
			'outputBase64' => true,
			'scale' => 8,
		] );
		$png_markup = ( new \chillerlan\QRCode\QRCode( $png_options ) )->render( $url );
	} catch ( Throwable $e ) {
		$png_markup = '';
	}

	$trimmed_png = ltrim( (string) $png_markup );
	if ( 0 === strpos( $trimmed_png, 'data:image/' ) ) {
		$output = '<div class="qr-code-placeholder qr-code-live" aria-label="Scan to open onboarding"><img src="' . esc_attr( $trimmed_png ) . '" alt="Scan to open onboarding" /></div>';
		set_transient( $cache_key, $output, DAY_IN_SECONDS );
		return $output;
	}

	return '';
}

function tm_account_panel_prepare_onboarding_link( $vendor_id ) {
	$vendor_id = (int) $vendor_id;
	if ( ! $vendor_id || ! function_exists( 'dokan_get_store_url' ) ) {
		return new WP_Error( 'invalid_vendor', 'Invalid vendor.' );
	}

	$vendor = dokan()->vendor->get( $vendor_id );
	if ( ! $vendor ) {
		return new WP_Error( 'invalid_vendor', 'Invalid vendor.' );
	}

	$store_name = $vendor->get_shop_name();
	$store_name = is_string( $store_name ) ? trim( $store_name ) : '';
	if ( '' === $store_name ) {
		return new WP_Error( 'missing_name', 'Please enter and save the full name before sharing.' );
	}

	$slug = tm_account_panel_unique_user_nicename( $store_name, $vendor_id );
	wp_update_user( [
		'ID' => $vendor_id,
		'user_nicename' => $slug,
	] );

	$store_url = dokan_get_store_url( $vendor_id );
	$token = wp_generate_password( 24, false, false );
	$expires_at = time() + DAY_IN_SECONDS;
	update_user_meta( $vendor_id, 'tm_preonboard_token', $token );
	update_user_meta( $vendor_id, 'tm_preonboard_expires', $expires_at );

	$onboard_url = add_query_arg( 'tm_onboard', $token, $store_url );
	$qr_markup = tm_account_panel_get_qr_svg_markup( $onboard_url );

	return [
		'link' => $onboard_url,
		'qr' => $qr_markup,
		'expires_at' => $expires_at,
		'store_url' => $store_url,
	];
}

function tm_account_panel_build_admin_full_name( $admin_id ) {
	$admin = $admin_id ? get_userdata( $admin_id ) : null;
	if ( ! $admin ) {
		return '';
	}
	$first_name = get_user_meta( $admin_id, 'first_name', true );
	$last_name = get_user_meta( $admin_id, 'last_name', true );
	$full_name = trim( $first_name . ' ' . $last_name );
	return $full_name ? $full_name : $admin->display_name;
}

function tm_account_panel_share_link() {
	if ( ! check_ajax_referer( 'vendor_inline_edit', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'invalid_nonce' ], 400 );
	}
	if ( ! tm_account_panel_is_admin_user() ) {
		wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
	}

	$vendor_id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;
	$is_preonboard = (bool) get_user_meta( $vendor_id, 'tm_preonboard', true );
	if ( ! $is_preonboard ) {
		wp_send_json_error( [ 'message' => 'This talent is not in pre-onboarding mode.' ], 400 );
	}

	$result = tm_account_panel_prepare_onboarding_link( $vendor_id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
	}

	$admin_id = get_current_user_id();
	$admin_name = tm_account_panel_build_admin_full_name( $admin_id );
	$talent_name = '';
	if ( function_exists( 'dokan' ) ) {
		$vendor = dokan()->vendor->get( $vendor_id );
		$talent_name = $vendor ? $vendor->get_shop_name() : '';
	}
	$default_message = "Dear \$TalentName,\n\n\$AdminName is inviting you to join Casting Agency Co and has already pre-filled your profile. Create an account to claim your talent profile, you will then be able to complete/publish it.";

	$admin_message = isset( $_POST['admin_message'] ) ? wp_unslash( $_POST['admin_message'] ) : '';
	$admin_message = $admin_message ? wp_kses_post( $admin_message ) : '';
	if ( '' === $admin_message ) {
		$admin_message = (string) get_user_meta( $vendor_id, 'tm_preonboard_admin_message', true );
	}
	if ( '' === $admin_message ) {
		$admin_message = $default_message;
	}

	$admin_avatar_id = isset( $_POST['admin_avatar_id'] ) ? absint( $_POST['admin_avatar_id'] ) : 0;
	$admin_avatar_url = '';
	if ( $admin_avatar_id ) {
		$admin_avatar_url = wp_get_attachment_image_url( $admin_avatar_id, 'thumbnail' );
	}
	if ( ! $admin_avatar_url ) {
		$admin_avatar_url = isset( $_POST['admin_avatar_url'] ) ? esc_url_raw( wp_unslash( $_POST['admin_avatar_url'] ) ) : '';
	}
	if ( ! $admin_avatar_url ) {
		$admin_avatar_url = (string) get_user_meta( $vendor_id, 'tm_preonboard_admin_avatar_url', true );
	}
	if ( ! $admin_avatar_url ) {
		$admin_avatar_url = get_avatar_url( $admin_id );
	}

	$vendor_avatar_id = isset( $_POST['vendor_avatar_id'] ) ? absint( $_POST['vendor_avatar_id'] ) : 0;
	$vendor_avatar_url = '';
	if ( $vendor_avatar_id ) {
		$vendor_avatar_url = wp_get_attachment_image_url( $vendor_avatar_id, 'thumbnail' );
	}
	if ( ! $vendor_avatar_url ) {
		$vendor_avatar_url = isset( $_POST['vendor_avatar_url'] ) ? esc_url_raw( wp_unslash( $_POST['vendor_avatar_url'] ) ) : '';
	}
	if ( ! $vendor_avatar_url ) {
		$vendor_avatar_url = (string) get_user_meta( $vendor_id, 'tm_preonboard_vendor_avatar_url', true );
	}
	if ( ! $vendor_avatar_url ) {
		if ( function_exists( 'mp_get_vendor_avatar_url' ) ) {
			$vendor_avatar_url = mp_get_vendor_avatar_url( $vendor_id, 120 );
		} else {
			$vendor_avatar_url = get_avatar_url( $vendor_id, array( 'size' => 120 ) );
		}
	}

	update_user_meta( $vendor_id, 'tm_preonboard_admin_message', $admin_message );
	update_user_meta( $vendor_id, 'tm_preonboard_admin_avatar_id', $admin_avatar_id );
	update_user_meta( $vendor_id, 'tm_preonboard_admin_avatar_url', $admin_avatar_url );
	update_user_meta( $vendor_id, 'tm_preonboard_vendor_avatar_id', $vendor_avatar_id );
	update_user_meta( $vendor_id, 'tm_preonboard_vendor_avatar_url', $vendor_avatar_url );

	$result['admin_name'] = $admin_name;
	$result['talent_name'] = $talent_name;
	$result['admin_message'] = $admin_message;
	$result['admin_avatar_url'] = $admin_avatar_url;
	$result['admin_avatar_id'] = $admin_avatar_id;
	$result['vendor_avatar_url'] = $vendor_avatar_url;
	$result['vendor_avatar_id'] = $vendor_avatar_id;

	wp_send_json_success( $result );
}

function tm_account_panel_get_onboard_state( $vendor_id, $token ) {
	$vendor_id = (int) $vendor_id;
	$token = is_string( $token ) ? trim( $token ) : '';
	if ( ! $vendor_id || '' === $token ) {
		return [ 'valid' => false ];
	}

	$stored = (string) get_user_meta( $vendor_id, 'tm_preonboard_token', true );
	$expires = (int) get_user_meta( $vendor_id, 'tm_preonboard_expires', true );
	if ( '' === $stored || $stored !== $token ) {
		return [ 'valid' => false ];
	}
	if ( $expires && time() > $expires ) {
		return [ 'valid' => false, 'expired' => true ];
	}

	$admin_id = (int) get_user_meta( $vendor_id, 'tm_preonboard_admin_id', true );
	$admin_name = '';
	if ( $admin_id ) {
		$admin_name = tm_account_panel_build_admin_full_name( $admin_id );
	}

	return [
		'valid' => true,
		'expires_at' => $expires,
		'admin_name' => $admin_name,
	];
}

function tm_account_panel_handle_claim() {
	$vendor_id = isset( $_POST['vendor_id'] ) ? absint( $_POST['vendor_id'] ) : 0;
	$token = isset( $_POST['tm_onboard'] ) ? sanitize_text_field( wp_unslash( $_POST['tm_onboard'] ) ) : '';
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';

	if ( ! $vendor_id || ! $token || ! $email || ! $password ) {
		wp_safe_redirect( add_query_arg( 'tm_onboard_error', 'missing', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}
	$accept_privacy = ! empty( $_POST['tm_accept_privacy'] );
	$accept_terms = ! empty( $_POST['tm_accept_terms'] );
	if ( ! $accept_privacy || ! $accept_terms ) {
		wp_safe_redirect( add_query_arg( 'tm_onboard_error', 'terms', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	if ( ! wp_verify_nonce( $_POST['tm_onboard_claim_nonce'] ?? '', 'tm_onboard_claim' ) ) {
		wp_safe_redirect( add_query_arg( 'tm_onboard_error', 'nonce', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	$state = tm_account_panel_get_onboard_state( $vendor_id, $token );
	if ( empty( $state['valid'] ) ) {
		wp_safe_redirect( add_query_arg( 'tm_onboard_error', 'expired', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	$existing = get_user_by( 'email', $email );
	if ( $existing && (int) $existing->ID !== $vendor_id ) {
		wp_safe_redirect( add_query_arg( 'tm_onboard_error', 'email', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	$updated = wp_update_user( [
		'ID' => $vendor_id,
		'user_email' => $email,
		'user_pass' => $password,
	] );
	if ( is_wp_error( $updated ) ) {
		wp_safe_redirect( add_query_arg( 'tm_onboard_error', 'save', wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	$vendor_avatar_id = (int) get_user_meta( $vendor_id, 'tm_preonboard_vendor_avatar_id', true );
	if ( $vendor_avatar_id && wp_attachment_is_image( $vendor_avatar_id ) ) {
		$attachment = get_post( $vendor_avatar_id );
		if ( $attachment && 'attachment' === $attachment->post_type && (int) $attachment->post_author !== $vendor_id ) {
			wp_update_post( [
				'ID' => $vendor_avatar_id,
				'post_author' => $vendor_id,
			] );
		}

		$profile_settings = get_user_meta( $vendor_id, 'dokan_profile_settings', true );
		if ( ! is_array( $profile_settings ) ) {
			$profile_settings = [];
		}
		$profile_settings['gravatar'] = $vendor_avatar_id;
		update_user_meta( $vendor_id, 'dokan_profile_settings', $profile_settings );
	}

	$existing_emails = get_user_meta( $vendor_id, 'tm_contact_emails', true );
	$existing_emails = is_array( $existing_emails ) ? $existing_emails : [];
	$existing_main = (string) get_user_meta( $vendor_id, 'tm_contact_email_main', true );
	$merged_emails = [];

	if ( $email ) {
		$merged_emails[] = $email;
	}
	if ( $existing_main && $existing_main !== $email ) {
		$merged_emails[] = $existing_main;
	}
	foreach ( $existing_emails as $existing_email ) {
		$existing_email = sanitize_email( $existing_email );
		if ( $existing_email && $existing_email !== $email && $existing_email !== $existing_main ) {
			$merged_emails[] = $existing_email;
		}
	}
	$merged_emails = array_values( array_unique( array_filter( $merged_emails ) ) );
	$merged_emails = array_slice( $merged_emails, 0, 3 );

	if ( ! empty( $merged_emails ) ) {
		update_user_meta( $vendor_id, 'tm_contact_emails', $merged_emails );
		update_user_meta( $vendor_id, 'tm_contact_email_main', $email );
		update_user_meta( $vendor_id, 'tm_contact_email', $email );
	}

	delete_user_meta( $vendor_id, 'tm_preonboard_token' );
	delete_user_meta( $vendor_id, 'tm_preonboard_expires' );
	update_user_meta( $vendor_id, 'tm_preonboard', 0 );

	wp_set_auth_cookie( $vendor_id, true );
	wp_set_current_user( $vendor_id );

	$redirect = function_exists( 'dokan_get_store_url' ) ? dokan_get_store_url( $vendor_id ) : home_url( '/' );
	$redirect = add_query_arg( 'tm_onboard_claimed', '1', $redirect );
	wp_safe_redirect( $redirect );
	exit;
}

function tm_account_panel_get_store_redirect_url( $user ) {
	if ( ! $user || is_wp_error( $user ) ) {
		return '';
	}

	$user_id = $user->ID;

	if ( function_exists( 'tm_get_vendor_public_profile_url' ) ) {
		$store_url = tm_get_vendor_public_profile_url( $user_id );
		if ( $store_url ) {
			return $store_url;
		}
	}

	if ( function_exists( 'dokan_get_store_url' ) ) {
		$store_url = dokan_get_store_url( $user_id );
		if ( $store_url ) {
			return $store_url;
		}
	}

	return '';
}

function tm_account_panel_is_vendor_user( $user ) {
	if ( ! $user || is_wp_error( $user ) ) {
		return false;
	}

	$user_id = is_object( $user ) ? $user->ID : (int) $user;

	if ( function_exists( 'dokan_is_user_seller' ) ) {
		return (bool) dokan_is_user_seller( $user_id );
	}

	return user_can( $user_id, 'dokandar' );
}

function tm_account_panel_is_modal_login_request( $redirect_to = '' ) {
	if ( $redirect_to && false !== strpos( $redirect_to, 'tm_account_panel=1' ) ) {
		return true;
	}

	if ( ! empty( $_REQUEST['redirect'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$posted_redirect = wp_unslash( $_REQUEST['redirect'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( false !== strpos( $posted_redirect, 'tm_account_panel=1' ) ) {
			return true;
		}
	}

	if ( ! empty( $_REQUEST['tm_account_panel'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return true;
	}

	return false;
}

function tm_account_panel_get_requested_registration_role() {
	$role = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
	if ( in_array( $role, [ 'customer', 'seller' ], true ) ) {
		return $role;
	}

	$account_type = isset( $_POST['tm_account_type'] ) ? sanitize_text_field( wp_unslash( $_POST['tm_account_type'] ) ) : '';
	if ( 'hirer' === $account_type ) {
		return 'customer';
	}
	if ( 'talent' === $account_type ) {
		return 'seller';
	}

	return '';
}

function tm_account_panel_is_modal_registration_request() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return false;
	}

	if ( empty( $_POST['register'] ) || empty( $_POST['email'] ) ) {
		return false;
	}

	return isset( $_POST['tm_account_type'] );
}

function tm_account_panel_get_posted_registration_password() {
	if ( isset( $_POST['tm_reg_password'] ) ) {
		return (string) wp_unslash( $_POST['tm_reg_password'] );
	}
	if ( isset( $_POST['password'] ) ) {
		return (string) wp_unslash( $_POST['password'] );
	}
	return '';
}

function tm_account_panel_get_posted_registration_password_confirm() {
	if ( isset( $_POST['tm_reg_password_confirm'] ) ) {
		return (string) wp_unslash( $_POST['tm_reg_password_confirm'] );
	}
	if ( isset( $_POST['password_2'] ) ) {
		return (string) wp_unslash( $_POST['password_2'] );
	}
	if ( isset( $_POST['password2'] ) ) {
		return (string) wp_unslash( $_POST['password2'] );
	}
	return '';
}

add_filter( 'pre_option_woocommerce_registration_generate_password', function( $value ) {
	if ( tm_account_panel_is_modal_registration_request() ) {
		return 'no';
	}
	return $value;
}, 10, 1 );

function tm_account_panel_normalize_registration_password_post() {
	if ( ! tm_account_panel_is_modal_registration_request() ) {
		return;
	}

	$password = tm_account_panel_get_posted_registration_password();
	$confirm  = tm_account_panel_get_posted_registration_password_confirm();

	if ( '' !== $password ) {
		$_POST['password'] = $password;
	}

	if ( '' !== $confirm ) {
		$_POST['password_2'] = $confirm;
		$_POST['password2']  = $confirm;
	}
}
add_action( 'init', 'tm_account_panel_normalize_registration_password_post', 1 );

function tm_account_panel_validate_registration_password( $errors ) {
	if ( ! ( $errors instanceof WP_Error ) ) {
		return $errors;
	}

	if ( ! tm_account_panel_is_modal_registration_request() ) {
		return $errors;
	}

	$password = tm_account_panel_get_posted_registration_password();
	$confirm  = tm_account_panel_get_posted_registration_password_confirm();

	if ( '' === trim( $password ) ) {
		$errors->add( 'tm-account-password-required', __( 'Please enter a password.', 'dokan-lite' ) );
		return $errors;
	}

	if ( strlen( $password ) < 6 ) {
		$errors->add( 'tm-account-password-short', __( 'Password must be at least 6 characters long.', 'dokan-lite' ) );
	}

	if ( '' === trim( $confirm ) ) {
		$errors->add( 'tm-account-password-confirm-required', __( 'Please repeat your password.', 'dokan-lite' ) );
		return $errors;
	}

	if ( ! hash_equals( $password, $confirm ) ) {
		$errors->add( 'tm-account-password-mismatch', __( 'Passwords do not match.', 'dokan-lite' ) );
	}

	return $errors;
}
add_filter( 'woocommerce_process_registration_errors', 'tm_account_panel_validate_registration_password', 15, 1 );
add_filter( 'woocommerce_registration_errors', 'tm_account_panel_validate_registration_password', 15, 1 );

function tm_account_panel_split_full_name( $full_name ) {
	$full_name = trim( preg_replace( '/\s+/', ' ', (string) $full_name ) );
	if ( '' === $full_name ) {
		return [ '', '' ];
	}

	$parts = explode( ' ', $full_name );
	if ( count( $parts ) < 2 ) {
		return [ $full_name, $full_name ];
	}

	$first_name = array_shift( $parts );
	$last_name  = trim( implode( ' ', $parts ) );
	return [ $first_name, $last_name ? $last_name : $first_name ];
}

function tm_account_panel_normalize_vendor_registration_post() {
	if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}

	if ( empty( $_POST['register'] ) || empty( $_POST['email'] ) ) {
		return;
	}

	if ( 'seller' !== tm_account_panel_get_requested_registration_role() ) {
		return;
	}

	$full_name = isset( $_POST['fname'] ) ? sanitize_text_field( wp_unslash( $_POST['fname'] ) ) : '';
	if ( '' === trim( $full_name ) ) {
		return;
	}

	list( $first_name, $last_name ) = tm_account_panel_split_full_name( $full_name );
	$_POST['fname'] = $first_name;

	if ( empty( $_POST['lname'] ) ) {
		$_POST['lname'] = $last_name;
	}

	if ( empty( $_POST['shopname'] ) ) {
		$_POST['shopname'] = $full_name;
	}

	if ( empty( $_POST['shopurl'] ) ) {
		$_POST['shopurl'] = sanitize_title( $full_name );
	}
}
add_action( 'init', 'tm_account_panel_normalize_vendor_registration_post', 1 );

add_filter( 'woocommerce_registration_redirect', function( $redirect_to ) {
	$role = tm_account_panel_get_requested_registration_role();
	if ( '' === $role ) {
		return $redirect_to;
	}

	if ( 'seller' === $role ) {
		$current_user = wp_get_current_user();
		if ( $current_user && $current_user->ID ) {
			$store_url = tm_account_panel_get_store_redirect_url( $current_user );
			if ( $store_url ) {
				return add_query_arg( 'tm_onboard_claimed', '1', $store_url );
			}
		}
		return $redirect_to;
	}

	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$my_account_url = wc_get_page_permalink( 'myaccount' );
		if ( $my_account_url ) {
			return $my_account_url;
		}
	}

	return $redirect_to;
}, 999 );

add_filter( 'login_redirect', function( $redirect_to, $requested_redirect_to, $user ) {
	if ( ! tm_account_panel_is_modal_login_request( $requested_redirect_to ) ) {
		return $redirect_to;
	}
	if ( ! tm_account_panel_is_vendor_user( $user ) ) {
		return $redirect_to;
	}
	$store_url = tm_account_panel_get_store_redirect_url( $user );
	return $store_url ? $store_url : $redirect_to;
}, 999, 3 );

add_filter( 'woocommerce_login_redirect', function( $redirect_to, $user ) {
	if ( ! tm_account_panel_is_modal_login_request( $redirect_to ) ) {
		return $redirect_to;
	}
	if ( ! tm_account_panel_is_vendor_user( $user ) ) {
		return $redirect_to;
	}
	$store_url = tm_account_panel_get_store_redirect_url( $user );
	return $store_url ? $store_url : $redirect_to;
}, 999, 2 );

add_filter( 'logout_redirect', function( $redirect_to, $requested_redirect_to, $user ) {
	if ( ! tm_account_panel_is_modal_login_request( $requested_redirect_to ) ) {
		return $redirect_to;
	}
	if ( ! tm_account_panel_is_vendor_user( $user ) ) {
		return $redirect_to;
	}
	$store_url = tm_account_panel_get_store_redirect_url( $user );
	return $store_url ? $store_url : $redirect_to;
}, 999, 3 );
