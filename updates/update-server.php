<?php
/**
 * EcomCine self-hosted plugin update server.
 *
 * Deploy this file as https://updates.ecomcine.com/update-server.php.
 */

declare( strict_types=1 );

$config_file = __DIR__ . '/config.php';

if ( ! is_file( $config_file ) ) {
	http_response_code( 503 );
	header( 'Content-Type: application/json' );
	echo json_encode( array( 'error' => 'Update server not configured.' ) );
	exit;
}

$cfg = require $config_file;

$env_token = getenv( 'ECOMCINE_GITHUB_TOKEN' );
if ( ! is_string( $env_token ) || '' === trim( $env_token ) ) {
	$env_token = getenv( 'GITHUB_TOKEN' );
}
$cfg_token = (string) ( $cfg['github_token'] ?? '' );
$resolved_token = ( is_string( $env_token ) && '' !== trim( $env_token ) ) ? trim( $env_token ) : $cfg_token;

define( 'UPD_GITHUB_TOKEN', $resolved_token );
define( 'UPD_GITHUB_OWNER', (string) ( $cfg['github_owner'] ?? '' ) );
define( 'UPD_GITHUB_REPO', (string) ( $cfg['github_repo'] ?? '' ) );
define( 'UPD_PLUGIN_SLUG', (string) ( $cfg['plugin_slug'] ?? 'ecomcine' ) );
define( 'UPD_DOWNLOAD_SECRET', (string) ( $cfg['download_secret'] ?? '' ) );
define( 'UPD_CACHE_TTL', (int) ( $cfg['cache_ttl'] ?? 900 ) );
define( 'UPD_RELEASE_TAG_PREFIX', (string) ( $cfg['release_tag_prefix'] ?? 'v' ) );
define( 'UPD_CACHE_FILE', __DIR__ . '/cache/latest-release.json' );

header( 'X-Robots-Tag: noindex, nofollow' );

$action = (string) ( $_GET['action'] ?? 'info' );

switch ( $action ) {
	case 'info':
		handle_info();
		break;
	case 'diag':
		handle_diag();
		break;
	case 'download':
		handle_download();
		break;
	default:
		http_response_code( 400 );
		header( 'Content-Type: application/json' );
		echo json_encode( array( 'error' => 'Unknown action.' ) );
}

exit;

function handle_info(): void {
	header( 'Content-Type: application/json' );
	header( 'Cache-Control: public, max-age=300' );

	$slug = sanitize_slug( (string) ( $_GET['slug'] ?? UPD_PLUGIN_SLUG ) );
	if ( UPD_PLUGIN_SLUG !== $slug ) {
		http_response_code( 404 );
		echo json_encode( array( 'error' => 'Unknown plugin slug.' ) );
		return;
	}

	$error = '';
	$release = get_latest_release( $error );
	if ( ! $release ) {
		http_response_code( 503 );
		$payload = array( 'error' => 'Could not fetch release data.' );
		if ( should_debug() && '' !== $error ) {
			$payload['detail'] = $error;
		}
		echo json_encode( $payload );
		return;
	}

	$tag = (string) ( $release['tag_name'] ?? '' );
	$version = normalize_release_version( $tag );
	if ( '' === $version ) {
		http_response_code( 503 );
		echo json_encode( array( 'error' => 'Latest release tag is invalid.' ) );
		return;
	}

	$download_url = get_release_asset_url( $tag, $slug );
	if ( ! $download_url ) {
		http_response_code( 503 );
		echo json_encode( array( 'error' => 'Could not resolve a release package URL.' ) );
		return;
	}

	$body_html = strip_tags( (string) ( $release['body'] ?? '' ) );

	echo json_encode(
		array(
			'name'          => (string) ( get_cfg( 'name', 'EcomCine' ) ),
			'slug'          => $slug,
			'version'       => $version,
			'author'        => (string) ( get_cfg( 'author', 'EcomCine' ) ),
			'homepage'      => (string) ( get_cfg( 'homepage', 'https://ecomcine.com' ) ),
			'requires'      => (string) ( get_cfg( 'requires', '6.5' ) ),
			'requires_php'  => (string) ( get_cfg( 'requires_php', '8.1' ) ),
			'tested'        => (string) ( get_cfg( 'tested', '6.7' ) ),
			'changelog_url' => (string) ( $release['html_url'] ?? '' ),
			'download_url'  => $download_url,
			'last_updated'  => (string) ( $release['published_at'] ?? '' ),
			'sections'      => array(
				'description' => (string) ( get_cfg( 'description', 'Unified EcomCine plugin updates.' ) ),
				'changelog'   => nl2br( htmlspecialchars( $body_html ) ),
			),
		),
		JSON_UNESCAPED_SLASHES
	);
}

