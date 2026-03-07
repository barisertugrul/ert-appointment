<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Schedule;

use DateTimeImmutable;

/**
 * Immutable value object representing a single bookable time slot.
 */
final class TimeSlot {

	public function __construct(
		/** "HH:MM" formatted time string. */
		public readonly string $time,
		/** Full datetime of the slot start (in site timezone). */
		public readonly DateTimeImmutable $datetime,
		/** Duration of the appointment in minutes. */
		public readonly int $durationMinutes,
		/** Whether this slot is still available for booking. */
		public readonly bool $available,
	) {}

	/**
	 * Returns a plain array suitable for JSON serialization (REST API response).
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'time'             => $this->time,
			'datetime'         => $this->datetime->format( 'Y-m-d\TH:i:s' ),
			'duration_minutes' => $this->durationMinutes,
			'available'        => $this->available,
		);
	}
}
