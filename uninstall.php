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
$erta_remove_data = get_option('erta_remove_data_on_uninstall', false);

if (! $erta_remove_data) {
    return;
}

// Drop all plugin tables in reverse dependency order.
$erta_tables = [
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

foreach ($erta_tables as $erta_table) {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall cleanup intentionally drops plugin tables.
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$erta_table}");
}

// Remove all options stored by the plugin.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup query.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        'erta_%',
        '_erta_%'   // transients
    )
);

// Remove user meta added by the plugin.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup query.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
        'erta_%'
    )
);

// Flush rewrite rules.
flush_rewrite_rules();
