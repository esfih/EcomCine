<?php
/**
 * Clone FluentCart core billing/licensing fixtures from WebmasterOS into EcomCine.
 *
 * Scope:
 * - 4 products (pricing + variation payloads + licensing settings + allowances)
 * - 1 customer
 * - 4 orders and linked addresses/items/operations/transactions/subscriptions
 * - Real FluentCart Pro license keys (wp_fct_licenses)
 * - Compatibility keys (wp_fluentcart_licenses) mirrored from wp_fct_licenses
 *
 * Run with:
 *   ./scripts/wp.sh php scripts/licensing/seed-fluentcart-from-wmos-clone.php
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
	WP_CLI::error( 'WordPress DB object is unavailable.' );
}

$tables = array(
	'product_details' => $wpdb->prefix . 'fct_product_details',
	'product_variations' => $wpdb->prefix . 'fct_product_variations',
	'product_meta' => $wpdb->prefix . 'fct_product_meta',
	'customers' => $wpdb->prefix . 'fct_customers',
	'customer_addresses' => $wpdb->prefix . 'fct_customer_addresses',
	'orders' => $wpdb->prefix . 'fct_orders',
	'order_addresses' => $wpdb->prefix . 'fct_order_addresses',
	'order_items' => $wpdb->prefix . 'fct_order_items',
	'order_meta' => $wpdb->prefix . 'fct_order_meta',
	'order_operations' => $wpdb->prefix . 'fct_order_operations',
	'order_transactions' => $wpdb->prefix . 'fct_order_transactions',
	'subscriptions' => $wpdb->prefix . 'fct_subscriptions',
	'fct_meta' => $wpdb->prefix . 'fct_meta',
	'licenses' => $wpdb->prefix . 'fct_licenses',
	'license_sites' => $wpdb->prefix . 'fct_license_sites',
	'license_activations' => $wpdb->prefix . 'fct_license_activations',
	'license_meta' => $wpdb->prefix . 'fct_license_meta',
	'compat_licenses' => $wpdb->prefix . 'fluentcart_licenses',
);

$required = array(
	$tables['product_details'],
	$tables['product_variations'],
	$tables['product_meta'],
	$tables['customers'],
	$tables['orders'],
	$tables['order_items'],
	$tables['licenses'],
	$tables['subscriptions'],
);

foreach ( $required as $table ) {
	$exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $exists !== $table ) {
		WP_CLI::error( sprintf( 'Required table missing: %s', $table ) );
	}
}

if ( (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tables['compat_licenses'] ) ) !== $tables['compat_licenses'] ) {
	$create_sql = "CREATE TABLE IF NOT EXISTS `{$tables['compat_licenses']}` (
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

/**
 * @param array<string,mixed> $data
 * @param array<string,string> $formats
 */
function ecomcine_seed_replace( $table, $data, $formats ) {
	global $wpdb;
	$ok = $wpdb->replace( $table, $data, array_values( $formats ) );
	if ( false === $ok ) {
		WP_CLI::warning( sprintf( 'Replace failed on %s: %s', $table, $wpdb->last_error ) );
	}
}

/**
 * @param array<int,int> $ids
 */
function ecomcine_prune_by_ids( $table, $ids ) {
	global $wpdb;
	$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
	if ( empty( $ids ) ) {
		$wpdb->query( "DELETE FROM {$table}" );
		return;
	}
	$in = implode( ',', $ids );
	$wpdb->query( "DELETE FROM {$table} WHERE id NOT IN ({$in})" );
}

$product_ids = array( 2566, 2569, 2571, 2573 );
$variation_to_product = array(
	1 => 2566,
	2 => 2569,
	3 => 2571,
	4 => 2573,
);

$product_post_map = array();
foreach ( $product_ids as $pid ) {
	$post_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$tables['product_details']} WHERE id = %d LIMIT 1", $pid ) );
	if ( $post_id <= 0 ) {
		WP_CLI::error( sprintf( 'Missing local post_id mapping in %s for product id %d.', $tables['product_details'], $pid ) );
	}
	$product_post_map[ $pid ] = $post_id;
}

$product_details_rows = array(
	2566 => array( 'min_price' => 0, 'max_price' => 0, 'other_info' => '{"is_bundle_product":null,"sold_individually":"yes"}', 'created_at' => '2026-03-09 18:19:11', 'updated_at' => '2026-03-11 00:08:27' ),
	2569 => array( 'min_price' => 900, 'max_price' => 900, 'other_info' => '{"is_bundle_product":null}', 'created_at' => '2026-03-11 00:13:01', 'updated_at' => '2026-03-11 00:14:29' ),
	2571 => array( 'min_price' => 1900, 'max_price' => 1900, 'other_info' => '{"is_bundle_product":null}', 'created_at' => '2026-03-11 00:16:15', 'updated_at' => '2026-03-11 00:16:59' ),
	2573 => array( 'min_price' => 9900, 'max_price' => 9900, 'other_info' => '{"is_bundle_product":null}', 'created_at' => '2026-03-11 00:18:48', 'updated_at' => '2026-03-11 00:19:34' ),
);

foreach ( $product_details_rows as $product_id => $row ) {
	ecomcine_seed_replace(
		$tables['product_details'],
		array(
			'id' => $product_id,
			'post_id' => $product_post_map[ $product_id ],
			'fulfillment_type' => 'digital',
			'min_price' => (float) $row['min_price'],
			'max_price' => (float) $row['max_price'],
			'default_variation_id' => null,
			'default_media' => null,
			'manage_stock' => 0,
			'stock_availability' => 'in-stock',
			'variation_type' => 'simple',
			'manage_downloadable' => 0,
			'other_info' => $row['other_info'],
			'created_at' => $row['created_at'],
			'updated_at' => $row['updated_at'],
		),
		array(
			'id' => '%d',
			'post_id' => '%d',
			'fulfillment_type' => '%s',
			'min_price' => '%f',
			'max_price' => '%f',
			'default_variation_id' => '%d',
			'default_media' => '%s',
			'manage_stock' => '%d',
			'stock_availability' => '%s',
			'variation_type' => '%s',
			'manage_downloadable' => '%d',
			'other_info' => '%s',
			'created_at' => '%s',
			'updated_at' => '%s',
		)
	);
}

