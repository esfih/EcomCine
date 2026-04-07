<?php
/**
 * EcomCine Base Theme — functions.php
 *
 * Intentionally minimal. All business logic lives in the EcomCine plugin.
 * This file only:
 *   1. Registers the ecomcine-base-css handle so plugin CSS can declare it as a dep.
 *   2. Registers the primary nav menu location (used by the cinematic header).
 *   3. Adds basic theme supports required by WordPress core + WooCommerce.
 *
 * @package EcomCine_Base_Theme
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Theme setup
// ---------------------------------------------------------------------------
add_action( 'after_setup_theme', function() {
	// Nav menus.
	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'ecomcine-base' ),
		'footer'  => __( 'Footer Menu', 'ecomcine-base' ),
	) );

	// Core theme supports.
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array(
		'height'      => 80,
		'width'       => 200,
		'flex-height' => true,
		'flex-width'  => true,
	) );

	// WooCommerce — opt-in to WC stylesheet suppression so WC knows this theme
	// handles its own layout.
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
} );

// ---------------------------------------------------------------------------
// Enqueue: register a base ecomcine-base-css handle (zero-weight stylesheet).
// Plugin CSS can declare 'ecomcine-base-css' as a dependency for correct load order.
// ---------------------------------------------------------------------------
add_action( 'wp_enqueue_scripts', function() {
	wp_register_style(
		'ecomcine-base-css',
		get_stylesheet_uri(),   // style.css
		array(),
		'1.0.0'
	);
	wp_enqueue_style( 'ecomcine-base-css' );
}, 5 );
