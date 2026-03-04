<?php declare(strict_types=1);
namespace ERTAppointment\Infrastructure\Repositories;

use ERTAppointment\Domain\Provider\Provider;
use ERTAppointment\Domain\Provider\ProviderRepository;

final class ERTProviderRepository implements ProviderRepository
{
    private function table(): string { global $wpdb; return $wpdb->prefix . 'erta_providers'; }
    private function usersTable(): string { global $wpdb; return $wpdb->prefix . 'erta_provider_users'; }

    public function findAll(bool $activeOnly = true): array
    {
        global $wpdb;
        $where = $activeOnly ? "WHERE status = 'active'" : '';
        $rows  = $wpdb->get_results("SELECT * FROM {$this->table()} {$where} ORDER BY sort_order, name", ARRAY_A);
        return array_map(fn($r) => Provider::fromRow($r), $rows);
    }

    public function findByDepartment(?int $departmentId, bool $activeOnly = true): array
    {
        global $wpdb;
        $conditions = $activeOnly ? ["status = 'active'"] : [];
        if ($departmentId !== null) {
            $conditions[] = $wpdb->prepare('department_id = %d', $departmentId);
        } else {
            $conditions[] = 'department_id IS NULL';
        }
        $where = 'WHERE ' . implode(' AND ', $conditions);
        $rows  = $wpdb->get_results("SELECT * FROM {$this->table()} {$where} ORDER BY sort_order, name", ARRAY_A);
        return array_map(fn($r) => Provider::fromRow($r), $rows);
    }

    public function findById(int $id): ?Provider
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id), ARRAY_A);
        return $row ? Provider::fromRow($row) : null;
    }

    public function save(Provider $provider): Provider
    {
        global $wpdb;
        $data = $provider->toArray();
        if ($provider->id === null) {
            $wpdb->insert($this->table(), $data);
            return $this->findById((int) $wpdb->insert_id);
        }
        $wpdb->update($this->table(), $data, ['id' => $provider->id]);
        return $this->findById($provider->id);
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        return (bool) $wpdb->delete($this->table(), ['id' => $id]);
    }

    public function findUserIds(int $providerId): array
    {
        global $wpdb;
        return array_map('intval', $wpdb->get_col(
            $wpdb->prepare("SELECT user_id FROM {$this->usersTable()} WHERE provider_id = %d", $providerId)
        ));
    }

    public function assignUser(int $providerId, int $userId, string $role = 'staff'): void
    {
        global $wpdb;
        $wpdb->replace($this->usersTable(), ['provider_id' => $providerId, 'user_id' => $userId, 'role' => $role]);
        // Ensure the WP user has the provider role/cap.
        $user = get_userdata($userId);
        if ($user) $user->add_cap('erta_view_appointments');
    }

    public function removeUser(int $providerId, int $userId): void
    {
        global $wpdb;
        $wpdb->delete($this->usersTable(), ['provider_id' => $providerId, 'user_id' => $userId]);
    }
}
