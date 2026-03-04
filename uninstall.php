<?php
/**
 * Uninstall script — runs when the plugin is deleted from WP admin.
 * This file is executed directly by WordPress, not through the plugin's autoloader.
 */

declare(strict_types=1);

// WordPress uninstall safety check.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Only remove data if the admin opted in via plugin settings.
$remove_data = get_option('erta_remove_data_on_uninstall', false);

if (! $remove_data) {
    return;
}

// Drop all plugin tables in reverse dependency order.
$tables = [
    'erta_notification_logs',
    'erta_notification_templates',
    'erta_appointments',
    'erta_forms',
    'erta_special_days',
    'erta_breaks',
    'erta_working_hours',
    'erta_settings',
    'erta_provider_users',
    'erta_providers',
    'erta_departments',
];

foreach ($tables as $table) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
}

// Remove all options stored by the plugin.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        'erta_%',
        '_erta_%'   // transients
    )
);

// Remove user meta added by the plugin.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        'erta_%'
    )
);

// Flush rewrite rules.
flush_rewrite_rules();
