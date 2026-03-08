<?php

declare(strict_types=1);

namespace ERTAppointment\Settings;

/**
 * Immutable value object representing the fully-merged settings for a provider.
 *
 * Consumers read settings through this object rather than querying the
 * SettingsManager directly, keeping domain code free of infrastructure concerns.
 */
final class ResolvedConfig {

	/**
	 * @param array<string, mixed> $settings   Merged global+department+provider map.
	 * @param int|null             $departmentId  Resolved department ID, if any.
	 */
	public function __construct(
		private readonly array $settings,
		private readonly ?int $departmentId = null
	) {}

	// -------------------------------------------------------------------------
	// Generic access
	// -------------------------------------------------------------------------

	/**
	 * Returns a setting value, or $default if not found.
	 *
	 * @param mixed $default
	 */
	public function get( string $key, mixed $default = null ): mixed {
		return $this->settings[ $key ] ?? $default;
	}

	/**
	 * Returns a setting cast to int.
	 */
	public function getInt( string $key, int $default = 0 ): int {
		return (int) ( $this->settings[ $key ] ?? $default );
	}

	/**
	 * Returns a setting cast to bool.
	 */
	public function getBool( string $key, bool $default = false ): bool {
		$value = $this->settings[ $key ] ?? $default;

		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( $value, array( 1, '1', 'true', 'yes' ), true );
	}

	/**
	 * Returns a setting cast to string.
	 */
	public function getString( string $key, string $default = '' ): string {
		return (string) ( $this->settings[ $key ] ?? $default );
	}

	// -------------------------------------------------------------------------
	// Schedule helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns slot duration in minutes.
	 */
	public function slotDuration(): int {
		$modern = $this->getInt( 'slot_duration', 0 );
		if ( $modern > 0 ) {
			return $modern;
		}

		return $this->getInt( 'slot_duration_minutes', 30 );
	}

	/**
	 * Returns the interval between slot starts in minutes.
	 */
	public function slotInterval(): int {
		return $this->getInt( 'slot_interval', 30 );
	}

	/**
	 * Returns the maximum number of appointments allowed per slot start time.
	 */
	public function slotCapacity(): int {
		return max( 1, $this->getInt( 'slot_capacity', 1 ) );
	}

	/**
	 * Returns the required pre-appointment buffer in minutes.
	 */
	public function bufferBefore(): int {
		$modern = $this->getInt( 'buffer_before', 0 );
		if ( $modern > 0 ) {
			return $modern;
		}

		return $this->getInt( 'buffer_before_minutes', 0 );
	}

	/**
	 * Returns the required post-appointment buffer in minutes.
	 */
	public function bufferAfter(): int {
		$modern = $this->getInt( 'buffer_after', 0 );
		if ( $modern > 0 ) {
			return $modern;
		}

		return $this->getInt( 'buffer_after_minutes', 0 );
	}

	/**
	 * Returns the minimum advance notice required to book, in minutes.
	 */
	public function minimumNotice(): int {
		$modern = $this->getInt( 'minimum_notice', 0 );
		if ( $modern > 0 ) {
			return $modern;
		}

		$hours = $this->getInt( 'min_notice_hours', 1 );
		return max( 0, $hours * 60 );
	}

	/**
	 * Returns how many days in advance a booking can be made.
	 */
	public function maximumAdvanceDays(): int {
		$modern = $this->getInt( 'maximum_advance', 0 );
		if ( $modern > 0 ) {
			return $modern;
		}

		return $this->getInt( 'max_advance_days', 60 );
	}

	/**
	 * Returns the arrival buffer (how many minutes early the customer should arrive).
	 */
	public function arrivalBuffer(): int {
		$modern = $this->getInt( 'arrival_buffer', 0 );
		if ( $modern > 0 ) {
			return $modern;
		}

		return $this->getInt( 'arrival_buffer_minutes', 0 );
	}

	public function bookingStartDate(): string {
		return $this->getString( 'booking_start_date', '' );
	}

	public function bookingEndDate(): string {
		return $this->getString( 'booking_end_date', '' );
	}

	public function appointmentLocation(): string {
		return $this->getString( 'appointment_location', '' );
	}

	public function showArrivalReminder(): bool {
		return $this->getBool( 'show_arrival_reminder', false );
	}

	public function bookingFormIntro(): string {
		return $this->getString( 'booking_form_intro', '' );
	}

	public function bookingFormIntroColor(): string {
		return $this->getString( 'booking_form_intro_color', '' );
	}

	public function postBookingInstructions(): string {
		return $this->getString( 'post_booking_instructions', '' );
	}

	public function postBookingInstructionsColor(): string {
		return $this->getString( 'post_booking_instructions_color', '' );
	}

	public function allowGeneralBooking(): bool {
		return $this->getBool( 'allow_general_booking', false );
	}

	public function bookingMode(): string {
		$mode = $this->getString( 'booking_mode', '' );
		$allowed = array(
			'general',
			'department_no_provider',
			'department_with_provider',
			'provider_only',
		);

		if ( in_array( $mode, $allowed, true ) ) {
			return $mode;
		}

		if ( $this->allowGeneralBooking() ) {
			return 'general';
		}

		return 'department_with_provider';
	}

	public function generalProviderId(): int {
		return $this->getInt( 'general_provider_id', 0 );
	}

	/**
	 * Returns working hours for a given ISO day of week (1=Monday…7=Sunday).
	 * Returns null if the day is not configured.
	 */
	public function workingHoursForDay( int $dayOfWeek ): ?array {
		$hours = $this->settings['default_working_hours'] ?? array();

		return $hours[ $dayOfWeek ] ?? null;
	}

	/**
	 * Whether auto-confirmation is enabled.
	 */
	public function autoConfirm(): bool {
		return $this->getBool( 'auto_confirm', true );
	}

	/**
	 * Whether payment is required.
	 */
	public function requiresPayment(): bool {
		return $this->getBool( 'require_payment', false );
	}

	/**
	 * Price for this provider/department (if applicable).
	 */
	public function price(): float {
		return (float) ( $this->settings['price'] ?? 0.0 );
	}

	/**
	 * The resolved department ID, if any.
	 */
	public function departmentId(): ?int {
		return $this->departmentId;
	}

	/**
	 * Returns the raw settings array (for serialization / debugging).
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return $this->settings;
	}
}
