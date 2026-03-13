<?php

declare(strict_types=1);

namespace ERTAppointment\Infrastructure\Repositories;

use function esc_sql;
use function sanitize_key;
use function esc_html__;
use const ARRAY_A;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- repository performs intentional reads on plugin-owned custom tables.

use DateTimeImmutable;
use RuntimeException;
use ERTAppointment\Domain\Appointment\Appointment;
use ERTAppointment\Domain\Appointment\AppointmentRepository;
use ERTAppointment\Domain\Appointment\AppointmentStatus;

/**
 * WordPress / wpdb implementation of AppointmentRepository.
 */
final class ERTAppointmentRepository implements AppointmentRepository {

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'erta_appointments';
	}

	private function tableSql(): string {
		return esc_sql( $this->table() );
	}

	// -------------------------------------------------------------------------
	// Reads
	// -------------------------------------------------------------------------

	public function findById( int $id ): ?Appointment {
		global $wpdb;
		$table = $this->table();

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ),
			ARRAY_A
		);

		return $row ? Appointment::fromRow( $row ) : null;
	}

	public function findOrFail( int $id ): Appointment {
		$appointment = $this->findById( $id );

		if ( $appointment === null ) {
			throw new RuntimeException( esc_html__( 'Appointment not found.', 'ert-appointment' ) );
		}

		return $appointment;
	}

	public function findByProvider(
		int $providerId,
		DateTimeImmutable $from,
		DateTimeImmutable $to
	): array {
		global $wpdb;
		$table = $this->table();

				$rows = $wpdb->get_results(
						$wpdb->prepare(
								'SELECT * FROM %i
								 WHERE provider_id = %d
									 AND start_datetime BETWEEN %s AND %s
								 ORDER BY start_datetime ASC',
								$table,
								$providerId,
								$from->format( 'Y-m-d H:i:s' ),
								$to->format( 'Y-m-d H:i:s' )
						),
						ARRAY_A
				);

		return array_map( fn( $row ) => Appointment::fromRow( $row ), $rows );
	}

	public function findBookedBlocks( int $providerId, DateTimeImmutable $date ): array {
		global $wpdb;

		$table   = $this->table();
		$dateStr = $date->format( 'Y-m-d' );

				$rows = $wpdb->get_results(
						$wpdb->prepare(
								'SELECT start_datetime, end_datetime FROM %i
								 WHERE provider_id = %d
									 AND DATE(start_datetime) = %s
									 AND status NOT IN (\'cancelled\', \'rescheduled\', \'no_show\')
								 ORDER BY start_datetime ASC',
								$table,
								$providerId,
								$dateStr
						),
						ARRAY_A
				);

		return array_map(
			fn( $row ) => array(
				'start' => new DateTimeImmutable( $row['start_datetime'] ),
				'end'   => new DateTimeImmutable( $row['end_datetime'] ),
			),
			$rows
		);
	}

	public function findByCustomerEmail( string $email, ?array $statuses = null ): array {
		global $wpdb;

		$table = $this->table();

		if ( $statuses === null || count( $statuses ) === 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE customer_email = %s ORDER BY start_datetime DESC',
					$table,
					$email
				),
				ARRAY_A
			);
		} else {
			$normalized = array_values(
				array_map(
					fn( $status ) => $status instanceof AppointmentStatus ? $status->value : sanitize_key( (string) $status ),
					$statuses
				)
			);

			if ( count( $normalized ) === 1 ) {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE customer_email = %s AND status = %s ORDER BY start_datetime DESC',
						$table,
						$email,
						$normalized[0]
					),
					ARRAY_A
				);
			} elseif ( count( $normalized ) === 2 ) {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE customer_email = %s AND status IN (%s, %s) ORDER BY start_datetime DESC',
						$table,
						$email,
						$normalized[0],
						$normalized[1]
					),
					ARRAY_A
				);
			} else {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE customer_email = %s AND status IN (%s, %s, %s) ORDER BY start_datetime DESC',
						$table,
						$email,
						$normalized[0],
						$normalized[1],
						$normalized[2]
					),
					ARRAY_A
				);
			}
		}

		return array_map( fn( $row ) => Appointment::fromRow( $row ), $rows );
	}

	public function findUpcoming( int $limit = 50 ): array {
		global $wpdb;
		$table = $this->table();

		$now = current_time( 'mysql' );

				$rows = $wpdb->get_results(
						$wpdb->prepare(
								'SELECT * FROM %i
								 WHERE start_datetime > %s
									 AND status IN (\'pending\', \'confirmed\')
								 ORDER BY start_datetime ASC
								 LIMIT %d',
								$table,
								$now,
								$limit
						),
						ARRAY_A
				);

		return array_map( fn( $row ) => Appointment::fromRow( $row ), $rows );
	}

	public function countByStatus( DateTimeImmutable $from, DateTimeImmutable $to ): array {
		global $wpdb;
		$table = $this->table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) AS cnt
				 FROM %i
				 WHERE start_datetime BETWEEN %s AND %s
				 GROUP BY status',
				$table,
				$from->format( 'Y-m-d H:i:s' ),
				$to->format( 'Y-m-d H:i:s' )
			),
			ARRAY_A
		);

		$result = array();
		foreach ( $rows as $row ) {
			$result[ $row['status'] ] = (int) $row['cnt'];
		}

		return $result;
	}

	public function paginate( int $page, int $perPage, array $filters = array() ): array {
		global $wpdb;
		$table  = $this->table();
		$offset = ( $page - 1 ) * $perPage;

		$providerId   = (int) ( $filters['provider_id'] ?? 0 );
		$departmentId = (int) ( $filters['department_id'] ?? 0 );
		$statusList   = array_values( array_map( 'sanitize_key', (array) ( $filters['status'] ?? array() ) ) );
		$dateFrom     = (string) ( $filters['date_from'] ?? '' );
		$dateTo       = (string) ( $filters['date_to'] ?? '' );
		$search       = (string) ( $filters['search'] ?? '' );
		$orderByRaw   = (string) ( $filters['order_by'] ?? 'start_datetime' );
		$orderRaw     = (string) ( $filters['order'] ?? 'desc' );

		$like = $search ? '%' . $wpdb->esc_like( $search ) . '%' : '';

		$statusList = array_slice( $statusList, 0, 3 );
		while ( count( $statusList ) < 3 ) {
			$statusList[] = '';
		}
		list( $status1, $status2, $status3 ) = $statusList;

		$allowedOrderBy = array( 'start_datetime', 'created_at', 'id' );
		$orderBy        = in_array( $orderByRaw, $allowedOrderBy, true ) ? $orderByRaw : 'start_datetime';
		$orderDir       = strtoupper( $orderRaw ) === 'ASC' ? 'ASC' : 'DESC';

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i
				WHERE (%d = 0 OR provider_id = %d)
				AND (%d = 0 OR department_id = %d)
				AND (%s = %s OR status = %s OR status = %s OR status = %s)
				AND (%s = %s OR start_datetime >= %s)
				AND (%s = %s OR start_datetime <= %s)
				AND (%s = %s OR customer_name LIKE %s OR customer_email LIKE %s)",
				$table,
				$providerId, $providerId,
				$departmentId, $departmentId,
				$status1, '', $status1, $status2, $status3,
				$dateFrom, '', $dateFrom,
				$dateTo, '', $dateTo,
				$search, '', $like, $like
			)
		);

		if ( $orderBy === 'start_datetime' && $orderDir === 'ASC' ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i
					WHERE (%d = 0 OR provider_id = %d)
					AND (%d = 0 OR department_id = %d)
					AND (%s = %s OR status = %s OR status = %s OR status = %s)
					AND (%s = %s OR start_datetime >= %s)
					AND (%s = %s OR start_datetime <= %s)
					AND (%s = %s OR customer_name LIKE %s OR customer_email LIKE %s)
					ORDER BY start_datetime ASC
					LIMIT %d OFFSET %d",
					$table,
					$providerId, $providerId,
					$departmentId, $departmentId,
					$status1, '', $status1, $status2, $status3,
					$dateFrom, '', $dateFrom,
					$dateTo, '', $dateTo,
					$search, '', $like, $like,
					$perPage, $offset
				),
				ARRAY_A
			);
		} elseif ( $orderBy === 'start_datetime' && $orderDir === 'DESC' ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i
					WHERE (%d = 0 OR provider_id = %d)
					AND (%d = 0 OR department_id = %d)
					AND (%s = %s OR status = %s OR status = %s OR status = %s)
					AND (%s = %s OR start_datetime >= %s)
					AND (%s = %s OR start_datetime <= %s)
					AND (%s = %s OR customer_name LIKE %s OR customer_email LIKE %s)
					ORDER BY start_datetime DESC
					LIMIT %d OFFSET %d",
					$table,
					$providerId, $providerId,
					$departmentId, $departmentId,
					$status1, '', $status1, $status2, $status3,
					$dateFrom, '', $dateFrom,
					$dateTo, '', $dateTo,
					$search, '', $like, $like,
					$perPage, $offset
				),
				ARRAY_A
			);
		} elseif ( $orderBy === 'created_at' && $orderDir === 'ASC' ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i
					WHERE (%d = 0 OR provider_id = %d)
					AND (%d = 0 OR department_id = %d)
					AND (%s = %s OR status = %s OR status = %s OR status = %s)
					AND (%s = %s OR start_datetime >= %s)
					AND (%s = %s OR start_datetime <= %s)
					AND (%s = %s OR customer_name LIKE %s OR customer_email LIKE %s)
					ORDER BY created_at ASC
					LIMIT %d OFFSET %d",
					$table,
					$providerId, $providerId,
					$departmentId, $departmentId,
					$status1, '', $status1, $status2, $status3,
					$dateFrom, '', $dateFrom,
					$dateTo, '', $dateTo,
					$search, '', $like, $like,
					$perPage, $offset
				),
				ARRAY_A
			);
		} elseif ( $orderBy === 'created_at' && $orderDir === 'DESC' ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i
					WHERE (%d = 0 OR provider_id = %d)
					AND (%d = 0 OR department_id = %d)
					AND (%s = %s OR status = %s OR status = %s OR status = %s)
					AND (%s = %s OR start_datetime >= %s)
					AND (%s = %s OR start_datetime <= %s)
					AND (%s = %s OR customer_name LIKE %s OR customer_email LIKE %s)
					ORDER BY created_at DESC
					LIMIT %d OFFSET %d",
					$table,
					$providerId, $providerId,
					$departmentId, $departmentId,
					$status1, '', $status1, $status2, $status3,
					$dateFrom, '', $dateFrom,
					$dateTo, '', $dateTo,
					$search, '', $like, $like,
					$perPage, $offset
				),
				ARRAY_A
			);
		} elseif ( $orderBy === 'id' && $orderDir === 'ASC' ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i
					WHERE (%d = 0 OR provider_id = %d)
					AND (%d = 0 OR department_id = %d)
					AND (%s = %s OR status = %s OR status = %s OR status = %s)
					AND (%s = %s OR start_datetime >= %s)
					AND (%s = %s OR start_datetime <= %s)
					AND (%s = %s OR customer_name LIKE %s OR customer_email LIKE %s)
					ORDER BY id ASC
					LIMIT %d OFFSET %d",
					$table,
					$providerId, $providerId,
					$departmentId, $departmentId,
					$status1, '', $status1, $status2, $status3,
					$dateFrom, '', $dateFrom,
					$dateTo, '', $dateTo,
					$search, '', $like, $like,
					$perPage, $offset
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i
					WHERE (%d = 0 OR provider_id = %d)
					AND (%d = 0 OR department_id = %d)
					AND (%s = %s OR status = %s OR status = %s OR status = %s)
					AND (%s = %s OR start_datetime >= %s)
					AND (%s = %s OR start_datetime <= %s)
					AND (%s = %s OR customer_name LIKE %s OR customer_email LIKE %s)
					ORDER BY id DESC
					LIMIT %d OFFSET %d",
					$table,
					$providerId, $providerId,
					$departmentId, $departmentId,
					$status1, '', $status1, $status2, $status3,
					$dateFrom, '', $dateFrom,
					$dateTo, '', $dateTo,
					$search, '', $like, $like,
					$perPage, $offset
				),
				ARRAY_A
			);
		}

		return array(
			'items' => array_map( fn( $row ) => Appointment::fromRow( $row ), $rows ),
			'total' => $total,
		);
	}

	// -------------------------------------------------------------------------
	// Writes
	// -------------------------------------------------------------------------

	public function save( Appointment $appointment ): Appointment {
		global $wpdb;
		$data = $appointment->toArray();

		if ( $appointment->id === null ) {
			$wpdb->insert( $this->table(), $data );
			$id = (int) $wpdb->insert_id;

			if ( $id === 0 ) {
				throw new RuntimeException(
					esc_html__( 'Failed to save appointment to database.', 'ert-appointment' )
				);
			}

			return $this->findOrFail( $id );
		}

		$wpdb->update( $this->table(), $data, array( 'id' => $appointment->id ) );

		return $this->findOrFail( $appointment->id );
	}

	public function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $this->table(), array( 'id' => $id ) );
	}

	// -------------------------------------------------------------------------
	// Query builder helpers
	// -------------------------------------------------------------------------

}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