$variation_rows = array(
	1 => array(
		'variation_title' => 'Freemium',
		'sku' => 'freemium',
		'item_price' => 0,
		'compare_price' => 900,
		'other_info' => '{"description":"","payment_type":"subscription","times":"","repeat_interval":"monthly","trial_days":"","billing_summary":"0 monthly Until Cancel","manage_setup_fee":"no","signup_fee_name":"","signup_fee":0,"setup_fee_per_item":"no","is_bundle_product":null,"installment":"no"}',
		'created_at' => '2026-03-09 18:19:11',
		'updated_at' => '2026-03-12 19:46:06',
	),
	2 => array(
		'variation_title' => 'Solo',
		'sku' => 'subs-solo',
		'item_price' => 900,
		'compare_price' => 1900,
		'other_info' => '{"description":"","payment_type":"subscription","times":"","repeat_interval":"monthly","trial_days":"","billing_summary":"9 monthly Until Cancel","manage_setup_fee":"no","signup_fee_name":"","signup_fee":0,"setup_fee_per_item":"no","is_bundle_product":null}',
		'created_at' => '2026-03-11 00:13:01',
		'updated_at' => '2026-03-11 00:40:58',
	),
	3 => array(
		'variation_title' => 'Maestro',
		'sku' => 'subs-maestro',
		'item_price' => 1900,
		'compare_price' => 4900,
		'other_info' => '{"description":"","payment_type":"subscription","times":"","repeat_interval":"monthly","trial_days":"","billing_summary":"9 monthly Until Cancel","manage_setup_fee":"no","signup_fee_name":"","signup_fee":0,"setup_fee_per_item":"no","is_bundle_product":null,"installment":"no"}',
		'created_at' => '2026-03-11 00:16:15',
		'updated_at' => '2026-03-11 00:57:06',
	),
	4 => array(
		'variation_title' => 'Agency',
		'sku' => 'subs-agency',
		'item_price' => 9900,
		'compare_price' => 24900,
		'other_info' => '{"description":"","payment_type":"subscription","times":"","repeat_interval":"monthly","trial_days":"","billing_summary":"9 monthly Until Cancel","manage_setup_fee":"no","signup_fee_name":"","signup_fee":0,"setup_fee_per_item":"no","is_bundle_product":null,"installment":"no"}',
		'created_at' => '2026-03-11 00:18:48',
		'updated_at' => '2026-03-12 19:49:31',
	),
);

foreach ( $variation_rows as $variation_id => $row ) {
	$product_id = $variation_to_product[ $variation_id ];
	ecomcine_seed_replace(
		$tables['product_variations'],
		array(
			'id' => $variation_id,
			'post_id' => $product_post_map[ $product_id ],
			'media_id' => null,
			'serial_index' => 1,
			'sold_individually' => 0,
			'variation_title' => $row['variation_title'],
			'variation_identifier' => null,
			'sku' => $row['sku'],
			'manage_stock' => 0,
			'payment_type' => 'subscription',
			'stock_status' => 'in-stock',
			'backorders' => 0,
			'total_stock' => 1,
			'on_hold' => 0,
			'committed' => 0,
			'available' => 1,
			'fulfillment_type' => 'digital',
			'item_status' => 'active',
			'manage_cost' => 'false',
			'item_price' => (float) $row['item_price'],
			'item_cost' => 0,
			'compare_price' => (float) $row['compare_price'],
			'shipping_class' => null,
			'other_info' => $row['other_info'],
			'downloadable' => 'false',
			'created_at' => $row['created_at'],
			'updated_at' => $row['updated_at'],
		),
		array(
			'id' => '%d', 'post_id' => '%d', 'media_id' => '%d', 'serial_index' => '%d', 'sold_individually' => '%d',
			'variation_title' => '%s', 'variation_identifier' => '%s', 'sku' => '%s', 'manage_stock' => '%d', 'payment_type' => '%s',
			'stock_status' => '%s', 'backorders' => '%d', 'total_stock' => '%d', 'on_hold' => '%d', 'committed' => '%d', 'available' => '%d',
			'fulfillment_type' => '%s', 'item_status' => '%s', 'manage_cost' => '%s', 'item_price' => '%f', 'item_cost' => '%f',
			'compare_price' => '%f', 'shipping_class' => '%d', 'other_info' => '%s', 'downloadable' => '%s', 'created_at' => '%s', 'updated_at' => '%s',
		)
	);
}

ecomcine_prune_by_ids( $tables['product_meta'], array() );

