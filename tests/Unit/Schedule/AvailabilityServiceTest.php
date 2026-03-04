<?php

declare(strict_types=1);

namespace ERTAppointment\Tests\Unit\Schedule;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ERTAppointment\Domain\Schedule\AvailabilityService;
use ERTAppointment\Domain\Schedule\SlotGenerator;
use ERTAppointment\Domain\Appointment\AppointmentRepository;
use ERTAppointment\Settings\SettingsManager;
use ERTAppointment\Infrastructure\Cache\TransientCache;

final class AvailabilityServiceTest extends TestCase
{
    private AvailabilityService $service;
    private MockObject $settings;
    private MockObject $appointmentRepo;
    private MockObject $cache;

    protected function setUp(): void
    {
        $this->settings        = $this->createMock(SettingsManager::class);
        $this->appointmentRepo = $this->createMock(AppointmentRepository::class);
        $this->cache           = $this->createMock(TransientCache::class);

        // Cache always executes callback (no caching in tests).
        $this->cache->method('remember')
            ->willReturnCallback(fn($key, $ttl, $cb) => $cb());

        $this->service = new AvailabilityService(
            settings:              $this->settings,
            slotGenerator:         new SlotGenerator(),
            appointmentRepository: $this->appointmentRepo,
            cache:                 $this->cache,
        );
    }

    public function test_returns_empty_slots_on_closed_day(): void
    {
        $config = $this->makeConfig(workingHours: null); // null = closed
        $this->settings->method('resolveForProvider')->willReturn($config);
        $this->mockNoSpecialDay();
        $this->mockNoBookedBlocks();

        $slots = $this->service->getAvailableSlots(1, new DateTimeImmutable('2025-06-16')); // Monday

        $this->assertEmpty($slots);
    }

    public function test_returns_slots_on_open_day(): void
    {
        $config = $this->makeConfig(workingHours: ['open' => true, 'start' => '09:00', 'end' => '17:00']);
        $this->settings->method('resolveForProvider')->willReturn($config);
        $this->mockNoSpecialDay();
        $this->mockNoBookedBlocks();

        $slots = $this->service->getAvailableSlots(1, new DateTimeImmutable('2025-06-16'));

        $this->assertNotEmpty($slots);
    }

    public function test_slot_count_matches_working_hours(): void
    {
        // 09:00–17:00 = 8 hours = 16 slots of 30 min (no buffers, no breaks).
        $config = $this->makeConfig(
            workingHours: ['open' => true, 'start' => '09:00', 'end' => '17:00'],
            slotDuration: 30,
            bufferBefore: 0,
            bufferAfter:  0,
        );
        $this->settings->method('resolveForProvider')->willReturn($config);
        $this->mockNoSpecialDay();
        $this->mockNoBookedBlocks();

        $slots = $this->service->getAvailableSlots(1, new DateTimeImmutable('2025-06-16'));

        $this->assertCount(16, $slots);
    }

    public function test_booked_slot_is_excluded(): void
    {
        $config = $this->makeConfig(
            workingHours: ['open' => true, 'start' => '09:00', 'end' => '17:00'],
            slotDuration: 30,
        );
        $this->settings->method('resolveForProvider')->willReturn($config);
        $this->mockNoSpecialDay();

        // Block the 09:00 slot.
        $this->appointmentRepo->method('findBookedBlocks')->willReturn([
            ['start' => '09:00', 'end' => '09:30'],
        ]);

        $slots = $this->service->getAvailableSlots(1, new DateTimeImmutable('2025-06-16'));
        $times = array_map(fn($s) => $s->startTime, $slots);

        $this->assertNotContains('09:00', $times);
        $this->assertContains('09:30', $times);
    }

    public function test_returns_empty_on_special_closed_day(): void
    {
        $config = $this->makeConfig(workingHours: ['open' => true, 'start' => '09:00', 'end' => '17:00']);
        $this->settings->method('resolveForProvider')->willReturn($config);

        // Special day marked as closed.
        $this->mockSpecialDay(['is_closed' => true]);
        $this->mockNoBookedBlocks();

        $slots = $this->service->getAvailableSlots(1, new DateTimeImmutable('2025-06-16'));

        $this->assertEmpty($slots);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeConfig(
        ?array $workingHours = null,
        int $slotDuration = 30,
        int $bufferBefore = 0,
        int $bufferAfter  = 0,
    ): object {
        return new class($workingHours, $slotDuration, $bufferBefore, $bufferAfter) {
            public function __construct(
                private ?array $hours,
                private int $duration,
                private int $before,
                private int $after,
            ) {}
            public function workingHoursForDay(int $day): ?array { return $this->hours; }
            public function slotDuration(): int   { return $this->duration; }
            public function slotInterval(): int   { return $this->duration; }
            public function bufferBefore(): int   { return $this->before; }
            public function bufferAfter(): int    { return $this->after; }
            public function minimumNotice(): int  { return 0; }
        };
    }

    private function mockNoSpecialDay(): void
    {
        $this->appointmentRepo->method('findSpecialDay')->willReturn(null);
    }

    private function mockSpecialDay(array $data): void
    {
        $this->appointmentRepo->method('findSpecialDay')->willReturn($data);
    }

    private function mockNoBookedBlocks(): void
    {
        $this->appointmentRepo->method('findBookedBlocks')->willReturn([]);
    }
}
