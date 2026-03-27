<?php
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
$store = new ActionScheduler_DBStore();
$store->init();
echo "ActionScheduler tables installed.\n";