$product_meta_rows = array(
	array( 'id' => 1, 'object_id' => 2566, 'meta_key' => 'license_settings', 'meta_value' => '{"enabled":"no","version":"0.1.6","global_update_file":{"id":"","driver":"local","path":"","url":""},"variations":{"1":{"variation_id":1,"activation_limit":"1","validity":{"unit":"year","value":1}}},"wp":{"is_wp":"yes","readme_url":"","banner_url":"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp","icon_url":"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp","required_php":"","required_wp":""},"prefix":"WMOS"}', 'created_at' => '2026-03-11 00:11:05', 'updated_at' => '2026-03-11 00:11:32' ),
	array( 'id' => 2, 'object_id' => 2569, 'meta_key' => 'license_settings', 'meta_value' => '{"enabled":"yes","version":"0.1.6","global_update_file":{"id":"","driver":"local","path":"","url":""},"variations":{"2":{"variation_id":2,"activation_limit":"3","validity":{"unit":"month","value":1}}},"wp":{"is_wp":"yes","readme_url":"https://webmasteros.com/changelog","banner_url":"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp","icon_url":"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp","required_php":"","required_wp":""},"prefix":"wmos"}', 'created_at' => '2026-03-11 00:15:31', 'updated_at' => '2026-03-11 00:18:11' ),
	array( 'id' => 3, 'object_id' => 2571, 'meta_key' => 'license_settings', 'meta_value' => '{"enabled":"yes","version":"0.1.6","global_update_file":{"id":"","driver":"local","path":"","url":""},"variations":{"3":{"variation_id":3,"activation_limit":"10","validity":{"unit":"month","value":1}}},"wp":{"is_wp":"yes","readme_url":"https://webmasteros.com/changelog","banner_url":"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp","icon_url":"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp","required_php":"","required_wp":""},"prefix":"wmos"}', 'created_at' => '2026-03-11 00:16:15', 'updated_at' => '2026-03-11 00:17:49' ),
	array( 'id' => 4, 'object_id' => 2573, 'meta_key' => 'license_settings', 'meta_value' => '{"enabled":"yes","version":"0.1.6","global_update_file":{"id":"","driver":"local","path":"","url":""},"variations":{"4":{"variation_id":4,"activation_limit":"100","validity":{"unit":"month","value":1}}},"wp":{"is_wp":"yes","readme_url":"https://webmasteros.com/changelog","banner_url":"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp","icon_url":"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp","required_php":"","required_wp":""},"prefix":"wmos"}', 'created_at' => '2026-03-11 00:18:48', 'updated_at' => '2026-03-11 00:19:43' ),
	array( 'id' => 5, 'object_id' => 2571, 'meta_key' => 'wmos_allowances_v1', 'meta_value' => '{"defaults":{"ai_queries_hour":30,"ai_queries_day":360,"ai_queries_month":7200,"remixes_max":150,"promotions_max":75,"queue_max":100,"ai_mode":"mutualized_ai"},"variations":[]}', 'created_at' => null, 'updated_at' => null ),
	array( 'id' => 6, 'object_id' => 2569, 'meta_key' => 'wmos_allowances_v1', 'meta_value' => '{"defaults":{"ai_queries_hour":12,"ai_queries_day":120,"ai_queries_month":2500,"remixes_max":10,"promotions_max":10,"queue_max":50,"ai_mode":"mutualized_ai"},"variations":[]}', 'created_at' => null, 'updated_at' => null ),
	array( 'id' => 7, 'object_id' => 2566, 'meta_key' => 'wmos_allowances_v1', 'meta_value' => '{"defaults":{"ai_queries_hour":1,"ai_queries_day":1,"ai_queries_month":30,"remixes_max":1,"promotions_max":1,"queue_max":10,"ai_mode":"mutualized_ai"},"variations":[]}', 'created_at' => null, 'updated_at' => null ),
	array( 'id' => 8, 'object_id' => 2573, 'meta_key' => 'wmos_allowances_v1', 'meta_value' => '{"defaults":{"ai_queries_hour":100,"ai_queries_day":1000,"ai_queries_month":30000,"remixes_max":10000,"promotions_max":10000,"queue_max":1000,"ai_mode":"confidential_ai"},"variations":[]}', 'created_at' => null, 'updated_at' => null ),
);

foreach ( $product_meta_rows as $row ) {
	ecomcine_seed_replace(
		$tables['product_meta'],
		array(
			'id' => (int) $row['id'],
			'object_id' => (int) $row['object_id'],
			'object_type' => null,
			'meta_key' => (string) $row['meta_key'],
			'meta_value' => (string) $row['meta_value'],
			'created_at' => $row['created_at'],
			'updated_at' => $row['updated_at'],
		),
		array(
			'id' => '%d', 'object_id' => '%d', 'object_type' => '%s', 'meta_key' => '%s', 'meta_value' => '%s', 'created_at' => '%s', 'updated_at' => '%s',
		)
	);
}

ecomcine_prune_by_ids( $tables['product_meta'], array( 1, 2, 3, 4, 5, 6, 7, 8 ) );

ecomcine_seed_replace(
	$tables['customers'],
	array(
		'id' => 1,
		'user_id' => 1,
		'contact_id' => 0,
		'email' => 'esfihm@gmail.com',
		'first_name' => 'teramohadmin',
		'last_name' => '',
		'status' => 'active',
		'purchase_value' => null,
		'purchase_count' => 3,
		'ltv' => 12700,
		'first_purchase_date' => '2026-03-11 00:52:53',
		'last_purchase_date' => '2026-03-11 00:59:21',
		'aov' => 4233.33,
		'notes' => '',
		'uuid' => 'cf8b841a79a208c3c14678fcb1ea4f5b',
		'country' => 'MU',
		'city' => 'deffeef',
		'state' => '',
		'postcode' => '41845845',
		'created_at' => '2026-03-11 00:52:53',
		'updated_at' => '2026-03-11 00:59:39',
	),
	array(
		'id' => '%d', 'user_id' => '%d', 'contact_id' => '%d', 'email' => '%s', 'first_name' => '%s', 'last_name' => '%s', 'status' => '%s',
		'purchase_value' => '%s', 'purchase_count' => '%d', 'ltv' => '%d', 'first_purchase_date' => '%s', 'last_purchase_date' => '%s', 'aov' => '%f',
		'notes' => '%s', 'uuid' => '%s', 'country' => '%s', 'city' => '%s', 'state' => '%s', 'postcode' => '%s', 'created_at' => '%s', 'updated_at' => '%s',
	)
);
ecomcine_prune_by_ids( $tables['customers'], array( 1 ) );

$customer_addresses = array(
	array( 'id' => 1, 'type' => 'billing' ),
	array( 'id' => 2, 'type' => 'shipping' ),
);
foreach ( $customer_addresses as $addr ) {
	ecomcine_seed_replace(
		$tables['customer_addresses'],
		array(
			'id' => (int) $addr['id'],
			'customer_id' => 1,
			'is_primary' => 1,
			'type' => (string) $addr['type'],
			'status' => 'active',
			'label' => '',
			'name' => 'teramohadmin ',
			'address_1' => 'wdwdwd',
			'address_2' => '',
			'city' => 'deffeef',
			'state' => '',
			'phone' => null,
			'email' => 'esfihm@gmail.com',
			'postcode' => '41845845',
			'country' => 'MU',
			'meta' => '{"other_data":{"first_name":"teramohadmin","last_name":""}}',
			'created_at' => '2026-03-11 00:52:53',
			'updated_at' => '2026-03-11 00:52:53',
		),
		array(
			'id' => '%d', 'customer_id' => '%d', 'is_primary' => '%d', 'type' => '%s', 'status' => '%s', 'label' => '%s', 'name' => '%s',
			'address_1' => '%s', 'address_2' => '%s', 'city' => '%s', 'state' => '%s', 'phone' => '%s', 'email' => '%s', 'postcode' => '%s',
			'country' => '%s', 'meta' => '%s', 'created_at' => '%s', 'updated_at' => '%s',
		)
	);
}
ecomcine_prune_by_ids( $tables['customer_addresses'], array( 1, 2 ) );

