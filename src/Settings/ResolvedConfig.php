<?php

declare(strict_types=1);

namespace ERTAppointment\Settings;

/**
 * Immutable value object representing the fully-merged settings for a provider.
 *
 * Consumers read settings through this object rather than querying the
 * SettingsManager directly, keeping domain code free of infrastructure concerns.
 */
final class ResolvedConfig
{
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
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Returns a setting cast to int.
     */
    public function getInt(string $key, int $default = 0): int
    {
        return (int) ($this->settings[$key] ?? $default);
    }

    /**
     * Returns a setting cast to bool.
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->settings[$key] ?? $default;

        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 'true', 'yes'], true);
    }

    /**
     * Returns a setting cast to string.
     */
    public function getString(string $key, string $default = ''): string
    {
        return (string) ($this->settings[$key] ?? $default);
    }

    // -------------------------------------------------------------------------
    // Schedule helpers
    // -------------------------------------------------------------------------

    /**
     * Returns slot duration in minutes.
     */
    public function slotDuration(): int
    {
        return $this->getInt('slot_duration', 30);
    }

    /**
     * Returns the interval between slot starts in minutes.
     */
    public function slotInterval(): int
    {
        return $this->getInt('slot_interval', 30);
    }

    /**
     * Returns the required pre-appointment buffer in minutes.
     */
    public function bufferBefore(): int
    {
        return $this->getInt('buffer_before', 0);
    }

    /**
     * Returns the required post-appointment buffer in minutes.
     */
    public function bufferAfter(): int
    {
        return $this->getInt('buffer_after', 0);
    }

    /**
     * Returns the minimum advance notice required to book, in minutes.
     */
    public function minimumNotice(): int
    {
        return $this->getInt('minimum_notice', 60);
    }

    /**
     * Returns how many days in advance a booking can be made.
     */
    public function maximumAdvanceDays(): int
    {
        return $this->getInt('maximum_advance', 60);
    }

    /**
     * Returns the arrival buffer (how many minutes early the customer should arrive).
     */
    public function arrivalBuffer(): int
    {
        return $this->getInt('arrival_buffer', 0);
    }

    /**
     * Returns working hours for a given ISO day of week (1=Monday…7=Sunday).
     * Returns null if the day is not configured.
     */
    public function workingHoursForDay(int $dayOfWeek): ?array
    {
        $hours = $this->settings['default_working_hours'] ?? [];

        return $hours[$dayOfWeek] ?? null;
    }

    /**
     * Whether auto-confirmation is enabled.
     */
    public function autoConfirm(): bool
    {
        return $this->getBool('auto_confirm', true);
    }

    /**
     * Whether payment is required.
     */
    public function requiresPayment(): bool
    {
        return $this->getBool('require_payment', false);
    }

    /**
     * Price for this provider/department (if applicable).
     */
    public function price(): float
    {
        return (float) ($this->settings['price'] ?? 0.0);
    }

    /**
     * The resolved department ID, if any.
     */
    public function departmentId(): ?int
    {
        return $this->departmentId;
    }

    /**
     * Returns the raw settings array (for serialization / debugging).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->settings;
    }
}
