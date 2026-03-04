<?php

declare(strict_types=1);

namespace ERTAppointment\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
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
final class SettingsApiController
{
    // Keys that are allowed to be saved; everything else is silently ignored.
    private const ALLOWED_KEYS = [
        // Scheduling
        'slot_duration_minutes', 'buffer_before_minutes', 'buffer_after_minutes',
        'min_notice_hours', 'max_advance_days', 'auto_confirm', 'arrival_buffer_minutes',

        // Payment
        'payment_required', 'payment_amount', 'payment_gateway',
        'paytr_merchant_id', 'paytr_merchant_key', 'paytr_merchant_salt', 'paytr_test_mode',
        'stripe_secret_key', 'stripe_webhook_secret', 'stripe_publishable_key',
        'paypal_client_id', 'paypal_client_secret', 'paypal_sandbox',
        'iyzico_api_key', 'iyzico_secret_key', 'iyzico_sandbox',

        // Notifications
        'admin_email', 'email_from_name', 'email_from_address',
        'sms_provider', 'twilio_account_sid', 'twilio_auth_token', 'twilio_from_number',
        'netgsm_usercode', 'netgsm_password', 'netgsm_header',

        // Integrations
        'google_client_id', 'google_client_secret',
        'zoom_account_id', 'zoom_client_id', 'zoom_client_secret', 'zoom_auto_create',

        // General
        'currency', 'date_format', 'time_format', 'timezone',
        'cancellation_policy_hours', 'rescheduling_allowed',
    ];

    public function __construct(
        private readonly SettingsManager $settingsManager
    ) {}

    // ── GET ───────────────────────────────────────────────────────────────

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        $scope   = $this->parseScope($request);
        $scopeId = (int) ($request->get_param('scope_id') ?? 0);

        $settings = $this->settingsManager->getAll($scope, $scopeId ?: null);

        return new WP_REST_Response([
            'scope'    => $scope,
            'scope_id' => $scopeId,
            'settings' => $settings,
        ]);
    }

    // ── POST (save) ───────────────────────────────────────────────────────

    public function save(WP_REST_Request $request): WP_REST_Response
    {
        $scope    = $this->parseScope($request);
        $scopeId  = (int) ($request->get_param('scope_id') ?? 0);
        $incoming = $request->get_param('settings');

        if (! is_array($incoming)) {
            return new WP_REST_Response(['error' => 'settings must be an object.'], 400);
        }

        // Filter to allowed keys only.
        $filtered = array_intersect_key($incoming, array_flip(self::ALLOWED_KEYS));

        // Sanitize values.
        $sanitized = $this->sanitize($filtered);

        // Persist — bulkSet handles cache invalidation internally.
        $this->settingsManager->bulkSet($scope, $scopeId ?: null, $sanitized);

        return new WP_REST_Response(['success' => true, 'saved' => count($sanitized)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function parseScope(WP_REST_Request $request): string
    {
        $scope = sanitize_key($request->get_param('scope') ?? 'global');
        return in_array($scope, ['global', 'department', 'provider'], true)
            ? $scope
            : 'global';
    }

    private function sanitize(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $out[$key] = match (true) {
                is_bool($value)                                         => (bool) $value,
                is_int($value)                                          => (int) $value,
                is_float($value)                                        => (float) $value,
                in_array($key, ['google_client_secret', 'paytr_merchant_key',
                                'paytr_merchant_salt',  'stripe_secret_key',
                                'stripe_webhook_secret','zoom_client_secret',
                                'paypal_client_secret', 'twilio_auth_token',
                                'netgsm_password',      'iyzico_secret_key'], true)
                    => sanitize_text_field((string) $value),   // sensitive — no URL/HTML
                default                                                 => sanitize_text_field((string) $value),
            };
        }
        return $out;
    }
}
