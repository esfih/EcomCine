<?php
/**
 * The template for displaying physical attributes filters in store lists
 *
 * @package Dokan/Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

$attributes_options = get_talent_physical_attributes_options();
$current_values = [
    'height'     => isset( $_GET['talent_height'] ) ? sanitize_text_field( $_GET['talent_height'] ) : '',
    'weight'     => isset( $_GET['talent_weight'] ) ? sanitize_text_field( $_GET['talent_weight'] ) : '',
    'waist'      => isset( $_GET['talent_waist'] ) ? sanitize_text_field( $_GET['talent_waist'] ) : '',
    'hip'        => isset( $_GET['talent_hip'] ) ? sanitize_text_field( $_GET['talent_hip'] ) : '',
    'chest'      => isset( $_GET['talent_chest'] ) ? sanitize_text_field( $_GET['talent_chest'] ) : '',
    'shoe_size'  => isset( $_GET['talent_shoe_size'] ) ? sanitize_text_field( $_GET['talent_shoe_size'] ) : '',
    'eye_color'  => isset( $_GET['talent_eye_color'] ) ? sanitize_text_field( $_GET['talent_eye_color'] ) : '',
    'hair_color' => isset( $_GET['talent_hair_color'] ) ? sanitize_text_field( $_GET['talent_hair_color'] ) : '',
];
?>

<div class="custom-filter-group physical-attributes-filters" data-category="model,artist">
    <div class="filter-group-title">
        <span class="filter-group-heading"><?php esc_html_e( 'Physical Attributes', 'dokan' ); ?></span>
    </div>
    <div class="filter-group-items">
        <!-- Height Filter -->
        <div class="filter-item attribute-filter">
        <label for="talent_height">📏 <?php esc_html_e( 'Height', 'dokan' ); ?>:</label>
        <select name="talent_height" id="talent_height" class="dokan-form-control">
            <?php foreach ( $attributes_options['height'] as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['height'], $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        </div>

        <!-- Weight Filter -->
        <div class="filter-item attribute-filter">
        <label for="talent_weight">⚖️ <?php esc_html_e( 'Weight', 'dokan' ); ?>:</label>
        <select name="talent_weight" id="talent_weight" class="dokan-form-control">
            <?php foreach ( $attributes_options['weight'] as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['weight'], $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        </div>

        <!-- Waist Filter -->
        <div class="filter-item attribute-filter">
        <label for="talent_waist">📐 <?php esc_html_e( 'Waist', 'dokan' ); ?>:</label>
        <select name="talent_waist" id="talent_waist" class="dokan-form-control">
            <?php foreach ( $attributes_options['waist'] as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['waist'], $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        </div>

        <!-- Hip Filter -->
        <div class="filter-item attribute-filter">
        <label for="talent_hip">📐 <?php esc_html_e( 'Hip', 'dokan' ); ?>:</label>
        <select name="talent_hip" id="talent_hip" class="dokan-form-control">
            <?php foreach ( $attributes_options['hip'] as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['hip'], $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        </div>

        <!-- Chest Filter -->
        <div class="filter-item attribute-filter">
        <label for="talent_chest">📐 <?php esc_html_e( 'Chest', 'dokan' ); ?>:</label>
        <select name="talent_chest" id="talent_chest" class="dokan-form-control">
            <?php foreach ( $attributes_options['chest'] as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['chest'], $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        </div>

        <!-- Shoe Size Filter -->
        <div class="filter-item attribute-filter">
        <label for="talent_shoe_size">👟 <?php esc_html_e( 'Shoe Size', 'dokan' ); ?>:</label>
        <select name="talent_shoe_size" id="talent_shoe_size" class="dokan-form-control">
            <?php foreach ( $attributes_options['shoe_size'] as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['shoe_size'], $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        </div>

        <!-- Eye Color Filter -->
        <div class="filter-item attribute-filter">
        <label for="talent_eye_color">👁️ <?php esc_html_e( 'Eye Color', 'dokan' ); ?>:</label>
        <select name="talent_eye_color" id="talent_eye_color" class="dokan-form-control">
            <?php foreach ( $attributes_options['eye_color'] as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['eye_color'], $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        </div>

        <!-- Hair Color Filter -->
        <div class="filter-item attribute-filter">
        <label for="talent_hair_color">💇 <?php esc_html_e( 'Hair Color', 'dokan' ); ?>:</label>
        <select name="talent_hair_color" id="talent_hair_color" class="dokan-form-control">
            <?php foreach ( $attributes_options['hair_color'] as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_values['hair_color'], $value ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        </div>
    </div>
</div>
