<?php

declare(strict_types=1);

namespace ERTAppointment\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ERTAppointment\Api\Controllers\AppointmentApiController;
use ERTAppointment\Api\Controllers\AvailabilityApiController;
use ERTAppointment\Api\Controllers\FormApiController;
use ERTAppointment\Api\Controllers\DepartmentApiController;
use ERTAppointment\Api\Controllers\ProviderApiController;
use ERTAppointment\Api\Controllers\WorkingHoursApiController;
use ERTAppointment\Api\Controllers\SettingsApiController;
use ERTAppointment\Api\Controllers\AdminDepartmentApiController;
use ERTAppointment\Api\Controllers\AdminProviderApiController;
use ERTAppointment\Api\Controllers\AdminFormApiController;
use ERTAppointment\Api\Controllers\NotificationTemplateApiController;
use ERTAppointment\Container;

/**
 * Registers all WP REST API routes for the plugin.
 * Namespace: erta/v1
 */
final class RestApiRegistrar {

	public function __construct( private readonly Container $container ) {}

	public function register(): void {
		// Controllers are resolved lazily — only instantiated on matching requests.
		add_action(
			'rest_api_init',
			function (): void {
				$this->registerAvailabilityRoutes();
				$this->registerAppointmentRoutes();
				$this->registerFormRoutes();
				$this->registerDepartmentRoutes();
				$this->registerProviderRoutes();
				$this->registerWorkingHoursRoutes();
				$this->registerAdminSettingsRoutes();
				$this->registerAdminDepartmentRoutes();
				$this->registerAdminProviderRoutes();
				$this->registerAdminFormRoutes();
				$this->registerAdminNotificationRoutes();

				/**
				 * Fires after core routes are registered.
				 * Pro add-on hooks here to add payment, waitlist, etc. routes.
				 */
				do_action( 'erta_rest_api_init', $this->container );
			}
		);
	}

	// -------------------------------------------------------------------------
	// Route groups
	// -------------------------------------------------------------------------

	private function registerAvailabilityRoutes(): void {
		$ctrl = fn() => $this->container->make( AvailabilityApiController::class );

		// GET /erta/v1/providers/{id}/slots?date=YYYY-MM-DD
		register_rest_route(
			'erta/v1',
			'/providers/(?P<id>\d+)/slots',
			array(
				'methods'             => 'GET',
				'callback'            => fn( $req ) => $ctrl()->getSlots( $req ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'   => array(
						'required' => true,
						'type'     => 'integer',
					),
					'date' => array(
						'required' => true,
						'type'     => 'string',
						'format'   => 'date',
					),
				),
			)
		);

