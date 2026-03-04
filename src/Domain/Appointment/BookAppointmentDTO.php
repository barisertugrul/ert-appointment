<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Appointment;

use DateTimeImmutable;

/**
 * Data Transfer Object for creating a new appointment.
 * Constructed from sanitized REST API / form input.
 */
final class BookAppointmentDTO
{
    public function __construct(
        public readonly int              $providerId,
        public readonly ?int             $departmentId,
        public readonly ?int             $formId,
        public readonly ?int             $customerUserId,
        public readonly string           $customerName,
        public readonly string           $customerEmail,
        public readonly ?string          $customerPhone,
        public readonly DateTimeImmutable $startDatetime,
        public readonly int              $durationMinutes,
        public readonly float            $price,
        public readonly array            $formData,
        public readonly ?string          $notes,
        public readonly int              $arrivalBufferMinutes,
    ) {}

    /**
     * Convenience factory for building a reschedule booking DTO from an
     * existing appointment and a reschedule request.
     */
    public static function fromReschedule(RescheduleDTO $dto, Appointment $original): self
    {
        return new self(
            providerId:           $original->providerId,
            departmentId:         $original->departmentId,
            formId:               $original->formId,
            customerUserId:       $original->customerUserId,
            customerName:         $original->customerName,
            customerEmail:        $original->customerEmail,
            customerPhone:        $original->customerPhone,
            startDatetime:        $dto->newStartDatetime,
            durationMinutes:      $original->durationMinutes,
            price:                $original->paymentAmount ?? 0.0,
            formData:             $original->formData,
            notes:                $dto->notes,
            arrivalBufferMinutes: $original->arrivalBufferMinutes,
        );
    }
}
