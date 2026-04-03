<?php
$page = get_page_by_path('talents');
echo 'PAGE=' . ($page ? $page->ID : 0) . PHP_EOL;
echo 'SHORTCODE=' . (shortcode_exists('ecomcine-stores') ? 'yes' : 'no') . PHP_EOL;
$output = do_shortcode('[ecomcine-stores]');
echo 'LEN=' . strlen($output) . PHP_EOL;
echo substr($output, 0, 1000) . PHP_EOL;
