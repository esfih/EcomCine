<?php
/**
 * EcomCine Demo Data admin page.
 *
 * Adds a "Demo Data" submenu under the EcomCine admin menu (parent slug: ecomcine-settings).
 * Provides a one-click "Install Demo Data" button that runs EcomCine_Demo_Importer::run()
 * via WP AJAX.
 */

defined( 'ABSPATH' ) || exit;

class EcomCine_Demo_Data_Page {

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'register_submenu' ], 20 );
		add_action( 'wp_ajax_ecomcine_import_demo', [ __CLASS__, 'ajax_import_demo' ] );
	}

	public static function register_submenu() {
		add_submenu_page(
			'ecomcine-settings',
			__( 'Demo Data', 'ecomcine' ),
			__( 'Demo Data', 'ecomcine' ),
			'manage_options',
			'ecomcine-demo-data',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ecomcine' ) );
		}

		$json_path = ECOMCINE_DIR . 'demo/vendor-data.json';
		$has_data  = file_exists( $json_path );
		$vendor_count = 0;
		$exported_at  = '';

		if ( $has_data ) {
			$raw = file_get_contents( $json_path );
			$payload = json_decode( $raw, true );
			if ( is_array( $payload ) ) {
				$vendor_count = (int) ( $payload['vendor_count'] ?? 0 );
				$exported_at  = sanitize_text_field( $payload['exported_at'] ?? '' );
			}
		}

		$nonce = wp_create_nonce( 'ecomcine_demo_import' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'EcomCine — Demo Data', 'ecomcine' ); ?></h1>

			<?php if ( ! $has_data ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php esc_html_e( 'Demo data bundle not found.', 'ecomcine' ); ?>
						<code><?php echo esc_html( 'ecomcine/demo/vendor-data.json' ); ?></code>
						<?php esc_html_e( 'Run the export command to generate it.', 'ecomcine' ); ?>
					</p>
				</div>
			<?php else : ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Vendor profiles', 'ecomcine' ); ?></th>
						<td><strong><?php echo esc_html( $vendor_count ); ?></strong></td>
					</tr>
					<?php if ( $exported_at ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Exported at', 'ecomcine' ); ?></th>
						<td><?php echo esc_html( $exported_at ); ?></td>
					</tr>
					<?php endif; ?>
				</table>

				<p class="description">
					<?php esc_html_e( 'Clicking "Install Demo Data" will create vendor user accounts, populate their profiles, and import their media from the bundle bundled with this plugin. Existing users (same login or email) are skipped.', 'ecomcine' ); ?>
				</p>

				<p>
					<button id="ecomcine-install-demo-btn" class="button button-primary">
						<?php esc_html_e( 'Install Demo Data', 'ecomcine' ); ?>
					</button>
				</p>

				<div id="ecomcine-demo-result" style="margin-top:16px;"></div>
			<?php endif; ?>
		</div>

		<script>
		(function () {
			var btn = document.getElementById('ecomcine-install-demo-btn');
			if (!btn) return;

			btn.addEventListener('click', function () {
				btn.disabled = true;
				btn.textContent = <?php echo json_encode( __( 'Installing…', 'ecomcine' ) ); ?>;

				var body = new URLSearchParams({
					action: 'ecomcine_import_demo',
					nonce: <?php echo json_encode( $nonce ); ?>,
				});

				fetch(<?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString(),
				})
				.then(function (r) { return r.json(); })
				.then(function (data) {
					var resultEl = document.getElementById('ecomcine-demo-result');
					if (data.success) {
						var d = data.data;
						var html = '<div class="notice notice-success inline"><p>'
							+ '<strong>' + <?php echo json_encode( __( 'Done!', 'ecomcine' ) ); ?> + '</strong> '
							+ d.imported + <?php echo json_encode( ' ' . __( 'vendors created,', 'ecomcine' ) . ' ' ); ?>
							+ d.updated + <?php echo json_encode( ' ' . __( 'updated.', 'ecomcine' ) ); ?>
							+ '</p>';
						if (d.errors && d.errors.length) {
							html += '<ul style="margin-left:18px;list-style:disc;">';
							d.errors.forEach(function (e) { html += '<li>' + e + '</li>'; });
							html += '</ul>';
						}
						html += '</div>';
						resultEl.innerHTML = html;
						btn.textContent = <?php echo json_encode( __( 'Install Demo Data', 'ecomcine' ) ); ?>;
						btn.disabled = false;
					} else {
						resultEl.innerHTML = '<div class="notice notice-error inline"><p>'
							+ (data.data || <?php echo json_encode( __( 'Import failed.', 'ecomcine' ) ); ?>)
							+ '</p></div>';
						btn.textContent = <?php echo json_encode( __( 'Install Demo Data', 'ecomcine' ) ); ?>;
						btn.disabled = false;
					}
				})
				.catch(function (err) {
					document.getElementById('ecomcine-demo-result').innerHTML =
						'<div class="notice notice-error inline"><p>' + err + '</p></div>';
					btn.textContent = <?php echo json_encode( __( 'Install Demo Data', 'ecomcine' ) ); ?>;
					btn.disabled = false;
				});
			});
		})();
		</script>
		<?php
	}

	public static function ajax_import_demo() {
		check_ajax_referer( 'ecomcine_demo_import', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'ecomcine' ), 403 );
		}

		if ( ! class_exists( 'EcomCine_Demo_Importer', false ) ) {
			wp_send_json_error( __( 'Demo importer class not loaded.', 'ecomcine' ), 500 );
		}

		$result = EcomCine_Demo_Importer::run();

		wp_send_json_success( $result );
	}
}
