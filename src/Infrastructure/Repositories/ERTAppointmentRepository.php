<?php

declare(strict_types=1);

namespace ERTAppointment\Infrastructure\Repositories;

use DateTimeImmutable;
use RuntimeException;
use ERTAppointment\Domain\Appointment\Appointment;
use ERTAppointment\Domain\Appointment\AppointmentRepository;
use ERTAppointment\Domain\Appointment\AppointmentStatus;

/**
 * WordPress / wpdb implementation of AppointmentRepository.
 */
final class ERTAppointmentRepository implements AppointmentRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'erta_appointments';
    }

    // -------------------------------------------------------------------------
    // Reads
    // -------------------------------------------------------------------------

    public function findById(int $id): ?Appointment
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ? Appointment::fromRow($row) : null;
    }

    public function findOrFail(int $id): Appointment
    {
        $appointment = $this->findById($id);

        if ($appointment === null) {
            throw new RuntimeException(
                sprintf(__('Appointment #%d not found.', 'ert-appointment'), $id)
            );
        }

        return $appointment;
    }

    public function findByProvider(
        int $providerId,
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): array {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()}
                 WHERE provider_id = %d
                   AND start_datetime BETWEEN %s AND %s
                 ORDER BY start_datetime ASC",
                $providerId,
                $from->format('Y-m-d H:i:s'),
                $to->format('Y-m-d H:i:s')
            ),
            ARRAY_A
        );

        return array_map(fn($row) => Appointment::fromRow($row), $rows);
    }

    public function findBookedBlocks(int $providerId, DateTimeImmutable $date): array
    {
        global $wpdb;

        $dateStr = $date->format('Y-m-d');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT start_datetime, end_datetime FROM {$this->table()}
                 WHERE provider_id = %d
                   AND DATE(start_datetime) = %s
                   AND status NOT IN ('cancelled', 'rescheduled', 'no_show')
                 ORDER BY start_datetime ASC",
                $providerId,
                $dateStr
            ),
            ARRAY_A
        );

        return array_map(
            fn($row) => [
                'start' => new DateTimeImmutable($row['start_datetime']),
                'end'   => new DateTimeImmutable($row['end_datetime']),
            ],
            $rows
        );
    }

    public function findByCustomerEmail(string $email, ?array $statuses = null): array
    {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table()} WHERE customer_email = %s";
        $params = [$email];

        if ($statuses !== null && count($statuses) > 0) {
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $sql .= " AND status IN ({$placeholders})";
            $params = array_merge($params, array_map(fn($s) => $s->value, $statuses));
        }

        $sql .= ' ORDER BY start_datetime DESC';

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return array_map(fn($row) => Appointment::fromRow($row), $rows);
    }

    public function findUpcoming(int $limit = 50): array
    {
        global $wpdb;

        $now = current_time('mysql');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table()}
                 WHERE start_datetime > %s
                   AND status IN ('pending', 'confirmed')
                 ORDER BY start_datetime ASC
                 LIMIT %d",
                $now,
                $limit
            ),
            ARRAY_A
        );

        return array_map(fn($row) => Appointment::fromRow($row), $rows);
    }

    public function countByStatus(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) AS cnt
                 FROM {$this->table()}
                 WHERE start_datetime BETWEEN %s AND %s
                 GROUP BY status",
                $from->format('Y-m-d H:i:s'),
                $to->format('Y-m-d H:i:s')
            ),
            ARRAY_A
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['cnt'];
        }

        return $result;
    }

    public function paginate(int $page, int $perPage, array $filters = []): array
    {
        global $wpdb;
        $table  = $this->table();
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->buildWhere($filters);

        $total = (int) $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} {$where}", ...$params)
        );

        $rows = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY start_datetime DESC LIMIT %d OFFSET %d",
                ...[...$params, $perPage, $offset]
            ),
            ARRAY_A
        );

        return [
            'items' => array_map(fn($row) => Appointment::fromRow($row), $rows),
            'total' => $total,
        ];
    }

    // -------------------------------------------------------------------------
    // Writes
    // -------------------------------------------------------------------------

    public function save(Appointment $appointment): Appointment
    {
        global $wpdb;
        $data = $appointment->toArray();

        if ($appointment->id === null) {
            $wpdb->insert($this->table(), $data);
            $id = (int) $wpdb->insert_id;

            if ($id === 0) {
                throw new RuntimeException(
                    __('Failed to save appointment to database.', 'ert-appointment')
                );
            }

            return $this->findOrFail($id);
        }

        $wpdb->update($this->table(), $data, ['id' => $appointment->id]);

        return $this->findOrFail($appointment->id);
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        return (bool) $wpdb->delete($this->table(), ['id' => $id]);
    }

    // -------------------------------------------------------------------------
    // Query builder helpers
    // -------------------------------------------------------------------------

    /**
     * Builds a WHERE clause from a filter map.
     *
     * @param array<string, mixed> $filters
     * @return array{string, list<mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = ['1=1'];
        $params     = [];

        if (! empty($filters['provider_id'])) {
            $conditions[] = 'provider_id = %d';
            $params[]     = (int) $filters['provider_id'];
        }

        if (! empty($filters['department_id'])) {
            $conditions[] = 'department_id = %d';
            $params[]     = (int) $filters['department_id'];
        }

        if (! empty($filters['status'])) {
            $statuses = (array) $filters['status'];
            $pls      = implode(',', array_fill(0, count($statuses), '%s'));
            $conditions[] = "status IN ({$pls})";
            $params = array_merge($params, $statuses);
        }

        if (! empty($filters['date_from'])) {
            $conditions[] = 'start_datetime >= %s';
            $params[]     = $filters['date_from'];
        }

        if (! empty($filters['date_to'])) {
            $conditions[] = 'start_datetime <= %s';
            $params[]     = $filters['date_to'];
        }

        if (! empty($filters['search'])) {
            $conditions[] = '(customer_name LIKE %s OR customer_email LIKE %s)';
            $like         = '%' . $GLOBALS['wpdb']->esc_like($filters['search']) . '%';
            $params[]     = $like;
            $params[]     = $like;
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
    }
}
