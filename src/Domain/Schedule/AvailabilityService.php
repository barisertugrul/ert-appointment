<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Schedule;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- availability calculation intentionally reads plugin-owned custom tables.

use DateTimeImmutable;
use ERTAppointment\Domain\Appointment\AppointmentRepository;
use ERTAppointment\Infrastructure\Cache\TransientCache;
use ERTAppointment\Settings\ResolvedConfig;
use ERTAppointment\Settings\SettingsManager;

/**
 * Computes available booking slots for a provider on a given date.
 *
 * Responsibilities:
 *  1. Load the resolved config (working hours, breaks, durations) for the provider.
 *  2. Check for special days (holidays, custom hours).
 *  3. Fetch already-booked blocks from the repository.
 *  4. Delegate pure slot calculation to SlotGenerator.
 *  5. Cache results to avoid re-computation on the same request.
 */
final class AvailabilityService {

	public function __construct(
		private readonly SlotGenerator $slotGenerator,
		private readonly SettingsManager $settings,
		private readonly AppointmentRepository $appointmentRepository,
		private readonly TransientCache $cache,
	) {}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Returns available time slots for a provider on a given date.
	 *
	 * @return list<TimeSlot>
	 */
	public function getAvailableSlots( int $providerId, DateTimeImmutable $date ): array {
		$dateStr  = $date->format( 'Y-m-d' );
		$cacheKey = "slots_{$providerId}_{$dateStr}";

		return $this->cache->remember(
			$cacheKey,
			120,
			function () use ( $providerId, $date, $dateStr ) {
				$config = $this->resolveAvailabilityConfig( $providerId );
				$slotCapacity = $this->resolveSlotCapacity( $providerId, $dateStr, $config );
				$slotDuration = $config->slotDuration();
				$bufferAfter  = $config->bufferAfter();
				$effectiveSlotInterval = max(
					$config->slotInterval(),
					$slotDuration + max( 0, $bufferAfter )
				);

				if ( ! $this->isWithinBookingWindow( $date, $config ) ) {
					return array();
				}

				// Check special-day override first.
				$specialDay = $this->loadSpecialDay( $providerId, $date, $config );

				if ( $specialDay !== null && $specialDay['is_closed'] ) {
					return array(); // Holiday / closure.
				}

				// Resolve working hours.
				$dayOfWeek = (int) $date->format( 'N' ); // 1=Monday, 7=Sunday (ISO-8601).

				$hours = $specialDay !== null
				? array(
					'start' => $specialDay['custom_open_time'],
					'end'   => $specialDay['custom_close_time'],
				)
				: $config->workingHoursForDay( $dayOfWeek );

				if ( $hours === null || ! ( $hours['open'] ?? true ) ) {
					return array(); // Non-working day.
				}

				// Load scoped breaks from storage.
				$breaks = $this->loadBreaks( $providerId, $date, $config, $dayOfWeek );

				// --- Load booked blocks. ---
				$bookedBlocks = $this->appointmentRepository->findBookedBlocks( $providerId, $date );

				$bookedBlocksForGenerator = $slotCapacity > 1 ? array() : $bookedBlocks;

				// --- Generate slots. ---
				$slots = $this->slotGenerator->generate(
					date:           $date,
					openTime:       $hours['start'],
					closeTime:      $hours['end'],
					slotDuration:   $slotDuration,
					slotInterval:   $effectiveSlotInterval,
					bufferBefore:   0,
					bufferAfter:    $bufferAfter,
					minimumNotice:  $config->minimumNotice(),
					breaks:         $breaks,
					bookedBlocks:   $bookedBlocksForGenerator,
				);

				if ( $slotCapacity > 1 ) {
					$bookedCountByTime = $this->countBookedStartsByTime( $bookedBlocks );
					$slots = array_values(
						array_filter(
							$slots,
							static fn( TimeSlot $slot ): bool => ( $bookedCountByTime[ $slot->time ] ?? 0 ) < $slotCapacity
						)
					);
				}

				/**
				 * Filter: allows Pro / third-party code to modify the slot list.
				 * Useful for capacity-based filtering, waitlist injection, etc.
				 *
				 * @param list<TimeSlot> $slots
				 * @param int            $providerId
				 * @param string         $dateStr
				 */
				return apply_filters( 'erta_available_slots', $slots, $providerId, $dateStr );
			}
		);
	}

