<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Appointment;

/**
 * Backed enum for payment lifecycle statuses.
 */
enum PaymentStatus: string
{
    case NotRequired = 'not_required';
    case Pending     = 'pending';
    case Paid        = 'paid';
    case Refunded    = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::NotRequired => __('Not Required', 'ert-appointment'),
            self::Pending     => __('Pending',      'ert-appointment'),
            self::Paid        => __('Paid',          'ert-appointment'),
            self::Refunded    => __('Refunded',      'ert-appointment'),
        };
    }
}
