<?php
/**
 * Admin  Vendor Edit Logs
 *
 * Adds a 'Vendor Edit Logs' sub-page under Users in the WP admin.
 * Tracks field-level changes made by admins on vendor profiles.
 *
 * EXPORTS
 * 
 * admin_menu hook         adds submenu page
 * tm_vendor_edit_logs_page()    display/filter the log table
 * tm_can_edit_vendor_profile()  auth check (also used by profile AJAX)
 * tm_log_admin_vendor_edit()    record a field change
 * tm_sanitize_log_value()       truncate/format a value for display
 *
 * @package Astra Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add Admin Menu for Vendor Edit Logs
 */
add_action( 'admin_menu', function() {
	add_submenu_page(
		'users.php',                    // Parent menu (Users)
		'Vendor Edit Logs',             // Page title
		'Vendor Edit Logs',             // Menu title
		'manage_options',               // Capability
		'vendor-edit-logs',             // Menu slug
		'tm_vendor_edit_logs_page'      // Callback function
	);
});

/**
 * Admin page content for vendor edit logs
 */
function tm_vendor_edit_logs_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have sufficient permissions.' );
	}
	
	// Handle log clearing
	if ( isset( $_POST['clear_logs'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'clear_vendor_logs' ) ) {
		delete_option( 'tm_admin_vendor_edit_logs' );
		echo '<div class="notice notice-success is-dismissible"><p>Logs cleared successfully.</p></div>';
	}
	
	// Get logs
	$logs = get_option( 'tm_admin_vendor_edit_logs', [] );
	$logs = array_reverse( $logs ); // Show newest first
	
	// Handle search/filtering
	$search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
	$filter_admin = isset( $_GET['filter_admin'] ) ? sanitize_text_field( $_GET['filter_admin'] ) : '';
	$filter_vendor = isset( $_GET['filter_vendor'] ) ? sanitize_text_field( $_GET['filter_vendor'] ) : '';
	
	if ( $search || $filter_admin || $filter_vendor ) {
		$logs = array_filter( $logs, function( $log ) use ( $search, $filter_admin, $filter_vendor ) {
			if ( $search && stripos( $log['field'] . ' ' . $log['admin_name'] . ' ' . $log['vendor_name'], $search ) === false ) {
				return false;
			}
			if ( $filter_admin && $log['admin_id'] != $filter_admin ) {
				return false;
			}
			if ( $filter_vendor && $log['vendor_id'] != $filter_vendor ) {
				return false;
			}
			return true;
		});
	}
	
	// Get unique admins and vendors for filter dropdowns
	$all_logs = get_option( 'tm_admin_vendor_edit_logs', [] );
	$admins = [];
	$vendors = [];
	foreach ( $all_logs as $log ) {
		$admins[ $log['admin_id'] ] = $log['admin_name'];
		$vendors[ $log['vendor_id'] ] = $log['vendor_name'];
	}
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline">Vendor Edit Logs</h1>
		<p class="description">Track admin modifications to vendor profiles for security and audit purposes.</p>
		
		<!-- Filters -->
		<div class="tablenav top">
			<form method="get" class="search-form" style="float: right; display: flex; gap: 10px; align-items: center;">
				<input type="hidden" name="page" value="vendor-edit-logs">
				
				<select name="filter_admin" style="min-width: 150px;">
					<option value="">All Admins</option>
					<?php foreach ( $admins as $admin_id => $admin_name ) : ?>
						<option value="<?php echo esc_attr( $admin_id ); ?>" <?php selected( $filter_admin, $admin_id ); ?>>
							<?php echo esc_html( $admin_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				
				<select name="filter_vendor" style="min-width: 150px;">
					<option value="">All Vendors</option>
					<?php foreach ( $vendors as $vendor_id => $vendor_name ) : ?>
						<option value="<?php echo esc_attr( $vendor_id ); ?>" <?php selected( $filter_vendor, $vendor_id ); ?>>
							<?php echo esc_html( $vendor_name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				
				<input type="search" name="search" value="<?php echo esc_attr( $search ); ?>" 
					   placeholder="Search fields, names..." style="min-width: 200px;">
				
				<input type="submit" class="button" value="Filter">
				
				<?php if ( $search || $filter_admin || $filter_vendor ) : ?>
					<a href="?page=vendor-edit-logs" class="button button-secondary">Clear</a>
				<?php endif; ?>
			</form>
			
			<div class="alignleft actions">
				<form method="post" style="display: inline;" 
					  onsubmit="return confirm('Are you sure you want to clear all logs? This cannot be undone.');">
					<?php wp_nonce_field( 'clear_vendor_logs' ); ?>
					<input type="submit" name="clear_logs" class="button button-secondary" value="Clear All Logs">
				</form>
				
				<button type="button" class="button button-secondary" onclick="exportVendorLogs()">Export CSV</button>
			</div>
		</div>
		
		<div class="clear"></div>
		
		<!-- Stats -->
		<div class="vendor-logs-stats" style="display: flex; gap: 20px; margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 4px;">
			<div><strong>Total Logs:</strong> <?php echo count( $all_logs ); ?></div>
			<div><strong>Showing:</strong> <?php echo count( $logs ); ?></div>
			<div><strong>Admins Active:</strong> <?php echo count( array_unique( array_column( $all_logs, 'admin_id' ) ) ); ?></div>
			<div><strong>Vendors Edited:</strong> <?php echo count( array_unique( array_column( $all_logs, 'vendor_id' ) ) ); ?></div>
		</div>
		
		<!-- Logs Table -->
		<?php if ( empty( $logs ) ) : ?>
			<div class="notice notice-info">
				<p><?php echo $search ? 'No logs found matching your search criteria.' : 'No admin vendor edits recorded yet.'; ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped" id="vendor-logs-table">
				<thead>
					<tr>
						<th scope="col" style="width: 140px;">Timestamp</th>
						<th scope="col">Admin User</th>
						<th scope="col">Vendor</th>
						<th scope="col">Field Edited</th>
						<th scope="col" style="width: 200px;">Changes</th>
						<th scope="col">Action</th>
						<th scope="col" style="width: 100px;">Links</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<?php 
						$has_values = !empty( $log['old_value'] ) || !empty( $log['new_value'] );
						$old_display = isset( $log['old_value'] ) ? $log['old_value'] : 'null';
						$new_display = isset( $log['new_value'] ) ? $log['new_value'] : 'null';
						?>
						<tr>
							<td title="<?php echo esc_attr( $log['timestamp'] ); ?>">
								<?php echo esc_html( human_time_diff( strtotime( $log['timestamp'] ) ) ); ?> ago
							</td>
							<td>
								<strong><?php echo esc_html( $log['admin_name'] ); ?></strong>
								<br><small>ID: <?php echo esc_html( $log['admin_id'] ); ?></small>
							</td>
							<td>
								<strong><?php echo esc_html( $log['vendor_name'] ); ?></strong>
								<br><small>ID: <?php echo esc_html( $log['vendor_id'] ); ?></small>
							</td>
							<td>
								<code><?php echo esc_html( $log['field'] ); ?></code>
							</td>
							<td>
								<?php if ( $has_values && ($old_display !== 'null' || $new_display !== 'null') ) : ?>
									<div class="field-changes">
										<div class="old-value" title="Old Value">
											<strong>From:</strong> <code><?php echo esc_html( $old_display ); ?></code>
										</div>
										<div class="new-value" title="New Value">
											<strong>To:</strong> <code><?php echo esc_html( $new_display ); ?></code>
										</div>
									</div>
								<?php else : ?>
									<em>Values not recorded</em>
								<?php endif; ?>
							</td>
							<td>
								<span class="vendor-log-action vendor-log-<?php echo esc_attr( $log['action'] ); ?>">
									<?php echo esc_html( ucfirst( $log['action'] ) ); ?>
								</span>
							</td>
							<td>
								<a href="<?php echo esc_url( get_edit_user_link( $log['vendor_id'] ) ); ?>" 
								   class="button button-small" target="_blank" title="Edit Vendor in Admin">Admin</a>
								<a href="<?php echo esc_url( dokan_get_store_url( $log['vendor_id'] ) ); ?>" 
								   class="button button-small" target="_blank" title="View Vendor Store">Store</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	
	<style>
		.vendor-log-action {
			padding: 4px 8px;
			border-radius: 3px;
			font-size: 12px;
			font-weight: 600;
		}
		.vendor-log-updated {
			background: #d1ecf1;
			color: #0c5460;
		}
		.vendor-log-created {
			background: #d4edda;
			color: #155724;
		}
		.vendor-log-deleted {
			background: #f8d7da;
			color: #721c24;
		}
		.search-form {
			margin-bottom: 10px;
		}
		.field-changes {
			font-size: 11px;
			line-height: 1.3;
		}
		.field-changes .old-value,
		.field-changes .new-value {
			margin-bottom: 3px;
		}
		.field-changes code {
			color: #666;
			font-size: 10px;
			background: #f7f7f7;
			padding: 1px 4px;
			max-width: 150px;
			display: inline-block;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			vertical-align: top;
			border-radius: 2px;
		}
		.old-value code { 
			background-color: #fef7f7; 
			border-left: 2px solid #dc3232;
		}
		.new-value code { 
			background-color: #f0f8f0; 
			border-left: 2px solid #46b450;
		}
		@media (max-width: 782px) {
			.search-form {
				flex-direction: column;
				align-items: stretch;
			}
			.search-form > * {
				margin-bottom: 5px;
			}
		}
	</style>
	
	<script>
	function exportVendorLogs() {
		const table = document.getElementById('vendor-logs-table');
		if (!table) {
			alert('No logs to export.');
			return;
		}
		
		let csv = 'Timestamp,Admin User,Admin ID,Vendor,Vendor ID,Field,Old Value,New Value,Action\n';
		
		const rows = table.querySelectorAll('tbody tr');
		rows.forEach(row => {
			const cells = row.querySelectorAll('td');
			const timestamp = cells[0].getAttribute('title');
			const adminName = cells[1].querySelector('strong').textContent;
			const adminId = cells[1].querySelector('small').textContent.replace('ID: ', '');
			const vendorName = cells[2].querySelector('strong').textContent;
			const vendorId = cells[2].querySelector('small').textContent.replace('ID: ', '');
			const field = cells[3].querySelector('code').textContent;
			
			// Extract old and new values from changes column (5th column with new layout)
			const changesCell = cells[4];
			let oldValue = 'N/A';
			let newValue = 'N/A';
			
			const oldValueEl = changesCell.querySelector('.old-value code');
			const newValueEl = changesCell.querySelector('.new-value code');
			if (oldValueEl && newValueEl) {
				oldValue = oldValueEl.textContent;
				newValue = newValueEl.textContent;
			}
			
			const action = cells[5].querySelector('span').textContent;
			
			csv += `"${timestamp}","${adminName}","${adminId}","${vendorName}","${vendorId}","${field}","${oldValue}","${newValue}","${action}"\n`;
		});
		
		const blob = new Blob([csv], { type: 'text/csv' });
		const url = window.URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = `vendor-edit-logs-${new Date().toISOString().split('T')[0]}.csv`;
		a.click();
		window.URL.revokeObjectURL(url);
	}
	</script>
	<?php
}

/**
 * Enhanced permission check for vendor profile editing
 * Allows both vendor owners and WordPress admins to edit profiles
 */
function tm_can_edit_vendor_profile( $vendor_id, $current_user_id = null ) {
	if ( !$current_user_id ) {
		$current_user_id = get_current_user_id();
	}
	
	// Original owner check
	if ( $current_user_id == $vendor_id ) {
		return true;
	}
	
	// Admin capability check - manage_options is core admin capability
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	
	// Edit users capability - for user managers
	if ( current_user_can( 'edit_users' ) ) {
		return true;
	}
	
	// Optional: Custom capability for vendor management (can be added later)
	if ( current_user_can( 'manage_vendors' ) ) {
		return true;
	}
	
	return false;
}

/**
 * Log admin edits to vendor profiles for audit purposes
 * Enhanced to capture old and new values
 */
function tm_log_admin_vendor_edit( $vendor_id, $field, $action = 'updated', $old_value = null, $new_value = null ) {
	$current_user_id = get_current_user_id();
	
	// Only log if this is an admin editing someone else's profile
	if ( $current_user_id != $vendor_id && current_user_can( 'manage_options' ) ) {
		$admin_user = get_userdata( $current_user_id );
		$vendor_user = get_userdata( $vendor_id );
		
		// Sanitize values for logging (truncate if too long)
		$old_display = $old_value !== null ? tm_sanitize_log_value( $old_value ) : null;
		$new_display = $new_value !== null ? tm_sanitize_log_value( $new_value ) : null;
		
		$log_entry = sprintf(
			'[ADMIN EDIT] %s (%s) %s field "%s" for vendor %s (%s)',
			$admin_user->display_name,
			$admin_user->user_login,
			$action,
			$field,
			$vendor_user->display_name,
			$vendor_user->user_login
		);
		
		if ( $old_display !== null || $new_display !== null ) {
			$log_entry .= sprintf( ' [%s → %s]', $old_display ?? 'null', $new_display ?? 'null' );
		}
		
		// Log to WordPress error log
		error_log( $log_entry );
		
		// Optional: Store in database for dashboard viewing
		$logs = get_option( 'tm_admin_vendor_edit_logs', [] );
		$logs[] = [
			'timestamp' => current_time( 'mysql' ),
			'admin_id' => $current_user_id,
			'admin_name' => $admin_user->display_name,
			'vendor_id' => $vendor_id,
			'vendor_name' => $vendor_user->display_name,
			'field' => $field,
			'action' => $action,
			'old_value' => $old_display,
			'new_value' => $new_display
		];
		
		// Keep only last 100 entries
		$logs = array_slice( $logs, -100, 100 );
		update_option( 'tm_admin_vendor_edit_logs', $logs );
	}
}

/**
 * Sanitize values for log display (handle arrays, long strings, sensitive data)
 */
function tm_sanitize_log_value( $value ) {
	if ( is_array( $value ) ) {
		// Handle arrays (like contact lists)
		if ( empty( $value ) ) {
			return '[]';
		}
		return '[' . implode( ', ', array_slice( $value, 0, 3 ) ) . ( count( $value ) > 3 ? '...' : '' ) . ']';
	}
	
	if ( is_string( $value ) ) {
		// Truncate long strings
		if ( strlen( $value ) > 100 ) {
			return substr( $value, 0, 97 ) . '...';
		}
		return $value;
	}
	
	if ( is_bool( $value ) ) {
		return $value ? 'true' : 'false';
	}
	
	if ( is_null( $value ) ) {
		return 'null';
	}
	
	return (string) $value;
}
