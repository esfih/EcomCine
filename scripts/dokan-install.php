<?php
// Force Dokan (lite + pro) to run their DB install routines
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
global $wpdb;

// Try Dokan Lite installer
if (function_exists('dokan')) {
    $dokan = dokan();
    echo "Dokan instance obtained.\n";
}

// Force Dokan Lite install
if (class_exists('WeDevs\Dokan\Install\Installer')) {
    $installer = new WeDevs\Dokan\Install\Installer();
    $installer->do_install();
    echo "Dokan Lite Installer::do_install() called.\n";
} else {
    echo "WARNING: Dokan Lite Installer class not found.\n";
    $classes = get_declared_classes();
    foreach ($classes as $c) {
        if (stripos($c, 'dokan') !== false && stripos($c, 'install') !== false) {
            echo "  $c\n";
        }
    }
}

// Try Dokan Pro installer
if (class_exists('WeDevs\DokanPro\Install\Installer')) {
    $installer = new WeDevs\DokanPro\Install\Installer();
    $installer->do_install();
    echo "DokanPro Installer::do_install() called.\n";
} else {
    echo "INFO: DokanPro Installer class not found.\n";
    $classes = get_declared_classes();
    foreach ($classes as $c) {
        if (stripos($c, 'dokan') !== false && strpos($c, 'Pro') !== false && stripos($c, 'install') !== false) {
            echo "  $c\n";
        }
    }
}

// Check resulting Dokan tables
$tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}dokan%'");
echo "\nDokan tables after install:\n";
foreach ($tables as $t) {
    echo "  - $t\n";
}
echo count($tables) . " dokan tables total.\n";
