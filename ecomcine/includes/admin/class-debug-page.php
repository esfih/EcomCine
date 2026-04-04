<?php
/**
 * EcomCine Debugging admin page.
 *
 * Adds a "Debugging" submenu under the EcomCine admin menu.
 * Provides a comprehensive health report that admins can copy and send to support.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Debug_Page {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_submenu' ), 25 );
		add_action( 'wp_ajax_ecomcine_debug_report', array( __CLASS__, 'ajax_debug_report' ) );
	}

	public static function register_submenu() {
		add_submenu_page(
			'ecomcine-settings',
			__( 'Debugging', 'ecomcine' ),
			__( 'Debugging', 'ecomcine' ),
			'manage_options',
			'ecomcine-debugging',
			array( __CLASS__, 'render_page' )
		);
	}

	// ── Data collectors ───────────────────────────────────────────────────────

	/**
	 * Collect full diagnostic report as a plain PHP array (JSON-serializable).
	 */
	public static function collect_report(): array {
		$report = array();

		// ── 1. Environment ────────────────────────────────────────────────────
		$report['environment'] = array(
			'generated_at'    => gmdate( 'Y-m-d H:i:s T' ),
			'wp_version'      => get_bloginfo( 'version' ),
			'php_version'     => PHP_VERSION,
			'php_memory_limit' => ini_get( 'memory_limit' ),
			'ecomcine_version' => defined( 'ECOMCINE_VERSION' ) ? ECOMCINE_VERSION : 'unknown',
			'site_url'        => home_url(),
			'is_multisite'    => is_multisite(),
			'wp_debug'        => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_log'    => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
		);

		// ── 2. Active plugins ─────────────────────────────────────────────────
		$active_plugins = get_option( 'active_plugins', array() );
		$plugin_info    = array();
		foreach ( (array) $active_plugins as $plugin_file ) {
			if ( ! is_string( $plugin_file ) ) {
				continue;
			}
			$data          = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, false );
			$plugin_info[] = array(
				'file'    => $plugin_file,
				'name'    => $data['Name'] ?? $plugin_file,
				'version' => $data['Version'] ?? '?',
			);
		}
		$report['active_plugins'] = $plugin_info;

		// ── 3. Active theme ───────────────────────────────────────────────────
		$theme = wp_get_theme();
		$report['active_theme'] = array(
			'name'      => $theme->get( 'Name' ),
			'version'   => $theme->get( 'Version' ),
			'stylesheet' => $theme->get_stylesheet(),
			'template'  => $theme->get_template(),
		);

		// ── 4. EcomCine settings ──────────────────────────────────────────────
		$report['ecomcine_settings'] = array();
		if ( class_exists( 'EcomCine_Admin_Settings', false ) ) {
			$settings = EcomCine_Admin_Settings::get_settings();
			$report['ecomcine_settings']['runtime_mode'] = $settings['runtime_mode'] ?? 'unknown';
			$report['ecomcine_settings']['features']     = $settings['features'] ?? array();
		}

		// ── 5. Adapter snapshot ───────────────────────────────────────────────
		$report['adapters'] = array();
		if ( function_exists( 'ecomcine_get_runtime_adapter_snapshot' ) ) {
			$snap = ecomcine_get_runtime_adapter_snapshot();
			$report['adapters'] = is_array( $snap ) ? $snap : array();
		}

		// ── 6. Plugin capabilities ────────────────────────────────────────────
		$report['plugin_capabilities'] = array();
		if ( class_exists( 'EcomCine_Plugin_Capability', false ) ) {
			$report['plugin_capabilities'] = EcomCine_Plugin_Capability::snapshot();
		}

		// ── 7. Key functions & classes ────────────────────────────────────────
		$key_fns = array(
			'tm_store_ui_is_person_live',
			'tm_vendor_completeness',
			'ecomcine_get_person_info',
			'ecomcine_get_geo',
			'ecomcine_is_person_enabled',
			'EcomCine_Person_Category_Registry',
			'TMP_WP_Vendor_CPT',
			'EcomCine_Demo_Importer',
		);
		$fn_status = array();
		foreach ( $key_fns as $fn ) {
			$fn_status[ $fn ] = function_exists( $fn ) || class_exists( $fn, false ) ? 'present' : 'MISSING';
		}
		$report['key_functions'] = $fn_status;

		// ── 8. Demo manifest ──────────────────────────────────────────────────
		$report['demo_manifest'] = array( 'status' => 'skipped' );
		if ( class_exists( 'EcomCine_Demo_Importer', false ) ) {
			$manifest = EcomCine_Demo_Importer::fetch_manifest();
			if ( is_array( $manifest ) ) {
				$report['demo_manifest'] = array(
					'status' => 'ok',
					'packs'  => array_map( function( $p ) {
						return array(
							'id'      => $p['id'] ?? '?',
							'version' => $p['version'] ?? '?',
							'zip_url' => $p['zip_url'] ?? '?',
						);
					}, (array) ( $manifest['packs'] ?? array() ) ),
				);
			} else {
				$report['demo_manifest'] = array( 'status' => 'fetch_error' );
			}
		}

		// ── 9. Vendor / talent summary ────────────────────────────────────────
		$vendor_rows = array();
		$live_count  = 0;
		$geo_count   = 0;
		$l1_count    = 0;

		$users = get_users( array(
			'role__in' => array( 'seller', 'ecomcine_person' ),
			'number'   => 200,
			'fields'   => array( 'ID', 'display_name' ),
		) );

		foreach ( $users as $u ) {
			$uid     = (int) $u->ID;
			$name    = (string) $u->display_name;

			$is_live = function_exists( 'tm_store_ui_is_person_live' )
				? tm_store_ui_is_person_live( $uid )
				: null;

			if ( $is_live ) {
				$live_count++;
			}

			// Completeness.
			$comp_published = null;
			$comp_l1        = null;
			$comp_missing   = array();
			if ( function_exists( 'tm_vendor_completeness' ) ) {
				$comp = tm_vendor_completeness( $uid );
				if ( is_array( $comp ) ) {
					$comp_published = ! empty( $comp['published'] );
					$comp_l1        = ! empty( $comp['level1']['complete'] );
					$comp_missing   = is_array( $comp['level1']['missing'] ?? null )
						? $comp['level1']['missing']
						: array();
					if ( $comp_l1 ) {
						$l1_count++;
					}
				}
			}

			// Geo.
			$geo        = function_exists( 'ecomcine_get_geo' ) ? ecomcine_get_geo( $uid ) : array();
			$has_lat    = ! empty( $geo['lat'] );
			$has_lng    = ! empty( $geo['lng'] );
			$has_addr   = ! empty( $geo['address'] );
			if ( $has_lat && $has_lng ) {
				$geo_count++;
			}

			// Profile URL.
			$profile_url = function_exists( 'ecomcine_get_person_url' ) ? ecomcine_get_person_url( $uid ) : '';

			$vendor_rows[] = array(
				'id'           => $uid,
				'name'         => $name,
				'is_live'      => $is_live,
				'published'    => $comp_published,
				'l1_complete'  => $comp_l1,
				'l1_missing'   => $comp_missing,
				'has_lat'      => $has_lat,
				'has_lng'      => $has_lng,
				'has_addr'     => $has_addr,
				'geo_address'  => $has_addr ? $geo['address'] : '',
				'profile_url'  => $profile_url,
			);
		}

		$report['vendors'] = array(
			'total'      => count( $vendor_rows ),
			'live_count' => $live_count,
			'l1_count'   => $l1_count,
			'geo_count'  => $geo_count,
			'rows'       => $vendor_rows,
		);

		// ── 10. EcomCine DB tables ────────────────────────────────────────────
		global $wpdb;
		$ec_tables = array(
			$wpdb->prefix . 'ecomcine_categories',
			$wpdb->prefix . 'ecomcine_person_categories',
		);
		$table_status = array();
		foreach ( $ec_tables as $tbl ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$tbl}'" );
			if ( $exists ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$tbl}`" );
				$table_status[ $tbl ] = array( 'exists' => true, 'rows' => $count );
			} else {
				$table_status[ $tbl ] = array( 'exists' => false, 'rows' => 0 );
			}
		}
		$report['db_tables'] = $table_status;

		// ── 11. WP debug log snippet (last 50 lines) ──────────────────────────
		$report['debug_log'] = array( 'status' => 'not_enabled' );
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_path = is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : ABSPATH . 'wp-content/debug.log';
			if ( file_exists( $log_path ) && is_readable( $log_path ) ) {
				$lines = file( $log_path );
				if ( is_array( $lines ) ) {
					// EcomCine and PHP errors only — last 60 relevant lines.
					$ecomcine_lines = array_filter(
						$lines,
						function( $l ) {
							return false !== stripos( $l, 'ecomcine' )
								|| false !== stripos( $l, 'tm_' )
								|| false !== stripos( $l, 'PHP Fatal' )
								|| false !== stripos( $l, 'PHP Warning' )
								|| false !== stripos( $l, 'PHP Notice' );
						}
					);
					$report['debug_log'] = array(
						'status' => 'ok',
						'lines'  => array_slice( array_values( $ecomcine_lines ), -60 ),
					);
				}
			} else {
				$report['debug_log'] = array( 'status' => 'file_not_found', 'path' => $log_path );
			}
		}

		return $report;
	}

	// ── AJAX handler ──────────────────────────────────────────────────────────

	public static function ajax_debug_report() {
		check_ajax_referer( 'ecomcine_debug_report', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.', 403 );
		}
		$report = self::collect_report();
		wp_send_json_success( $report );
	}

	// ── Render ────────────────────────────────────────────────────────────────

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ecomcine' ) );
		}

		$nonce  = wp_create_nonce( 'ecomcine_debug_report' );
		$report = self::collect_report();

		$env     = $report['environment'];
		$vendors = $report['vendors'];
		?>
		<div class="wrap ecomcine-debug-wrap" style="max-width:1200px;">
			<h1 style="display:flex;align-items:center;gap:12px;">
				<?php esc_html_e( 'EcomCine — Debugging', 'ecomcine' ); ?>
				<span style="font-size:13px;font-weight:400;color:#777;"><?php echo esc_html( $env['generated_at'] ); ?></span>
			</h1>
			<p class="description" style="margin-bottom:20px;">
				<?php esc_html_e( 'This page compiles a full diagnostic snapshot of your EcomCine installation. Use the "Copy Report" button to share it with support.', 'ecomcine' ); ?>
			</p>

			<p>
				<button id="ecomcine-copy-report" class="button button-primary" style="margin-right:10px;">
					&#128203; <?php esc_html_e( 'Copy Report (JSON)', 'ecomcine' ); ?>
				</button>
				<button id="ecomcine-refresh-report" class="button">
					&#8635; <?php esc_html_e( 'Refresh', 'ecomcine' ); ?>
				</button>
				<span id="ecomcine-copy-status" style="margin-left:10px;color:green;display:none;"><?php esc_html_e( 'Copied!', 'ecomcine' ); ?></span>
			</p>

			<!-- Section: Environment -->
			<h2 style="border-bottom:1px solid #ddd;padding-bottom:6px;"><?php esc_html_e( '1. Environment', 'ecomcine' ); ?></h2>
			<table class="widefat striped" style="margin-bottom:24px;width:auto;min-width:500px;">
				<tbody>
					<?php
					$env_labels = array(
						'ecomcine_version' => 'EcomCine version',
						'wp_version'       => 'WordPress version',
						'php_version'      => 'PHP version',
						'php_memory_limit' => 'PHP memory limit',
						'site_url'         => 'Site URL',
						'wp_debug'         => 'WP_DEBUG',
						'wp_debug_log'     => 'WP_DEBUG_LOG',
					);
					foreach ( $env_labels as $key => $label ) :
						$val = $env[ $key ] ?? '';
						if ( is_bool( $val ) ) {
							$val = $val ? 'true' : 'false';
						}
					?>
					<tr>
						<th style="width:200px;text-align:left;"><?php echo esc_html( $label ); ?></th>
						<td><code><?php echo esc_html( (string) $val ); ?></code></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Section: Key functions -->
			<h2 style="border-bottom:1px solid #ddd;padding-bottom:6px;"><?php esc_html_e( '2. Key Functions & Classes', 'ecomcine' ); ?></h2>
			<table class="widefat striped" style="margin-bottom:24px;width:auto;min-width:500px;">
				<tbody>
					<?php foreach ( (array) ( $report['key_functions'] ?? array() ) as $fn => $status ) : ?>
					<tr>
						<th style="width:340px;text-align:left;"><code><?php echo esc_html( $fn ); ?></code></th>
						<td>
							<?php if ( 'present' === $status ) : ?>
								<span style="color:green;">&#10003; present</span>
							<?php else : ?>
								<span style="color:red;font-weight:600;">&#10007; MISSING</span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Section: EcomCine settings -->
			<h2 style="border-bottom:1px solid #ddd;padding-bottom:6px;"><?php esc_html_e( '3. EcomCine Configuration', 'ecomcine' ); ?></h2>
			<table class="widefat striped" style="margin-bottom:24px;width:auto;min-width:500px;">
				<tbody>
					<?php
					$ec_settings = $report['ecomcine_settings'];
					?>
					<tr>
						<th style="width:200px;text-align:left;"><?php esc_html_e( 'Runtime mode', 'ecomcine' ); ?></th>
						<td><code><?php echo esc_html( $ec_settings['runtime_mode'] ?? 'unknown' ); ?></code></td>
					</tr>
					<?php
					foreach ( (array) ( $ec_settings['features'] ?? array() ) as $feat => $enabled ) :
						$lbl = esc_html( 'Feature: ' . $feat );
					?>
					<tr>
						<th style="text-align:left;"><?php echo $lbl; ?></th>
						<td><?php echo $enabled ? '<span style="color:green;">enabled</span>' : '<span style="color:#aaa;">disabled</span>'; ?></td>
					</tr>
					<?php endforeach; ?>
					<?php foreach ( (array) ( $report['adapters'] ?? array() ) as $adapter_key => $adapter_val ) : ?>
					<tr>
						<th style="text-align:left;">Adapter: <?php echo esc_html( $adapter_key ); ?></th>
						<td><code><?php echo is_string( $adapter_val ) ? esc_html( $adapter_val ) : esc_html( wp_json_encode( $adapter_val ) ); ?></code></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Section: DB Tables -->
			<h2 style="border-bottom:1px solid #ddd;padding-bottom:6px;"><?php esc_html_e( '4. EcomCine DB Tables', 'ecomcine' ); ?></h2>
			<table class="widefat striped" style="margin-bottom:24px;width:auto;min-width:500px;">
				<thead><tr><th style="text-align:left;width:340px;">Table</th><th>Exists</th><th>Rows</th></tr></thead>
				<tbody>
					<?php foreach ( (array) ( $report['db_tables'] ?? array() ) as $tbl => $info ) : ?>
					<tr>
						<td><code><?php echo esc_html( $tbl ); ?></code></td>
						<td>
							<?php if ( ! empty( $info['exists'] ) ) : ?>
								<span style="color:green;">&#10003;</span>
							<?php else : ?>
								<span style="color:red;font-weight:600;">&#10007; MISSING</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) ( $info['rows'] ?? 0 ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Section: Demo manifest -->
			<h2 style="border-bottom:1px solid #ddd;padding-bottom:6px;"><?php esc_html_e( '5. Demo Manifest', 'ecomcine' ); ?></h2>
			<?php $dm = $report['demo_manifest']; ?>
			<?php if ( 'ok' === $dm['status'] ) : ?>
				<table class="widefat striped" style="margin-bottom:24px;width:auto;min-width:500px;">
					<thead><tr><th>Pack ID</th><th>Version</th><th>Zip URL</th></tr></thead>
					<tbody>
						<?php foreach ( (array) ( $dm['packs'] ?? array() ) as $pack ) : ?>
						<tr>
							<td><?php echo esc_html( $pack['id'] ); ?></td>
							<td><?php echo esc_html( $pack['version'] ); ?></td>
							<td><a href="<?php echo esc_url( $pack['zip_url'] ); ?>" target="_blank"><?php echo esc_html( $pack['zip_url'] ); ?></a></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p style="color:orange;"><?php echo esc_html( 'Manifest status: ' . $dm['status'] ); ?></p>
			<?php endif; ?>

			<!-- Section: Vendor health -->
			<h2 style="border-bottom:1px solid #ddd;padding-bottom:6px;"><?php esc_html_e( '6. Vendor Health', 'ecomcine' ); ?></h2>
			<p>
				<?php
				printf(
					esc_html__( 'Total: %1$d &nbsp;|&nbsp; Live (is_live=true): %2$d &nbsp;|&nbsp; L1 complete: %3$d &nbsp;|&nbsp; Has geo: %4$d', 'ecomcine' ),
					$vendors['total'],
					$vendors['live_count'],
					$vendors['l1_count'],
					$vendors['geo_count']
				);
				?>
			</p>
			<table class="widefat striped" style="margin-bottom:24px;">
				<thead>
					<tr>
						<th>ID</th>
						<th><?php esc_html_e( 'Name', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Live', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Published', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'L1 Complete', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Geo', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Location', 'ecomcine' ); ?></th>
						<th><?php esc_html_e( 'Missing L1 Fields', 'ecomcine' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( (array) ( $vendors['rows'] ?? array() ) as $row ) :
						$live_ok = $row['is_live'] === true;
						$l1_ok   = $row['l1_complete'] === true;
						$geo_ok  = $row['has_lat'] && $row['has_lng'];
						$row_style = ! $live_ok ? 'background:#fff3cd;' : '';
					?>
					<tr style="<?php echo esc_attr( $row_style ); ?>">
						<td><?php echo esc_html( (string) $row['id'] ); ?></td>
						<td>
							<?php if ( ! empty( $row['profile_url'] ) ) : ?>
								<a href="<?php echo esc_url( $row['profile_url'] ); ?>" target="_blank"><?php echo esc_html( $row['name'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $row['name'] ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo self::render_bool( $row['is_live'] ); ?></td>
						<td><?php echo self::render_bool( $row['published'] ); ?></td>
						<td><?php echo self::render_bool( $row['l1_complete'] ); ?></td>
						<td><?php echo $geo_ok ? '<span style="color:green;">&#10003;</span>' : '<span style="color:red;">&#10007;</span>'; ?></td>
						<td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $row['geo_address'] ); ?>"><?php echo esc_html( $row['geo_address'] ?: '—' ); ?></td>
						<td style="font-size:11px;color:#c00;">
							<?php echo esc_html( implode( ', ', $row['l1_missing'] ) ?: '—' ); ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Section: Active Plugins -->
			<h2 style="border-bottom:1px solid #ddd;padding-bottom:6px;"><?php esc_html_e( '7. Active Plugins', 'ecomcine' ); ?></h2>
			<table class="widefat striped" style="margin-bottom:24px;">
				<thead><tr><th>Plugin</th><th>Version</th><th>File</th></tr></thead>
				<tbody>
					<?php foreach ( (array) ( $report['active_plugins'] ?? array() ) as $pl ) : ?>
					<tr>
						<td><?php echo esc_html( $pl['name'] ); ?></td>
						<td><?php echo esc_html( $pl['version'] ); ?></td>
						<td style="font-size:11px;color:#777;"><code><?php echo esc_html( $pl['file'] ); ?></code></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Section: Debug log -->
			<?php if ( 'ok' === ( $report['debug_log']['status'] ?? '' ) ) : ?>
			<h2 style="border-bottom:1px solid #ddd;padding-bottom:6px;"><?php esc_html_e( '8. Debug Log (EcomCine Entries)', 'ecomcine' ); ?></h2>
			<pre style="background:#1e1e1e;color:#d4d4d4;padding:16px;overflow:auto;max-height:300px;font-size:11px;line-height:1.5;border-radius:4px;margin-bottom:24px;"><?php
				foreach ( (array) ( $report['debug_log']['lines'] ?? array() ) as $line ) {
					echo esc_html( rtrim( (string) $line ) ) . "\n";
				}
			?></pre>
			<?php endif; ?>

			<!-- Section: Raw JSON -->
			<h2 style="border-bottom:1px solid #ddd;padding-bottom:6px;" id="ecomcine-raw-json-heading"><?php esc_html_e( 'Raw JSON Report', 'ecomcine' ); ?> <small style="font-weight:400;font-size:13px;">(<?php esc_html_e( 'for support', 'ecomcine' ); ?>)</small></h2>
			<textarea id="ecomcine-debug-raw" readonly
				style="width:100%;height:200px;font-family:monospace;font-size:11px;background:#fafafa;padding:10px;resize:vertical;border:1px solid #ccc;"
				><?php echo esc_textarea( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></textarea>

		</div><!-- .ecomcine-debug-wrap -->

		<script>
		(function(){
			var copyBtn    = document.getElementById('ecomcine-copy-report');
			var copyStatus = document.getElementById('ecomcine-copy-status');
			var rawTA      = document.getElementById('ecomcine-debug-raw');

			if (copyBtn && rawTA) {
				copyBtn.addEventListener('click', function() {
					rawTA.select();
					var text = rawTA.value;
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(text).then(function() {
							if (copyStatus) { copyStatus.style.display='inline'; setTimeout(function(){ copyStatus.style.display='none'; }, 2500); }
						});
					} else {
						document.execCommand('copy');
						if (copyStatus) { copyStatus.style.display='inline'; setTimeout(function(){ copyStatus.style.display='none'; }, 2500); }
					}
				});
			}

			var refreshBtn = document.getElementById('ecomcine-refresh-report');
			if (refreshBtn) {
				refreshBtn.addEventListener('click', function() {
					window.location.reload();
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Render a boolean value as a coloured tick/cross.
	 */
	private static function render_bool( $val ): string {
		if ( true === $val ) {
			return '<span style="color:green;">&#10003;</span>';
		}
		if ( false === $val ) {
			return '<span style="color:red;">&#10007;</span>';
		}
		return '<span style="color:#aaa;">&mdash;</span>';
	}
}
