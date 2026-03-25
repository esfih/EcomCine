<?php
/**
 * Dashboard Fields Handler
 * 
 * Renders attribute fields in vendor dashboard settings
 * 
 * @package Dokan_Category_Attributes
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCA_Dashboard_Fields {
	
	/**
	 * Attribute manager instance
	 * 
	 * @var DCA_Attribute_Manager
	 */
	private $manager;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->manager = new DCA_Attribute_Manager();
		
		// Hook to display fields in dashboard
		add_action( 'dokan_settings_after_store_phone', array( $this, 'render_fields' ), 10, 2 );
		
		// Hook to save field values
		add_action( 'dokan_store_profile_saved', array( $this, 'save_fields' ), 10, 1 );
	}
	
	/**
	 * Render attribute fields in vendor dashboard
	 * 
	 * @param int $store_id Store/Vendor ID
	 * @param array $store_settings Store settings
	 */
	public function render_fields( $store_id, $store_settings ) {
		// Get all active attribute sets
		$attribute_sets = $this->manager->get_attribute_sets( array( 'status' => 'active' ) );
		
		if ( empty( $attribute_sets ) ) {
			return;
		}
		
		// Get vendor categories
		$vendor_categories = wp_get_object_terms( $store_id, 'store_category', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $vendor_categories ) ) {
			$vendor_categories = array();
		}
		
		// Render each attribute set
		foreach ( $attribute_sets as $set ) {
			// Check if this set applies to vendor's categories
			$categories_match = ! empty( array_intersect( $set->categories, $vendor_categories ) );
			
			// Get fields for this set
			$fields = $this->manager->get_fields( $set->id, array( 'location' => 'dashboard' ) );
			
			if ( empty( $fields ) ) {
				continue;
			}
			
			// Prepare category data attribute
			$category_attr = esc_attr( implode( ',', $set->categories ) );
			
			// Determine initial display state
			$initial_display = $categories_match ? 'block' : 'none';
			
			?>
			<div class="dokan-form-group dca-attribute-section" 
				 data-category="<?php echo $category_attr; ?>" 
				 style="display: <?php echo $initial_display; ?>;">
				
				<h3 style="margin-top: 20px; color: #f0ad4e; font-size: 16px;">
					<?php if ( $set->icon ) : ?>
						<span class="dashicons dashicons-<?php echo esc_attr( $set->icon ); ?>"></span>
					<?php endif; ?>
					<?php echo esc_html( $set->name ); ?>
				</h3>
				
				<?php foreach ( $fields as $field ) : 
					$field_value = get_user_meta( $store_id, $field->field_name, true );
					?>
					
					<div class="dokan-form-group dca-field-wrapper" 
						 data-category="<?php echo $category_attr; ?>" 
						 style="display: <?php echo $initial_display; ?>;">
						
						<label class="dokan-w3 dokan-control-label" for="<?php echo esc_attr( $field->field_name ); ?>">
							<?php if ( $field->field_icon ) : ?>
								<span class="dashicons dashicons-<?php echo esc_attr( $field->field_icon ); ?>"></span>
							<?php endif; ?>
							<?php echo esc_html( $field->field_label ); ?>
							<?php if ( $field->required ) : ?>
								<span class="required">*</span>
							<?php endif; ?>
						</label>
						
						<div class="dokan-w5 dokan-text-left">
							<?php $this->render_field_input( $field, $field_value ); ?>
						</div>
					</div>
					
				<?php endforeach; ?>
			</div>
			<?php
		}
	}
	
	/**
	 * Render individual field input
	 * 
	 * @param object $field Field object
	 * @param mixed $value Current value
	 */
	private function render_field_input( $field, $value ) {
		$field_id = esc_attr( $field->field_name );
		$field_name = esc_attr( $field->field_name );
		$required = $field->required ? 'required' : '';
		
		switch ( $field->field_type ) {
			case 'select':
				?>
				<select id="<?php echo $field_id; ?>" 
						name="<?php echo $field_name; ?>" 
						class="dokan-form-control" 
						<?php echo $required; ?>>
					<option value="">Select...</option>
					<?php if ( ! empty( $field->field_options ) ) : 
						foreach ( $field->field_options as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach;
					endif; ?>
				</select>
				<?php
				break;
				
			case 'radio':
				if ( ! empty( $field->field_options ) ) :
					foreach ( $field->field_options as $key => $label ) : ?>
						<label style="display: block; margin: 5px 0;">
							<input type="radio" 
								   name="<?php echo $field_name; ?>" 
								   value="<?php echo esc_attr( $key ); ?>" 
								   <?php checked( $value, $key ); ?>
								   <?php echo $required; ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach;
				endif;
				break;
				
			case 'checkbox':
				$values = is_array( $value ) ? $value : array();
				if ( ! empty( $field->field_options ) ) :
					foreach ( $field->field_options as $key => $label ) : ?>
						<label style="display: block; margin: 5px 0;">
							<input type="checkbox" 
								   name="<?php echo $field_name; ?>[]" 
								   value="<?php echo esc_attr( $key ); ?>" 
								   <?php checked( in_array( $key, $values ) ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach;
				endif;
				break;
				
			case 'textarea':
				?>
				<textarea id="<?php echo $field_id; ?>" 
						  name="<?php echo $field_name; ?>" 
						  class="dokan-form-control" 
						  rows="4" 
						  <?php echo $required; ?>><?php echo esc_textarea( $value ); ?></textarea>
				<?php
				break;
				
			case 'number':
				$min = isset( $field->field_options['min'] ) ? $field->field_options['min'] : '';
				$max = isset( $field->field_options['max'] ) ? $field->field_options['max'] : '';
				$step = isset( $field->field_options['step'] ) ? $field->field_options['step'] : '1';
				?>
				<input type="number" 
					   id="<?php echo $field_id; ?>" 
					   name="<?php echo $field_name; ?>" 
					   class="dokan-form-control" 
					   value="<?php echo esc_attr( $value ); ?>" 
					   min="<?php echo esc_attr( $min ); ?>" 
					   max="<?php echo esc_attr( $max ); ?>" 
					   step="<?php echo esc_attr( $step ); ?>" 
					   <?php echo $required; ?>>
				<?php
				break;
				
			case 'text':
			default:
				?>
				<input type="text" 
					   id="<?php echo $field_id; ?>" 
					   name="<?php echo $field_name; ?>" 
					   class="dokan-form-control" 
					   value="<?php echo esc_attr( $value ); ?>" 
					   <?php echo $required; ?>>
				<?php
				break;
		}
	}
	
	/**
	 * Save attribute field values
	 * 
	 * @param int $store_id Store/Vendor ID
	 */
	public function save_fields( $store_id ) {
		// Get all active attribute sets
		$attribute_sets = $this->manager->get_attribute_sets( array( 'status' => 'active' ) );
		
		foreach ( $attribute_sets as $set ) {
			$fields = $this->manager->get_fields( $set->id );
			
			foreach ( $fields as $field ) {
				if ( isset( $_POST[ $field->field_name ] ) ) {
					$value = $_POST[ $field->field_name ];
					
					// Sanitize based on field type
					if ( $field->field_type === 'checkbox' && is_array( $value ) ) {
						$value = array_map( 'sanitize_text_field', $value );
					} elseif ( $field->field_type === 'textarea' ) {
						$value = sanitize_textarea_field( $value );
					} elseif ( $field->field_type === 'number' ) {
						$value = floatval( $value );
					} else {
						$value = sanitize_text_field( $value );
					}
					
					update_user_meta( $store_id, $field->field_name, $value );
				} else {
					// Field not submitted - delete if exists
					delete_user_meta( $store_id, $field->field_name );
				}
			}
		}
	}
}
