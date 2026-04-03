<?php
$url = home_url('/');
if (strpos($url, 'localhost:8180') !== false) {
    $url = str_replace('http://localhost:8180', 'http://127.0.0.1', $url);
}
$response = wp_remote_get($url, ['timeout' => 30]);
if (is_wp_error($response)) {
    echo 'ERROR=' . $response->get_error_message() . "\n";
    return;
}
$code = wp_remote_retrieve_response_code($response);
$body = wp_remote_retrieve_body($response);
if ($code < 200 || $code >= 300 || !is_string($body) || $body === '') {
    echo 'HTTP=' . $code . "\n";
    return;
}
$target = ABSPATH . 'wp-content/themes/ecomcine-base/output-files/showcase.html';
wp_mkdir_p( dirname( $target ) );
file_put_contents($target, $body);
echo 'WROTE=' . $target . "\n";
echo 'HAS_TM_STORE_UI_101=' . (strpos($body, 'tm-store-ui/assets/js/vendor-store.js?ver=1.0.1') !== false ? 'yes' : 'no') . "\n";
echo 'HAS_COMPANY_FA=' . (strpos($body, 'fa-building tm-menu-icon') !== false ? 'yes' : 'no') . "\n";
echo 'HAS_SOCIAL_FA=' . (strpos($body, 'fab fa-youtube') !== false ? 'yes' : 'no') . "\n";
