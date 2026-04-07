#!/usr/bin/env php
<?php
/**
 * Extract WMOS FluentCart billing parity data from a SQL dump.
 *
 * Usage:
 *   php scripts/licensing/extract-wmos-billing-seed.php <source-sql> <output-json>
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "This script must run in CLI.\n");
	exit(1);
}

$source = $argv[1] ?? '/root/dev/WebMasterOS-main/WebMasterOS-DB.sql';
$output = $argv[2] ?? '/tmp/ecomcine-wmos-billing-seed.json';

if (!is_file($source)) {
	fwrite(STDERR, "Source SQL file not found: {$source}\n");
	exit(1);
}

$sql = file_get_contents($source);
if ($sql === false) {
	fwrite(STDERR, "Failed to read source SQL file.\n");
	exit(1);
}

$catalog = [];
$activationsRaw = '';

if (preg_match_all('/\(\d+,\s*(\d+),\s*NULL,\s*\'license_settings\',\s*\'((?:\\\\\'|[^\'])*)\'/m', $sql, $licenseMatches, PREG_SET_ORDER)) {
	foreach ($licenseMatches as $match) {
		$productId = (int) $match[1];
		$payloadJson = stripcslashes($match[2]);
		$payload = json_decode($payloadJson, true);
		if (!is_array($payload)) {
			continue;
		}

		$variationId = 0;
		$activationLimit = 1;
		if (isset($payload['variations']) && is_array($payload['variations'])) {
			foreach ($payload['variations'] as $variation) {
				if (!is_array($variation)) {
					continue;
				}
				$variationId = (int)($variation['variation_id'] ?? 0);
				$activationLimit = max(1, (int)($variation['activation_limit'] ?? $variation['activations_limit'] ?? 1));
				break;
			}
		}

		$catalog[(string) $productId] = [
			'product_id' => $productId,
			'variation_id' => $variationId,
			'max_site_activations' => $activationLimit,
			'license_settings' => $payload,
		];
	}
}

if (preg_match_all('/\(\d+,\s*(\d+),\s*NULL,\s*\'wmos_allowances_v1\',\s*\'((?:\\\\\'|[^\'])*)\'/m', $sql, $allowanceMatches, PREG_SET_ORDER)) {
	foreach ($allowanceMatches as $match) {
		$productId = (int) $match[1];
		$payloadJson = stripcslashes($match[2]);
		$payload = json_decode($payloadJson, true);
		if (!is_array($payload)) {
			continue;
		}

		if (!isset($catalog[(string) $productId])) {
			$catalog[(string) $productId] = [
				'product_id' => $productId,
				'variation_id' => 0,
				'max_site_activations' => 1,
			];
		}

		$catalog[(string) $productId]['wmos_allowances_v1'] = $payload;
	}
}

if (preg_match('/\(\d+,\s*\'wmos_cp_activations\',\s*\'((?:\\\\\'|[^\'])*)\',\s*\'[^\']*\'\)/m', $sql, $activationMatch)) {
	$activationsRaw = stripcslashes($activationMatch[1]);
}

$knownPlans = [
	'2566' => 'freemium',
	'2569' => 'solo',
	'2571' => 'maestro',
	'2573' => 'agency',
];

$offers = [];
foreach ($knownPlans as $productId => $plan) {
	$row = $catalog[$productId] ?? null;
	if (!is_array($row)) {
		continue;
	}

	$offers[$plan] = [
		'plan' => $plan,
		'product_id' => (int)($row['product_id'] ?? 0),
		'variation_id' => (int)($row['variation_id'] ?? 0),
		'max_site_activations' => (int)($row['max_site_activations'] ?? 1),
		'allowances' => is_array($row['wmos_allowances_v1']['defaults'] ?? null) ? $row['wmos_allowances_v1']['defaults'] : [],
	];
}

$seed = [
	'generated_at' => gmdate('c'),
	'source_sql' => $source,
	'offers' => $offers,
	'activations_serialized' => $activationsRaw,
];

$json = json_encode($seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($json)) {
	fwrite(STDERR, "Failed to encode seed JSON.\n");
	exit(1);
}

if (file_put_contents($output, $json) === false) {
	fwrite(STDERR, "Failed to write output file: {$output}\n");
	exit(1);
}

fwrite(STDOUT, "WMOS billing seed extracted to {$output}\n");
