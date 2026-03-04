<?php

declare(strict_types=1);

// Minimal WordPress stubs so domain logic tests run without a full WP install.

define('ABSPATH', '/tmp/');
define('ERTA_TEST_MODE', true);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS',  86400);

// ── WP function stubs ─────────────────────────────────────────────────────

if (! function_exists('__')) {
    function __(string $text, string $domain = ''): string { return $text; }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return $value; }
}

if (! function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void {}
}

if (! function_exists('get_option')) {
    function get_option(string $key, mixed $default = false): mixed { return $default; }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data): string|false { return json_encode($data); }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string { return trim(strip_tags($str)); }
}

if (! function_exists('sanitize_email')) {
    function sanitize_email(string $email): string { return filter_var($email, FILTER_SANITIZE_EMAIL); }
}

if (! function_exists('is_email')) {
    function is_email(string $email): bool { return (bool) filter_var($email, FILTER_VALIDATE_EMAIL); }
}

if (! function_exists('current_time')) {
    function current_time(string $type): string {
        return $type === 'timestamp' ? (string) time() : date('Y-m-d H:i:s');
    }
}

if (! function_exists('wp_timezone_string')) {
    function wp_timezone_string(): string { return 'Europe/Istanbul'; }
}

// Autoload
require_once dirname(__DIR__) . '/vendor/autoload.php';
