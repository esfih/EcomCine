<?php
/**
 * Admin: Vendor Completeness Diagnostics & Force-Recompute
 *
 * Adds a WP Admin page under "Appearance" that:
 *   – Lists all seller accounts with their tm_l1_complete / tm_l2_complete flag values
 *   – Shows exactly which L1 fields are missing per vendor
 *   – Has a "Force Recompute All" button to refresh all flags immediately
 *
 * URL: /wp-admin/themes.php?page=tm_vendor_completeness
 *
 * @package Astra Child
 */

defined( 'ABSPATH' ) || exit;

// ── Register the admin menu page ─────────────────────────────────────────────
add_action( 'admin_menu', function() {
	add_theme_page(
		'Vendor Completeness Flags',
		'Vendor Flags',
		'manage_options',
		'tm_vendor_completeness',
		'tm_render_vendor_completeness_admin'
	);
} );

// ── Handle the force-recompute POST action ────────────────────────────────────
add_action( 'admin_post_tm_recompute_vendor_flags', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions.' );
	}
	check_admin_referer( 'tm_recompute_vendor_flags' );

	$seller_ids = function_exists( 'ecomcine_get_persons' )
		? array_column( ecomcine_get_persons( [ 'fields' => 'ID', 'number' => -1 ] ), 'ID' )
		: get_users( [ 'role' => 'seller', 'fields' => 'ID', 'number' => -1 ] );
	foreach ( $seller_ids as $vid ) {
		tm_update_completeness_flags( (int) $vid );
	}

	// Bump the migration stamp to prevent future automatic re-runs from undoing manual work.
	update_option( 'tm_completeness_flags_v2', '1', false );

	wp_safe_redirect( add_query_arg( [
		'page'      => 'tm_vendor_completeness',
		'recomputed' => '1',
	], admin_url( 'themes.php' ) ) );
	exit;
} );

