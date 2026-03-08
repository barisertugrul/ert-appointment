<?php declare(strict_types=1);
namespace ERTAppointment\Infrastructure\Repositories;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- repository performs intentional reads on plugin-owned custom tables.

use ERTAppointment\Domain\Provider\Provider;
use ERTAppointment\Domain\Provider\ProviderRepository;

final class ERTProviderRepository implements ProviderRepository {

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'erta_providers';
	}

	private function tableSql(): string {
		return esc_sql( $this->table() );
	}

	private function usersTable(): string {
		global $wpdb;
		return $wpdb->prefix . 'erta_provider_users';
	}

	private function usersTableSql(): string {
		return esc_sql( $this->usersTable() );
	}

	public function findAll( bool $activeOnly = true ): array {
		global $wpdb;
		$table = $this->table();

		if ( $activeOnly ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY sort_order, name', $table, 'active' ),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY sort_order, name', $table ), ARRAY_A );
		}

		return array_map( fn( $r ) => Provider::fromRow( $r ), $rows );
	}

	public function findByDepartment( ?int $departmentId, bool $activeOnly = true ): array {
		global $wpdb;
		$table = $this->table();

		if ( $departmentId !== null && $activeOnly ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s AND department_id = %d ORDER BY sort_order, name',
					$table,
					'active',
					$departmentId
				),
				ARRAY_A
			);
		} elseif ( $departmentId !== null ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE department_id = %d ORDER BY sort_order, name',
					$table,
					$departmentId
				),
				ARRAY_A
			);
		} elseif ( $activeOnly ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s AND department_id IS NULL ORDER BY sort_order, name',
					$table,
					'active'
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE department_id IS NULL ORDER BY sort_order, name', $table ),
				ARRAY_A
			);
		}

		return array_map( fn( $r ) => Provider::fromRow( $r ), $rows );
	}

	public function findById( int $id ): ?Provider {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ), ARRAY_A );
		return $row ? Provider::fromRow( $row ) : null;
	}

	public function save( Provider $provider ): Provider {
		global $wpdb;
		$data = $provider->toArray();
		if ( $provider->id === null ) {
			$wpdb->insert( $this->table(), $data );
			return $this->findById( (int) $wpdb->insert_id );
		}
		$wpdb->update( $this->table(), $data, array( 'id' => $provider->id ) );
		return $this->findById( $provider->id );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => $id ) );
	}

	public function findUserIds( int $providerId ): array {
		global $wpdb;
		$table = $this->usersTable();
		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare( 'SELECT user_id FROM %i WHERE provider_id = %d', $table, $providerId )
			)
		);
	}

	public function assignUser( int $providerId, int $userId, string $role = 'staff' ): void {
		global $wpdb;
		$wpdb->replace(
			$this->usersTable(),
			array(
				'provider_id' => $providerId,
				'user_id'     => $userId,
				'role'        => $role,
			)
		);
		// Ensure the WP user has the provider role/cap.
		$user = get_userdata( $userId );
		if ( $user ) {
			$user->add_cap( 'erta_view_appointments' );
		}
	}

	public function removeUser( int $providerId, int $userId ): void {
		global $wpdb;
		$wpdb->delete(
			$this->usersTable(),
			array(
				'provider_id' => $providerId,
				'user_id'     => $userId,
			)
		);
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