$orders = array(
	array( 'id' => 1, 'status' => 'completed', 'receipt_number' => 1, 'invoice_no' => 'INV-1', 'subtotal' => 900, 'total_amount' => 900, 'total_paid' => 900, 'completed_at' => '2026-03-11 00:53:46', 'uuid' => '1cef3fbd62314bb74b0694577874e0f4', 'created_at' => '2026-03-11 00:52:53', 'updated_at' => '2026-03-11 00:53:46', 'payment_status' => 'paid' ),
	array( 'id' => 2, 'status' => 'on-hold', 'receipt_number' => null, 'invoice_no' => '', 'subtotal' => 1900, 'total_amount' => 1900, 'total_paid' => 0, 'completed_at' => null, 'uuid' => 'b2f493200a65a6517064fc43b4f256d7', 'created_at' => '2026-03-11 00:57:33', 'updated_at' => '2026-03-11 00:57:33', 'payment_status' => 'pending' ),
	array( 'id' => 3, 'status' => 'completed', 'receipt_number' => 2, 'invoice_no' => 'INV-2', 'subtotal' => 1900, 'total_amount' => 1900, 'total_paid' => 1900, 'completed_at' => '2026-03-11 00:58:42', 'uuid' => 'c71565883c5b15b3093639fe34a0ca6e', 'created_at' => '2026-03-11 00:58:15', 'updated_at' => '2026-03-11 00:58:42', 'payment_status' => 'paid' ),
	array( 'id' => 4, 'status' => 'completed', 'receipt_number' => 3, 'invoice_no' => 'INV-3', 'subtotal' => 9900, 'total_amount' => 9900, 'total_paid' => 9900, 'completed_at' => '2026-03-11 00:59:40', 'uuid' => '7fe99cf633471b3f9be3a3aeceb0e7f8', 'created_at' => '2026-03-11 00:59:21', 'updated_at' => '2026-03-11 00:59:40', 'payment_status' => 'paid' ),
);

foreach ( $orders as $order ) {
	ecomcine_seed_replace(
		$tables['orders'],
		array(
			'id' => (int) $order['id'],
			'status' => (string) $order['status'],
			'parent_id' => null,
			'receipt_number' => $order['receipt_number'],
			'invoice_no' => (string) $order['invoice_no'],
			'fulfillment_type' => 'digital',
			'type' => 'subscription',
			'mode' => 'test',
			'shipping_status' => '',
			'customer_id' => 1,
			'payment_method' => 'offline_payment',
			'payment_status' => (string) $order['payment_status'],
			'payment_method_title' => 'Cash',
			'currency' => 'USD',
			'subtotal' => (int) $order['subtotal'],
			'discount_tax' => 0,
			'manual_discount_total' => 0,
			'coupon_discount_total' => 0,
			'shipping_tax' => 0,
			'shipping_total' => 0,
			'tax_total' => 0,
			'total_amount' => (int) $order['total_amount'],
			'total_paid' => (int) $order['total_paid'],
			'total_refund' => 0,
			'rate' => 1,
			'tax_behavior' => 0,
			'note' => '',
			'ip_address' => '197.225.123.51',
			'completed_at' => $order['completed_at'],
			'refunded_at' => null,
			'uuid' => (string) $order['uuid'],
			'config' => '{"user_tz":"Indian\\/Mauritius","create_account_after_paid":"yes"}',
			'created_at' => (string) $order['created_at'],
			'updated_at' => (string) $order['updated_at'],
		),
		array(
			'id' => '%d', 'status' => '%s', 'parent_id' => '%d', 'receipt_number' => '%d', 'invoice_no' => '%s', 'fulfillment_type' => '%s',
			'type' => '%s', 'mode' => '%s', 'shipping_status' => '%s', 'customer_id' => '%d', 'payment_method' => '%s', 'payment_status' => '%s',
			'payment_method_title' => '%s', 'currency' => '%s', 'subtotal' => '%d', 'discount_tax' => '%d', 'manual_discount_total' => '%d',
			'coupon_discount_total' => '%d', 'shipping_tax' => '%d', 'shipping_total' => '%d', 'tax_total' => '%d', 'total_amount' => '%d',
			'total_paid' => '%d', 'total_refund' => '%d', 'rate' => '%f', 'tax_behavior' => '%d', 'note' => '%s', 'ip_address' => '%s',
			'completed_at' => '%s', 'refunded_at' => '%s', 'uuid' => '%s', 'config' => '%s', 'created_at' => '%s', 'updated_at' => '%s',
		)
	);
}
ecomcine_prune_by_ids( $tables['orders'], array( 1, 2, 3, 4 ) );

$order_addresses = array(
	array( 'id' => 1, 'order_id' => 1, 'type' => 'billing', 'meta' => '{"other_data":{"email":"esfihm@gmail.com","first_name":"teramohadmin"}}', 'created_at' => '2026-03-11 00:52:53' ),
	array( 'id' => 2, 'order_id' => 1, 'type' => 'shipping', 'meta' => '{"other_data":{"email":"esfihm@gmail.com","first_name":"teramohadmin"}}', 'created_at' => '2026-03-11 00:52:53' ),
	array( 'id' => 3, 'order_id' => 2, 'type' => 'billing', 'meta' => '{"other_data":{"email":"esfihm@gmail.com","address_id":"1","first_name":"teramohadmin"}}', 'created_at' => '2026-03-11 00:57:33' ),
	array( 'id' => 4, 'order_id' => 2, 'type' => 'shipping', 'meta' => '{"other_data":{"email":"esfihm@gmail.com","address_id":"1","first_name":"teramohadmin"}}', 'created_at' => '2026-03-11 00:57:33' ),
	array( 'id' => 5, 'order_id' => 3, 'type' => 'billing', 'meta' => '{"other_data":{"email":"esfihm@gmail.com","address_id":"1","first_name":"teramohadmin"}}', 'created_at' => '2026-03-11 00:58:15' ),
	array( 'id' => 6, 'order_id' => 3, 'type' => 'shipping', 'meta' => '{"other_data":{"email":"esfihm@gmail.com","address_id":"1","first_name":"teramohadmin"}}', 'created_at' => '2026-03-11 00:58:15' ),
	array( 'id' => 7, 'order_id' => 4, 'type' => 'billing', 'meta' => '{"other_data":{"email":"esfihm@gmail.com","address_id":"1","first_name":"teramohadmin"}}', 'created_at' => '2026-03-11 00:59:21' ),
	array( 'id' => 8, 'order_id' => 4, 'type' => 'shipping', 'meta' => '{"other_data":{"email":"esfihm@gmail.com","address_id":"1","first_name":"teramohadmin"}}', 'created_at' => '2026-03-11 00:59:21' ),
);

