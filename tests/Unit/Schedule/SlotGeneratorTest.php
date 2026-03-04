<?php

declare(strict_types=1);

namespace ERTAppointment\Tests\Unit\Schedule;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ERTAppointment\Domain\Schedule\SlotGenerator;
use ERTAppointment\Domain\Schedule\TimeSlot;

/**
 * Unit tests for SlotGenerator.
 *
 * SlotGenerator is a pure function — no mocks needed.
 * All tests use a date far in the future so "minimum notice" never
 * blocks slots during the test run.
 */
final class SlotGeneratorTest extends TestCase
{
    private SlotGenerator $gen;

    /** A fixed future date that is always well beyond minimum-notice windows. */
    private DateTimeImmutable $futureDate;

    protected function setUp(): void
    {
        $this->gen        = new SlotGenerator();
        $this->futureDate = new DateTimeImmutable('2030-06-16'); // Monday
    }

    // ── Basic generation ──────────────────────────────────────────────────────

    public function test_returns_timeslot_instances(): void
    {
        $slots = $this->generate('09:00', '10:00', slotDuration: 30);

        $this->assertNotEmpty($slots);
        $this->assertContainsOnlyInstancesOf(TimeSlot::class, $slots);
    }

    public function test_generates_correct_count_for_clean_window(): void
    {
        // 09:00–17:00 = 8 h = 16 slots of 30 min.
        $slots = $this->generate('09:00', '17:00', slotDuration: 30, slotInterval: 30);
        $this->assertCount(16, $slots);
    }

    public function test_slot_interval_controls_density(): void
    {
        // 09:00–11:00 = 2 h.
        // Interval 15 min → 8 slots; interval 30 min → 4 slots.
        $dense  = $this->generate('09:00', '11:00', slotDuration: 30, slotInterval: 15);
        $sparse = $this->generate('09:00', '11:00', slotDuration: 30, slotInterval: 30);

        $this->assertCount(8, $dense);
        $this->assertCount(4, $sparse);
    }

    public function test_first_slot_starts_at_open_time(): void
    {
        $slots = $this->generate('09:00', '12:00', slotDuration: 30);
        $this->assertSame('09:00', $slots[0]->time);
    }

    public function test_last_slot_ends_at_or_before_close_time(): void
    {
        // 09:00–17:00, 30 min slots — last slot should start at 16:30.
        $slots    = $this->generate('09:00', '17:00', slotDuration: 30);
        $lastSlot = end($slots);
        $this->assertSame('16:30', $lastSlot->time);
    }

    // ── Edge cases ────────────────────────────────────────────────────────────

    public function test_returns_empty_when_window_is_zero(): void
    {
        $slots = $this->generate('09:00', '09:00', slotDuration: 30);
        $this->assertEmpty($slots);
    }

    public function test_returns_empty_when_close_before_open(): void
    {
        $slots = $this->generate('17:00', '09:00', slotDuration: 30);
        $this->assertEmpty($slots);
    }

    public function test_returns_empty_when_slot_longer_than_window(): void
    {
        // Window: 30 min, slot: 60 min — no slot fits.
        $slots = $this->generate('09:00', '09:30', slotDuration: 60);
        $this->assertEmpty($slots);
    }

    public function test_single_exact_fit_slot(): void
    {
        // Window exactly equals one slot.
        $slots = $this->generate('09:00', '09:30', slotDuration: 30);
        $this->assertCount(1, $slots);
        $this->assertSame('09:00', $slots[0]->time);
    }

    // ── Break exclusions ──────────────────────────────────────────────────────

    public function test_slot_overlapping_break_is_excluded(): void
    {
        $slots = $this->generate(
            open: '09:00', close: '17:00',
            slotDuration: 60,
            breaks: [['start' => '12:00', 'end' => '13:00']],
        );

        $times = array_column($slots, 'time');
        $this->assertNotContains('12:00', $times);
    }

    public function test_slot_partially_overlapping_break_is_excluded(): void
    {
        // Slot 11:30–12:30 overlaps break 12:00–13:00.
        $slots = $this->generate(
            open: '09:00', close: '17:00',
            slotDuration: 60, slotInterval: 30,
            breaks: [['start' => '12:00', 'end' => '13:00']],
        );

        $times = array_column($slots, 'time');
        $this->assertNotContains('11:30', $times, 'Partially overlapping slot must be excluded');
    }

