<?php

declare(strict_types=1);

namespace ERTAppointment\Settings;

use ERTAppointment\Infrastructure\Cache\TransientCache;

/**
 * Central settings manager.
 *
 * Settings exist at three scopes and are merged in priority order:
 *   global (lowest) → department → provider (highest)
 *
 * This allows global defaults to be overridden at the department level,
 * which can in turn be overridden per-provider.
 *
 * All reads are cached in-process (and in the WP transient layer) to avoid
 * repeated database queries on pages that render many provider slots.
 */
final class SettingsManager
{
    /** In-process cache keyed by "scope:scope_id". */
    private array $rawCache = [];

    /** Resolved config objects keyed by provider ID. */
    private array $resolvedCache = [];

    public function __construct(
        private readonly TransientCache $cache
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Reads a single global setting.
     *
     * @param mixed $default
     */
    public function getGlobal(string $key, mixed $default = null): mixed
    {
        $settings = $this->loadScope('global', null);

        return $settings[$key] ?? $default;
    }

    /**
     * Reads a single department-level setting (falls back to global).
     *
     * @param mixed $default
     */
    public function getDepartment(int $departmentId, string $key, mixed $default = null): mixed
    {
        $settings = array_merge(
            $this->loadScope('global', null),
            $this->loadScope('department', $departmentId)
        );

        return $settings[$key] ?? $default;
    }

    /**
     * Returns a fully resolved ResolvedConfig for a provider,
     * merging global → department → provider in priority order.
     */
    public function resolveForProvider(int $providerId): ResolvedConfig
    {
        if (isset($this->resolvedCache[$providerId])) {
            return $this->resolvedCache[$providerId];
        }

        $global   = $this->loadScope('global', null);
        $provider = $this->loadProviderMeta($providerId);

        $departmentSettings = [];
        if ($provider['department_id'] !== null) {
            $departmentSettings = $this->loadScope('department', (int) $provider['department_id']);
        }

        $providerSettings = $this->loadScope('provider', $providerId);

        // Merge — right side wins.
        $merged = array_merge($global, $departmentSettings, $providerSettings);

        $config = new ResolvedConfig($merged, $provider['department_id']);

        $this->resolvedCache[$providerId] = $config;

        return $config;
    }

    /**
     * Writes a setting to the database (upsert).
     *
     * @param mixed $value  Scalar or array; will be JSON-encoded if array.
     */
    public function set(string $scope, ?int $scopeId, string $key, mixed $value): void
    {
        global $wpdb;

        $encoded = is_array($value) || is_object($value)
            ? wp_json_encode($value)
            : (string) $value;

        $table = $wpdb->prefix . 'erta_settings';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE scope = %s AND scope_id <=> %d AND setting_key = %s",
            $scope,
            $scopeId,
            $key
        ));

        if ($existing) {
            $wpdb->update(
                $table,
                ['setting_value' => $encoded],
                ['id' => $existing]
            );
        } else {
            $wpdb->insert($table, [
                'scope'         => $scope,
                'scope_id'      => $scopeId,
                'setting_key'   => $key,
                'setting_value' => $encoded,
            ]);
        }

        // Invalidate caches for this scope.
        $this->invalidate($scope, $scopeId);
    }

    /**
     * Bulk-saves an associative array of settings for a given scope.
     *
     * @param array<string, mixed> $settings
     */
    public function bulkSet(string $scope, ?int $scopeId, array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($scope, $scopeId, $key, $value);
        }
    }

    /**
     * Retrieves all settings for a scope as an associative array.
     *
     * @return array<string, mixed>
     */
    public function getAll(string $scope, ?int $scopeId): array
    {
        return $this->loadScope($scope, $scopeId);
    }

    /**
     * Deletes a single setting.
     */
    public function delete(string $scope, ?int $scopeId, string $key): void
    {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'erta_settings',
            [
                'scope'       => $scope,
                'scope_id'    => $scopeId,
                'setting_key' => $key,
            ]
        );

        $this->invalidate($scope, $scopeId);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Loads all settings for a scope from the database (or in-process cache).
     *
     * @return array<string, mixed>
     */
    private function loadScope(string $scope, ?int $scopeId): array
    {
        $cacheKey = "{$scope}:" . ($scopeId ?? 'null');

        if (isset($this->rawCache[$cacheKey])) {
            return $this->rawCache[$cacheKey];
        }

        // Try transient cache first.
        $transientKey = "erta_settings_{$scope}_" . ($scopeId ?? 'global');
        $cached       = $this->cache->get($transientKey);

        if ($cached !== false) {
            $this->rawCache[$cacheKey] = $cached;
            return $cached;
        }

        // Query database.
        global $wpdb;
        $table = $wpdb->prefix . 'erta_settings';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT setting_key, setting_value FROM {$table}
                 WHERE scope = %s AND scope_id <=> %d",
                $scope,
                $scopeId
            ),
            ARRAY_A
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = $this->decode($row['setting_value']);
        }

        $this->cache->set($transientKey, $result, 300);
        $this->rawCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Loads minimal provider metadata (department_id) needed for scope resolution.
     *
     * @return array{department_id: int|null}
     */
    private function loadProviderMeta(int $providerId): array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT department_id FROM {$wpdb->prefix}erta_providers WHERE id = %d",
                $providerId
            ),
            ARRAY_A
        );

        return ['department_id' => $row ? ($row['department_id'] ? (int) $row['department_id'] : null) : null];
    }

    /**
     * Decodes a stored setting value. JSON arrays/objects are decoded; scalars returned as-is.
     */
    private function decode(string|null $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if (in_array($trimmed[0] ?? '', ['{', '['], true)) {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Boolean-like strings.
        if ($trimmed === 'true')  return true;
        if ($trimmed === 'false') return false;
        if (is_numeric($trimmed)) return $trimmed + 0;

        return $trimmed;
    }

    /**
     * Invalidates all caches for a given scope.
     */
    private function invalidate(string $scope, ?int $scopeId): void
    {
        $cacheKey     = "{$scope}:" . ($scopeId ?? 'null');
        $transientKey = "erta_settings_{$scope}_" . ($scopeId ?? 'global');

        unset($this->rawCache[$cacheKey]);
        $this->cache->delete($transientKey);

        // Also clear any resolved provider configs (they depend on all scopes).
        $this->resolvedCache = [];
    }
}
