<?php
/**
 * LiteSpeed Cache Object Cache
 * 
 * This file is loaded by WordPress before any other plugins.
 * It initializes the LiteSpeed Cache object cache backend.
 * 
 * @package LiteSpeed\Cache
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define LiteSpeed Cache Object Cache version
define( 'LSCWP_OBJECT_CACHE_VERSION', '1.0' );

// Check if LiteSpeed Cache plugin is loaded
if ( ! defined( 'LSCWP_PATH' ) ) {
    // LiteSpeed Cache plugin is not loaded
    // This prevents errors when LS Cache is deactivated
    return;
}

// Check if we're in the admin area
if ( is_admin() && ! defined( 'DOING_AJAX' ) && ! defined( 'DOING_CRON' ) ) {
    // In admin, we need to ensure LS Cache is loaded
    if ( ! file_exists( LSCWP_PATH . 'object-cache.php' ) ) {
        // LS Cache object-cache.php doesn't exist
        error_log( 'LiteSpeed Cache: object-cache.php not found at ' . LSCWP_PATH . 'object-cache.php' );
        return;
    }
}

// Initialize LiteSpeed Cache object cache
require_once LSCWP_PATH . 'object-cache.php';
