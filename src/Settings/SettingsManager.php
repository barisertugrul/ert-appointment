<?php

declare(strict_types=1);

namespace ERTAppointment\Settings;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- settings manager performs intentional reads/writes on plugin-owned custom tables.

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
final class SettingsManager {

	/** In-process cache keyed by "scope:scope_id". */
	private array $rawCache = array();

	/** Resolved config objects keyed by provider ID. */
	private array $resolvedCache = array();

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
	public function getGlobal( string $key, mixed $default = null ): mixed {
		$settings = $this->loadScope( 'global', null );

		return $settings[ $key ] ?? $default;
	}

	/**
	 * Reads a single department-level setting (falls back to global).
	 *
	 * @param mixed $default
	 */
	public function getDepartment( int $departmentId, string $key, mixed $default = null ): mixed {
		$settings = array_merge(
			$this->loadScope( 'global', null ),
			$this->loadScope( 'department', $departmentId )
		);

		return $settings[ $key ] ?? $default;
	}

	/**
	 * Returns a fully resolved ResolvedConfig for a provider,
	 * merging global → department → provider in priority order.
	 */
	public function resolveForProvider( int $providerId ): ResolvedConfig {
		if ( isset( $this->resolvedCache[ $providerId ] ) ) {
			return $this->resolvedCache[ $providerId ];
		}

		$global   = $this->loadScope( 'global', null );
		$provider = $this->loadProviderMeta( $providerId );

		$departmentSettings = array();
		if ( $provider['department_id'] !== null ) {
			$departmentSettings = $this->loadScope( 'department', (int) $provider['department_id'] );
		}

		$providerSettings = $this->loadScope( 'provider', $providerId );

		// Merge — right side wins.
		$merged = array_merge( $global, $departmentSettings, $providerSettings );

		$config = new ResolvedConfig( $merged, $provider['department_id'] );

		$this->resolvedCache[ $providerId ] = $config;

		return $config;
	}

