<?php

declare(strict_types=1);

namespace ERTAppointment\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ERTAppointment\Container;

/**
 * Registers the admin menu structure and renders each page
 * by delegating to the appropriate controller.
 *
 * All admin pages are single-page-application shells that mount
 * the Vue admin bundle. The Vue router handles sub-navigation.
 */
final class AdminMenu {

	public function __construct( private readonly Container $container ) {}

	public function register(): void {
		// Only administrators can access the admin panel.
		if ( ! current_user_can( 'erta_manage_all' ) ) {
			return;
		}

		$pages = array(
			'erta-appointments'  => __( 'Appointments', 'ert-appointment' ),
			'erta-departments'   => __( 'Departments', 'ert-appointment' ),
			'erta-providers'     => __( 'Providers', 'ert-appointment' ),
			'erta-forms'         => __( 'Forms', 'ert-appointment' ),
			'erta-working-hours' => __( 'Working Hours', 'ert-appointment' ),
			'erta-notifications' => __( 'Notifications', 'ert-appointment' ),
			'erta-reports'       => __( 'Reports', 'ert-appointment' ),
			'erta-settings'      => __( 'Settings', 'ert-appointment' ),
		);

		add_menu_page(
			page_title: __( 'ERT Appointment', 'ert-appointment' ),
			menu_title: __( 'ERT Appointments', 'ert-appointment' ),
			capability: 'erta_manage_all',
			menu_slug:  'erta-dashboard',
			callback:   array( $this, 'renderAdminShell' ),
			icon_url:   'dashicons-calendar-alt',
			position:   30
		);

		// Keep routes registered under the real parent, then hide them from WP submenu UI.
		// This prevents "access denied" on page refresh while avoiding null parent_slug deprecations.
		foreach ( $pages as $slug => $label ) {
			$this->registerHiddenSpaPage( $slug, $label, 'erta_manage_all', array( $this, 'renderAdminShell' ) );
		}

		/**
		 * Fires after core admin menu pages are registered.
		 * Pro add-on adds its own submenu pages here.
		 *
		 * @param Container $container Shared DI container.
		 * @param string|null $hiddenParentSlug Use this as parent slug for hidden routes (default null).
		 * @param callable $spaRenderer SPA shell callback for hidden pages.
		 */
		do_action( 'erta_admin_menu_registered', $this->container, $this->getHiddenParentSlug(), array( $this, 'renderAdminShell' ) );

		// Hide submenu UI only (do not remove submenu registrations, to keep refresh access working).
		add_action( 'admin_head', array( $this, 'printHideSubmenuCss' ) );
	}

	public function getHiddenParentSlug(): string {
		return 'erta-dashboard';
	}

	private function registerHiddenSpaPage( string $slug, string $label, string $capability, callable $callback ): void {
		add_submenu_page(
			$this->getHiddenParentSlug(),
			$label,
			$label,
			$capability,
			$slug,
			$callback
		);
	}

	public function printHideSubmenuCss(): void {
		$css = '#toplevel_page_erta-dashboard .wp-submenu{display:none !important;}';
		echo '<style>' . esc_html( $css ) . '</style>';
	}

	/**
	 * Renders the single HTML shell that mounts the Vue admin SPA.
	 * The Vue router reads the ?page= query param to determine which view to show.
	 */
	public function renderAdminShell(): void {
		$page = 'erta-dashboard';
		$pageParam = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_string( $pageParam ) && $pageParam !== '' ) {
			$page = sanitize_key( wp_unslash( $pageParam ) );
		}
		?>
		<div id="erta-admin-app" data-page="<?php echo esc_attr( $page ); ?>">
			<div class="erta-admin-loading">
				<span class="spinner is-active"></span>
				<?php esc_html_e( 'Loading…', 'ert-appointment' ); ?>
			</div>
		</div>
		<?php
	}
}
