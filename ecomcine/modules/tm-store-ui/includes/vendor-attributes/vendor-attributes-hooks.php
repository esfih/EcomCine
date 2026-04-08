<?php
/**
 * Vendor Attributes — Display & Save Hooks
 *
 * All WordPress hooks that render and save category-specific vendor attributes.
 * Option lists come from vendor-attribute-sets.php (loaded before this file).
 *
 * CONTENTS
 * ─────────────────────────────────────────────────────────────────────────────
 * 1. PROFILE DISPLAY   dokan_store_profile_bottom_drawer
 *    1a. Demographic & Availability      (priority 3 – all vendors)
 *    1b. Physical Attributes + Cameraman (priority 5 – model / artist / cameraman)
 *
 * 2. DASHBOARD SETTINGS FORM FIELDS  dokan_settings_after_store_phone
 *    2a. Demographic & Availability form (priority 5  – all vendors)
 *    2b. Cameraman Equipment form        (priority 10 – cameraman only)
 *
 * 3. PROFILE SAVE   dokan_store_profile_saved
 *    Persists physical, cameraman, and demographic fields on form submit.
 *
 * @package Astra Child
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'ecomcine_profile_get_drawer_section_label' ) ) {
	/**
	 * Resolve an admin-managed bottom drawer section label.
	 *
	 * @param string $section_key Section key.
	 * @param string $fallback    Default label.
	 * @return string
	 */
	function ecomcine_profile_get_drawer_section_label( string $section_key, string $fallback ): string {
		if ( class_exists( 'EcomCine_Admin_Settings', false ) && method_exists( 'EcomCine_Admin_Settings', 'get_profile_drawer_sections' ) ) {
			$sections = EcomCine_Admin_Settings::get_profile_drawer_sections();
			if ( isset( $sections[ $section_key ]['label'] ) && is_string( $sections[ $section_key ]['label'] ) ) {
				$label = trim( $sections[ $section_key ]['label'] );
				if ( '' !== $label ) {
					return $label;
				}
			}
		}

		return $fallback;
	}
}

if ( ! function_exists( 'ecomcine_profile_get_social_metric_config_map' ) ) {
	/**
	 * Resolve admin-managed social metric configuration indexed by platform.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function ecomcine_profile_get_social_metric_config_map(): array {
		if ( class_exists( 'EcomCine_Admin_Settings', false ) && method_exists( 'EcomCine_Admin_Settings', 'get_profile_social_metric_map' ) ) {
			return EcomCine_Admin_Settings::get_profile_social_metric_map();
		}

		return array();
	}
}

if ( ! function_exists( 'ecomcine_profile_field_has_value' ) ) {
	/**
	 * Determine whether a profile field value should be considered non-empty.
	 *
	 * @param mixed $value Raw user meta value.
	 * @return bool
	 */
	function ecomcine_profile_field_has_value( $value ): bool {
		if ( is_array( $value ) ) {
			return ! empty(
				array_filter(
					array_map(
						static function( $item ) {
							if ( is_scalar( $item ) ) {
								return trim( (string) $item );
							}

							return '';
						},
						$value
					)
				)
			);
		}

		if ( null === $value || false === $value ) {
			return false;
		}

		if ( is_string( $value ) ) {
			return '' !== trim( $value );
		}

		return true;
	}
}

