<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Appointment;

use DateTimeImmutable;

/**
 * Data Transfer Object for rescheduling an existing appointment.
 */
final class RescheduleDTO {

	public function __construct(
		public readonly DateTimeImmutable $newStartDatetime,
		public readonly ?string $notes = null,
	) {}
}
