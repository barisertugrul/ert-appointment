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
final class NotificationTemplateApiController
{
    private const ALLOWED_EVENTS = [
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
    ];

    private const ALLOWED_CHANNELS = ['email', 'sms'];

    private const ALLOWED_RECIPIENTS = ['customer', 'provider', 'admin'];

    public function __construct(
        private readonly TemplateRenderer $renderer
    ) {}

    // ── List ──────────────────────────────────────────────────────────────

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input, static query
            "SELECT * FROM {$wpdb->prefix}erta_notification_templates
             ORDER BY event ASC, channel ASC, recipient ASC",
            ARRAY_A
        );

        return new WP_REST_Response($rows ?: []);
    }

    // ── Single ────────────────────────────────────────────────────────────

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erta_notification_templates WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (! $row) {
            return new WP_REST_Response(['error' => 'Template not found.'], 404);
        }

        return new WP_REST_Response($row);
    }

    // ── Placeholders hint list ────────────────────────────────────────────

    /**
     * Returns all available {{placeholder}} tokens for the template editor.
     * Pro add-ons extend this via the 'erta_available_placeholder_hints' filter.
     */
    public function placeholders(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->renderer->availablePlaceholders());
    }

    // ── Create ────────────────────────────────────────────────────────────

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        $data   = $this->extractFields($request);
        $errors = $this->validate($data);

        if ($errors) {
            return new WP_REST_Response(['error' => implode(' ', $errors)], 422);
        }

        global $wpdb;

        // Prevent duplicates: one template per event + channel + recipient.
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}erta_notification_templates
             WHERE event = %s AND channel = %s AND recipient = %s",
            $data['event'], $data['channel'], $data['recipient']
        ));

        if ($exists) {
            return new WP_REST_Response([
                'error' => "A template already exists for event '{$data['event']}' / channel '{$data['channel']}' / recipient '{$data['recipient']}'. Use PUT to update it.",
            ], 409);
        }

        $wpdb->insert("{$wpdb->prefix}erta_notification_templates", [
            'event'      => $data['event'],
            'channel'    => $data['channel'],
            'recipient'  => $data['recipient'],
            'subject'    => $data['subject'],
            'body'       => $data['body'],
            'is_active'  => (int) $data['is_active'],
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        $id  = $wpdb->insert_id;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erta_notification_templates WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return new WP_REST_Response($row, 201);
    }

    // ── Update ────────────────────────────────────────────────────────────

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        global $wpdb;
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erta_notification_templates WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (! $existing) {
            return new WP_REST_Response(['error' => 'Template not found.'], 404);
        }

        $data   = $this->extractFields($request, partial: true);
        $errors = $this->validate($data, partial: true);

        if ($errors) {
            return new WP_REST_Response(['error' => implode(' ', $errors)], 422);
        }

        // Merge with existing — only update provided fields.
        $update = array_filter([
            'subject'    => $data['subject']   ?? null,
            'body'       => $data['body']       ?? null,
            'is_active'  => isset($data['is_active']) ? (int) $data['is_active'] : null,
            'updated_at' => current_time('mysql'),
        ], fn($v) => $v !== null);

        $wpdb->update("{$wpdb->prefix}erta_notification_templates", $update, ['id' => $id]);

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erta_notification_templates WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return new WP_REST_Response($row);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function extractFields(WP_REST_Request $request, bool $partial = false): array
    {
        $out = [];

        if (! $partial || $request->has_param('event')) {
            $out['event'] = sanitize_key($request->get_param('event') ?? '');
        }
        if (! $partial || $request->has_param('channel')) {
            $out['channel'] = sanitize_key($request->get_param('channel') ?? 'email');
        }
        if (! $partial || $request->has_param('recipient')) {
            $out['recipient'] = sanitize_key($request->get_param('recipient') ?? 'customer');
        }
        if (! $partial || $request->has_param('subject')) {
            $out['subject'] = sanitize_text_field($request->get_param('subject') ?? '');
        }
        if (! $partial || $request->has_param('body')) {
            // Body may contain HTML — wp_kses_post strips dangerous tags but keeps formatting.
            $out['body'] = wp_kses_post($request->get_param('body') ?? '');
        }
        if (! $partial || $request->has_param('is_active')) {
            $out['is_active'] = (bool) ($request->get_param('is_active') ?? true);
        }

        return $out;
    }

    private function validate(array $data, bool $partial = false): array
    {
        $errors = [];

        if (! $partial) {
            // Full create: all required fields must be present.
            if (empty($data['event'])) {
                $errors[] = 'event is required.';
            } elseif (! in_array($data['event'], self::ALLOWED_EVENTS, true)) {
                $errors[] = 'Invalid event. Allowed: ' . implode(', ', self::ALLOWED_EVENTS);
            }

            if (! in_array($data['channel'] ?? '', self::ALLOWED_CHANNELS, true)) {
                $errors[] = 'channel must be email or sms.';
            }

            if (! in_array($data['recipient'] ?? '', self::ALLOWED_RECIPIENTS, true)) {
                $errors[] = 'recipient must be customer, provider, or admin.';
            }

            if (empty($data['body'])) {
                $errors[] = 'body is required.';
            }
        } else {
            // Partial update: validate only what was provided.
            if (isset($data['event']) && ! in_array($data['event'], self::ALLOWED_EVENTS, true)) {
                $errors[] = 'Invalid event.';
            }
            if (isset($data['channel']) && ! in_array($data['channel'], self::ALLOWED_CHANNELS, true)) {
                $errors[] = 'Invalid channel.';
            }
            if (isset($data['recipient']) && ! in_array($data['recipient'], self::ALLOWED_RECIPIENTS, true)) {
                $errors[] = 'Invalid recipient.';
            }
        }

        // Warn if body references unknown placeholders (non-blocking).
        // (Could be extended to return warnings alongside the saved data.)

        return $errors;
    }
}
