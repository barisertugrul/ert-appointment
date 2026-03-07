<?php

declare(strict_types=1);

namespace ERTAppointment\Api\Controllers;

use DateTimeImmutable;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ERTAppointment\Domain\Appointment\AppointmentService;
use ERTAppointment\Domain\Appointment\AppointmentRepository;
use ERTAppointment\Domain\Appointment\AppointmentStatus;
use ERTAppointment\Domain\Appointment\BookAppointmentDTO;
use ERTAppointment\Domain\Appointment\RescheduleDTO;
use ERTAppointment\Domain\Appointment\SlotNotAvailableException;
use ERTAppointment\Settings\SettingsManager;

/**
 * REST controller for appointment CRUD and lifecycle transitions.
 * Route prefix: /erta/v1/appointments
 */
final class AppointmentApiController {
	/**
	 * @var array<int, string>
	 */
	private array $providerNames = array();

	/**
	 * @var array<int, string>
	 */
	private array $departmentNames = array();

	public function __construct(
		private readonly AppointmentService $service,
		private readonly AppointmentRepository $repository,
		private readonly SettingsManager $settings,
	) {}

	// -------------------------------------------------------------------------
	// Public booking endpoint
	// -------------------------------------------------------------------------

	/**
	 * POST /erta/v1/appointments
	 * Creates a new appointment from the booking form submission.
	 */
	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params() ?: $request->get_body_params();

