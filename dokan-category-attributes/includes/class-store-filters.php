<?php
/**
 * Store Filters Handler
 * 
 * Adds category-specific filters to store listing page
 * 
 * @package Dokan_Category_Attributes
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DCA_Store_Filters {
	
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
		
		// Hook to display filters
		add_action( 'dokan_before_store_lists_filter_search', array( $this, 'render_filters' ) );
		
		// Hook to apply filters to query
		add_filter( 'dokan_seller_listing_args', array( $this, 'apply_filters' ), 10, 2 );
	}
	
	/**
	 * Render attribute filters
	 */
	public function render_filters() {
		// Get all active attribute sets
		$attribute_sets = $this->manager->get_attribute_sets( array( 'status' => 'active' ) );
		
		if ( empty( $attribute_sets ) ) {
			return;
		}
		
		foreach ( $attribute_sets as $set ) {
			// Get fields for filters
			$fields = $this->manager->get_fields( $set->id, array( 'location' => 'filters' ) );
			
			if ( empty( $fields ) ) {
				continue;
			}
			
			// Prepare category data attribute
			$category_attr = esc_attr( implode( ',', $set->categories ) );
			
			?>
			<div class="filter-group dca-filter-section" 
				 data-category="<?php echo $category_attr; ?>" 
				 style="display: none;">
				
				<h4 class="filter-group-title">
					<?php if ( $set->icon ) : ?>
						<span class="dashicons dashicons-<?php echo esc_attr( $set->icon ); ?>"></span>
					<?php endif; ?>
					<?php echo esc_html( $set->name ); ?>
				</h4>
				
				<?php foreach ( $fields as $field ) : 
					$current_value = isset( $_GET[ $field->field_name ] ) ? sanitize_text_field( $_GET[ $field->field_name ] ) : '';
					?>
					
					<div class="filter-item dca-filter-field" 
						 data-category="<?php echo $category_attr; ?>" 
						 style="display: none;">
						
						<label for="<?php echo esc_attr( 'filter_' . $field->field_name ); ?>">
							<?php if ( $field->field_icon ) : ?>
								<span class="dashicons dashicons-<?php echo esc_attr( $field->field_icon ); ?>"></span>
							<?php endif; ?>
							<?php echo esc_html( $field->field_label ); ?>
						</label>
						
						<?php $this->render_filter_input( $field, $current_value ); ?>
					</div>
					
				<?php endforeach; ?>
			</div>
			<?php
		}
	}
	
	/**
	 * Render filter input field
	 * 
	 * @param object $field Field object
	 * @param mixed $current_value Current filter value
	 */
	private function render_filter_input( $field, $current_value ) {
		$field_id = esc_attr( 'filter_' . $field->field_name );
		$field_name = esc_attr( $field->field_name );
		
		switch ( $field->field_type ) {
			case 'select':
				?>
				<select id="<?php echo $field_id; ?>" 
						name="<?php echo $field_name; ?>" 
						class="dca-filter-select">
					<option value="">All</option>
					<?php if ( ! empty( $field->field_options ) ) : 
						foreach ( $field->field_options as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_value, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach;
					endif; ?>
				</select>
				<?php
				break;
				
			case 'checkbox':
				$current_values = isset( $_GET[ $field_name ] ) ? (array) $_GET[ $field_name ] : array();
				if ( ! empty( $field->field_options ) ) :
					foreach ( $field->field_options as $key => $label ) : ?>
						<label style="display: block; margin: 3px 0;">
							<input type="checkbox" 
								   name="<?php echo $field_name; ?>[]" 
								   value="<?php echo esc_attr( $key ); ?>" 
								   <?php checked( in_array( $key, $current_values ) ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach;
				endif;
				break;
				
			case 'number':
				?>
				<input type="number" 
					   id="<?php echo $field_id; ?>" 
					   name="<?php echo $field_name; ?>" 
					   value="<?php echo esc_attr( $current_value ); ?>" 
					   class="dca-filter-input" 
					   placeholder="Any">
				<?php
				break;
				
			case 'text':
			default:
				?>
				<input type="text" 
					   id="<?php echo $field_id; ?>" 
					   name="<?php echo $field_name; ?>" 
					   value="<?php echo esc_attr( $current_value ); ?>" 
					   class="dca-filter-input" 
					   placeholder="Any">
				<?php
				break;
		}
	}
	
	/**
	 * Apply attribute filters to seller query
	 * 
	 * @param array $args Query args
	 * @param array $filter_data Filter data
	 * @return array Modified args
	 */
	public function apply_filters( $args, $filter_data ) {
		$meta_query = isset( $args['meta_query'] ) ? $args['meta_query'] : array();
		
		// Get all active attribute sets
		$attribute_sets = $this->manager->get_attribute_sets( array( 'status' => 'active' ) );
		
		foreach ( $attribute_sets as $set ) {
			$fields = $this->manager->get_fields( $set->id );
			
			foreach ( $fields as $field ) {
				if ( isset( $_GET[ $field->field_name ] ) && ! empty( $_GET[ $field->field_name ] ) ) {
					$value = $_GET[ $field->field_name ];
					
					if ( is_array( $value ) ) {
						// Multiple values (checkbox)
						$meta_query[] = array(
							'key' => $field->field_name,
							'value' => $value,
							'compare' => 'IN',
						);
					} else {
						// Single value
						$meta_query[] = array(
							'key' => $field->field_name,
							'value' => sanitize_text_field( $value ),
							'compare' => '=',
						);
					}
				}
			}
		}
		
		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}
		
		return $args;
	}
}
