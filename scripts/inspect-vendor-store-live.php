<?php
$path = WP_PLUGIN_DIR . '/tm-store-ui/assets/js/vendor-store.js';
if ( ! file_exists( $path ) ) {
    echo "MISSING\n";
    return;
}
$lines = file( $path );
$line = isset( $lines[240] ) ? rtrim( $lines[240], "\r\n" ) : 'LINE_241_MISSING';
echo "PATH=" . $path . "\n";
echo "LINE241=" . $line . "\n";
