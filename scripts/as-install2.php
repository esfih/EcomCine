<?php
// Bootstrap WordPress + ActionScheduler and force table creation
define('DOING_CRON', true);

// Include ActionScheduler loader directly and force table creation
$as_path = WP_PLUGIN_DIR . '/woocommerce/packages/action-scheduler/classes/data-stores/ActionScheduler_DBStore.php';
if (!file_exists($as_path)) {
    echo "ERROR: ActionScheduler_DBStore.php not found at: $as_path\n";
    exit(1);
}

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// Force ActionScheduler migration runner
if (class_exists('ActionScheduler_DBStore')) {
    $store = new ActionScheduler_DBStore();
    $store->init();
    echo "ActionScheduler_DBStore::init() called.\n";
} else {
    echo "ERROR: ActionScheduler_DBStore class not loaded.\n";
}

// Also try the migration runner
if (class_exists('ActionScheduler')) {
    $migrator = ActionScheduler::migration_runner();
    if ($migrator) {
        echo "Migration runner found.\n";
    }
}

// Verify tables
global $wpdb;
$tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}actionscheduler%'");
echo "ActionScheduler tables: " . implode(', ', $tables) . "\n";
echo count($tables) . " tables found.\n";
