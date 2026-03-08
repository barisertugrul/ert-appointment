<?php declare(strict_types=1);
namespace ERTAppointment\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ERTAppointment\Domain\Form\FormRepository;

final class FormApiController {

	public function __construct( private readonly FormRepository $repository ) {}

	public function get( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$scope   = sanitize_key( (string) ( $request->get_param( 'scope' ) ?? 'global' ) );
		$scopeId = $request->get_param( 'scope_id' ) ? (int) $request->get_param( 'scope_id' ) : null;

		if ( $scope === 'id' ) {
			if ( ! $scopeId ) {
				return new WP_Error( 'erta_bad_request', __( 'Form ID is required.', 'ert-appointment' ), array( 'status' => 400 ) );
			}

			$form = $this->repository->findById( $scopeId );
		} else {
			$form = $this->repository->findForScope( $scope, $scopeId );
		}

		if ( ! $form ) {
			return new WP_Error( 'erta_not_found', __( 'No form found for this scope.', 'ert-appointment' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response(
			array(
				'id'                 => $form->id,
				'name'               => $form->name,
				'scope'              => $form->scope,
				'fields'             => $form->fields,
				'submit_button_text' => $this->getSubmitButtonText( $form->id ),
				'department_label'   => $this->getDepartmentLabel( $form->id ),
				'provider_label'     => $this->getProviderLabel( $form->id ),
				'ui_styles'          => $this->getUiStyles( $form->id ),
			)
		);
	}

	private function getSubmitButtonText( ?int $formId ): string {
		if ( ! $formId || $formId <= 0 ) {
			return '';
		}

		$value = get_option( 'erta_form_submit_button_' . (int) $formId, '' );
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	private function getDepartmentLabel( ?int $formId ): string {
		if ( ! $formId || $formId <= 0 ) {
			return '';
		}

		$value = get_option( 'erta_form_department_label_' . (int) $formId, '' );
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	private function getProviderLabel( ?int $formId ): string {
		if ( ! $formId || $formId <= 0 ) {
			return '';
		}

		$value = get_option( 'erta_form_provider_label_' . (int) $formId, '' );
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * @return array<string, string>
	 */
	private function getUiStyles( ?int $formId ): array {
		if ( ! $formId || $formId <= 0 ) {
			return array();
		}

		$value = get_option( 'erta_form_ui_styles_' . (int) $formId, array() );
		if ( ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $value as $key => $styleValue ) {
			$styleKey = sanitize_key( (string) $key );
			$styleText = sanitize_text_field( (string) $styleValue );
			if ( $styleKey === '' || $styleText === '' ) {
				continue;
			}

			$sanitized[ $styleKey ] = $styleText;
		}

		return $sanitized;
	}
}
