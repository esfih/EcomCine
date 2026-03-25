<?php
if ( ! function_exists( 'dokan' ) || ! dokan()->vendor ) {
    return;
}

$store_user = dokan()->vendor->get( get_query_var( 'author' ) );
if ( ! $store_user ) {
    return;
}

$store_info    = $store_user->get_shop_info();
$social_info   = $store_user->get_social_profiles();
$store_tabs    = dokan_get_store_tabs( $store_user->get_id() );
$social_fields = dokan_get_social_profile_fields();

$dokan_store_times = ! empty( $store_info['dokan_store_time'] ) ? $store_info['dokan_store_time'] : [];
$current_time      = dokan_current_datetime();
$today             = strtolower( $current_time->format( 'l' ) );

$dokan_appearance = get_option( 'dokan_appearance' );
$profile_layout   = empty( $dokan_appearance['store_header_template'] ) ? 'default' : $dokan_appearance['store_header_template'];
$store_address    = dokan_get_seller_short_address( $store_user->get_id(), false );

$dokan_store_time_enabled = isset( $store_info['dokan_store_time_enabled'] ) ? $store_info['dokan_store_time_enabled'] : '';
$store_open_notice        = isset( $store_info['dokan_store_open_notice'] ) && ! empty( $store_info['dokan_store_open_notice'] ) ? $store_info['dokan_store_open_notice'] : __( 'Store Open', 'dokan-lite' );
$store_closed_notice      = isset( $store_info['dokan_store_close_notice'] ) && ! empty( $store_info['dokan_store_close_notice'] ) ? $store_info['dokan_store_close_notice'] : __( 'Store Closed', 'dokan-lite' );
$show_store_open_close    = dokan_get_option( 'store_open_close', 'dokan_appearance', 'on' );

$general_settings = get_option( 'dokan_general', [] );
$banner_width     = dokan_get_vendor_store_banner_width();

if ( ( 'default' === $profile_layout ) || ( 'layout2' === $profile_layout ) ) {
    $profile_img_class = 'profile-img-circle';
} else {
    $profile_img_class = 'profile-img-square';
}

if ( 'layout3' === $profile_layout ) {
    unset( $store_info['banner'] );

    $no_banner_class      = ' profile-frame-no-banner';
    $no_banner_class_tabs = ' dokan-store-tabs-no-banner';
} else {
    $no_banner_class      = '';
    $no_banner_class_tabs = '';
}

// Get Featured/Verified status
$vendor_id = $store_user->get_id();
$current_user_id = get_current_user_id();
$is_owner = function_exists( 'tm_can_edit_vendor_profile' ) 
	? tm_can_edit_vendor_profile( $vendor_id, $current_user_id )
	: ( $current_user_id && $current_user_id == $vendor_id );
$is_admin_editing = $is_owner && $current_user_id != $vendor_id && current_user_can( 'manage_options' );
$is_featured = get_user_meta( $vendor_id, 'dokan_feature_seller', true );
$is_verified = get_user_meta( $vendor_id, 'dokan_store_verified', true );
$is_preonboard = (bool) get_user_meta( $vendor_id, 'tm_preonboard', true );

$onboard_token = isset( $_GET['tm_onboard'] ) ? sanitize_text_field( wp_unslash( $_GET['tm_onboard'] ) ) : '';
$onboard_state = function_exists( 'tm_account_panel_get_onboard_state' )
    ? tm_account_panel_get_onboard_state( $vendor_id, $onboard_token )
    : [ 'valid' => false ];
$onboard_claimed = isset( $_GET['tm_onboard_claimed'] ) ? sanitize_text_field( wp_unslash( $_GET['tm_onboard_claimed'] ) ) : '';
$onboard_error = isset( $_GET['tm_onboard_error'] ) ? sanitize_text_field( wp_unslash( $_GET['tm_onboard_error'] ) ) : '';
$is_onboarding_context = $is_preonboard || ! empty( $onboard_state['valid'] ) || ! empty( $onboard_claimed );

// Store categories data for inline editing in the profile panel
$store_category_terms = get_terms( [
    'taxonomy'   => 'store_category',
    'hide_empty' => false,
] );
$store_category_options = [];
if ( ! is_wp_error( $store_category_terms ) ) {
    foreach ( $store_category_terms as $term ) {
        $store_category_options[ (int) $term->term_id ] = $term->name;
    }
}

$selected_category_ids = [];

// Pull categories from Dokan profile settings (vendor dashboard source of truth)
$profile_settings = $vendor_id ? get_user_meta( $vendor_id, 'dokan_profile_settings', true ) : [];
if ( is_array( $profile_settings ) ) {
    if ( ! empty( $profile_settings['dokan_category'] ) ) {
        $selected_category_ids = (array) $profile_settings['dokan_category'];
    } elseif ( ! empty( $profile_settings['categories'] ) ) {
        $selected_category_ids = (array) $profile_settings['categories'];
    }
}

// Fallback: dedicated meta stored by our normalization hook
if ( empty( $selected_category_ids ) && $vendor_id ) {
    $meta_cats = get_user_meta( $vendor_id, 'dokan_store_categories', true );
    if ( is_array( $meta_cats ) ) {
        $selected_category_ids = $meta_cats;
    }
}

// Fallback: store_info payload
if ( empty( $selected_category_ids ) && ! empty( $store_info['categories'] ) && is_array( $store_info['categories'] ) ) {
    $selected_category_ids = $store_info['categories'];
}

// Fallback: taxonomy terms
if ( empty( $selected_category_ids ) && $vendor_id ) {
    $term_ids = wp_get_object_terms( $vendor_id, 'store_category', [ 'fields' => 'ids' ] );
    if ( ! is_wp_error( $term_ids ) ) {
        $selected_category_ids = $term_ids;
    }
}

$selected_category_ids = array_values( array_filter( $selected_category_ids ) );
// Normalize category IDs defensively (some vendors may have malformed category arrays stored).
$selected_category_ids = array_map( 'absint', array_filter( (array) $selected_category_ids, 'is_scalar' ) );
$selected_category_ids = array_values( array_filter( $selected_category_ids ) );
$selected_category_names = [];
foreach ( $selected_category_ids as $term_id ) {
    if ( isset( $store_category_options[ $term_id ] ) ) {
        $selected_category_names[] = $store_category_options[ $term_id ];
    }
}
$store_category_display = implode( ', ', $selected_category_names );
$store_category_display_full = $store_category_display;
$store_category_display = strlen($store_category_display) > 30 ? substr($store_category_display, 0, 30) . '...' : $store_category_display;
$shop_name = $store_user->get_shop_name();
$shop_name = $shop_name ? $shop_name : '';
$shop_name_words = preg_split( '/\s+/', trim( $shop_name ) );
$contact_first_name = ( $shop_name_words && ! empty( $shop_name_words[0] ) ) ? $shop_name_words[0] : 'this talent';

$inline_profile_settings = $vendor_id ? get_user_meta( $vendor_id, 'dokan_profile_settings', true ) : [];
$inline_geo = [];
if ( is_array( $inline_profile_settings ) && ! empty( $inline_profile_settings['geolocation'] ) && is_array( $inline_profile_settings['geolocation'] ) ) {
    $inline_geo = $inline_profile_settings['geolocation'];
}
if ( empty( $inline_geo ) && ! empty( $store_info['geolocation'] ) && is_array( $store_info['geolocation'] ) ) {
    $inline_geo = $store_info['geolocation'];
}
$geo_lat = isset( $inline_geo['latitude'] ) ? $inline_geo['latitude'] : '';
$geo_lng = isset( $inline_geo['longitude'] ) ? $inline_geo['longitude'] : '';
$geo_lat_attr = is_numeric( $geo_lat ) ? $geo_lat : '';
$geo_lng_attr = is_numeric( $geo_lng ) ? $geo_lng : '';

$login_email = $store_user->get_email();
$legacy_contact_email = $vendor_id ? get_user_meta( $vendor_id, 'tm_contact_email', true ) : '';
$contact_emails = $vendor_id ? get_user_meta( $vendor_id, 'tm_contact_emails', true ) : [];
if ( ! is_array( $contact_emails ) ) {
    $contact_emails = [];
}
$contact_email_main = $vendor_id ? get_user_meta( $vendor_id, 'tm_contact_email_main', true ) : '';
if ( ! $contact_email_main && $legacy_contact_email ) {
    $contact_email_main = $legacy_contact_email;
}
if ( empty( $contact_emails ) && $contact_email_main ) {
    $contact_emails = [ $contact_email_main ];
}

$contact_phones = $vendor_id ? get_user_meta( $vendor_id, 'tm_contact_phones', true ) : [];
if ( ! is_array( $contact_phones ) ) {
    $contact_phones = [];
}
$contact_phone_main = $vendor_id ? get_user_meta( $vendor_id, 'tm_contact_phone_main', true ) : '';
$profile_phone = '';
if ( is_array( $inline_profile_settings ) && ! empty( $inline_profile_settings['phone'] ) ) {
    $profile_phone = $inline_profile_settings['phone'];
}
if ( ! $contact_phone_main && $profile_phone ) {
    $contact_phone_main = $profile_phone;
}
if ( ! $contact_phone_main ) {
    $contact_phone_main = $store_user->get_phone();
}
if ( empty( $contact_phones ) && $contact_phone_main ) {
    $contact_phones = [ $contact_phone_main ];
}

$contact_email_display = $contact_email_main ? $contact_email_main : 'Not set';
$contact_phone_display = $contact_phone_main ? $contact_phone_main : 'Not set';
$contact_email_rows = ! empty( $contact_emails ) ? $contact_emails : [ '' ];
$contact_phone_rows = ! empty( $contact_phones ) ? $contact_phones : [ '' ];
$contact_email_extra_count = max( count( $contact_emails ) - 1, 0 );
$contact_phone_extra_count = max( count( $contact_phones ) - 1, 0 );
$contact_email_count = count( $contact_emails );
$contact_phone_count = count( $contact_phones );
$social_count = 0;
if ( $social_fields ) {
    foreach ( $social_fields as $key => $field ) {
        if ( ! empty( $social_info[ $key ] ) ) {
            $social_count++;
        }
    }
}
$media_playlist = $vendor_id ? tm_get_vendor_media_playlist( $vendor_id ) : [ 'items' => [], 'fallbackImage' => '' ];
$media_items = isset( $media_playlist['items'] ) && is_array( $media_playlist['items'] )
	? array_filter( $media_playlist['items'], function( $item ) {
		return is_array( $item ) && ! empty( $item['src'] );
	} )
	: [];
