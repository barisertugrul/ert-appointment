<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Appointment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backed enum for appointment lifecycle statuses.
 * Values are stored as-is in the `status` database column.
 */
enum AppointmentStatus: string {

	case Pending     = 'pending';
	case Confirmed   = 'confirmed';
	case Cancelled   = 'cancelled';
	case Completed   = 'completed';
	case NoShow      = 'no_show';
	case Rescheduled = 'rescheduled';
	case Waitlisted  = 'waitlisted';   // Pro feature — stored but UI gated

	/**
	 * Returns a human-readable label.
	 */
	public function label(): string {
		return match ( $this ) {
			self::Pending     => __( 'Pending', 'ert-appointment' ),
			self::Confirmed   => __( 'Confirmed', 'ert-appointment' ),
			self::Cancelled   => __( 'Cancelled', 'ert-appointment' ),
			self::Completed   => __( 'Completed', 'ert-appointment' ),
			self::NoShow      => __( 'No Show', 'ert-appointment' ),
			self::Rescheduled => __( 'Rescheduled', 'ert-appointment' ),
			self::Waitlisted  => __( 'Waitlisted', 'ert-appointment' ),
		};
	}

	/**
	 * Returns a CSS class fragment for badge styling.
	 */
	public function cssClass(): string {
		return 'erta-badge--' . str_replace( '_', '-', $this->value );
	}

	/**
	 * Returns statuses that are considered "active" (not terminal).
	 *
	 * @return list<self>
	 */
	public static function activeStatuses(): array {
		return array( self::Pending, self::Confirmed );
	}

	/**
	 * Returns terminal statuses where no further transitions are allowed.
	 *
	 * @return list<self>
	 */
	public static function terminalStatuses(): array {
		return array( self::Cancelled, self::Completed, self::NoShow, self::Rescheduled );
	}
}