foreach ( $order_addresses as $addr ) {
	ecomcine_seed_replace(
		$tables['order_addresses'],
		array(
			'id' => (int) $addr['id'],
			'order_id' => (int) $addr['order_id'],
			'type' => (string) $addr['type'],
			'name' => 'teramohadmin',
			'address_1' => 'wdwdwd',
			'address_2' => '',
			'city' => 'deffeef',
			'state' => '',
			'postcode' => '41845845',
			'country' => 'MU',
			'meta' => (string) $addr['meta'],
			'created_at' => (string) $addr['created_at'],
			'updated_at' => (string) $addr['created_at'],
		),
		array( 'id' => '%d', 'order_id' => '%d', 'type' => '%s', 'name' => '%s', 'address_1' => '%s', 'address_2' => '%s', 'city' => '%s', 'state' => '%s', 'postcode' => '%s', 'country' => '%s', 'meta' => '%s', 'created_at' => '%s', 'updated_at' => '%s' )
	);
}
ecomcine_prune_by_ids( $tables['order_addresses'], array( 1, 2, 3, 4, 5, 6, 7, 8 ) );

$order_items = array(
	array( 'id' => 1, 'order_id' => 1, 'product_id' => 2569, 'variation_id' => 2, 'title' => 'Solo', 'unit_price' => 900, 'created_at' => '2026-03-11 00:52:53' ),
	array( 'id' => 2, 'order_id' => 2, 'product_id' => 2571, 'variation_id' => 3, 'title' => 'Maestro', 'unit_price' => 1900, 'created_at' => '2026-03-11 00:57:33' ),
	array( 'id' => 3, 'order_id' => 3, 'product_id' => 2571, 'variation_id' => 3, 'title' => 'Maestro', 'unit_price' => 1900, 'created_at' => '2026-03-11 00:58:15' ),
	array( 'id' => 4, 'order_id' => 4, 'product_id' => 2573, 'variation_id' => 4, 'title' => 'Agency', 'unit_price' => 9900, 'created_at' => '2026-03-11 00:59:21' ),
);

foreach ( $order_items as $item ) {
	$product_id = (int) $item['product_id'];
	ecomcine_seed_replace(
		$tables['order_items'],
		array(
			'id' => (int) $item['id'],
			'order_id' => (int) $item['order_id'],
			'post_id' => $product_post_map[ $product_id ],
			'fulfillment_type' => 'digital',
			'payment_type' => 'subscription',
			'post_title' => (string) $item['title'],
			'title' => (string) $item['title'],
			'object_id' => (int) $item['variation_id'],
			'cart_index' => 0,
			'quantity' => 1,
			'unit_price' => (int) $item['unit_price'],
			'cost' => 0,
			'subtotal' => (int) $item['unit_price'],
			'tax_amount' => 0,
			'shipping_charge' => 0,
			'discount_total' => 0,
			'line_total' => (int) $item['unit_price'],
			'refund_total' => 0,
			'rate' => 1,
			'other_info' => '{"description":"","payment_type":"subscription","times":"","repeat_interval":"monthly","trial_days":"","billing_summary":"9 monthly Until Cancel","manage_setup_fee":"no","signup_fee_name":"","signup_fee":0,"setup_fee_per_item":"no","is_bundle_product":null,"installment":"no"}',
			'line_meta' => '[]',
			'fulfilled_quantity' => 0,
			'referrer' => null,
			'created_at' => (string) $item['created_at'],
			'updated_at' => (string) $item['created_at'],
		),
		array(
			'id' => '%d', 'order_id' => '%d', 'post_id' => '%d', 'fulfillment_type' => '%s', 'payment_type' => '%s', 'post_title' => '%s', 'title' => '%s',
			'object_id' => '%d', 'cart_index' => '%d', 'quantity' => '%d', 'unit_price' => '%d', 'cost' => '%d', 'subtotal' => '%d', 'tax_amount' => '%d',
			'shipping_charge' => '%d', 'discount_total' => '%d', 'line_total' => '%d', 'refund_total' => '%d', 'rate' => '%d', 'other_info' => '%s',
			'line_meta' => '%s', 'fulfilled_quantity' => '%d', 'referrer' => '%s', 'created_at' => '%s', 'updated_at' => '%s',
		)
	);
}
ecomcine_prune_by_ids( $tables['order_items'], array( 1, 2, 3, 4 ) );

$wpdb->query( "DELETE FROM {$tables['order_meta']}" );

for ( $i = 1; $i <= 4; $i++ ) {
	ecomcine_seed_replace(
		$tables['order_operations'],
		array(
			'id' => $i,
			'order_id' => $i,
			'created_via' => null,
			'emails_sent' => 0,
			'sales_recorded' => 0,
			'utm_campaign' => '',
			'utm_term' => '',
			'utm_source' => '',
			'utm_medium' => '',
			'utm_content' => '',
			'utm_id' => '',
			'cart_hash' => '',
			'refer_url' => '',
			'meta' => null,
			'created_at' => array( '2026-03-11 00:52:53', '2026-03-11 00:57:33', '2026-03-11 00:58:15', '2026-03-11 00:59:21' )[ $i - 1 ],
			'updated_at' => array( '2026-03-11 00:52:53', '2026-03-11 00:57:33', '2026-03-11 00:58:15', '2026-03-11 00:59:21' )[ $i - 1 ],
		),
		array( 'id' => '%d', 'order_id' => '%d', 'created_via' => '%s', 'emails_sent' => '%d', 'sales_recorded' => '%d', 'utm_campaign' => '%s', 'utm_term' => '%s', 'utm_source' => '%s', 'utm_medium' => '%s', 'utm_content' => '%s', 'utm_id' => '%s', 'cart_hash' => '%s', 'refer_url' => '%s', 'meta' => '%s', 'created_at' => '%s', 'updated_at' => '%s' )
	);
}
ecomcine_prune_by_ids( $tables['order_operations'], array( 1, 2, 3, 4 ) );

