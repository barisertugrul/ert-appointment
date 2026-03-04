<?php declare(strict_types=1);
namespace ERTAppointment\Domain\Department;

interface DepartmentRepository
{
    /** @return list<Department> */
    public function findAll(bool $activeOnly = true): array;
    public function findById(int $id): ?Department;
    public function save(Department $department): Department;
    public function delete(int $id): bool;
}