function handle_diag(): void {
	header( 'Content-Type: application/json' );
	header( 'Cache-Control: no-store' );

	$url = sprintf(
		'https://api.github.com/repos/%s/%s/releases/latest',
		rawurlencode( UPD_GITHUB_OWNER ),
		rawurlencode( UPD_GITHUB_REPO )
	);
	$probe = github_get_with_meta( $url );
	$decoded = null;
	if ( $probe['ok'] && is_string( $probe['body'] ) ) {
		$decoded = json_decode( $probe['body'], true );
	}

	echo json_encode(
		array(
			'service'          => 'ecomcine-update-server',
			'plugin_slug'      => UPD_PLUGIN_SLUG,
			'github_owner'     => UPD_GITHUB_OWNER,
			'github_repo'      => UPD_GITHUB_REPO,
			'token_configured' => '' !== trim( UPD_GITHUB_TOKEN ),
			'curl_available'   => function_exists( 'curl_init' ),
			'github_probe'     => array(
				'ok'           => $probe['ok'],
				'http_code'    => $probe['code'],
				'curl_error'   => $probe['error'],
				'has_tag_name' => is_array( $decoded ) && ! empty( $decoded['tag_name'] ),
			),
		),
		JSON_UNESCAPED_SLASHES
	);
}

function handle_download(): void {
	$slug = sanitize_slug( (string) ( $_GET['slug'] ?? '' ) );
	$version = (string) ( $_GET['version'] ?? '' );

	if ( UPD_PLUGIN_SLUG !== $slug ) {
		http_response_code( 404 );
		header( 'Content-Type: application/json' );
		echo json_encode( array( 'error' => 'Unknown plugin slug.' ) );
		return;
	}

	if ( ! preg_match( '/^\d+\.\d+(\.\d+)?$/', $version ) ) {
		http_response_code( 400 );
		header( 'Content-Type: application/json' );
		echo json_encode( array( 'error' => 'Invalid version.' ) );
		return;
	}

	$tag = UPD_RELEASE_TAG_PREFIX . $version;
	$asset_url = get_release_asset_url( $tag, $slug );
	if ( ! $asset_url ) {
		http_response_code( 404 );
		header( 'Content-Type: application/json' );
		echo json_encode( array( 'error' => 'Release asset not found for version ' . $version . '.' ) );
		return;
	}

	$filename = $slug . '-v' . $version . '.zip';
	$http_code = 0;
	$content_type = '';
	$curl_error = '';
	$binary = http_get_binary( $asset_url, $http_code, $content_type, $curl_error );

	if ( null === $binary ) {
		http_response_code( 502 );
		header( 'Content-Type: application/json' );
		echo json_encode(
			array(
				'error' => 'Failed to fetch package binary.',
				'detail' => sprintf( 'HTTP %d%s', (int) $http_code, '' !== $curl_error ? '; cURL: ' . $curl_error : '' ),
			)
		);
		return;
	}

	if ( strlen( $binary ) < 4 || 0 !== strpos( $binary, "PK\x03\x04" ) ) {
		http_response_code( 502 );
		header( 'Content-Type: application/json' );
		echo json_encode(
			array(
				'error' => 'Resolved package is not a ZIP archive.',
				'content_type' => $content_type,
			)
		);
		return;
	}

	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . strlen( $binary ) );
	header( 'Cache-Control: no-store' );
	header( 'X-Content-Type-Options: nosniff' );
	echo $binary;
}

function get_latest_release( ?string &$error = null ): ?array {
	if ( is_file( UPD_CACHE_FILE ) ) {
		$cache_age = time() - (int) filemtime( UPD_CACHE_FILE );
		if ( $cache_age >= 0 && $cache_age < UPD_CACHE_TTL ) {
		$cached = json_decode( (string) file_get_contents( UPD_CACHE_FILE ), true );
		if ( is_array( $cached ) && ! empty( $cached['tag_name'] ) ) {
			return $cached;
		}
		}
	}

	$url = sprintf(
		'https://api.github.com/repos/%s/%s/releases/latest',
		rawurlencode( UPD_GITHUB_OWNER ),
		rawurlencode( UPD_GITHUB_REPO )
	);
	$probe = github_get_with_meta( $url );
	if ( ! $probe['ok'] || ! is_string( $probe['body'] ) ) {
		$error = sprintf(
			'GitHub releases/latest failed (HTTP %d%s)',
			(int) $probe['code'],
			$probe['error'] ? '; cURL: ' . $probe['error'] : ''
		);
		return null;
	}

	$release = json_decode( $probe['body'], true );
	if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
		$error = 'GitHub response did not contain a valid tag_name.';
		return null;
	}

	$cache_dir = dirname( UPD_CACHE_FILE );
	if ( ! is_dir( $cache_dir ) ) {
		@mkdir( $cache_dir, 0755, true );
	}
	file_put_contents( UPD_CACHE_FILE, json_encode( $release ), LOCK_EX );

	return $release;
}

