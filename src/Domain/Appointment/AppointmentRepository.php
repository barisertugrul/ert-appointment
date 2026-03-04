<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Appointment;

use DateTimeImmutable;

/**
 * Contract for appointment persistence.
 * The concrete implementation lives in the Infrastructure layer.
 */
interface AppointmentRepository
{
    /**
     * Finds an appointment by its ID.
     * Returns null if not found.
     */
    public function findById(int $id): ?Appointment;

    /**
     * Finds an appointment or throws if not found.
     *
     * @throws \RuntimeException
     */
    public function findOrFail(int $id): Appointment;

    /**
     * Returns all appointments for a provider within a date range.
     *
     * @return list<Appointment>
     */
    public function findByProvider(
        int $providerId,
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): array;

    /**
     * Returns booked time blocks for a provider on a given date (for slot calculation).
     *
     * @return list<array{start: DateTimeImmutable, end: DateTimeImmutable}>
     */
    public function findBookedBlocks(int $providerId, DateTimeImmutable $date): array;

    /**
     * Returns appointments for a customer email (all time).
     *
     * @param AppointmentStatus[]|null $statuses  Filter by statuses; null = all.
     * @return list<Appointment>
     */
    public function findByCustomerEmail(string $email, ?array $statuses = null): array;

    /**
     * Returns upcoming appointments across all providers (for admin dashboard).
     *
     * @return list<Appointment>
     */
    public function findUpcoming(int $limit = 50): array;

    /**
     * Saves (insert or update) an appointment.
     * Returns the appointment with its database ID populated.
     */
    public function save(Appointment $appointment): Appointment;

    /**
     * Deletes an appointment permanently (use with caution — prefer status changes).
     */
    public function delete(int $id): bool;

    /**
     * Counts appointments grouped by status for a given date range.
     *
     * @return array<string, int>  ['pending' => 3, 'confirmed' => 12, ...]
     */
    public function countByStatus(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Paginated list with optional filters — used by admin appointment list page.
     *
     * @param array<string, mixed> $filters
     * @return array{items: list<Appointment>, total: int}
     */
    public function paginate(int $page, int $perPage, array $filters = []): array;
}
