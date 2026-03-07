<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Appointment;

use DateTimeImmutable;
use RuntimeException;
use ERTAppointment\Domain\Schedule\AvailabilityService;
use ERTAppointment\Domain\Notification\NotificationService;
use ERTAppointment\Infrastructure\Cache\TransientCache;
use ERTAppointment\Settings\SettingsManager;

/**
 * Orchestrates all appointment lifecycle operations.
 *
 * This is the primary entry point for booking, confirming, cancelling,
 * and rescheduling appointments. Payment is intentionally kept out of this
 * class and delegated via a WordPress filter so that the Pro add-on can
 * inject payment logic without modifying core code.
 */
final class AppointmentService {

	public function __construct(
		private readonly AppointmentRepository $repository,
		private readonly AvailabilityService $availabilityService,
		private readonly NotificationService $notificationService,
		private readonly SettingsManager $settings,
		private readonly TransientCache $cache,
	) {}

	// -------------------------------------------------------------------------
	// Booking
	// -------------------------------------------------------------------------

	/**
	 * Books a new appointment.
	 *
	 * Steps:
	 *  1. Validate slot is still available (concurrency-safe check).
	 *  2. Build the Appointment entity.
	 *  3. Persist.
	 *  4. Trigger payment flow if required (via filter — Pro handles this).
	 *  5. Auto-confirm if configured.
	 *  6. Dispatch notifications.
	 *  7. Bust slot cache.
	 *
	 * @throws SlotNotAvailableException  When the chosen slot is no longer open.
	 * @throws \RuntimeException          On any other failure.
	 */
	public function book( BookAppointmentDTO $dto ): Appointment {
		// 1. Re-check availability (race condition guard).
		$this->assertSlotAvailable( $dto->providerId, $dto->startDatetime, $dto->durationMinutes );

		// 2. Resolve settings for price / arrival buffer.
		$config = $this->settings->resolveForProvider( $dto->providerId );

		// 3. Build entity.
		$appointment = Appointment::create( $dto );

		// 4. Persist so we have an ID.
		$appointment = $this->repository->save( $appointment );

		/**
		 * Filter: allows the Pro add-on to initiate a payment and attach a URL.
		 * The filter receives the saved Appointment and should return either
		 * the same appointment (if payment is not required) or a new instance
		 * with a paymentUrl set and payment_status = 'pending'.
		 *
		 * @param Appointment $appointment
		 * @param SettingsManager $settings
		 */
		$appointment = apply_filters( 'erta_after_booking_saved', $appointment, $this->settings );

		if ( $appointment->requiresPayment() && $appointment->paymentUrl !== null ) {
			// Payment pending — do not confirm yet; return with redirect URL.
			$this->repository->save( $appointment->withPayment( PaymentStatus::Pending ) );
			return $appointment;
		}

		// 5. Auto-confirm.
		if ( $config->autoConfirm() ) {
			$appointment = $this->confirm( $appointment->id );
		} else {
			// Notify admin for manual review.
			$this->notificationService->dispatch( 'appointment_pending', $appointment );
		}

		// 6. Bust slot cache for this provider + date.
		$this->bustSlotCache( $dto->providerId, $dto->startDatetime );

		return $appointment;
	}

	// -------------------------------------------------------------------------
	// Status transitions
	// -------------------------------------------------------------------------

	/**
	 * Confirms a pending appointment.
	 */
	public function confirm( int $appointmentId ): Appointment {
		$appointment = $this->repository->findOrFail( $appointmentId );

		if ( $appointment->status === AppointmentStatus::Confirmed ) {
			return $appointment; // Idempotent.
		}

		$appointment = $appointment->withStatus( AppointmentStatus::Confirmed );
		$appointment = $this->repository->save( $appointment );

		$this->notificationService->dispatch( 'appointment_confirmed', $appointment );

		/**
		 * Fires after an appointment is confirmed.
		 *
		 * @param Appointment $appointment
		 */
		do_action( 'erta_appointment_confirmed', $appointment );

		return $appointment;
	}