// ── Render the admin page ─────────────────────────────────────────────────────
function tm_render_vendor_completeness_admin() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! function_exists( 'tm_vendor_completeness' ) ) {
		echo '<div class="wrap"><p>tm_vendor_completeness() not available.</p></div>';
		return;
	}

	$did_recompute = ! empty( $_GET['recomputed'] );

	$sellers = function_exists( 'ecomcine_get_persons' )
		? ecomcine_get_persons( [ 'fields' => 'all', 'number' => -1, 'orderby' => 'display_name' ] )
		: get_users( [ 'role' => 'seller', 'fields' => 'all', 'number' => -1, 'orderby' => 'display_name' ] );

	?>
	<div class="wrap">
		<h1>Vendor Completeness Flags</h1>

		<?php if ( $did_recompute ) : ?>
			<div class="notice notice-success"><p><strong>✅ All vendor flags recomputed successfully.</strong></p></div>
		<?php endif; ?>

		<p>
			This page shows the live <code>tm_l1_complete</code> and <code>tm_l2_complete</code> meta values
			for every seller, plus exactly which L1 fields are missing. Use this to diagnose why vendors
			are not appearing on the public store listing.
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:20px;">
			<?php wp_nonce_field( 'tm_recompute_vendor_flags' ); ?>
			<input type="hidden" name="action" value="tm_recompute_vendor_flags">
			<button type="submit" class="button button-primary button-large">
				🔄 Force Recompute All Vendor Flags
			</button>
			<span style="margin-left:10px;color:#666;">Recalculates tm_l1_complete / tm_l2_complete for every seller account.</span>
		</form>

		<style>
			.tm-flag-table { border-collapse: collapse; width: 100%; }
			.tm-flag-table th, .tm-flag-table td { padding: 8px 12px; border: 1px solid #ccd0d4; text-align: left; vertical-align: top; }
			.tm-flag-table th { background: #f0f0f1; font-weight: 600; }
			.tm-flag-table tr:nth-child(even) td { background: #fafafa; }
			.tm-pass { color: #2e7d32; font-weight: bold; }
			.tm-fail { color: #c62828; font-weight: bold; }
			.tm-missing-list { margin: 4px 0 0; padding: 0; list-style: none; }
			.tm-missing-list li { font-size: 12px; color: #c62828; }
			.tm-missing-list li::before { content: "✗ "; }
			.tm-na { color: #888; }
		</style>

		<table class="tm-flag-table widefat">
			<thead>
				<tr>
					<th>Vendor (ID)</th>
					<th>Category</th>
					<th>Enabled<br><code>ecomcine_enabled</code></th>
					<th style="width:120px">L1 Flag<br><code>tm_l1_complete</code></th>
					<th>L1 Score</th>
					<th>Missing L1 Fields (live calc)</th>
					<th style="width:80px">L2 Flag</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $sellers as $seller ) :
				$vid        = (int) $seller->ID;
			$enabled    = function_exists( 'ecomcine_is_person_enabled' ) ? ecomcine_is_person_enabled( $vid ) : false;
			$l1_stored  = get_user_meta( $vid, 'tm_l1_complete', true );
			$l2_stored  = get_user_meta( $vid, 'tm_l2_complete', true );
			if ( function_exists( 'ecomcine_get_person_info' ) ) {
				$person_info = ecomcine_get_person_info( $vid );
				$shop_name   = ! empty( $person_info['store_name'] ) ? esc_html( $person_info['store_name'] ) : esc_html( $seller->display_name );
			} elseif ( function_exists( 'dokan_get_store_info' ) ) {
				$store_info = dokan_get_store_info( $vid );
				$shop_name  = ! empty( $store_info['store_name'] ) ? esc_html( $store_info['store_name'] ) : esc_html( $seller->display_name );
			} else {
				$shop_name = esc_html( $seller->display_name );
			}

			if ( class_exists( 'EcomCine_Person_Category_Registry', false ) ) {
				$cat_rows = EcomCine_Person_Category_Registry::get_for_person( $vid );
				$cat_list = ! empty( $cat_rows ) ? implode( ', ', wp_list_pluck( $cat_rows, 'name' ) ) : '—';
			} else {
				$v_terms  = wp_get_object_terms( $vid, 'store_category', [ 'fields' => 'names' ] );
				$cat_list = ( is_array( $v_terms ) && ! is_wp_error( $v_terms ) ) ? implode( ', ', $v_terms ) : '—';
			}
				$c = tm_vendor_completeness( $vid );
				$l1_live     = $c ? $c['level1']['complete'] : null;
				$l1_pct      = $c ? $c['level1']['pct'] : 0;
				$l1_done     = $c ? $c['level1']['done'] : 0;
				$l1_total    = $c ? $c['level1']['total'] : 0;
				$l1_missing  = $c ? $c['level1']['missing'] : [];

				// Flag mismatch warning
				$mismatch = ( $l1_stored !== '' && $l2_stored !== '' ) && (
					( $l1_stored === '1' ) !== (bool) $l1_live
				);
			?>
				<tr<?php if ( $mismatch ) echo ' style="background:#fff3e0!important"'; ?>>
					<td>
						<?php echo $shop_name; ?>
						<br><small style="color:#888">ID <?php echo $vid; ?></small>
						<?php if ( $mismatch ) echo '<br><small style="color:#e65100">⚠ Stored flag mismatches live calc!</small>'; ?>
					</td>
					<td><?php echo esc_html( $cat_list ); ?></td>
					<td><?php
						if ( $enabled ) {
							echo '<span class="tm-pass">✓ yes</span>';
						} else {
							echo '<span class="tm-fail">✗ no</span>';
						}
					?></td>
					<td><?php
						if ( $l1_stored === '1' ) {
							echo '<span class="tm-pass">✓ 1</span>';
						} elseif ( $l1_stored === '0' ) {
							echo '<span class="tm-fail">✗ 0</span>';
						} else {
							echo '<span class="tm-na">(not set)</span>';
						}
					?></td>
					<td>
						<?php echo esc_html( $l1_pct ) . '%'; ?>
						<br><small><?php echo esc_html( $l1_done ) . ' / ' . esc_html( $l1_total ); ?></small>
					</td>
					<td><?php
						if ( empty( $l1_missing ) ) {
							echo '<span class="tm-pass">✓ All fields complete</span>';
						} else {
							echo '<ul class="tm-missing-list">';
							foreach ( $l1_missing as $field ) {
								echo '<li>' . esc_html( $field ) . '</li>';
							}
							echo '</ul>';
						}
					?></td>
					<td><?php
						if ( $l2_stored === '1' ) {
							echo '<span class="tm-pass">✓ 1</span>';
						} elseif ( $l2_stored === '0' ) {
							echo '<span class="tm-fail">✗ 0</span>';
						} else {
							echo '<span class="tm-na">(not set)</span>';
						}
					?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p style="margin-top:16px;color:#666;">
			<strong>Note:</strong> The "Missing L1 Fields" column shows a <em>live</em> calculation every time this page loads.
			The "L1 Flag" column shows what is currently stored in the database.
			If they differ, click "Force Recompute" above.
		</p>
	</div>
	<?php
}
