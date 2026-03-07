<?php

declare(strict_types=1);

namespace ERTAppointment\Core;

use ERTAppointment\Domain\Form\FormRepository;
use ERTAppointment\Domain\Schedule\AvailabilityService;
use ERTAppointment\Settings\SettingsManager;

/**
 * Registers all plugin shortcodes.
 *
 * [erta_booking]              — Full booking widget (Vue SPA mount point)
 * [erta_my_appointments]     — Customer's own appointment list
 * [erta_booking department="slug" provider="id"]  — Pre-filtered widget
 */
final class Shortcodes {

	public function __construct(
		private readonly AvailabilityService $availabilityService,
		private readonly FormRepository $formRepository,
		private readonly SettingsManager $settings,
	) {}

	public function register(): void {
		add_shortcode( 'erta_booking', array( $this, 'renderBookingWidget' ) );
		add_shortcode( 'erta-booking', array( $this, 'renderBookingWidget' ) );
		add_shortcode( 'erta_my_appointments', array( $this, 'renderMyAppointments' ) );
		add_shortcode( 'erta-my-appointments', array( $this, 'renderMyAppointments' ) );
	}

	// -------------------------------------------------------------------------
	// [erta_booking]
	// -------------------------------------------------------------------------

	/**
	 * Renders the booking widget SPA mount point.
	 *
	 * Shortcode attributes:
	 *  - department  : pre-select department slug
	 *  - provider    : pre-select provider ID
	 *  - form        : override form ID
	 *
	 * @param array<string, string>|string $atts
	 */
	public function renderBookingWidget( array|string $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'department' => '',
				'provider'   => '',
				'form'       => '',
			),
			$atts,
			'erta_booking'
		);

		// Build data attributes for the Vue app to pick up.
		$dataAttrs = '';
		if ( $atts['department'] ) {
			$dataAttrs .= ' data-department="' . esc_attr( $atts['department'] ) . '"';
		}
		if ( $atts['provider'] ) {
			$dataAttrs .= ' data-provider="' . esc_attr( $atts['provider'] ) . '"';
		}
		if ( $atts['form'] ) {
			$dataAttrs .= ' data-form="' . esc_attr( $atts['form'] ) . '"';
		}

		ob_start();
		?>
		<div id="erta-booking-app" class="erta-booking-host"<?php echo wp_kses_data( $dataAttrs ); ?>>
			<noscript>
				<?php
				esc_html_e(
					'Please enable JavaScript to use the appointment booking system.',
					'ert-appointment'
				);
				?>
			</noscript>
			<div class="erta-loading-placeholder">
				<?php esc_html_e( 'Loading booking form…', 'ert-appointment' ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [erta_my_appointments]
	// -------------------------------------------------------------------------

	/**
	 * Renders the customer's appointment management widget.
	 * Requires the user to be logged in (or provides a login prompt).
	 */
	public function renderMyAppointments(): string {
		if ( ! is_user_logged_in() ) {
			$loginUrl = wp_login_url( get_permalink() );

			ob_start();
			?>
			<p class="erta-login-notice">
				<?php
				printf(
					/* translators: %s login URL */
					esc_html__( 'Please %s to view and manage your appointments.', 'ert-appointment' ),
					'<a href="' . esc_url( $loginUrl ) . '">' . esc_html__( 'log in', 'ert-appointment' ) . '</a>'
				);
				?>
			</p>
			<?php
			return ob_get_clean();
		}

		ob_start();
		?>
		<div id="erta-my-appointments-app" class="erta-my-appointments-host"
			data-user-id="<?php echo esc_attr( get_current_user_id() ); ?>">
			<div class="erta-loading-placeholder">
				<?php esc_html_e( 'Loading your appointments…', 'ert-appointment' ); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
