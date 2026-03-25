<?php
/**
 * Attribute Sets List View
 * 
 * @package Dokan_Category_Attributes
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$manager = new DCA_Attribute_Manager();
$attribute_sets = $manager->get_attribute_sets( array( 'status' => '' ) ); // Get all statuses
?>

<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php _e( 'Category Attributes', 'dokan-category-attributes' ); ?>
	</h1>
	
	<a href="<?php echo admin_url( 'admin.php?page=dokan-category-attributes-builder' ); ?>" class="page-title-action">
		<?php _e( 'Add New', 'dokan-category-attributes' ); ?>
	</a>
	
	<hr class="wp-header-end">
	
	<?php if ( empty( $attribute_sets ) ) : ?>
		<div class="notice notice-info">
			<p><?php _e( 'No attribute sets found. Create your first attribute set to get started.', 'dokan-category-attributes' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 30px;"><?php _e( 'ID', 'dokan-category-attributes' ); ?></th>
					<th><?php _e( 'Name', 'dokan-category-attributes' ); ?></th>
					<th><?php _e( 'Categories', 'dokan-category-attributes' ); ?></th>
					<th><?php _e( 'Fields', 'dokan-category-attributes' ); ?></th>
					<th><?php _e( 'Priority', 'dokan-category-attributes' ); ?></th>
					<th><?php _e( 'Status', 'dokan-category-attributes' ); ?></th>
					<th><?php _e( 'Actions', 'dokan-category-attributes' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $attribute_sets as $set ) : 
					$fields = $manager->get_fields( $set->id );
					$nonce = wp_create_nonce( 'dca_action' );
					?>
					<tr>
						<td><?php echo esc_html( $set->id ); ?></td>
						<td>
							<strong>
								<a href="<?php echo admin_url( 'admin.php?page=dokan-category-attributes-builder&set_id=' . $set->id ); ?>">
									<?php echo esc_html( $set->name ); ?>
								</a>
							</strong>
							<?php if ( $set->icon ) : ?>
								<span class="dashicons dashicons-<?php echo esc_attr( $set->icon ); ?>"></span>
							<?php endif; ?>
						</td>
						<td>
							<?php 
							if ( ! empty( $set->categories ) ) {
								$category_names = array();
								foreach ( $set->categories as $cat_slug ) {
									$term = get_term_by( 'slug', $cat_slug, 'store_category' );
									if ( $term ) {
										$category_names[] = $term->name;
									}
								}
								echo esc_html( implode( ', ', $category_names ) );
							} else {
								_e( 'None', 'dokan-category-attributes' );
							}
							?>
						</td>
						<td><?php echo count( $fields ); ?></td>
						<td><?php echo esc_html( $set->priority ); ?></td>
						<td>
							<?php if ( $set->status === 'active' ) : ?>
								<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
								<?php _e( 'Active', 'dokan-category-attributes' ); ?>
							<?php else : ?>
								<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
								<?php _e( 'Inactive', 'dokan-category-attributes' ); ?>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo admin_url( 'admin.php?page=dokan-category-attributes-builder&set_id=' . $set->id ); ?>" class="button button-small">
								<?php _e( 'Edit', 'dokan-category-attributes' ); ?>
							</a>
							
							<a href="<?php echo admin_url( 'admin.php?page=dokan-category-attributes&action=duplicate&set_id=' . $set->id . '&_wpnonce=' . $nonce ); ?>" 
							   class="button button-small" 
							   onclick="return confirm('<?php _e( 'Duplicate this attribute set?', 'dokan-category-attributes' ); ?>');">
								<?php _e( 'Duplicate', 'dokan-category-attributes' ); ?>
							</a>
							
							<a href="<?php echo admin_url( 'admin.php?page=dokan-category-attributes&action=toggle_status&set_id=' . $set->id . '&_wpnonce=' . $nonce ); ?>" 
							   class="button button-small">
								<?php echo ( $set->status === 'active' ) ? __( 'Deactivate', 'dokan-category-attributes' ) : __( 'Activate', 'dokan-category-attributes' ); ?>
							</a>
							
							<a href="<?php echo admin_url( 'admin.php?page=dokan-category-attributes&action=delete&set_id=' . $set->id . '&_wpnonce=' . $nonce ); ?>" 
							   class="button button-small button-link-delete" 
							   onclick="return confirm('<?php _e( 'Are you sure you want to delete this attribute set? This cannot be undone.', 'dokan-category-attributes' ); ?>');">
								<?php _e( 'Delete', 'dokan-category-attributes' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	
	<div class="dca-import-export" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
		<h2><?php _e( 'Import / Export', 'dokan-category-attributes' ); ?></h2>
		
		<div style="margin-bottom: 20px;">
			<h3><?php _e( 'Export Attribute Set', 'dokan-category-attributes' ); ?></h3>
			<p><?php _e( 'Select an attribute set to export as JSON:', 'dokan-category-attributes' ); ?></p>
			<select id="dca-export-set" style="min-width: 300px;">
				<option value=""><?php _e( '-- Select Set --', 'dokan-category-attributes' ); ?></option>
				<?php foreach ( $attribute_sets as $set ) : ?>
					<option value="<?php echo esc_attr( $set->id ); ?>"><?php echo esc_html( $set->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button" id="dca-export-btn"><?php _e( 'Export', 'dokan-category-attributes' ); ?></button>
		</div>
		
		<div>
			<h3><?php _e( 'Import Attribute Set', 'dokan-category-attributes' ); ?></h3>
			<p><?php _e( 'Upload a JSON file to import:', 'dokan-category-attributes' ); ?></p>
			<input type="file" id="dca-import-file" accept=".json">
			<button type="button" class="button" id="dca-import-btn"><?php _e( 'Import', 'dokan-category-attributes' ); ?></button>
		</div>
	</div>
</div>

<style>
.dca-import-export {
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.dca-import-export h3 {
	margin-top: 0;
	font-size: 14px;
	font-weight: 600;
}
</style>

<script>
jQuery(document).ready(function($) {
	// Export functionality
	$('#dca-export-btn').on('click', function() {
		var setId = $('#dca-export-set').val();
		if (!setId) {
			alert('<?php _e( 'Please select an attribute set', 'dokan-category-attributes' ); ?>');
			return;
		}
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'dca_export_set',
				set_id: setId,
				nonce: '<?php echo wp_create_nonce( 'dca_export' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					// Create download link
					var blob = new Blob([response.data], {type: 'application/json'});
					var url = window.URL.createObjectURL(blob);
					var a = document.createElement('a');
					a.href = url;
					a.download = 'attribute-set-' + setId + '.json';
					document.body.appendChild(a);
					a.click();
					window.URL.revokeObjectURL(url);
					document.body.removeChild(a);
				} else {
					alert(response.data);
				}
			}
		});
	});
	
	// Import functionality
	$('#dca-import-btn').on('click', function() {
		var file = $('#dca-import-file')[0].files[0];
		if (!file) {
			alert('<?php _e( 'Please select a file', 'dokan-category-attributes' ); ?>');
			return;
		}
		
		var reader = new FileReader();
		reader.onload = function(e) {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'dca_import_set',
					json_data: e.target.result,
					nonce: '<?php echo wp_create_nonce( 'dca_import' ); ?>'
				},
				success: function(response) {
					if (response.success) {
						alert('<?php _e( 'Attribute set imported successfully', 'dokan-category-attributes' ); ?>');
						location.reload();
					} else {
						alert(response.data);
					}
				}
			});
		};
		reader.readAsText(file);
	});
});
</script>