		// GET /erta/v1/providers/{id}/calendar?from=&to=
		register_rest_route(
			'erta/v1',
			'/providers/(?P<id>\d+)/calendar',
			array(
				'methods'             => 'GET',
				'callback'            => fn( $req ) => $ctrl()->getCalendar( $req ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'   => array(
						'required' => true,
						'type'     => 'integer',
					),
					'from' => array(
						'required' => true,
						'type'     => 'string',
						'format'   => 'date',
					),
					'to'   => array(
						'required' => true,
						'type'     => 'string',
						'format'   => 'date',
					),
				),
			)
		);
	}

	private function registerAppointmentRoutes(): void {
		$ctrl = fn() => $this->container->make( AppointmentApiController::class );

		// POST /erta/v1/appointments  — public booking endpoint
		register_rest_route(
			'erta/v1',
			'/appointments',
			array(
				'methods'             => 'POST',
				'callback'            => fn( $req ) => $ctrl()->create( $req ),
				'permission_callback' => '__return_true',
			)
		);

		// GET /erta/v1/appointments/{id}
		register_rest_route(
			'erta/v1',
			'/appointments/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => fn( $req ) => $ctrl()->get( $req ),
					'permission_callback' => fn( $req ) => $ctrl()->canViewAppointment( $req ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => fn( $req ) => $ctrl()->delete( $req ),
					'permission_callback' => fn( $req ) => $ctrl()->canManageAppointment( $req ),
				),
			)
		);

		// EDITABLE /erta/v1/appointments/{id}/confirm
		register_rest_route(
			'erta/v1',
			'/appointments/(?P<id>\d+)/confirm',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => fn( $req ) => $ctrl()->confirm( $req ),
				'permission_callback' => fn( $req ) => $ctrl()->canManageAppointment( $req ),
			)
		);

		// EDITABLE /erta/v1/appointments/{id}/unconfirm
		register_rest_route(
			'erta/v1',
			'/appointments/(?P<id>\d+)/unconfirm',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => fn( $req ) => $ctrl()->unconfirm( $req ),
				'permission_callback' => fn( $req ) => $ctrl()->canManageAppointment( $req ),
			)
		);

		// EDITABLE /erta/v1/appointments/{id}/cancel
		register_rest_route(
			'erta/v1',
			'/appointments/(?P<id>\d+)/cancel',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => fn( $req ) => $ctrl()->cancel( $req ),
				'permission_callback' => fn( $req ) => $ctrl()->canActOnAppointment( $req ),
			)
		);

		// EDITABLE /erta/v1/appointments/{id}/reschedule
		register_rest_route(
			'erta/v1',
			'/appointments/(?P<id>\d+)/reschedule',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => fn( $req ) => $ctrl()->reschedule( $req ),
				'permission_callback' => fn( $req ) => $ctrl()->canActOnAppointment( $req ),
			)
		);

		// GET /erta/v1/my-appointments  — logged-in customer
		register_rest_route(
			'erta/v1',
			'/my-appointments',
			array(
				'methods'             => 'GET',
				'callback'            => fn( $req ) => $ctrl()->myAppointments( $req ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// GET /erta/v1/admin/appointments  — admin paginated list
		register_rest_route(
			'erta/v1',
			'/admin/appointments',
			array(
				'methods'             => 'GET',
				'callback'            => fn( $req ) => $ctrl()->adminList( $req ),
				'permission_callback' => fn() => current_user_can( 'erta_manage_all' ),
			)
		);

		register_rest_route(
			'erta/v1',
			'/admin/appointments/bulk',
			array(
				'methods'             => 'POST',
				'callback'            => fn( $req ) => $ctrl()->bulk( $req ),
				'permission_callback' => fn() => current_user_can( 'erta_manage_all' ),
			)
		);

		// GET /erta/v1/provider/appointments  — provider's own appointments
		register_rest_route(
			'erta/v1',
			'/provider/appointments',
			array(
				'methods'             => 'GET',
				'callback'            => fn( $req ) => $ctrl()->providerList( $req ),
				'permission_callback' => fn() => current_user_can( 'erta_view_appointments' ),
			)
		);
	}

	private function registerFormRoutes(): void {
		$ctrl = fn() => $this->container->make( FormApiController::class );

		// GET /erta/v1/forms/{scope}/{scope_id?}
		register_rest_route(
			'erta/v1',
			'/forms/(?P<scope>[a-z]+)(?:/(?P<scope_id>\d+))?',
			array(
				'methods'             => 'GET',
				'callback'            => fn( $req ) => $ctrl()->get( $req ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private function registerDepartmentRoutes(): void {
		$ctrl = fn() => $this->container->make( DepartmentApiController::class );

		register_rest_route(
			'erta/v1',
			'/departments',
			array(
				'methods'             => 'GET',
				'callback'            => fn( $req ) => $ctrl()->index( $req ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private function registerProviderRoutes(): void {
		$ctrl = fn() => $this->container->make( ProviderApiController::class );

		// GET /erta/v1/providers?department_id=
		register_rest_route(
			'erta/v1',
			'/providers',
			array(
				'methods'             => 'GET',
				'callback'            => fn( $req ) => $ctrl()->index( $req ),
				'permission_callback' => '__return_true',
			)
		);

		// GET /erta/v1/providers/{id}
		register_rest_route(
			'erta/v1',
			'/providers/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => fn( $req ) => $ctrl()->get( $req ),
				'permission_callback' => '__return_true',
			)
		);
	}

	private function registerWorkingHoursRoutes(): void {
		$ctrl = fn() => $this->container->make( WorkingHoursApiController::class );
		$ctrl()->registerRoutes();
	}

	// ── Admin: Settings ────────────────────────────────────────────────────

	private function registerAdminSettingsRoutes(): void {
		$admin = fn() => current_user_can( 'erta_manage_all' );
		$ctrl  = fn() => $this->container->make( SettingsApiController::class );

		register_rest_route(
			'erta/v1',
			'/admin/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => fn( $req ) => $ctrl()->get( $req ),
					'permission_callback' => $admin,
					'args'                => array(
						'scope'    => array(
							'type'    => 'string',
							'default' => 'global',
						),
						'scope_id' => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => fn( $req ) => $ctrl()->save( $req ),
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			'erta/v1',
			'/admin/settings/repair',
			array(
				'methods'             => 'POST',
				'callback'            => fn( $req ) => $ctrl()->repair( $req ),
				'permission_callback' => $admin,
			)
		);
	}

	// ── Admin: Departments ─────────────────────────────────────────────────

	private function registerAdminDepartmentRoutes(): void {
		$admin = fn() => current_user_can( 'erta_manage_all' );
		$ctrl  = fn() => $this->container->make( AdminDepartmentApiController::class );

		register_rest_route(
			'erta/v1',
			'/admin/departments',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => fn( $req ) => $ctrl()->index( $req ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'POST',
					'callback'            => fn( $req ) => $ctrl()->create( $req ),
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			'erta/v1',
			'/admin/departments/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => fn( $req ) => $ctrl()->update( $req ),
					'permission_callback' => $admin,
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => fn( $req ) => $ctrl()->delete( $req ),
					'permission_callback' => $admin,
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
			)
		);
	}

	// ── Admin: Providers ───────────────────────────────────────────────────

	private function registerAdminProviderRoutes(): void {
		$admin = fn() => current_user_can( 'erta_manage_all' );
		$ctrl  = fn() => $this->container->make( AdminProviderApiController::class );

		register_rest_route(
			'erta/v1',
			'/admin/providers',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => fn( $req ) => $ctrl()->index( $req ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'POST',
					'callback'            => fn( $req ) => $ctrl()->create( $req ),
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			'erta/v1',
			'/admin/providers/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => fn( $req ) => $ctrl()->update( $req ),
					'permission_callback' => $admin,
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => fn( $req ) => $ctrl()->delete( $req ),
					'permission_callback' => $admin,
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
			)
		);

		// User assignment sub-resource.
		register_rest_route(
			'erta/v1',
			'/admin/providers/(?P<id>\d+)/users',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => fn( $req ) => $ctrl()->listUsers( $req ),
					'permission_callback' => $admin,
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => fn( $req ) => $ctrl()->assignUser( $req ),
					'permission_callback' => $admin,
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
			)
		);

		register_rest_route(
			'erta/v1',
			'/admin/providers/(?P<id>\d+)/users/(?P<user_id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => fn( $req ) => $ctrl()->removeUser( $req ),
				'permission_callback' => $admin,
				'args'                => array(
					'id'      => array(
						'required' => true,
						'type'     => 'integer',
					),
					'user_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);
	}

	// ── Admin: Forms ───────────────────────────────────────────────────────

	private function registerAdminFormRoutes(): void {
		$admin = fn() => current_user_can( 'erta_manage_all' );
		$ctrl  = fn() => $this->container->make( AdminFormApiController::class );

		register_rest_route(
			'erta/v1',
			'/admin/forms',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => fn( $req ) => $ctrl()->index( $req ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'POST',
					'callback'            => fn( $req ) => $ctrl()->create( $req ),
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			'erta/v1',
			'/admin/forms/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => fn( $req ) => $ctrl()->update( $req ),
					'permission_callback' => $admin,
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => fn( $req ) => $ctrl()->delete( $req ),
					'permission_callback' => $admin,
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
			)
		);
	}

	// ── Admin: Notification Templates ──────────────────────────────────────

	private function registerAdminNotificationRoutes(): void {
		$admin = fn() => current_user_can( 'erta_manage_all' );
		$ctrl  = fn() => $this->container->make( NotificationTemplateApiController::class );

		// Placeholder hint list — must be registered before /{id} to avoid conflict.
		register_rest_route(
			'erta/v1',
			'/admin/notification-templates/placeholders',
			array(
				'methods'             => 'GET',
				'callback'            => fn( $req ) => $ctrl()->placeholders( $req ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			'erta/v1',
			'/admin/notification-templates',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => fn( $req ) => $ctrl()->index( $req ),
					'permission_callback' => $admin,
				),
				array(
					'methods'             => 'POST',
					'callback'            => fn( $req ) => $ctrl()->create( $req ),
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			'erta/v1',
			'/admin/notification-templates/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => fn( $req ) => $ctrl()->get( $req ),
					'permission_callback' => $admin,
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => fn( $req ) => $ctrl()->update( $req ),
					'permission_callback' => $admin,
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
			)
		);
	}
}
