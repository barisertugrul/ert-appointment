<?php

declare(strict_types=1);

namespace ERTAppointment\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use ERTAppointment\Core\Installer;
use ERTAppointment\Settings\SettingsManager;

/**
 * Admin REST endpoints for plugin settings.
 *
 * Settings are scope-aware: the same keys live at global / department / provider level,
 * with the lower scope overriding the upper one at runtime (see SettingsManager).
 *
 * Routes:
 *  GET  /erta/v1/admin/settings?scope=global
 *  GET  /erta/v1/admin/settings?scope=department&scope_id=5
 *  POST /erta/v1/admin/settings  { scope, scope_id?, settings: {...} }
 */
final class SettingsApiController {

	// Keys that are allowed to be saved; everything else is silently ignored.
	private const ALLOWED_KEYS = array(
		// Scheduling
		'slot_duration_minutes',
		'slot_duration',
		'buffer_before_minutes',
		'buffer_before',
		'buffer_after_minutes',
		'buffer_after',
		'min_notice_hours',
		'minimum_notice',
		'max_advance_days',
		'maximum_advance',
		'auto_confirm',
		'arrival_buffer_minutes',
		'arrival_buffer',
		'booking_start_date',
		'booking_end_date',
		'show_arrival_reminder',
		'allow_general_booking',
		'general_provider_id',
		'appointment_location',
		'booking_form_intro',
		'post_booking_instructions',

		// Payment
		'payment_required',
		'payment_amount',
		'payment_gateway',
		'paytr_merchant_id',
		'paytr_merchant_key',
		'paytr_merchant_salt',
		'paytr_test_mode',
		'stripe_secret_key',
		'stripe_webhook_secret',
		'stripe_publishable_key',
		'paypal_client_id',
		'paypal_client_secret',
		'paypal_sandbox',
		'iyzico_api_key',
		'iyzico_secret_key',
		'iyzico_sandbox',

		// Notifications
		'admin_email',
		'email_from_name',
		'email_from_address',
		'sms_provider',
		'twilio_account_sid',
		'twilio_auth_token',
		'twilio_from_number',
		'netgsm_usercode',
		'netgsm_password',
		'netgsm_header',

		// Integrations
		'google_client_id',
		'google_client_secret',
		'zoom_account_id',
		'zoom_client_id',
		'zoom_client_secret',
		'zoom_auto_create',

		// General
		'currency',
		'date_format',
		'time_format',
		'timezone',
		'cancellation_policy_hours',
		'rescheduling_allowed',
	);

	public function __construct(
		private readonly SettingsManager $settingsManager,
		private readonly Installer $installer
	) {}

	// ── GET ───────────────────────────────────────────────────────────────

	public function get( WP_REST_Request $request ): WP_REST_Response {
		$scope   = $this->parseScope( $request );
		$rawScopeId = (int) ( $request->get_param( 'scope_id' ) ?? 0 );
		$scopeId    = $this->normalizeScopeId( $scope, $rawScopeId );

		if ( $scope !== 'global' && $scopeId === null ) {
			return new WP_REST_Response( array( 'error' => 'scope_id must be provided for department/provider scope.' ), 400 );
		}

		$settings = $this->settingsManager->getAll( $scope, $scopeId );

		return new WP_REST_Response(
			array(
				'scope'        => $scope,
				'scope_id'     => $scopeId ?? 0,
				'settings'     => $settings,
				'installation' => $this->installer->getInstallationChecklist(),
			)
		);
	}

	// ── POST (save) ───────────────────────────────────────────────────────

	public function save( WP_REST_Request $request ): WP_REST_Response {
		$scope    = $this->parseScope( $request );
		$scopeId  = (int) ( $request->get_param( 'scope_id' ) ?? 0 );
		$scopeId  = $this->normalizeScopeId( $scope, $scopeId );
		$incoming = $request->get_param( 'settings' );

		if ( $scope !== 'global' && $scopeId === null ) {
			return new WP_REST_Response( array( 'error' => 'scope_id must be provided for department/provider scope.' ), 400 );
		}

		if ( ! is_array( $incoming ) ) {
			return new WP_REST_Response( array( 'error' => 'settings must be an object.' ), 400 );
		}

		// Filter to allowed keys only.
		$filtered = array_intersect_key( $incoming, array_flip( self::ALLOWED_KEYS ) );

		// Sanitize values.
		$sanitized = $this->sanitize( $filtered );

		// Persist — bulkSet handles cache invalidation internally.
		$this->settingsManager->bulkSet( $scope, $scopeId, $sanitized );

		return new WP_REST_Response(
			array(
				'success' => true,
				'saved'   => count( $sanitized ),
			)
		);
	}

	public function repair( WP_REST_Request $request ): WP_REST_Response {
		$this->installer->repairInstallation();

		return new WP_REST_Response(
			array(
				'success'      => true,
				'installation' => $this->installer->getInstallationChecklist(),
			)
		);
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	private function parseScope( WP_REST_Request $request ): string {
		$scope = \sanitize_key( $request->get_param( 'scope' ) ?? 'global' );
		return in_array( $scope, array( 'global', 'department', 'provider' ), true )
			? $scope
			: 'global';
	}

	private function sanitize( array $data ): array {
		$out = array();
		foreach ( $data as $key => $value ) {
			$out[ $key ] = match ( true ) {
				is_bool( $value )                                         => (bool) $value,
				is_int( $value )                                          => (int) $value,
				is_float( $value )                                        => (float) $value,
				in_array(
					$key, array(
						'google_client_secret',
						'paytr_merchant_key',
						'paytr_merchant_salt',
						'stripe_secret_key',
						'stripe_webhook_secret',
						'zoom_client_secret',
						'paypal_client_secret',
						'twilio_auth_token',
						'netgsm_password',
						'iyzico_secret_key',
					), true
				)
					=> sanitize_text_field( (string) $value ),   // sensitive — no URL/HTML
				default                                                 => sanitize_text_field( (string) $value ),
			};
		}
		return $out;
	}

	private function normalizeScopeId( string $scope, int $scopeId ): ?int {
		if ( $scope === 'global' ) {
			return null;
		}

		return $scopeId > 0 ? $scopeId : null;
	}
}
