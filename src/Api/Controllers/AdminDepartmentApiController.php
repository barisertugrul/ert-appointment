<?php

declare(strict_types=1);

namespace ERTAppointment\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use ERTAppointment\Domain\Department\Department;
use ERTAppointment\Domain\Department\DepartmentRepository;

/**
 * Admin REST endpoints for department management.
 *
 * Routes:
 *  GET    /erta/v1/admin/departments
 *  POST   /erta/v1/admin/departments
 *  PUT    /erta/v1/admin/departments/{id}
 *  DELETE /erta/v1/admin/departments/{id}
 */
final class AdminDepartmentApiController
{
    public function __construct(
        private readonly DepartmentRepository $departments
    ) {}

    // ── List ──────────────────────────────────────────────────────────────

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $items = $this->departments->findAll();

        return new WP_REST_Response([
            'items' => array_map(fn(Department $d) => $d->toArray(), $items),
            'total' => count($items),
        ]);
    }

    // ── Create ────────────────────────────────────────────────────────────

    public function create(WP_REST_Request $request): WP_REST_Response
    {
        $data = $this->extractFields($request);
        $errors = $this->validate($data);

        if ($errors) {
            return new WP_REST_Response(['error' => implode(' ', $errors)], 422);
        }

        // Auto-generate slug if not provided.
        if (empty($data['slug'])) {
            $data['slug'] = sanitize_title($data['name']);
        }

        // Ensure slug is unique.
        $data['slug'] = $this->uniqueSlug($data['slug']);

        $department = Department::create(
            name:        $data['name'],
            slug:        $data['slug'],
            description: $data['description'] ?? '',
            status:      $data['status']       ?? 'active',
            sortOrder:   (int) ($data['sort_order'] ?? 0),
        );

        $saved = $this->departments->save($department);

        return new WP_REST_Response($saved->toArray(), 201);
    }

    // ── Update ────────────────────────────────────────────────────────────

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $dept = $this->departments->findById($id);

        if (! $dept) {
            return new WP_REST_Response(['error' => 'Department not found.'], 404);
        }

        $data   = $this->extractFields($request);
        $errors = $this->validate($data, $id);

        if ($errors) {
            return new WP_REST_Response(['error' => implode(' ', $errors)], 422);
        }

        $updated = $dept->with([
            'name'        => $data['name']        ?? $dept->name,
            'slug'        => $data['slug']        ?? $dept->slug,
            'description' => $data['description'] ?? $dept->description,
            'status'      => $data['status']      ?? $dept->status,
            'sort_order'  => (int) ($data['sort_order'] ?? $dept->sortOrder),
        ]);

        $saved = $this->departments->save($updated);

        return new WP_REST_Response($saved->toArray());
    }

    // ── Delete ────────────────────────────────────────────────────────────

    public function delete(WP_REST_Request $request): WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $dept = $this->departments->findById($id);

        if (! $dept) {
            return new WP_REST_Response(['error' => 'Department not found.'], 404);
        }

        // Prevent deletion if providers are still assigned.
        if ($this->hasProviders($id)) {
            return new WP_REST_Response([
                'error' => 'Cannot delete department with assigned providers. Reassign or delete providers first.',
            ], 409);
        }

        $this->departments->delete($id);

        return new WP_REST_Response(['deleted' => true, 'id' => $id]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function extractFields(WP_REST_Request $request): array
    {
        return [
            'name'        => sanitize_text_field($request->get_param('name')        ?? ''),
            'slug'        => sanitize_title($request->get_param('slug')             ?? ''),
            'description' => sanitize_textarea_field($request->get_param('description') ?? ''),
            'status'      => sanitize_key($request->get_param('status')             ?? 'active'),
            'sort_order'  => (int) ($request->get_param('sort_order')               ?? 0),
        ];
    }

    private function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = 'Name is required.';
        }

        $allowedStatuses = ['active', 'inactive'];
        if (! in_array($data['status'] ?? 'active', $allowedStatuses, true)) {
            $errors[] = 'Invalid status. Allowed: ' . implode(', ', $allowedStatuses);
        }

        return $errors;
    }

    private function uniqueSlug(string $base, ?int $excludeId = null): string
    {
        global $wpdb;

        $slug    = $base;
        $counter = 1;

        while (true) {
            $query = $excludeId
                ? $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}erta_departments WHERE slug = %s AND id != %d",
                    $slug, $excludeId
                )
                : $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}erta_departments WHERE slug = %s",
                    $slug
                );

            if (! $wpdb->get_var($query)) break;

            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    private function hasProviders(int $departmentId): bool
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}erta_providers WHERE department_id = %d AND status != 'deleted'",
            $departmentId
        ));
    }
}
