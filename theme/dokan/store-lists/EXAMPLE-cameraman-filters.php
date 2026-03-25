<?php
/**
 * EXAMPLE: Camera equipment filter for Cameraman category
 * 
 * This demonstrates how to create a new filter group for a different category
 * using the same reusable structure as Physical Attributes.
 * 
 * @package Dokan/Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Example: Get cameraman-specific filter options
// You would create a similar function like get_talent_physical_attributes_options()
$camera_options = [
    'camera_type' => [
        ''       => 'Select Camera Type',
        'dslr'   => 'DSLR',
        'mirrorless' => 'Mirrorless',
        'cinema' => 'Cinema Camera',
        'action' => 'Action Camera',
    ],
    'editing_software' => [
        ''           => 'Select Software',
        'premiere'   => 'Adobe Premiere Pro',
        'final-cut'  => 'Final Cut Pro',
        'davinci'    => 'DaVinci Resolve',
        'avid'       => 'Avid Media Composer',
    ],
];

// Get current values from URL
$current_values = [
    'camera_type'      => isset( $_GET['camera_type'] ) ? sanitize_text_field( $_GET['camera_type'] ) : '',
    'editing_software' => isset( $_GET['editing_software'] ) ? sanitize_text_field( $_GET['editing_software'] ) : '',
];
?>

<!-- This filter group will only show for Cameraman category (via data-category attribute) -->
<div class="custom-filter-group cameraman-filters" data-category="cameraman">
    <div class="filter-group-title">
        <span class="filter-group-heading"><?php esc_html_e( 'Equipment & Skills', 'dokan' ); ?></span>
    </div>
    <div class="filter-group-items">
        
        <!-- Camera Type Filter -->
        <div class="filter-item">
            <label for="camera_type">📷 <?php esc_html_e( 'Camera Type', 'dokan' ); ?>:</label>
            <select name="camera_type" id="camera_type" class="dokan-form-control">
                <?php foreach ( $camera_options['camera_type'] as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['camera_type'], $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Editing Software Filter -->
        <div class="filter-item">
            <label for="editing_software">🎬 <?php esc_html_e( 'Editing Software', 'dokan' ); ?>:</label>
            <select name="editing_software" id="editing_software" class="dokan-form-control">
                <?php foreach ( $camera_options['editing_software'] as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['editing_software'], $value ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
    </div>
</div>
