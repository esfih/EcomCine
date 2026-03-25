<?php
/**
 * Field Row Template
 * Used in field builder for each attribute field
 * 
 * @package Dokan_Category_Attributes
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// $field and $index should be set by parent template
?>
<div class="dca-field-row">
	<span class="dashicons dashicons-menu dca-field-handle"></span>
	<a href="#" class="dca-remove-field">
		<span class="dashicons dashicons-no-alt"></span>
	</a>
	
	<div class="dca-field-content">
		<input type="hidden" 
			   name="fields[<?php echo $index; ?>][id]" 
			   value="<?php echo $field ? esc_attr( $field->id ) : ''; ?>">
		
		<input type="hidden" 
			   class="dca-field-order" 
			   name="fields[<?php echo $index; ?>][order]" 
			   value="<?php echo $field ? esc_attr( $field->display_order ) : $index; ?>">
		
		<div class="dca-field-row-inner">
			<table class="form-table" style="margin: 0;">
				<tr>
					<td style="width: 50%; padding-right: 10px;">
						<label><?php _e( 'Field Label', 'dokan-category-attributes' ); ?> *</label>
						<input type="text" 
							   class="widefat dca-field-label" 
							   name="fields[<?php echo $index; ?>][label]" 
							   value="<?php echo $field ? esc_attr( $field->field_label ) : ''; ?>" 
							   placeholder="<?php _e( 'e.g., Eye Color', 'dokan-category-attributes' ); ?>" 
							   required>
					</td>
					<td style="width: 50%; padding-left: 10px;">
						<label><?php _e( 'Field Name (slug)', 'dokan-category-attributes' ); ?> *</label>
						<input type="text" 
							   class="widefat dca-field-name" 
							   name="fields[<?php echo $index; ?>][name]" 
							   value="<?php echo $field ? esc_attr( $field->field_name ) : ''; ?>" 
							   placeholder="<?php _e( 'e.g., eye_color', 'dokan-category-attributes' ); ?>" 
							   required>
					</td>
				</tr>
				<tr>
					<td style="padding-right: 10px;">
						<label><?php _e( 'Field Type', 'dokan-category-attributes' ); ?></label>
						<select class="widefat dca-field-type" name="fields[<?php echo $index; ?>][type]">
							<option value="select" <?php selected( $field ? $field->field_type : 'select', 'select' ); ?>>
								<?php _e( 'Select Dropdown', 'dokan-category-attributes' ); ?>
							</option>
							<option value="text" <?php selected( $field ? $field->field_type : '', 'text' ); ?>>
								<?php _e( 'Text Input', 'dokan-category-attributes' ); ?>
							</option>
							<option value="textarea" <?php selected( $field ? $field->field_type : '', 'textarea' ); ?>>
								<?php _e( 'Textarea', 'dokan-category-attributes' ); ?>
							</option>
							<option value="number" <?php selected( $field ? $field->field_type : '', 'number' ); ?>>
								<?php _e( 'Number', 'dokan-category-attributes' ); ?>
							</option>
							<option value="radio" <?php selected( $field ? $field->field_type : '', 'radio' ); ?>>
								<?php _e( 'Radio Buttons', 'dokan-category-attributes' ); ?>
							</option>
							<option value="checkbox" <?php selected( $field ? $field->field_type : '', 'checkbox' ); ?>>
								<?php _e( 'Checkboxes', 'dokan-category-attributes' ); ?>
							</option>
						</select>
					</td>
					<td style="padding-left: 10px;">
						<label><?php _e( 'Icon (optional)', 'dokan-category-attributes' ); ?></label>
						<input type="text" 
							   class="widefat" 
							   name="fields[<?php echo $index; ?>][icon]" 
							   value="<?php echo $field ? esc_attr( $field->field_icon ) : ''; ?>" 
							   placeholder="<?php _e( 'e.g., visibility', 'dokan-category-attributes' ); ?>">
					</td>
				</tr>
			</table>
			
			<div class="dca-field-options-row" style="<?php echo ( $field && in_array( $field->field_type, array( 'select', 'radio', 'checkbox', 'number' ) ) ) ? '' : 'display:none;'; ?>">
				<label><?php _e( 'Field Options', 'dokan-category-attributes' ); ?></label>
				<textarea class="widefat" 
						  name="fields[<?php echo $index; ?>][options]" 
						  rows="4" 
						  placeholder="<?php _e( 'Option 1\nOption 2\nvalue:Label', 'dokan-category-attributes' ); ?>"><?php 
					if ( $field && ! empty( $field->field_options ) ) {
						if ( is_array( $field->field_options ) ) {
							foreach ( $field->field_options as $key => $value ) {
								echo $key === $value ? esc_textarea( $value ) : esc_textarea( $key . ':' . $value );
								echo "\n";
							}
						}
					}
				?></textarea>
				<p class="description">
					<?php _e( 'For select/radio/checkbox: One option per line. Use "value:label" format or just the value.', 'dokan-category-attributes' ); ?><br>
					<?php _e( 'For number: Use format "min=0|max=100|step=1"', 'dokan-category-attributes' ); ?>
				</p>
			</div>
			
			<div style="margin-top: 10px;">
				<label style="margin-right: 15px;">
					<input type="checkbox" 
						   name="fields[<?php echo $index; ?>][required]" 
						   value="1"
						   <?php checked( $field ? $field->required : 0, 1 ); ?>>
					<?php _e( 'Required', 'dokan-category-attributes' ); ?>
				</label>
				
				<label style="margin-right: 15px;">
					<input type="checkbox" 
						   name="fields[<?php echo $index; ?>][show_dashboard]" 
						   value="1"
						   <?php checked( $field ? $field->show_in_dashboard : 1, 1 ); ?>>
					<?php _e( 'Show in Dashboard', 'dokan-category-attributes' ); ?>
				</label>
				
				<label style="margin-right: 15px;">
					<input type="checkbox" 
						   name="fields[<?php echo $index; ?>][show_public]" 
						   value="1"
						   <?php checked( $field ? $field->show_in_public : 1, 1 ); ?>>
					<?php _e( 'Show on Public Store', 'dokan-category-attributes' ); ?>
				</label>
				
				<label>
					<input type="checkbox" 
						   name="fields[<?php echo $index; ?>][show_filters]" 
						   value="1"
						   <?php checked( $field ? $field->show_in_filters : 1, 1 ); ?>>
					<?php _e( 'Show in Store Filters', 'dokan-category-attributes' ); ?>
				</label>
			</div>
		</div>
	</div>
</div>
