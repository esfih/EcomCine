<?php
/**
 * Seed FluentCart customers + orders + generated license keys for local testing.
 *
 * Run with:
 *   ./scripts/wp.sh wp eval-file scripts/licensing/seed-fluentcart-customers-orders.php
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
	WP_CLI::error( 'WordPress DB object is unavailable.' );
}

$customers_table = $wpdb->prefix . 'fct_customers';
$customer_meta_table = $wpdb->prefix . 'fct_customer_meta';
$orders_table = $wpdb->prefix . 'fct_orders';
$order_items_table = $wpdb->prefix . 'fct_order_items';
$order_meta_table = $wpdb->prefix . 'fct_order_meta';
$product_details_table = $wpdb->prefix . 'fct_product_details';
$licenses_table = $wpdb->prefix . 'fluentcart_licenses';

$required_tables = array(
	$customers_table,
	$orders_table,
	$order_items_table,
	$order_meta_table,
	$product_details_table,
);

foreach ( $required_tables as $table ) {
	$exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $exists !== $table ) {
		WP_CLI::error( sprintf( 'Required FluentCart table missing: %s', $table ) );
	}
}

if ( (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $licenses_table ) ) !== $licenses_table ) {
	$create_sql = "CREATE TABLE IF NOT EXISTS `{$licenses_table}` (
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
}

$plan_rows = array(
	array(
		'plan' => 'freemium',
		'email' => 'freemium.tester@ecomcine.local',
		'first_name' => 'Freemium',
		'last_name' => 'Tester',
		'product_id' => 2566,
		'variation_id' => 1,
		'price' => 0,
		'activation_limit' => 1,
	),
	array(
		'plan' => 'solo',
		'email' => 'solo.tester@ecomcine.local',
		'first_name' => 'Solo',
		'last_name' => 'Tester',
		'product_id' => 2569,
		'variation_id' => 2,
		'price' => 49,
		'activation_limit' => 3,
	),
	array(
		'plan' => 'maestro',
		'email' => 'maestro.tester@ecomcine.local',
		'first_name' => 'Maestro',
		'last_name' => 'Tester',
		'product_id' => 2571,
		'variation_id' => 3,
		'price' => 149,
		'activation_limit' => 10,
	),
	array(
		'plan' => 'agency',
		'email' => 'agency.tester@ecomcine.local',
		'first_name' => 'Agency',
		'last_name' => 'Tester',
		'product_id' => 2573,
		'variation_id' => 4,
		'price' => 399,
		'activation_limit' => 100,
	),
);

$now = current_time( 'mysql', true );
$summary = array();

/**
 * @return string
 */
function ecomcine_build_license_key( $plan, $email ) {
	$seed = strtoupper( sanitize_key( (string) $plan ) . '|' . strtolower( trim( (string) $email ) ) );
	$hash = strtoupper( substr( hash( 'sha256', $seed ), 0, 12 ) );
	return sprintf(
		'ECOM%s-%s-%s-%s',
		strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) $plan ), 0, 4 ) ),
		substr( $hash, 0, 4 ),
		substr( $hash, 4, 4 ),
		substr( $hash, 8, 4 )
	);
}