    public function test_slots_before_and_after_break_are_available(): void
    {
        $slots = $this->generate(
            open: '09:00', close: '15:00',
            slotDuration: 60,
            breaks: [['start' => '12:00', 'end' => '13:00']],
        );

        $times = array_column($slots, 'time');
        $this->assertContains('11:00', $times, 'Slot before break should be available');
        $this->assertContains('13:00', $times, 'Slot after break should be available');
    }

    // ── Booked block exclusions ────────────────────────────────────────────────

    public function test_slot_overlapping_booked_block_is_excluded(): void
    {
        $d = $this->futureDate->format('Y-m-d');
        $slots = $this->generate(
            open: '09:00', close: '17:00',
            slotDuration: 60,
            bookedBlocks: [
                [
                    'start' => new DateTimeImmutable("{$d} 10:00"),
                    'end'   => new DateTimeImmutable("{$d} 11:00"),
                ],
            ],
        );

        $times = array_column($slots, 'time');
        $this->assertNotContains('10:00', $times);
    }

    public function test_buffer_after_blocks_subsequent_slot(): void
    {
        // Booked 10:00–11:00, bufferAfter=30 → 11:00 slot is also blocked.
        $d = $this->futureDate->format('Y-m-d');
        $slots = $this->generate(
            open: '09:00', close: '17:00',
            slotDuration: 60, slotInterval: 60,
            bufferAfter: 30,
            bookedBlocks: [
                [
                    'start' => new DateTimeImmutable("{$d} 10:00"),
                    'end'   => new DateTimeImmutable("{$d} 11:00"),
                ],
            ],
        );

        $times = array_column($slots, 'time');
        $this->assertNotContains('11:00', $times, 'Buffer after should block the next slot');
        $this->assertContains('12:00', $times, 'Slot after buffer window should be available');
    }

    public function test_buffer_before_blocks_preceding_slot(): void
    {
        // Booked 12:00–13:00, bufferBefore=30 → 11:30 slot is also blocked.
        $d = $this->futureDate->format('Y-m-d');
        $slots = $this->generate(
            open: '09:00', close: '17:00',
            slotDuration: 30, slotInterval: 30,
            bufferBefore: 30,
            bookedBlocks: [
                [
                    'start' => new DateTimeImmutable("{$d} 12:00"),
                    'end'   => new DateTimeImmutable("{$d} 12:30"),
                ],
            ],
        );

        $times = array_column($slots, 'time');
        $this->assertNotContains('11:30', $times, 'Buffer before should block preceding slot');
    }

    // ── All slots marked as available ────────────────────────────────────────

    public function test_all_returned_slots_are_marked_available(): void
    {
        $slots = $this->generate('09:00', '12:00', slotDuration: 30);

        foreach ($slots as $slot) {
            $this->assertTrue($slot->available, "Slot {$slot->time} should be marked available");
        }
    }

    // ── toArray ───────────────────────────────────────────────────────────────

    public function test_timeslot_to_array_has_required_keys(): void
    {
        $slots = $this->generate('09:00', '10:00', slotDuration: 30);
        $arr   = $slots[0]->toArray();

        $this->assertArrayHasKey('time', $arr);
        $this->assertArrayHasKey('datetime', $arr);
        $this->assertArrayHasKey('duration_minutes', $arr);
        $this->assertArrayHasKey('available', $arr);
    }

    public function test_timeslot_datetime_format_is_iso8601(): void
    {
        $slots = $this->generate('09:00', '10:00', slotDuration: 30);
        $dt    = $slots[0]->toArray()['datetime'];

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $dt);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * @param list<array{start:string,end:string}> $breaks
     * @param list<array{start:DateTimeImmutable,end:DateTimeImmutable}> $bookedBlocks
     * @return list<TimeSlot>
     */
    private function generate(
        string $open         = '09:00',
        string $close        = '17:00',
        int    $slotDuration = 30,
        int    $slotInterval = 30,
        int    $bufferBefore = 0,
        int    $bufferAfter  = 0,
        int    $minimumNotice = 0,
        array  $breaks       = [],
        array  $bookedBlocks = [],
    ): array {
        return $this->gen->generate(
            date:          $this->futureDate,
            openTime:      $open,
            closeTime:     $close,
            slotDuration:  $slotDuration,
            slotInterval:  $slotInterval,
            bufferBefore:  $bufferBefore,
            bufferAfter:   $bufferAfter,
            minimumNotice: $minimumNotice,
            breaks:        $breaks,
            bookedBlocks:  $bookedBlocks,
        );
    }
}
