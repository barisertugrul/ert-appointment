<?php

declare(strict_types=1);

namespace ERTAppointment;

use ERTAppointment\Admin\AdminMenu;
use ERTAppointment\Core\Assets;
use ERTAppointment\Core\Installer;
use ERTAppointment\Core\RestApiRegistrar;
use ERTAppointment\Core\Shortcodes;
use ERTAppointment\Core\HookManager;
use ERTAppointment\Domain\Appointment\AppointmentRepository;
use ERTAppointment\Domain\Department\DepartmentRepository;
use ERTAppointment\Domain\Form\FormRepository;
use ERTAppointment\Domain\Notification\Channels\EmailChannel;
use ERTAppointment\Domain\Notification\NotificationService;
use ERTAppointment\Domain\Notification\TemplateRenderer;
use ERTAppointment\Domain\Provider\ProviderRepository;
use ERTAppointment\Domain\Appointment\AppointmentService;
use ERTAppointment\Domain\Schedule\AvailabilityService;
use ERTAppointment\Domain\Schedule\SlotGenerator;
use ERTAppointment\Infrastructure\Cache\TransientCache;
use ERTAppointment\Infrastructure\Repositories\ERTAppointmentRepository;
use ERTAppointment\Infrastructure\Repositories\ERTDepartmentRepository;
use ERTAppointment\Infrastructure\Repositories\ERTFormRepository;
use ERTAppointment\Infrastructure\Repositories\ERTProviderRepository;
use ERTAppointment\Provider\ProviderMenu;
use ERTAppointment\Settings\SettingsManager;

/**
 * Central plugin class. Bootstraps the service container, registers all bindings,
 * and wires WordPress hooks. Only one instance exists (singleton).
 */
final class Plugin {

	private static ?Plugin $instance = null;
	private Container $container;

	// -------------------------------------------------------------------------
	// Construction
	// -------------------------------------------------------------------------

	private function __construct( private readonly string $file ) {
		$this->container = new Container();
		$this->registerBindings();
		$this->registerWordPressHooks();

		/**
		 * Fires after the lite plugin has been fully bootstrapped.
		 * Pro add-on (and other extensions) hook here to register their own
		 * bindings and extend core behaviour.
		 *
		 * @param Container $container The shared DI container.
		 */
		do_action( 'erta_loaded', $this->container );
	}

	/**
	 * Returns the singleton instance, creating it on first call.
	 */
	public static function getInstance( string $file ): self {
		if ( self::$instance === null ) {
			self::$instance = new self( $file );
		}

		return self::$instance;
	}

	// -------------------------------------------------------------------------
	// Service container bindings
	// -------------------------------------------------------------------------

