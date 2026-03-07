<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Appointment;

use DateTimeImmutable;

/**
 * Appointment entity.
 *
 * Immutable after construction. All mutations return new instances,
 * keeping the entity free of side effects and easy to test.
 */
final class Appointment {

	// -------------------------------------------------------------------------
	// Constructor
	// -------------------------------------------------------------------------

	public function __construct(
		public readonly ?int $id,
		public readonly int $providerId,
		public readonly ?int $departmentId,
		public readonly ?int $formId,
		public readonly ?int $customerUserId,
		public readonly string $customerName,
		public readonly string $customerEmail,
		public readonly string $customerPhone,
		public readonly DateTimeImmutable $startDatetime,
		public readonly DateTimeImmutable $endDatetime,
		public readonly int $durationMinutes,
		public readonly AppointmentStatus $status,
		public readonly PaymentStatus $paymentStatus,
		public readonly ?float $paymentAmount,
		public readonly ?string $paymentGateway,
		public readonly ?string $paymentTransactionId,
		public readonly array $formData,          // deserialized form submission
		public readonly string $notes,
		public readonly string $internalNotes,
		public readonly string $cancellationReason,
		public readonly ?int $rescheduledFrom,   // original appointment ID
		public readonly ?int $groupId,           // Pro: group booking
		public readonly int $arrivalBufferMinutes,
		public readonly ?string $paymentUrl,        // transient, not stored
		public readonly DateTimeImmutable $createdAt,
		public readonly DateTimeImmutable $updatedAt,
	) {}

	// -------------------------------------------------------------------------
	// Factory
	// -------------------------------------------------------------------------

	/**
	 * Creates a new (unsaved) appointment from a booking DTO.
	 */
	public static function create( BookAppointmentDTO $dto ): self {
		$now = new DateTimeImmutable();
		$end = $dto->startDatetime->modify( "+{$dto->durationMinutes} minutes" );

		return new self(
			id:                    null,
			providerId:            $dto->providerId,
			departmentId:          $dto->departmentId,
			formId:                $dto->formId,
			customerUserId:        $dto->customerUserId,
			customerName:          $dto->customerName,
			customerEmail:         $dto->customerEmail,
			customerPhone:         $dto->customerPhone ?? '',
			startDatetime:         $dto->startDatetime,
			endDatetime:           $end,
			durationMinutes:       $dto->durationMinutes,
			status:                AppointmentStatus::Pending,
			paymentStatus:         PaymentStatus::NotRequired,
			paymentAmount:         $dto->price > 0.0 ? $dto->price : null,
			paymentGateway:        null,
			paymentTransactionId:  null,
			formData:              $dto->formData,
			notes:                 $dto->notes ?? '',
			internalNotes:         '',
			cancellationReason:    '',
			rescheduledFrom:       null,
			groupId:               null,
			arrivalBufferMinutes:  $dto->arrivalBufferMinutes,
			paymentUrl:            null,
			createdAt:             $now,
			updatedAt:             $now,
		);
	}

	/**
	 * Reconstructs an appointment from a raw database row.
	 *
	 * @param array<string, mixed> $row
	 */
	public static function fromRow( array $row ): self {
		return new self(
			id:                    (int) $row['id'],
			providerId:            (int) $row['provider_id'],
			departmentId:          isset( $row['department_id'] ) ? (int) $row['department_id'] : null,
			formId:                isset( $row['form_id'] ) ? (int) $row['form_id'] : null,
			customerUserId:        isset( $row['customer_user_id'] ) ? (int) $row['customer_user_id'] : null,
			customerName:          (string) $row['customer_name'],
			customerEmail:         (string) $row['customer_email'],
			customerPhone:         (string) ( $row['customer_phone'] ?? '' ),
			startDatetime:         new DateTimeImmutable( $row['start_datetime'] ),
			endDatetime:           new DateTimeImmutable( $row['end_datetime'] ),
			durationMinutes:       (int) $row['duration_minutes'],
			status:                AppointmentStatus::from( $row['status'] ),
			paymentStatus:         PaymentStatus::from( $row['payment_status'] ),
			paymentAmount:         isset( $row['payment_amount'] ) ? (float) $row['payment_amount'] : null,
			paymentGateway:        $row['payment_gateway'] ?? null,
			paymentTransactionId:  $row['payment_transaction_id'] ?? null,
			formData:              json_decode( $row['form_data'] ?? '[]', true ) ?? array(),
			notes:                 (string) ( $row['notes'] ?? '' ),
			internalNotes:         (string) ( $row['internal_notes'] ?? '' ),
			cancellationReason:    (string) ( $row['cancellation_reason'] ?? '' ),
			rescheduledFrom:       isset( $row['rescheduled_from'] ) ? (int) $row['rescheduled_from'] : null,
			groupId:               isset( $row['group_id'] ) ? (int) $row['group_id'] : null,
			arrivalBufferMinutes:  (int) ( $row['arrival_buffer_minutes'] ?? 0 ),
			paymentUrl:            null,
			createdAt:             new DateTimeImmutable( $row['created_at'] ),
			updatedAt:             new DateTimeImmutable( $row['updated_at'] ),
		);
	}

	// -------------------------------------------------------------------------
	// Mutation helpers (return new instances)
	// -------------------------------------------------------------------------

