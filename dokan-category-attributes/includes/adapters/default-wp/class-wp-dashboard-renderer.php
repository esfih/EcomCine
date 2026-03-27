<?php
/**
 * Default WP Dashboard Renderer — CPT-based implementation (Phase 2).
 *
 * Renders the same HTML form fields as DCA_Dashboard_Fields but reads the
 * attribute set and field definitions from the 'dca_attribute_set' /
 * 'dca_attribute_field' CPTs via DCA_WP_CPT_Storage.
 *
 * This renderer does NOT self-register any hooks.  The caller is responsible
 * for hooking render_fields() and save_submitted_values() at the appropriate
 * points (Dokan dashboard hook, WP user profile, REST endpoint, etc.).
 *
 * save_submitted_values() accepts $post_data directly (no $_POST dependency)
 * making it testable and REST-compatible.
 *
 * Phase 3 note: rewiring the Dokan hook to this renderer (instead of the
 * legacy DCA_Dashboard_Fields instance) will be done in Phase 3.
 *
 * @package DCA\Adapters\DefaultWP
 * @since   1.1.0
 *
 * Remediation-Type: source-fix
 * Phase: 2 — Default WP Pilot Adapter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DCA_WP_Dashboard_Renderer
 *
 * @implements DCA_Dashboard_Renderer
 */
class DCA_WP_Dashboard_Renderer implements DCA_Dashboard_Renderer {

	/** @var DCA_WP_CPT_Storage */
	private $storage;

	public function __construct() {
		$this->storage = new DCA_WP_CPT_Storage();
	}

	/**
	 * @inheritdoc
	 *
	 * Outputs the same HTML structure as DCA_Dashboard_Fields::render_fields()
	 * so that existing Dokan dashboard CSS/JS continues to work unchanged.
	 * Reads set/field definitions from CPT storage.
	 *
	 * @param int   $vendor_id
	 * @param array $store_settings  Unused in Phase 2; reserved for Phase 3.
	 */
	public function render_fields( $vendor_id, array $store_settings = array() ) {
		$vendor_id = (int) $vendor_id;

		$attribute_sets = $this->storage->get_sets( array( 'status' => 'active' ) );
		if ( empty( $attribute_sets ) ) {
			return;
		}

		$vendor_categories = wp_get_object_terms(
			$vendor_id,
			'store_category',
			array( 'fields' => 'slugs' )
		);
		if ( is_wp_error( $vendor_categories ) ) {
			$vendor_categories = array();
		}

		foreach ( $attribute_sets as $set ) {
			$fields = $this->storage->get_fields_by_set( $set->id, array( 'location' => 'dashboard' ) );
			if ( empty( $fields ) ) {
				continue;
			}

			$categories_match = ! empty( array_intersect( $set->categories, $vendor_categories ) );
			$category_attr    = esc_attr( implode( ',', $set->categories ) );
			$initial_display  = $categories_match ? 'block' : 'none';
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
					$field_value = get_user_meta( $vendor_id, $field->field_name, true );
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
	 * @inheritdoc
	 *
	 * Sanitizes field values from $post_data and persists them to user meta.
	 * No dependency on $_POST — fully testable and REST-compatible.
	 *
	 * Returns summary array with 'saved' (field_name[]) and 'errors' (field_name[]).
	 *
	 * @param int   $vendor_id
	 * @param array $post_data  Raw submitted values keyed by field_name.
	 * @return array{saved: string[], errors: string[]}
	 */
	public function save_submitted_values( $vendor_id, array $post_data ) {
		$vendor_id      = (int) $vendor_id;
		$attribute_sets = $this->storage->get_sets( array( 'status' => 'active' ) );
		$saved          = array();
		$errors         = array();

		foreach ( $attribute_sets as $set ) {
			$fields = $this->storage->get_fields_by_set( $set->id );

			foreach ( $fields as $field ) {
				$key = $field->field_name;

				if ( array_key_exists( $key, $post_data ) ) {
					$value = $post_data[ $key ];

					switch ( $field->field_type ) {
						case 'checkbox':
							$value = is_array( $value )
								? array_map( 'sanitize_text_field', $value )
								: array();
							break;
						case 'textarea':
							$value = sanitize_textarea_field( (string) $value );
							break;
						case 'number':
							$value = floatval( $value );
							break;
						default:
							$value = sanitize_text_field( (string) $value );
							break;
					}

					$result = update_user_meta( $vendor_id, $key, $value );
					if ( false !== $result ) {
						$saved[] = $key;
					} else {
						$errors[] = $key;
					}
				} else {
					// Field absent from submission — treat as cleared.
					delete_user_meta( $vendor_id, $key );
					$saved[] = $key;
				}
			}
		}

		return array(
			'saved'  => $saved,
			'errors' => $errors,
		);
	}

	// -------------------------------------------------------------------------
	// Private rendering helpers
	// -------------------------------------------------------------------------

	/**
	 * Render the HTML input for a single field.
	 * Matches the same output as DCA_Dashboard_Fields::render_field_input().
	 *
	 * @param object $field
	 * @param mixed  $value  Current stored value.
	 */
	private function render_field_input( $field, $value ) {
		$field_id   = esc_attr( $field->field_name );
		$field_name = esc_attr( $field->field_name );
		$required   = $field->required ? 'required' : '';

		switch ( $field->field_type ) {
			case 'select':
				?>
				<select id="<?php echo $field_id; ?>"
						name="<?php echo $field_name; ?>"
						class="dokan-form-control"
						<?php echo $required; ?>>
					<option value=""><?php esc_html_e( 'Select...', 'dokan-category-attributes' ); ?></option>
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
								   <?php checked( in_array( $key, $values, true ) ); ?>>
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
						  <?php echo $required; ?>><?php echo esc_textarea( (string) $value ); ?></textarea>
				<?php
				break;

			case 'number':
				$min  = isset( $field->field_options['min'] )  ? $field->field_options['min']  : '';
				$max  = isset( $field->field_options['max'] )  ? $field->field_options['max']  : '';
				$step = isset( $field->field_options['step'] ) ? $field->field_options['step'] : '1';
				?>
				<input type="number"
					   id="<?php echo $field_id; ?>"
					   name="<?php echo $field_name; ?>"
					   class="dokan-form-control"
					   value="<?php echo esc_attr( (string) $value ); ?>"
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
					   value="<?php echo esc_attr( (string) $value ); ?>"
					   <?php echo $required; ?>>
				<?php
				break;
		}
	}
}
