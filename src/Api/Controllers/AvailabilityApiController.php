<?php

declare(strict_types=1);

namespace ERTAppointment\Api\Controllers;

use DateTimeImmutable;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ERTAppointment\Domain\Schedule\AvailabilityService;
use ERTAppointment\Settings\SettingsManager;

/**
 * REST controller for slot and calendar availability queries.
 * These endpoints are called by the Vue.js booking widget.
 */
final class AvailabilityApiController {

	public function __construct(
		private readonly AvailabilityService $availabilityService,
		private readonly SettingsManager $settings
	) {}

	/**
	 * GET /erta/v1/providers/{id}/slots?date=YYYY-MM-DD
	 *
	 * Returns available time slots for a provider on a specific date.
	 */
	public function getSlots( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$providerId = (int) $request->get_param( 'id' );
		$dateStr    = $request->get_param( 'date' );

		try {
			$date = new DateTimeImmutable( $dateStr );
		} catch ( \Throwable ) {
			return new WP_Error(
				'erta_invalid_date',
				__( 'Invalid date format. Use YYYY-MM-DD.', 'ert-appointment' ),
				array( 'status' => 400 )
			);
		}

		$slots = $this->availabilityService->getAvailableSlots( $providerId, $date );
		$config = $this->settings->resolveForProvider( $providerId );

		return new WP_REST_Response(
			array(
				'date'  => $dateStr,
				'slots' => array_map( fn( $slot ) => $slot->toArray(), $slots ),
				'meta'  => $this->metaFromConfig( $config ),
			)
		);
	}

	/**
	 * GET /erta/v1/providers/{id}/calendar?from=YYYY-MM-DD&to=YYYY-MM-DD
	 *
	 * Returns a date-keyed map of available slot counts for a date range.
	 * Used to mark available / unavailable days on the calendar widget.
	 *
	 * Maximum range: 90 days (to limit server load).
	 */
	public function getCalendar( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$providerId = (int) $request->get_param( 'id' );

		try {
			$from = new DateTimeImmutable( $request->get_param( 'from' ) );
			$to   = new DateTimeImmutable( $request->get_param( 'to' ) );
		} catch ( \Throwable ) {
			return new WP_Error(
				'erta_invalid_date',
				__( 'Invalid date format. Use YYYY-MM-DD.', 'ert-appointment' ),
				array( 'status' => 400 )
			);
		}

		// Safety cap.
		$daysDiff = $from->diff( $to )->days;
		if ( $daysDiff > 90 ) {
			$to = $from->modify( '+90 days' );
		}

		$calendar = $this->availabilityService->getAvailabilityCalendar( $providerId, $from, $to );
		$config = $this->settings->resolveForProvider( $providerId );

		return new WP_REST_Response(
			array(
				'provider_id'  => $providerId,
				'from'         => $from->format( 'Y-m-d' ),
				'to'           => $to->format( 'Y-m-d' ),
				// Availability map of date => available slot count.
				'availability' => $calendar,
				'meta'         => $this->metaFromConfig( $config ),
			)
		);
	}

	private function metaFromConfig( $config ): array {
		return array(
			'booking_start_date'       => $config->bookingStartDate(),
			'booking_end_date'         => $config->bookingEndDate(),
			'appointment_location'     => $config->appointmentLocation(),
			'booking_form_intro'       => $config->bookingFormIntro(),
			'post_booking_instructions'=> $config->postBookingInstructions(),
			'show_arrival_reminder'    => $config->showArrivalReminder(),
			'arrival_buffer'           => $config->arrivalBuffer(),
			'allow_general_booking'    => $config->allowGeneralBooking(),
			'general_provider_id'      => $config->generalProviderId(),
		);
	}
}