	public function withStatus( AppointmentStatus $status, string $reason = '' ): self {
		return new self(
			...array(
				...$this->toNamedArgs(),
				'status'             => $status,
				'cancellationReason' => $reason,
				'updatedAt'          => new DateTimeImmutable(),
			)
		);
	}

	public function withPaymentUrl( string $url ): self {
		return new self(
			...array(
				...$this->toNamedArgs(),
				'paymentUrl' => $url,
			)
		);
	}

	public function withPayment(
		PaymentStatus $paymentStatus,
		?string $gateway = null,
		?string $transactionId = null
	): self {
		return new self(
			...array(
				...$this->toNamedArgs(),
				'paymentStatus'        => $paymentStatus,
				'paymentGateway'       => $gateway,
				'paymentTransactionId' => $transactionId,
				'updatedAt'            => new DateTimeImmutable(),
			)
		);
	}

	/**
	 * Creates a new appointment entity representing a reschedule of this one.
	 */
	public function reschedule( RescheduleDTO $dto ): self {
		$end = $dto->newStartDatetime->modify( "+{$this->durationMinutes} minutes" );

		return new self(
			...array(
				...$this->toNamedArgs(),
				'id'              => null,         // new row
				'startDatetime'   => $dto->newStartDatetime,
				'endDatetime'     => $end,
				'status'          => AppointmentStatus::Confirmed,
				'rescheduledFrom' => $this->id,
				'notes'           => $dto->notes ?? $this->notes,
				'updatedAt'       => new DateTimeImmutable(),
				'createdAt'       => new DateTimeImmutable(),
			)
		);
	}

	// -------------------------------------------------------------------------
	// State queries
	// -------------------------------------------------------------------------

	public function requiresPayment(): bool {
		return $this->paymentAmount !== null && $this->paymentAmount > 0.0;
	}

	public function isPaid(): bool {
		return $this->paymentStatus === PaymentStatus::Paid;
	}

	public function isCancellable(): bool {
		return in_array(
			$this->status,
			array(
				AppointmentStatus::Pending,
				AppointmentStatus::Confirmed,
			),
			true
		);
	}

	public function isReschedulable(): bool {
		return $this->isCancellable();
	}

	public function isUpcoming(): bool {
		return $this->startDatetime > new DateTimeImmutable()
			&& $this->isCancellable();
	}

	/**
	 * Returns the display date string using WP timezone.
	 */
	public function formattedDate( string $format = 'Y-m-d' ): string {
		return wp_date( $format, $this->startDatetime->getTimestamp() );
	}

	/**
	 * Returns the display time string using WP timezone.
	 */
	public function formattedTime( string $format = 'H:i' ): string {
		return wp_date( $format, $this->startDatetime->getTimestamp() );
	}

	// -------------------------------------------------------------------------
	// Serialization helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns a flat array suitable for database insertion/update.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'provider_id'            => $this->providerId,
			'department_id'          => $this->departmentId,
			'form_id'                => $this->formId,
			'customer_user_id'       => $this->customerUserId,
			'customer_name'          => $this->customerName,
			'customer_email'         => $this->customerEmail,
			'customer_phone'         => $this->customerPhone,
			'start_datetime'         => $this->startDatetime->format( 'Y-m-d H:i:s' ),
			'end_datetime'           => $this->endDatetime->format( 'Y-m-d H:i:s' ),
			'duration_minutes'       => $this->durationMinutes,
			'status'                 => $this->status->value,
			'payment_status'         => $this->paymentStatus->value,
			'payment_amount'         => $this->paymentAmount,
			'payment_gateway'        => $this->paymentGateway,
			'payment_transaction_id' => $this->paymentTransactionId,
			'form_data'              => wp_json_encode( $this->formData ),
			'notes'                  => $this->notes,
			'internal_notes'         => $this->internalNotes,
			'cancellation_reason'    => $this->cancellationReason,
			'rescheduled_from'       => $this->rescheduledFrom,
			'group_id'               => $this->groupId,
			'arrival_buffer_minutes' => $this->arrivalBufferMinutes,
		);
	}

	/**
	 * Returns named constructor arguments for spread-with-override pattern.
	 *
	 * @return array<string, mixed>
	 */
	private function toNamedArgs(): array {
		return array(
			'id'                   => $this->id,
			'providerId'           => $this->providerId,
			'departmentId'         => $this->departmentId,
			'formId'               => $this->formId,
			'customerUserId'       => $this->customerUserId,
			'customerName'         => $this->customerName,
			'customerEmail'        => $this->customerEmail,
			'customerPhone'        => $this->customerPhone,
			'startDatetime'        => $this->startDatetime,
			'endDatetime'          => $this->endDatetime,
			'durationMinutes'      => $this->durationMinutes,
			'status'               => $this->status,
			'paymentStatus'        => $this->paymentStatus,
			'paymentAmount'        => $this->paymentAmount,
			'paymentGateway'       => $this->paymentGateway,
			'paymentTransactionId' => $this->paymentTransactionId,
			'formData'             => $this->formData,
			'notes'                => $this->notes,
			'internalNotes'        => $this->internalNotes,
			'cancellationReason'   => $this->cancellationReason,
			'rescheduledFrom'      => $this->rescheduledFrom,
			'groupId'              => $this->groupId,
			'arrivalBufferMinutes' => $this->arrivalBufferMinutes,
			'paymentUrl'           => $this->paymentUrl,
			'createdAt'            => $this->createdAt,
			'updatedAt'            => $this->updatedAt,
		);
	}
}