$media_count = count( $media_items );
if ( $media_count === 0 && ! empty( $media_playlist['fallbackImage'] ) ) {
	$media_count = 1;
}
$contact_email_label = $contact_email_count === 1 ? 'email' : 'emails';
$contact_phone_label = $contact_phone_count === 1 ? 'phone' : 'phones';
$contact_social_label = $social_count === 1 ? 'social' : 'socials';
$contact_media_label = $media_count === 1 ? 'media item' : 'media items';
$contact_email_help = sprintf( 'We can reach %s on %d %s', $contact_first_name, $contact_email_count, $contact_email_label );
$contact_phone_help = sprintf( 'We can reach %s on %d %s', $contact_first_name, $contact_phone_count, $contact_phone_label );
$contact_social_help = sprintf( 'We can reach %s on %d %s', $contact_first_name, $social_count, $contact_social_label );
$contact_media_help = sprintf( '%s has %d playable %s', $contact_first_name, $media_count, $contact_media_label );
$contact_email_radio_name = 'contact_email_main_choice_' . $vendor_id;
$contact_phone_radio_name = 'contact_phone_main_choice_' . $vendor_id;

// ── Pre-compute vendor completeness once ──────────────────────────────────────
// Reused in the level badge (talent info panel) AND the publish strip below.
$_tm_completeness = ( $vendor_id && function_exists( 'tm_vendor_completeness' ) )
    ? tm_vendor_completeness( $vendor_id )
    : null;

// Persist L1/L2 flags for use in the store-listing DB query (lazy update).
if ( $_tm_completeness && $vendor_id && function_exists( 'tm_update_completeness_flags' ) ) {
    $_l1_stored = get_user_meta( $vendor_id, 'tm_l1_complete', true );
    $_l2_stored = get_user_meta( $vendor_id, 'tm_l2_complete', true );
    $_l1_now    = $_tm_completeness['level1']['complete'] ? '1' : '0';
    $_l2_now    = $_tm_completeness['level2']['complete'] ? '1' : '0';
    if ( $_l1_stored !== $_l1_now || $_l2_stored !== $_l2_now ) {
        update_user_meta( $vendor_id, 'tm_l1_complete', $_l1_now );
        update_user_meta( $vendor_id, 'tm_l2_complete', $_l2_now );
    }
}

// Derive level label + CSS tier from completeness data.
$_tm_level_label = '';
$_tm_level_tier  = '';
if ( $_tm_completeness ) {
    if ( ! empty( $_tm_completeness['level2']['complete'] ) ) {
        $_tm_level_label = 'Mediatic Level';
        $_tm_level_tier  = 'mediatic';
    } elseif ( ! empty( $_tm_completeness['level1']['complete'] ) ) {
        $_tm_level_label = 'Basic Level';
        $_tm_level_tier  = 'basic';
    }
    // Level 3 (Cinematic) is a placeholder — no achievable badge yet.
}

?>

<script>
// Current vendor ID for navigation
window.currentVendorId = <?php echo absint( $vendor_id ); ?>;
</script>


