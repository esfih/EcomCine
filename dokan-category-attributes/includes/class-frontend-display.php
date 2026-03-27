<?php
/**
 * Frontend Display Handler
 * 
 * Displays attributes on public vendor store pages
 * 
 * @package Dokan_Category_Attributes
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCA_Frontend_Display {
	
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
		
		// Hook to display on public vendor pages
		add_action( 'dokan_store_profile_frame_after', array( $this, 'display_attributes' ), 5, 2 );
	}
	
	/**
	 * Display attributes on vendor store page
	 * 
	 * @param object $store_user Vendor user object
	 * @param array $store_info Store information
	 */
	public function display_attributes( $store_user, $store_info ) {
		$vendor_id = $store_user->ID;
		
		// Get vendor categories
		$vendor_categories = wp_get_object_terms( $vendor_id, 'store_category', array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $vendor_categories ) ) {
			return;
		}
		
		// Get all active attribute sets
		$attribute_sets = $this->manager->get_attribute_sets( array( 'status' => 'active' ) );
		
		if ( empty( $attribute_sets ) ) {
			return;
		}
		
		// Start wrapper
		echo '<div class="vendor-custom-attributes-wrapper">';
		
		foreach ( $attribute_sets as $set ) {
			// Check if this set applies to vendor's categories
			if ( empty( array_intersect( $set->categories, $vendor_categories ) ) ) {
				continue;
			}
			
			// Get fields for public display
			$fields = $this->manager->get_fields( $set->id, array( 'location' => 'public' ) );
			
			if ( empty( $fields ) ) {
				continue;
			}
			
			// Collect field values
			$has_values = false;
			$field_data = array();
			
			foreach ( $fields as $field ) {
				$value = get_user_meta( $vendor_id, $field->field_name, true );
				if ( ! empty( $value ) ) {
					$has_values = true;
					$field_data[] = array(
						'field' => $field,
						'value' => $value,
					);
				}
			}
			
			// Only display section if there are values
			if ( ! $has_values ) {
				continue;
			}
			
			// Render section
			?>
			<div class="vendor-attribute-section">
				<h3 class="vendor-section-title">
					<?php if ( $set->icon ) : ?>
						<span class="dashicons dashicons-<?php echo esc_attr( $set->icon ); ?>"></span>
					<?php endif; ?>
					<?php echo esc_html( $set->name ); ?>
				</h3>
				
				<div class="vendor-attributes-grid">
					<?php foreach ( $field_data as $item ) : 
						$field = $item['field'];
						$value = $item['value'];
						$display_value = $this->format_display_value( $field, $value );
						?>
						<div class="vendor-attribute-item">
							<span class="attribute-label">
								<?php if ( $field->field_icon ) : ?>
									<span class="dashicons dashicons-<?php echo esc_attr( $field->field_icon ); ?>"></span>
								<?php endif; ?>
								<?php echo esc_html( $field->field_label ); ?>:
							</span>
							<span class="attribute-value"><?php echo esc_html( $display_value ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php
		}
		
		// End wrapper
		echo '</div>';
	}
	
	/**
	 * Format field value for display
	 * 
	 * @param object $field Field object
	 * @param mixed $value Field value
	 * @return string
	 */
	private function format_display_value( $field, $value ) {
		// For select/radio, get label from options
		if ( in_array( $field->field_type, array( 'select', 'radio' ) ) ) {
			if ( isset( $field->field_options[ $value ] ) ) {
				return $field->field_options[ $value ];
			}
		}
		
		// For checkbox, get multiple labels
		if ( $field->field_type === 'checkbox' && is_array( $value ) ) {
			$labels = array();
			foreach ( $value as $val ) {
				if ( isset( $field->field_options[ $val ] ) ) {
					$labels[] = $field->field_options[ $val ];
				}
			}
			return implode( ', ', $labels );
		}
		
		// Default: return value as-is
		return $value;
	}
}