if ( ! function_exists( 'ecomcine_profile_get_dynamic_category_sections' ) ) {
	/**
	 * Build dynamic bottom drawer sections from assigned categories with custom fields.
	 *
	 * @param int  $vendor_id Vendor ID.
	 * @param bool $is_owner  Whether current viewer can edit.
	 * @return array<int,array<string,mixed>>
	 */
	function ecomcine_profile_get_dynamic_category_sections( int $vendor_id, bool $is_owner ): array {
		if ( $vendor_id < 1 || ! class_exists( 'EcomCine_Person_Category_Registry', false ) ) {
			return array();
		}

		$sections          = array();
		$excluded_slugs    = array( 'demographic-availability' );
		$categories        = EcomCine_Person_Category_Registry::get_for_person( $vendor_id );

		foreach ( $categories as $category ) {
			$category_id = isset( $category['id'] ) ? (int) $category['id'] : 0;
			$slug        = isset( $category['slug'] ) ? sanitize_title( (string) $category['slug'] ) : '';
			$name        = isset( $category['name'] ) ? trim( (string) $category['name'] ) : '';

			if ( $category_id < 1 || '' === $slug || '' === $name || in_array( $slug, $excluded_slugs, true ) ) {
				continue;
			}

			$fields = EcomCine_Person_Category_Registry::get_fields_for_category( $category_id, true );
			if ( empty( $fields ) ) {
				continue;
			}

			$field_rows = array();
			$has_value  = false;

			foreach ( $fields as $field ) {
				$field_key = isset( $field['field_key'] ) ? sanitize_key( (string) $field['field_key'] ) : '';
				if ( '' === $field_key ) {
					continue;
				}

				$current_value = get_user_meta( $vendor_id, $field_key, true );
				$field_type    = isset( $field['field_type'] ) ? sanitize_key( (string) $field['field_type'] ) : 'text';

				if ( 'checkbox' === $field_type && ! is_array( $current_value ) ) {
					$current_value = '' === (string) $current_value ? array() : array( (string) $current_value );
				}

				if ( ecomcine_profile_field_has_value( $current_value ) ) {
					$has_value = true;
				}

				$field_rows[] = array(
					'definition' => $field,
					'value'      => $current_value,
				);
			}

			if ( empty( $field_rows ) || ( ! $is_owner && ! $has_value ) ) {
				continue;
			}

			$icon_markup = '';
			$category_icon_url = EcomCine_Person_Category_Registry::get_category_icon_url( $category );
			if ( '' !== $category_icon_url ) {
				$icon_markup = '<img class="section-title-image" src="' . esc_url( $category_icon_url ) . '" alt="' . esc_attr( $name ) . '" />';
			} else {
				$icon_key = isset( $category['icon_key'] ) ? EcomCine_Person_Category_Registry::sanitize_icon_key( (string) $category['icon_key'] ) : '';
				if ( '' !== $icon_key && class_exists( 'TM_Icons' ) ) {
					$icon_markup = TM_Icons::svg( $icon_key, 'section-title-icon', $name );
				} else {
					$first_field = $field_rows[0]['definition'];
					$first_icon  = EcomCine_Person_Category_Registry::get_field_icon_url( $first_field );
					if ( '' !== $first_icon ) {
						$icon_markup = '<img class="section-title-image" src="' . esc_url( $first_icon ) . '" alt="' . esc_attr( $name ) . '" />';
					} elseif ( class_exists( 'TM_Icons' ) ) {
						$icon_markup = TM_Icons::svg( 'briefcase', 'section-title-icon', $name );
					}
				}
			}

			$sections[] = array(
				'category'    => $category,
				'fields'      => $field_rows,
				'section_id'  => 'category-section-' . sanitize_html_class( $slug ),
				'label'       => $name,
				'icon_markup' => $icon_markup,
			);
		}

		return $sections;
	}
}

