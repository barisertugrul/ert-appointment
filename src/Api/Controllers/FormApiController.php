<?php declare(strict_types=1);
namespace ERTAppointment\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ERTAppointment\Domain\Form\FormRepository;

final class FormApiController {

	public function __construct( private readonly FormRepository $repository ) {}

	public function get( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$scope   = $request->get_param( 'scope' ) ?? 'global';
		$scopeId = $request->get_param( 'scope_id' ) ? (int) $request->get_param( 'scope_id' ) : null;

		$form = $this->repository->findForScope( $scope, $scopeId );

		if ( ! $form ) {
			return new WP_Error( 'erta_not_found', __( 'No form found for this scope.', 'ert-appointment' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response(
			array(
				'id'     => $form->id,
				'name'   => $form->name,
				'scope'  => $form->scope,
				'fields' => $form->fields,
			)
		);
	}
}
