<?php

declare(strict_types=1);

namespace ERTAppointment\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;
use ERTAppointment\Settings\ResolvedConfig;

/**
 * Unit tests for ResolvedConfig.
 *
 * ResolvedConfig is a pure value object — no mocks or WP dependencies needed.
 */
final class ResolvedConfigTest extends TestCase
{
    // ── Generic accessors ─────────────────────────────────────────────────────

    public function test_get_returns_value_when_key_exists(): void
    {
        $config = new ResolvedConfig(['currency' => 'TRY']);
        $this->assertSame('TRY', $config->get('currency'));
    }

    public function test_get_returns_default_when_key_missing(): void
    {
        $config = new ResolvedConfig([]);
        $this->assertSame('fallback', $config->get('missing', 'fallback'));
    }

    public function test_get_int_casts_to_int(): void
    {
        $config = new ResolvedConfig(['slot_duration' => '45']);
        $this->assertSame(45, $config->getInt('slot_duration'));
    }

    public function test_get_int_returns_default_for_missing_key(): void
    {
        $config = new ResolvedConfig([]);
        $this->assertSame(99, $config->getInt('nope', 99));
    }

    public function test_get_bool_true_for_truthy_string(): void
    {
        foreach (['1', 'true', 'yes'] as $truthy) {
            $config = new ResolvedConfig(['flag' => $truthy]);
            $this->assertTrue($config->getBool('flag'), "Expected true for '{$truthy}'");
        }
    }

    public function test_get_bool_false_for_falsy_values(): void
    {
        foreach (['0', 'false', 'no', ''] as $falsy) {
            $config = new ResolvedConfig(['flag' => $falsy]);
            $this->assertFalse($config->getBool('flag'), "Expected false for '{$falsy}'");
        }
    }

    public function test_get_string_casts_value(): void
    {
        $config = new ResolvedConfig(['label' => 42]);
        $this->assertSame('42', $config->getString('label'));
    }

    // ── Schedule helpers ──────────────────────────────────────────────────────

    public function test_slot_duration_default(): void
    {
        $config = new ResolvedConfig([]);
        $this->assertSame(30, $config->slotDuration());
    }

    public function test_slot_duration_from_settings(): void
    {
        $config = new ResolvedConfig(['slot_duration' => 60]);
        $this->assertSame(60, $config->slotDuration());
    }

    public function test_slot_interval_default(): void
    {
        $config = new ResolvedConfig([]);
        $this->assertSame(30, $config->slotInterval());
    }

    public function test_buffer_before_default_is_zero(): void
    {
        $config = new ResolvedConfig([]);
        $this->assertSame(0, $config->bufferBefore());
    }

    public function test_buffer_after_default_is_zero(): void
    {
        $config = new ResolvedConfig([]);
        $this->assertSame(0, $config->bufferAfter());
    }

    public function test_minimum_notice_default(): void
    {
        $config = new ResolvedConfig([]);
        $this->assertSame(60, $config->minimumNotice());
    }

    public function test_maximum_advance_days_default(): void
    {
        $config = new ResolvedConfig([]);
        $this->assertSame(60, $config->maximumAdvanceDays());
    }

    public function test_auto_confirm_default_is_true(): void
    {
        $config = new ResolvedConfig([]);
        $this->assertTrue($config->autoConfirm());
    }

    public function test_auto_confirm_can_be_disabled(): void
    {
        $config = new ResolvedConfig(['auto_confirm' => false]);
        $this->assertFalse($config->autoConfirm());
    }

    public function test_requires_payment_default_is_false(): void
    {
        $config = new ResolvedConfig([]);
        $this->assertFalse($config->requiresPayment());
    }

    public function test_price_default_is_zero(): void
    {
        $config = new ResolvedConfig([]);
        $this->assertSame(0.0, $config->price());
    }

    public function test_price_is_cast_to_float(): void
    {
        $config = new ResolvedConfig(['price' => '150.50']);
        $this->assertSame(150.5, $config->price());
    }

    // ── Working hours ─────────────────────────────────────────────────────────

    public function test_working_hours_for_configured_day(): void
    {
        $hours = ['open' => true, 'start' => '09:00', 'end' => '17:00'];
        $config = new ResolvedConfig(['default_working_hours' => [1 => $hours]]);

        $this->assertSame($hours, $config->workingHoursForDay(1));
    }

    public function test_working_hours_returns_null_for_missing_day(): void
    {
        $config = new ResolvedConfig([]);
        $this->assertNull($config->workingHoursForDay(7));
    }

    // ── Department scope ──────────────────────────────────────────────────────

    public function test_department_id_is_null_by_default(): void
    {
        $config = new ResolvedConfig([]);
        $this->assertNull($config->departmentId());
    }

    public function test_department_id_is_set_correctly(): void
    {
        $config = new ResolvedConfig([], departmentId: 5);
        $this->assertSame(5, $config->departmentId());
    }

    // ── Serialisation ─────────────────────────────────────────────────────────

    public function test_to_array_returns_original_settings(): void
    {
        $data   = ['currency' => 'USD', 'slot_duration' => 30];
        $config = new ResolvedConfig($data);

        $this->assertSame($data, $config->toArray());
    }
}