foreach ( $plan_rows as $row ) {
	$plan = sanitize_key( (string) $row['plan'] );
	$email = sanitize_email( (string) $row['email'] );
	$first_name = sanitize_text_field( (string) $row['first_name'] );
	$last_name = sanitize_text_field( (string) $row['last_name'] );
	$product_id = (int) $row['product_id'];
	$variation_id = (int) $row['variation_id'];
	$price = (int) $row['price'];
	$activation_limit = (int) $row['activation_limit'];

	if ( '' === $email || $product_id <= 0 || $variation_id <= 0 ) {
		WP_CLI::warning( sprintf( 'Skipping invalid row for plan: %s', $plan ) );
		continue;
	}

	$post_id = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT post_id FROM {$product_details_table} WHERE id = %d LIMIT 1", $product_id )
	);
	if ( $post_id <= 0 ) {
		WP_CLI::warning( sprintf( 'Skipping %s: product_id %d not found in %s', $plan, $product_id, $product_details_table ) );
		continue;
	}

	$customer_id = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT id FROM {$customers_table} WHERE email = %s LIMIT 1", $email )
	);

	$purchase_value = wp_json_encode(
		array(
			'currency' => 'USD',
			'amount' => $price,
		)
	);

	if ( $customer_id > 0 ) {
		$wpdb->update(
			$customers_table,
			array(
				'first_name' => $first_name,
				'last_name' => $last_name,
				'status' => 'active',
				'purchase_count' => 1,
				'ltv' => $price,
				'first_purchase_date' => $now,
				'last_purchase_date' => $now,
				'aov' => (float) $price,
				'purchase_value' => $purchase_value,
				'updated_at' => $now,
			),
			array( 'id' => $customer_id ),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%f', '%s', '%s' ),
			array( '%d' )
		);
	} else {
		$wpdb->insert(
			$customers_table,
			array(
				'user_id' => null,
				'contact_id' => 0,
				'email' => $email,
				'first_name' => $first_name,
				'last_name' => $last_name,
				'status' => 'active',
				'purchase_value' => $purchase_value,
				'purchase_count' => 1,
				'ltv' => $price,
				'first_purchase_date' => $now,
				'last_purchase_date' => $now,
				'aov' => (float) $price,
				'notes' => '',
				'uuid' => wp_generate_uuid4(),
				'country' => 'US',
				'city' => 'Local',
				'state' => 'CA',
				'postcode' => '90001',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		$customer_id = (int) $wpdb->insert_id;
	}

	if ( $customer_id <= 0 ) {
		WP_CLI::warning( sprintf( 'Skipping %s: failed to upsert customer.', $plan ) );
		continue;
	}

	$invoice_no = sprintf( 'EC-%s-%05d', strtoupper( $plan ), $customer_id );
	$order_id = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT id FROM {$orders_table} WHERE invoice_no = %s LIMIT 1", $invoice_no )
	);

	$order_payload = array(
		'status' => 'paid',
		'fulfillment_type' => 'digital',
		'type' => 'payment',
		'mode' => 'live',
		'shipping_status' => '',
		'customer_id' => $customer_id,
		'payment_method' => 'manual',
		'payment_status' => 'paid',
		'payment_method_title' => 'Manual',
		'currency' => 'USD',
		'subtotal' => $price,
		'discount_tax' => 0,
		'manual_discount_total' => 0,
		'coupon_discount_total' => 0,
		'shipping_tax' => 0,
		'shipping_total' => 0,
		'tax_total' => 0,
		'total_amount' => $price,
		'total_paid' => $price,
		'total_refund' => 0,
		'rate' => 1,
		'tax_behavior' => 0,
		'note' => 'Seeded local test order',
		'ip_address' => '127.0.0.1',
		'completed_at' => $now,
		'uuid' => 'ord_' . wp_generate_password( 20, false, false ),
		'invoice_no' => $invoice_no,
		'updated_at' => $now,
	);

	if ( $order_id > 0 ) {
		$wpdb->update(
			$orders_table,
			$order_payload,
			array( 'id' => $order_id ),
			array(
				'%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%d', '%s', '%s', '%s', '%s', '%s'
			),
			array( '%d' )
		);
	} else {
		$order_payload['created_at'] = $now;
		$wpdb->insert(
			$orders_table,
			$order_payload,
			array(
				'%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s'
			)
		);
		$order_id = (int) $wpdb->insert_id;
	}

	if ( $order_id <= 0 ) {
		WP_CLI::warning( sprintf( 'Skipping %s: failed to upsert order.', $plan ) );
		continue;
	}

	$item_id = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT id FROM {$order_items_table} WHERE order_id = %d AND object_id = %d LIMIT 1", $order_id, $variation_id )
	);

	$item_payload = array(
		'order_id' => $order_id,
		'post_id' => $post_id,
		'fulfillment_type' => 'digital',
		'payment_type' => 'onetime',
		'post_title' => 'EcomCine ' . ucfirst( $plan ),
		'title' => 'EcomCine ' . ucfirst( $plan ) . ' License',
		'object_id' => $variation_id,
		'cart_index' => 1,
		'quantity' => 1,
		'unit_price' => $price,
		'cost' => $price,
		'subtotal' => $price,
		'tax_amount' => 0,
		'shipping_charge' => 0,
		'discount_total' => 0,
		'line_total' => $price,
		'refund_total' => 0,
		'rate' => 1,
		'fulfilled_quantity' => 1,
		'updated_at' => $now,
	);

	if ( $item_id > 0 ) {
		$wpdb->update(
			$order_items_table,
			$item_payload,
			array( 'id' => $item_id ),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);
	} else {
		$item_payload['created_at'] = $now;
		$wpdb->insert(
			$order_items_table,
			$item_payload,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
	}

	$license_key = ecomcine_build_license_key( $plan, $email );
	$license_id = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT id FROM {$licenses_table} WHERE license_key = %s LIMIT 1", $license_key )
	);

	if ( $license_id > 0 ) {
		$wpdb->update(
			$licenses_table,
			array(
				'product_id' => $product_id,
				'variation_id' => $variation_id,
				'activation_limit' => $activation_limit,
				'status' => 'active',
				'updated_at' => $now,
			),
			array( 'id' => $license_id ),
			array( '%d', '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);
	} else {
		$wpdb->insert(
			$licenses_table,
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

	$meta_rows = array(
		array( 'license_key', $license_key ),
		array( 'license_product_id', (string) $product_id ),
		array( 'license_variation_id', (string) $variation_id ),
	);

	foreach ( $meta_rows as $meta_row ) {
		$meta_key = (string) $meta_row[0];
		$meta_value = maybe_serialize( $meta_row[1] );

		$meta_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$order_meta_table} WHERE order_id = %d AND meta_key = %s LIMIT 1", $order_id, $meta_key )
		);

		if ( $meta_id > 0 ) {
			$wpdb->update(
				$order_meta_table,
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
				$order_meta_table,
				array(
					'order_id' => $order_id,
					'meta_key' => $meta_key,
					'meta_value' => $meta_value,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s' )
			);
		}
	}

	if ( (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $customer_meta_table ) ) === $customer_meta_table ) {
		$customer_meta_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$customer_meta_table} WHERE customer_id = %d AND meta_key = %s LIMIT 1", $customer_id, 'last_license_key' )
		);

		if ( $customer_meta_id > 0 ) {
			$wpdb->update(
				$customer_meta_table,
				array(
					'meta_value' => $license_key,
					'updated_at' => $now,
				),
				array( 'id' => $customer_meta_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$customer_meta_table,
				array(
					'customer_id' => $customer_id,
					'meta_key' => 'last_license_key',
					'meta_value' => $license_key,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d', '%s', '%s', '%s', '%s' )
			);
		}
	}

	$summary[] = array(
		'plan' => $plan,
		'email' => $email,
		'customer_id' => $customer_id,
		'order_id' => $order_id,
		'license_key' => $license_key,
		'product_id' => $product_id,
		'variation_id' => $variation_id,
		'activation_limit' => $activation_limit,
	);
}

WP_CLI::success( sprintf( 'Seeded %d FluentCart customer/order records with license keys.', count( $summary ) ) );
foreach ( $summary as $item ) {
	WP_CLI::log(
		sprintf(
			'%s | customer_id=%d | order_id=%d | %s | product_id=%d variation_id=%d activation_limit=%d',
			$item['plan'],
			(int) $item['customer_id'],
			(int) $item['order_id'],
			(string) $item['license_key'],
			(int) $item['product_id'],
			(int) $item['variation_id'],
			(int) $item['activation_limit']
		)
	);
}