		// Basic field validation.
		$required = array( 'provider_id', 'start_datetime', 'customer_name', 'customer_email' );
		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new WP_Error(
					'erta_missing_field',
					/* translators: %s required field name */
					sprintf( __( 'Field "%s" is required.', 'ert-appointment' ), $field ),
					array( 'status' => 400 )
				);
			}
		}

		if ( ! is_email( $data['customer_email'] ) ) {
			return new WP_Error(
				'erta_invalid_email',
				__( 'Invalid email address.', 'ert-appointment' ),
				array( 'status' => 400 )
			);
		}

		try {
			$start = new DateTimeImmutable( sanitize_text_field( $data['start_datetime'] ) );
		} catch ( \Throwable ) {
			return new WP_Error(
				'erta_invalid_date',
				__( 'Invalid start_datetime format. Use ISO 8601 (e.g. 2024-04-01T09:00:00).', 'ert-appointment' ),
				array( 'status' => 400 )
			);
		}

		$providerId = (int) $data['provider_id'];
		$config     = $this->settings->resolveForProvider( $providerId );

		$dto = new BookAppointmentDTO(
			providerId:           $providerId,
			departmentId: ! empty( $data['department_id'] ) ? (int) $data['department_id'] : null,
			formId: ! empty( $data['form_id'] ) ? (int) $data['form_id'] : null,
			customerUserId:       get_current_user_id() ?: null,
			customerName:         sanitize_text_field( $data['customer_name'] ),
			customerEmail:        sanitize_email( $data['customer_email'] ),
			customerPhone: ! empty( $data['customer_phone'] ) ? sanitize_text_field( $data['customer_phone'] ) : null,
			startDatetime:        $start,
			durationMinutes:      $config->slotDuration(),
			price:                $config->price(),
			formData:             is_array( $data['form_data'] ?? null ) ? $data['form_data'] : array(),
			notes: ! empty( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
			arrivalBufferMinutes: $config->arrivalBuffer(),
		);

		try {
			$appointment = $this->service->book( $dto );
		} catch ( SlotNotAvailableException $e ) {
			return new WP_Error( 'erta_slot_unavailable', $e->getMessage(), array( 'status' => 409 ) );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'erta_booking_error', $e->getMessage(), array( 'status' => 500 ) );
		}

		$responseData = $this->formatAppointment( $appointment );

		// If payment is required, include the redirect URL in the response.
		if ( $appointment->paymentUrl !== null ) {
			$responseData['payment_url']      = $appointment->paymentUrl;
			$responseData['requires_payment'] = true;
		}

		return new WP_REST_Response( $responseData, 201 );
	}

	// -------------------------------------------------------------------------
	// Read
	// -------------------------------------------------------------------------

	public function get( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$appointment = $this->repository->findById( (int) $request->get_param( 'id' ) );

		if ( $appointment === null ) {
			return new WP_Error( 'erta_not_found', __( 'Appointment not found.', 'ert-appointment' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $this->formatAppointment( $appointment ) );
	}

	// -------------------------------------------------------------------------
	// Status transitions
	// -------------------------------------------------------------------------

	public function confirm( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$appointment = $this->service->confirm( (int) $request->get_param( 'id' ) );
			return new WP_REST_Response( $this->formatAppointment( $appointment ) );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'erta_confirm_error', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function unconfirm( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		try {
			$appointment = $this->service->unconfirm( (int) $request->get_param( 'id' ) );
			return new WP_REST_Response( $this->formatAppointment( $appointment ) );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'erta_unconfirm_error', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function cancel( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$reason = sanitize_textarea_field( $request->get_param( 'reason' ) ?? '' );

		try {
			$appointment = $this->service->cancel(
				(int) $request->get_param( 'id' ),
				$reason,
				get_current_user_id()
			);
			return new WP_REST_Response( $this->formatAppointment( $appointment ) );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'erta_cancel_error', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	public function reschedule( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$newDatetime = $request->get_param( 'new_start_datetime' );

		if ( empty( $newDatetime ) ) {
			return new WP_Error(
				'erta_missing_field',
				__( 'new_start_datetime is required.', 'ert-appointment' ),
				array( 'status' => 400 )
			);
		}

		try {
			$dto = new RescheduleDTO(
				newStartDatetime: new DateTimeImmutable( $newDatetime ),
				notes:            $request->get_param( 'notes' )
			);

			$appointment = $this->service->reschedule(
				(int) $request->get_param( 'id' ),
				$dto,
				get_current_user_id()
			);

			return new WP_REST_Response( $this->formatAppointment( $appointment ) );
		} catch ( SlotNotAvailableException $e ) {
			return new WP_Error( 'erta_slot_unavailable', $e->getMessage(), array( 'status' => 409 ) );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'erta_reschedule_error', $e->getMessage(), array( 'status' => 400 ) );
		}
	}

	// -------------------------------------------------------------------------
	// List endpoints
	// -------------------------------------------------------------------------

	public function myAppointments( WP_REST_Request $request ): WP_REST_Response {
		$user  = wp_get_current_user();
		$items = $this->repository->findByCustomerEmail( $user->user_email );

		return new WP_REST_Response(
			array(
				'items' => array_map( array( $this, 'formatAppointment' ), $items ),
			)
		);
	}

	public function adminList( WP_REST_Request $request ): WP_REST_Response {
		$page    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?? 20 ) ) );

		$filters = array_filter(
			array(
				'provider_id'   => $request->get_param( 'provider_id' ),
				'department_id' => $request->get_param( 'department_id' ),
				'status'        => $request->get_param( 'status' ),
				'date_from'     => $request->get_param( 'date_from' ),
				'date_to'       => $request->get_param( 'date_to' ),
				'search'        => $request->get_param( 'search' ),
			)
		);

		$result = $this->repository->paginate( $page, $perPage, $filters );

		return new WP_REST_Response(
			array(
				'items'       => array_map( array( $this, 'formatAppointment' ), $result['items'] ),
				'total'       => $result['total'],
				'page'        => $page,
				'per_page'    => $perPage,
				'total_pages' => (int) ceil( $result['total'] / $perPage ),
			)
		);
	}

	public function providerList( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		// Resolve which providers the current user manages.
		$userId      = get_current_user_id();
		$providerIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT provider_id FROM {$wpdb->prefix}erta_provider_users WHERE user_id = %d",
				$userId
			)
		);

		if ( empty( $providerIds ) ) {
			return new WP_REST_Response(
				array(
					'items' => array(),
					'total' => 0,
				)
			);
		}

		$upcoming = $request->get_param( 'upcoming' ) === 'true';
		$statuses = $upcoming
			? array( AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value )
			: null;

		// For simplicity we load per provider and merge; a single query would be more efficient
		// but this keeps the repository interface clean.
		$allItems = array();
		foreach ( $providerIds as $pid ) {
			$items = $this->repository->findByCustomerEmail( '', null ); // placeholder — use paginate
		}

		// Use paginate with provider_id filter for each provider.
		$page    = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = min( 100, (int) ( $request->get_param( 'per_page' ) ?? 20 ) );

		$filters = array( 'provider_id' => $providerIds[0] ); // simplified — multi-provider handled below
		// When there are multiple providers, pass the first for now.
		// A production version would use an IN clause in paginate().
		if ( count( $providerIds ) === 1 ) {
			$result = $this->repository->paginate( $page, $perPage, $filters );
		} else {
			// Aggregate across providers — kept simple here.
			$result = array(
				'items' => array(),
				'total' => 0,
			);
			foreach ( $providerIds as $pid ) {
				$r                = $this->repository->paginate( $page, $perPage, array( 'provider_id' => $pid ) );
				$result['items']  = array_merge( $result['items'], $r['items'] );
				$result['total'] += $r['total'];
			}
		}

		return new WP_REST_Response(
			array(
				'items' => array_map( array( $this, 'formatAppointment' ), $result['items'] ),
				'total' => $result['total'],
			)
		);
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	public function canViewAppointment( WP_REST_Request $request ): bool {
		if ( current_user_can( 'erta_manage_all' ) ) {
			return true;
		}

		$appointment = $this->repository->findById( (int) $request->get_param( 'id' ) );
		if ( ! $appointment ) {
			return false;
		}

		// Customer viewing their own.
		$user = wp_get_current_user();
		if ( $user && $user->user_email === $appointment->customerEmail ) {
			return true;
		}

		// Provider user.
		return current_user_can( 'erta_view_appointments' );
	}

	public function canManageAppointment( WP_REST_Request $request ): bool {
		return current_user_can( 'erta_manage_all' );
	}

	public function canActOnAppointment( WP_REST_Request $request ): bool {
		return is_user_logged_in();
	}

	// -------------------------------------------------------------------------
	// Serialisation
	// -------------------------------------------------------------------------

	/**
	 * Converts an Appointment entity to a plain array for the REST response.
	 *
	 * @return array<string, mixed>
	 */
	private function formatAppointment( \ERTAppointment\Domain\Appointment\Appointment $appointment ): array {
		return array(
			'id'               => $appointment->id,
			'provider_id'      => $appointment->providerId,
			'provider_name'    => $this->providerName( $appointment->providerId ),
			'department_id'    => $appointment->departmentId,
			'department_name'  => $this->departmentName( $appointment->departmentId ),
			'customer_name'    => $appointment->customerName,
			'customer_email'   => $appointment->customerEmail,
			'customer_phone'   => $appointment->customerPhone,
			'start_datetime'   => $appointment->startDatetime->format( 'Y-m-d\TH:i:s' ),
			'end_datetime'     => $appointment->endDatetime->format( 'Y-m-d\TH:i:s' ),
			'duration_minutes' => $appointment->durationMinutes,
			'status'           => $appointment->status->value,
			'status_label'     => $appointment->status->label(),
			'payment_status'   => $appointment->paymentStatus->value,
			'payment_amount'   => $appointment->paymentAmount,
			'notes'            => $appointment->notes,
			'form_data'        => $appointment->formData,
			'arrival_buffer'   => $appointment->arrivalBufferMinutes,
			'is_upcoming'      => $appointment->isUpcoming(),
			'is_cancellable'   => $appointment->isCancellable(),
			'is_reschedulable' => $appointment->isReschedulable(),
			'created_at'       => $appointment->createdAt->format( 'Y-m-d\TH:i:s' ),
		);
	}

	private function providerName( int $providerId ): string {
		if ( $providerId <= 0 ) {
			return '';
		}

		if ( array_key_exists( $providerId, $this->providerNames ) ) {
			return $this->providerNames[ $providerId ];
		}

		global $wpdb;
		$name = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name FROM {$wpdb->prefix}erta_providers WHERE id = %d",
				$providerId
			)
		);

		$this->providerNames[ $providerId ] = $name;

		return $name;
	}

	private function departmentName( ?int $departmentId ): string {
		if ( empty( $departmentId ) ) {
			return '';
		}

		if ( array_key_exists( $departmentId, $this->departmentNames ) ) {
			return $this->departmentNames[ $departmentId ];
		}

		global $wpdb;
		$name = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name FROM {$wpdb->prefix}erta_departments WHERE id = %d",
				$departmentId
			)
		);

		$this->departmentNames[ $departmentId ] = $name;

		return $name;
	}
}
