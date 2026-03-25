<?php
/**
 * The template for displaying cameraman/cinematographer filters in store lists
 *
 * @package Dokan/Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

$cameraman_options = get_cameraman_filter_options();
$current_values = [
    'camera_type'       => isset( $_GET['camera_type'] ) ? sanitize_text_field( $_GET['camera_type'] ) : '',
    'experience_level'  => isset( $_GET['experience_level'] ) ? sanitize_text_field( $_GET['experience_level'] ) : '',
    'editing_software'  => isset( $_GET['editing_software'] ) ? sanitize_text_field( $_GET['editing_software'] ) : '',
    'specialization'    => isset( $_GET['specialization'] ) ? sanitize_text_field( $_GET['specialization'] ) : '',
    'years_experience'  => isset( $_GET['years_experience'] ) ? sanitize_text_field( $_GET['years_experience'] ) : '',
    'equipment_ownership' => isset( $_GET['equipment_ownership'] ) ? sanitize_text_field( $_GET['equipment_ownership'] ) : '',
    'lighting_equipment' => isset( $_GET['lighting_equipment'] ) ? sanitize_text_field( $_GET['lighting_equipment'] ) : '',
    'audio_equipment'   => isset( $_GET['audio_equipment'] ) ? sanitize_text_field( $_GET['audio_equipment'] ) : '',
    'drone_capability'  => isset( $_GET['drone_capability'] ) ? sanitize_text_field( $_GET['drone_capability'] ) : '',
];
?>

<div class="custom-filter-group cameraman-filters" data-category="cameraman">
    <div class="filter-group-title">
        <span class="filter-group-heading"><?php esc_html_e( 'Equipment & Skills', 'dokan' ); ?></span>
    </div>
    <div class="filter-group-items">
        
        <!-- Camera Type Filter -->
        <div class="filter-item cameraman-filter">
            <label for="camera_type">📷 <?php esc_html_e( 'Camera Type', 'dokan' ); ?>:</label>
            <select name="camera_type" id="camera_type" class="dokan-form-control">
                <?php foreach ( $cameraman_options['camera_type'] as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['camera_type'], $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Experience Level Filter -->
        <div class="filter-item cameraman-filter">
            <label for="experience_level">⭐ <?php esc_html_e( 'Experience Level', 'dokan' ); ?>:</label>
            <select name="experience_level" id="experience_level" class="dokan-form-control">
                <?php foreach ( $cameraman_options['experience_level'] as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['experience_level'], $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Editing Software Filter -->
        <div class="filter-item cameraman-filter">
            <label for="editing_software">🎬 <?php esc_html_e( 'Editing Software', 'dokan' ); ?>:</label>
            <select name="editing_software" id="editing_software" class="dokan-form-control">
                <?php foreach ( $cameraman_options['editing_software'] as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['editing_software'], $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Specialization Filter -->
        <div class="filter-item cameraman-filter">
            <label for="specialization">🎯 <?php esc_html_e( 'Specialization', 'dokan' ); ?>:</label>
            <select name="specialization" id="specialization" class="dokan-form-control">
                <?php foreach ( $cameraman_options['specialization'] as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['specialization'], $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Years of Experience Filter -->
        <div class="filter-item cameraman-filter">
            <label for="years_experience">📅 <?php esc_html_e( 'Years of Experience', 'dokan' ); ?>:</label>
            <select name="years_experience" id="years_experience" class="dokan-form-control">
                <?php foreach ( $cameraman_options['years_experience'] as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['years_experience'], $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Equipment Ownership Filter -->
        <div class="filter-item cameraman-filter">
            <label for="equipment_ownership">🎥 <?php esc_html_e( 'Equipment Ownership', 'dokan' ); ?>:</label>
            <select name="equipment_ownership" id="equipment_ownership" class="dokan-form-control">
                <?php foreach ( $cameraman_options['equipment_ownership'] as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['equipment_ownership'], $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Lighting Equipment Filter -->
        <div class="filter-item cameraman-filter">
            <label for="lighting_equipment">💡 <?php esc_html_e( 'Lighting Equipment', 'dokan' ); ?>:</label>
            <select name="lighting_equipment" id="lighting_equipment" class="dokan-form-control">
                <?php foreach ( $cameraman_options['lighting_equipment'] as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['lighting_equipment'], $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Audio Equipment Filter -->
        <div class="filter-item cameraman-filter">
            <label for="audio_equipment">🎤 <?php esc_html_e( 'Audio Equipment', 'dokan' ); ?>:</label>
            <select name="audio_equipment" id="audio_equipment" class="dokan-form-control">
                <?php foreach ( $cameraman_options['audio_equipment'] as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['audio_equipment'], $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Drone Capability Filter -->
        <div class="filter-item cameraman-filter">
            <label for="drone_capability">🚁 <?php esc_html_e( 'Drone/Aerial', 'dokan' ); ?>:</label>
            <select name="drone_capability" id="drone_capability" class="dokan-form-control">
                <?php foreach ( $cameraman_options['drone_capability'] as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['drone_capability'], $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
    </div>
</div>
