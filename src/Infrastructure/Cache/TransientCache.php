<?php

declare(strict_types=1);

namespace ERTAppointment\Infrastructure\Cache;

/**
 * Thin wrapper around WordPress transients with in-process request cache.
 *
 * The in-process layer avoids redundant DB/cache-backend hits within the
 * same PHP request (e.g. multiple calls to getAvailableSlots() for the same
 * provider on a page that lists many providers).
 */
final class TransientCache {

	/** @var array<string, mixed> In-process key→value store. */
	private array $memory = array();

	/**
	 * Returns the cached value, or false if not found.
	 */
	public function get( string $key ): mixed {
		if ( array_key_exists( $key, $this->memory ) ) {
			return $this->memory[ $key ];
		}

		$value = get_transient( $this->prefix( $key ) );

		if ( $value !== false ) {
			$this->memory[ $key ] = $value;
		}

		return $value;
	}

	/**
	 * Stores a value. $ttl is in seconds (default 5 minutes).
	 */
	public function set( string $key, mixed $value, int $ttl = 300 ): bool {
		$this->memory[ $key ] = $value;

		return set_transient( $this->prefix( $key ), $value, $ttl );
	}

	/**
	 * Deletes an entry from both layers.
	 */
	public function delete( string $key ): bool {
		unset( $this->memory[ $key ] );

		return delete_transient( $this->prefix( $key ) );
	}

	/**
	 * Returns cached value if it exists; otherwise calls $callback,
	 * stores its return value, and returns it.
	 *
	 * @param callable(): mixed $callback
	 */
	public function remember( string $key, int $ttl, callable $callback ): mixed {
		$cached = $this->get( $key );

		if ( $cached !== false ) {
			return $cached;
		}

		$value = $callback();
		$this->set( $key, $value, $ttl );

		return $value;
	}

	/**
	 * Clears all in-process memory (useful in tests between scenarios).
	 */
	public function flush(): void {
		$this->memory = array();
	}

	/**
	 * Deletes all plugin transients matching a prefix pattern.
	 * Uses a DB query because WordPress has no native "delete by prefix" API.
	 */
	public function deleteByPrefix( string $prefix ): void {
		global $wpdb;

		unset( $this->memory[ $prefix ] ); // best-effort in-memory clear

		$like = $wpdb->esc_like( '_transient_erta_' . $prefix ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- WordPress core has no delete-transient-by-prefix API.
		$keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			)
		);

		foreach ( $keys as $optionName ) {
			// Strip the _transient_ prefix to get the transient key.
			$transientKey = substr( $optionName, strlen( '_transient_' ) );
			delete_transient( $transientKey );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Adds the plugin namespace prefix to avoid collisions with other plugins.
	 */
	private function prefix( string $key ): string {
		return 'erta_' . $key;
	}
}
