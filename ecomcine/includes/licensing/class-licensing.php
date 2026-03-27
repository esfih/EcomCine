<?php
/**
 * EcomCine app-side licensing module.
 *
 * Customer flow:
 * 1) User enters license key in app plugin Licensing tab.
 * 2) App plugin activates against control-plane /activations endpoint.
 * 3) App plugin stores activation_id + site_token.
 * 4) App plugin resolves entitlement via /entitlements/resolve.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Licensing {
	const OPTION_KEY = 'ecomcine_license_settings';
	const OPTION_ENTITLEMENT = 'ecomcine_license_entitlement';
	const OPTION_LAST_SYNC = 'ecomcine_license_last_sync';
	const OPTION_LAST_ERROR = 'ecomcine_license_last_error';
	const OPTION_ACTIVATION_ID = 'ecomcine_cp_activation_id';
	const OPTION_SITE_TOKEN = 'ecomcine_cp_site_token';
	const OPTION_ACTIVATION_LICENSE_REF = 'ecomcine_cp_activation_license_ref';
	const SITE_TOKEN_HEADER = 'X-WMOS-Site-Token';

	/**
	 * Bootstrap licensing hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
		add_action( 'admin_post_ecomcine_license_verify', array( __CLASS__, 'handle_verify' ) );
		add_action( 'admin_post_ecomcine_license_clear', array( __CLASS__, 'handle_clear' ) );
	}

	/**
	 * Default licensing settings.
	 */
	public static function defaults() {
		return array(
			'license_key'       => '',
			'enforcement_mode'  => 'soft',
		);
	}

	/**
	 * Billing control-plane base URL.
	 *
	 * No customer-editable admin field on purpose.
	 */
	public static function get_control_plane_base_url() {
		$base = trailingslashit( 'https://ecomcine.com/wp-json/ecomcine-control-plane/v1/' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$base = trailingslashit( 'http://127.0.0.1/wp-json/ecomcine-control-plane/v1/' );
		}

		/**
		 * Dev/staging override hook.
		 */
		$base = apply_filters( 'ecomcine_control_plane_base_url', $base );

		return esc_url_raw( (string) $base );
	}

	/**
	 * Return merged licensing settings.
	 */
	public static function get_settings() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Resolve license status.
	 *
	 * A private control-plane plugin can override this via ecomcine_license_status filter.
	 */
	public static function get_status() {
		$settings = self::get_settings();
		$entitlement = self::get_cached_entitlement();
		$plan = isset( $entitlement['plan_slug'] ) ? sanitize_key( (string) $entitlement['plan_slug'] ) : '';
		$offer = '' !== $plan ? ( EcomCine_Offer_Catalog::get_catalog()[ $plan ] ?? array() ) : array();
		$active = isset( $entitlement['status'] ) && 'active' === (string) $entitlement['status'];

		$last_error = (string) get_option( self::OPTION_LAST_ERROR, '' );
		$last_sync = (string) get_option( self::OPTION_LAST_SYNC, '' );
		$activation_id = (string) get_option( self::OPTION_ACTIVATION_ID, '' );

		$status = array(
			'active'          => $active,
			'source'          => $active ? 'control-plane' : 'local-default',
			'enforcement'     => $settings['enforcement_mode'],
			'control_plane'   => self::get_control_plane_base_url(),
			'offer_slug'      => isset( $offer['plan'] ) ? (string) $offer['plan'] : $plan,
			'max_site_activations' => isset( $offer['max_site_activations'] ) ? (int) $offer['max_site_activations'] : 1,
			'allowances'      => isset( $offer['allowances'] ) && is_array( $offer['allowances'] ) ? $offer['allowances'] : array(),
			'site_fingerprint'=> self::generate_site_fingerprint(),
			'activation_id'   => $activation_id,
			'last_error'      => $last_error,
			'last_sync'       => $last_sync,
		);

		$status = apply_filters( 'ecomcine_license_status', $status, $settings );

		if ( ! is_array( $status ) ) {
			return array(
				'active'      => true,
				'source'      => 'local-default',
				'enforcement' => 'soft',
			);
		}

		$status['active'] = ! empty( $status['active'] );
		$status['source'] = isset( $status['source'] ) ? sanitize_text_field( $status['source'] ) : 'unknown';
		$status['enforcement'] = isset( $status['enforcement'] ) ? sanitize_text_field( $status['enforcement'] ) : 'soft';
		$status['offer_slug'] = isset( $status['offer_slug'] ) ? sanitize_key( (string) $status['offer_slug'] ) : '';
		$status['max_site_activations'] = isset( $status['max_site_activations'] ) ? max( 1, (int) $status['max_site_activations'] ) : 1;
		if ( ! isset( $status['allowances'] ) || ! is_array( $status['allowances'] ) ) {
			$status['allowances'] = array();
		}

		return $status;
	}

	/**
	 * Register settings option.
	 */
	public static function register_settings() {
		register_setting(
			'ecomcine_license_group',
			self::OPTION_KEY,
			array( __CLASS__, 'sanitize_settings' )
		);
	}

	/**
	 * Register licensing submenu under EcomCine.
	 */
	public static function register_menu() {
		add_submenu_page(
			'ecomcine-settings',
			'EcomCine Licensing',
			'Licensing',
			'manage_options',
			'ecomcine-licensing',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Sanitize saved licensing fields.
	 */
	public static function sanitize_settings( $input ) {
		$defaults = self::defaults();
		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$enforcement_mode = isset( $input['enforcement_mode'] ) ? sanitize_text_field( $input['enforcement_mode'] ) : '';
		$allowed_modes = array( 'soft', 'strict' );

		return array(
			'license_key'       => isset( $input['license_key'] ) ? sanitize_text_field( $input['license_key'] ) : '',
			'enforcement_mode'  => in_array( $enforcement_mode, $allowed_modes, true ) ? $enforcement_mode : 'soft',
		);
	}

	/**
	 * Handle manual verify/validate action from admin UI.
	 */
	public static function handle_verify() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'ecomcine_license_verify' );

		$ok = self::sync_entitlement( 'admin_verify' );
		$redirect = add_query_arg(
			array(
				'page' => 'ecomcine-licensing',
				'ecomcine_license_result' => $ok ? 'success' : 'error',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle clear/deactivate action from admin UI.
	 */
	public static function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'ecomcine_license_clear' );

		self::clear_remote_state();
		update_option( self::OPTION_LAST_ERROR, '', false );

		$redirect = add_query_arg(
			array(
				'page' => 'ecomcine-licensing',
				'ecomcine_license_result' => 'cleared',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function get_cached_entitlement() {
		$value = get_option( self::OPTION_ENTITLEMENT, array() );
		return is_array( $value ) ? $value : array();
	}

	private static function set_cached_entitlement( $entitlement ) {
		update_option( self::OPTION_ENTITLEMENT, is_array( $entitlement ) ? $entitlement : array(), false );
	}

	private static function generate_site_fingerprint() {
		$parts = array(
			network_home_url( '/' ),
			home_url( '/' ),
			site_url( '/' ),
			(string) get_current_blog_id(),
		);

		return strtoupper( substr( hash( 'sha256', implode( '|', $parts ) ), 0, 16 ) );
	}

	private static function clear_remote_state() {
		delete_option( self::OPTION_ACTIVATION_ID );
		delete_option( self::OPTION_SITE_TOKEN );
		delete_option( self::OPTION_ACTIVATION_LICENSE_REF );
		delete_option( self::OPTION_ENTITLEMENT );
		delete_option( self::OPTION_LAST_SYNC );
	}

	private static function mask_license_key( $license_key ) {
		$key = (string) $license_key;
		if ( strlen( $key ) < 8 ) {
			return '****';
		}

		return substr( $key, 0, 4 ) . '...' . substr( $key, -4 );
	}

	/**
	 * Run activation if needed and then resolve entitlement.
	 */
	public static function sync_entitlement( $reason = 'runtime' ) {
		$settings = self::get_settings();
		$license_key = strtoupper( trim( (string) ( $settings['license_key'] ?? '' ) ) );
		if ( '' === $license_key ) {
			update_option( self::OPTION_LAST_ERROR, 'License key is required.', false );
			return false;
		}

		$license_ref = self::mask_license_key( $license_key );
		$activation_id = (string) get_option( self::OPTION_ACTIVATION_ID, '' );
		$site_token = (string) get_option( self::OPTION_SITE_TOKEN, '' );
		$bound_license = (string) get_option( self::OPTION_ACTIVATION_LICENSE_REF, '' );

		if ( '' !== $bound_license && $bound_license !== $license_ref ) {
			self::clear_remote_state();
			$activation_id = '';
			$site_token = '';
		}

		$base_url = self::get_control_plane_base_url();
		if ( '' === $activation_id || '' === $site_token ) {
			if ( ! self::activate_remote( $base_url, $license_key, $license_ref ) ) {
				return false;
			}
			$activation_id = (string) get_option( self::OPTION_ACTIVATION_ID, '' );
			$site_token = (string) get_option( self::OPTION_SITE_TOKEN, '' );
		}

		if ( '' === $activation_id || '' === $site_token ) {
			update_option( self::OPTION_LAST_ERROR, 'Activation credentials missing after verify.', false );
			return false;
		}

		$payload = array(
			'activation_id'    => $activation_id,
			'site_fingerprint' => self::generate_site_fingerprint(),
			'plugin_version'   => defined( 'ECOMCINE_VERSION' ) ? ECOMCINE_VERSION : 'unknown',
			'reason'           => sanitize_key( (string) $reason ),
		);

		$response = self::post_json( trailingslashit( $base_url ) . 'entitlements/resolve', $payload, $site_token );
		if ( is_wp_error( $response ) ) {
			update_option( self::OPTION_LAST_ERROR, sprintf( 'Entitlement sync failed: %s', $response->get_error_message() ), false );
			return false;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status_code < 200 || $status_code >= 300 || ! is_array( $body ) || empty( $body['success'] ) || ! is_array( $body['data'] ?? null ) ) {
			$cp_error = '';
			if ( is_array( $body ) ) {
				$cp_error = (string) ( $body['error'] ?? $body['message'] ?? '' );
			}
			$error = '' !== $cp_error ? sprintf( 'Entitlement sync rejected: %s', $cp_error ) : sprintf( 'Entitlement sync rejected (HTTP %d).', $status_code );
			update_option( self::OPTION_LAST_ERROR, $error, false );
			return false;
		}

		$normalized = self::normalize_contract( (array) $body['data'] );
		self::set_cached_entitlement( $normalized );
		update_option( self::OPTION_LAST_SYNC, gmdate( 'c' ), false );
		update_option( self::OPTION_LAST_ERROR, '', false );

		return true;
	}

	private static function activate_remote( $base_url, $license_key, $license_ref ) {
		$payload = array(
			'license_key'      => (string) $license_key,
			'site_url'         => site_url( '/' ),
			'site_fingerprint' => self::generate_site_fingerprint(),
			'plugin_version'   => defined( 'ECOMCINE_VERSION' ) ? ECOMCINE_VERSION : 'unknown',
		);

		$response = self::post_json( trailingslashit( (string) $base_url ) . 'activations', $payload, '' );
		if ( is_wp_error( $response ) ) {
			update_option( self::OPTION_LAST_ERROR, sprintf( 'Activation failed: %s', $response->get_error_message() ), false );
			return false;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status_code < 200 || $status_code >= 300 || ! is_array( $body ) || empty( $body['success'] ) || ! is_array( $body['data'] ?? null ) ) {
			$cp_error = '';
			if ( is_array( $body ) ) {
				$cp_error = (string) ( $body['error'] ?? $body['message'] ?? '' );
			}
			$error = '' !== $cp_error ? sprintf( 'Activation rejected: %s', $cp_error ) : sprintf( 'Activation rejected (HTTP %d).', $status_code );
			update_option( self::OPTION_LAST_ERROR, $error, false );
			return false;
		}

		$data = (array) $body['data'];
		update_option( self::OPTION_ACTIVATION_ID, sanitize_text_field( (string) ( $data['activation_id'] ?? '' ) ), false );
		update_option( self::OPTION_SITE_TOKEN, sanitize_text_field( (string) ( $data['site_token'] ?? '' ) ), false );
		update_option( self::OPTION_ACTIVATION_LICENSE_REF, sanitize_text_field( (string) $license_ref ), false );

		return true;
	}

	private static function post_json( $url, $payload, $site_token = '', $timeout = 12 ) {
		$headers = array(
			'Content-Type' => 'application/json',
		);
		if ( '' !== (string) $site_token ) {
			$headers[ self::SITE_TOKEN_HEADER ] = (string) $site_token;
		}

		return wp_remote_post(
			(string) $url,
			array(
				'timeout' => max( 1, (int) $timeout ),
				'headers' => $headers,
				'body'    => (string) wp_json_encode( is_array( $payload ) ? $payload : array() ),
			)
		);
	}

	/**
	 * @param array<string,mixed> $contract
	 * @return array<string,mixed>
	 */
	private static function normalize_contract( $contract ) {
		$plan = sanitize_key( (string) ( $contract['plan'] ?? 'free' ) );
		$limits = isset( $contract['limits'] ) && is_array( $contract['limits'] ) ? $contract['limits'] : array();
		$policy = isset( $contract['activation_policy'] ) && is_array( $contract['activation_policy'] ) ? $contract['activation_policy'] : array();

		return array(
			'plan_slug'          => $plan,
			'status'             => sanitize_text_field( (string) ( $contract['status'] ?? 'inactive' ) ),
			'limits'             => $limits,
			'activation_policy'  => $policy,
			'contract_signature' => sanitize_text_field( (string) ( $contract['contract_signature'] ?? '' ) ),
			'normalized_at'      => gmdate( 'c' ),
		);
	}

	/**
	 * Show an admin notice for strict mode + inactive license.
	 */
	public static function render_admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = self::get_status();
		if ( ! empty( $status['active'] ) ) {
			return;
		}
		if ( empty( $status['enforcement'] ) || 'strict' !== $status['enforcement'] ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>EcomCine license is inactive in strict mode. Enter your license key and verify under EcomCine > Licensing.</p></div>';
	}

	/**
	 * Render licensing settings screen.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		$status = self::get_status();
		$catalog = EcomCine_Offer_Catalog::get_catalog();
		$result = isset( $_GET['ecomcine_license_result'] ) ? sanitize_key( (string) wp_unslash( $_GET['ecomcine_license_result'] ) ) : '';
		if ( 'success' === $result ) {
			echo '<div class="notice notice-success"><p>License verified and entitlement refreshed.</p></div>';
		} elseif ( 'error' === $result ) {
			echo '<div class="notice notice-error"><p>License verification failed. See Last Error below.</p></div>';
		} elseif ( 'cleared' === $result ) {
			echo '<div class="notice notice-info"><p>Stored activation/session state cleared.</p></div>';
		}
		?>
		<div class="wrap">
			<h1>EcomCine Licensing</h1>
			<p>Enter your license key and validate against EcomCine billing control-plane.</p>
			<table class="widefat striped" style="max-width: 780px; margin: 12px 0 24px 0;">
				<tbody>
					<tr><th style="width:220px;">License Status</th><td><?php echo ! empty( $status['active'] ) ? 'Active' : 'Inactive'; ?></td></tr>
					<tr><th>Status Source</th><td><?php echo esc_html( isset( $status['source'] ) ? $status['source'] : 'unknown' ); ?></td></tr>
					<tr><th>Enforcement</th><td><?php echo esc_html( isset( $status['enforcement'] ) ? $status['enforcement'] : 'soft' ); ?></td></tr>
					<tr><th>Control Plane URL</th><td><code><?php echo esc_html( self::get_control_plane_base_url() ); ?></code></td></tr>
					<tr><th>Activation ID</th><td><?php echo esc_html( isset( $status['activation_id'] ) ? (string) $status['activation_id'] : '' ); ?></td></tr>
					<tr><th>Last Sync</th><td><?php echo esc_html( isset( $status['last_sync'] ) ? (string) $status['last_sync'] : '' ); ?></td></tr>
					<tr><th>Last Error</th><td><?php echo esc_html( isset( $status['last_error'] ) ? (string) $status['last_error'] : '' ); ?></td></tr>
					<tr><th>Resolved Offer</th><td><?php echo esc_html( isset( $status['offer_slug'] ) && '' !== $status['offer_slug'] ? $status['offer_slug'] : 'unknown' ); ?></td></tr>
					<tr><th>Max Site Activations</th><td><?php echo esc_html( (string) ( isset( $status['max_site_activations'] ) ? (int) $status['max_site_activations'] : 1 ) ); ?></td></tr>
				</tbody>
			</table>
			<h2>WMOS FluentCart Parity Offers</h2>
			<p>Canonical 4-offer mapping mirrored from WebmasterOS billing metadata for freemium to agency tiers.</p>
			<table class="widefat striped" style="max-width: 980px; margin: 12px 0 24px 0;">
				<thead>
					<tr>
						<th>Plan</th>
						<th>Product ID</th>
						<th>Variation ID</th>
						<th>Activation Limit</th>
						<th>AI Mode</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $catalog as $row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) ( $row['plan'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( (int) ( $row['product_id'] ?? 0 ) ) ); ?></td>
						<td><?php echo esc_html( (string) ( (int) ( $row['variation_id'] ?? 0 ) ) ); ?></td>
						<td><?php echo esc_html( (string) ( (int) ( $row['max_site_activations'] ?? 1 ) ) ); ?></td>
						<td><?php echo esc_html( (string) ( $row['allowances']['ai_mode'] ?? '' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<form method="post" action="options.php">
				<?php settings_fields( 'ecomcine_license_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ecomcine-license-key">License Key</label></th>
						<td><input id="ecomcine-license-key" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[license_key]" value="<?php echo esc_attr( $settings['license_key'] ); ?>" class="regular-text" autocomplete="off" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="ecomcine-enforcement-mode">Enforcement Mode</label></th>
						<td>
							<select id="ecomcine-enforcement-mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enforcement_mode]">
								<option value="soft" <?php selected( $settings['enforcement_mode'], 'soft' ); ?>>Soft (warn only)</option>
								<option value="strict" <?php selected( $settings['enforcement_mode'], 'strict' ); ?>>Strict (enforce inactive state)</option>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Save Licensing Settings' ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px; display:inline-block; margin-right:10px;">
				<?php wp_nonce_field( 'ecomcine_license_verify' ); ?>
				<input type="hidden" name="action" value="ecomcine_license_verify" />
				<?php submit_button( 'Verify / Validate License', 'primary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px; display:inline-block;">
				<?php wp_nonce_field( 'ecomcine_license_clear' ); ?>
				<input type="hidden" name="action" value="ecomcine_license_clear" />
				<?php submit_button( 'Clear Activation State', 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