	/**
	 * Writes a setting to the database (upsert).
	 *
	 * @param mixed $value  Scalar or array; will be JSON-encoded if array.
	 */
	public function set( string $scope, ?int $scopeId, string $key, mixed $value ): void {
		global $wpdb;

		$scopeId = $this->normalizeScopeId( $scope, $scopeId );
		if ( $scope !== 'global' && $scopeId <= 0 ) {
			return;
		}

		$encoded = is_array( $value ) || is_object( $value )
			? wp_json_encode( $value )
			: ( is_bool( $value ) ? ( $value ? 'true' : 'false' ) : (string) $value );

		$table    = $wpdb->prefix . 'erta_settings';
		$tableSql = $table;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i
				 WHERE scope = %s
				   AND (scope_id = %d OR (scope_id IS NULL AND %d = 0))
				   AND setting_key = %s
				 ORDER BY id DESC',
				$tableSql,
				$scope,
				$scopeId,
				$scopeId,
				$key
			)
		);

		if ( ! empty( $ids ) ) {
			$primaryId = (int) $ids[0];

			$wpdb->update(
				$table,
				array(
					'scope_id'      => $scopeId,
					'setting_value' => $encoded,
				),
				array( 'id' => $primaryId )
			);

			if ( count( $ids ) > 1 ) {
				$extraIds = array_map( 'intval', array_slice( $ids, 1 ) );

				if ( ! empty( $extraIds ) ) {
					foreach ( $extraIds as $extraId ) {
						$wpdb->query(
							$wpdb->prepare(
								'DELETE FROM %i WHERE id = %d',
								$tableSql,
								$extraId
							)
						);
					}
				}
			}
		} else {
			$wpdb->insert(
				$table,
				array(
					'scope'         => $scope,
					'scope_id'      => $scopeId,
					'setting_key'   => $key,
					'setting_value' => $encoded,
				)
			);
		}

		// Invalidate caches for this scope.
		$this->invalidate( $scope, $scopeId );
	}

	/**
	 * Bulk-saves an associative array of settings for a given scope.
	 *
	 * @param array<string, mixed> $settings
	 */
	public function bulkSet( string $scope, ?int $scopeId, array $settings ): void {
		foreach ( $settings as $key => $value ) {
			$this->set( $scope, $scopeId, $key, $value );
		}
	}

	/**
	 * Retrieves all settings for a scope as an associative array.
	 *
	 * @return array<string, mixed>
	 */
	public function getAll( string $scope, ?int $scopeId ): array {
		return $this->loadScope( $scope, $scopeId );
	}

	/**
	 * Deletes a single setting.
	 */
	public function delete( string $scope, ?int $scopeId, string $key ): void {
		global $wpdb;

		$scopeId = $this->normalizeScopeId( $scope, $scopeId );
		if ( $scope !== 'global' && $scopeId <= 0 ) {
			return;
		}
		$table   = $wpdb->prefix . 'erta_settings';
		$tableSql = $table;

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i
				 WHERE scope = %s
				   AND (scope_id = %d OR (scope_id IS NULL AND %d = 0))
				   AND setting_key = %s',
				$tableSql,
				$scope,
				$scopeId,
				$scopeId,
				$key
			)
		);

		$this->invalidate( $scope, $scopeId );
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Loads all settings for a scope from the database (or in-process cache).
	 *
	 * @return array<string, mixed>
	 */
	private function loadScope( string $scope, ?int $scopeId ): array {
		$scopeId  = $this->normalizeScopeId( $scope, $scopeId );
		if ( $scope !== 'global' && $scopeId <= 0 ) {
			return array();
		}
		$cacheKey = "{$scope}:{$scopeId}";

		if ( isset( $this->rawCache[ $cacheKey ] ) ) {
			return $this->rawCache[ $cacheKey ];
		}

		// Try transient cache first.
		$transientKey = "erta_settings_{$scope}_" . ( $scopeId === 0 ? 'global' : (string) $scopeId );
		$cached       = $this->cache->get( $transientKey );

		if ( $cached !== false ) {
			$this->rawCache[ $cacheKey ] = $cached;
			return $cached;
		}

		// Query database.
		global $wpdb;
		$table = $wpdb->prefix . 'erta_settings';
		$tableSql = $table;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT setting_key, setting_value FROM %i
				 WHERE scope = %s
				   AND (scope_id = %d OR (scope_id IS NULL AND %d = 0))
				 ORDER BY id ASC',
				$tableSql,
				$scope,
				$scopeId,
				$scopeId
			),
			\ARRAY_A
		);

		$result = array();
		foreach ( $rows as $row ) {
			$result[ $row['setting_key'] ] = $this->decode( $row['setting_value'] );
		}

		$this->cache->set( $transientKey, $result, 300 );
		$this->rawCache[ $cacheKey ] = $result;

		return $result;
	}

	/**
	 * Loads minimal provider metadata (department_id) needed for scope resolution.
	 *
	 * @return array{department_id: int|null}
	 */
	private function loadProviderMeta( int $providerId ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'erta_providers';
		$tableSql = $table;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT department_id FROM %i WHERE id = %d',
				$tableSql,
				$providerId
			),
			\ARRAY_A
		);

		return array( 'department_id' => $row ? ( $row['department_id'] ? (int) $row['department_id'] : null ) : null );
	}

	/**
	 * Decodes a stored setting value. JSON arrays/objects are decoded; scalars returned as-is.
	 */
	private function decode( string|null $value ): mixed {
		if ( $value === null ) {
			return null;
		}

		$trimmed = trim( $value );

		if ( in_array( $trimmed[0] ?? '', array( '{', '[' ), true ) ) {
			$decoded = json_decode( $trimmed, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $decoded;
			}
		}

		// Boolean-like strings.
		if ( $trimmed === 'true' ) {
			return true;
		}
		if ( $trimmed === 'false' ) {
			return false;
		}
		if ( is_numeric( $trimmed ) ) {
			return $trimmed + 0;
		}

		return $trimmed;
	}

	/**
	 * Invalidates all caches for a given scope.
	 */
	private function invalidate( string $scope, ?int $scopeId ): void {
		$scopeId      = $this->normalizeScopeId( $scope, $scopeId );
		$cacheKey     = "{$scope}:{$scopeId}";
		$transientKey = "erta_settings_{$scope}_" . ( $scopeId === 0 ? 'global' : (string) $scopeId );

		unset( $this->rawCache[ $cacheKey ] );
		$this->cache->delete( $transientKey );

		// Also clear any resolved provider configs (they depend on all scopes).
		$this->resolvedCache = array();
	}

	/**
	 * Normalizes nullable scope IDs for reliable comparisons and unique behavior.
	 */
	private function normalizeScopeId( string $scope, ?int $scopeId ): int {
		if ( $scope === 'global' ) {
			return 0;
		}

		return max( 0, (int) ( $scopeId ?? 0 ) );
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
