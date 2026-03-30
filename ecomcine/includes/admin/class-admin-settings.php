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
		add_action( 'wp_head', array( __CLASS__, 'render_style_tokens_css' ), 30 );
		add_action( 'init', array( __CLASS__, 'register_bootstrap_shortcodes' ) );
		add_action( 'admin_post_ecomcine_create_bootstrap_pages', array( __CLASS__, 'handle_create_bootstrap_pages' ) );
		add_action( 'admin_post_ecomcine_install_activate_theme', array( __CLASS__, 'handle_install_activate_theme' ) );
	}

	/**
	 * Register helper shortcodes used by auto-generated onboarding pages.
	 */
	public static function register_bootstrap_shortcodes() {
		if ( ! shortcode_exists( 'ecomcine_categories' ) ) {
			add_shortcode( 'ecomcine_categories', array( __CLASS__, 'shortcode_categories' ) );
		}

		if ( ! shortcode_exists( 'ecomcine_locations' ) ) {
			add_shortcode( 'ecomcine_locations', array( __CLASS__, 'shortcode_locations' ) );
		}
	}

	/**
	 * Render basic categories list for onboarding pages.
	 */
	public static function shortcode_categories() {
		$taxonomy = taxonomy_exists( 'product_cat' ) ? 'product_cat' : ( taxonomy_exists( 'category' ) ? 'category' : '' );
		if ( '' === $taxonomy ) {
			return '<p>No category taxonomy is available yet.</p>';
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 30,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '<p>No categories found yet.</p>';
		}

		$html = '<ul class="ecomcine-term-list ecomcine-term-list--categories">';
		foreach ( $terms as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$html .= '<li><a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a></li>';
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Render basic locations list for onboarding pages.
	 */
	public static function shortcode_locations() {
		$candidate_taxonomies = array( 'location', 'product_location', 'pa_location' );
		$taxonomy = '';
		foreach ( $candidate_taxonomies as $candidate ) {
			if ( taxonomy_exists( $candidate ) ) {
				$taxonomy = $candidate;
				break;
			}
		}

		if ( '' === $taxonomy ) {
			return '<p>No location taxonomy is available yet.</p>';
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 30,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '<p>No locations found yet.</p>';
		}

		$html = '<ul class="ecomcine-term-list ecomcine-term-list--locations">';
		foreach ( $terms as $term ) {
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$html .= '<li><a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a></li>';
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Admin action: create default onboarding pages.
	 */
	public static function handle_create_bootstrap_pages() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'ecomcine' ) );
		}

		check_admin_referer( 'ecomcine_bootstrap_pages' );

		$pages = array(
			array(
				'title'     => 'Showcase',
				'slug'      => 'showcase',
				'shortcode' => '[tm_talent_showcase]',
			),
			array(
				'title'     => 'Talents',
				'slug'      => 'talents',
				'shortcode' => '[tm_talent_player]',
			),
			array(
				'title'     => 'Categories',
				'slug'      => 'categories',
				'shortcode' => '[ecomcine_categories]',
			),
			array(
				'title'     => 'Locations',
				'slug'      => 'locations',
				'shortcode' => '[ecomcine_locations]',
			),
		);

		$created = 0;
		$updated = 0;
		foreach ( $pages as $page ) {
			$post = get_page_by_path( $page['slug'], OBJECT, 'page' );
			if ( $post ) {
				$content = is_string( $post->post_content ) ? $post->post_content : '';
				if ( false === strpos( $content, $page['shortcode'] ) ) {
					wp_update_post(
						array(
							'ID'           => (int) $post->ID,
							'post_content' => trim( $content . "\n\n" . $page['shortcode'] ),
						)
					);
					$updated++;
				}
				continue;
			}

			$new_id = wp_insert_post(
				array(
					'post_title'   => $page['title'],
					'post_name'    => $page['slug'],
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_content' => $page['shortcode'],
				)
			);

			if ( ! is_wp_error( $new_id ) && $new_id > 0 ) {
				$created++;
			}
		}

		$redirect = add_query_arg(
			array(
				'page'                => 'ecomcine-settings',
				'tab'                 => 'settings',
				'ecomcine_pages_done' => 1,
				'ecomcine_created'    => $created,
				'ecomcine_updated'    => $updated,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Admin action: install (if needed) and activate preferred theme.
	 */
	public static function handle_install_activate_theme() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'ecomcine' ) );
		}

		check_admin_referer( 'ecomcine_bootstrap_theme' );

		$target_slugs = array( 'tm-theme', 'astra-child', 'astra' );
		foreach ( $target_slugs as $slug ) {
			$theme = wp_get_theme( $slug );
			if ( $theme->exists() ) {
				switch_theme( $slug );
				$redirect = add_query_arg(
					array(
						'page'                 => 'ecomcine-settings',
						'tab'                  => 'settings',
						'ecomcine_theme_done'  => 1,
						'ecomcine_theme_slug'  => $slug,
					),
					admin_url( 'admin.php' )
				);
				wp_safe_redirect( $redirect );
				exit;
			}
		}

		if ( ! function_exists( 'themes_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme-install.php';
		}
		if ( ! class_exists( 'Theme_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$api = themes_api(
			'theme_information',
			array(
				'slug'   => 'astra',
				'fields' => array( 'sections' => false ),
			)
		);

		$error_code = '';
		if ( is_wp_error( $api ) || empty( $api->download_link ) ) {
			$error_code = 'astra_api';
		} else {
			$upgrader = new Theme_Upgrader();
			$result = $upgrader->install( (string) $api->download_link );
			if ( is_wp_error( $result ) || ! $result ) {
				$error_code = 'astra_install';
			} else {
				switch_theme( 'astra' );
			}
		}

		$args = array(
			'page'                 => 'ecomcine-settings',
			'tab'                  => 'settings',
			'ecomcine_theme_done'  => 1,
			'ecomcine_theme_slug'  => wp_get_theme()->get_stylesheet(),
		);
		if ( '' !== $error_code ) {
			$args['ecomcine_theme_error'] = $error_code;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Default settings payload.
	 */
	public static function defaults() {
		return array(
			'runtime_mode' => 'wp_woo_dokan_booking',
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
		return isset( $settings['runtime_mode'] ) ? (string) $settings['runtime_mode'] : 'wp_woo_dokan_booking';
	}

	/**
	 * Canonical set of valid mode slugs.
	 */
	public static function allowed_modes(): array {
		return array(
			'wp_cpt',
			'wp_woo',
			'wp_woo_booking',
			'wp_woo_dokan',
			'wp_woo_dokan_booking',
			'wp_fluentcart',
		);
	}

	/**
	 * Return the plugin capabilities required for a given mode.
	 * Keys match EcomCine_Plugin_Capability::snapshot() keys.
	 *
	 * @param string $mode
	 * @return string[]
	 */
	public static function mode_prerequisites( string $mode ): array {
		switch ( $mode ) {
			case 'wp_woo':
				return array( 'woocommerce' );
			case 'wp_woo_booking':
				return array( 'woocommerce', 'wc_bookings' );
			case 'wp_woo_dokan':
				return array( 'woocommerce', 'dokan' );
			case 'wp_woo_dokan_booking':
				return array( 'woocommerce', 'dokan', 'wc_bookings' );
			case 'wp_fluentcart':
				return array( 'fluentcart' );
			default: // wp_cpt
				return array();
		}
	}

	/**
	 * Return true when all prerequisites for a mode are currently satisfied.
	 *
	 * @param string $mode
	 * @return bool
	 */
	public static function mode_prerequisites_met( string $mode ): bool {
		if ( ! class_exists( 'EcomCine_Plugin_Capability', false ) ) {
			return true; // can't check — allow
		}
		$caps = EcomCine_Plugin_Capability::snapshot();
		foreach ( self::mode_prerequisites( $mode ) as $cap ) {
			if ( empty( $caps[ $cap ]['present'] ) ) {
				return false;
			}
		}
		return true;
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

		// Migrate legacy slugs from old two-option system.
		if ( 'preferred_stack' === $runtime_mode ) {
			$runtime_mode = 'wp_woo_dokan_booking';
		} elseif ( 'baseline_wp' === $runtime_mode ) {
			$runtime_mode = 'wp_cpt';
		}

		if ( ! in_array( $runtime_mode, self::allowed_modes(), true ) ) {
			$runtime_mode = $defaults['runtime_mode'];
		}

		// Reject if prerequisites are not met; keep existing saved mode instead.
		if ( ! self::mode_prerequisites_met( $runtime_mode ) ) {
			$previous = self::get_runtime_mode();
			$runtime_mode = in_array( $previous, self::allowed_modes(), true ) ? $previous : $defaults['runtime_mode'];
			add_settings_error(
				self::OPTION_KEY,
				'runtime_mode_prereqs',
				__( 'Runtime mode not saved: one or more required plugins for the selected mode are not active. Activate the missing plugins first.', 'ecomcine' ),
				'error'
			);
		}

		$sanitized['runtime_mode'] = $runtime_mode;

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
	 * Render a read-only "Plugin Requirements" panel above the settings form.
	 * Shows which third-party plugins are active and what each is required by.
	 */
	public static function render_plugin_capabilities_section(): void {
		if ( ! class_exists( 'EcomCine_Plugin_Capability', false ) ) {
			return;
		}

		$caps   = EcomCine_Plugin_Capability::snapshot();
		$labels = array(
			'woocommerce'    => 'WooCommerce',
			'wc_bookings'    => 'WooCommerce Bookings',
			'dokan'          => 'Dokan Lite',
			'dokan_pro'      => 'Dokan Pro',
			'fluentcart'     => 'FluentCart',
			'fluentcart_pro' => 'FluentCart Pro',
		);
		?>
		<h2>Plugin Requirements</h2>
		<p>Read-only status of plugins that EcomCine features depend on. Install or activate missing plugins to unlock the corresponding features.</p>
		<table class="widefat striped" style="max-width:700px;margin-bottom:24px;">
			<thead>
				<tr>
					<th>Plugin</th>
					<th>Required by</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $caps as $key => $info ) : ?>
					<tr>
						<td><?php echo esc_html( $labels[ $key ] ?? $key ); ?></td>
						<td><code><?php echo esc_html( $info['required_by'] ); ?></code></td>
						<td>
							<?php if ( $info['present'] ) : ?>
								<span style="color:#00a32a;font-weight:600;">&#10003; Active</span>
							<?php else : ?>
								<span style="color:#d63638;">&#10007; Not detected</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render admin settings page (tabbed: Settings | Licensing).
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$settings   = self::get_settings();
		$features   = $settings['features'];
		$tokens     = $settings['style_tokens'];
		$labels     = $settings['labels'];
		$created_pages = isset( $_GET['ecomcine_created'] ) ? absint( $_GET['ecomcine_created'] ) : 0;
		$updated_pages = isset( $_GET['ecomcine_updated'] ) ? absint( $_GET['ecomcine_updated'] ) : 0;
		$theme_slug = isset( $_GET['ecomcine_theme_slug'] ) ? sanitize_key( wp_unslash( $_GET['ecomcine_theme_slug'] ) ) : '';
		$theme_error = isset( $_GET['ecomcine_theme_error'] ) ? sanitize_key( wp_unslash( $_GET['ecomcine_theme_error'] ) ) : '';
		?>
		<div class="wrap">
			<h1>EcomCine</h1>

			<?php if ( isset( $_GET['ecomcine_pages_done'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php echo esc_html( sprintf( 'Bootstrap pages processed. Created: %d, Updated: %d.', $created_pages, $updated_pages ) ); ?>
				</p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['ecomcine_theme_done'] ) && '' === $theme_error ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php echo esc_html( sprintf( 'Theme activated: %s', $theme_slug ) ); ?>
				</p></div>
			<?php elseif ( isset( $_GET['ecomcine_theme_done'] ) ) : ?>
				<div class="notice notice-error is-dismissible"><p>
					<?php echo esc_html( sprintf( 'Theme setup could not complete (%s). Please install/activate theme manually.', $theme_error ) ); ?>
				</p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper" style="margin-bottom:0;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ecomcine-settings&tab=settings' ) ); ?>"
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">Settings</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ecomcine-settings&tab=licensing' ) ); ?>"
				   class="nav-tab <?php echo 'licensing' === $active_tab ? 'nav-tab-active' : ''; ?>">Licensing</a>
			</nav>

			<?php if ( 'licensing' === $active_tab ) : ?>

				<?php if ( class_exists( 'EcomCine_Licensing', false ) ) : ?>
					<?php EcomCine_Licensing::render_tab_content(); ?>
				<?php else : ?>
					<p style="margin-top:16px;">Licensing module not loaded.</p>
				<?php endif; ?>

			<?php else : ?>

				<style>
				.ecomcine-settings-grid {
					display: grid;
					grid-template-columns: 1fr 1fr;
					gap: 20px;
					max-width: 1060px;
					margin-top: 20px;
				}
				.ecomcine-settings-card {
					background: #fff;
					border: 1px solid #ccd0d4;
					border-radius: 3px;
					padding: 16px 20px 12px;
					box-shadow: 0 1px 1px rgba(0,0,0,.04);
				}
				.ecomcine-settings-card h2 {
					margin-top: 0;
					padding-bottom: 8px;
					border-bottom: 1px solid #eee;
					font-size: 14px;
					font-weight: 600;
				}
				.ecomcine-settings-card .form-table {
					margin: 0;
				}
				.ecomcine-settings-card .form-table th {
					width: 130px;
					padding-left: 0;
					font-weight: 500;
				}
				.ecomcine-settings-card .form-table td {
					padding-right: 0;
				}
				.ecomcine-settings-full {
					grid-column: 1 / -1;
				}
				.ecomcine-bootstrap-actions {
					background: #fff;
					border: 1px solid #ccd0d4;
					border-radius: 3px;
					padding: 16px 20px 12px;
					box-shadow: 0 1px 1px rgba(0,0,0,.04);
					margin: 0 0 20px;
					max-width: 1060px;
				}
				.ecomcine-bootstrap-actions h2 {
					margin-top: 0;
					padding-bottom: 8px;
					border-bottom: 1px solid #eee;
					font-size: 14px;
					font-weight: 600;
				}
				@media ( max-width: 782px ) {
					.ecomcine-settings-grid { grid-template-columns: 1fr; }
				}
				</style>

				<?php self::render_plugin_capabilities_section(); ?>

				<div class="ecomcine-bootstrap-actions">
					<h2>Site Bootstrap</h2>
					<p>Create baseline pages and activate a supported theme for fresh WordPress installs.</p>
					<p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right:10px;">
							<?php wp_nonce_field( 'ecomcine_bootstrap_pages' ); ?>
							<input type="hidden" name="action" value="ecomcine_create_bootstrap_pages" />
							<?php submit_button( 'Create Pages', 'secondary', 'submit', false, array( 'onclick' => "return confirm('Create/patch Showcase, Talents, Categories, and Locations pages with their shortcodes?');" ) ); ?>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
							<?php wp_nonce_field( 'ecomcine_bootstrap_theme' ); ?>
							<input type="hidden" name="action" value="ecomcine_install_activate_theme" />
							<?php submit_button( 'Install + Activate Theme', 'secondary', 'submit', false, array( 'onclick' => "return confirm('Install (if needed) and activate the recommended theme now?');" ) ); ?>
						</form>
					</p>
					<p class="description">Pages: Showcase [tm_talent_showcase], Talents [tm_talent_player], Categories [ecomcine_categories], Locations [ecomcine_locations].</p>
				</div>

				<form method="post" action="options.php">
					<?php settings_fields( 'ecomcine_settings_group' ); ?>

					<div class="ecomcine-settings-grid">

						<div class="ecomcine-settings-card">
							<h2>Runtime Preset</h2>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="ecomcine-runtime-mode">Stack</label></th>
									<td>
										<?php
										$mode_options = array(
											'wp_cpt'               => 'WordPress CPT (Blog / Directory mode)',
											'wp_woo'               => 'WP + WooCommerce (Single Store mode)',
											'wp_woo_booking'       => 'WP + Woo + Booking (Single Store booking mode)',
											'wp_woo_dokan'         => 'WP + Woo + Dokan (Marketplace Mode)',
											'wp_woo_dokan_booking' => 'WP + Woo + Dokan + Booking (Marketplace Mode)',
										);
										?>
										<select id="ecomcine-runtime-mode" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[runtime_mode]">
											<?php foreach ( $mode_options as $slug => $mode_label ) : ?>
												<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $settings['runtime_mode'], $slug ); ?>>
													<?php echo esc_html( $mode_label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<?php
										if ( class_exists( 'EcomCine_Plugin_Capability', false ) ) {
											$missing      = array();
											$caps         = EcomCine_Plugin_Capability::snapshot();
											$prereq_labels = array(
												'woocommerce' => 'WooCommerce',
												'wc_bookings' => 'WooCommerce Bookings',
												'dokan'       => 'Dokan Lite',											'fluentcart'  => 'FluentCart',											);
											foreach ( self::mode_prerequisites( $settings['runtime_mode'] ) as $cap ) {
												if ( empty( $caps[ $cap ]['present'] ) ) {
													$missing[] = $prereq_labels[ $cap ] ?? $cap;
												}
											}
											if ( ! empty( $missing ) ) {
												echo '<p class="description" style="color:#d63638;">Missing: ' . esc_html( implode( ', ', $missing ) ) . '</p>';
											} else {
												echo '<p class="description" style="color:#00a32a;">All prerequisites active.</p>';
											}
										}
										?>
									</td>
								</tr>
							</table>
						</div>

						<div class="ecomcine-settings-card">
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
						</div>

						<div class="ecomcine-settings-card">
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
						</div>

						<div class="ecomcine-settings-card">
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
						</div>

						<div class="ecomcine-settings-full">
							<?php submit_button( 'Save EcomCine Settings' ); ?>
						</div>

					</div><!-- .ecomcine-settings-grid -->
				</form>

			<?php endif; ?>
		</div>
		<?php
	}
}
