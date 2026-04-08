<?php
/**
 * Standalone storefront bridge for wp_cpt mode.
 *
 * Provides minimal Dokan-compatible shims so the existing store-header template
 * can render without Dokan/Woo plugins.
 *
 * @package TM_Store_UI
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TM_Standalone_Store_User', false ) ) {
	class TM_Standalone_Store_User {
		private int $vendor_id;
		private ?array $shop_info = null;

		public function __construct( int $vendor_id ) {
			$this->vendor_id = max( 0, $vendor_id );
		}

		public function get_id(): int {
			return $this->vendor_id;
		}

		public function get_shop_info(): array {
			if ( null === $this->shop_info ) {
				$info = function_exists( 'ecomcine_get_person_info' ) ? ecomcine_get_person_info( $this->vendor_id ) : array();
				$geo  = function_exists( 'ecomcine_get_geo' ) ? ecomcine_get_geo( $this->vendor_id ) : array();
				$this->shop_info = array(
					'store_name'        => (string) ( $info['store_name'] ?? '' ),
					'vendor_biography'  => (string) ( $info['bio'] ?? '' ),
					'phone'             => (string) ( $info['phone'] ?? '' ),
					'banner'            => (int) ( $info['banner_id'] ?? 0 ),
					'gravatar'          => (int) ( $info['avatar_id'] ?? 0 ),
					'address'           => isset( $info['address'] ) && is_array( $info['address'] ) ? $info['address'] : array(),
					'social'            => isset( $info['social'] ) && is_array( $info['social'] ) ? $info['social'] : array(),
					'location'          => (string) ( $geo['address'] ?? '' ),
					'geolocation'       => array(
						'latitude'  => (string) ( $geo['lat'] ?? '' ),
						'longitude' => (string) ( $geo['lng'] ?? '' ),
					),
				);
			}

			return $this->shop_info;
		}

		public function get_social_profiles(): array {
			$info = $this->get_shop_info();
			if ( ! empty( $info['social'] ) && is_array( $info['social'] ) ) {
				return $info['social'];
			}

			return array();
		}

		public function get_shop_name(): string {
			$info = function_exists( 'ecomcine_get_person_info' ) ? ecomcine_get_person_info( $this->vendor_id ) : array();
			$name = isset( $info['store_name'] ) ? (string) $info['store_name'] : '';
			if ( '' !== trim( $name ) ) {
				return $name;
			}

			$user = get_userdata( $this->vendor_id );
			if ( ! $user ) {
				return '';
			}

			$display = (string) $user->display_name;
			if ( '' !== trim( $display ) ) {
				return $display;
			}

			return (string) $user->user_login;
		}

		public function get_email(): string {
			$user = get_userdata( $this->vendor_id );
			return $user ? (string) $user->user_email : '';
		}

		public function show_email(): bool {
			return true;
		}

		public function get_phone(): string {
			$info = $this->get_shop_info();
			if ( ! empty( $info['phone'] ) && is_string( $info['phone'] ) ) {
				return $info['phone'];
			}

			$fallback = get_user_meta( $this->vendor_id, 'tm_contact_phone_main', true );
			return is_string( $fallback ) ? $fallback : '';
		}

		public function get_banner(): string {
			$info = $this->get_shop_info();
			$banner_id = isset( $info['banner'] ) ? absint( $info['banner'] ) : 0;
			if ( $banner_id > 0 ) {
				$url = wp_get_attachment_image_url( $banner_id, 'full' );
				if ( $url ) {
					return (string) $url;
				}
			}

			return '';
		}

		public function get_avatar(): string {
			$info = $this->get_shop_info();

			// Primary source: vendor profile attachment stored in profile settings.
			$avatar_id = isset( $info['gravatar'] ) ? absint( $info['gravatar'] ) : 0;
			if ( $avatar_id > 0 ) {
				$url = wp_get_attachment_image_url( $avatar_id, 'full' );
				if ( $url ) {
					return (string) $url;
				}
			}

			// Secondary source: pre-onboarding avatar meta.
			$meta_avatar_id = absint( (string) get_user_meta( $this->vendor_id, 'tm_preonboard_vendor_avatar_id', true ) );
			if ( $meta_avatar_id > 0 ) {
				$url = wp_get_attachment_image_url( $meta_avatar_id, 'full' );
				if ( $url ) {
					return (string) $url;
				}
			}

			$meta_avatar_url = (string) get_user_meta( $this->vendor_id, 'tm_preonboard_vendor_avatar_url', true );
			if ( '' !== trim( $meta_avatar_url ) ) {
				return $meta_avatar_url;
			}

			// Standalone source: tm_vendor CPT featured image.
			$vendor_post_id = $this->get_vendor_cpt_post_id();
			if ( $vendor_post_id > 0 ) {
				$thumb_id = (int) get_post_thumbnail_id( $vendor_post_id );
				if ( $thumb_id > 0 ) {
					$url = wp_get_attachment_image_url( $thumb_id, 'full' );
					if ( $url ) {
						return (string) $url;
					}
				}
			}

			// Fallback: WordPress avatar service.
			return (string) get_avatar_url( $this->vendor_id, array( 'size' => 512 ) );
		}

		private function get_vendor_cpt_post_id(): int {
			if ( class_exists( 'TMP_WP_Vendor_CPT', false ) && method_exists( 'TMP_WP_Vendor_CPT', 'get_post_id_for_vendor' ) ) {
				return (int) TMP_WP_Vendor_CPT::get_post_id_for_vendor( $this->vendor_id );
			}

			$post_ids = get_posts(
				array(
					'post_type'      => 'tm_vendor',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'   => '_tm_vendor_user_id',
							'value' => $this->vendor_id,
						),
					),
				)
			);

			return ! empty( $post_ids ) ? (int) $post_ids[0] : 0;
		}
	}
}

if ( ! class_exists( 'TM_Standalone_Dokan_Vendor_Manager', false ) ) {
	class TM_Standalone_Dokan_Vendor_Manager {
		public function get( $vendor_id ) {
			$vendor_id = absint( $vendor_id );
			if ( $vendor_id <= 0 ) {
				return null;
			}

			return new TM_Standalone_Store_User( $vendor_id );
		}
	}
}

if ( ! class_exists( 'TM_Standalone_Dokan_App', false ) ) {
	class TM_Standalone_Dokan_App {
		public TM_Standalone_Dokan_Vendor_Manager $vendor;

		public function __construct() {
			$this->vendor = new TM_Standalone_Dokan_Vendor_Manager();
		}
	}
}

if ( ! function_exists( 'tm_store_ui_get_store_user' ) ) {
	function tm_store_ui_get_store_user( int $vendor_id ) {
		if ( function_exists( 'dokan' ) ) {
			$app = dokan();
			if ( is_object( $app ) && isset( $app->vendor ) && is_object( $app->vendor ) && method_exists( $app->vendor, 'get' ) ) {
				return $app->vendor->get( $vendor_id );
			}
		}

		return new TM_Standalone_Store_User( $vendor_id );
	}
}

if ( ! function_exists( 'tm_store_ui_render_store_header' ) ) {
	/**
	 * Render the store header template with plugin-first routing.
	 *
	 * @param int $vendor_id Vendor/user ID.
	 * @return bool True when markup was rendered.
	 */
	function tm_store_ui_render_store_header( int $vendor_id ): bool {
		if ( $vendor_id <= 0 ) {
			return false;
		}

		$is_showcase_context = ! empty( $GLOBALS['tm_showcase_page'] )
			|| ( function_exists( 'tm_is_showcase_page' ) && tm_is_showcase_page() );

		set_query_var( 'author', $vendor_id );

		if ( $is_showcase_context && class_exists( 'TM_Media_Player_Assets', false ) && ! wp_script_is( 'tm-player-js', 'enqueued' ) ) {
			$showcase_ids = function_exists( 'tm_get_showcase_vendor_ids' ) ? tm_get_showcase_vendor_ids() : array( $vendor_id );
			TM_Media_Player_Assets::enqueue_for_showcase( $vendor_id, 'showcase', $showcase_ids );
		}

		if ( $is_showcase_context && function_exists( 'tm_account_panel_enqueue_assets' ) && ! wp_script_is( 'tm-account-panel-js', 'enqueued' ) ) {
			tm_account_panel_enqueue_assets( true );
		}

		ob_start();
		if ( function_exists( 'dokan_get_template_part' ) ) {
			dokan_get_template_part( 'store-header' );
		} else {
			$template = defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR . 'templates/vendor-store/store-header.php' : '';
			if ( $template && file_exists( $template ) ) {
				include $template;
			}
		}
		$html = trim( (string) ob_get_clean() );
		if ( '' === $html ) {
			return false;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return true;
	}
}

