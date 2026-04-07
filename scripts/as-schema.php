<?php
// Force ActionScheduler schema installation via the abstract schema system
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

global $wpdb;

// Find and load ActionScheduler schema classes
$as_base = WP_PLUGIN_DIR . '/woocommerce/packages/action-scheduler';

// Include necessary schema files
$schema_files = [
    $as_base . '/classes/abstracts/ActionScheduler_Abstract_Schema.php',
    $as_base . '/classes/schema/ActionScheduler_LoggerSchema.php',
    $as_base . '/classes/schema/ActionScheduler_StoreSchema.php',
];

foreach ($schema_files as $f) {
    if (file_exists($f)) {
        require_once $f;
        echo "Loaded: " . basename($f) . "\n";
    } else {
        echo "NOT FOUND: $f\n";
    }
}

// Create tables using the schema classes
if (class_exists('ActionScheduler_StoreSchema')) {
    $schema = new ActionScheduler_StoreSchema();
    $schema->register_tables(true);
    echo "ActionScheduler_StoreSchema::register_tables() called.\n";
}

if (class_exists('ActionScheduler_LoggerSchema')) {
    $schema = new ActionScheduler_LoggerSchema();
    $schema->register_tables(true);
    echo "ActionScheduler_LoggerSchema::register_tables() called.\n";
}

// Check result
$tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}actionscheduler%'");
echo "\nResulting tables:\n";
foreach ($tables as $t) {
    echo "  - $t\n";
}
echo count($tables) . " actionscheduler tables total.\n";
