<?php
/**
 * Profile Level filter - mirrors the Category filter UI exactly.
 * A div-based single-select custom dropdown (no <select>).
 * Empty selection = All Levels (no filter applied).
 */

defined( 'ABSPATH' ) || exit;

$selected_level = isset( $_GET['profile_level'] ) ? sanitize_text_field( $_GET['profile_level'] ) : '';

$_level_labels = [
	'basic'     => 'Basic',
	'mediatic'  => 'Mediatic',
	'cinematic' => 'Cinematic',
];
$_selected_label = ( $selected_level && isset( $_level_labels[ $selected_level ] ) )
	? $_level_labels[ $selected_level ]
	: '';
?>
<div class="item store-lists-category tm-level-filter-wrap">
    <input type="hidden" id="profile_level" name="profile_level" value="<?php echo esc_attr( $selected_level ); ?>">
    <div class="category-input" id="tm-level-filter-toggle">
        <span class="category-label" style="color:#D4AF37;font-weight:600;">Level:</span>
        <span class="tm-level-items"><?php echo esc_html( $_selected_label ); ?></span>
        <span class="category-arrow" aria-hidden="true">&#9662;</span>
    </div>
    <div class="category-box tm-level-box" style="display:none;">
        <ul>
            <li data-value="basic"><?php esc_html_e( 'Basic', 'dokan' ); ?></li>
            <li data-value="mediatic"><?php esc_html_e( 'Mediatic', 'dokan' ); ?></li>
            <li data-value="cinematic"><?php esc_html_e( 'Cinematic', 'dokan' ); ?></li>
        </ul>
    </div>
</div>
