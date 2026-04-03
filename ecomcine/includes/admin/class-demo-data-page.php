<?php
/**
 * EcomCine Demo Data admin page.
 *
 * Adds a "Demo Data" submenu under the EcomCine admin menu.
 *
 * Shows available demo packs fetched from the remote manifest at
 * ECOMCINE_DEMO_MANIFEST_URL (https://ecomcine.com/demos/manifest.json).
 * Each pack can be imported with one click via AJAX.
 *
 * Falls back to the local bundled demo (ecomcine/demo/) when present,
 * which is only included in development/local builds.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Demo_Data_Page {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_submenu' ), 20 );
		add_action( 'wp_ajax_ecomcine_import_demo',        array( __CLASS__, 'ajax_import_demo' ) );
		add_action( 'wp_ajax_ecomcine_import_demo_remote', array( __CLASS__, 'ajax_import_demo_remote' ) );
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

		// ── Local bundle (dev / offline fallback) ──────────────────────────────
		$local_json    = ECOMCINE_DIR . 'demo/vendor-data.json';
		$has_local     = file_exists( $local_json );
		$local_count   = 0;
		if ( $has_local ) {
			$payload = json_decode( (string) file_get_contents( $local_json ), true ); // phpcs:ignore
			$local_count = (int) ( $payload['vendor_count'] ?? 0 );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'EcomCine — Demo Data', 'ecomcine' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Import a demo content pack to populate your site with sample talent profiles, media and categories.', 'ecomcine' ); ?>
			</p>

			<?php if ( $manifest_error ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( $manifest_error ); ?></p></div>
			<?php endif; ?>

			<?php // ── Remote demo packs ──────────────────────────────────────────────── ?>
			<?php if ( is_array( $manifest ) && ! empty( $manifest['packs'] ) ) : ?>
				<h2><?php esc_html_e( 'Available Demo Packs', 'ecomcine' ); ?></h2>
				<div style="display:flex;flex-wrap:wrap;gap:20px;margin-top:12px;">
					<?php foreach ( $manifest['packs'] as $pack ) :
						$pack_name     = sanitize_text_field( $pack['name'] ?? '' );
						$pack_desc     = sanitize_text_field( $pack['description'] ?? '' );
						$pack_url      = esc_url_raw( $pack['zip_url'] ?? '' );
						$pack_count    = (int) ( $pack['vendor_count'] ?? 0 );
						$pack_preview  = esc_url( $pack['preview_image'] ?? '' );
						$pack_id       = sanitize_key( $pack['id'] ?? sanitize_title( $pack_name ) );
						if ( empty( $pack_url ) ) continue;
					?>
					<div class="ecomcine-demo-pack-card" style="border:1px solid #ccd0d4;border-radius:4px;padding:16px;max-width:280px;background:#fff;">
						<?php if ( $pack_preview ) : ?>
							<img src="<?php echo esc_url( $pack_preview ); ?>" alt="<?php echo esc_attr( $pack_name ); ?>"
								style="width:100%;height:140px;object-fit:cover;border-radius:3px;margin-bottom:10px;">
						<?php endif; ?>
						<strong><?php echo esc_html( $pack_name ); ?></strong>
						<?php if ( $pack_desc ) : ?>
							<p style="margin:6px 0 10px;color:#555;font-size:13px;"><?php echo esc_html( $pack_desc ); ?></p>
						<?php endif; ?>
						<?php if ( $pack_count ) : ?>
							<p style="margin:0 0 10px;font-size:12px;color:#777;">
								<?php echo esc_html( sprintf( _n( '%d vendor profile', '%d vendor profiles', $pack_count, 'ecomcine' ), $pack_count ) ); ?>
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

			<?php // ── Local fallback ─────────────────────────────────────────────────── ?>
			<?php if ( $has_local ) : ?>
				<h2 style="margin-top:30px;"><?php esc_html_e( 'Local Bundled Demo', 'ecomcine' ); ?></h2>
				<p class="description">
					<?php echo esc_html( sprintf(
						/* translators: %d number of vendors */
						__( 'A local demo bundle with %d vendor profiles is available (development build).', 'ecomcine' ),
						$local_count
					) ); ?>
				</p>
				<p>
					<button id="ecomcine-install-demo-btn" class="button"
						data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<?php esc_html_e( 'Import Local Demo', 'ecomcine' ); ?>
					</button>
				</p>
				<div id="ecomcine-demo-result" style="margin-top:12px;"></div>
			<?php elseif ( ! is_array( $manifest ) || empty( $manifest['packs'] ) ) : ?>
				<div class="notice notice-info inline" style="margin-top:20px;">
					<p><?php esc_html_e( 'No demo packs available. Upload demo content to ecomcine.com/demos/ and update the manifest.', 'ecomcine' ); ?></p>
				</div>
			<?php endif; ?>

			<div id="ecomcine-remote-result" style="margin-top:20px;"></div>
		</div>

		<script>
		(function () {
			var ajaxUrl = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

			// ── Remote pack import ────────────────────────────────────────────
			document.querySelectorAll('.ecomcine-import-remote-btn').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var packId  = btn.dataset.packId;
					var zipUrl  = btn.dataset.zipUrl;
					var nonce   = btn.dataset.nonce;
					var status  = document.querySelector('.ecomcine-pack-status[data-pack-id="' + packId + '"]');
					var resultEl = document.getElementById('ecomcine-remote-result');

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
					.then(function (r) { return r.json(); })
					.then(function (data) {
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
						btn.disabled = false;
						if (status) status.textContent = '✗';
						resultEl.innerHTML = '<div class="notice notice-error inline"><p>' + err + '</p></div>';
					});
				});
			});

			// ── Local demo import ─────────────────────────────────────────────
			var localBtn = document.getElementById('ecomcine-install-demo-btn');
			if (localBtn) {
				localBtn.addEventListener('click', function () {
					localBtn.disabled = true;
					localBtn.textContent = <?php echo json_encode( __( 'Installing…', 'ecomcine' ) ); ?>;
					var body = new URLSearchParams({
						action: 'ecomcine_import_demo',
						nonce:  localBtn.dataset.nonce,
					});
					fetch(ajaxUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: body.toString(),
					})
					.then(function (r) { return r.json(); })
					.then(function (data) {
						var resultEl = document.getElementById('ecomcine-demo-result');
						localBtn.disabled = false;
						localBtn.textContent = <?php echo json_encode( __( 'Import Local Demo', 'ecomcine' ) ); ?>;
						if (data.success) {
							var d = data.data;
							resultEl.innerHTML = '<div class="notice notice-success inline"><p><strong><?php esc_html_e( 'Done!', 'ecomcine' ); ?></strong> '
								+ d.imported + ' <?php esc_html_e( 'vendors created,', 'ecomcine' ); ?> '
								+ d.updated  + ' <?php esc_html_e( 'updated.', 'ecomcine' ); ?></p></div>';
						} else {
							resultEl.innerHTML = '<div class="notice notice-error inline"><p>'
								+ (data.data || '<?php esc_html_e( 'Import failed.', 'ecomcine' ); ?>') + '</p></div>';
						}
					});
				});
			}
		})();
		</script>
		<?php
	}

	/** AJAX: local bundled import. */
	public static function ajax_import_demo() {
		check_ajax_referer( 'ecomcine_demo_import', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.', 403 );
		}
		if ( ! class_exists( 'EcomCine_Demo_Importer', false ) ) {
			wp_send_json_error( 'Demo importer class not loaded.', 500 );
		}
		wp_send_json_success( EcomCine_Demo_Importer::run() );
	}

	/** AJAX: remote zip import. */
	public static function ajax_import_demo_remote() {
		check_ajax_referer( 'ecomcine_demo_import', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.', 403 );
		}
		if ( ! class_exists( 'EcomCine_Demo_Importer', false ) ) {
			wp_send_json_error( 'Demo importer class not loaded.', 500 );
		}
		$zip_url = esc_url_raw( wp_unslash( $_POST['zip_url'] ?? '' ) );
		if ( empty( $zip_url ) ) {
			wp_send_json_error( 'Missing zip_url parameter.' );
		}
		wp_send_json_success( EcomCine_Demo_Importer::run_remote( $zip_url ) );
	}
}