	/**
	 * Registers all service bindings into the container.
	 * Each bind/singleton call is lightweight — no objects are created here;
	 * they are resolved lazily on first use.
	 */
	private function registerBindings(): void {
		// ----- Infrastructure ------------------------------------------------

		$this->container->singleton(
			TransientCache::class,
			fn() => new TransientCache()
		);

		// ----- Repositories (interface → concrete) ---------------------------

		$this->container->bind(
			AppointmentRepository::class,
			ERTAppointmentRepository::class
		);

		$this->container->bind(
			DepartmentRepository::class,
			ERTDepartmentRepository::class
		);

		$this->container->bind(
			ProviderRepository::class,
			ERTProviderRepository::class
		);

		$this->container->bind(
			FormRepository::class,
			ERTFormRepository::class
		);

		// ----- Settings ------------------------------------------------------

		$this->container->singleton(
			SettingsManager::class,
			fn( Container $c ) => new SettingsManager(
				$c->make( TransientCache::class )
			)
		);

		// ----- Scheduling ----------------------------------------------------

		$this->container->singleton(
			SlotGenerator::class,
			fn() => new SlotGenerator()
		);

		$this->container->singleton(
			AvailabilityService::class,
			fn( Container $c ) => new AvailabilityService(
				$c->make( SlotGenerator::class ),
				$c->make( SettingsManager::class ),
				$c->make( AppointmentRepository::class ),
				$c->make( TransientCache::class )
			)
		);

		// ----- Notifications -------------------------------------------------

		$this->container->singleton(
			TemplateRenderer::class,
			fn() => new TemplateRenderer()
		);

		$this->container->singleton(
			EmailChannel::class,
			fn() => new EmailChannel()
		);

		$this->container->singleton(
			NotificationService::class,
			fn( Container $c ) => new NotificationService(
				// Channels array — Pro add-on can push additional channels (SMS etc.)
				apply_filters(
					'erta_notification_channels',
					array(
						$c->make( EmailChannel::class ),
					)
				),
				$c->make( TemplateRenderer::class ),
				$c->make( SettingsManager::class )
			)
		);

		// ----- Domain services -----------------------------------------------

		$this->container->singleton(
			AppointmentService::class,
			fn( Container $c ) => new AppointmentService(
				$c->make( AppointmentRepository::class ),
				$c->make( AvailabilityService::class ),
				$c->make( NotificationService::class ),
				$c->make( SettingsManager::class ),
				$c->make( TransientCache::class )
			)
		);

		// ----- Core ----------------------------------------------------------

		$this->container->singleton(
			Installer::class,
			fn( Container $c ) => new Installer( $c->make( SettingsManager::class ) )
		);

		$this->container->singleton(
			Assets::class,
			fn() => new Assets()
		);

		$this->container->singleton(
			Shortcodes::class,
			fn( Container $c ) => new Shortcodes(
				$c->make( AvailabilityService::class ),
				$c->make( FormRepository::class ),
				$c->make( SettingsManager::class )
			)
		);

		$this->container->singleton(
			RestApiRegistrar::class,
			fn( Container $c ) => new RestApiRegistrar( $c )
		);

		// ----- Admin panels --------------------------------------------------

		$this->container->singleton(
			AdminMenu::class,
			fn( Container $c ) => new AdminMenu( $c )
		);

		$this->container->singleton(
			ProviderMenu::class,
			fn( Container $c ) => new ProviderMenu( $c )
		);

		// ----- Admin REST API controllers ------------------------------------

		$this->container->singleton(
			\ERTAppointment\Api\Controllers\SettingsApiController::class,
			fn( Container $c ) => new \ERTAppointment\Api\Controllers\SettingsApiController(
				$c->make( \ERTAppointment\Settings\SettingsManager::class ),
				$c->make( Installer::class )
			)
		);

		$this->container->singleton(
			\ERTAppointment\Api\Controllers\AdminDepartmentApiController::class,
			fn( Container $c ) => new \ERTAppointment\Api\Controllers\AdminDepartmentApiController(
				$c->make( DepartmentRepository::class )
			)
		);

		$this->container->singleton(
			\ERTAppointment\Api\Controllers\AdminProviderApiController::class,
			fn( Container $c ) => new \ERTAppointment\Api\Controllers\AdminProviderApiController(
				$c->make( ProviderRepository::class )
			)
		);

		$this->container->singleton(
			\ERTAppointment\Api\Controllers\AdminFormApiController::class,
			fn( Container $c ) => new \ERTAppointment\Api\Controllers\AdminFormApiController(
				$c->make( FormRepository::class )
			)
		);

		$this->container->singleton(
			\ERTAppointment\Api\Controllers\NotificationTemplateApiController::class,
			fn( Container $c ) => new \ERTAppointment\Api\Controllers\NotificationTemplateApiController(
				$c->make( \ERTAppointment\Domain\Notification\TemplateRenderer::class )
			)
		);

		$this->container->singleton(
			\ERTAppointment\Api\Controllers\WorkingHoursApiController::class,
			fn( Container $c ) => new \ERTAppointment\Api\Controllers\WorkingHoursApiController()
		);
	}

	// -------------------------------------------------------------------------
	// WordPress action / filter hooks
	// -------------------------------------------------------------------------

	private function registerWordPressHooks(): void {
		$hooks = new HookManager();

		$hooks->add( 'init', array( $this->container->make( RestApiRegistrar::class ), 'register' ) );
		$hooks->add( 'init', array( $this->container->make( Shortcodes::class ), 'register' ) );
		$hooks->add( 'init', array( $this->container->make( Installer::class ), 'maybeRepairInstallation' ), 1 );

		$hooks->add( 'admin_menu', array( $this->container->make( AdminMenu::class ), 'register' ) );
		$hooks->add( 'admin_menu', array( $this->container->make( ProviderMenu::class ), 'register' ) );

		$assets = $this->container->make( Assets::class );

		$hooks->add( 'wp_enqueue_scripts', array( $assets, 'enqueueFrontend' ) );
		$hooks->add( 'admin_enqueue_scripts', array( $assets, 'enqueueAdmin' ) );

		// Script tag'lerine type="module" ekle (Vite ES module output'u için).
		$hooks->filter( 'script_loader_tag', array( $assets, 'filterScriptTag' ), 10, 3 );

		$hooks->register();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	public function getContainer(): Container {
		return $this->container;
	}
}
