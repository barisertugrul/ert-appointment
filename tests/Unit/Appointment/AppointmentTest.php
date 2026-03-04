<?php

declare(strict_types=1);

namespace ERTAppointment\Tests\Unit\Appointment;

use PHPUnit\Framework\TestCase;
use ERTAppointment\Domain\Appointment\Appointment;

final class AppointmentTest extends TestCase
{
    private function makeAppointment(array $overrides = []): Appointment
    {
        return Appointment::create(array_merge([
            'provider_id'      => 1,
            'department_id'    => 1,
            'customer_name'    => 'Test User',
            'customer_email'   => 'test@example.com',
            'customer_phone'   => '',
            'start_datetime'   => new \DateTimeImmutable('2025-06-15 10:00:00'),
            'end_datetime'     => new \DateTimeImmutable('2025-06-15 10:30:00'),
            'duration_minutes' => 30,
            'status'           => 'pending',
            'notes'            => '',
        ], $overrides));
    }

    public function test_create_returns_appointment_instance(): void
    {
        $appt = $this->makeAppointment();
        $this->assertInstanceOf(Appointment::class, $appt);
    }

    public function test_status_is_pending_by_default(): void
    {
        $appt = $this->makeAppointment();
        $this->assertSame('pending', $appt->status);
    }

    public function test_confirm_changes_status(): void
    {
        $appt      = $this->makeAppointment();
        $confirmed = $appt->confirm();

        $this->assertSame('confirmed', $confirmed->status);
        // Original unchanged (immutable).
        $this->assertSame('pending', $appt->status);
    }

    public function test_cancel_changes_status(): void
    {
        $appt      = $this->makeAppointment();
        $cancelled = $appt->cancel('Schedule conflict');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('Schedule conflict', $cancelled->cancellationReason);
    }

    public function test_duration_is_correct(): void
    {
        $appt = $this->makeAppointment(['duration_minutes' => 45]);
        $this->assertSame(45, $appt->durationMinutes);
    }

    public function test_formatted_date(): void
    {
        $appt = $this->makeAppointment();
        $this->assertSame('2025-06-15', $appt->formattedDate('Y-m-d'));
    }

    public function test_formatted_time(): void
    {
        $appt = $this->makeAppointment();
        $this->assertSame('10:00', $appt->formattedTime('H:i'));
    }

    public function test_is_upcoming_for_future_appointment(): void
    {
        $appt = $this->makeAppointment([
            'start_datetime' => new \DateTimeImmutable('+1 day'),
            'end_datetime'   => new \DateTimeImmutable('+1 day +30 minutes'),
        ]);

        $this->assertTrue($appt->isUpcoming());
    }

    public function test_is_not_upcoming_for_past_appointment(): void
    {
        $appt = $this->makeAppointment([
            'start_datetime' => new \DateTimeImmutable('-1 day'),
            'end_datetime'   => new \DateTimeImmutable('-1 day +30 minutes'),
        ]);

        $this->assertFalse($appt->isUpcoming());
    }
}