if ( ! function_exists( 'ecomcine_profile_get_demographic_fallback_fields' ) ) {
	/**
	 * Provide fallback demographic field definitions until the registry category exists.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	function ecomcine_profile_get_demographic_fallback_fields(): array {
		return array(
			'demo_birth_date' => array(
				'field_key'   => 'demo_birth_date',
				'field_label' => 'Birth Date',
				'field_type'  => 'text',
				'options_map' => array(),
				'field_icon'  => '📅',
				'sort_order'  => 1,
			),
			'demo_ethnicity' => array(
				'field_key'   => 'demo_ethnicity',
				'field_label' => 'Ethnicity',
				'field_type'  => 'checkbox',
				'options_map' => array(
					'caucasian'        => 'Caucasian',
					'african'          => 'African',
					'asian'            => 'Asian',
					'hispanic'         => 'Hispanic/Latino',
					'middle_eastern'   => 'Middle Eastern',
					'native_american'  => 'Native American',
					'pacific_islander' => 'Pacific Islander',
				),
				'field_icon'  => '🌍',
				'sort_order'  => 2,
			),
			'demo_languages' => array(
				'field_key'   => 'demo_languages',
				'field_label' => 'Languages',
				'field_type'  => 'checkbox',
				'options_map' => array(
					'english'    => 'English',
					'spanish'    => 'Spanish',
					'french'     => 'French',
					'german'     => 'German',
					'italian'    => 'Italian',
					'portuguese' => 'Portuguese',
					'arabic'     => 'Arabic',
					'chinese'    => 'Chinese (Mandarin)',
					'japanese'   => 'Japanese',
					'korean'     => 'Korean',
					'hindi'      => 'Hindi',
					'russian'    => 'Russian',
				),
				'field_icon'  => '💬',
				'sort_order'  => 3,
			),
			'demo_availability' => array(
				'field_key'   => 'demo_availability',
				'field_label' => 'Availability',
				'field_type'  => 'select',
				'options_map' => array(
					'part-time' => 'Part-time',
					'full-time' => 'Full-time',
					'on-demand' => 'On-demand',
				),
				'field_icon'  => '🕒',
				'sort_order'  => 4,
			),
			'demo_notice_time' => array(
				'field_key'   => 'demo_notice_time',
				'field_label' => 'Notice Time',
				'field_type'  => 'select',
				'options_map' => array(
					'in_days'   => 'in Days',
					'in_weeks'  => 'in Weeks',
					'in_months' => 'in Months',
				),
				'field_icon'  => '🔔',
				'sort_order'  => 5,
			),
			'demo_can_travel' => array(
				'field_key'   => 'demo_can_travel',
				'field_label' => 'Can Travel',
				'field_type'  => 'select',
				'options_map' => array(
					'yes' => 'Yes',
					'no'  => 'No',
				),
				'field_icon'  => '✈️',
				'sort_order'  => 6,
			),
			'demo_daily_rate' => array(
				'field_key'   => 'demo_daily_rate',
				'field_label' => 'Daily Rate',
				'field_type'  => 'select',
				'options_map' => array(
					'under_1k' => '<$1K',
					'1k_to_2k' => '$1K to $2K',
					'3k_to_5k' => '$3K to $5K',
					'over_5k'  => '>$5K',
				),
				'field_icon'  => '💰',
				'sort_order'  => 7,
			),
			'demo_education' => array(
				'field_key'   => 'demo_education',
				'field_label' => 'Education',
				'field_type'  => 'select',
				'options_map' => array(
					'doctorate'   => 'Doctorate',
					'masters'     => 'Master\'s Degree',
					'bachelors'   => 'Bachelor\'s Degree',
					'associates'  => 'Associate\'s Degree',
					'diploma'     => 'Diploma',
					'high_school' => 'High School',
				),
				'field_icon'  => '🎓',
				'sort_order'  => 8,
			),
		);
	}
}

if ( ! function_exists( 'ecomcine_profile_normalize_demographic_field_definition' ) ) {
	/**
	 * Normalize a demographic field definition for profile and form rendering.
	 *
	 * @param array<string,mixed> $field Raw field definition.
	 * @return array<string,mixed>
	 */
	function ecomcine_profile_normalize_demographic_field_definition( array $field ): array {
		$field_key  = isset( $field['field_key'] ) ? sanitize_key( (string) $field['field_key'] ) : '';
		$field_type = isset( $field['field_type'] ) ? sanitize_key( (string) $field['field_type'] ) : 'text';
		$help_texts = array(
			'demo_birth_date' => 'Age will be calculated automatically.',
			'demo_ethnicity'  => 'Hold CTRL (Windows) or CMD (Mac) to select multiple.',
			'demo_languages'  => 'Hold CTRL (Windows) or CMD (Mac) to select multiple.',
		);
		$defaults = ecomcine_profile_get_demographic_fallback_fields();
		$input_type = 'text';
		$is_multi   = false;

		if ( 'demo_birth_date' === $field_key ) {
			$input_type = 'date';
		} else {
			switch ( $field_type ) {
				case 'number':
					$input_type = 'number';
					break;
				case 'textarea':
					$input_type = 'textarea';
					break;
				case 'checkbox':
					$input_type = 'select';
					$is_multi   = true;
					break;
				case 'radio':
				case 'select':
					$input_type = 'select';
					break;
				default:
					$input_type = 'text';
			}
		}

		$field['field_key']   = $field_key;
		$field['field_label'] = isset( $field['field_label'] ) && '' !== trim( (string) $field['field_label'] ) ? (string) $field['field_label'] : $field_key;
		$field['options_map'] = isset( $field['options_map'] ) && is_array( $field['options_map'] ) ? $field['options_map'] : array();
		$field['field_icon']  = isset( $field['field_icon'] ) && '' !== (string) $field['field_icon']
			? (string) $field['field_icon']
			: ( $defaults[ $field_key ]['field_icon'] ?? '•' );
		$field['input_type']  = $input_type;
		$field['multi']       = $is_multi;
		$field['help_text']   = $help_texts[ $field_key ] ?? '';

		return $field;
	}
}

