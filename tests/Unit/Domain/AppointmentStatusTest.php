<?php

declare(strict_types=1);

namespace ERTAppointment\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use ERTAppointment\Domain\Appointment\AppointmentStatus;

/**
 * Unit tests for the AppointmentStatus backed enum.
 */
final class AppointmentStatusTest extends TestCase
{
    // ── Backing values ────────────────────────────────────────────────────────

    public function test_pending_value(): void
    {
        $this->assertSame('pending', AppointmentStatus::Pending->value);
    }

    public function test_confirmed_value(): void
    {
        $this->assertSame('confirmed', AppointmentStatus::Confirmed->value);
    }

    public function test_cancelled_value(): void
    {
        $this->assertSame('cancelled', AppointmentStatus::Cancelled->value);
    }

    public function test_completed_value(): void
    {
        $this->assertSame('completed', AppointmentStatus::Completed->value);
    }

    public function test_no_show_value(): void
    {
        $this->assertSame('no_show', AppointmentStatus::NoShow->value);
    }

    public function test_rescheduled_value(): void
    {
        $this->assertSame('rescheduled', AppointmentStatus::Rescheduled->value);
    }

    // ── from() / tryFrom() ────────────────────────────────────────────────────

    public function test_from_string_returns_correct_case(): void
    {
        $this->assertSame(AppointmentStatus::Confirmed, AppointmentStatus::from('confirmed'));
    }

    public function test_try_from_returns_null_for_unknown_value(): void
    {
        $this->assertNull(AppointmentStatus::tryFrom('unknown_status'));
    }

    // ── label() ───────────────────────────────────────────────────────────────

    public function test_label_returns_non_empty_string_for_all_cases(): void
    {
        foreach (AppointmentStatus::cases() as $status) {
            $this->assertNotEmpty($status->label(), "label() is empty for {$status->name}");
        }
    }

    // ── cssClass() ────────────────────────────────────────────────────────────

    public function test_css_class_starts_with_prefix(): void
    {
        foreach (AppointmentStatus::cases() as $status) {
            $this->assertStringStartsWith(
                'ertaa-badge--',
                $status->cssClass(),
                "cssClass() missing prefix for {$status->name}"
            );
        }
    }

    public function test_css_class_uses_hyphens_not_underscores(): void
    {
        $this->assertSame('ertaa-badge--no-show', AppointmentStatus::NoShow->cssClass());
    }

    // ── activeStatuses() / terminalStatuses() ─────────────────────────────────

    public function test_active_statuses_contains_pending_and_confirmed(): void
    {
        $active = AppointmentStatus::activeStatuses();

        $this->assertContains(AppointmentStatus::Pending,   $active);
        $this->assertContains(AppointmentStatus::Confirmed, $active);
    }

    public function test_active_statuses_does_not_contain_cancelled(): void
    {
        $this->assertNotContains(AppointmentStatus::Cancelled, AppointmentStatus::activeStatuses());
    }

    public function test_terminal_statuses_contains_expected_cases(): void
    {
        $terminal = AppointmentStatus::terminalStatuses();

        foreach ([
            AppointmentStatus::Cancelled,
            AppointmentStatus::Completed,
            AppointmentStatus::NoShow,
            AppointmentStatus::Rescheduled,
        ] as $expected) {
            $this->assertContains($expected, $terminal);
        }
    }

    public function test_active_and_terminal_are_disjoint(): void
    {
        $active   = AppointmentStatus::activeStatuses();
        $terminal = AppointmentStatus::terminalStatuses();
        $overlap  = array_intersect($active, $terminal);

        $this->assertEmpty($overlap, 'A status should not appear in both active and terminal lists');
    }
}
