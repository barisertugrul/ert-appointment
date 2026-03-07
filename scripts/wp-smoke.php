<?php

declare(strict_types=1);

if (!defined('ABSPATH') && PHP_SAPI !== 'cli') {
    exit;
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/wp-smoke.php <wp-root> [plugin-slug]\n");
    exit(1);
}

$wpRoot = rtrim($argv[1], "\\/");
$pluginFileArg = $argv[2] ?? 'ert-appointment/ert-appointment.php';

$wpLoad = $wpRoot . DIRECTORY_SEPARATOR . 'wp-load.php';
if (!file_exists($wpLoad)) {
    fwrite(STDERR, "wp-load.php not found at: {$wpLoad}\n");
    exit(2);
}

require $wpLoad;
require_once ABSPATH . 'wp-admin/includes/plugin.php';

echo 'wp_version=' . get_bloginfo('version') . "\n";
$pluginsDir = WP_CONTENT_DIR . '/plugins';
$pluginCheckInstalled = is_dir($pluginsDir . '/plugin-check') || is_dir($pluginsDir . '/plugin-checker');
echo 'plugin_check_installed=' . ($pluginCheckInstalled ? 'yes' : 'no') . "\n";

$pluginFile = $pluginFileArg;

$before = is_plugin_active($pluginFile) ? 'active' : 'inactive';
echo "before={$before}\n";

$result = activate_plugin($pluginFile);
if (is_wp_error($result)) {
    echo 'activate=error:' . $result->get_error_message() . "\n";
    exit(3);
}

echo "activate=ok\n";
$after = is_plugin_active($pluginFile) ? 'active' : 'inactive';
echo "after={$after}\n";

$rest = new WP_REST_Request('GET', '/erta/v1/departments');
$response = rest_do_request($rest);
$server = rest_get_server();
$data = $server->response_to_data($response, false);

echo 'rest_status=' . $response->get_status() . "\n";
echo 'rest_type=' . (is_array($data) ? 'array' : gettype($data)) . "\n";
if (is_array($data)) {
    echo 'rest_count=' . count($data) . "\n";
}