	private function resolveAvailabilityConfig( int $providerId ) {
		$globalMode = \sanitize_key( (string) $this->settings->getGlobal( 'booking_mode', '' ) );

		if ( $globalMode === 'general' ) {
			$global = $this->settings->getAll( 'global', null );
			return new ResolvedConfig( $global, null );
		}

		return $this->settings->resolveForProvider( $providerId );
	}

	private function resolveSlotCapacity( int $providerId, string $dateStr, $config ): int {
		$capacity = max( 1, (int) $config->slotCapacity() );

		$enabled = (bool) apply_filters( 'erta_enable_slot_capacity', false, $providerId, $dateStr, $capacity );

		return $enabled ? $capacity : 1;
	}

	/**
	 * @param list<array{start: DateTimeImmutable, end: DateTimeImmutable}> $bookedBlocks
	 * @return array<string, int>
	 */
	private function countBookedStartsByTime( array $bookedBlocks ): array {
		$counts = array();

		foreach ( $bookedBlocks as $block ) {
			if ( ! isset( $block['start'] ) || ! ( $block['start'] instanceof DateTimeImmutable ) ) {
				continue;
			}

			$time = $block['start']->format( 'H:i' );
			$counts[ $time ] = ( $counts[ $time ] ?? 0 ) + 1;
		}

		return $counts;
	}

	private function isWithinBookingWindow( DateTimeImmutable $date, $config ): bool {
		$target = $date->format( 'Y-m-d' );

		$start = $config->bookingStartDate();
		if ( $start !== '' && $target < $start ) {
			return false;
		}

		$end = $config->bookingEndDate();
		if ( $end !== '' && $target > $end ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns a map of available slot counts per date for a date range.
	 * Used to highlight available dates on the calendar widget.
	 *
	 * @return array<string, int>  ['2024-04-01' => 8, '2024-04-02' => 0, ...]
	 */
	public function getAvailabilityCalendar(
		int $providerId,
		DateTimeImmutable $from,
		DateTimeImmutable $to
	): array {
		$result  = array();
		$current = $from->setTime( 0, 0, 0 );
		$end     = $to->setTime( 0, 0, 0 );

		while ( $current <= $end ) {
			$slots                                 = $this->getAvailableSlots( $providerId, $current );
			$result[ $current->format( 'Y-m-d' ) ] = count( $slots );
			$current                               = $current->modify( '+1 day' );
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Checks for a special-day row for the provider (or its department / global).
	 *
	 * @return array<string, mixed>|null
	 */
	private function loadSpecialDay( int $providerId, DateTimeImmutable $date, $config ): ?array {
		global $wpdb;
		$table   = $wpdb->prefix . 'erta_special_days';
		$dateStr = $date->format( 'Y-m-d' );

		// Priority: provider > department > global.
		$scopes = array(
			array( 'provider', $providerId ),
			array( 'department', $config->departmentId() ),
			array( 'global', null ),
		);

		foreach ( $scopes as [$scope, $scopeId] ) {
			if ( $scopeId === null && $scope !== 'global' ) {
				continue;
			}

			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i
                     WHERE scope = %s AND scope_id <=> %d AND date = %s
                     LIMIT 1',
					$table,
					$scope,
					$scopeId,
					$dateStr
				),
				\ARRAY_A
			);

			if ( $row !== null ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Loads all applicable breaks for the provider on this day of week.
	 *
	 * @return list<array{start: string, end: string}>
	 */
	private function loadBreaks( int $providerId, DateTimeImmutable $date, $config, int $dayOfWeek ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'erta_breaks';

		$scopeParts = array(
			array( 'global', null ),
			array( 'department', $config->departmentId() ),
			array( 'provider', $providerId ),
		);

		$allBreaks = array();

		foreach ( $scopeParts as [$scope, $scopeId] ) {
			if ( $scopeId === null && $scope !== 'global' ) {
				continue;
			}

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i
                     WHERE scope = %s AND scope_id <=> %d
                       AND (day_of_week IS NULL OR day_of_week = %d)',
					$table,
					$scope,
					$scopeId,
					$dayOfWeek
				),
				\ARRAY_A
			);

			foreach ( $rows as $row ) {
				$allBreaks[] = array(
					'start' => $row['start_time'],
					'end'   => $row['end_time'],
				);
			}
		}

		return $allBreaks;
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