$order_transactions = array(
	array( 'id' => 1, 'order_id' => 1, 'transaction_type' => 'subscription', 'subscription_id' => 1, 'status' => 'succeeded', 'total' => 900, 'uuid' => 'f6a1225d6be50a1ac3238d8883d7ab31', 'created_at' => '2026-03-11 00:52:53', 'updated_at' => '2026-03-11 00:53:46' ),
	array( 'id' => 2, 'order_id' => 2, 'transaction_type' => 'charge', 'subscription_id' => 2, 'status' => 'pending', 'total' => 1900, 'uuid' => 'd0b7452ca65a0530564b66665757846c', 'created_at' => '2026-03-11 00:57:33', 'updated_at' => '2026-03-11 00:57:33' ),
	array( 'id' => 3, 'order_id' => 3, 'transaction_type' => 'subscription', 'subscription_id' => 3, 'status' => 'succeeded', 'total' => 1900, 'uuid' => 'ac2b2d5be6b01241c89abbd8ff6b6761', 'created_at' => '2026-03-11 00:58:15', 'updated_at' => '2026-03-11 00:58:42' ),
	array( 'id' => 4, 'order_id' => 4, 'transaction_type' => 'subscription', 'subscription_id' => 4, 'status' => 'succeeded', 'total' => 9900, 'uuid' => '060a6d4d97808dc496aaaef4482da366', 'created_at' => '2026-03-11 00:59:21', 'updated_at' => '2026-03-11 00:59:39' ),
);

foreach ( $order_transactions as $txn ) {
	ecomcine_seed_replace(
		$tables['order_transactions'],
		array(
			'id' => (int) $txn['id'],
			'order_id' => (int) $txn['order_id'],
			'order_type' => 'subscription',
			'transaction_type' => (string) $txn['transaction_type'],
			'subscription_id' => (int) $txn['subscription_id'],
			'card_last_4' => null,
			'card_brand' => null,
			'vendor_charge_id' => '',
			'payment_method' => 'offline_payment',
			'payment_mode' => 'test',
			'payment_method_type' => 'offline_payment',
			'status' => (string) $txn['status'],
			'currency' => 'USD',
			'total' => (int) $txn['total'],
			'rate' => 1,
			'uuid' => (string) $txn['uuid'],
			'meta' => '[]',
			'created_at' => (string) $txn['created_at'],
			'updated_at' => (string) $txn['updated_at'],
		),
		array( 'id' => '%d', 'order_id' => '%d', 'order_type' => '%s', 'transaction_type' => '%s', 'subscription_id' => '%d', 'card_last_4' => '%d', 'card_brand' => '%s', 'vendor_charge_id' => '%s', 'payment_method' => '%s', 'payment_mode' => '%s', 'payment_method_type' => '%s', 'status' => '%s', 'currency' => '%s', 'total' => '%d', 'rate' => '%d', 'uuid' => '%s', 'meta' => '%s', 'created_at' => '%s', 'updated_at' => '%s' )
	);
}
ecomcine_prune_by_ids( $tables['order_transactions'], array( 1, 2, 3, 4 ) );

$subscriptions = array(
	array( 'id' => 1, 'uuid' => '629fcbb9ad9ce49d9cc25083bd8f7072', 'parent_order_id' => 1, 'product_id' => 2569, 'item_name' => 'Solo - Solo', 'variation_id' => 2, 'recurring_amount' => 900, 'recurring_total' => 900, 'created_at' => '2026-03-11 00:52:53' ),
	array( 'id' => 2, 'uuid' => '2f69f68518894e12551d086086de303b', 'parent_order_id' => 2, 'product_id' => 2571, 'item_name' => 'Maestro - Maestro', 'variation_id' => 3, 'recurring_amount' => 1900, 'recurring_total' => 1900, 'created_at' => '2026-03-11 00:57:33' ),
	array( 'id' => 3, 'uuid' => 'b541dac4353b54ef23dced2e85825b95', 'parent_order_id' => 3, 'product_id' => 2571, 'item_name' => 'Maestro - Maestro', 'variation_id' => 3, 'recurring_amount' => 1900, 'recurring_total' => 1900, 'created_at' => '2026-03-11 00:58:15' ),
	array( 'id' => 4, 'uuid' => 'ffe672b9142edccf5a6ecdd6b187b86d', 'parent_order_id' => 4, 'product_id' => 2573, 'item_name' => 'Agency - Agency', 'variation_id' => 4, 'recurring_amount' => 9900, 'recurring_total' => 9900, 'created_at' => '2026-03-11 00:59:21' ),
);

foreach ( $subscriptions as $sub ) {
	ecomcine_seed_replace(
		$tables['subscriptions'],
		array(
			'id' => (int) $sub['id'],
			'uuid' => (string) $sub['uuid'],
			'customer_id' => 1,
			'parent_order_id' => (int) $sub['parent_order_id'],
			'product_id' => (int) $sub['product_id'],
			'item_name' => (string) $sub['item_name'],
			'quantity' => 1,
			'variation_id' => (int) $sub['variation_id'],
			'billing_interval' => 'monthly',
			'signup_fee' => 0,
			'initial_tax_total' => 0,
			'recurring_amount' => (int) $sub['recurring_amount'],
			'recurring_tax_total' => 0,
			'recurring_total' => (int) $sub['recurring_total'],
			'bill_times' => 0,
			'bill_count' => 0,
			'expire_at' => null,
			'trial_ends_at' => null,
			'canceled_at' => null,
			'restored_at' => null,
			'collection_method' => 'automatic',
			'next_billing_date' => null,
			'trial_days' => 0,
			'vendor_customer_id' => null,
			'vendor_plan_id' => null,
			'vendor_subscription_id' => null,
			'status' => 'pending',
			'original_plan' => null,
			'vendor_response' => null,
			'current_payment_method' => 'offline_payment',
			'config' => '{"is_trial_days_simulated":"no","currency":"USD"}',
			'created_at' => (string) $sub['created_at'],
			'updated_at' => (string) $sub['created_at'],
		),
		array(
			'id' => '%d', 'uuid' => '%s', 'customer_id' => '%d', 'parent_order_id' => '%d', 'product_id' => '%d', 'item_name' => '%s',
			'quantity' => '%d', 'variation_id' => '%d', 'billing_interval' => '%s', 'signup_fee' => '%d', 'initial_tax_total' => '%d',
			'recurring_amount' => '%d', 'recurring_tax_total' => '%d', 'recurring_total' => '%d', 'bill_times' => '%d', 'bill_count' => '%d',
			'expire_at' => '%s', 'trial_ends_at' => '%s', 'canceled_at' => '%s', 'restored_at' => '%s', 'collection_method' => '%s',
			'next_billing_date' => '%s', 'trial_days' => '%d', 'vendor_customer_id' => '%s', 'vendor_plan_id' => '%s', 'vendor_subscription_id' => '%s',
			'status' => '%s', 'original_plan' => '%s', 'vendor_response' => '%s', 'current_payment_method' => '%s', 'config' => '%s',
			'created_at' => '%s', 'updated_at' => '%s',
		)
	);
}
ecomcine_prune_by_ids( $tables['subscriptions'], array( 1, 2, 3, 4 ) );

