<?php declare(strict_types=1);
namespace ERTAppointment\Infrastructure\Repositories;

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
		$table = $this->tableSql();

		if ( $activeOnly ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE status = %s ORDER BY sort_order, name', 'active' ),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( 'SELECT * FROM ' . $table . ' ORDER BY sort_order, name', ARRAY_A );
		}

		return array_map( fn( $r ) => Provider::fromRow( $r ), $rows );
	}

	public function findByDepartment( ?int $departmentId, bool $activeOnly = true ): array {
		global $wpdb;
		$table      = $this->tableSql();
		$conditions = array();
		$params     = array();

		if ( $activeOnly ) {
			$conditions[] = 'status = %s';
			$params[]     = 'active';
		}

		if ( $departmentId !== null ) {
			$conditions[] = 'department_id = %d';
			$params[]     = $departmentId;
		} else {
			$conditions[] = 'department_id IS NULL';
		}

		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $conditions ) . ' ORDER BY sort_order, name';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		return array_map( fn( $r ) => Provider::fromRow( $r ), $rows );
	}

	public function findById( int $id ): ?Provider {
		global $wpdb;
		$table = $this->tableSql();
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d', $id ), ARRAY_A );
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
		$table = $this->usersTableSql();
		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare( 'SELECT user_id FROM ' . $table . ' WHERE provider_id = %d', $providerId )
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
