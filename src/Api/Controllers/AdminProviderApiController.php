<?php

declare(strict_types=1);

namespace ERTAppointment\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use ERTAppointment\Domain\Provider\Provider;
use ERTAppointment\Domain\Provider\ProviderRepository;

/**
 * Admin REST endpoints for provider management.
 *
 * Routes:
 *  GET    /erta/v1/admin/providers
 *  POST   /erta/v1/admin/providers
 *  PUT    /erta/v1/admin/providers/{id}
 *  DELETE /erta/v1/admin/providers/{id}
 *  GET    /erta/v1/admin/providers/{id}/users        — list assigned WP users
 *  POST   /erta/v1/admin/providers/{id}/users        — assign user { user_id, role }
 *  DELETE /erta/v1/admin/providers/{id}/users/{uid}  — remove user
 */
final class AdminProviderApiController {

	public function __construct(
		private readonly ProviderRepository $providers
	) {}

	// ── List ──────────────────────────────────────────────────────────────

	public function index( WP_REST_Request $request ): WP_REST_Response {
		$departmentId = $request->get_param( 'department_id' )
			? (int) $request->get_param( 'department_id' )
			: null;

		$items = $departmentId
			? $this->providers->findByDepartment( $departmentId, false )
			: $this->providers->findAll( false );

		return new WP_REST_Response(
			array(
				'items' => array_map( fn( Provider $p ) => $this->providerRow( $p ), $items ),
				'total' => count( $items ),
			)
		);
	}

	// ── Create ────────────────────────────────────────────────────────────

	public function create( WP_REST_Request $request ): WP_REST_Response {
		$data   = $this->extractFields( $request );
		$errors = $this->validate( $data );

		if ( $errors ) {
			return new WP_REST_Response( array( 'error' => implode( ' ', $errors ) ), 422 );
		}

		$provider = Provider::create(
			departmentId: $data['department_id'],
			type:         $data['type'],
			name:         $data['name'],
			email:        $data['email'],
			phone:        $data['phone'],
			description:  $data['description'],
			status:       $data['status'],
			sortOrder:    $data['sort_order'],
		);

		$saved = $this->providers->save( $provider );

		// Auto-assign a WP user if provided.
		if ( ! empty( $data['user_id'] ) ) {
			$this->providers->assignUser( $saved->id, (int) $data['user_id'], 'manager' );
		}

		return new WP_REST_Response( $this->providerRow( $saved ), 201 );
	}

	// ── Update ────────────────────────────────────────────────────────────

	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$provider = $this->providers->findById( $id );

		if ( ! $provider ) {
			return new WP_REST_Response( array( 'error' => __( 'Provider not found.', 'ert-appointment' ) ), 404 );
		}

		$data   = $this->extractFields( $request );
		$errors = $this->validate( $data );

		if ( $errors ) {
			return new WP_REST_Response( array( 'error' => implode( ' ', $errors ) ), 422 );
		}

		$updated = $provider->with(
			array(
				'department_id' => $data['department_id'] ?? $provider->departmentId,
				'type'          => $data['type'] ?? $provider->type,
				'name'          => $data['name'] ?? $provider->name,
				'email'         => $data['email'] ?? $provider->email,
				'phone'         => $data['phone'] ?? $provider->phone,
				'description'   => $data['description'] ?? $provider->description,
				'status'        => $data['status'] ?? $provider->status,
				'sort_order'    => $data['sort_order'] ?? $provider->sortOrder,
			)
		);

		$saved = $this->providers->save( $updated );

