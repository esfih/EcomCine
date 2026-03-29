<?php
/**
 * Self-hosted plugin updater integration for EcomCine.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Plugin_Updater {
	const SLUG = 'ecomcine';
	const CACHE_KEY = 'ecomcine_update_server_info';
	const CACHE_TTL = 900;

	/**
	 * Register WordPress update hooks.
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'purge_cache_after_upgrade' ), 10, 2 );
	}

	/**
	 * Default update endpoint, filterable for staging/local testing.
	 */
	public static function get_server_endpoint() {
		$url = 'https://updates.ecomcine.com/update-server.php';
		$url = apply_filters( 'ecomcine_update_server_url', $url );
		return esc_url_raw( (string) $url );
	}

	/**
	 * Add update metadata to the standard plugin update transient.
	 *
	 * @param object $transient
	 * @return object
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = self::get_remote_info();
		if ( ! is_array( $remote ) || empty( $remote['version'] ) || empty( $remote['download_url'] ) ) {
			return $transient;
		}

		$plugin_basename = plugin_basename( ECOMCINE_FILE );
		$current_version = (string) ( $transient->checked[ $plugin_basename ] ?? ECOMCINE_VERSION );
		$remote_version  = (string) $remote['version'];

		if ( version_compare( $remote_version, $current_version, '<=' ) ) {
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$transient->no_update[ $plugin_basename ] = (object) self::build_update_payload( $remote );
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		$transient->response[ $plugin_basename ] = (object) self::build_update_payload( $remote );
		return $transient;
	}

	/**
	 * Provide plugin details for the "View details" modal.
	 *
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return false|object|array
	 */
	public static function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$remote = self::get_remote_info();
		if ( ! is_array( $remote ) || empty( $remote['version'] ) ) {
			return $result;
		}

		$sections = array(
			'description' => wp_kses_post( (string) ( $remote['sections']['description'] ?? 'EcomCine plugin updates.' ) ),
			'changelog'   => wp_kses_post( (string) ( $remote['sections']['changelog'] ?? '' ) ),
		);

		return (object) array(
			'name'          => (string) ( $remote['name'] ?? 'EcomCine' ),
			'slug'          => self::SLUG,
			'version'       => (string) $remote['version'],
			'author'        => (string) ( $remote['author'] ?? 'EcomCine' ),
			'homepage'      => (string) ( $remote['homepage'] ?? 'https://ecomcine.com' ),
			'requires'      => (string) ( $remote['requires'] ?? '' ),
			'tested'        => (string) ( $remote['tested'] ?? '' ),
			'requires_php'  => (string) ( $remote['requires_php'] ?? '' ),
			'download_link' => (string) ( $remote['download_url'] ?? '' ),
			'last_updated'  => (string) ( $remote['last_updated'] ?? '' ),
			'sections'      => $sections,
			'banners'       => is_array( $remote['banners'] ?? null ) ? $remote['banners'] : array(),
			'icons'         => is_array( $remote['icons'] ?? null ) ? $remote['icons'] : array(),
		);
	}

	/**
	 * Clear cached update metadata after plugin upgrade operations.
	 *
	 * @param mixed $return
	 * @param array $hook_extra
	 * @return mixed
	 */
	public static function purge_cache_after_upgrade( $return, $hook_extra ) {
		if ( ! is_array( $hook_extra ) ) {
			return $return;
		}

		if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return $return;
		}

		$plugins = array();
		if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			$plugins = $hook_extra['plugins'];
		} elseif ( ! empty( $hook_extra['plugin'] ) ) {
			$plugins = array( (string) $hook_extra['plugin'] );
		}

		if ( in_array( plugin_basename( ECOMCINE_FILE ), $plugins, true ) ) {
			delete_site_transient( self::CACHE_KEY );
		}

		return $return;
	}

	/**
	 * Fetch and cache latest update payload from the update server.
	 *
	 * @return array<string,mixed>|null
	 */
	private static function get_remote_info() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && ! empty( $cached['version'] ) && ! empty( $cached['download_url'] ) ) {
			return $cached;
		}

		$endpoint = self::get_server_endpoint();
		if ( '' === $endpoint ) {
			return null;
		}

		$url = add_query_arg(
			array(
				'action' => 'info',
				'slug'   => self::SLUG,
			),
			$endpoint
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['version'] ) || empty( $body['download_url'] ) ) {
			return null;
		}

		$body['version'] = (string) $body['version'];
		$body['download_url'] = esc_url_raw( (string) $body['download_url'] );
		set_site_transient( self::CACHE_KEY, $body, self::CACHE_TTL );

		return $body;
	}

	/**
	 * Build the payload object expected by WordPress update UI.
	 *
	 * @param array<string,mixed> $remote
	 * @return array<string,mixed>
	 */
	private static function build_update_payload( array $remote ): array {
		return array(
			'id'            => self::SLUG,
			'slug'          => self::SLUG,
			'plugin'        => plugin_basename( ECOMCINE_FILE ),
			'new_version'   => (string) ( $remote['version'] ?? ECOMCINE_VERSION ),
			'url'           => (string) ( $remote['homepage'] ?? 'https://ecomcine.com' ),
			'package'       => (string) ( $remote['download_url'] ?? '' ),
			'requires'      => (string) ( $remote['requires'] ?? '' ),
			'tested'        => (string) ( $remote['tested'] ?? '' ),
			'requires_php'  => (string) ( $remote['requires_php'] ?? '' ),
		);
	}
}