if ( ! function_exists( 'ecomcine_profile_get_demographic_field_definitions' ) ) {
	/**
	 * Resolve Demographic & Availability field definitions from the registry.
	 *
	 * @param bool $public_only Limit to fields visible on public profiles.
	 * @return array<int,array<string,mixed>>
	 */
	function ecomcine_profile_get_demographic_field_definitions( bool $public_only = false ): array {
		$definitions = array();
		$fallbacks   = ecomcine_profile_get_demographic_fallback_fields();

		if ( class_exists( 'EcomCine_Person_Category_Registry', false ) ) {
			$category = EcomCine_Person_Category_Registry::get_by_slug( 'demographic-availability' );
			if ( $category && isset( $category['id'] ) ) {
				$fields = EcomCine_Person_Category_Registry::get_fields_for_category( (int) $category['id'], $public_only );
				foreach ( $fields as $field ) {
					$field_key = isset( $field['field_key'] ) ? sanitize_key( (string) $field['field_key'] ) : '';
					if ( '' === $field_key ) {
						continue;
					}

					$base = $fallbacks[ $field_key ] ?? array(
						'field_key'   => $field_key,
						'field_label' => $field_key,
						'field_type'  => 'text',
						'options_map' => array(),
						'field_icon'  => '•',
						'sort_order'  => isset( $field['sort_order'] ) ? (int) $field['sort_order'] : 0,
					);

					$definitions[] = ecomcine_profile_normalize_demographic_field_definition( array_merge( $base, $field ) );
				}
			}
		}

		if ( ! empty( $definitions ) ) {
			usort(
				$definitions,
				static function( array $left, array $right ): int {
					$left_sort  = isset( $left['sort_order'] ) ? (int) $left['sort_order'] : 0;
					$right_sort = isset( $right['sort_order'] ) ? (int) $right['sort_order'] : 0;
					return $left_sort <=> $right_sort;
				}
			);

			return $definitions;
		}

		foreach ( $fallbacks as $field ) {
			$definitions[] = ecomcine_profile_normalize_demographic_field_definition( $field );
		}

		return $definitions;
	}
}

if ( ! function_exists( 'ecomcine_profile_normalize_demographic_field_value' ) ) {
	/**
	 * Normalize legacy demographic option values to current option keys.
	 *
	 * Older records may store option labels instead of option keys. Resolve them
	 * against both the current admin labels and the fallback seed labels so the
	 * current field definition still controls rendering.
	 *
	 * @param mixed               $value Raw user meta value.
	 * @param array<string,mixed> $field Field definition.
	 * @return mixed
	 */
	function ecomcine_profile_normalize_demographic_field_value( $value, array $field ) {
		$field_key    = isset( $field['field_key'] ) ? sanitize_key( (string) $field['field_key'] ) : '';
		$current_map  = isset( $field['options_map'] ) && is_array( $field['options_map'] ) ? $field['options_map'] : array();
		$fallbacks    = ecomcine_profile_get_demographic_fallback_fields();
		$fallback_map = isset( $fallbacks[ $field_key ]['options_map'] ) && is_array( $fallbacks[ $field_key ]['options_map'] )
			? $fallbacks[ $field_key ]['options_map']
			: array();
		$is_multi = ! empty( $field['multi'] );

		if ( empty( $current_map ) && empty( $fallback_map ) ) {
			return $value;
		}

		$current_reverse = array();
		foreach ( $current_map as $option_key => $option_label ) {
			$current_reverse[ (string) $option_label ] = (string) $option_key;
		}

		$fallback_reverse = array();
		foreach ( $fallback_map as $option_key => $option_label ) {
			$fallback_reverse[ (string) $option_label ] = (string) $option_key;
		}

		$normalize_one = static function( $item ) use ( $current_map, $current_reverse, $fallback_reverse ) {
			$item = is_scalar( $item ) ? trim( (string) $item ) : '';
			if ( '' === $item ) {
				return '';
			}

			if ( array_key_exists( $item, $current_map ) ) {
				return $item;
			}

			if ( isset( $current_reverse[ $item ] ) ) {
				return $current_reverse[ $item ];
			}

			if ( isset( $fallback_reverse[ $item ] ) ) {
				return $fallback_reverse[ $item ];
			}

			return $item;
		};

		if ( $is_multi ) {
			$items = is_array( $value ) ? $value : ( '' === trim( (string) $value ) ? array() : array( (string) $value ) );
			$items = array_values( array_filter( array_map( $normalize_one, $items ), 'strlen' ) );
			return array_values( array_unique( $items ) );
		}

		return $normalize_one( $value );
	}
}


// =============================================================================
// 1. PROFILE DISPLAY
// =============================================================================

// --- 1a. Demographic & Availability (priority 3, all vendors) ---

/**
 * Display Demographic & Availability on Vendor Store Page
 * This appears for ALL vendors regardless of category
 * Priority 3 = appears before category-specific attributes (priority 5)
 */