if ( ! function_exists( 'dokan' ) ) {
	function dokan() {
		static $tm_dokan_app = null;
		if ( null === $tm_dokan_app ) {
			$tm_dokan_app = new TM_Standalone_Dokan_App();
		}

		return $tm_dokan_app;
	}
}

if ( ! function_exists( 'dokan_get_store_tabs' ) ) {
	function dokan_get_store_tabs( $vendor_id ): array {
		return array();
	}
}

if ( ! function_exists( 'dokan_get_social_profile_fields' ) ) {
	function dokan_get_social_profile_fields(): array {
		return array(
			'facebook'  => array( 'label' => 'Facebook' ),
			'instagram' => array( 'label' => 'Instagram' ),
			'x'         => array( 'label' => 'X' ),
			'youtube'   => array( 'label' => 'YouTube' ),
		);
	}
}

if ( ! function_exists( 'dokan_current_datetime' ) ) {
	function dokan_current_datetime(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', wp_timezone() );
	}
}

if ( ! function_exists( 'dokan_get_seller_short_address' ) ) {
	function dokan_get_seller_short_address( $vendor_id, $full = false ): string {
		$info = function_exists( 'ecomcine_get_person_info' ) ? ecomcine_get_person_info( absint( $vendor_id ) ) : array();
		if ( empty( $info['address'] ) || ! is_array( $info['address'] ) ) {
			return '';
		}

		$parts = array_filter(
			array(
				$info['address']['street_1'] ?? '',
				$info['address']['city'] ?? '',
				$info['address']['state'] ?? '',
				$info['address']['zip'] ?? '',
				$info['address']['country'] ?? '',
			),
			'strlen'
		);

		if ( empty( $parts ) ) {
			return '';
		}

		$address = implode( ', ', $parts );
		if ( $full ) {
			return $address;
		}

		return (string) wp_html_excerpt( $address, 120, '...' );
	}
}