function get_release_asset_url( string $tag, string $slug ): ?string {
	$url = sprintf(
		'https://api.github.com/repos/%s/%s/releases/tags/%s',
		rawurlencode( UPD_GITHUB_OWNER ),
		rawurlencode( UPD_GITHUB_REPO ),
		rawurlencode( $tag )
	);
	$body = github_get( $url );
	if ( ! $body ) {
		return null;
	}

	$release = json_decode( $body, true );
	if ( ! is_array( $release ) ) {
		return null;
	}

	$preferred = null;
	$fallback = null;
	foreach ( $release['assets'] ?? array() as $asset ) {
		$name = strtolower( (string) ( $asset['name'] ?? '' ) );
		if ( ! str_ends_with( $name, '.zip' ) ) {
			continue;
		}
		$asset_download_url = (string) ( $asset['browser_download_url'] ?? '' );
		if ( '' === $asset_download_url ) {
			$asset_download_url = (string) ( $asset['url'] ?? '' );
		}
		if ( '' === $asset_download_url ) {
			continue;
		}

		if ( false !== strpos( $name, strtolower( $slug ) ) ) {
			$preferred = $asset_download_url;
			break;
		}
		if ( null === $fallback ) {
			$fallback = $asset_download_url;
		}
	}

	if ( null !== $preferred ) {
		return $preferred;
	}

	if ( null !== $fallback ) {
		return $fallback;
	}

	return sprintf(
		'https://github.com/%s/%s/archive/refs/tags/%s.zip',
		rawurlencode( UPD_GITHUB_OWNER ),
		rawurlencode( UPD_GITHUB_REPO ),
		rawurlencode( $tag )
	);
}

function github_get( string $url ): ?string {
	$probe = github_get_with_meta( $url );
	return ( $probe['ok'] && is_string( $probe['body'] ) ) ? (string) $probe['body'] : null;
}

function github_get_with_meta( string $url ): array {
	$ch = curl_init();
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 15,
			CURLOPT_HTTPHEADER => github_api_headers(),
		)
	);
	$body = curl_exec( $ch );
	$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$err = curl_error( $ch );
	curl_close( $ch );

	return array(
		'ok'    => ( false !== $body && 200 === (int) $code ),
		'body'  => false !== $body ? (string) $body : null,
		'code'  => (int) $code,
		'error' => '' !== $err ? $err : null,
	);
}

function http_get_binary( string $url, int &$http_code = 0, string &$content_type = '', string &$curl_error = '' ): ?string {
	$ch = curl_init();
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_TIMEOUT => 120,
			CURLOPT_HTTPHEADER => github_download_headers(),
		)
	);
	$body = curl_exec( $ch );
	$http_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$content_type = (string) curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );
	$curl_error = (string) curl_error( $ch );
	curl_close( $ch );

	if ( false === $body || 200 !== $http_code ) {
		return null;
	}

	return (string) $body;
}

function github_api_headers(): array {
	$headers = array(
		'Accept: application/vnd.github+json',
		'X-GitHub-Api-Version: 2022-11-28',
		'User-Agent: EcomCine-UpdateServer/1.0',
	);

	if ( '' !== trim( UPD_GITHUB_TOKEN ) ) {
		$headers[] = 'Authorization: Bearer ' . UPD_GITHUB_TOKEN;
	}

	return $headers;
}

function github_download_headers(): array {
	$headers = array(
		'Accept: application/octet-stream',
		'User-Agent: EcomCine-UpdateServer/1.0',
	);

	if ( '' !== trim( UPD_GITHUB_TOKEN ) ) {
		$headers[] = 'Authorization: Bearer ' . UPD_GITHUB_TOKEN;
	}

	return $headers;
}

function sign_download( string $slug, string $version, int $expires ): string {
	return hash_hmac( 'sha256', $slug . '|' . $version . '|' . $expires, UPD_DOWNLOAD_SECRET );
}

function normalize_release_version( string $tag ): string {
	$tag = trim( $tag );
	$prefix = trim( UPD_RELEASE_TAG_PREFIX );
	if ( '' !== $prefix && 0 === strpos( $tag, $prefix ) ) {
		$tag = substr( $tag, strlen( $prefix ) );
	}

	if ( preg_match( '/^\d+\.\d+(\.\d+)?$/', $tag ) ) {
		return $tag;
	}

	return '';
}

function sanitize_slug( string $value ): string {
	$result = preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) );
	return is_string( $result ) ? $result : '';
}

function current_url_base(): string {
	$scheme = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] ) ? 'https' : 'http';
	$host = (string) ( $_SERVER['HTTP_HOST'] ?? 'updates.ecomcine.com' );
	$path = strtok( (string) ( $_SERVER['REQUEST_URI'] ?? '/update-server.php' ), '?' );
	return $scheme . '://' . $host . $path;
}

function get_cfg( string $key, string $fallback ): string {
	global $cfg;
	if ( isset( $cfg[ $key ] ) && is_string( $cfg[ $key ] ) && '' !== trim( $cfg[ $key ] ) ) {
		return (string) $cfg[ $key ];
	}
	return $fallback;
}

function should_debug(): bool {
	return isset( $_GET['debug'] ) && '1' === (string) $_GET['debug'];
}
