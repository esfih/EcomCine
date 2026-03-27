<?php
/**
 * Seed local FluentCart-compatible license key table for control-plane API tests.
 *
 * This creates a local compatibility table that matches the control-plane native
 * resolver expectations (table name + license_key/product_id/variation_id columns).
 *
 * Run with:
 *   ./scripts/wp.sh php scripts/licensing/seed-fluentcart-license-keys.php
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
	WP_CLI::error( 'WordPress DB object is unavailable.' );
}

$table = $wpdb->prefix . 'fluentcart_licenses';

$create_sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`license_key` VARCHAR(100) NOT NULL,
	`product_id` BIGINT UNSIGNED NOT NULL,
	`variation_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
	`activation_limit` INT NOT NULL DEFAULT 1,
	`status` VARCHAR(30) NOT NULL DEFAULT 'active',
	`created_at` DATETIME NULL,
	`updated_at` DATETIME NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uniq_license_key` (`license_key`),
	KEY `idx_product_variation` (`product_id`,`variation_id`)
) {$wpdb->get_charset_collate()};";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( $create_sql );

$keys = array(
	array(
		'license_key' => 'ECOMFREEMIUM-1111-1111-1111',
		'product_id' => 2566,
		'variation_id' => 1,
		'activation_limit' => 1,
	),
	array(
		'license_key' => 'ECOMSOLO-2222-2222-2222',
		'product_id' => 2569,
		'variation_id' => 2,
		'activation_limit' => 3,
	),
	array(
		'license_key' => 'ECOMMAESTRO-3333-3333-3333',
		'product_id' => 2571,
		'variation_id' => 3,
		'activation_limit' => 10,
	),
	array(
		'license_key' => 'ECOMAGENCY-4444-4444-4444',
		'product_id' => 2573,
		'variation_id' => 4,
		'activation_limit' => 100,
	),
);

$now = current_time( 'mysql', true );
$seeded = 0;

foreach ( $keys as $row ) {
	$license_key = strtoupper( sanitize_text_field( (string) $row['license_key'] ) );
	$product_id = (int) $row['product_id'];
	$variation_id = (int) $row['variation_id'];
	$activation_limit = (int) $row['activation_limit'];

	$existing_id = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT id FROM {$table} WHERE license_key = %s LIMIT 1", $license_key )
	);

	if ( $existing_id > 0 ) {
		$wpdb->update(
			$table,
			array(
				'product_id' => $product_id,
				'variation_id' => $variation_id,
				'activation_limit' => $activation_limit,
				'status' => 'active',
				'updated_at' => $now,
			),
			array( 'id' => $existing_id ),
			array( '%d', '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);
	} else {
		$wpdb->insert(
			$table,
			array(
				'license_key' => $license_key,
				'product_id' => $product_id,
				'variation_id' => $variation_id,
				'activation_limit' => $activation_limit,
				'status' => 'active',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	++$seeded;
}

WP_CLI::success( sprintf( 'Seeded %d compatibility license keys in %s.', $seeded, $table ) );
foreach ( $keys as $row ) {
	WP_CLI::log(
		sprintf(
			'%s => product_id=%d variation_id=%d activation_limit=%d',
			$row['license_key'],
			(int) $row['product_id'],
			(int) $row['variation_id'],
			(int) $row['activation_limit']
		)
	);
}
