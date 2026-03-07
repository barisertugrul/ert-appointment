<?php

declare(strict_types=1);

namespace ERTAppointment\Infrastructure\Repositories;

use ERTAppointment\Domain\Department\DepartmentRepository;

/**
 * WordPress / wpdb implementation of DepartmentRepository.
 */
final class ERTDepartmentRepository implements DepartmentRepository {

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'erta_departments';
	}

	private function tableSql(): string {
		return esc_sql( $this->table() );
	}

	public function findAll( bool $activeOnly = true ): array {
		global $wpdb;
		$table = $this->tableSql();
		$sql   = 'SELECT * FROM ' . $table . ' ORDER BY sort_order, name';

		if ( $activeOnly ) {
			$sql = 'SELECT * FROM ' . $table . ' WHERE status = %s ORDER BY sort_order, name';
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, 'active' ), ARRAY_A );
		} else {
			$rows = $wpdb->get_results( $sql, ARRAY_A );
		}

		return array_map( fn( $r ) => \ERTAppointment\Domain\Department\Department::fromRow( $r ), $rows );
	}

	public function findById( int $id ): ?\ERTAppointment\Domain\Department\Department {
		global $wpdb;
		$table = $this->tableSql();
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $table . ' WHERE id = %d', $id ), ARRAY_A );
		return $row ? \ERTAppointment\Domain\Department\Department::fromRow( $row ) : null;
	}

	public function save( \ERTAppointment\Domain\Department\Department $department ): \ERTAppointment\Domain\Department\Department {
		global $wpdb;
		$data = $department->toArray();
		if ( $department->id === null ) {
			$wpdb->insert( $this->table(), $data );
			return $this->findById( (int) $wpdb->insert_id );
		}
		$wpdb->update( $this->table(), $data, array( 'id' => $department->id ) );
		return $this->findById( $department->id );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => $id ) );
	}
}