add_action( 'ecomcine_person_profile_display', function( $store_user, $store_info ) {
	$vendor_id = get_vendor_id_from_store_user( $store_user );
	if ( ! $vendor_id ) {
		return;
	}
	$current_user_id = get_current_user_id();
	$is_owner = function_exists( 'tm_can_edit_vendor_profile' )
		? tm_can_edit_vendor_profile( $vendor_id )
		: ( $current_user_id && $current_user_id == $vendor_id );

	$social_profiles = tm_get_vendor_social_profiles( $vendor_id );
	$linkedin_url = '';
	if ( ! empty( $social_profiles['linkedin'] ) ) {
		$linkedin_url = $social_profiles['linkedin'];
	} elseif ( ! empty( $social_profiles['linked_in'] ) ) {
		$linkedin_url = $social_profiles['linked_in'];
	}
	$linkedin_metrics     = get_user_meta( $vendor_id, 'tm_social_metrics_linkedin', true );
	$linkedin_followers   = is_array( $linkedin_metrics ) && isset( $linkedin_metrics['followers'] ) ? (int) $linkedin_metrics['followers'] : null;
	$linkedin_connections = is_array( $linkedin_metrics ) && isset( $linkedin_metrics['connections'] ) ? (int) $linkedin_metrics['connections'] : null;
	$linkedin_display_url = $linkedin_url;
	if ( ! $linkedin_display_url && is_array( $linkedin_metrics ) && ! empty( $linkedin_metrics['url'] ) ) {
		$linkedin_display_url = $linkedin_metrics['url'];
	}
	$linkedin_updated_at = is_array( $linkedin_metrics ) && ! empty( $linkedin_metrics['updated_at'] ) ? $linkedin_metrics['updated_at'] : '';
	if ( $linkedin_url ) {
		$needs_refresh = true;
		if ( is_array( $linkedin_metrics ) && ! empty( $linkedin_metrics['updated_at'] ) ) {
			$last = strtotime( $linkedin_metrics['updated_at'] );
			$needs_refresh = $last ? ( time() - $last ) > ( defined( 'MONTH_IN_SECONDS' ) ? MONTH_IN_SECONDS : 30 * DAY_IN_SECONDS ) : true;
		}
		if ( $needs_refresh ) {
			tm_queue_linkedin_metrics_refresh( $vendor_id, $linkedin_url );
		}
	}

	$demo_fields = ecomcine_profile_get_demographic_field_definitions( true );

	// Check if any fields have values
	$has_values = false;
	foreach ( $demo_fields as $field ) {
		$field_key = isset( $field['field_key'] ) ? sanitize_key( (string) $field['field_key'] ) : '';
		if ( '' === $field_key ) {
			continue;
		}

		$current_value = ecomcine_profile_normalize_demographic_field_value( get_user_meta( $vendor_id, $field_key, true ), $field );
		if ( ! empty( $field['multi'] ) && ! is_array( $current_value ) ) {
			$current_value = '' === (string) $current_value ? array() : array( (string) $current_value );
		}

		if ( 'demo_birth_date' === $field_key ) {
			$age = calculate_age_from_birth_date( (string) $current_value );
			$current_value = null !== $age ? $age . ' years' : '';
		}

		if ( ecomcine_profile_field_has_value( $current_value ) ) {
			$has_values = true;
			break;
		}
	}

	?>
	<div id="demographic-section" class="talent-physical-attributes vendor-demographic-section attribute-slide-section">
		<h3 class="section-title">
			<i class="fas fa-user-circle section-title-icon"></i> <?php echo esc_html( ecomcine_profile_get_drawer_section_label( 'demographics', 'Demographic & Availability' ) ); ?>
			<?php if ( $is_owner ) : ?>
				<span class="owner-edit-hint">(Click pencil to edit)</span>
			<?php endif; ?>
		</h3>
		<div class="attributes-grid">
			<?php if ( ! $has_values && ! $is_owner ) : ?>
				<div class="stat-item">
					<strong class="stat-value--gold">No demographic data available yet.</strong>
				</div>
			<?php else : ?>
				<?php
				foreach ( $demo_fields as $field ) {
					$field_key   = isset( $field['field_key'] ) ? sanitize_key( (string) $field['field_key'] ) : '';
					$field_label = isset( $field['field_label'] ) ? (string) $field['field_label'] : $field_key;
					$options_map = isset( $field['options_map'] ) && is_array( $field['options_map'] ) ? $field['options_map'] : array();
					$current_value = '' !== $field_key ? ecomcine_profile_normalize_demographic_field_value( get_user_meta( $vendor_id, $field_key, true ), $field ) : '';
					$raw_value = $current_value;
					$icon_url = class_exists( 'EcomCine_Person_Category_Registry', false ) ? EcomCine_Person_Category_Registry::get_field_icon_url( $field ) : '';
					$icon_value = '' !== $icon_url
						? '<img class="stat-icon-image" src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $field_label ) . '" />'
						: ( isset( $field['field_icon'] ) ? (string) $field['field_icon'] : '•' );

					if ( ! empty( $field['multi'] ) && ! is_array( $current_value ) ) {
						$current_value = '' === (string) $current_value ? array() : array( (string) $current_value );
					}

					$display_label = $field_label;
					if ( 'demo_birth_date' === $field_key ) {
						$age = calculate_age_from_birth_date( (string) $raw_value );
						$current_value = null !== $age ? $age . ' years' : '';
						if ( 'Birth Date' === $field_label ) {
							$display_label = 'Age';
						}
					}

					render_editable_attribute([
						'name'       => $field_key,
						'label'      => $display_label,
						'edit_label' => $field_label,
						'icon'       => $icon_value,
						'user_id'    => $vendor_id,
						'is_owner'   => $is_owner,
						'options'    => $options_map,
						'multi'      => ! empty( $field['multi'] ),
						'type'       => $field['input_type'] ?? 'text',
						'value'      => $current_value,
						'raw_value'  => $raw_value,
						'help_text'  => $field['help_text'] ?? '',
					]);
				}
				?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}, 3, 2 ); // Priority 3 – appears before category-specific attributes (priority 5)


