<?php

declare(strict_types=1);

namespace ERTAppointment\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use ERTAppointment\Domain\Notification\TemplateRenderer;

/**
 * Admin REST endpoints for notification template management.
 *
 * Templates are event-based (appointment_confirmed, cancelled, etc.)
 * and channel-based (email, sms). Admin can edit subject + body per event.
 *
 * Routes:
 *  GET  /erta/v1/admin/notification-templates
 *  GET  /erta/v1/admin/notification-templates/{id}
 *  POST /erta/v1/admin/notification-templates
 *  PUT  /erta/v1/admin/notification-templates/{id}
 *  GET  /erta/v1/admin/notification-templates/placeholders  — hint list for editor
 */
final class NotificationTemplateApiController {

	private const ALLOWED_EVENTS = array(
		'appointment_pending',
		'appointment_confirmed',
		'appointment_cancelled',
		'appointment_rescheduled',
		'appointment_completed',
		'appointment_no_show',
		'appointment_reminder',
		'appointment_reminder_24h',
		'appointment_reminder_1h',
		'waitlist_available',
	);

	private const ALLOWED_CHANNELS = array( 'email', 'sms' );

	private const ALLOWED_RECIPIENTS = array( 'customer', 'provider', 'admin' );

	public function __construct(
		private readonly TemplateRenderer $renderer
	) {}

	// ── List ──────────────────────────────────────────────────────────────

	public function index( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$rows = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input, static query
			"SELECT * FROM {$wpdb->prefix}erta_notification_templates
             ORDER BY event_type ASC, channel ASC, recipient_type ASC",
			ARRAY_A
		);

		$normalized = array_map( array( $this, 'normalizeTemplateRow' ), $rows ?: array() );

