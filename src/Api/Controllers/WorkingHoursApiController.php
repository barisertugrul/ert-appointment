<?php

declare(strict_types=1);

namespace ERTAppointment\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;

/**
 * REST endpoints for working hours, breaks, and special days.
 *
 * All endpoints require 'erta_manage_all' capability (admin only).
 * Scope-aware: each record can be global / department / provider level.
 *
 * Routes:
 *  GET  /erta/v1/admin/working-hours?scope=&scope_id=
 *  POST /erta/v1/admin/working-hours    { scope, scope_id, hours: [...] }
 *  GET  /erta/v1/admin/breaks?scope=&scope_id=
 *  POST /erta/v1/admin/breaks           { scope, scope_id, breaks: [...] }
 *  GET  /erta/v1/admin/special-days?scope=&scope_id=
 *  POST /erta/v1/admin/special-days     { scope, scope_id, days: [...] }
 */
final class WorkingHoursApiController
{
    public function registerRoutes(): void
    {
        $adminCap = fn() => current_user_can('manage_options');

        // Working hours
        register_rest_route('erta/v1', '/admin/working-hours', [
            ['methods' => 'GET',  'callback' => [$this, 'getHours'],  'permission_callback' => $adminCap],
            ['methods' => 'POST', 'callback' => [$this, 'saveHours'], 'permission_callback' => $adminCap],
        ]);

        // Breaks
        register_rest_route('erta/v1', '/admin/breaks', [
            ['methods' => 'GET',  'callback' => [$this, 'getBreaks'],  'permission_callback' => $adminCap],
            ['methods' => 'POST', 'callback' => [$this, 'saveBreaks'], 'permission_callback' => $adminCap],
        ]);

        // Special days
        register_rest_route('erta/v1', '/admin/special-days', [
            ['methods' => 'GET',  'callback' => [$this, 'getSpecialDays'],  'permission_callback' => $adminCap],
            ['methods' => 'POST', 'callback' => [$this, 'saveSpecialDays'], 'permission_callback' => $adminCap],
        ]);
    }

    // ── Working Hours ─────────────────────────────────────────────────────

    public function getHours(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        [$scope, $scopeId] = $this->parseScope($request);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}erta_working_hours
             WHERE scope = %s AND scope_id = %d
             ORDER BY day_of_week ASC",
            $scope, $scopeId
        ), ARRAY_A);

        // If no records exist yet, return empty array
        // (frontend will show defaults; user saves to create records).
        return new WP_REST_Response(array_values($rows ?: []));
    }

    public function saveHours(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        [$scope, $scopeId] = $this->parseScope($request);
        $hours = $request->get_param('hours');

        if (! is_array($hours)) {
            return new WP_REST_Response(['error' => 'Invalid payload.'], 400);
        }

        $table = $wpdb->prefix . 'erta_working_hours';

        // Delete existing records for this scope, then re-insert.
        $wpdb->delete($table, ['scope' => $scope, 'scope_id' => $scopeId]);

        foreach ($hours as $row) {
            $wpdb->insert($table, [
                'scope'        => $scope,
                'scope_id'     => $scopeId,
                'day_of_week'  => (int) ($row['day_of_week'] ?? 1),
                'is_open'      => (int) (bool) ($row['is_open'] ?? false),
                'open_time'    => sanitize_text_field($row['open_time']  ?? '09:00'),
                'close_time'   => sanitize_text_field($row['close_time'] ?? '17:00'),
            ]);
        }

        // Bust availability cache for this scope.
        $this->bustAvailabilityCache($scope, $scopeId);

        return new WP_REST_Response(['success' => true]);
    }

    // ── Breaks ────────────────────────────────────────────────────────────

    public function getBreaks(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        [$scope, $scopeId] = $this->parseScope($request);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}erta_breaks
             WHERE scope = %s AND scope_id = %d
             ORDER BY id ASC",
            $scope, $scopeId
        ), ARRAY_A);

        return new WP_REST_Response(array_values($rows ?: []));
    }

    public function saveBreaks(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        [$scope, $scopeId] = $this->parseScope($request);
        $breaks = $request->get_param('breaks');

        if (! is_array($breaks)) {
            return new WP_REST_Response(['error' => 'Invalid payload.'], 400);
        }

        $table = $wpdb->prefix . 'erta_breaks';
        $wpdb->delete($table, ['scope' => $scope, 'scope_id' => $scopeId]);

        foreach ($breaks as $brk) {
            $wpdb->insert($table, [
                'scope'       => $scope,
                'scope_id'    => $scopeId,
                'day_of_week' => isset($brk['day_of_week']) && $brk['day_of_week'] !== null
                    ? (int) $brk['day_of_week'] : null,
                'start_time'  => sanitize_text_field($brk['start_time'] ?? '12:00'),
                'end_time'    => sanitize_text_field($brk['end_time']   ?? '13:00'),
                'name'        => sanitize_text_field($brk['name']       ?? ''),
                'break_type'  => 'regular',
            ]);
        }

        $this->bustAvailabilityCache($scope, $scopeId);

        return new WP_REST_Response(['success' => true]);
    }

    // ── Special Days ──────────────────────────────────────────────────────

    public function getSpecialDays(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        [$scope, $scopeId] = $this->parseScope($request);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}erta_special_days
             WHERE scope = %s AND scope_id = %d
             ORDER BY date ASC",
            $scope, $scopeId
        ), ARRAY_A);

        return new WP_REST_Response(array_values($rows ?: []));
    }

    public function saveSpecialDays(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        [$scope, $scopeId] = $this->parseScope($request);
        $days = $request->get_param('days');

        if (! is_array($days)) {
            return new WP_REST_Response(['error' => 'Invalid payload.'], 400);
        }

        $table = $wpdb->prefix . 'erta_special_days';
        $wpdb->delete($table, ['scope' => $scope, 'scope_id' => $scopeId]);

        foreach ($days as $sd) {
            $date = sanitize_text_field($sd['date'] ?? '');
            if (! $date || ! strtotime($date)) continue;   // skip invalid dates

            $wpdb->insert($table, [
                'scope'             => $scope,
                'scope_id'          => $scopeId,
                'date'              => $date,
                'is_closed'         => (int) (bool) ($sd['is_closed'] ?? true),
                'custom_open_time'  => sanitize_text_field($sd['custom_open_time']  ?? '09:00'),
                'custom_close_time' => sanitize_text_field($sd['custom_close_time'] ?? '17:00'),
                'name'              => sanitize_text_field($sd['name'] ?? ''),
            ]);
        }

        $this->bustAvailabilityCache($scope, $scopeId);

        return new WP_REST_Response(['success' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Returns [scope, scope_id] from request params.
     * scope_id defaults to 0 for global scope.
     */
    private function parseScope(WP_REST_Request $request): array
    {
        $scope   = sanitize_text_field($request->get_param('scope') ?? 'global');
        $scopeId = (int) ($request->get_param('scope_id') ?? 0);

        $allowed = ['global', 'department', 'provider'];
        if (! in_array($scope, $allowed, true)) $scope = 'global';

        return [$scope, $scopeId];
    }

    /**
     * Deletes all availability transients related to the given scope.
     * Forces the slot generator to re-calculate on next request.
     */
    private function bustAvailabilityCache(string $scope, int $scopeId): void
    {
        global $wpdb;
        $prefix = $scope === 'provider'
            ? "erta_slots_{$scopeId}_"
            : 'erta_slots_';

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_' . $prefix . '%'
        ));
    }
}
