<?php declare(strict_types=1);
namespace ERTAppointment\Domain\Provider;

interface ProviderRepository {

	/** @return list<Provider> */
	public function findAll( bool $activeOnly = true ): array;
	/** @return list<Provider> */
	public function findByDepartment( ?int $departmentId, bool $activeOnly = true ): array;
	public function findById( int $id ): ?Provider;
	public function save( Provider $provider ): Provider;
	public function delete( int $id ): bool;
	/** @return list<int> WP user IDs */
	public function findUserIds( int $providerId ): array;
	public function assignUser( int $providerId, int $userId, string $role = 'staff' ): void;
	public function removeUser( int $providerId, int $userId ): void;
}
