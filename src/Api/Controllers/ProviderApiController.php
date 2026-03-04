<?php declare(strict_types=1);
namespace ERTAppointment\Api\Controllers;

use WP_REST_Request; use WP_REST_Response; use WP_Error;
use ERTAppointment\Domain\Provider\ProviderRepository;

final class ProviderApiController
{
    public function __construct(private readonly ProviderRepository $repository) {}

    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $departmentId = $request->get_param('department_id');
        $providers = $departmentId !== null
            ? $this->repository->findByDepartment((int) $departmentId)
            : $this->repository->findAll();

        return new WP_REST_Response(array_map([$this, 'format'], $providers));
    }

    public function get(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $provider = $this->repository->findById((int) $request->get_param('id'));
        if (!$provider) return new WP_Error('erta_not_found', __('Provider not found.', 'ert-appointment'), ['status' => 404]);
        return new WP_REST_Response($this->format($provider));
    }

    private function format(\ERTAppointment\Domain\Provider\Provider $p): array
    {
        return [
            'id'            => $p->id,
            'department_id' => $p->departmentId,
            'type'          => $p->type,
            'name'          => $p->name,
            'description'   => $p->description,
            'avatar_url'    => $p->avatarUrl,
        ];
    }
}