	/**
	 * Reverts a confirmed appointment back to pending.
	 *
	 * @throws RuntimeException When the appointment is not in confirmed state.
	 */
	public function unconfirm( int $appointmentId ): Appointment {
		$appointment = $this->repository->findOrFail( $appointmentId );

		if ( $appointment->status === AppointmentStatus::Pending ) {
			return $appointment; // Idempotent.
		}

		if ( $appointment->status !== AppointmentStatus::Confirmed ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s appointment status */
					esc_html__( 'Cannot undo confirmation for an appointment with status "%s".', 'ert-appointment' ),
					esc_html( $appointment->status->label() )
				)
			);
		}

		$appointment = $appointment->withStatus( AppointmentStatus::Pending );
		$appointment = $this->repository->save( $appointment );

		do_action( 'erta_appointment_unconfirmed', $appointment );

		return $appointment;
	}

	/**
	 * Cancels an appointment.
	 *
	 * @throws RuntimeException When the appointment cannot be cancelled.
	 */
	public function cancel( int $appointmentId, string $reason, int $actorUserId ): Appointment {
		$appointment = $this->repository->findOrFail( $appointmentId );

		$this->assertCanActOn( $appointment, $actorUserId, 'cancel' );

		if ( ! $appointment->isCancellable() ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s appointment status */
					esc_html__( 'Cannot cancel an appointment with status "%s".', 'ert-appointment' ),
					esc_html( $appointment->status->label() )
				)
			);
		}

		$appointment = $appointment->withStatus( AppointmentStatus::Cancelled, $reason );
		$appointment = $this->repository->save( $appointment );

		/**
		 * Filter: Pro add-on can hook here to process refunds before notifications.
		 *
		 * @param Appointment $appointment
		 */
		$appointment = apply_filters( 'erta_before_cancel_notifications', $appointment );

		$this->notificationService->dispatch( 'appointment_cancelled', $appointment );
		$this->bustSlotCache( $appointment->providerId, $appointment->startDatetime );

		/**
		 * Fires after an appointment is cancelled.
		 */
		do_action( 'erta_appointment_cancelled', $appointment );

		return $appointment;
	}

	/**
	 * Reschedules an appointment to a new date/time.
	 *
	 * @throws SlotNotAvailableException
	 * @throws RuntimeException
	 */
	public function reschedule( int $appointmentId, RescheduleDTO $dto, int $actorUserId ): Appointment {
		$original = $this->repository->findOrFail( $appointmentId );

		$this->assertCanActOn( $original, $actorUserId, 'reschedule' );

		if ( ! $original->isReschedulable() ) {
			throw new RuntimeException(
				esc_html__( 'This appointment cannot be rescheduled.', 'ert-appointment' )
			);
		}

		// Validate new slot.
		$this->assertSlotAvailable(
			$original->providerId,
			$dto->newStartDatetime,
			$original->durationMinutes
		);

		// Mark original as rescheduled, create new confirmed appointment.
		$original = $original->withStatus( AppointmentStatus::Rescheduled );
		$this->repository->save( $original );

		$rescheduled = $original->reschedule( $dto );
		$rescheduled = $this->repository->save( $rescheduled );

		$this->notificationService->dispatch( 'appointment_rescheduled', $rescheduled );
		$this->bustSlotCache( $original->providerId, $original->startDatetime );
		$this->bustSlotCache( $rescheduled->providerId, $rescheduled->startDatetime );

		do_action( 'erta_appointment_rescheduled', $rescheduled, $original );

		return $rescheduled;
	}

	/**
	 * Marks an appointment as completed.
	 */
	public function complete( int $appointmentId ): Appointment {
		$appointment = $this->repository->findOrFail( $appointmentId );
		$appointment = $appointment->withStatus( AppointmentStatus::Completed );
		$appointment = $this->repository->save( $appointment );

		do_action( 'erta_appointment_completed', $appointment );

		return $appointment;
	}

	/**
	 * Marks an appointment as no-show.
	 */
	public function markNoShow( int $appointmentId ): Appointment {
		$appointment = $this->repository->findOrFail( $appointmentId );
		$appointment = $appointment->withStatus( AppointmentStatus::NoShow );

		return $this->repository->save( $appointment );
	}

	// -------------------------------------------------------------------------
	// Validation helpers
	// -------------------------------------------------------------------------

	/**
	 * Throws if the chosen slot is not available.
	 *
	 * @throws SlotNotAvailableException
	 */
	private function assertSlotAvailable(
		int $providerId,
		DateTimeImmutable $start,
		int $durationMinutes
	): void {
		$date  = $start->setTime( 0, 0, 0 );
		$slots = $this->availabilityService->getAvailableSlots( $providerId, $date );
		$time  = $start->format( 'H:i' );

		foreach ( $slots as $slot ) {
			if ( $slot->time === $time && $slot->durationMinutes >= $durationMinutes ) {
				return; // Slot found and available.
			}
		}

		throw new SlotNotAvailableException(
			esc_html__( 'The selected time slot is no longer available. Please choose another.', 'ert-appointment' )
		);
	}

	/**
	 * Checks whether the acting user has permission to cancel/reschedule.
	 *
	 * Admins can act on any appointment.
	 * Providers can act on their own appointments.
	 * Customers can act only if the appointment belongs to their email.
	 *
	 * @throws RuntimeException
	 */
	private function assertCanActOn( Appointment $appointment, int $actorUserId, string $action ): void {
		if ( user_can( $actorUserId, 'erta_manage_all' ) ) {
			return;
		}

		// Check if actor is an assigned provider user.
		global $wpdb;
		$isProvider = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}erta_provider_users
             WHERE provider_id = %d AND user_id = %d",
				$appointment->providerId,
				$actorUserId
			)
		);

		if ( $isProvider ) {
			return;
		}

		// Allow the customer themselves.
		if ( $appointment->customerUserId === $actorUserId ) {
			return;
		}

		throw new RuntimeException(
			sprintf(
				/* translators: %s action name */
				esc_html__( 'You do not have permission to %s this appointment.', 'ert-appointment' ),
				esc_html( $action )
			)
		);
	}

	// -------------------------------------------------------------------------
	// Cache helpers
	// -------------------------------------------------------------------------

	private function bustSlotCache( int $providerId, DateTimeImmutable $date ): void {
		$key = "slots_{$providerId}_{$date->format('Y-m-d')}";
		$this->cache->delete( $key );
	}
}
