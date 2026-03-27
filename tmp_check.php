<?php
require '/var/www/html/wp-load.php';
echo class_exists('WC_Booking_Form') ? 'EXISTS' : 'MISSING';
