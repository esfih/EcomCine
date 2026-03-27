<?php
/**
 * Field Builder View
 * 
 * @package Dokan_Category_Attributes
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$manager = new DCA_Attribute_Manager();
$set_id = isset( $_GET['set_id'] ) ? intval( $_GET['set_id'] ) : 0;
$set = null;
$fields = array();

if ( $set_id > 0 ) {
	$set = $manager->get_attribute_set( $set_id );
	$fields = $manager->get_fields( $set_id );
}

$available_categories = $manager->get_available_categories();
?>

<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php echo $set_id > 0 ? __( 'Edit Attribute Set', 'dokan-category-attributes' ) : __( 'Add Attribute Set', 'dokan-category-attributes' ); ?>
	</h1>
	
	<a href="<?php echo admin_url( 'admin.php?page=dokan-category-attributes' ); ?>" class="page-title-action">
		<?php _e( 'Back to List', 'dokan-category-attributes' ); ?>
	</a>
	
	<hr class="wp-header-end">
	
	<form method="post" id="dca-builder-form">
		<?php wp_nonce_field( 'dca_save_set' ); ?>
		<input type="hidden" name="dca_save_attribute_set" value="1">
		<input type="hidden" name="deleted_fields" id="deleted-fields" value="">
		
		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<!-- Main Content -->
				<div id="post-body-content">
					<div class="postbox">
						<div class="postbox-header">
							<h2><?php _e( 'Attribute Set Configuration', 'dokan-category-attributes' ); ?></h2>
						</div>
						<div class="inside">
							<table class="form-table">
								<tr>
									<th><label for="set-name"><?php _e( 'Set Name', 'dokan-category-attributes' ); ?> *</label></th>
									<td>
										<input type="text" 
											   id="set-name" 
											   name="set_name" 
											   value="<?php echo $set ? esc_attr( $set->name ) : ''; ?>" 
											   class="regular-text" 
											   required>
									</td>
								</tr>
								<tr>
									<th><label for="set-slug"><?php _e( 'Slug', 'dokan-category-attributes' ); ?> *</label></th>
									<td>
										<input type="text" 
											   id="set-slug" 
											   name="set_slug" 
											   value="<?php echo $set ? esc_attr( $set->slug ) : ''; ?>" 
											   class="regular-text" 
											   required>
										<p class="description"><?php _e( 'Unique identifier (lowercase, no spaces)', 'dokan-category-attributes' ); ?></p>
									</td>
								</tr>
								<tr>
									<th><label for="set-icon"><?php _e( 'Dashicon', 'dokan-category-attributes' ); ?></label></th>
									<td>
										<input type="text" 
											   id="set-icon" 
											   name="set_icon" 
											   value="<?php echo $set ? esc_attr( $set->icon ) : 'admin-generic'; ?>" 
											   class="regular-text">
										<p class="description">
											<?php _e( 'Dashicon name (e.g., "admin-users", "camera", "art")', 'dokan-category-attributes' ); ?>
											- <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank"><?php _e( 'Browse icons', 'dokan-category-attributes' ); ?></a>
										</p>
									</td>
								</tr>
							</table>
						</div>
					</div>
					
					<!-- Fields Builder -->
					<div class="postbox">
						<div class="postbox-header">
							<h2><?php _e( 'Fields', 'dokan-category-attributes' ); ?></h2>
						</div>
						<div class="inside">
							<div id="dca-fields-container">
								<?php if ( ! empty( $fields ) ) : 
									foreach ( $fields as $index => $field ) : ?>
										<?php include 'field-row-template.php'; ?>
									<?php endforeach;
								endif; ?>
							</div>
							
							<button type="button" class="button button-primary" id="dca-add-field">
								<span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
								<?php _e( 'Add Field', 'dokan-category-attributes' ); ?>
							</button>
						</div>
					</div>
				</div>
				
				<!-- Sidebar -->
				<div id="postbox-container-1" class="postbox-container">
					<div class="postbox">
						<div class="postbox-header">
							<h2><?php _e( 'Publish', 'dokan-category-attributes' ); ?></h2>
						</div>
						<div class="inside">
							<div class="submitbox">
								<div id="minor-publishing">
									<div class="misc-pub-section">
										<label for="set-status"><?php _e( 'Status:', 'dokan-category-attributes' ); ?></label>
										<select name="set_status" id="set-status">
											<option value="active" <?php selected( $set ? $set->status : 'active', 'active' ); ?>>
												<?php _e( 'Active', 'dokan-category-attributes' ); ?>
											</option>
											<option value="draft" <?php selected( $set ? $set->status : '', 'draft' ); ?>>
												<?php _e( 'Draft', 'dokan-category-attributes' ); ?>
											</option>
										</select>
									</div>
									
									<div class="misc-pub-section">
										<label for="set-priority"><?php _e( 'Display Priority:', 'dokan-category-attributes' ); ?></label>
										<input type="number" 
											   id="set-priority" 
											   name="set_priority" 
											   value="<?php echo $set ? esc_attr( $set->priority ) : '10'; ?>" 
											   min="0" 
											   step="1" 
											   style="width: 80px;">
										<p class="description"><?php _e( 'Lower numbers display first', 'dokan-category-attributes' ); ?></p>
									</div>
								</div>
								
								<div id="major-publishing-actions">
									<div id="publishing-action">
										<button type="submit" class="button button-primary button-large">
											<?php echo $set_id > 0 ? __( 'Update', 'dokan-category-attributes' ) : __( 'Publish', 'dokan-category-attributes' ); ?>
										</button>
									</div>
									<div class="clear"></div>
								</div>
							</div>
						</div>
					</div>
					
					<div class="postbox">
						<div class="postbox-header">
							<h2><?php _e( 'Categories', 'dokan-category-attributes' ); ?></h2>
						</div>
						<div class="inside">
							<p><?php _e( 'Select which store categories should see this attribute set:', 'dokan-category-attributes' ); ?></p>
							<?php foreach ( $available_categories as $slug => $name ) : ?>
								<label style="display: block; margin: 5px 0;">
									<input type="checkbox" 
										   name="set_categories[]" 
										   value="<?php echo esc_attr( $slug ); ?>"
										   <?php checked( $set && is_array( $set->categories ) && in_array( $slug, $set->categories ) ); ?>>
									<?php echo esc_html( $name ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>

<!-- Field Row Template (for new fields) -->
<script type="text/html" id="dca-field-template">
	<?php 
	$field = null; 
	$index = '{{INDEX}}';
	include 'field-row-template.php'; 
	?>
</script>

<script>
jQuery(document).ready(function($) {
	var fieldIndex = <?php echo count( $fields ); ?>;
	var deletedFields = [];
	
	// Add new field
	$('#dca-add-field').on('click', function() {
		var template = $('#dca-field-template').html();
		template = template.replace(/{{INDEX}}/g, fieldIndex);
		$('#dca-fields-container').append(template);
		fieldIndex++;
	});
	
	// Remove field
	$(document).on('click', '.dca-remove-field', function() {
		if (!confirm('<?php _e( 'Remove this field?', 'dokan-category-attributes' ); ?>')) {
			return;
		}
		
		var $row = $(this).closest('.dca-field-row');
		var fieldId = $row.find('input[name*="[id]"]').val();
		
		if (fieldId) {
			deletedFields.push(fieldId);
			$('#deleted-fields').val(deletedFields.join(','));
		}
		
		$row.remove();
		updateFieldOrders();
	});
	
	// Auto-generate slug from name
	$('#set-name').on('blur', function() {
		if (!$('#set-slug').val()) {
			var slug = $(this).val().toLowerCase()
				.replace(/[^a-z0-9]+/g, '_')
				.replace(/^_+|_+$/g, '');
			$('#set-slug').val(slug);
		}
	});
	
	// Auto-generate field name from label
	$(document).on('blur', '.dca-field-label', function() {
		var $row = $(this).closest('.dca-field-row');
		var $nameInput = $row.find('.dca-field-name');
		
		if (!$nameInput.val()) {
			var name = $(this).val().toLowerCase()
				.replace(/[^a-z0-9]+/g, '_')
				.replace(/^_+|_+$/g, '');
			$nameInput.val(name);
		}
	});
	
	// Sortable fields
	$('#dca-fields-container').sortable({
		handle: '.dca-field-handle',
		placeholder: 'dca-field-placeholder',
		stop: function() {
			updateFieldOrders();
		}
	});
	
	function updateFieldOrders() {
		$('#dca-fields-container .dca-field-row').each(function(index) {
			$(this).find('.dca-field-order').val(index);
		});
	}
	
	// Toggle field options visibility based on field type
	$(document).on('change', '.dca-field-type', function() {
		var $row = $(this).closest('.dca-field-row');
		var type = $(this).val();
		var $optionsRow = $row.find('.dca-field-options-row');
		
		if (['select', 'radio', 'checkbox', 'number'].includes(type)) {
			$optionsRow.show();
			
			// Update placeholder
			var placeholder = type === 'number' 
				? 'min=0|max=100|step=1' 
				: 'Option 1\nOption 2\nvalue:Label';
			$optionsRow.find('textarea').attr('placeholder', placeholder);
		} else {
			$optionsRow.hide();
		}
	});
});
</script>

<style>
.dca-field-row {
	background: #fff;
	border: 1px solid #ccd0d4;
	margin-bottom: 10px;
	padding: 15px;
	position: relative;
}
.dca-field-row:hover {
	border-color: #999;
}
.dca-field-handle {
	cursor: move;
	position: absolute;
	top: 15px;
	left: 15px;
	color: #999;
}
.dca-field-content {
	margin-left: 35px;
}
.dca-field-placeholder {
	background: #f0f0f1;
	border: 2px dashed #c3c4c7;
	height: 100px;
	margin-bottom: 10px;
}
.dca-remove-field {
	position: absolute;
	top: 15px;
	right: 15px;
	color: #b32d2e;
}
.dca-field-options-row {
	margin-top: 10px;
}
</style>