<div class="dokan-profile-frame-wrapper">
    <div class="profile-frame<?php echo esc_attr( $no_banner_class ); ?>">

        <div class="profile-info-box profile-layout-<?php echo esc_attr( $profile_layout ); ?>" data-vendor-id="<?php echo absint( $vendor_id ); ?>">
            <?php 
            // We render a neutral video container that our JS controls. Banner image is used as poster only.
                 $video_position = get_user_meta( $store_user->get_id(), 'dokan_banner_video_position', true );
                 $video_position = $video_position ? $video_position : 'center';
                 $video_style_attr = '';
                 if ( 'center' !== $video_position ) {
                  $video_style_attr = ' style="object-position: ' . esc_attr( $video_position ) . ';"';
                 }
            $banner_poster = $store_user->get_banner();
            ?>
                 <video class="profile-info-img profile-banner-video hero-video-slot hero-video-slot-a" playsinline preload="none"<?php echo $video_style_attr; ?>
                     <?php if ( $banner_poster ) { ?>poster="<?php echo esc_url( $banner_poster ); ?>"<?php } ?>></video>
            <?php
            // A/B buffer slots — preload next/prev items so swaps require no src change during gesture
            $buffer_style = 'display:none;';
            if ( $video_position && 'center' !== $video_position ) {
                $buffer_style .= ' object-position: ' . esc_attr( $video_position ) . ';';
            }
            ?>
            <video class="profile-banner-video hero-video-slot hero-video-slot-b" playsinline preload="none" muted style="<?php echo esc_attr( $buffer_style ); ?>"></video>
            <video class="profile-banner-video hero-video-slot hero-video-slot-c" playsinline preload="none" muted style="<?php echo esc_attr( $buffer_style ); ?>"></video>
            <?php if ( ! $banner_poster ) { ?>
                <div class="profile-info-img dummy-image">&nbsp;</div>
            <?php } ?>

            <img class="hero-media-image" alt="Store media" />
            <audio class="hero-audio" preload="metadata"></audio>
            <div class="hero-audio-eq" aria-hidden="true">
                <span class="eq-bar"></span>
                <span class="eq-bar"></span>
                <span class="eq-bar"></span>
                <span class="eq-bar"></span>
                <span class="eq-bar"></span>
            </div>

            <?php if ( ! $is_onboarding_context ) : ?>
                <!-- Cinematic remote controls: visual layer only (stage 1) -->
                <div class="hero-remote" aria-label="Media controls (visual mockup)">
                    <div class="hero-remote-row">
                        <button class="hero-btn hero-prev" type="button" aria-label="Previous media">
                            <span class="hero-btn-icon" title="Previous media"><i class="fas fa-chevron-up" aria-hidden="true"></i></span>
                        </button>
                        <button class="hero-btn hero-play" type="button" aria-label="Play media (press space)">
                            <span class="hero-btn-icon" title="Play media (press space)"><i class="fas fa-play" aria-hidden="true"></i></span>
                        </button>
                        <button class="hero-btn hero-pause" type="button" aria-label="Pause media (press space)">
                            <span class="hero-btn-icon" title="Pause media (press space)"><i class="fas fa-pause" aria-hidden="true"></i></span>
                        </button>
                        <button class="hero-btn hero-next" type="button" aria-label="Next media">
                            <span class="hero-btn-icon" title="Next media"><i class="fas fa-chevron-down" aria-hidden="true"></i></span>
                        </button>
                    </div>
                    <div class="hero-toggle-row">
                        <label class="hero-toggle"><input type="checkbox" class="hero-toggle-full" /><span class="toggle-text-full"> Play full duration</span><span class="toggle-text-short"> Play full</span></label>
                        <label class="hero-toggle"><input type="checkbox" class="hero-toggle-loop" /><span class="toggle-text-full"> Loop this media</span><span class="toggle-text-short"> Loop this</span></label>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Global controls - bottom right corner -->
            <div class="hero-global-controls" aria-label="Global playback controls">
                <button class="hero-global-btn hero-global-mute" type="button" aria-label="Toggle sound (press M)">
                    <span class="mute-icon mute-on" title="Sound on (press M)"><i class="fas fa-volume-up" aria-hidden="true"></i></span>
                    <span class="mute-icon mute-off" title="Muted (press M)"><i class="fas fa-volume-mute" aria-hidden="true"></i></span>
                </button>
                <div class="hero-resolution-wrap">
                    <button class="hero-global-btn hero-global-resolution" type="button" aria-label="Resolution: HD (coming soon)" aria-haspopup="true" aria-expanded="false" title="Resolution: HD (coming soon)">
                        <span class="resolution-label">HD</span>
                    </button>
                    <div class="hero-resolution-panel" role="menu" aria-label="Resolution options">
                        <button class="hero-resolution-option is-active" type="button" role="menuitem" aria-disabled="true">HD</button>
                        <button class="hero-resolution-option is-disabled" type="button" role="menuitem" aria-disabled="true">2K</button>
                        <button class="hero-resolution-option is-disabled" type="button" role="menuitem" aria-disabled="true">4K</button>
                        <button class="hero-resolution-option is-disabled" type="button" role="menuitem" aria-disabled="true">8K</button>
                    </div>
                </div>
                <button class="hero-global-btn hero-global-fullscreen" type="button" aria-label="Full screen (F11, Esc to exit)" aria-pressed="false" title="Full screen (F11, Esc to exit)">
                    <i class="fas fa-expand" aria-hidden="true"></i>
                </button>
                <button class="hero-global-btn hero-global-theatre" type="button" aria-label="Theatre mode (Esc to exit)" aria-pressed="false" title="Theatre mode (Esc to exit)">
                    <i class="fas fa-film" aria-hidden="true"></i>
                </button>
            </div>

            <div class="profile-info-summery-wrapper dokan-clearfix">
                <div class="profile-info-summery">
                    <?php if ( $is_owner ) : ?>
                        <div class="banner-edit-actions" role="group" aria-label="Media editors">
                            <button class="edit-banner-btn" type="button" title="Change Banner" aria-label="Change Banner">
                                <i class="fas fa-image" aria-hidden="true"></i>
                                <i class="fas fa-pen edit-banner-btn__pen" aria-hidden="true"></i>
                            </button>
                            <button class="edit-media-btn edit-media-gallery-btn" type="button" title="Edit Image Playlist" aria-label="Edit Image Playlist">
                                <i class="fas fa-images" aria-hidden="true"></i>
                                <i class="fas fa-pen edit-banner-btn__pen" aria-hidden="true"></i>
                            </button>
                            <button class="edit-media-btn edit-media-video-btn" type="button" title="Edit Video Playlist" aria-label="Edit Video Playlist">
                                <i class="fas fa-video" aria-hidden="true"></i>
                                <i class="fas fa-pen edit-banner-btn__pen" aria-hidden="true"></i>
                            </button>
                            <button class="edit-media-btn edit-media-audio-btn" type="button" title="Edit Audio Playlist" aria-label="Edit Audio Playlist">
                                <i class="fas fa-music" aria-hidden="true"></i>
                                <i class="fas fa-pen edit-banner-btn__pen" aria-hidden="true"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php
                    $profile_head_class = 'profile-info-head';
                    if ( ! wp_is_mobile() ) {
                        $profile_head_class .= ' is-collapsed';
                    }
                    ?>
                    <div class="<?php echo esc_attr( $profile_head_class ); ?>">
                        <?php // Collapsed tab with avatar and vendor name rotated 90deg ?>
                        <span class="collapsed-tab-label">
                            <span class="collapsed-tab-name">
                                <?php echo esc_html( $store_user->get_shop_name() ); ?>
                            </span>
                        </span>
                        <div class="profile-img <?php echo esc_attr( $profile_img_class ); ?><?php echo $is_owner ? ' editable-avatar' : ''; ?>" data-vendor-id="<?php echo esc_attr( $store_user->get_id() ); ?>">
                            <img src="<?php echo esc_url( $store_user->get_avatar() ); ?>"
                                alt="<?php echo esc_attr( $store_user->get_shop_name() ); ?>"
                                size="150"
                                class="avatar-image">
                            <?php if ( $is_owner ) : ?>
                                <button class="edit-avatar-btn" type="button" title="Change Avatar">
                                    <i class="fas fa-camera"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ( $is_featured === 'yes' || $is_verified === 'yes' ) : ?>
                            <div class="featured-favourite-avatar-overlay">
                                <?php if ( $is_featured === 'yes' ) : ?>
                                    <div class="featured-label"><?php esc_html_e( 'Featured', 'dokan-lite' ); ?></div>
                                <?php endif; ?>
                                <?php if ( $is_verified === 'yes' ) : ?>
                                    <div class="verified-label"><?php esc_html_e( 'Verified', 'dokan-lite' ); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    <div class="profile-info-content<?php echo $is_owner ? ' has-contact-card' : ''; ?>">

                        <?php if ( $onboard_error ) : ?>
                            <div class="tm-onboard-notice tm-onboard-error">We could not complete onboarding. Please try again or contact support.</div>
                        <?php endif; ?>
                        <?php if ( 'default' === $profile_layout && ( ! empty( $store_user->get_shop_name() ) || $is_owner || $is_preonboard || $is_admin_editing ) ) { ?>
                            <?php // Badge slot no longer needed in content flow ?>
                        <div class="talent-info-block overlay-section" data-section="talent-info">
                            <?php if ( $onboard_claimed || ! empty( $onboard_state['valid'] ) ) : ?>
                                <?php $admin_name = ! empty( $onboard_state['admin_name'] ) ? $onboard_state['admin_name'] : 'An admin'; ?>
                                <div class="tm-onboard-help">
                                    <button type="button" class="help-toggle-btn" aria-label="Show help" data-help-text="<?php echo esc_attr( $admin_name ); ?> has pre-filled your talent profile. Please review and complete any missing fields, then click Save.">
                                        <i class="fas fa-question-circle" aria-hidden="true"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                                <?php if ( $is_admin_editing && $is_preonboard ) : ?>
                                    <div class="tm-onboard-actions tm-onboard-actions--rail">
                                        <button class="tm-onboard-share-btn" type="button" data-vendor-id="<?php echo esc_attr( $vendor_id ); ?>">Share Onboarding Link</button>
                                    </div>
                                <?php endif; ?>
                                <?php
                                $store_name_value = $store_user->get_shop_name();
                                $store_name_display = $store_name_value ? $store_name_value : 'Talent Name';
                                ?>
                            <div class="store-name-wrapper<?php echo $is_owner ? ' editable-field' : ''; ?>" data-field="store_name" data-help="You can display your real name or a stage name.">
                                <div class="field-display">
                                    <h1 class="store-name">
                                            <span class="field-value<?php echo $store_name_value ? '' : ' is-empty'; ?>"><?php echo esc_html( $store_name_display ); ?></span>
                                        <?php do_action( 'dokan_store_header_after_store_name', $store_user ); ?>
                                    </h1>
                                    <?php if ( $is_owner ) : ?>
                                        <button class="edit-field-btn profile-edit-btn" type="button" title="Edit Name">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php if ( $is_owner ) : ?>
                                    <div class="field-edit">
                                        <label>EDIT Talent Name</label>
                                        <input type="text" name="store_name" class="edit-field-input" value="<?php echo esc_attr( $store_user->get_shop_name() ); ?>">
                                        <div class="field-edit-actions">
                                            <button class="save-field-btn" type="button"><i class="fas fa-check"></i> Save</button>
                                            <button class="cancel-field-btn" type="button"><i class="fas fa-times"></i> Cancel</button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php // Store categories (inline editable) ?>
                            <?php if ( ! empty( $store_category_display ) || $is_owner || $is_preonboard || $is_admin_editing ) : ?>
        		                        <div class="store-categories-wrapper<?php echo $is_owner ? ' editable-field' : ''; ?>" data-field="store_categories" data-help="Use CTRL + click to select multiple options">
                                    <div class="field-display">
                                        <div class="store-categories-display tm-combo-pill<?php echo $_tm_level_tier ? ' tm-combo-pill--' . esc_attr( $_tm_level_tier ) : ''; ?>">
                                            <span class="tm-combo-pill__cats-row">
                                                <span class="tm-combo-pill__cats field-value" title="<?php echo esc_attr( $store_category_display_full ); ?>"><?php echo esc_html( $store_category_display ? $store_category_display : 'Categories' ); ?></span>
                                                <?php if ( $is_owner ) : ?>
                                                    <button class="edit-field-btn profile-edit-btn" type="button" title="Edit Categories">
                                                        <i class="fas fa-pencil-alt"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </span>
                                            <?php if ( $_tm_level_label ) : ?>
                                            <span class="tm-combo-pill__level"><?php echo esc_html( $_tm_level_label ); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ( $is_owner ) : ?>
                                        <div class="field-edit" data-is-multi="true">
                                            <label>
                                                EDIT Categories
                                                <span class="multi-select-indicator">(multi-select)</span>
                                                <span class="help-icon-wrapper">
                                                    <button type="button" class="help-toggle-btn" aria-label="Show help" data-help-text="Use CTRL + click to select multiple options">
                                                        <i class="fas fa-question-circle" aria-hidden="true"></i>
                                                    </button>
                                                </span>
                                            </label>
                                            <div class="field-edit-body">
                                                <select name="store_categories[]" class="edit-field-input" data-field="store_categories" multiple size="5">
                                                    <?php foreach ( $store_category_options as $term_id => $term_name ) : ?>
                                                        <option value="<?php echo esc_attr( $term_id ); ?>"<?php echo in_array( $term_id, $selected_category_ids, true ) ? ' selected' : ''; ?>><?php echo esc_html( $term_name ); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="field-edit-actions">
                                                <button class="save-field-btn" type="button"><i class="fas fa-check"></i> Save</button>
                                                <button class="cancel-field-btn" type="button"><i class="fas fa-times"></i> Cancel</button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php // Display Mapbox location only in profile-info-head ?>
                            <?php
                            $geo_location_display = function_exists( 'tm_get_vendor_geo_location_display' )
                                ? tm_get_vendor_geo_location_display( $store_user->get_id(), $store_info, $store_address )
                                : '';
                            $geo_address_raw = get_user_meta( $store_user->get_id(), 'dokan_geo_address', true );
                            ?>
                            <?php if ( ! dokan_is_vendor_info_hidden( 'address' ) && ( ! empty( $geo_location_display ) || $is_owner || $is_preonboard || $is_admin_editing ) ) { ?>
                                <?php $geo_location_display_text = $geo_location_display ? $geo_location_display : 'Location'; ?>
                                <div class="location-wrapper<?php echo $is_owner ? ' editable-field' : ''; ?>" data-field="geo_location" data-help="Start typing to search for your location. Select from the dropdown to autocomplete.">
                                    <div class="field-display">
                                        <div class="dokan-store-address-head">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span class="field-value"><?php echo wp_kses_post( $geo_location_display_text ); ?></span>
                                        </div>
                                        <?php if ( $is_owner ) : ?>
                                            <button class="edit-field-btn profile-edit-btn" type="button" title="Edit Location">
                                                <i class="fas fa-pencil-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ( $is_owner ) : ?>
                                        <div class="field-edit">
                                            <label>
                                                EDIT Location
                                                <span class="help-icon-wrapper">
                                                    <button type="button" class="help-toggle-btn" aria-label="Show help" data-help-text="Start typing to search for your location. Select from the dropdown to autocomplete.">
                                                        <i class="fas fa-question-circle" aria-hidden="true"></i>
                                                    </button>
                                                </span>
                                            </label>
                                            <input type="text" id="vendor-location-search-head" name="geo_location" class="edit-field-input location-search-input" value="<?php echo esc_attr( $geo_address_raw ); ?>" placeholder="Start typing location...">
                                            <input type="hidden" id="vendor-location-data-head" name="location_data" value="">
                                            <div class="inline-mapbox-panel" data-lat="<?php echo esc_attr( $geo_lat_attr ); ?>" data-lng="<?php echo esc_attr( $geo_lng_attr ); ?>">
                                                <div class="inline-mapbox-search"></div>
                                                <div class="inline-mapbox-map"></div>
                                            </div>
                                            <div class="field-edit-actions">
                                                <button class="save-field-btn" type="button"><i class="fas fa-check"></i> Save</button>
                                                <button class="cancel-field-btn" type="button"><i class="fas fa-times"></i> Cancel</button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php } ?>
								</div>
                            
                            <?php if ( $is_owner ) : ?>
                                <div class="contact-info-section overlay-section" data-section="contact-info">
                                    <div class="contact-info-title">Contact Details</div>
                                    <div class="contact-email-wrapper editable-field" data-field="contact_emails" data-help="Enter up to 3 emails and mark 1 of them as main">
                                        <div class="field-display">
                                            <div class="contact-info-row">
                                                <span class="contact-label">Main Email</span>
                                                <span class="contact-value-line">
                                                    <span class="contact-value contact-email-value"><?php echo esc_html( $contact_email_display ); ?></span>
                                                    <?php if ( $contact_email_extra_count > 0 ) : ?>
                                                        <span class="contact-extra-count contact-email-count">(+<?php echo esc_html( $contact_email_extra_count ); ?>)</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <button class="edit-field-btn profile-edit-btn edit-contact-email-btn" type="button" title="Edit Contact Email">
                                                <i class="fas fa-pencil-alt"></i>
                                            </button>
                                        </div>
                                        <div class="field-edit">
                                            <label>
                                                EDIT Contact Emails
                                                <span class="help-icon-wrapper">
                                                    <button type="button" class="help-toggle-btn" aria-label="Show help" data-help-text="Enter up to 3 emails and mark 1 of them as main">
                                                        <i class="fas fa-question-circle" aria-hidden="true"></i>
                                                    </button>
                                                </span>
                                            </label>
                                            <div class="field-edit-body">
                                                <div class="contact-main-summary">
                                                    <span class="contact-label">Main Email</span>
                                                    <span class="contact-value contact-email-main-value"><?php echo esc_html( $contact_email_display ); ?></span>
                                                </div>
                                                <div class="contact-edit-entries">
                                                    <div class="contact-edit-list" data-type="email" data-radio-name="<?php echo esc_attr( $contact_email_radio_name ); ?>">
                                                        <?php for ( $i = 0; $i < 3; $i++ ) : ?>
                                                            <?php $email_item = isset( $contact_email_rows[ $i ] ) ? $contact_email_rows[ $i ] : ''; ?>
                                                            <?php $is_main = $contact_email_main ? ( $email_item === $contact_email_main ) : ( 0 === $i ); ?>
                                                            <div class="contact-edit-row">
                                                                <input type="email" class="edit-field-input contact-list-input contact-email-input" value="<?php echo esc_attr( $email_item ); ?>" placeholder="name@email.com">
                                                                <label class="contact-main-choice">
                                                                    <input type="radio" class="contact-main-radio" name="<?php echo esc_attr( $contact_email_radio_name ); ?>"<?php checked( $is_main ); ?>>
                                                                    <span>Main</span>
                                                                </label>
                                                            </div>
                                                    <?php endfor; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="field-edit-actions">
                                                <button class="save-field-btn" type="button"><i class="fas fa-check"></i> Save</button>
                                                <button class="cancel-field-btn" type="button"><i class="fas fa-times"></i> Cancel</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="contact-phone-wrapper editable-field" data-field="contact_phones" data-help="Enter up to 3 phones and mark 1 of them as main">
                                        <div class="field-display">
                                            <div class="contact-info-row is-inline">
                                                <span class="contact-label">Main Phone</span>
                                                <span class="contact-value-line">
                                                    <span class="contact-value contact-phone-value"><?php echo esc_html( $contact_phone_display ); ?></span>
                                                    <?php if ( $contact_phone_extra_count > 0 ) : ?>
                                                        <span class="contact-extra-count contact-phone-count">(+<?php echo esc_html( $contact_phone_extra_count ); ?>)</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <button class="edit-field-btn profile-edit-btn edit-contact-phone-btn" type="button" title="Edit Phone">
                                                <i class="fas fa-pencil-alt"></i>
                                            </button>
                                        </div>
                                        <div class="field-edit">
                                            <label>
                                                EDIT Contact Phones
                                                <span class="help-icon-wrapper">
                                                    <button type="button" class="help-toggle-btn" aria-label="Show help" data-help-text="Enter up to 3 phones and mark 1 of them as main">
                                                        <i class="fas fa-question-circle" aria-hidden="true"></i>
                                                    </button>
                                                </span>
                                            </label>
                                            <div class="field-edit-body">
                                                <div class="contact-main-summary">
                                                    <span class="contact-label">Main Phone</span>
                                                    <span class="contact-value contact-phone-main-value"><?php echo esc_html( $contact_phone_display ); ?></span>
                                                </div>
                                                <div class="contact-edit-entries">
                                                    <div class="contact-edit-list" data-type="phone" data-radio-name="<?php echo esc_attr( $contact_phone_radio_name ); ?>">
                                                        <?php for ( $i = 0; $i < 3; $i++ ) : ?>
                                                            <?php $phone_item = isset( $contact_phone_rows[ $i ] ) ? $contact_phone_rows[ $i ] : ''; ?>
                                                            <?php $is_main = $contact_phone_main ? ( $phone_item === $contact_phone_main ) : ( 0 === $i ); ?>
                                                            <div class="contact-edit-row">
                                                                <input type="text" class="edit-field-input contact-list-input contact-phone-input" value="<?php echo esc_attr( $phone_item ); ?>" placeholder="+1 302 548 7789">
                                                                <label class="contact-main-choice">
                                                                    <input type="radio" class="contact-main-radio" name="<?php echo esc_attr( $contact_phone_radio_name ); ?>"<?php checked( $is_main ); ?>>
                                                                    <span>Main</span>
                                                                </label>
                                                            </div>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="field-edit-actions">
                                                <button class="save-field-btn" type="button"><i class="fas fa-check"></i> Save</button>
                                                <button class="cancel-field-btn" type="button"><i class="fas fa-times"></i> Cancel</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else : ?>
                                <?php if ( $is_owner ) : ?>
                                    <div class="contact-info-section">
                                        <div class="contact-info-title">Contact Details</div>
                                        <div class="contact-email-wrapper editable-field" data-field="contact_emails">
                                            <div class="field-display">
                                                <div class="contact-info-row">
                                                    <span class="contact-label">Main Email</span>
                                                    <span class="contact-value-line">
                                                        <span class="contact-value contact-email-value"><?php echo esc_html( $contact_email_display ); ?></span>
                                                        <?php if ( $contact_email_extra_count > 0 ) : ?>
                                                            <span class="contact-extra-count contact-email-count">(+<?php echo esc_html( $contact_email_extra_count ); ?>)</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <button class="edit-field-btn profile-edit-btn edit-contact-email-btn" type="button" title="Edit Contact Email">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                            </div>
                                            <div class="field-edit">
                                                <label>
                                                    EDIT Contact Emails
                                                    <span class="help-icon-wrapper">
                                                        <button type="button" class="help-toggle-btn" aria-label="Show help" data-help-text="Enter up to 3 emails and mark 1 of them as main">
                                                            <i class="fas fa-question-circle" aria-hidden="true"></i>
                                                        </button>
                                                    </span>
                                                </label>
                                                <div class="field-edit-body">
                                                    <div class="contact-main-summary">
                                                        <span class="contact-label">Main Email</span>
                                                        <span class="contact-value contact-email-main-value"><?php echo esc_html( $contact_email_display ); ?></span>
                                                    </div>
                                                    <div class="contact-edit-entries">
                                                        <div class="contact-edit-list" data-type="email" data-radio-name="<?php echo esc_attr( $contact_email_radio_name ); ?>">
                                                            <?php for ( $i = 0; $i < 3; $i++ ) : ?>
                                                                <?php $email_item = isset( $contact_email_rows[ $i ] ) ? $contact_email_rows[ $i ] : ''; ?>
                                                                <?php $is_main = $contact_email_main ? ( $email_item === $contact_email_main ) : ( 0 === $i ); ?>
                                                                <div class="contact-edit-row">
                                                                    <input type="email" class="edit-field-input contact-list-input contact-email-input" value="<?php echo esc_attr( $email_item ); ?>" placeholder="name@email.com">
                                                                    <label class="contact-main-choice">
                                                                        <input type="radio" class="contact-main-radio" name="<?php echo esc_attr( $contact_email_radio_name ); ?>"<?php checked( $is_main ); ?>>
                                                                        <span>Main</span>
                                                                    </label>
                                                                </div>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="field-edit-actions">
                                                    <button class="save-field-btn" type="button"><i class="fas fa-check"></i> Save</button>
                                                    <button class="cancel-field-btn" type="button"><i class="fas fa-times"></i> Cancel</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="contact-phone-wrapper editable-field" data-field="contact_phones">
                                            <div class="field-display">
                                                <div class="contact-info-row is-inline">
                                                    <span class="contact-label">Main Phone</span>
                                                    <span class="contact-value-line">
                                                        <span class="contact-value contact-phone-value"><?php echo esc_html( $contact_phone_display ); ?></span>
                                                        <?php if ( $contact_phone_extra_count > 0 ) : ?>
                                                            <span class="contact-extra-count contact-phone-count">(+<?php echo esc_html( $contact_phone_extra_count ); ?>)</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <button class="edit-field-btn profile-edit-btn edit-contact-phone-btn" type="button" title="Edit Phone">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                            </div>
                                            <div class="field-edit">
                                                <label>
                                                    EDIT Contact Phones
                                                    <span class="help-icon-wrapper">
                                                        <button type="button" class="help-toggle-btn" aria-label="Show help" data-help-text="Enter up to 3 phones and mark 1 of them as main">
                                                            <i class="fas fa-question-circle" aria-hidden="true"></i>
                                                        </button>
                                                    </span>
                                                </label>
                                                <div class="field-edit-body">
                                                    <div class="contact-main-summary">
                                                        <span class="contact-label">Main Phone</span>
                                                        <span class="contact-value contact-phone-main-value"><?php echo esc_html( $contact_phone_display ); ?></span>
                                                    </div>
                                                    <div class="contact-edit-entries">
                                                        <div class="contact-edit-list" data-type="phone" data-radio-name="<?php echo esc_attr( $contact_phone_radio_name ); ?>">
                                                            <?php for ( $i = 0; $i < 3; $i++ ) : ?>
                                                                <?php $phone_item = isset( $contact_phone_rows[ $i ] ) ? $contact_phone_rows[ $i ] : ''; ?>
                                                                <?php $is_main = $contact_phone_main ? ( $phone_item === $contact_phone_main ) : ( 0 === $i ); ?>
                                                                <div class="contact-edit-row">
                                                                    <input type="text" class="edit-field-input contact-list-input contact-phone-input" value="<?php echo esc_attr( $phone_item ); ?>" placeholder="+1 302 548 7789">
                                                                    <label class="contact-main-choice">
                                                                        <input type="radio" class="contact-main-radio" name="<?php echo esc_attr( $contact_phone_radio_name ); ?>"<?php checked( $is_main ); ?>>
                                                                        <span>Main</span>
                                                                    </label>
                                                                </div>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="field-edit-actions">
                                                    <button class="save-field-btn" type="button"><i class="fas fa-check"></i> Save</button>
                                                    <button class="cancel-field-btn" type="button"><i class="fas fa-times"></i> Cancel</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else : ?>
                                    <?php // CTA Buttons with QR Code ?>
                                    <div class="vendor-cta-buttons">
                                        <div class="vendor-cta-qr">
                                            <?php
                                            $qr_markup = function_exists( 'tm_get_vendor_qr_svg_markup' )
                                                ? tm_get_vendor_qr_svg_markup( $store_user->get_id(), [ 'context' => 'guest-cta' ] )
                                                : '';
                                            ?>
                                            <?php if ( $qr_markup ) : ?>
                                                <?php echo $qr_markup; ?>
                                            <?php else : ?>
                                                <div class="qr-code-placeholder qr-code-fallback">
                                                    <i class="fas fa-qrcode" aria-hidden="true"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="vendor-cta-actions">
                                            <a href="#" class="vendor-cta-btn"><i class="fas fa-question" aria-hidden="true"></i> <?php esc_html_e( 'Ask question', 'dokan-lite' ); ?></a>
                                            <a href="#" class="vendor-cta-btn"><i class="far fa-calendar-alt" aria-hidden="true"></i> <?php esc_html_e( 'Book session', 'dokan-lite' ); ?></a>
                                        </div>
                                    </div>
                                    <div class="contact-channel-row" aria-label="Contact channel counts">
                                        <div class="contact-channel-item help-icon-wrapper">
                                            <button type="button" class="tm-header-icon contact-channel-icon help-toggle-btn" aria-label="Show help" data-help-text="<?php echo esc_attr( $contact_email_help ); ?>">
                                                <i class="fas fa-envelope" aria-hidden="true"></i>
                                                <span class="tm-header-count"><?php echo esc_html( $contact_email_count ); ?></span>
                                            </button>
                                        </div>
                                        <div class="contact-channel-item help-icon-wrapper">
                                            <button type="button" class="tm-header-icon contact-channel-icon help-toggle-btn" aria-label="Show help" data-help-text="<?php echo esc_attr( $contact_phone_help ); ?>">
                                                <i class="fas fa-phone" aria-hidden="true"></i>
                                                <span class="tm-header-count"><?php echo esc_html( $contact_phone_count ); ?></span>
                                            </button>
                                        </div>
                                        <div class="contact-channel-item help-icon-wrapper">
                                            <button type="button" class="tm-header-icon contact-channel-icon help-toggle-btn" aria-label="Show help" data-help-text="<?php echo esc_attr( $contact_social_help ); ?>">
                                                <i class="fas fa-share-alt" aria-hidden="true"></i>
                                                <span class="tm-header-count"><?php echo esc_html( $social_count ); ?></span>
                                            </button>
                                        </div>
                                        <div class="contact-channel-item help-icon-wrapper">
                                            <button type="button" class="tm-header-icon contact-channel-icon help-toggle-btn" aria-label="Show help" data-help-text="<?php echo esc_attr( $contact_media_help ); ?>">
                                                <i class="fas fa-photo-video" aria-hidden="true"></i>
                                                <span class="tm-header-count"><?php echo esc_html( $media_count ); ?></span>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php } ?>
					</div>
                    </div>

					
                        <?php if ( ! empty( $store_user->get_shop_name() ) && 'default' !== $profile_layout ) { ?>
                            <?php // Badges first to avoid empty gap when absent ?>
                            <div class="badge-slot">
                            <?php if ( $is_featured === 'yes' || $is_verified === 'yes' ) : ?>
                                <div class="featured-favourite">
                                    <?php if ( $is_featured === 'yes' ) : ?>
                                        <div class="featured-label"><?php esc_html_e( 'Featured', 'dokan-lite' ); ?></div>
                                    <?php endif; ?>
                                    <?php if ( $is_verified === 'yes' ) : ?>
                                        <div class="verified-label"><?php esc_html_e( 'Verified', 'dokan-lite' ); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            </div>
			    
                            <h1 class="store-name">
                                <?php echo esc_html( $store_user->get_shop_name() ); ?>
                                <?php do_action( 'dokan_store_header_after_store_name', $store_user ); ?>
                            </h1>
			    
                            <?php // Store categories (inline editable) ?>
                            <?php if ( ! empty( $store_category_display ) || $is_owner || $is_preonboard || $is_admin_editing ) : ?>
                                <div class="store-categories-wrapper<?php echo $is_owner ? ' editable-field' : ''; ?>" data-field="store_categories" data-help="Use CTRL + click to select multiple options">
                                    <div class="field-display">
                                        <div class="store-categories-display">
                                            <span class="field-value" title="<?php echo esc_attr( $store_category_display_full ); ?>"><?php echo esc_html( $store_category_display ? $store_category_display : 'Categories' ); ?></span>
                                            <?php if ( $is_owner ) : ?>
                                                <button class="edit-field-btn profile-edit-btn" type="button" title="Edit Categories">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ( $is_owner ) : ?>
                                        <div class="field-edit" data-is-multi="true">
                                            <label>
                                                EDIT Categories
                                                <span class="multi-select-indicator">(multi-select)</span>
                                                <span class="help-icon-wrapper">
                                                    <button type="button" class="help-toggle-btn" aria-label="Show help" data-help-text="Use CTRL + click to select multiple options">
                                                        <i class="fas fa-question-circle" aria-hidden="true"></i>
                                                    </button>
                                                </span>
                                            </label>
                                            <select name="store_categories[]" class="edit-field-input" data-field="store_categories" multiple size="5">
                                                <?php foreach ( $store_category_options as $term_id => $term_name ) : ?>
                                                    <option value="<?php echo esc_attr( $term_id ); ?>"<?php echo in_array( $term_id, $selected_category_ids, true ) ? ' selected' : ''; ?>><?php echo esc_html( $term_name ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="field-edit-actions">
                                                <button class="save-field-btn" type="button"><i class="fas fa-check"></i> Save</button>
                                                <button class="cancel-field-btn" type="button"><i class="fas fa-times"></i> Cancel</button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php // Display Mapbox location only in profile-info-head ?>
                            <?php
                            $geo_location_display = function_exists( 'tm_get_vendor_geo_location_display' )
                                ? tm_get_vendor_geo_location_display( $store_user->get_id(), $store_info, $store_address )
                                : '';
                            $geo_address_raw = get_user_meta( $store_user->get_id(), 'dokan_geo_address', true );
                            ?>
                            <?php if ( ! dokan_is_vendor_info_hidden( 'address' ) && ( ! empty( $geo_location_display ) || $is_owner || $is_preonboard || $is_admin_editing ) ) { ?>
                                <?php $geo_location_display_text = $geo_location_display ? $geo_location_display : 'Location'; ?>
                                <div class="location-wrapper<?php echo $is_owner ? ' editable-field' : ''; ?>" data-field="geo_location" data-help="Start typing to search for your location. Select from the dropdown to autocomplete.">
                                    <div class="field-display">
                                        <div class="dokan-store-address-head">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span class="field-value"><?php echo wp_kses_post( $geo_location_display_text ); ?></span>
                                        </div>
                                        <?php if ( $is_owner ) : ?>
                                            <button class="edit-field-btn profile-edit-btn" type="button" title="Edit Location">
                                                <i class="fas fa-pencil-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ( $is_owner ) : ?>
                                        <div class="field-edit">
                                            <label>
                                                EDIT Location
                                                <span class="help-icon-wrapper">
                                                    <button type="button" class="help-toggle-btn" aria-label="Show help" data-help-text="Start typing to search for your location. Select from the dropdown to autocomplete.">
                                                        <i class="fas fa-question-circle" aria-hidden="true"></i>
                                                    </button>
                                                </span>
                                            </label>
                                            <input type="text" id="vendor-location-search-info" name="geo_location" class="edit-field-input location-search-input" value="<?php echo esc_attr( $geo_address_raw ); ?>" placeholder="Start typing location...">
                                            <input type="hidden" id="vendor-location-data-info" name="location_data" value="">
                                            <div class="inline-mapbox-panel" data-lat="<?php echo esc_attr( $geo_lat_attr ); ?>" data-lng="<?php echo esc_attr( $geo_lng_attr ); ?>">
                                                <div class="inline-mapbox-search"></div>
                                                <div class="inline-mapbox-map"></div>
                                            </div>
                                            <div class="field-edit-actions">
                                                <button class="save-field-btn" type="button"><i class="fas fa-check"></i> Save</button>
                                                <button class="cancel-field-btn" type="button"><i class="fas fa-times"></i> Cancel</button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php } ?>
                            
                            <?php // CTA Buttons with QR Code ?>
                            <div class="vendor-cta-buttons">
                                <div class="vendor-cta-qr">
                                    <?php
                                    $qr_markup = function_exists( 'tm_get_vendor_qr_svg_markup' )
                                        ? tm_get_vendor_qr_svg_markup( $store_user->get_id(), [ 'context' => 'profile-cta' ] )
                                        : '';
                                    ?>
                                    <?php if ( $qr_markup ) : ?>
                                        <?php echo $qr_markup; ?>
                                    <?php else : ?>
                                        <div class="qr-code-placeholder qr-code-fallback">
                                            <i class="fas fa-qrcode" aria-hidden="true"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="vendor-cta-actions">
                                    <a href="#" class="vendor-cta-btn"><i class="fas fa-question" aria-hidden="true"></i> <?php esc_html_e( 'Ask question', 'dokan-lite' ); ?></a>
                                    <a href="#" class="vendor-cta-btn"><i class="far fa-calendar-alt" aria-hidden="true"></i> <?php esc_html_e( 'Book session', 'dokan-lite' ); ?></a>
                                </div>
                            </div>
                        <?php } ?>

                        <ul class="dokan-store-info">
                            <?php // Address moved to profile-info-head for better alignment in default layout ?>

                            <?php if ( ! dokan_is_vendor_info_hidden( 'phone' ) && ! empty( $store_user->get_phone() ) ) { ?>
                                <li class="dokan-store-phone">
                                    <i class="fas fa-mobile-alt"></i>
                                    <a href="tel:<?php echo esc_html( $store_user->get_phone() ); ?>"><?php echo esc_html( $store_user->get_phone() ); ?></a>
                                </li>
                            <?php } ?>

                            <?php if ( ! dokan_is_vendor_info_hidden( 'email' ) && $store_user->show_email() ) { ?>
                                <li class="dokan-store-email">
                                    <i class="far fa-envelope"></i>
                                    <a href="mailto:<?php echo esc_attr( antispambot( $store_user->get_email() ) ); ?>"><?php echo esc_attr( antispambot( $store_user->get_email() ) ); ?></a>
                                </li>
                            <?php } ?>

                            <li class="dokan-store-rating">
                                <i class="fas fa-star"></i>
                                <?php echo wp_kses_post( dokan_get_readable_seller_rating( $store_user->get_id() ) ); ?>
                            </li>

                            <?php if ( $show_store_open_close === 'on' && $dokan_store_time_enabled === 'yes' ) : ?>
                                <li class="dokan-store-open-close">
                                    <i class="fas fa-shopping-cart"></i>
                                    <div class="store-open-close-notice">
                                        <?php if ( dokan_is_store_open( $store_user->get_id() ) ) : ?>
                                            <span class='store-notice'><?php echo esc_attr( $store_open_notice ); ?></span>
                                        <?php else : ?>
                                            <span class='store-notice'><?php echo esc_attr( $store_closed_notice ); ?></span>
                                        <?php endif; ?>

                                        <span class="fas fa-angle-down"></span>
                                        <?php
                                        // Vendor store times template shown here.
                                        dokan_get_template_part(
                                            'store-header-times',
                                            '',
                                            [
                                                'dokan_store_times' => $dokan_store_times,
                                                'today'             => $today,
                                                'dokan_days' => dokan_get_translated_days(),
                                                'current_time' => $current_time,
                                                'times_heading' => __( 'Weekly Store Timing', 'dokan-lite' ),
                                                'closed_status' => __( 'CLOSED', 'dokan-lite' ),
                                            ]
                                        );
                                        ?>
                                    </div>
                                </li>
                            <?php endif ?>

                            <?php do_action( 'dokan_store_header_info_fields', $store_user->get_id() ); ?>
                        </ul>

                        <?php if ( $social_fields ) { ?>
                            <div class="store-social-wrapper">
                                <ul class="store-social">
                                    <?php foreach ( $social_fields as $key => $field ) { ?>
                                        <?php if ( ! empty( $social_info[ $key ] ) ) { ?>
                                            <li>
                                                <a href="<?php echo esc_url( $social_info[ $key ] ); ?>" target="_blank"><i class="fab fa-<?php echo esc_attr( $field['icon'] ); ?>"></i></a>
                                            </li>
                                        <?php } ?>
                                    <?php } ?>
                                </ul>
                            </div>
                        <?php } ?>

					
                </div><!-- .profile-info-summery -->
            </div><!-- .profile-info-summery-wrapper -->

            <div class="tm-location-modal" aria-hidden="true" role="dialog" aria-modal="true">
                <div class="tm-location-modal__backdrop"></div>
                <div class="tm-location-modal__dialog" role="document">
                    <button class="tm-location-modal__close" type="button" aria-label="Close location editor">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                    <div class="tm-location-modal__content"></div>
                </div>
            </div>

            <div class="tm-field-editor-modal" aria-hidden="true" role="dialog" aria-modal="true">
                <div class="tm-field-editor-backdrop"></div>
                <div class="tm-field-editor-dialog" role="document">
                    <div class="editor-header">
                        <h3 class="editor-title"></h3>
                    </div>
                    <div class="editor-body">
                        <!-- Content loaded dynamically -->
                    </div>
                    <div class="editor-footer">
                        <button class="editor-save-btn" type="button"><i class="fas fa-check"></i> Save</button>
                        <button class="editor-cancel-btn" type="button"><i class="fas fa-times"></i> Cancel</button>
                    </div>
                </div>
            </div>

            <?php if ( ! empty( $onboard_state['valid'] ) && ! is_user_logged_in() ) : ?>
                <div class="tm-onboard-claim-template" style="display:none;">
                    <?php
                    $admin_name = ! empty( $onboard_state['admin_name'] ) ? $onboard_state['admin_name'] : 'An admin';
                    $claim_vendor_id = method_exists( $store_user, 'get_id' ) ? $store_user->get_id() : ( $store_user->ID ?? 0 );
                    $admin_id = (int) get_user_meta( $claim_vendor_id, 'tm_preonboard_admin_id', true );
                    $admin_avatar_url = (string) get_user_meta( $claim_vendor_id, 'tm_preonboard_admin_avatar_url', true );
                    if ( ! $admin_avatar_url && $admin_id ) {
                        $admin_avatar_url = get_avatar_url( $admin_id );
                    }
                    $vendor_avatar_id = (int) get_user_meta( $claim_vendor_id, 'tm_preonboard_vendor_avatar_id', true );
                    $vendor_avatar_url = (string) get_user_meta( $claim_vendor_id, 'tm_preonboard_vendor_avatar_url', true );
                    if ( $vendor_avatar_id ) {
                        $vendor_avatar_url = wp_get_attachment_image_url( $vendor_avatar_id, 'thumbnail' ) ?: $vendor_avatar_url;
                    }
                    if ( ! $vendor_avatar_url ) {
                        $vendor_avatar_url = function_exists( 'mp_get_vendor_avatar_url' )
                            ? mp_get_vendor_avatar_url( $claim_vendor_id, 120 )
                            : get_avatar_url( $claim_vendor_id, array( 'size' => 120 ) );
                    }
                    $raw_message = (string) get_user_meta( $claim_vendor_id, 'tm_preonboard_admin_message', true );
                    $talent_name = $store_user->get_shop_name() ? $store_user->get_shop_name() : 'Talent';
                    $default_message = "Dear \$TalentName,\n\n\$AdminName is inviting you to join Casting Agency Co and has already pre-filled your profile. Create an account to claim your talent profile, you will then be able to complete/publish it.";
                    $raw_message = $raw_message ? $raw_message : $default_message;
                    $render_message = str_replace(
                        [ '$TalentName', '$AdminName' ],
                        [ $talent_name, $admin_name ],
                        $raw_message
                    );
                    ?>
                    <div class="tm-onboard-modal">
                        <div class="tm-onboard-claim-layout">
                            <div class="tm-onboard-claim-avatar">
                                <div class="tm-onboard-avatar-stack">
                                    <div class="tm-onboard-avatar-preview tm-onboard-avatar-preview--admin">
                                        <?php if ( $admin_avatar_url ) : ?>
                                            <img src="<?php echo esc_url( $admin_avatar_url ); ?>" alt="<?php echo esc_attr( $admin_name ); ?>" />
                                        <?php endif; ?>
                                    </div>
                                    <div class="tm-onboard-avatar-preview tm-onboard-avatar-preview--vendor">
                                        <?php if ( $vendor_avatar_url ) : ?>
                                            <img src="<?php echo esc_url( $vendor_avatar_url ); ?>" alt="<?php echo esc_attr( $talent_name ); ?>" />
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="tm-onboard-claim-message">
                                <?php echo wp_kses_post( nl2br( esc_html( $render_message ) ) ); ?>
                            </div>
                        </div>
                        <form class="tm-onboard-claim tm-onboard-claim--modal" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="tm_onboard_claim" />
                            <input type="hidden" name="vendor_id" value="<?php echo esc_attr( $claim_vendor_id ); ?>" />
                            <input type="hidden" name="tm_onboard" value="<?php echo esc_attr( $onboard_token ); ?>" />
                            <?php wp_nonce_field( 'tm_onboard_claim', 'tm_onboard_claim_nonce' ); ?>
                            <div class="tm-onboard-claim-fields">
                                <div class="tm-onboard-field">
                                    <label for="tm-onboard-email">Email</label>
                                    <input type="email" id="tm-onboard-email" name="email" required />
                                </div>
                                <div class="tm-onboard-field">
                                    <label for="tm-onboard-password">Password</label>
                                    <input type="password" id="tm-onboard-password" name="password" required minlength="6" />
                                </div>
                            </div>
                            <div class="tm-onboard-claim-terms">
                                <label>
                                    <input type="checkbox" name="tm_accept_privacy" required />
                                    I Accept the <a href="<?php echo esc_url( home_url( '/privacy' ) ); ?>" target="_blank" rel="noopener">privacy policy</a>
                                </label>
                                <label>
                                    <input type="checkbox" name="tm_accept_terms" required />
                                    I Accept the <a href="<?php echo esc_url( home_url( '/talent-terms' ) ); ?>" target="_blank" rel="noopener">talent terms</a>
                                </label>
                            </div>
                            <button class="tm-onboard-claim-btn" type="submit">Claim profile &amp; create account</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            $vendor_id = method_exists( $store_user, 'get_id' ) ? $store_user->get_id() : ( $store_user->ID ?? 0 );
            
            $store_categories = $vendor_id ? wp_get_object_terms( $vendor_id, 'store_category', array( 'fields' => 'slugs' ) ) : array();
            if ( is_wp_error( $store_categories ) ) {
                $store_categories = array();
            }
            
            // Normalize category slugs to lowercase for consistent checking
            $store_categories = array_map( 'strtolower', $store_categories );

            $physical_meta_keys = [
                'talent_height',
                'talent_weight',
                'talent_waist',
                'talent_hip',
                'talent_chest',
                'talent_shoe_size',
                'talent_eye_color',
                'talent_hair_color',
                'talent_hair_style',
            ];
            $has_physical_tab = false;
            $has_physical_category = $vendor_id && ( in_array( 'model', $store_categories, true ) || in_array( 'artist', $store_categories, true ) );
            if ( $has_physical_category ) {
                if ( $is_owner ) {
                    $has_physical_tab = true;
                } else {
                    foreach ( $physical_meta_keys as $meta_key ) {
                        $value = get_user_meta( $vendor_id, $meta_key, true );
                        if ( is_array( $value ) ) {
                            $value = array_filter( $value );
                        }
                        if ( $value !== '' && $value !== null && $value !== false && $value !== array() ) {
                            $has_physical_tab = true;
                            break;
                        }
                    }
                }
            }

            $cameraman_meta_keys = [
                'camera_type',
                'experience_level',
                'editing_software',
                'specialization',
                'years_experience',
                'equipment_ownership',
                'lighting_equipment',
                'audio_equipment',
                'drone_capability',
            ];
            $has_cameraman_tab = false;
            $has_cameraman_category = $vendor_id && in_array( 'cameraman', $store_categories, true );
            if ( $has_cameraman_category ) {
                if ( $is_owner ) {
                    $has_cameraman_tab = true;
                } else {
                    foreach ( $cameraman_meta_keys as $meta_key ) {
                        $value = get_user_meta( $vendor_id, $meta_key, true );
                        if ( is_array( $value ) ) {
                            $value = array_filter( $value );
                        }
                        if ( $value !== '' && $value !== null && $value !== false && $value !== array() ) {
                            $has_cameraman_tab = true;
                            break;
                        }
                    }
                }
            }
            ?>
            
            <?php
            // ── Profile Completion & Publish Strip ─────────────────────────────────
            // Shown to the vendor owner (or admin editing) for ALL vendors — published
            // or not — so every vendor sees their current level and what's needed next.
            // Calls tm_vendor_completeness() from includes/vendor-profile/vendor-completeness.php.
            if ( ( $is_owner || $is_admin_editing ) && ! $is_onboarding_context && function_exists( 'tm_vendor_completeness' ) ) :
                // Reuse the pre-computed completeness data from the top of this template.
                $_c = $_tm_completeness;
                if ( $_c ) :
                    $_l1    = $_c['level1'];
                    $_l2    = $_c['level2'];
                    $_pub   = $_c['published'];

                    // 'active' = published (any level) | 'ready' = L1 done, not published | 'incomplete' = L1 in progress
                    if ( $_pub ) {
                        $_state = 'active';
                    } elseif ( $_l1['complete'] ) {
                        $_state = 'ready';
                    } else {
                        $_state = 'incomplete';
                    }

                    $_miss         = count( $_l1['missing'] );
                    $_nonce        = wp_create_nonce( 'tm_vendor_publish' ); // needed for both publish AND unpublish
                    $_lib_have      = isset( $_l2['library'] )  ? (int) $_l2['library']  : 0;
                    $_playlist_have = isset( $_l2['playlist'] ) ? (int) $_l2['playlist'] : 0;
                    $_social_have   = isset( $_l2['social'] )   ? (int) $_l2['social']   : 0;

                    // ── "View details" popup: group missing L1 fields by section ──────────
                    $_field_sections = [
                        'Talent Name'        => 'Identity',   'Profile Photo'      => 'Identity',
                        'Banner Image'       => 'Identity',   'Location'           => 'Identity',
                        'Category'           => 'Identity',   'Phone'              => 'Contact',
                        'Email'              => 'Contact',
                        'Birth Date'         => 'Demographics', 'Ethnicity'        => 'Demographics',
                        'Languages'          => 'Demographics', 'Availability'     => 'Demographics',
                        'Notice Time'        => 'Demographics', 'Can Travel'       => 'Demographics',
                        'Daily Rate'         => 'Demographics', 'Education'        => 'Demographics',
                        'Height'     => 'Physical', 'Weight'    => 'Physical', 'Waist'     => 'Physical',
                        'Hip'        => 'Physical', 'Chest'     => 'Physical', 'Shoe Size' => 'Physical',
                        'Eye Color'  => 'Physical', 'Hair Color'=> 'Physical', 'Hair Style'=> 'Physical',
                        'Camera Type'         => 'Equipment', 'Experience Level'    => 'Equipment',
                        'Editing Software'    => 'Equipment', 'Specialization'      => 'Equipment',
                        'Years Experience'    => 'Equipment', 'Equipment Ownership' => 'Equipment',
                        'Lighting Equipment'  => 'Equipment', 'Audio Equipment'     => 'Equipment',
                        'Drone Capability'    => 'Equipment',
                    ];
                    $_missing_by_section = [];
                    foreach ( $_l1['missing'] as $_mf ) {
                        $_sec = isset( $_field_sections[ $_mf ] ) ? $_field_sections[ $_mf ] : 'Other';
                        $_missing_by_section[ $_sec ][] = $_mf;
                    }
                    if ( $_l1['complete'] && ! empty( $_l2['missing'] ) ) {
                        $_missing_by_section['Level 2 · Mediatic'] = array_map( function( $item ) use ( $_social_have ) {
                            if ( $item === 'All 4 Social Media URLs' ) {
                                return 'All 4 Social Media URLs (' . $_social_have . '/4 set)';
                            }
                            return $item;
                        }, $_l2['missing'] );
                    }
                    $_missing_json = wp_json_encode( $_missing_by_section );
                    ?>
            <div class="tm-publish-strip tm-publish-strip--<?php echo esc_attr( $_state ); ?>"
                 data-vendor-id="<?php echo esc_attr( $vendor_id ); ?>"
                 data-missing="<?php echo esc_attr( $_missing_json ); ?>"
                 <?php if ( $_nonce ) { echo 'data-nonce="' . esc_attr( $_nonce ) . '"'; } ?>>  
                <div class="tm-publish-strip__inner">

                    <?php if ( 'active' !== $_state ) : ?>
                    <div class="tm-publish-strip__message">
                        <?php if ( 'ready' === $_state ) : ?>
                            <span class="tm-strip-icon">✅</span>
                            <span class="tm-strip-text">Your profile is complete and ready to go live.</span>
                        <?php else : ?>
                            <span class="tm-strip-icon">⚠️</span>
                            <span class="tm-strip-text">
                                Profile incomplete —
                                <strong><?php echo esc_html( $_miss ); ?> field<?php echo $_miss !== 1 ? 's' : ''; ?></strong>
                                still needed before you can publish.
                            </span>
                            <?php if ( $_miss > 0 ) : ?>
                            <button type="button" class="tm-strip-details-btn" aria-expanded="false" aria-controls="tm-strip-details-popup">View details</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="tm-publish-strip__levels">

                        <div class="tm-level-row<?php echo $_l1['complete'] ? ' is-complete' : ''; ?>">
                            <div class="tm-level-meta">
                                <span class="tm-level-name">Lvl 1 · Basic</span>
                                <span class="tm-level-pct"><?php echo esc_html( $_l1['pct'] ); ?>%</span>
                            </div>
                            <div class="tm-level-track">
                                <div class="tm-level-fill" style="width:<?php echo esc_attr( $_l1['pct'] ); ?>%"></div>
                            </div>
                        </div>

                        <div class="tm-level-row<?php
                            if ( $_l2['complete'] ) { echo ' is-complete'; }
                            elseif ( ! $_l1['complete'] ) { echo ' is-locked'; }
                        ?>">
                            <div class="tm-level-meta">
                                <span class="tm-level-name">Lvl 2 · Mediatic</span>
                                <span class="tm-level-pct"><?php echo esc_html( $_l1['complete'] ? $_l2['pct'] : 0 ); ?>%</span>
                            </div>
                            <div class="tm-level-track">
                                <div class="tm-level-fill" style="width:<?php echo esc_attr( $_l1['complete'] ? $_l2['pct'] : 0 ); ?>%"></div>
                            </div>
                        </div>

                        <div class="tm-level-row is-locked is-placeholder">
                            <div class="tm-level-meta">
                                <span class="tm-level-name">Lvl 3 · Cinematic</span>
                                <span class="tm-level-soon">Soon</span>
                            </div>
                            <div class="tm-level-track">
                                <div class="tm-level-fill" style="width:0%"></div>
                            </div>
                        </div>

                    </div><!-- .tm-publish-strip__levels -->

                    <?php if ( 'active' === $_state ) : ?>
                    <div class="tm-strip-hint">
                        <?php if ( $_l2['complete'] ) : ?>
                            <span class="tm-strip-icon">🏆</span>
                            <span>Level 3 · Cinematic is coming soon — keep an eye out!</span>
                        <?php else :
                            $_lib_need      = max( 0, 6 - $_lib_have );
                            $_plist_need    = $_playlist_have >= 1 ? 0 : 1;
                            $_social_need   = max( 0, 4 - $_social_have );
                            $_next_parts    = array_filter( [
                                $_lib_need > 0    ? 'upload ' . $_lib_need . ' more file' . ( $_lib_need !== 1 ? 's' : '' ) . ' to your media library' : '',
                                $_plist_need > 0  ? 'assign at least 1 media item to your profile playlist' : '',
                                $_social_need > 0 ? 'add ' . $_social_need . ' more social media URL' . ( $_social_need !== 1 ? 's' : '' ) : '',
                            ] );
                        ?>
                            <span class="tm-strip-icon">⚡</span>
                            <span>
                                <?php if ( ! empty( $_next_parts ) ) : ?>
                                    <?php echo esc_html( ucfirst( implode( ', and ', $_next_parts ) ) ); ?> to reach <strong>Level 2 · Mediatic</strong>.
                                <?php else : ?>
                                    Working toward <strong>Level 2 · Mediatic</strong>&hellip;
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php
                    // ── Visibility (Publish / Unpublish) Action Button ──────────────────────
                    // State derives directly from $_state:
                    //   incomplete → locked  (grey,   disabled) — Level 1 not yet complete
                    //   ready      → hidden  (amber,  publish)  — L1 done, profile not live
                    //   active     → live    (green, unpublish) — profile currently published
                    $_vb_map = [
                        'incomplete' => [
                            'state'  => 'locked',
                            'action' => 'disabled',
                            'status' => 'Profile Hidden',
                            'cta'    => 'Complete Level 1 to Publish',
                            'icon'   => 'fas fa-lock',
                        ],
                        'ready' => [
                            'state'  => 'hidden',
                            'action' => 'publish',
                            'status' => 'Profile is Hidden',
                            'cta'    => 'Click to Go Live',
                            'icon'   => 'far fa-eye-slash',
                        ],
                        'active' => [
                            'state'  => 'live',
                            'action' => 'unpublish',
                            'status' => 'Profile is Live',
                            'cta'    => 'Click to Take Offline',
                            'icon'   => 'fas fa-circle',
                        ],
                    ];
                    $_vb = $_vb_map[ $_state ];
                    ?>
                    <div class="tm-visibility-btn tm-visibility-btn--<?php echo esc_attr( $_vb['state'] ); ?>"
                         data-action="<?php echo esc_attr( $_vb['action'] ); ?>"
                         <?php if ( 'disabled' !== $_vb['action'] ) : ?>role="button" tabindex="0"<?php endif; ?>
                         aria-label="<?php echo esc_attr( $_vb['cta'] ); ?>">
                        <span class="tm-vbtn__main">
                            <span class="tm-vbtn__status">
                                <i class="<?php echo esc_attr( $_vb['icon'] ); ?>" aria-hidden="true"></i>
                                <?php echo esc_html( $_vb['status'] ); ?>
                            </span>
                            <span class="tm-vbtn__sep" aria-hidden="true">·</span>
                            <span class="tm-vbtn__action"><?php echo esc_html( $_vb['cta'] ); ?></span>
                        </span>
                        <?php if ( 'unpublish' === $_vb['action'] ) : ?>
                        <span class="tm-vbtn__confirm" hidden>
                            Take your profile offline?&ensp;
                            <button class="tm-vbtn__yes" type="button">Yes, hide me</button>
                            <button class="tm-vbtn__no" type="button">Cancel</button>
                        </span>
                        <?php endif; ?>
                    </div>

                </div><!-- .tm-publish-strip__inner -->
            </div><!-- .tm-publish-strip -->

            <div id="tm-strip-details-popup" class="tm-strip-details-popup" hidden aria-hidden="true" role="dialog" aria-label="Missing profile fields">
                <div class="tm-strip-details-popup__arrow"></div>
                <div class="tm-strip-details-popup__inner">
                    <div class="tm-strip-details-popup__header">
                        <strong class="tm-strip-details-popup__title">Missing Profile Fields</strong>
                        <button type="button" class="tm-strip-details-close" aria-label="Close">✕</button>
                    </div>
                    <div class="tm-strip-details-popup__body"></div>
                </div>
            </div>

            <script>
            (function($){
                var _ajaxUrl = (window.vendorStoreData && vendorStoreData.ajax_url) || '/wp-admin/admin-ajax.php';

                // ── Core AJAX helper ────────────────────────────────────────────────────
                function tmFireVisibility( vendorId, nonce, actionType, $vbtn ) {
                    $vbtn.addClass('is-loading');
                    $vbtn.find('.tm-vbtn__action').text( actionType === 'publish' ? 'Publishing…' : 'Taking offline…' );
                    $.post( _ajaxUrl, {
                        action:      'tm_vendor_publish',
                        action_type: actionType,
                        vendor_id:   vendorId,
                        nonce:       nonce
                    }, function(res){
                        if ( res && res.success ) {
                            $vbtn.find('.tm-vbtn__action').text('Done! Reloading…');
                            setTimeout(function(){ window.location.reload(); }, 900);
                        } else {
                            var msg  = (res && res.data && res.data.message) ? res.data.message : 'Action failed. Please try again.';
                            var orig = actionType === 'publish' ? 'Click to Go Live' : 'Click to Take Offline';
                            $vbtn.removeClass('is-loading');
                            $vbtn.find('.tm-vbtn__action').text(orig);
                            $vbtn.find('.tm-vbtn__confirm').prop('hidden', true);
                            $vbtn.find('.tm-vbtn__main').css('opacity', '');
                            alert(msg);
                        }
                    }, 'json').fail(function(jqXHR){
                        // WordPress sometimes appends a trailing byte (e.g. a stray "0" from an
                        // old-style AJAX die) after the JSON body. jQuery's strict JSON parser
                        // then fires .fail() even though the action itself fully succeeded.
                        // Check the raw response text before treating this as a true failure.
                        if ( (jqXHR.responseText || '').indexOf('"success":true') !== -1 ) {
                            $vbtn.find('.tm-vbtn__action').text('Done! Reloading\u2026');
                            setTimeout(function(){ window.location.reload(); }, 900);
                            return;
                        }
                        var orig = actionType === 'publish' ? 'Click to Go Live' : 'Click to Take Offline';
                        $vbtn.removeClass('is-loading');
                        $vbtn.find('.tm-vbtn__action').text(orig);
                        $vbtn.find('.tm-vbtn__confirm').prop('hidden', true);
                        $vbtn.find('.tm-vbtn__main').css('opacity', '');
                        alert('Action failed. Please try again.');
                    });
                }

                // ── Single-click: publish OR unpublish ─────────────────────────────────
                $(document).on('click', '.tm-visibility-btn[data-action="publish"], .tm-visibility-btn[data-action="unpublish"]', function(){
                    if ( $(this).hasClass('is-loading') ) return;
                    var $strip     = $(this).closest('.tm-publish-strip');
                    var actionType = $(this).data('action');
                    tmFireVisibility( $strip.data('vendor-id'), $strip.data('nonce'), actionType, $(this) );
                });

                // ── "View details" missing-fields popup ────────────────────────────────
                var _sectionIcons = {
                    'Identity'            : '\uD83D\uDC64',
                    'Contact'             : '\uD83D\uDCDE',
                    'Demographics'        : '\uD83D\uDCCB',
                    'Physical'            : '\uD83D\uDCCF',
                    'Equipment'           : '\uD83C\uDFA5',
                    'Level 2 \u00B7 Mediatic' : '\uD83C\uDFAC',
                    'Other'               : '\uD83D\uDCCC',
                };

                function tmOpenDetailsPopup( $strip ) {
                    var raw = $strip.attr('data-missing');
                    if ( ! raw ) return;
                    var groups;
                    try { groups = JSON.parse(raw); } catch(e) { return; }
                    var $body = $('#tm-strip-details-popup .tm-strip-details-popup__body').empty();
                    var hasContent = false;
                    $.each( groups, function( section, items ) {
                        if ( ! items || ! items.length ) return;
                        hasContent = true;
                        var icon = _sectionIcons[section] || '\uD83D\uDCCC';
                        var $sec = $('<div class="tm-sdp-section">');
                        $sec.append( $('<div class="tm-sdp-section__name">').text( icon + '\u2002' + section ) );
                        var $ul = $('<ul class="tm-sdp-section__items">');
                        $.each( items, function(i, item) {
                            $ul.append( $('<li>').text(item) );
                        });
                        $sec.append($ul);
                        $body.append($sec);
                    });
                    if ( ! hasContent ) {
                        $body.append('<p class="tm-sdp-empty">All fields are complete!</p>');
                    }
                    var $popup = $('#tm-strip-details-popup');
                    $popup.prop('hidden', false).attr('aria-hidden','false');
                    // Position fixed, just below the strip
                    var rect = $strip[0].getBoundingClientRect();
                    $popup.css({
                        top   : ( rect.bottom + 6 ) + 'px',
                        left  : rect.left + 'px',
                        width : rect.width + 'px',
                    });
                }

                function tmCloseDetailsPopup() {
                    $('#tm-strip-details-popup').prop('hidden', true).attr('aria-hidden','true');
                    $('.tm-strip-details-btn').attr('aria-expanded','false');
                }

                $(document).on('click', '.tm-strip-details-btn', function(e) {
                    e.stopPropagation();
                    var $popup = $('#tm-strip-details-popup');
                    if ( ! $popup.prop('hidden') ) {
                        tmCloseDetailsPopup();
                        return;
                    }
                    tmOpenDetailsPopup( $(this).closest('.tm-publish-strip') );
                    $(this).attr('aria-expanded','true');
                });

                $(document).on('click', '.tm-strip-details-close', function(e) {
                    e.stopPropagation();
                    tmCloseDetailsPopup();
                });

                $(document).on('click', function(e) {
                    if ( ! $(e.target).closest('#tm-strip-details-popup, .tm-strip-details-btn').length ) {
                        if ( ! $('#tm-strip-details-popup').prop('hidden') ) {
                            tmCloseDetailsPopup();
                        }
                    }
                });

            })(jQuery);
            </script>
                    <?php
                endif; // $_c
            endif; // is_owner
            ?>

            <!-- Bottom Drawer: Tabs + Panels -->
            <div class="profile-bottom-drawer<?php echo $is_owner ? ' owner-viewing' : ''; ?>">
                <div class="profile-bottom-tabs">
                    <div class="bottom-tab-item" data-target="demographic-section">
                        <span class="bottom-tab-label">
                            <span class="bottom-tab-icon" aria-hidden="true"><i class="fas fa-id-card"></i></span>
                            <span class="bottom-tab-text"><span class="bottom-tab-word">Demographic</span><span class="bottom-tab-rest"> &amp; Availability</span></span>
                            <?php if ( $is_owner ) : ?><span class="tab-edit-icon" aria-hidden="true"><i class="fas fa-pencil-alt"></i></span><?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="bottom-tab-item" data-target="social-section">
                        <span class="bottom-tab-label">
                            <span class="bottom-tab-icon" aria-hidden="true"><i class="fas fa-chart-line"></i></span>
                            <span class="bottom-tab-text"><span class="bottom-tab-word">Social</span><span class="bottom-tab-rest"> Influence Metrics</span></span>
                            <?php if ( $is_owner ) : ?><span class="tab-edit-icon" aria-hidden="true"><i class="fas fa-pencil-alt"></i></span><?php endif; ?>
                        </span>
                    </div>
                    
                    <?php if ( $has_physical_tab ) : ?>
                        <div class="bottom-tab-item" data-target="physical-section">
                            <span class="bottom-tab-label">
                                <span class="bottom-tab-icon" aria-hidden="true"><i class="fas fa-ruler-combined"></i></span>
                                <span class="bottom-tab-text"><span class="bottom-tab-word">Physical</span><span class="bottom-tab-rest"> Attributes</span></span>
                                <?php if ( $is_owner ) : ?><span class="tab-edit-icon" aria-hidden="true"><i class="fas fa-pencil-alt"></i></span><?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <?php if ( $has_cameraman_tab ) : ?>
                        <div class="bottom-tab-item" data-target="cameraman-section">
                            <span class="bottom-tab-label">
                                <span class="bottom-tab-icon" aria-hidden="true"><i class="fas fa-camera"></i></span>
                                <span class="bottom-tab-text"><span class="bottom-tab-word">Equipment</span><span class="bottom-tab-rest"> &amp; Skills</span></span>
                                <?php if ( $is_owner ) : ?><span class="tab-edit-icon" aria-hidden="true"><i class="fas fa-pencil-alt"></i></span><?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php do_action( 'dokan_store_profile_bottom_drawer_primary', $store_user, $store_info ); ?>
                <?php do_action( 'dokan_store_profile_bottom_drawer', $store_user, $store_info ); ?>
            </div>
            <!-- End Bottom Drawer -->
                        <!-- Keyboard Navigation Controls - Bottom Left -->
                        <div class="keyboard-nav-container" aria-label="Navigation controls">
                            <div class="keyboard-nav-row keyboard-nav-top">
                                <button class="keyboard-nav-btn keyboard-nav-up" type="button" aria-label="Previous media" title="Previous media (↑)">
                                    <span class="keyboard-nav-icon">▲</span>
                                </button>
                            </div>
                            <div class="keyboard-nav-row keyboard-nav-bottom">
                                <button class="keyboard-nav-btn keyboard-nav-left" type="button" aria-label="Previous talent" title="Previous talent (←)">
                                    <span class="keyboard-nav-icon">▲</span>
                                </button>
                                <button class="keyboard-nav-btn keyboard-nav-down" type="button" aria-label="Next media" title="Next media (↓)">
                                    <span class="keyboard-nav-icon">▼</span>
                                </button>
                                <button class="keyboard-nav-btn keyboard-nav-right" type="button" aria-label="Next talent" title="Next talent (→)">
                                    <span class="keyboard-nav-icon">▲</span>
                                </button>
                                <button class="keyboard-nav-btn keyboard-nav-loop" type="button" aria-label="Toggle talent loop" aria-pressed="false" title="Talent loop off (advance to next talent)">
                                    <span class="keyboard-nav-icon"><i class="fas fa-sync-alt" aria-hidden="true"></i></span>
                                </button>
                            </div>
                        </div>
                        <!-- End Keyboard Navigation -->
            
            
        </div> <!-- .profile-info-box -->
    </div> <!-- .profile-frame -->

</div>