		return new WP_REST_Response( $normalized );
	}

	// ── Single ────────────────────────────────────────────────────────────

	public function get( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}erta_notification_templates WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return new WP_REST_Response( array( 'error' => 'Template not found.' ), 404 );
		}

		return new WP_REST_Response( $this->normalizeTemplateRow( $row ) );
	}

	// ── Placeholders hint list ────────────────────────────────────────────

	/**
	 * Returns all available {{placeholder}} tokens for the template editor.
	 * Pro add-ons extend this via the 'erta_available_placeholder_hints' filter.
	 */
	public function placeholders( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->renderer->availablePlaceholders() );
	}

	// ── Create ────────────────────────────────────────────────────────────

	public function create( WP_REST_Request $request ): WP_REST_Response {
		$data   = $this->extractFields( $request );
		$errors = $this->validate( $data );

		if ( $errors ) {
			return new WP_REST_Response( array( 'error' => implode( ' ', $errors ) ), 422 );
		}

		global $wpdb;

		// Prevent duplicates: one template per event + channel + recipient.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}erta_notification_templates
             WHERE event_type = %s AND channel = %s AND recipient_type = %s",
				$data['event'],
				$data['channel'],
				$data['recipient']
			)
		);

		if ( $exists ) {
			return new WP_REST_Response(
				array(
					'error' => "A template already exists for event '{$data['event']}' / channel '{$data['channel']}' / recipient '{$data['recipient']}'. Use PUT to update it.",
				),
				409
			);
		}

		$wpdb->insert(
			"{$wpdb->prefix}erta_notification_templates",
			array(
				'event_type'      => $data['event'],
				'channel'    => $data['channel'],
				'recipient_type'  => $data['recipient'],
				'subject'    => $data['subject'],
				'body'       => $data['body'],
				'is_active'  => (int) $data['is_active'],
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			)
		);

		$id  = $wpdb->insert_id;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}erta_notification_templates WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		return new WP_REST_Response( $this->normalizeTemplateRow( $row ), 201 );
	}

	// ── Update ────────────────────────────────────────────────────────────

	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		global $wpdb;
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}erta_notification_templates WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_REST_Response( array( 'error' => 'Template not found.' ), 404 );
		}

		$data   = $this->extractFields( $request, partial: true );
		$errors = $this->validate( $data, partial: true );

		if ( $errors ) {
			return new WP_REST_Response( array( 'error' => implode( ' ', $errors ) ), 422 );
		}

		// Merge with existing — only update provided fields.
		$nextEvent     = $data['event'] ?? ( $existing['event_type'] ?? '' );
		$nextChannel   = $data['channel'] ?? ( $existing['channel'] ?? '' );
		$nextRecipient = $data['recipient'] ?? ( $existing['recipient_type'] ?? '' );

		$duplicate = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}erta_notification_templates
				 WHERE event_type = %s AND channel = %s AND recipient_type = %s AND id <> %d",
				$nextEvent,
				$nextChannel,
				$nextRecipient,
				$id
			)
		);

		if ( $duplicate ) {
			return new WP_REST_Response(
				array(
					'error' => "A template already exists for event '{$nextEvent}' / channel '{$nextChannel}' / recipient '{$nextRecipient}'.",
				),
				409
			);
		}

		$update = array_filter(
			array(
				'event_type' => $data['event'] ?? null,
				'channel'    => $data['channel'] ?? null,
				'recipient_type' => $data['recipient'] ?? null,
				'subject'    => $data['subject'] ?? null,
				'body'       => $data['body'] ?? null,
				'is_active'  => isset( $data['is_active'] ) ? (int) $data['is_active'] : null,
				'updated_at' => current_time( 'mysql' ),
			),
			fn( $v ) => $v !== null
		);

		$wpdb->update( "{$wpdb->prefix}erta_notification_templates", $update, array( 'id' => $id ) );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}erta_notification_templates WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		return new WP_REST_Response( $this->normalizeTemplateRow( $row ) );
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	private function extractFields( WP_REST_Request $request, bool $partial = false ): array {
		$out = array();

		if ( ! $partial || $request->has_param( 'event' ) ) {
			$out['event'] = sanitize_key( $request->get_param( 'event' ) ?? '' );
		}
		if ( ! $partial || $request->has_param( 'channel' ) ) {
			$out['channel'] = sanitize_key( $request->get_param( 'channel' ) ?? 'email' );
		}
		if ( ! $partial || $request->has_param( 'recipient' ) ) {
			$out['recipient'] = sanitize_key( $request->get_param( 'recipient' ) ?? 'customer' );
		}
		if ( ! $partial || $request->has_param( 'subject' ) ) {
			$out['subject'] = sanitize_text_field( $request->get_param( 'subject' ) ?? '' );
		}
		if ( ! $partial || $request->has_param( 'body' ) ) {
			// Body may contain HTML — wp_kses_post strips dangerous tags but keeps formatting.
			$out['body'] = wp_kses_post( $request->get_param( 'body' ) ?? '' );
		}
		if ( ! $partial || $request->has_param( 'is_active' ) ) {
			$out['is_active'] = (bool) ( $request->get_param( 'is_active' ) ?? true );
		}

		return $out;
	}

	private function validate( array $data, bool $partial = false ): array {
		$errors = array();

		if ( ! $partial ) {
			// Full create: all required fields must be present.
			if ( empty( $data['event'] ) ) {
				$errors[] = 'event is required.';
			} elseif ( ! in_array( $data['event'], self::ALLOWED_EVENTS, true ) ) {
				$errors[] = __( 'Invalid event.', 'ert-appointment' ) . ' Allowed: ' . implode( ', ', self::ALLOWED_EVENTS );
			}

			if ( ! in_array( $data['channel'] ?? '', self::ALLOWED_CHANNELS, true ) ) {
				$errors[] = 'channel must be email or sms.';
			}

			if ( ! in_array( $data['recipient'] ?? '', self::ALLOWED_RECIPIENTS, true ) ) {
				$errors[] = 'recipient must be customer, provider, or admin.';
			}

			if ( empty( $data['body'] ) ) {
				$errors[] = 'body is required.';
			}
		} else {
			// Partial update: validate only what was provided.
			if ( isset( $data['event'] ) && ! in_array( $data['event'], self::ALLOWED_EVENTS, true ) ) {
				$errors[] = __( 'Invalid event.', 'ert-appointment' );
			}
			if ( isset( $data['channel'] ) && ! in_array( $data['channel'], self::ALLOWED_CHANNELS, true ) ) {
				$errors[] = __( 'Invalid channel.', 'ert-appointment' );
			}
			if ( isset( $data['recipient'] ) && ! in_array( $data['recipient'], self::ALLOWED_RECIPIENTS, true ) ) {
				$errors[] = __( 'Invalid recipient.', 'ert-appointment' );
			}
		}

		// Warn if body references unknown placeholders (non-blocking).
		// (Could be extended to return warnings alongside the saved data.)

		return $errors;
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private function normalizeTemplateRow( array $row ): array {
		if ( isset( $row['event_type'] ) && ! isset( $row['event'] ) ) {
			$row['event'] = $row['event_type'];
		}

		if ( isset( $row['recipient_type'] ) && ! isset( $row['recipient'] ) ) {
			$row['recipient'] = $row['recipient_type'];
		}

		return $row;
	}
}
