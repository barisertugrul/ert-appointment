<?php

declare(strict_types=1);

namespace ERTAppointment\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ERTAppointment\Container;

/**
 * Registers the reduced-capability admin menu for the erta_provider role.
 * Providers can only see their own appointments and basic profile settings.
 */
final class ProviderMenu {

	public function __construct( private readonly Container $container ) {}

	public function register(): void {
		// Skip if the user has full admin access (they see the full admin menu).
		if ( current_user_can( 'erta_manage_all' ) ) {
			return;
		}

		// Only show to users with provider-level capability.
		if ( ! current_user_can( 'erta_view_appointments' ) ) {
			return;
		}

		add_menu_page(
			page_title: __( 'My Appointments', 'ert-appointment' ),
			menu_title: __( 'My Appointments', 'ert-appointment' ),
			capability: 'erta_view_appointments',
			menu_slug:  'erta-provider-dashboard',
			callback:   array( $this, 'renderProviderShell' ),
			icon_url:   'dashicons-calendar-alt',
			position:   30
		);

		// Register SPA routes under the real parent, then hide WP submenu rows.
		// This avoids null parent_slug deprecations while keeping direct refresh access.
		$this->registerHiddenSpaPage(
			'erta-provider-past',
			__( 'Past Appointments', 'ert-appointment' ),
			'erta_view_appointments',
			array( $this, 'renderProviderShell' )
		);

		/**
		 * Fires after provider menu pages are registered.
		 *
		 * @param Container $container Shared DI container.
		 * @param string|null $hiddenParentSlug Use this as parent slug for hidden routes (default null).
		 * @param callable $spaRenderer SPA shell callback for hidden pages.
		 */
		do_action( 'erta_provider_menu_registered', $this->container, $this->getHiddenParentSlug(), array( $this, 'renderProviderShell' ) );

		// Hide submenu UI only (do not remove submenu registrations, to keep refresh access working).
		add_action( 'admin_head', array( $this, 'printHideSubmenuCss' ) );
	}

	public function getHiddenParentSlug(): string {
		return 'erta-provider-dashboard';
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
		echo '<style>#toplevel_page_erta-provider-dashboard .wp-submenu{display:none !important;}</style>';
	}

	/**
	 * Renders the SPA shell for the provider panel.
	 * Shares the same Vue bundle; the router detects the page slug.
	 */
	public function renderProviderShell(): void {
		$page = 'erta-provider-dashboard';
		if ( isset( $_GET['page'] ) ) {
			$page = sanitize_key( wp_unslash( (string) $_GET['page'] ) );
		}
		?>
		<div id="erta-provider-app"
			data-page="<?php echo esc_attr( $page ); ?>"
			data-user-id="<?php echo esc_attr( get_current_user_id() ); ?>">
			<div class="erta-admin-loading">
				<span class="spinner is-active"></span>
				<?php esc_html_e( 'Loading…', 'ert-appointment' ); ?>
			</div>
		</div>
		<?php
	}
}
