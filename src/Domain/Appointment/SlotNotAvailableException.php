<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Appointment;

use RuntimeException;

/**
 * Thrown when a booking is attempted on a slot that is no longer available.
 * This is a domain exception — it should be caught and converted to a user-facing
 * error message at the application boundary (REST controller, form handler, etc.).
 */
final class SlotNotAvailableException extends RuntimeException {}
