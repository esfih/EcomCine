<?php
/**
 * Combined parity runner — all three adapter groups.
 *
 * Correctly invokes:
 *   TAP_Parity_Check   (instance method, returns array)
 *   TVBM_Parity_Check  (instance method, returns array)
 *   THO_Parity_Check   (static method, prints own output, exits on fail)
 *
 * Run via catalog command: parity.check.all
 *   ./scripts/wp.sh php scripts/run-parity-checks.php
 *
 * Exit codes:
 *   0 — all groups PASS
 *   1 — one or more groups FAIL
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "[ERROR] Must run inside WordPress context via wp.sh\n";
	exit( 1 );
}

$GLOBALS['_parity_failures'] = 0;

// ---------------------------------------------------------------------------
// Helper: print array-based parity report (TAP and TVBM)
// ---------------------------------------------------------------------------

function parity_print_report( string $prefix, array $report ): void {
	$pass  = $report['pass'] ?? 0;
	$fail  = $report['fail'] ?? 0;
	$total = $pass + $fail;

	foreach ( $report['results'] ?? [] as $r ) {
		$icon = $r['pass'] ? '[PASS]' : '[FAIL]';
		echo "{$icon} {$r['label']}\n";
	}

	if ( $fail > 0 ) {
		echo "\n{$prefix} PARITY: {$pass}/{$total} — {$fail} FAILURE(S)\n";
		$GLOBALS['_parity_failures'] += $fail;
	} else {
		echo "\n{$prefix} PARITY: {$pass}/{$total} PASS\n";
	}
}

// ---------------------------------------------------------------------------
// TAP
// ---------------------------------------------------------------------------

echo "\n=== TAP Parity Check ===\n";

if ( ! class_exists( 'TAP_Parity_Check' ) ) {
	echo "[SKIP] TAP_Parity_Check class not loaded — plugin not active?\n";
	$GLOBALS['_parity_failures']++;
} else {
	$report = ( new TAP_Parity_Check() )->run();
	parity_print_report( 'TAP', $report );
}

// ---------------------------------------------------------------------------
// TVBM
// ---------------------------------------------------------------------------

echo "\n=== TVBM Parity Check ===\n";

if ( ! class_exists( 'TVBM_Parity_Check' ) ) {
	echo "[SKIP] TVBM_Parity_Check class not loaded — plugin not active?\n";
	$GLOBALS['_parity_failures']++;
} else {
	$report = ( new TVBM_Parity_Check() )->run();
	parity_print_report( 'TVBM', $report );
}

// ---------------------------------------------------------------------------
// THO — load parity file manually (excluded from normal theme runtime)
// then call static run() which prints its own output and exits on failure
// ---------------------------------------------------------------------------

echo "\n=== THO Parity Check ===\n";

$tho_parity_file = ABSPATH . 'wp-content/themes/ecomcine-base/includes/parity/class-parity-check.php';

if ( ! file_exists( $tho_parity_file ) ) {
	echo "[SKIP] THO parity file not found at: {$tho_parity_file}\n";
	$GLOBALS['_parity_failures']++;
} elseif ( class_exists( 'THO_Parity_Check' ) || ( require_once $tho_parity_file ) ) {
	// Pre-flight: if TAP or TVBM already failed, save the count before THO exits.
	// THO_Parity_Check::run() calls exit(1) on failure or returns on success.
	// We capture pre-existing failures so we don't exit 0 even if THO passes.
	$pre_tho_failures = $GLOBALS['_parity_failures'];

	THO_Parity_Check::run();

	// Reached only if THO passed (no exit(1)).
	if ( $pre_tho_failures > 0 ) {
		echo "\nPrevious groups had {$pre_tho_failures} failure(s) — overall result: FAIL\n";
		exit( 1 );
	}
} else {
	echo "[ERROR] Could not load THO parity file.\n";
	$GLOBALS['_parity_failures']++;
}

// ---------------------------------------------------------------------------
// Final summary (reached only when THO passes via return, not exit)
// ---------------------------------------------------------------------------

$total_failures = $GLOBALS['_parity_failures'];

echo "\n=== Combined Summary ===\n";
if ( 0 === $total_failures ) {
	echo "ALL PARITY CHECKS PASS\n";
	exit( 0 );
} else {
	echo "PARITY FAILURES: {$total_failures} — see [FAIL] lines above\n";
	exit( 1 );
}