$licenses = array(
	array( 'id' => 1, 'status' => 'active', 'limit' => 3, 'activation_count' => 0, 'license_key' => 'wmosa68e07dbbeca57077a7285ad0e8f4d96', 'product_id' => 2569, 'variation_id' => 2, 'order_id' => 1, 'customer_id' => 1, 'expiration_date' => '2026-04-11 00:53:46', 'subscription_id' => 1, 'created_at' => '2026-03-11 00:53:46', 'updated_at' => '2026-03-11 18:16:25' ),
	array( 'id' => 2, 'status' => 'inactive', 'limit' => 10, 'activation_count' => 0, 'license_key' => 'wmos773a1c71af46ea15e0a405eb2a358cb4', 'product_id' => 2571, 'variation_id' => 3, 'order_id' => 3, 'customer_id' => 1, 'expiration_date' => '2026-04-11 00:58:42', 'subscription_id' => 3, 'created_at' => '2026-03-11 00:58:42', 'updated_at' => '2026-03-11 00:58:42' ),
	array( 'id' => 3, 'status' => 'inactive', 'limit' => 100, 'activation_count' => 0, 'license_key' => 'wmos408dc5d94f8685e068bf1983722c2099', 'product_id' => 2573, 'variation_id' => 4, 'order_id' => 4, 'customer_id' => 1, 'expiration_date' => '2026-04-11 00:59:39', 'subscription_id' => 4, 'created_at' => '2026-03-11 00:59:39', 'updated_at' => '2026-03-11 00:59:39' ),
);

foreach ( $licenses as $license ) {
	ecomcine_seed_replace(
		$tables['licenses'],
		array(
			'id' => (int) $license['id'],
			'status' => (string) $license['status'],
			'limit' => (int) $license['limit'],
			'activation_count' => (int) $license['activation_count'],
			'license_key' => (string) $license['license_key'],
			'product_id' => (int) $license['product_id'],
			'variation_id' => (int) $license['variation_id'],
			'order_id' => (int) $license['order_id'],
			'parent_id' => null,
			'customer_id' => (int) $license['customer_id'],
			'expiration_date' => (string) $license['expiration_date'],
			'last_reminder_sent' => null,
			'last_reminder_type' => null,
			'subscription_id' => (int) $license['subscription_id'],
			'config' => '[]',
			'created_at' => (string) $license['created_at'],
			'updated_at' => (string) $license['updated_at'],
		),
		array(
			'id' => '%d', 'status' => '%s', 'limit' => '%d', 'activation_count' => '%d', 'license_key' => '%s', 'product_id' => '%d',
			'variation_id' => '%d', 'order_id' => '%d', 'parent_id' => '%d', 'customer_id' => '%d', 'expiration_date' => '%s',
			'last_reminder_sent' => '%s', 'last_reminder_type' => '%s', 'subscription_id' => '%d', 'config' => '%s', 'created_at' => '%s', 'updated_at' => '%s',
		)
	);
}
ecomcine_prune_by_ids( $tables['licenses'], array( 1, 2, 3 ) );

ecomcine_seed_replace(
	$tables['license_sites'],
	array(
		'id' => 1,
		'site_url' => 'wmos-license-probe.example',
		'server_version' => '8.2.0',
		'platform_version' => '6.8.1',
		'other' => null,
		'created_at' => '2026-03-11 01:13:01',
		'updated_at' => '2026-03-11 01:13:01',
	),
	array( 'id' => '%d', 'site_url' => '%s', 'server_version' => '%s', 'platform_version' => '%s', 'other' => '%s', 'created_at' => '%s', 'updated_at' => '%s' )
);
ecomcine_prune_by_ids( $tables['license_sites'], array( 1 ) );

$wpdb->query( "DELETE FROM {$tables['license_activations']}" );
$wpdb->query( "DELETE FROM {$tables['license_meta']}" );