if ( ! function_exists( 'dokan_get_option' ) ) {
	function dokan_get_option( $option, $section, $default = '' ) {
		$group = get_option( (string) $section, array() );
		if ( is_array( $group ) && array_key_exists( (string) $option, $group ) ) {
			return $group[ (string) $option ];
		}

		return $default;
	}
}

if ( ! function_exists( 'dokan_get_vendor_store_banner_width' ) ) {
	function dokan_get_vendor_store_banner_width(): int {
		return 1600;
	}
}

if ( ! function_exists( 'dokan_is_vendor_info_hidden' ) ) {
	function dokan_is_vendor_info_hidden( $field ): bool {
		return false;
	}
}

if ( ! function_exists( 'dokan_get_readable_seller_rating' ) ) {
	function dokan_get_readable_seller_rating( $vendor_id ): string {
		return '';
	}
}

if ( ! function_exists( 'dokan_is_store_open' ) ) {
	function dokan_is_store_open( $vendor_id ): bool {
		return true;
	}
}

if ( ! function_exists( 'dokan_get_translated_days' ) ) {
	function dokan_get_translated_days(): array {
		return array(
			'monday'    => __( 'Monday', 'ecomcine' ),
			'tuesday'   => __( 'Tuesday', 'ecomcine' ),
			'wednesday' => __( 'Wednesday', 'ecomcine' ),
			'thursday'  => __( 'Thursday', 'ecomcine' ),
			'friday'    => __( 'Friday', 'ecomcine' ),
			'saturday'  => __( 'Saturday', 'ecomcine' ),
			'sunday'    => __( 'Sunday', 'ecomcine' ),
		);
	}
}

if ( ! function_exists( 'dokan_get_template_part' ) ) {
	function dokan_get_template_part( $slug, $name = '', $args = array() ) {
		$filename = $name ? "{$slug}-{$name}.php" : "{$slug}.php";
		$template = defined( 'TM_STORE_UI_DIR' ) ? TM_STORE_UI_DIR . 'templates/vendor-store/' . $filename : '';
		if ( ! $template || ! file_exists( $template ) ) {
			return;
		}

		if ( ! empty( $args ) && is_array( $args ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args, EXTR_SKIP );
		}

		include $template;
	}
}