// --- 1b. Physical Attributes + Cameraman Equipment (priority 5) ---

/**
 * Display Physical Attributes & Cameraman Equipment on Vendor Store Page
 * Combined in one parent wrapper for consistent positioning
 */
add_action( 'ecomcine_person_profile_display', function( $store_user, $store_info ) {
	$vendor_id = get_vendor_id_from_store_user( $store_user );
	if ( ! $vendor_id ) {
		return;
	}

	$current_user_id = get_current_user_id();
	$is_owner = function_exists( 'tm_can_edit_vendor_profile' )
		? tm_can_edit_vendor_profile( $vendor_id )
		: ( $current_user_id && $current_user_id == $vendor_id );

	$sections = ecomcine_profile_get_dynamic_category_sections( $vendor_id, $is_owner );
	if ( empty( $sections ) ) {
		return;
	}

	echo '<div class="vendor-custom-attributes-wrapper" data-is-owner="' . ( $is_owner ? '1' : '0' ) . '">';

	foreach ( $sections as $section ) {
		$section_id = isset( $section['section_id'] ) ? (string) $section['section_id'] : '';
		$label      = isset( $section['label'] ) ? (string) $section['label'] : '';
		$fields     = isset( $section['fields'] ) && is_array( $section['fields'] ) ? $section['fields'] : array();
		if ( '' === $section_id || '' === $label || empty( $fields ) ) {
			continue;
		}
		?>
		<div id="<?php echo esc_attr( $section_id ); ?>" class="talent-physical-attributes attribute-slide-section ecomcine-dynamic-category-section">
			<h3 class="section-title">
				<?php
				if ( ! empty( $section['icon_markup'] ) ) {
					echo wp_kses_post( (string) $section['icon_markup'] );
				}
				?>
				<?php echo esc_html( $label ); ?>
				<?php if ( $is_owner ) : ?>
					<span class="owner-edit-hint">(Click pencil to edit)</span>
				<?php endif; ?>
			</h3>
			<div class="attributes-grid">
				<?php foreach ( $fields as $field_row ) : ?>
					<?php
					$field = isset( $field_row['definition'] ) && is_array( $field_row['definition'] ) ? $field_row['definition'] : array();
					$value = $field_row['value'] ?? '';
					$field_key = isset( $field['field_key'] ) ? sanitize_key( (string) $field['field_key'] ) : '';
					$field_label = isset( $field['field_label'] ) ? (string) $field['field_label'] : $field_key;
					$field_type = isset( $field['field_type'] ) ? sanitize_key( (string) $field['field_type'] ) : 'text';
					$options_map = isset( $field['options_map'] ) && is_array( $field['options_map'] ) ? $field['options_map'] : array();
					$icon_url = EcomCine_Person_Category_Registry::get_field_icon_url( $field );
					$icon_value = '' !== $icon_url ? '<img class="stat-icon-image" src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $field_label ) . '" />' : ( isset( $field['field_icon'] ) ? (string) $field['field_icon'] : '•' );
					$input_type = 'text';
					$is_multi = false;

					switch ( $field_type ) {
						case 'number':
							$input_type = 'number';
							break;
						case 'textarea':
							$input_type = 'textarea';
							break;
						case 'checkbox':
							$input_type = 'select';
							$is_multi = true;
							break;
						case 'radio':
						case 'select':
							$input_type = 'select';
							break;
						default:
							$input_type = 'text';
					}

					render_editable_attribute([
						'name'       => $field_key,
						'label'      => $field_label,
						'edit_label' => $field_label,
						'icon'       => $icon_value,
						'user_id'    => $vendor_id,
						'is_owner'   => $is_owner,
						'options'    => $options_map,
						'multi'      => $is_multi,
						'type'       => $input_type,
						'value'      => $value,
						'raw_value'  => $value,
					]);
					?>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	echo '</div>';
}, 5, 2 );


// =============================================================================
// 2. DASHBOARD SETTINGS FORM FIELDS
// =============================================================================

// --- 2a. Demographic & Availability form fields (priority 5, all vendors) ---

/**
 * Add Demographic & Availability fields to vendor dashboard
 * These fields appear for ALL vendors regardless of category
 * Priority 5 = appears before category-specific attributes (priority 10)
 */
add_action( 'ecomcine_person_settings_fields', function( $user_id, $profile_info ) {
	$demo_fields = ecomcine_profile_get_demographic_field_definitions( false );
	?>
	<div class="ecomcine-form-group demographic-availability-section">
		<div class="ecomcine-col-12">
			<h3 style="margin-top: 30px; margin-bottom: 20px; font-size: 18px; border-bottom: 2px solid #f0ad4e; padding-bottom: 10px; color: #f0ad4e;">
				<span class="dashicons dashicons-id-alt" style="font-size: 20px; margin-right: 5px;"></span>
				<?php esc_html_e( 'Demographic & Availability', 'ecomcine' ); ?>
			</h3>
		</div>
	</div>
	<?php foreach ( $demo_fields as $field ) : ?>
		<?php
		$field_key   = isset( $field['field_key'] ) ? sanitize_key( (string) $field['field_key'] ) : '';
		$field_label = isset( $field['field_label'] ) ? (string) $field['field_label'] : $field_key;
		$options_map = isset( $field['options_map'] ) && is_array( $field['options_map'] ) ? $field['options_map'] : array();
		$current_value = '' !== $field_key ? ecomcine_profile_normalize_demographic_field_value( get_user_meta( $user_id, $field_key, true ), $field ) : '';
		$input_type  = isset( $field['input_type'] ) ? (string) $field['input_type'] : 'text';
		$is_multi    = ! empty( $field['multi'] );
		$help_text   = isset( $field['help_text'] ) ? (string) $field['help_text'] : '';
		$field_icon  = isset( $field['field_icon'] ) ? (string) $field['field_icon'] : '•';

		if ( $is_multi && ! is_array( $current_value ) ) {
			$current_value = '' === (string) $current_value ? array() : array( (string) $current_value );
		}
		?>
		<div class="ecomcine-form-group">
			<label class="ecomcine-col-3 ecomcine-control-label" for="<?php echo esc_attr( $field_key ); ?>">
				<span><?php echo esc_html( $field_icon ); ?></span>
				<?php echo esc_html( $field_label ); ?>
			</label>
			<div class="ecomcine-col-5 ecomcine-text-left">
				<?php if ( 'date' === $input_type ) : ?>
					<input type="date" id="<?php echo esc_attr( $field_key ); ?>" name="<?php echo esc_attr( $field_key ); ?>" class="ecomcine-form-control" value="<?php echo esc_attr( is_array( $current_value ) ? '' : (string) $current_value ); ?>">
				<?php elseif ( 'textarea' === $input_type ) : ?>
					<textarea id="<?php echo esc_attr( $field_key ); ?>" name="<?php echo esc_attr( $field_key ); ?>" class="ecomcine-form-control" rows="4"><?php echo esc_textarea( is_array( $current_value ) ? '' : (string) $current_value ); ?></textarea>
				<?php elseif ( 'number' === $input_type ) : ?>
					<input type="number" id="<?php echo esc_attr( $field_key ); ?>" name="<?php echo esc_attr( $field_key ); ?>" class="ecomcine-form-control" value="<?php echo esc_attr( is_array( $current_value ) ? '' : (string) $current_value ); ?>">
				<?php elseif ( 'select' === $input_type && $is_multi ) : ?>
					<select id="<?php echo esc_attr( $field_key ); ?>" name="<?php echo esc_attr( $field_key ); ?>[]" class="ecomcine-form-control" multiple size="5">
						<?php foreach ( $options_map as $option_key => $option_label ) : ?>
							<option value="<?php echo esc_attr( $option_key ); ?>"<?php echo in_array( (string) $option_key, array_map( 'strval', (array) $current_value ), true ) ? ' selected' : ''; ?>><?php echo esc_html( $option_label ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php elseif ( 'select' === $input_type ) : ?>
					<select id="<?php echo esc_attr( $field_key ); ?>" name="<?php echo esc_attr( $field_key ); ?>" class="ecomcine-form-control">
						<option value=""></option>
						<?php foreach ( $options_map as $option_key => $option_label ) : ?>
							<option value="<?php echo esc_attr( $option_key ); ?>"<?php selected( is_array( $current_value ) ? '' : (string) $current_value, (string) $option_key ); ?>><?php echo esc_html( $option_label ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="text" id="<?php echo esc_attr( $field_key ); ?>" name="<?php echo esc_attr( $field_key ); ?>" class="ecomcine-form-control" value="<?php echo esc_attr( is_array( $current_value ) ? '' : (string) $current_value ); ?>">
				<?php endif; ?>

				<?php if ( '' !== $help_text ) : ?>
					<p class="description"><?php echo esc_html( $help_text ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
	<?php
}, 5, 2 ); // Priority 5 – appears before category-specific attributes (priority 10)


// --- 2b. Cameraman Equipment & Skills form fields (priority 10, cameraman only) ---

/**
 * Add Cameraman Fields to Vendor Dashboard Settings
 * Uses correct Dokan hook: dokan_settings_after_store_phone
 * Note: Physical Attributes are in the template file (store-form.php)
 */
add_action( 'ecomcine_person_settings_fields', function( $user_id, $profile_info ) {
	$cameraman_options = get_cameraman_filter_options();

	// Section header
	?>
	<div class="ecomcine-form-group cameraman-section-header" data-category="cameraman" style="display:none;">
		<div class="ecomcine-col-12">
			<h3 style="margin-top: 30px; margin-bottom: 20px; font-size: 18px; border-bottom: 2px solid #f05025; padding-bottom: 10px;">
				🎬 <?php esc_html_e( 'Equipment & Skills', 'ecomcine' ); ?>
			</h3>
		</div>
	</div>
	<?php

	// Build each cameraman field generically
	$cameraman_field_defs = [
		'camera_type'         => [ 'icon' => '📷', 'label' => 'Camera Type' ],
		'experience_level'    => [ 'icon' => '⭐', 'label' => 'Experience Level' ],
		'editing_software'    => [ 'icon' => '💻', 'label' => 'Editing Software' ],
		'specialization'      => [ 'icon' => '🎬', 'label' => 'Specialization' ],
		'years_experience'    => [ 'icon' => '📅', 'label' => 'Years of Experience' ],
		'equipment_ownership' => [ 'icon' => '🎥', 'label' => 'Equipment Ownership' ],
		'lighting_equipment'  => [ 'icon' => '💡', 'label' => 'Lighting Equipment' ],
		'audio_equipment'     => [ 'icon' => '🎤', 'label' => 'Audio Equipment' ],
		'drone_capability'    => [ 'icon' => '🚁', 'label' => 'Drone Capability' ],
	];

	foreach ( $cameraman_field_defs as $field_key => $field_def ) {
		$current_value = get_user_meta( $user_id, $field_key, true );
		?>
		<div class="ecomcine-form-group <?php echo esc_attr( $field_key ); ?>" data-category="cameraman" style="display:none;">
			<label class="ecomcine-col-3 ecomcine-control-label" for="<?php echo esc_attr( $field_key ); ?>">
				<?php echo esc_html( $field_def['icon'] . ' ' ); ?><?php esc_html_e( $field_def['label'], 'ecomcine' ); ?>
			</label>
			<div class="ecomcine-col-5">
				<select class="ecomcine-form-control" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>">
					<?php
					foreach ( $cameraman_options[ $field_key ] as $value => $label ) {
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $value ),
							selected( $current_value, $value, false ),
							esc_html( $label )
						);
					}
					?>
				</select>
			</div>
		</div>
		<?php
	}
}, 10, 2 );


// =============================================================================
// 3. PROFILE SAVE
// =============================================================================

/**
 * Save Physical Attributes & Cameraman Fields for Vendor Profile
 */
add_action( 'ecomcine_person_profile_saved', function ( $store_id, $dokan_settings ) {
	$store_id = (int) $store_id;
	if ( ! $store_id ) return;

	// Physical attributes
	$attributes = [
		'talent_height', 'talent_weight', 'talent_waist', 'talent_hip', 'talent_chest',
		'talent_shoe_size', 'talent_eye_color', 'talent_hair_color',
	];

	// Cameraman fields
	$cameraman_fields = [
		'camera_type', 'experience_level', 'editing_software', 'specialization',
		'years_experience', 'equipment_ownership', 'lighting_equipment', 'audio_equipment', 'drone_capability',
	];

	$demographic_fields = ecomcine_profile_get_demographic_field_definitions( false );

	foreach ( array_merge( $attributes, $cameraman_fields ) as $field ) {
		if ( isset( $_POST[ $field ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			update_user_meta( $store_id, $field, $value );
		}
	}

	foreach ( $demographic_fields as $field ) {
		$field_key  = isset( $field['field_key'] ) ? sanitize_key( (string) $field['field_key'] ) : '';
		$is_multi   = ! empty( $field['multi'] );

		if ( '' === $field_key ) {
			continue;
		}

		if ( $is_multi ) {
			if ( isset( $_POST[ $field_key ] ) && is_array( $_POST[ $field_key ] ) ) {
				$values = array_values( array_filter( array_map( 'sanitize_text_field', wp_unslash( $_POST[ $field_key ] ) ), 'strlen' ) );
				update_user_meta( $store_id, $field_key, $values );
			} else {
				delete_user_meta( $store_id, $field_key );
			}
			continue;
		}

		if ( isset( $_POST[ $field_key ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $field_key ] ) );
			update_user_meta( $store_id, $field_key, $value );
		}
	}
}, 10, 2 );
