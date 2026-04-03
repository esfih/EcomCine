<?php
/**
 * Theme-orchestration parity check.
 *
 * Validates that both compat and default-WP implementations satisfy the
 * same contracts before deploying.
 *
 * Usage (via WP-CLI):
 *   wp eval 'require ABSPATH . "wp-content/themes/ecomcine-base/includes/parity/class-parity-check.php"; THO_Parity_Check::run();' --allow-root
 *
 * @package EcomCine_Base_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class THO_Parity_Check {

	private int $pass = 0;
	private int $fail = 0;
	private array $results = array();

	public static function run(): void {
		$instance = new self();

		echo "\n=== THO Parity Check ===\n";
		echo "Testing COMPAT adapter...\n";
		$instance->run_suite( 'compat' );

		echo "\nTesting DEFAULT-WP adapter...\n";
		$instance->run_suite( 'default-wp' );

		echo "\n--- Results ---\n";
		foreach ( $instance->results as $result ) {
			$icon = 'PASS' === $result['status'] ? '✓' : '✗';
			printf( "[%s] [%s] %s\n", $result['adapter'], $icon, $result['check'] );
			if ( isset( $result['error'] ) ) {
				printf( "      ERROR: %s\n", $result['error'] );
			}
		}

		$total = $instance->pass + $instance->fail;
		printf( "\n%d/%d checks passed.\n", $instance->pass, $total );

		if ( $instance->fail > 0 ) {
			exit( 1 );
		}
	}

	private function run_suite( string $mode ): void {
		THO_Adapter_Registry::reset();
		if ( 'default-wp' === $mode ) {
			define( 'THO_ADAPTER', 'default-wp' );
		}

		$this->check( $mode, 'template_router_store_returns_string', function() {
			$result = THO_Adapter_Registry::get_template_router()->get_store_page_template( array() );
			assert( is_string( $result ), 'get_store_page_template must return string' );
		} );

		$this->check( $mode, 'template_router_listing_returns_string', function() {
			$result = THO_Adapter_Registry::get_template_router()->get_listing_page_template( array() );
			assert( is_string( $result ), 'get_listing_page_template must return string' );
		} );

		$this->check( $mode, 'asset_policy_returns_array_with_required_keys', function() {
			$result = THO_Adapter_Registry::get_asset_policy_provider()->get_asset_policy( array() );
			assert( is_array( $result ), 'get_asset_policy must return array' );
			assert( array_key_exists( 'dequeue_scripts', $result ), 'must have dequeue_scripts key' );
			assert( array_key_exists( 'dequeue_styles', $result ), 'must have dequeue_styles key' );
		} );

		$this->check( $mode, 'profile_meta_invalid_vendor_returns_empty_structure', function() {
			$result = THO_Adapter_Registry::get_profile_meta_provider()->get_vendor_profile_meta( 0 );
			assert( is_array( $result ), 'must return array' );
			assert( array_key_exists( 'biography', $result ), 'must have biography key' );
			assert( array_key_exists( 'social', $result ), 'must have social key' );
		} );

		$this->check( $mode, 'completeness_score_returns_required_keys', function() {
			$result = THO_Adapter_Registry::get_profile_meta_provider()->compute_completeness_score( 0 );
			assert( is_array( $result ), 'must return array' );
			assert( array_key_exists( 'score', $result ), 'must have score key' );
			assert( array_key_exists( 'percent', $result ), 'must have percent key' );
			assert( array_key_exists( 'detail', $result ), 'must have detail key' );
		} );

		$this->check( $mode, 'identity_projector_invalid_vendor_returns_structure', function() {
			$result = THO_Adapter_Registry::get_vendor_identity_projector()->project_vendor_identity( 0 );
			assert( is_array( $result ), 'must return array' );
			assert( array_key_exists( 'vendor_id', $result ), 'must have vendor_id key' );
			assert( array_key_exists( 'name', $result ), 'must have name key' );
			assert( array_key_exists( 'store_url', $result ), 'must have store_url key' );
		} );

		$this->check( $mode, 'identity_projector_render_block_returns_string', function() {
			$result = THO_Adapter_Registry::get_vendor_identity_projector()->render_vendor_identity_block( 0, 'card' );
			assert( is_string( $result ), 'render_vendor_identity_block must return string' );
		} );

		$this->check( $mode, 'metrics_social_returns_required_keys', function() {
			$result = THO_Adapter_Registry::get_metrics_provider()->compute_social_metrics( 0 );
			assert( is_array( $result ), 'must return array' );
			assert( array_key_exists( 'links', $result ), 'must have links key' );
			assert( array_key_exists( 'active_count', $result ), 'must have active_count key' );
		} );

		$this->check( $mode, 'metrics_completeness_returns_required_keys', function() {
			$result = THO_Adapter_Registry::get_metrics_provider()->compute_completeness( 0 );
			assert( is_array( $result ), 'must return array' );
			assert( array_key_exists( 'score', $result ), 'must have score key' );
			assert( array_key_exists( 'percent', $result ), 'must have percent key' );
		} );

		$this->check( $mode, 'metrics_map_embed_invalid_vendor_returns_string', function() {
			$result = THO_Adapter_Registry::get_metrics_provider()->render_map_embed( 0 );
			assert( is_string( $result ), 'render_map_embed must return string' );
		} );

		THO_Adapter_Registry::reset();
	}

	private function check( string $adapter, string $name, callable $fn ): void {
		try {
			$fn();
			$this->pass++;
			$this->results[] = array( 'adapter' => $adapter, 'check' => $name, 'status' => 'PASS' );
		} catch ( \Throwable $e ) {
			$this->fail++;
			$this->results[] = array( 'adapter' => $adapter, 'check' => $name, 'status' => 'FAIL', 'error' => $e->getMessage() );
		}
	}
}