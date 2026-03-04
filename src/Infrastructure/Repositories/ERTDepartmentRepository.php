<?php

declare(strict_types=1);

namespace ERTAppointment\Infrastructure\Repositories;

use ERTAppointment\Domain\Department\DepartmentRepository;

/**
 * WordPress / wpdb implementation of DepartmentRepository.
 */
final class ERTDepartmentRepository implements DepartmentRepository
{
    private function table(): string { global $wpdb; return $wpdb->prefix . 'erta_departments'; }

    public function findAll(bool $activeOnly = true): array
    {
        global $wpdb;
        $where = $activeOnly ? "WHERE status = 'active'" : '';
        $rows  = $wpdb->get_results("SELECT * FROM {$this->table()} {$where} ORDER BY sort_order, name", ARRAY_A);
        return array_map(fn($r) => \ERTAppointment\Domain\Department\Department::fromRow($r), $rows);
    }

    public function findById(int $id): ?\ERTAppointment\Domain\Department\Department
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A);
        return $row ? \ERTAppointment\Domain\Department\Department::fromRow($row) : null;
    }

    public function save(\ERTAppointment\Domain\Department\Department $department): \ERTAppointment\Domain\Department\Department
    {
        global $wpdb;
        $data = $department->toArray();
        if ($department->id === null) {
            $wpdb->insert($this->table(), $data);
            return $this->findById((int) $wpdb->insert_id);
        }
        $wpdb->update($this->table(), $data, ['id' => $department->id]);
        return $this->findById($department->id);
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        return (bool) $wpdb->delete($this->table(), ['id' => $id]);
    }
}
