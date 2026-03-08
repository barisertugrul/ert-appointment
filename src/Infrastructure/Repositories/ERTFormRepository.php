<?php declare(strict_types=1);
namespace ERTAppointment\Infrastructure\Repositories;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- repository performs intentional reads on plugin-owned custom tables.

use ERTAppointment\Domain\Form\Form;
use ERTAppointment\Domain\Form\FormRepository;

final class ERTFormRepository implements FormRepository {

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'erta_forms';
	}

	private function tableSql(): string {
		return esc_sql( $this->table() );
	}

	public function findForScope( string $scope, ?int $scopeId ): ?Form {
		global $wpdb;
		$table = $this->table();

		// Priority: exact scope → fall back to global.
		if ( $scope !== 'global' && $scopeId !== null ) {
			$row = $wpdb->get_row(
				$wpdb->prepare( 'SELECT * FROM %i WHERE scope=%s AND scope_id=%d AND is_active=1 LIMIT 1', $table, $scope, $scopeId ),
				ARRAY_A
			);
			if ( $row ) {
				return Form::fromRow( $row );
			}
		}
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE scope=%s AND is_active=1 LIMIT 1', $table, 'global' ),
			ARRAY_A
		);
		return $row ? Form::fromRow( $row ) : null;
	}

	public function findById( int $id ): ?Form {
		global $wpdb;
		$table = $this->table();
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $table, $id ), ARRAY_A );
		return $row ? Form::fromRow( $row ) : null;
	}

	public function save( Form $form ): Form {
		global $wpdb;
		$data = $form->toArray();
		if ( $form->id === null ) {
			$wpdb->insert( $this->table(), $data );
			return $this->findById( (int) $wpdb->insert_id );
		}
		$wpdb->update( $this->table(), $data, array( 'id' => $form->id ) );
		return $this->findById( $form->id );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => $id ) );
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
