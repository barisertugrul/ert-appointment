<?php

declare(strict_types=1);

namespace ERTAppointment\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use ERTAppointment\Domain\Form\Form;
use ERTAppointment\Domain\Form\FormRepository;

/**
 * Admin REST endpoints for dynamic form management.
 *
 * Routes:
 *  GET    /erta/v1/admin/forms
 *  POST   /erta/v1/admin/forms
 *  PUT    /erta/v1/admin/forms/{id}
 *  DELETE /erta/v1/admin/forms/{id}
 */
final class AdminFormApiController
{
    // Field types the form builder supports.
    private const ALLOWED_FIELD_TYPES = [
        'text', 'email', 'tel', 'number', 'date', 'textarea', 'select', 'checkbox', 'calendar',
    ];

    public function __construct(
        private readonly FormRepository $forms
    ) {}

    // ── List ──────────────────────────────────────────────────────────────

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        // FormRepository may only have findForScope; query all directly.
        $rows = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- no user input, static query
            "SELECT * FROM {$wpdb->prefix}erta_forms ORDER BY scope ASC, id ASC",
            ARRAY_A
        );

        $items = array_map(fn(array $row) => $this->decodeRow($row), $rows ?: []);

        return new WP_REST_Response($items);
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
        $wpdb->insert("{$wpdb->prefix}erta_forms", [
            'scope'      => $data['scope'],
            'scope_id'   => $data['scope_id'],
            'name'       => $data['name'],
            'fields'     => wp_json_encode($data['fields']),
            'is_active'  => 1,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        $id = $wpdb->insert_id;

        return new WP_REST_Response(
            $this->decodeRow($wpdb->get_row("SELECT * FROM {$wpdb->prefix}erta_forms WHERE id = {$id}", ARRAY_A)),
            201
        );
    }

    // ── Update ────────────────────────────────────────────────────────────

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        global $wpdb;
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}erta_forms WHERE id = %d", $id),
            ARRAY_A
        );

        if (! $existing) {
            return new WP_REST_Response(['error' => 'Form not found.'], 404);
        }

        $data   = $this->extractFields($request);
        $errors = $this->validate($data);

        if ($errors) {
            return new WP_REST_Response(['error' => implode(' ', $errors)], 422);
        }

        $wpdb->update(
            "{$wpdb->prefix}erta_forms",
            [
                'scope'      => $data['scope']    ?? $existing['scope'],
                'scope_id'   => $data['scope_id'] ?? $existing['scope_id'],
                'name'       => $data['name']      ?? $existing['name'],
                'fields'     => wp_json_encode($data['fields'] ?? json_decode($existing['fields'], true)),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id]
        );

        $updated = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}erta_forms WHERE id = %d", $id),
            ARRAY_A
        );

        return new WP_REST_Response($this->decodeRow($updated));
    }

    // ── Delete ────────────────────────────────────────────────────────────

    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        global $wpdb;
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$wpdb->prefix}erta_forms WHERE id = %d", $id)
        );

        if (! $existing) {
            return new WP_REST_Response(['error' => 'Form not found.'], 404);
        }

        $wpdb->delete("{$wpdb->prefix}erta_forms", ['id' => $id]);

        return new WP_REST_Response(['deleted' => true, 'id' => $id]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function extractFields(WP_REST_Request $request): array
    {
        $rawFields = $request->get_param('fields');
        $fields    = is_array($rawFields) ? $this->sanitizeFields($rawFields) : [];

        return [
            'name'     => sanitize_text_field($request->get_param('name')     ?? ''),
            'scope'    => sanitize_key($request->get_param('scope')            ?? 'global'),
            'scope_id' => (int) ($request->get_param('scope_id')               ?? 0),
            'fields'   => $fields,
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = 'Form name is required.';
        }

        if (! in_array($data['scope'], ['global', 'department', 'provider'], true)) {
            $errors[] = 'scope must be global, department, or provider.';
        }

        if (empty($data['fields'])) {
            $errors[] = 'At least one field is required.';
        }

        // Must have exactly one calendar placeholder.
        $calendarCount = count(array_filter($data['fields'], fn($f) => ($f['type'] ?? '') === 'calendar'));
        if ($calendarCount === 0) {
            $errors[] = 'Form must contain a calendar (date/time) field.';
        } elseif ($calendarCount > 1) {
            $errors[] = 'Form can only have one calendar field.';
        }

        return $errors;
    }

    /**
     * Sanitizes a fields array coming from the form builder.
     * Strips unknown keys and ensures each field has the minimum required attributes.
     */
    private function sanitizeFields(array $rawFields): array
    {
        $sanitized = [];

        foreach ($rawFields as $field) {
            if (! is_array($field)) continue;

            $type = sanitize_key($field['type'] ?? 'text');
            if (! in_array($type, self::ALLOWED_FIELD_TYPES, true)) continue;

            $clean = [
                'id'       => sanitize_key($field['id']    ?? 'field_' . uniqid()),
                'type'     => $type,
                'label'    => sanitize_text_field($field['label']       ?? ''),
                'required' => (bool) ($field['required']                ?? false),
                'system'   => (bool) ($field['system']                  ?? false),
            ];

            if (! empty($field['placeholder'])) {
                $clean['placeholder'] = sanitize_text_field($field['placeholder']);
            }

            if (! empty($field['help'])) {
                $clean['help'] = sanitize_text_field($field['help']);
            }

            // For select: sanitize options array.
            if ($type === 'select' && ! empty($field['options']) && is_array($field['options'])) {
                $clean['options'] = array_values(array_map(fn($opt) => [
                    'label' => sanitize_text_field($opt['label'] ?? ''),
                    'value' => sanitize_key($opt['value']        ?? ''),
                ], $field['options']));
            }

            $sanitized[] = $clean;
        }

        return $sanitized;
    }

    private function decodeRow(array $row): array
    {
        $row['fields'] = json_decode($row['fields'] ?? '[]', true) ?? [];
        return $row;
    }
}
