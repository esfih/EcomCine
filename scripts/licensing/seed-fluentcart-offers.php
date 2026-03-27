<?php
/**
 * Seed FluentCart + FluentCart Pro canonical EcomCine offers for local testing.
 *
 * Run with:
 *   ./scripts/wp.sh wp eval-file scripts/licensing/seed-fluentcart-offers.php
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
	WP_CLI::error( 'WordPress DB object is unavailable.' );
}

$product_table = $wpdb->prefix . 'fct_product_details';
$variation_table = $wpdb->prefix . 'fct_product_variations';
$meta_table = $wpdb->prefix . 'fct_product_meta';

$required_tables = array( $product_table, $variation_table, $meta_table );
foreach ( $required_tables as $table ) {
	$exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $exists !== $table ) {
		WP_CLI::error( sprintf( 'Required FluentCart table missing: %s', $table ) );
	}
}

$offers = array(
	array(
		'plan' => 'freemium',
		'label' => 'EcomCine Freemium',
		'product_id' => 2566,
		'variation_id' => 1,
		'price' => 0,
		'activation_limit' => 1,
		'allowances' => array(
			'ai_queries_hour' => 1,
			'ai_queries_day' => 1,
			'ai_queries_month' => 30,
			'remixes_max' => 1,
			'promotions_max' => 1,
			'queue_max' => 10,
			'ai_mode' => 'mutualized_ai',
		),
	),
	array(
		'plan' => 'solo',
		'label' => 'EcomCine Solo',
		'product_id' => 2569,
		'variation_id' => 2,
		'price' => 49,
		'activation_limit' => 3,
		'allowances' => array(
			'ai_queries_hour' => 12,
			'ai_queries_day' => 120,
			'ai_queries_month' => 2500,
			'remixes_max' => 10,
			'promotions_max' => 10,
			'queue_max' => 50,
			'ai_mode' => 'mutualized_ai',
		),
	),
	array(
		'plan' => 'maestro',
		'label' => 'EcomCine Maestro',
		'product_id' => 2571,
		'variation_id' => 3,
		'price' => 149,
		'activation_limit' => 10,
		'allowances' => array(
			'ai_queries_hour' => 30,
			'ai_queries_day' => 360,
			'ai_queries_month' => 7200,
			'remixes_max' => 150,
			'promotions_max' => 75,
			'queue_max' => 100,
			'ai_mode' => 'mutualized_ai',
		),
	),
	array(
		'plan' => 'agency',
		'label' => 'EcomCine Agency',
		'product_id' => 2573,
		'variation_id' => 4,
		'price' => 399,
		'activation_limit' => 100,
		'allowances' => array(
			'ai_queries_hour' => 100,
			'ai_queries_day' => 1000,
			'ai_queries_month' => 30000,
			'remixes_max' => 10000,
			'promotions_max' => 10000,
			'queue_max' => 1000,
			'ai_mode' => 'confidential_ai',
		),
	),
);

$now = current_time( 'mysql', true );
$seeded = array();

foreach ( $offers as $offer ) {
	$plan = sanitize_key( (string) $offer['plan'] );
	$product_id = (int) $offer['product_id'];
	$variation_id = (int) $offer['variation_id'];
	$price = (float) $offer['price'];
	$activation_limit = (int) $offer['activation_limit'];
	$label = sanitize_text_field( (string) $offer['label'] );

	$post_id = 0;
	$existing_post = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT post_id FROM {$product_table} WHERE id = %d LIMIT 1", $product_id )
	);
	if ( $existing_post > 0 ) {
		$post_id = $existing_post;
	}

	if ( $post_id <= 0 ) {
		$post_id = wp_insert_post(
			array(
				'post_type' => 'fluent-products',
				'post_status' => 'publish',
				'post_title' => $label,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			WP_CLI::warning( sprintf( 'Skipping %s: failed to create post (%s)', $plan, $post_id->get_error_message() ) );
			continue;
		}
		$post_id = (int) $post_id;
	}

	$exists_product = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$product_table} WHERE id = %d", $product_id )
	);

	if ( $exists_product > 0 ) {
		$wpdb->update(
			$product_table,
			array(
				'post_id' => $post_id,
				'fulfillment_type' => 'digital',
				'min_price' => $price,
				'max_price' => $price,
				'default_variation_id' => $variation_id,
				'variation_type' => 'simple',
				'manage_stock' => 0,
				'stock_availability' => 'in-stock',
				'updated_at' => $now,
			),
			array( 'id' => $product_id ),
			array( '%d', '%s', '%f', '%f', '%d', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
	} else {
		$wpdb->insert(
			$product_table,
			array(
				'id' => $product_id,
				'post_id' => $post_id,
				'fulfillment_type' => 'digital',
				'min_price' => $price,
				'max_price' => $price,
				'default_variation_id' => $variation_id,
				'manage_stock' => 0,
				'stock_availability' => 'in-stock',
				'variation_type' => 'simple',
				'manage_downloadable' => 0,
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%d', '%s', '%f', '%f', '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	$exists_variation = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$variation_table} WHERE id = %d", $variation_id )
	);
	$variation_identifier = 'plan_' . $plan;
	$variation_title = $label . ' Plan';

	if ( $exists_variation > 0 ) {
		$wpdb->update(
			$variation_table,
			array(
				'post_id' => $post_id,
				'variation_title' => $variation_title,
				'variation_identifier' => $variation_identifier,
				'fulfillment_type' => 'digital',
				'item_status' => 'active',
				'stock_status' => 'in-stock',
				'item_price' => $price,
				'item_cost' => $price,
				'updated_at' => $now,
			),
			array( 'id' => $variation_id ),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s' ),
			array( '%d' )
		);
	} else {
		$wpdb->insert(
			$variation_table,
			array(
				'id' => $variation_id,
				'post_id' => $post_id,
				'serial_index' => 1,
				'sold_individually' => 0,
				'variation_title' => $variation_title,
				'variation_identifier' => $variation_identifier,
				'manage_stock' => 0,
				'stock_status' => 'in-stock',
				'fulfillment_type' => 'digital',
				'item_status' => 'active',
				'manage_cost' => 'false',
				'item_price' => $price,
				'item_cost' => $price,
				'compare_price' => 0,
				'downloadable' => 'false',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s', '%s' )
		);
	}

	$license_settings = array(
		'variations' => array(
			array(
				'variation_id' => $variation_id,
				'activation_limit' => $activation_limit,
			),
		),
	);

	$allowances_payload = array(
		'defaults' => $offer['allowances'],
		'updated_at' => gmdate( 'c' ),
	);

	$meta_rows = array(
		'license_settings' => wp_json_encode( $license_settings ),
		'wmos_allowances_v1' => wp_json_encode( $allowances_payload ),
	);

	foreach ( $meta_rows as $meta_key => $meta_value ) {
		$meta_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$meta_table} WHERE object_id = %d AND meta_key = %s LIMIT 1", $product_id, $meta_key )
		);
		if ( $meta_id > 0 ) {
			$wpdb->update(
				$meta_table,
				array(
					'meta_value' => $meta_value,
					'updated_at' => $now,
				),
				array( 'id' => $meta_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$meta_table,
				array(
					'object_id' => $product_id,
					'object_type' => 'product',
					'meta_key' => $meta_key,
					'meta_value' => $meta_value,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	$seeded[] = array(
		'plan' => $plan,
		'product_id' => $product_id,
		'variation_id' => $variation_id,
		'post_id' => $post_id,
		'price' => $price,
		'activation_limit' => $activation_limit,
	);
}

if ( empty( $seeded ) ) {
	WP_CLI::warning( 'No FluentCart offers were seeded.' );
	return;
}

WP_CLI::success( sprintf( 'Seeded %d FluentCart offers.', count( $seeded ) ) );
foreach ( $seeded as $row ) {
	WP_CLI::log(
		sprintf(
			'%s => product_id=%d variation_id=%d post_id=%d price=%s activation_limit=%d',
			$row['plan'],
			(int) $row['product_id'],
			(int) $row['variation_id'],
			(int) $row['post_id'],
			(string) $row['price'],
			(int) $row['activation_limit']
		)
	);
}
