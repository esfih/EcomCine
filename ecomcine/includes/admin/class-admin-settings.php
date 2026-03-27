<?php
/**
 * EcomCine admin settings and feature toggles.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Admin_Settings {
	const OPTION_KEY = 'ecomcine_settings';

	/**
	 * Register admin hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_filter( 'body_class', array( __CLASS__, 'apply_layout_body_class' ), 30 );
		add_action( 'wp_head', array( __CLASS__, 'render_style_tokens_css' ), 30 );
	}

	/**
	 * Default settings payload.
	 */
	public static function defaults() {
		return array(
			'runtime_mode' => 'preferred_stack',
			'layout_preset' => 'cinematic',
			'features' => array(
				'media_player'  => true,
				'account_panel' => true,
				'booking_modal' => true,
			),
			'labels' => array(
				'talent_label' => 'Talent',
				'location_label' => 'Location',
			),
			'style_tokens' => array(
				'accent_color'  => '#D4AF37',
				'surface_color' => '#111111',
				'text_color'    => '#F5F5F5',
			),
		);
	}

	/**
	 * Resolve merged settings with defaults.
	 */
	public static function get_settings() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$defaults = self::defaults();
		$settings = wp_parse_args( $stored, $defaults );

		$settings['features'] = wp_parse_args(
			isset( $stored['features'] ) && is_array( $stored['features'] ) ? $stored['features'] : array(),
			$defaults['features']
		);
		$settings['style_tokens'] = wp_parse_args(
			isset( $stored['style_tokens'] ) && is_array( $stored['style_tokens'] ) ? $stored['style_tokens'] : array(),
			$defaults['style_tokens']
		);
		$settings['labels'] = wp_parse_args(
			isset( $stored['labels'] ) && is_array( $stored['labels'] ) ? $stored['labels'] : array(),
			$defaults['labels']
		);

		return $settings;
	}

	/**
	 * Determine whether a feature toggle is enabled.
	 */
	public static function is_feature_enabled( $feature_key ) {
		$settings = self::get_settings();
		if ( empty( $settings['features'][ $feature_key ] ) ) {
			return false;
		}

		return (bool) $settings['features'][ $feature_key ];
	}

	/**
	 * Get current runtime mode.
	 */
	public static function get_runtime_mode() {
		$settings = self::get_settings();
		return isset( $settings['runtime_mode'] ) ? (string) $settings['runtime_mode'] : 'preferred_stack';
	}

	/**
	 * Get current layout preset.
	 */
	public static function get_layout_preset() {
		$settings = self::get_settings();
		return isset( $settings['layout_preset'] ) ? (string) $settings['layout_preset'] : 'cinematic';
	}

	/**
	 * Get admin-managed labels.
	 */
	public static function get_labels() {
		$settings = self::get_settings();
		return isset( $settings['labels'] ) && is_array( $settings['labels'] )
			? $settings['labels']
			: self::defaults()['labels'];
	}

	/**
	 * Resolve single label with fallback.
	 */
	public static function get_label( $key, $fallback = '' ) {
		$labels = self::get_labels();
		if ( isset( $labels[ $key ] ) && '' !== trim( (string) $labels[ $key ] ) ) {
			return (string) $labels[ $key ];
		}

		return (string) $fallback;
	}

	/**
	 * Get style token settings.
	 */
	public static function get_style_tokens() {
		$settings = self::get_settings();
		return isset( $settings['style_tokens'] ) && is_array( $settings['style_tokens'] )
			? $settings['style_tokens']
			: self::defaults()['style_tokens'];
	}

	/**
	 * Register option and sanitization callback.
	 */
	public static function register_settings() {
		register_setting(
			'ecomcine_settings_group',
			self::OPTION_KEY,
			array( __CLASS__, 'sanitize_settings' )
		);
	}

	/**
	 * Add EcomCine settings page.
	 */
	public static function register_menu() {
		add_menu_page(
			'EcomCine Settings',
			'EcomCine',
			'manage_options',
			'ecomcine-settings',
			array( __CLASS__, 'render_settings_page' ),
			'dashicons-format-video',
			56
		);
	}

	/**
	 * Sanitize submitted settings.
	 */
	public static function sanitize_settings( $input ) {
		$defaults = self::defaults();
		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$sanitized = $defaults;

		$runtime_mode = isset( $input['runtime_mode'] ) ? sanitize_text_field( $input['runtime_mode'] ) : '';
		$allowed_modes = array( 'preferred_stack', 'baseline_wp' );
		$sanitized['runtime_mode'] = in_array( $runtime_mode, $allowed_modes, true )
			? $runtime_mode
			: $defaults['runtime_mode'];

		$layout_preset = isset( $input['layout_preset'] ) ? sanitize_text_field( $input['layout_preset'] ) : '';
		$allowed_presets = array( 'cinematic', 'clean', 'minimal' );
		$sanitized['layout_preset'] = in_array( $layout_preset, $allowed_presets, true )
			? $layout_preset
			: $defaults['layout_preset'];

		$feature_keys = array_keys( $defaults['features'] );
		foreach ( $feature_keys as $feature_key ) {
			$sanitized['features'][ $feature_key ] = ! empty( $input['features'][ $feature_key ] );
		}

		$style_keys = array_keys( $defaults['style_tokens'] );
		foreach ( $style_keys as $style_key ) {
			$raw = isset( $input['style_tokens'][ $style_key ] ) ? $input['style_tokens'][ $style_key ] : '';
			$color = sanitize_hex_color( $raw );
			$sanitized['style_tokens'][ $style_key ] = $color ? $color : $defaults['style_tokens'][ $style_key ];
		}

		$label_keys = array_keys( $defaults['labels'] );
		foreach ( $label_keys as $label_key ) {
			$raw_label = isset( $input['labels'][ $label_key ] ) ? sanitize_text_field( $input['labels'][ $label_key ] ) : '';
			$sanitized['labels'][ $label_key ] = '' !== $raw_label ? $raw_label : $defaults['labels'][ $label_key ];
		}

		return $sanitized;
	}

	/**
	 * Apply admin-managed layout class on frontend body.
	 */
	public static function apply_layout_body_class( $classes ) {
		if ( is_admin() ) {
			return $classes;
		}

		$preset = sanitize_html_class( self::get_layout_preset(), 'cinematic' );
		$classes[] = 'ecomcine-layout-' . $preset;

		return $classes;
	}

	/**
	 * Print style token CSS variables for frontend consumption.
	 */
	public static function render_style_tokens_css() {
		if ( is_admin() ) {
			return;
		}

		$tokens = self::get_style_tokens();
		$accent = isset( $tokens['accent_color'] ) ? $tokens['accent_color'] : '#D4AF37';
		$surface = isset( $tokens['surface_color'] ) ? $tokens['surface_color'] : '#111111';
		$text = isset( $tokens['text_color'] ) ? $tokens['text_color'] : '#F5F5F5';

		echo '<style id="ecomcine-style-tokens">:root{--ecomcine-accent-color:' . esc_html( $accent ) . ';--ecomcine-surface-color:' . esc_html( $surface ) . ';--ecomcine-text-color:' . esc_html( $text ) . ';}</style>';
	}

	/**
	 * Render admin settings page.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		$features = $settings['features'];
		$tokens = $settings['style_tokens'];
		$labels = $settings['labels'];
		?>
		<div class="wrap">
			<h1>EcomCine Settings</h1>
			<p>Phase 3 admin controls for runtime mode, layout presets, labels, feature toggles, and style tokens.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'ecomcine_settings_group' ); ?>

				<h2>Runtime Presets</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ecomcine-runtime-mode">Stack Preset</label></th>
						<td>
							<select id="ecomcine-runtime-mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[runtime_mode]">
								<option value="preferred_stack" <?php selected( $settings['runtime_mode'], 'preferred_stack' ); ?>>Preferred stack (Astra + Dokan + Woo)</option>
								<option value="baseline_wp" <?php selected( $settings['runtime_mode'], 'baseline_wp' ); ?>>Baseline WordPress mode</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ecomcine-layout-preset">Layout Preset</label></th>
						<td>
							<select id="ecomcine-layout-preset" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[layout_preset]">
								<option value="cinematic" <?php selected( $settings['layout_preset'], 'cinematic' ); ?>>Cinematic</option>
								<option value="clean" <?php selected( $settings['layout_preset'], 'clean' ); ?>>Clean</option>
								<option value="minimal" <?php selected( $settings['layout_preset'], 'minimal' ); ?>>Minimal</option>
							</select>
						</td>
					</tr>
				</table>

				<h2>Labels</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ecomcine-talent-label">Talent Label</label></th>
						<td>
							<input id="ecomcine-talent-label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[labels][talent_label]" value="<?php echo esc_attr( $labels['talent_label'] ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ecomcine-location-label">Location Label</label></th>
						<td>
							<input id="ecomcine-location-label" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[labels][location_label]" value="<?php echo esc_attr( $labels['location_label'] ); ?>" class="regular-text" />
						</td>
					</tr>
				</table>

				<h2>Feature Toggles</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Modules</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[features][media_player]" value="1" <?php checked( ! empty( $features['media_player'] ) ); ?> />
								Media player
							</label><br />
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[features][account_panel]" value="1" <?php checked( ! empty( $features['account_panel'] ) ); ?> />
								Account panel
							</label><br />
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[features][booking_modal]" value="1" <?php checked( ! empty( $features['booking_modal'] ) ); ?> />
								Booking modal
							</label>
						</td>
					</tr>
				</table>

				<h2>Style Tokens</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ecomcine-accent-color">Accent Color</label></th>
						<td>
							<input id="ecomcine-accent-color" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_tokens][accent_color]" value="<?php echo esc_attr( $tokens['accent_color'] ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ecomcine-surface-color">Surface Color</label></th>
						<td>
							<input id="ecomcine-surface-color" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_tokens][surface_color]" value="<?php echo esc_attr( $tokens['surface_color'] ); ?>" class="regular-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ecomcine-text-color">Text Color</label></th>
						<td>
							<input id="ecomcine-text-color" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[style_tokens][text_color]" value="<?php echo esc_attr( $tokens['text_color'] ); ?>" class="regular-text" />
						</td>
					</tr>
				</table>

				<?php submit_button( 'Save EcomCine Settings' ); ?>
			</form>
		</div>
		<?php
	}
}
