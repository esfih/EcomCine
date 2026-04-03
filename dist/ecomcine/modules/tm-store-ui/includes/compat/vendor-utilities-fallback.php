<?php
/**
 * QR helper fallback when canonical ecomcine utilities are unavailable.
 *
 * @package TM_Store_UI
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tm_get_vendor_public_profile_url' ) ) {
	function tm_get_vendor_public_profile_url( $vendor_id ) {
		$vendor_id = (int) $vendor_id;
		if ( ! $vendor_id || ! function_exists( 'dokan_get_store_url' ) ) {
			return '';
		}

		$canonical_url = dokan_get_store_url( $vendor_id );
		if ( empty( $canonical_url ) ) {
			return '';
		}

		$current_url = '';
		if ( ! is_admin() ) {
			global $wp;
			if ( isset( $wp ) && is_object( $wp ) && isset( $wp->request ) ) {
				$current_url = home_url( '/' . ltrim( (string) $wp->request, '/' ) . '/' );
			}
		}

		if ( $current_url ) {
			$canonical_path = untrailingslashit( (string) wp_parse_url( $canonical_url, PHP_URL_PATH ) );
			$current_path   = untrailingslashit( (string) wp_parse_url( $current_url, PHP_URL_PATH ) );
			if ( $canonical_path && 0 === strpos( $current_path, $canonical_path ) ) {
				return $current_url;
			}
		}

		return $canonical_url;
	}
}

if ( ! function_exists( 'tm_load_qr_library' ) ) {
	function tm_load_qr_library() {
		if ( class_exists( '\\chillerlan\\QRCode\\QRCode' ) ) {
			return true;
		}

		$autoload_paths = array(
			TM_STORE_UI_DIR . 'vendor/autoload.php',
			plugin_dir_path( TM_STORE_UI_FILE ) . 'vendor/autoload.php',
			get_stylesheet_directory() . '/vendor/autoload.php',
			get_stylesheet_directory() . '/lib/php-qrcode/vendor/autoload.php',
			get_template_directory() . '/vendor/autoload.php',
			get_template_directory() . '/lib/php-qrcode/vendor/autoload.php',
			defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/themes/ecomcine-base/vendor/autoload.php' : '',
			defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/themes/ecomcine-base/lib/php-qrcode/vendor/autoload.php' : '',
			defined( 'ECOMCINE_DIR' ) ? ECOMCINE_DIR . 'vendor/autoload.php' : '',
		);

		foreach ( $autoload_paths as $autoload ) {
			if ( ! $autoload || ! file_exists( $autoload ) ) {
				continue;
			}
			require_once $autoload;
			if ( class_exists( '\\chillerlan\\QRCode\\QRCode' ) ) {
				return true;
			}
		}

		return class_exists( '\\chillerlan\\QRCode\\QRCode' );
	}
}

if ( ! function_exists( 'tm_get_vendor_qr_svg_markup' ) ) {
	function tm_get_vendor_qr_svg_markup( $vendor_id, $args = array() ) {
		$vendor_id = (int) $vendor_id;
		if ( ! $vendor_id ) {
			return '';
		}

		$url = tm_get_vendor_public_profile_url( $vendor_id );
		if ( empty( $url ) ) {
			return '';
		}

		$defaults = array(
			'size'    => 320,
			'context' => 'default',
		);
		$args = wp_parse_args( $args, $defaults );

		$cache_key = 'tm_vendor_qr_svg_' . md5( $url . '|' . (string) $args['size'] . '|' . (string) $args['context'] );
		$cached    = get_transient( $cache_key );
		if ( $cached ) {
			return $cached;
		}

		if ( ! tm_load_qr_library() ) {
			return '';
		}
		if ( ! class_exists( '\\chillerlan\\QRCode\\QROptions' ) || ! class_exists( '\\chillerlan\\QRCode\\QRCode' ) ) {
			return '';
		}

		$svg_markup = '';
		try {
			$qr_output_options = array(
				'eccLevel'               => 3,
				'addQuietzone'           => true,
				'quietzoneSize'          => 4,
				'scale'                  => 8,
				'outputBase64'           => false,
				'svgAddXmlHeader'        => false,
				'svgPreserveAspectRatio' => 'xMidYMid meet',
			);
			if ( class_exists( '\\chillerlan\\QRCode\\Output\\QRMarkupSVG' ) ) {
				$qr_output_options['outputInterface'] = \chillerlan\QRCode\Output\QRMarkupSVG::class;
			} else {
				$qr_output_options['outputType'] = 'svg';
			}
			$options    = new \chillerlan\QRCode\QROptions( $qr_output_options );
			$svg_markup = ( new \chillerlan\QRCode\QRCode( $options ) )->render( $url );
		} catch ( Throwable $e ) {
			$svg_markup = '';
		}

		if ( ! empty( $svg_markup ) && is_string( $svg_markup ) ) {
			$trimmed = ltrim( $svg_markup );
			if ( 0 === strpos( $trimmed, 'data:image/' ) ) {
				$output = '<div class="qr-code-placeholder qr-code-live" aria-label="Scan to open profile"><img src="' . esc_attr( $trimmed ) . '" alt="Scan to open profile" /></div>';
				set_transient( $cache_key, $output, DAY_IN_SECONDS );
				return $output;
			}
			if ( false !== strpos( $svg_markup, '<svg' ) ) {
				$svg_data_uri = 'data:image/svg+xml;base64,' . base64_encode( $svg_markup );
				$output       = '<div class="qr-code-placeholder qr-code-live" aria-label="Scan to open profile"><img src="' . esc_attr( $svg_data_uri ) . '" alt="Scan to open profile" /></div>';
				set_transient( $cache_key, $output, DAY_IN_SECONDS );
				return $output;
			}
		}

		$allowed = array(
			'svg'  => array(
				'xmlns'               => true,
				'width'               => true,
				'height'              => true,
				'viewBox'             => true,
				'preserveAspectRatio' => true,
				'class'               => true,
				'role'                => true,
				'aria-hidden'         => true,
				'focusable'           => true,
				'fill'                => true,
			),
			'path' => array(
				'd'     => true,
				'fill'  => true,
				'class' => true,
			),
			'rect' => array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
				'ry'     => true,
				'fill'   => true,
				'class'  => true,
			),
			'g' => array(
				'fill'  => true,
				'class' => true,
			),
			'defs' => array(
				'class' => true,
			),
			'title' => array(),
		);

		if ( ! empty( $svg_markup ) ) {
			$svg_markup = wp_kses( $svg_markup, $allowed );
			if ( ! empty( $svg_markup ) ) {
				$output = '<div class="qr-code-placeholder qr-code-live" aria-label="Scan to open profile">' . $svg_markup . '</div>';
				set_transient( $cache_key, $output, DAY_IN_SECONDS );
				return $output;
			}
		}

		$png_markup = '';
		try {
			$png_options = new \chillerlan\QRCode\QROptions(
				array(
					'eccLevel'     => 3,
					'outputType'   => 'png',
					'outputBase64' => true,
					'scale'        => 8,
				)
			);
			$png_markup = ( new \chillerlan\QRCode\QRCode( $png_options ) )->render( $url );
		} catch ( Throwable $e ) {
			$png_markup = '';
		}

		$trimmed_png = ltrim( (string) $png_markup );
		if ( 0 === strpos( $trimmed_png, 'data:image/' ) ) {
			$output = '<div class="qr-code-placeholder qr-code-live" aria-label="Scan to open profile"><img src="' . esc_attr( $trimmed_png ) . '" alt="Scan to open profile" /></div>';
			set_transient( $cache_key, $output, DAY_IN_SECONDS );
			return $output;
		}

		return '';
	}
}
