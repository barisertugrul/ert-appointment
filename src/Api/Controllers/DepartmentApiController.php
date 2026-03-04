<?php declare(strict_types=1);
namespace ERTAppointment\Api\Controllers;

use WP_REST_Request; use WP_REST_Response;
use ERTAppointment\Domain\Department\DepartmentRepository;

final class DepartmentApiController
{
    public function __construct(private readonly DepartmentRepository $repository) {}

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $departments = $this->repository->findAll();
        return new WP_REST_Response(array_map(fn($d) => [
            'id' => $d->id, 'name' => $d->name, 'slug' => $d->slug, 'description' => $d->description,
        ], $departments));
    }
}
