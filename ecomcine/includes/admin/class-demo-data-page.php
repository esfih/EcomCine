<?php
/**
 * EcomCine Demo Data admin page.
 *
 * Adds a "Demo Data" submenu under the EcomCine admin menu.
 *
 * Shows available demo packs fetched from the remote manifest at
 * ECOMCINE_DEMO_MANIFEST_URL (https://ecomcine.com/demos/manifest.json).
 * Each pack can be imported with one click via AJAX.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Demo_Data_Page {

	public static function init() {
		// Debug: Log that init is being called - write to WordPress uploads directory
		$log_file = ABSPATH . 'wp-content/uploads/ecomcine_init_debug.log';
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - EcomCine_Demo_Data_Page::init called\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		add_action( 'admin_menu', array( __CLASS__, 'register_submenu' ), 20 );
		
		add_action( 'wp_ajax_ecomcine_import_demo_remote', array( __CLASS__, 'ajax_import_demo_remote' ) );
		add_action( 'wp_ajax_nopriv_ecomcine_import_demo_remote', array( __CLASS__, 'ajax_import_demo_remote' ) );
		add_action( 'wp_ajax_ecomcine_clear_demo_cache', array( __CLASS__, 'ajax_clear_demo_cache' ) );
		add_action( 'wp_ajax_nopriv_ecomcine_clear_demo_cache', array( __CLASS__, 'ajax_clear_demo_cache' ) );
		add_action( 'wp_ajax_ecomcine_talent_debug', array( __CLASS__, 'ajax_talent_debug' ) );
	}

	public static function register_submenu() {
		add_submenu_page(
			'ecomcine-settings',
			__( 'Demo Data', 'ecomcine' ),
			__( 'Demo Data', 'ecomcine' ),
			'manage_options',
			'ecomcine-demo-data',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ecomcine' ) );
		}

		$nonce = wp_create_nonce( 'ecomcine_demo_import' );

		// ── Remote manifest ────────────────────────────────────────────────────
		$manifest      = null;
		$manifest_error = '';
		if ( class_exists( 'EcomCine_Demo_Importer', false ) ) {
			$manifest = EcomCine_Demo_Importer::fetch_manifest();
			if ( null === $manifest ) {
				$manifest_error = sprintf(
					/* translators: %s URL */
					__( 'Could not fetch demo manifest from %s. Check the server connection or configure the URL via the ecomcine_demo_manifest_url filter.', 'ecomcine' ),
					esc_html( ECOMCINE_DEMO_MANIFEST_URL )
				);
			}
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'EcomCine — Demo Data', 'ecomcine' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Import a demo content pack to populate your site with sample talent profiles, media and categories.', 'ecomcine' ); ?>
			</p>

			<?php // Expose diagnostic nonce for browser-console debugging (admin only). ?>
			<script>window.ecomcineDiagNonce = '<?php echo esc_js( wp_create_nonce( 'ecomcine_diag' ) ); ?>';</script>
			<p style="font-size:11px;color:#999;margin:0 0 16px;">
				<?php esc_html_e( 'Diagnostic nonce injected for console debugging (visible to admins only).', 'ecomcine' ); ?>
			</p>

			<?php if ( $manifest_error ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( $manifest_error ); ?></p></div>
			<?php endif; ?>

			<?php // ── Remote demo packs ──────────────────────────────────────────────── ?>
			<?php if ( is_array( $manifest ) && ! empty( $manifest['packs'] ) ) : ?>
				<div style="display:flex;justify-content:space-between;align-items:center;margin:12px 0;">
					<h2 style="margin:0;"><?php esc_html_e( 'Available Demo Packs', 'ecomcine' ); ?></h2>
					<div style="display:flex;gap:8px;">
						<button class="button" id="ecomcine-check-updates-btn" style="margin:0;">
							<?php esc_html_e( 'Check for Updates', 'ecomcine' ); ?>
						</button>
						<button class="button button-secondary" id="ecomcine-clear-cache-btn" style="margin:0;">
							<?php esc_html_e( 'Clear Cache', 'ecomcine' ); ?>
						</button>
					</div>
				</div>
				<div style="display:flex;flex-wrap:wrap;gap:20px;margin-top:12px;">
					<?php foreach ( $manifest['packs'] as $pack ) :
						$pack_name     = sanitize_text_field( $pack['name'] ?? '' );
						$pack_desc     = sanitize_text_field( $pack['description'] ?? '' );
						$pack_url      = esc_url_raw( $pack['zip_url'] ?? '' );
						$pack_count    = (int) ( $pack['vendor_count'] ?? 0 );
						$pack_size_mb  = (int) ( $pack['disk_size_mb'] ?? 0 );
						$pack_id       = sanitize_key( $pack['id'] ?? sanitize_title( $pack_name ) );
						if ( empty( $pack_url ) ) continue;
					?>
					<div class="ecomcine-demo-pack-card" style="border:1px solid #ccd0d4;border-radius:4px;padding:16px;max-width:280px;background:#fff;">
						<strong><?php echo esc_html( $pack_name ); ?></strong>
						<?php if ( $pack_desc ) : ?>
							<p style="margin:6px 0 10px;color:#555;font-size:13px;"><?php echo esc_html( $pack_desc ); ?></p>
						<?php endif; ?>
						<?php if ( $pack_count || $pack_size_mb ) : ?>
							<p style="margin:0 0 10px;font-size:12px;color:#777;">
								<?php if ( $pack_count ) : ?>
									<?php echo esc_html( sprintf( _n( '%d vendor profile', '%d vendor profiles', $pack_count, 'ecomcine' ), $pack_count ) ); ?>
								<?php endif; ?>
								<?php if ( $pack_count && $pack_size_mb ) : ?>&nbsp;&middot;&nbsp;<?php endif; ?>
								<?php if ( $pack_size_mb ) : ?>
									<?php echo esc_html( sprintf( __( '~%d MB disk space required', 'ecomcine' ), $pack_size_mb ) ); ?>
								<?php endif; ?>
							</p>
						<?php endif; ?>
						<button class="button button-primary ecomcine-import-remote-btn"
							data-pack-id="<?php echo esc_attr( $pack_id ); ?>"
							data-zip-url="<?php echo esc_url( $pack_url ); ?>"
							data-nonce="<?php echo esc_attr( $nonce ); ?>">
							<?php esc_html_e( 'Import', 'ecomcine' ); ?>
						</button>
						<span class="ecomcine-pack-status" data-pack-id="<?php echo esc_attr( $pack_id ); ?>" style="margin-left:8px;"></span>
					</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! is_array( $manifest ) || empty( $manifest['packs'] ) ) : ?>
				<div class="notice notice-info inline" style="margin-top:20px;">
					<p><?php esc_html_e( 'No demo packs available. Upload demo content to ecomcine.com/demos/ and update the manifest.', 'ecomcine' ); ?></p>
				</div>
			<?php endif; ?>

			<div id="ecomcine-remote-result" style="margin-top:20px;"></div>
		</div>

		<script>
		(function () {
			var ajaxUrl = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

			// ── Check for updates ────────────────────────────────────────────
			var checkUpdatesBtn = document.getElementById('ecomcine-check-updates-btn');
			if (checkUpdatesBtn) {
				checkUpdatesBtn.addEventListener('click', function () {
					var btn = this;
					btn.disabled = true;
					btn.textContent = <?php echo json_encode( __( 'Refreshing…', 'ecomcine' ) ); ?>;

					// Reload the page with cache-busting parameter
					location.reload(true);
				});
			}

			// ── Clear cache ────────────────────────────────────────────
			var clearCacheBtn = document.getElementById('ecomcine-clear-cache-btn');
			if (clearCacheBtn) {
				clearCacheBtn.addEventListener('click', function () {
					var btn = this;
					var originalText = btn.textContent;
					btn.disabled = true;
					btn.textContent = <?php echo json_encode( __( 'Clearing cache…', 'ecomcine' ) ); ?>;

					// Clear WordPress transients and reload
					var ajaxUrl = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
					fetch(ajaxUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: 'action=ecomcine_clear_demo_cache&nonce=<?php echo esc_js( wp_create_nonce( 'ecomcine_demo_import' ) ); ?>'
					})
					.then(function (r) { return r.json(); })
					.then(function (data) {
						if (data.success) {
							alert(<?php echo json_encode( __( 'Cache cleared! Reloading…', 'ecomcine' ) ); ?>);
							location.reload();
						} else {
							alert(data.data || <?php echo json_encode( __( 'Failed to clear cache.', 'ecomcine' ) ); ?>);
							btn.disabled = false;
							btn.textContent = originalText;
						}
					})
					.catch(function (err) {
						alert(<?php echo json_encode( __( 'Error clearing cache.', 'ecomcine' ) ); ?>);
						btn.disabled = false;
						btn.textContent = originalText;
					});
				});
			}

			// ── Remote pack import ────────────────────────────────────────────
			document.querySelectorAll('.ecomcine-import-remote-btn').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var packId  = btn.dataset.packId;
					var zipUrl  = btn.dataset.zipUrl;
					var nonce   = btn.dataset.nonce;
					var status  = document.querySelector('.ecomcine-pack-status[data-pack-id="' + packId + '"]');
					var resultEl = document.getElementById('ecomcine-remote-result');

					// Debug: Log what we're sending
					console.log('Import button clicked');
					console.log('packId:', packId);
					console.log('zipUrl:', zipUrl);
					console.log('nonce:', nonce);
					console.log('action: ecomcine_import_demo_remote');

					btn.disabled = true;
					if (status) status.textContent = <?php echo json_encode( __( 'Downloading…', 'ecomcine' ) ); ?>;

					var body = new URLSearchParams({
						action:  'ecomcine_import_demo_remote',
						nonce:   nonce,
						zip_url: zipUrl,
					});

					fetch(ajaxUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: body.toString(),
					})
					.then(function (r) { 
						// Debug: Log the raw response
						console.log('Demo import response status:', r.status);
						console.log('Demo import response headers:', r.headers);
						
						// Get text first, then parse
						return r.text().then(function(textData) {
							console.log('Demo import response text:', textData);
							
							// Debug: Check if text is empty
							if (!textData || textData.trim() === '') {
								console.error('Demo import: Empty response from server');
								throw new Error('Empty response from server');
							}
							
							// Debug: Check for PHP errors in response
							if (textData.indexOf('Warning:') !== -1 || textData.indexOf('Error:') !== -1) {
								console.error('Demo import: PHP error in response:', textData);
							}
							
							return JSON.parse(textData);
						});
					})
					.then(function (data) {
						console.log('Demo import parsed data:', data);
						btn.disabled = false;
						if (data.success) {
							var d = data.data;
							if (status) status.textContent = '✓ ' + d.imported + ' created, ' + d.updated + ' updated';
							var html = '<div class="notice notice-success inline"><p>'
								+ '<strong><?php esc_html_e( 'Done!', 'ecomcine' ); ?></strong> '
								+ d.imported + ' <?php esc_html_e( 'vendors created,', 'ecomcine' ); ?> '
								+ d.updated  + ' <?php esc_html_e( 'updated.', 'ecomcine' ); ?></p>';
							if (d.errors && d.errors.length) {
								html += '<ul style="margin-left:18px;list-style:disc;">';
								d.errors.forEach(function (e) { html += '<li>' + e + '</li>'; });
								html += '</ul>';
							}
							html += '</div>';
							resultEl.innerHTML = html;
						} else {
							if (status) status.textContent = '✗ <?php esc_html_e( 'Failed', 'ecomcine' ); ?>';
							resultEl.innerHTML = '<div class="notice notice-error inline"><p>'
								+ (data.data || '<?php esc_html_e( 'Import failed.', 'ecomcine' ); ?>') + '</p></div>';
						}
					})
					.catch(function (err) {
						console.error('Demo import error:', err);
						btn.disabled = false;
						if (status) status.textContent = '✗';
						resultEl.innerHTML = '<div class="notice notice-error inline"><p>' + err.message + '</p></div>';
					});
				});
			});

		})();
		</script>
		<?php
	}

	/** AJAX: remote zip import. */
	public static function ajax_import_demo_remote() {
		// ALWAYS send a response, even if something goes wrong
		// Ensure no output before JSON
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		
		// Disable error output to JSON
		@ini_set( 'display_errors', '0' );
		
		// Debug: Write to file for easy checking - use WordPress uploads directory
		$log_file = ABSPATH . 'wp-content/uploads/ecomcine_ajax_debug.log';
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - ajax_import_demo_remote called\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		// Check nonce - use false to prevent automatic wp_die()
		$nonce_valid = check_ajax_referer( 'ecomcine_demo_import', 'nonce', false );
		
		if ( ! $nonce_valid ) {
			$debug_msg = date( 'Y-m-d H:i:s' ) . " - Nonce check failed\n";
			@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
			wp_send_json_error( 'Invalid security token.' );
			return;
		}
		
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - Nonce check passed\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		// Debug: Log that we're proceeding
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - Proceeding with import\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		// Debug: Log the zip_url being used
		$debug_msg = date( 'Y-m-d H:i:s' ) . " - Zip URL: " . $zip_url . "\n";
		@file_put_contents( $log_file, $debug_msg, FILE_APPEND );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
			return;
		}
		
		if ( ! class_exists( 'EcomCine_Demo_Importer', false ) ) {
			wp_send_json_error( 'Demo importer class not loaded.' );
			return;
		}
		
		$zip_url = isset( $_POST['zip_url'] ) ? esc_url_raw( wp_unslash( $_POST['zip_url'] ) ) : '';
		if ( empty( $zip_url ) ) {
			wp_send_json_error( 'Missing zip_url parameter.' );
			return;
		}
		
		// Debug: Log the request
		error_log( 'Demo import request: zip_url=' . $zip_url );
		
		// Ensure error reporting is disabled to prevent output corruption
		$old_error_reporting = error_reporting( 0 );
		
		try {
			$result = EcomCine_Demo_Importer::run_remote( $zip_url );
			
			// Debug: Log the result
			error_log( 'Demo import result: ' . print_r( $result, true ) );
			
			// Restore error reporting
			error_reporting( $old_error_reporting );
			
			// If there are errors, send them properly
			if ( ! empty( $result['errors'] ) ) {
				wp_send_json_error( implode( '\n', $result['errors'] ) );
				return;
			}
			
			wp_send_json_success( $result );
			
		} catch ( Exception $e ) {
			// Restore error reporting
			error_reporting( $old_error_reporting );
			
			// Log the exception
			error_log( 'Demo import exception: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() );
			
			// Send error response
			wp_send_json_error( 'Import failed: ' . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler: clear demo data cache transients.
	 *
	 * Deletes cached manifest data to force fresh fetch from GitHub.
	 */
	public static function ajax_clear_demo_cache() {
		check_ajax_referer( 'ecomcine_demo_import', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.', 403 );
		}

		// Clear any cached manifest transients
		delete_transient( 'ecomcine_demo_manifest_cache' );
		delete_transient( 'ecomcine_demo_manifest_error' );

		wp_send_json_success( array( 'message' => 'Cache cleared' ) );
	}

	/**
	 * AJAX handler: talent-listing diagnostic.
	 *
	 * Traces every filter stage of tm_store_ui_collect_person_ids_for_listing()
	 * and returns a structured JSON report.  Admin-only.
	 *
	 * Call via browser console (logged-in admin):
	 *   fetch('/wp-admin/admin-ajax.php', {method:'POST', credentials:'include',
	 *     headers:{'Content-Type':'application/x-www-form-urlencoded'},
	 *     body:'action=ecomcine_talent_debug&nonce='+ecomcineDiagNonce})
	 *   .then(r=>r.json()).then(d=>console.log(JSON.stringify(d,null,2)));
	 */
	public static function ajax_talent_debug() {
		if ( ! check_ajax_referer( 'ecomcine_diag', 'nonce', false ) ) {
			wp_send_json_error( 'Bad nonce.', 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.', 403 );
		}

		$report = array();

		// ── Environment ───────────────────────────────────────────────────────
		$report['environment'] = array(
			'plugin_version'        => defined( 'ECOMCINE_VERSION' ) ? ECOMCINE_VERSION : 'unknown',
			'stored_version'        => get_option( 'ecomcine_version', 'none' ),
			'php_version'           => PHP_VERSION,
			'home_url'              => home_url(),
			'tm_vendor_cpt_exists'  => post_type_exists( 'tm_vendor' ),
			'TMP_WP_Vendor_CPT_cls' => class_exists( 'TMP_WP_Vendor_CPT', false ),
			'media_player_enabled'  => function_exists( 'ecomcine_feature_enabled' ) ? (bool) ecomcine_feature_enabled( 'media_player' ) : null,
			'ecomcine_settings'     => get_option( 'ecomcine_settings', array() ),
		);

		// ── Stage 0: raw seller/ecomcine_person users ─────────────────────────
		$all_sellers = get_users( array(
			'role__in' => array( 'seller', 'ecomcine_person', 'vendor' ),
			'number'   => -1,
		) );
		$stage0_ids = array_map( function( $u ) { return (int) $u->ID; }, $all_sellers );
		$report['stage0_all_roles'] = count( $stage0_ids );

		// ── Stage 1: ecomcine_get_persons ────────────────────────────────────
		$persons = function_exists( 'ecomcine_get_persons' )
			? ecomcine_get_persons( array( 'number' => -1 ) )
			: $all_sellers;
		$stage1_ids = array_map( function( $u ) { return (int) $u->ID; }, $persons );
		$report['stage1_ecomcine_get_persons'] = count( $stage1_ids );

		// ── Per-vendor detail ─────────────────────────────────────────────────
		$vendor_details = array();
		foreach ( $stage1_ids as $uid ) {
			$u = get_userdata( $uid );
			$is_enabled  = function_exists( 'ecomcine_is_person_enabled' ) ? ecomcine_is_person_enabled( $uid ) : null;
			$is_live     = function_exists( 'tm_store_ui_is_person_live' ) ? tm_store_ui_is_person_live( $uid ) : null;
			$has_profile = function_exists( 'ecomcine_has_public_person_profile' ) ? ecomcine_has_public_person_profile( $uid ) : null;
			$person_url  = function_exists( 'ecomcine_get_person_url' ) ? ecomcine_get_person_url( $uid ) : null;
			$cpt_id      = (int) get_user_meta( $uid, '_tm_vendor_cpt_id', true );

			$vendor_details[] = array(
				'id'           => $uid,
				'name'         => $u ? $u->display_name : '?',
				'roles'        => $u ? $u->roles : array(),
				'ec_enabled'   => get_user_meta( $uid, 'ecomcine_enabled', true ),
				'dokan_sell'   => get_user_meta( $uid, 'dokan_enable_selling', true ),
				'tm_l1'        => get_user_meta( $uid, 'tm_l1_complete', true ),
				'geo_lat'      => get_user_meta( $uid, 'ecomcine_geo_lat', true ),
				'cpt_id'       => $cpt_id,
				'cpt_status'   => $cpt_id ? get_post_status( $cpt_id ) : 'no_cpt',
				'is_enabled'   => $is_enabled,
				'is_live'      => $is_live,
				'has_profile'  => $has_profile,
				'has_url'      => $person_url !== null ? ( '' !== trim( $person_url ) ) : null,
				'url'          => $person_url,
				'passes_all'   => $is_enabled && $is_live && $has_profile && $person_url !== '',
			);
		}
		$report['vendors'] = $vendor_details;

		// ── Stage summaries ───────────────────────────────────────────────────
		$report['stage2_is_enabled']   = count( array_filter( $vendor_details, fn($v) => $v['is_enabled'] ) );
		$report['stage3_is_live']      = count( array_filter( $vendor_details, fn($v) => $v['is_enabled'] && $v['is_live'] ) );
		$report['stage4_has_profile']  = count( array_filter( $vendor_details, fn($v) => $v['is_enabled'] && $v['is_live'] && $v['has_profile'] ) );
		$report['stage5_has_url']      = count( array_filter( $vendor_details, fn($v) => $v['passes_all'] ) );

		// ── Final collect result ──────────────────────────────────────────────
		if ( function_exists( 'tm_store_ui_collect_person_ids_for_listing' ) ) {
			$final = tm_store_ui_collect_person_ids_for_listing();
			$report['final_collect_result'] = count( $final );
		}

		wp_send_json_success( $report );
	}
}