$wpdb->query( "DELETE FROM {$tables['compat_licenses']}" );
$now = current_time( 'mysql', true );
foreach ( $licenses as $license ) {
	$wpdb->insert(
		$tables['compat_licenses'],
		array(
			'license_key' => (string) $license['license_key'],
			'product_id' => (int) $license['product_id'],
			'variation_id' => (int) $license['variation_id'],
			'activation_limit' => (int) $license['limit'],
			'status' => (string) $license['status'],
			'created_at' => $now,
			'updated_at' => $now,
		),
		array( '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
	);
}

// FluentCart settings/options clone pass (excluding environment-specific license/version internals).
$current_store_settings = get_option( 'fluent_cart_store_settings', array() );
if ( ! is_array( $current_store_settings ) ) {
	$current_store_settings = array();
}

$source_store_settings = array(
	'store_name' => 'WebMasterOS',
	'note_for_user_account_creation' => 'An user account will be created',
	'checkout_button_text' => 'Checkout',
	'view_cart_button_text' => 'View Cart',
	'cart_button_text' => 'Add To Cart',
	'popup_button_text' => 'View Product',
	'out_of_stock_button_text' => 'Not Available',
	'currency_position' => 'before',
	'decimal_separator' => 'dot',
	'checkout_method_style' => 'logo',
	'enable_modal_checkout' => 'no',
	'require_logged_in' => 'no',
	'show_cart_icon_in_nav' => 'no',
	'show_cart_icon_in_body' => 'yes',
	'additional_address_field' => 'yes',
	'hide_coupon_field' => 'no',
	'user_account_creation_mode' => 'all',
	'checkout_page_id' => '2560',
	'custom_payment_page_id' => '',
	'registration_page_id' => '',
	'login_page_id' => '',
	'cart_page_id' => '2561',
	'receipt_page_id' => '2562',
	'shop_page_id' => '2563',
	'customer_profile_page_id' => '2564',
	'customer_profile_page_slug' => 'customer-profile',
	'currency' => 'USD',
	'store_address1' => '',
	'store_address2' => '',
	'store_city' => '',
	'store_country' => 'US',
	'store_postcode' => '',
	'store_state' => '',
	'show_relevant_product_in_single_page' => 'yes',
	'show_relevant_product_in_modal' => '',
	'order_mode' => 'live',
	'variation_view' => 'both',
	'variation_columns' => 'masonry',
	'enable_early_payment_for_installment' => 'yes',
	'modules_settings' => array(),
	'min_receipt_number' => '1',
	'inv_prefix' => 'INV-',
	'store_logo' => array(
		'id' => '2312',
		'url' => 'https://webmasteros.com/wp-content/uploads/2026/03/WM-Febicon_WebP.webp',
		'title' => 'WM Febicon_WebP',
	),
	'query_timestamp' => 1773044159289,
);

// Keep locally resolved page mappings if source IDs do not exist in this environment.
foreach ( array( 'checkout_page_id', 'cart_page_id', 'receipt_page_id', 'shop_page_id', 'customer_profile_page_id' ) as $page_key ) {
	$source_page_id = (int) ( $source_store_settings[ $page_key ] ?? 0 );
	if ( $source_page_id > 0 && get_post( $source_page_id ) ) {
		continue;
	}
	if ( isset( $current_store_settings[ $page_key ] ) ) {
		$source_store_settings[ $page_key ] = (string) $current_store_settings[ $page_key ];
	}
}

$store_settings = array_merge( $current_store_settings, $source_store_settings );
update_option( 'fluent_cart_store_settings', $store_settings, true );

$source_modules_settings = array(
	'turnstile' => array(
		'active' => 'no',
		'site_key' => '',
		'secret_key' => '',
	),
	'stock_management' => array(
		'active' => 'no',
	),
	'license' => array(
		'active' => 'yes',
	),
	'order_bump' => array(
		'active' => 'no',
	),
);
update_option( 'fluent_cart_modules_settings', $source_modules_settings, true );
update_option( 'fluent_cart_plugin_once_activated', '1', true );

// Source fct_meta rows for payment settings.
$wpdb->query( "DELETE FROM {$tables['fct_meta']} WHERE object_type = 'option' AND meta_key IN ('fluent_cart_payment_settings_stripe','fluent_cart_payment_settings_offline_payment')" );
ecomcine_seed_replace(
	$tables['fct_meta'],
	array(
		'id' => 1,
		'object_type' => 'option',
		'object_id' => null,
		'meta_key' => 'fluent_cart_payment_settings_stripe',
		'meta_value' => '{"is_active":"yes","define_test_keys":false,"define_live_keys":false,"test_publishable_key":"","test_secret_key":"","test_webhook_secret":"","live_publishable_key":"pk_live_51FB4mCJV4TDq0RwSSvqweJ3weu3jW4PK8gnFpiE1UzQwm1JVt2iAT2iOu1sh9Mq8PSRYB7vDpUGAFsAROb6FdN9I00tA20tpbo","live_secret_key":"1Lu9O97LF793EUCn0zzzZ0NvQlZlOFZ2MHg2ZXViSjl4eWxPUFJGTGJDRVllUmx3M1JoTHZpWWpJYUxCOTRxSjZtR3VEZGk3Z1BVZUV5elhYR3cyWDgxbEZZRkhYT1Nxd3ROTU1UY0hXMlF5RXVrbGNObXRGWDVBNW9vTmYrVUM3SHIwZ3VOL2hUY2J0cTVFZHUvb2E0SndhTE5rZnc3TU83MEM4bE96TXdGL05zZGJTcnJhSkV6NEZPSWdHelUwc0pRS2YxZm5vaUpjM2REeE5RSmN3OWZzaVZnWklpdGU4WDdSMzJNM3E3V3dWa0Q0QlRkSQ==","live_webhook_secret":"","payment_mode":"live","checkout_mode":"onsite","live_is_encrypted":"yes","test_is_encrypted":"no","secure":"yes","live_connect_hash":"3a1623885fe019553bbd8c681fa58072","live_account_id":"acct_1FB4mCJV4TDq0RwS"}',
		'created_at' => '2026-03-11 00:41:53',
		'updated_at' => '2026-03-11 00:51:26',
	),
	array(
		'id' => '%d', 'object_type' => '%s', 'object_id' => '%d', 'meta_key' => '%s', 'meta_value' => '%s', 'created_at' => '%s', 'updated_at' => '%s',
	)
);
ecomcine_seed_replace(
	$tables['fct_meta'],
	array(
		'id' => 2,
		'object_type' => 'option',
		'object_id' => null,
		'meta_key' => 'fluent_cart_payment_settings_offline_payment',
		'meta_value' => '{"is_active":"yes","payment_mode":"live","checkout_label":"Cash"}',
		'created_at' => '2026-03-11 00:52:30',
		'updated_at' => '2026-03-11 00:52:38',
	),
	array(
		'id' => '%d', 'object_type' => '%s', 'object_id' => '%d', 'meta_key' => '%s', 'meta_value' => '%s', 'created_at' => '%s', 'updated_at' => '%s',
	)
);

$counts = array(
	'customers' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['customers']}" ),
	'orders' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['orders']}" ),
	'order_items' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['order_items']}" ),
	'subscriptions' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['subscriptions']}" ),
	'licenses' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['licenses']}" ),
	'fct_meta' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['fct_meta']}" ),
);

WP_CLI::success( 'FluentCart clone fixtures imported from WebmasterOS baseline.' );
WP_CLI::log( sprintf( 'Counts: customers=%d orders=%d order_items=%d subscriptions=%d licenses=%d fct_meta=%d', $counts['customers'], $counts['orders'], $counts['order_items'], $counts['subscriptions'], $counts['licenses'], $counts['fct_meta'] ) );
WP_CLI::log( 'License keys:' );
foreach ( $licenses as $license ) {
	WP_CLI::log( sprintf( '- %s (product_id=%d variation_id=%d limit=%d status=%s)', $license['license_key'], (int) $license['product_id'], (int) $license['variation_id'], (int) $license['limit'], (string) $license['status'] ) );
}