		return new WP_REST_Response( $this->providerRow( $saved ) );
	}

	// ── Delete (soft) ─────────────────────────────────────────────────────

	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$provider = $this->providers->findById( $id );

		if ( ! $provider ) {
			return new WP_REST_Response( array( 'error' => __( 'Provider not found.', 'ert-appointment' ) ), 404 );
		}

		// Check for upcoming confirmed appointments.
		if ( $this->hasUpcomingAppointments( $id ) ) {
			return new WP_REST_Response(
				array(
					'error' => 'Provider has upcoming appointments. Cancel or reassign them before deleting.',
				),
				409
			);
		}

		$this->providers->delete( $id );

		return new WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	// ── User assignments ──────────────────────────────────────────────────

	public function listUsers( WP_REST_Request $request ): WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$users = $this->getAssignedUsers( $id );

		return new WP_REST_Response( array( 'items' => $users ) );
	}

	public function assignUser( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$userId = (int) ( $request->get_param( 'user_id' ) ?? 0 );
		$role   = sanitize_key( $request->get_param( 'role' ) ?? 'staff' );

		if ( ! $userId || ! get_user_by( 'id', $userId ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Invalid user_id.', 'ert-appointment' ) ), 422 );
		}

		$allowedRoles = array( 'manager', 'staff' );
		if ( ! in_array( $role, $allowedRoles, true ) ) {
			return new WP_REST_Response( array( 'error' => 'role must be manager or staff.' ), 422 );
		}

		$this->providers->assignUser( $id, $userId, $role );

		return new WP_REST_Response(
			array(
				'assigned' => true,
				'user_id'  => $userId,
				'role'     => $role,
			),
			201
		);
	}

	public function removeUser( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$userId = (int) $request->get_param( 'user_id' );

		$this->providers->removeUser( $id, $userId );

		return new WP_REST_Response( array( 'removed' => true ) );
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	private function extractFields( WP_REST_Request $request ): array {
		return array(
			'department_id' => (int) ( $request->get_param( 'department_id' ) ?? 0 ) ?: null,
			'type'          => sanitize_key( $request->get_param( 'type' ) ?? 'individual' ),
			'name'          => sanitize_text_field( $request->get_param( 'name' ) ?? '' ),
			'email'         => sanitize_email( $request->get_param( 'email' ) ?? '' ),
			'phone'         => sanitize_text_field( $request->get_param( 'phone' ) ?? '' ),
			'description'   => sanitize_textarea_field( $request->get_param( 'description' ) ?? '' ),
			'status'        => sanitize_key( $request->get_param( 'status' ) ?? 'active' ),
			'sort_order'    => (int) ( $request->get_param( 'sort_order' ) ?? 0 ),
			'user_id'       => (int) ( $request->get_param( 'user_id' ) ?? 0 ),
		);
	}

	private function validate( array $data ): array {
		$errors = array();

		if ( empty( $data['name'] ) ) {
			$errors[] = __( 'Name is required.', 'ert-appointment' );
		}

		if ( ! in_array( $data['type'], array( 'individual', 'unit' ), true ) ) {
			$errors[] = 'type must be individual or unit.';
		}

		if ( ! in_array( $data['status'], array( 'active', 'inactive' ), true ) ) {
			$errors[] = __( 'Invalid status.', 'ert-appointment' );
		}

		if ( $data['type'] === 'unit' && empty( $data['department_id'] ) ) {
			$errors[] = __( 'Department is required when type is unit.', 'ert-appointment' );
		}

		if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
			$errors[] = __( 'Invalid email address.', 'ert-appointment' );
		}

		return $errors;
	}

	private function providerRow( Provider $p ): array {
		$row = array_merge(
			array( 'id' => $p->id ),
			$p->toArray()
		);

		// Attach department name for display.
		if ( $p->departmentId ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional lookup on plugin table.
			$row['department_name'] = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT name FROM {$wpdb->prefix}erta_departments WHERE id = %d",
					$p->departmentId
				)
			);
		}

		return $row;
	}

	private function getAssignedUsers( int $providerId ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional join for provider-user mapping.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pu.user_id, pu.role, u.display_name, u.user_email
             FROM {$wpdb->prefix}erta_provider_users pu
             JOIN {$wpdb->users} u ON u.ID = pu.user_id
             WHERE pu.provider_id = %d
             ORDER BY pu.role ASC, u.display_name ASC",
				$providerId
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	private function hasUpcomingAppointments( int $providerId ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional count query for safe delete guard.
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}erta_appointments
             WHERE provider_id = %d
               AND status IN ('pending','confirmed')
               AND start_datetime > NOW()",
				$providerId
			)
		);
	}
}
