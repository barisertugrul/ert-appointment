<?php

declare(strict_types=1);

namespace ERTAppointment\Api\Controllers;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- admin form endpoints intentionally query plugin-owned custom form table.

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
final class AdminFormApiController {

	// Field types the form builder supports.
	private const ALLOWED_FIELD_TYPES = array(
		'text',
		'email',
		'tel',
		'number',
		'date',
		'textarea',
		'select',
		'checkbox',
		'calendar',
	);

	public function __construct(
		private readonly FormRepository $forms
	) {}

	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'erta_forms';
	}

	private function tableSql(): string {
		return esc_sql( $this->table() );
	}

	// ── List ──────────────────────────────────────────────────────────────

	public function index( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$table = $this->table();

		// FormRepository may only have findForScope; query all directly.
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY scope ASC, id ASC', $table ),
			ARRAY_A
		);

		$items = array_map( fn( array $row ) => $this->decodeRow( $row ), $rows ?: array() );

		return new WP_REST_Response( $items );
	}

	// ── Create ────────────────────────────────────────────────────────────

	public function create( WP_REST_Request $request ): WP_REST_Response {
		$data   = $this->extractFields( $request );
		$errors = $this->validate( $data );

		if ( $errors ) {
			return new WP_REST_Response( array( 'error' => implode( ' ', $errors ) ), 422 );
		}

		global $wpdb;
		$table = $this->table();
		$wpdb->insert(
			$table,
			array(
				'scope'      => $data['scope'],
				'scope_id'   => $data['scope_id'],
				'name'       => $data['name'],
				'fields'     => wp_json_encode( $data['fields'] ),
				'is_active'  => 1,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			)
		);

		$id = $wpdb->insert_id;
		$this->persistSubmitButtonText( (int) $id, (string) ( $data['submit_button_text'] ?? '' ) );
		$this->persistExtraMeta( (int) $id, $data );
		$formTable = $this->table();
		$created = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $formTable, (int) $id ),
			ARRAY_A
		);

		return new WP_REST_Response(
			$this->decodeRow( $created ?: array() ),
			201
		);
	}

	// ── Update ────────────────────────────────────────────────────────────

	public function update( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		global $wpdb;
		$table    = $this->table();
		$tableSql = $this->table();
		$existing = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $tableSql, $id ),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_REST_Response( array( 'error' => 'Form not found.' ), 404 );
		}

		$data   = $this->extractFields( $request );
		$errors = $this->validate( $data );

		if ( $errors ) {
			return new WP_REST_Response( array( 'error' => implode( ' ', $errors ) ), 422 );
		}

		$wpdb->update(
			$table,
			array(
				'scope'      => $data['scope'] ?? $existing['scope'],
				'scope_id'   => $data['scope_id'] ?? $existing['scope_id'],
				'name'       => $data['name'] ?? $existing['name'],
				'fields'     => wp_json_encode( $data['fields'] ?? json_decode( $existing['fields'], true ) ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id )
		);

		$this->persistSubmitButtonText( $id, (string) ( $data['submit_button_text'] ?? '' ) );
		$this->persistExtraMeta( $id, $data );

		$updated = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $tableSql, $id ),
			ARRAY_A
		);

		return new WP_REST_Response( $this->decodeRow( $updated ) );
	}

	// ── Delete ────────────────────────────────────────────────────────────

	public function delete( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		global $wpdb;
		$table    = $this->table();
		$tableSql = $this->table();
		$existing = $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM %i WHERE id = %d', $tableSql, $id )
		);

		if ( ! $existing ) {
			return new WP_REST_Response( array( 'error' => 'Form not found.' ), 404 );
		}

		$wpdb->delete( $table, array( 'id' => $id ) );
		$this->deleteSubmitButtonText( $id );
		$this->deleteExtraMeta( $id );

		return new WP_REST_Response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	private function extractFields( WP_REST_Request $request ): array {
		$rawFields = $request->get_param( 'fields' );
		$fields    = is_array( $rawFields ) ? $this->sanitizeFields( $rawFields ) : array();

		return array(
			'name'     => sanitize_text_field( $request->get_param( 'name' ) ?? '' ),
			'scope'    => sanitize_key( $request->get_param( 'scope' ) ?? 'global' ),
			'scope_id' => (int) ( $request->get_param( 'scope_id' ) ?? 0 ),
			'fields'   => $fields,
			'submit_button_text' => sanitize_text_field( (string) ( $request->get_param( 'submit_button_text' ) ?? '' ) ),
			'department_label'   => sanitize_text_field( (string) ( $request->get_param( 'department_label' ) ?? '' ) ),
			'provider_label'     => sanitize_text_field( (string) ( $request->get_param( 'provider_label' ) ?? '' ) ),
			'ui_styles'          => $this->sanitizeUiStyles( $request->get_param( 'ui_styles' ) ),
		);
	}

	private function validate( array $data ): array {
		$errors = array();

		if ( empty( $data['name'] ) ) {
			$errors[] = 'Form name is required.';
		}

		if ( ! in_array( $data['scope'], array( 'global', 'department', 'provider' ), true ) ) {
			$errors[] = 'scope must be global, department, or provider.';
		}

		if ( empty( $data['fields'] ) ) {
			$errors[] = 'At least one field is required.';
		}

		// Must have exactly one calendar placeholder.
		$calendarCount = count( array_filter( $data['fields'], fn( $f ) => ( $f['type'] ?? '' ) === 'calendar' ) );
		if ( $calendarCount === 0 ) {
			$errors[] = 'Form must contain a calendar (date/time) field.';
		} elseif ( $calendarCount > 1 ) {
			$errors[] = 'Form can only have one calendar field.';
		}

		return $errors;
	}

	/**
	 * Sanitizes a fields array coming from the form builder.
	 * Strips unknown keys and ensures each field has the minimum required attributes.
	 */
	private function sanitizeFields( array $rawFields ): array {
		$sanitized = array();

		foreach ( $rawFields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$type = sanitize_key( $field['type'] ?? 'text' );
			if ( ! in_array( $type, self::ALLOWED_FIELD_TYPES, true ) ) {
				continue;
			}

			$clean = array(
				'id'       => sanitize_key( $field['id'] ?? 'field_' . uniqid() ),
				'type'     => $type,
				'label'    => sanitize_text_field( $field['label'] ?? '' ),
				'required' => (bool) ( $field['required'] ?? false ),
				'system'   => (bool) ( $field['system'] ?? false ),
			);

			if ( ! empty( $field['placeholder'] ) ) {
				$clean['placeholder'] = sanitize_text_field( $field['placeholder'] );
			}

			if ( ! empty( $field['help'] ) ) {
				$clean['help'] = sanitize_text_field( $field['help'] );
			}

			// For select: sanitize options array.
			if ( $type === 'select' && ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
				$clean['options'] = array_values(
					array_map(
						fn( $opt ) => array(
							'label' => sanitize_text_field( $opt['label'] ?? '' ),
							'value' => sanitize_key( $opt['value'] ?? '' ),
						),
						$field['options']
					)
				);
			}

			$sanitized[] = $clean;
		}

		return $sanitized;
	}

	private function decodeRow( array $row ): array {
		$row['fields'] = json_decode( $row['fields'] ?? '[]', true ) ?? array();
		$row['submit_button_text'] = $this->getSubmitButtonText( isset( $row['id'] ) ? (int) $row['id'] : 0 );
		$row['department_label'] = $this->getDepartmentLabel( isset( $row['id'] ) ? (int) $row['id'] : 0 );
		$row['provider_label'] = $this->getProviderLabel( isset( $row['id'] ) ? (int) $row['id'] : 0 );
		$row['ui_styles'] = $this->getUiStyles( isset( $row['id'] ) ? (int) $row['id'] : 0 );
		return $row;
	}

	private function submitButtonOptionKey( int $formId ): string {
		return 'erta_form_submit_button_' . $formId;
	}

	private function departmentLabelOptionKey( int $formId ): string {
		return 'erta_form_department_label_' . $formId;
	}

	private function providerLabelOptionKey( int $formId ): string {
		return 'erta_form_provider_label_' . $formId;
	}

	private function uiStylesOptionKey( int $formId ): string {
		return 'erta_form_ui_styles_' . $formId;
	}

	private function getSubmitButtonText( int $formId ): string {
		if ( $formId <= 0 ) {
			return '';
		}

		$value = get_option( $this->submitButtonOptionKey( $formId ), '' );
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	private function persistSubmitButtonText( int $formId, string $submitButtonText ): void {
		if ( $formId <= 0 ) {
			return;
		}

		$sanitized = sanitize_text_field( $submitButtonText );
		if ( $sanitized === '' ) {
			$this->deleteSubmitButtonText( $formId );
			return;
		}

		update_option( $this->submitButtonOptionKey( $formId ), $sanitized, false );
	}

	private function getDepartmentLabel( int $formId ): string {
		if ( $formId <= 0 ) {
			return '';
		}

		$externalValue = $this->getExternalMeta( $formId, 'department_label' );
		if ( $externalValue !== null ) {
			return is_string( $externalValue ) ? sanitize_text_field( $externalValue ) : '';
		}

		$value = get_option( $this->departmentLabelOptionKey( $formId ), '' );
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	private function persistDepartmentLabel( int $formId, string $departmentLabel ): void {
		if ( $formId <= 0 ) {
			return;
		}

		$sanitized = sanitize_text_field( $departmentLabel );
		if ( $this->setExternalMeta( $formId, 'department_label', $sanitized ) ) {
			return;
		}

		if ( $sanitized === '' ) {
			$this->deleteDepartmentLabel( $formId );
			return;
		}

		update_option( $this->departmentLabelOptionKey( $formId ), $sanitized, false );
	}

	private function deleteDepartmentLabel( int $formId ): void {
		if ( $formId <= 0 ) {
			return;
		}

		if ( $this->deleteExternalMeta( $formId, 'department_label' ) ) {
			return;
		}

		delete_option( $this->departmentLabelOptionKey( $formId ) );
	}

	private function getProviderLabel( int $formId ): string {
		if ( $formId <= 0 ) {
			return '';
		}

		$externalValue = $this->getExternalMeta( $formId, 'provider_label' );
		if ( $externalValue !== null ) {
			return is_string( $externalValue ) ? sanitize_text_field( $externalValue ) : '';
		}

		$value = get_option( $this->providerLabelOptionKey( $formId ), '' );
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	private function persistProviderLabel( int $formId, string $providerLabel ): void {
		if ( $formId <= 0 ) {
			return;
		}

		$sanitized = sanitize_text_field( $providerLabel );
		if ( $this->setExternalMeta( $formId, 'provider_label', $sanitized ) ) {
			return;
		}

		if ( $sanitized === '' ) {
			$this->deleteProviderLabel( $formId );
			return;
		}

		update_option( $this->providerLabelOptionKey( $formId ), $sanitized, false );
	}

	private function deleteProviderLabel( int $formId ): void {
		if ( $formId <= 0 ) {
			return;
		}

		if ( $this->deleteExternalMeta( $formId, 'provider_label' ) ) {
			return;
		}

		delete_option( $this->providerLabelOptionKey( $formId ) );
	}

	/**
	 * @param mixed $styles
	 * @return array<string, string>
	 */
	private function sanitizeUiStyles( mixed $styles ): array {
		if ( ! is_array( $styles ) ) {
			return array();
		}

		$allowed = array(
			'primary_color',
			'panel_background',
			'panel_radius',
			'button_radius',
			'input_radius',
			'title_font_size',
			'body_font_size',
			'card_border_width',
			'card_border_color',
		);

		$sanitized = array();
		foreach ( $styles as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( ! in_array( $key, $allowed, true ) ) {
				continue;
			}

			$clean = sanitize_text_field( (string) $value );
			if ( $clean === '' ) {
				continue;
			}

			$sanitized[ $key ] = $clean;
		}

		return $sanitized;
	}

	/**
	 * @return array<string, string>
	 */
	private function getUiStyles( int $formId ): array {
		if ( $formId <= 0 ) {
			return array();
		}

		$externalValue = $this->getExternalMeta( $formId, 'ui_styles' );
		if ( is_array( $externalValue ) ) {
			return $this->sanitizeUiStyles( $externalValue );
		}

		$value = get_option( $this->uiStylesOptionKey( $formId ), array() );
		if ( ! is_array( $value ) ) {
			return array();
		}

		return $this->sanitizeUiStyles( $value );
	}

	/**
	 * @param array<string, string> $styles
	 */
	private function persistUiStyles( int $formId, array $styles ): void {
		if ( $formId <= 0 ) {
			return;
		}

		$sanitized = $this->sanitizeUiStyles( $styles );
		if ( $this->setExternalMeta( $formId, 'ui_styles', $sanitized ) ) {
			return;
		}

		if ( empty( $sanitized ) ) {
			$this->deleteUiStyles( $formId );
			return;
		}

		update_option( $this->uiStylesOptionKey( $formId ), $sanitized, false );
	}

	private function deleteUiStyles( int $formId ): void {
		if ( $formId <= 0 ) {
			return;
		}

		if ( $this->deleteExternalMeta( $formId, 'ui_styles' ) ) {
			return;
		}

		delete_option( $this->uiStylesOptionKey( $formId ) );
	}

	/**
	 * @return mixed
	 */
	private function getExternalMeta( int $formId, string $key ): mixed {
		return apply_filters( 'erta_form_meta_get', null, $formId, $key );
	}

	/**
	 * @param mixed $value
	 */
	private function setExternalMeta( int $formId, string $key, mixed $value ): bool {
		return (bool) apply_filters( 'erta_form_meta_set', false, $formId, $key, $value );
	}

	private function deleteExternalMeta( int $formId, string $key ): bool {
		return (bool) apply_filters( 'erta_form_meta_delete', false, $formId, $key );
	}

	private function deleteSubmitButtonText( int $formId ): void {
		if ( $formId <= 0 ) {
			return;
		}

		delete_option( $this->submitButtonOptionKey( $formId ) );
	}

	private function persistExtraMeta( int $formId, array $data ): void {
		$this->persistDepartmentLabel( $formId, (string) ( $data['department_label'] ?? '' ) );
		$this->persistProviderLabel( $formId, (string) ( $data['provider_label'] ?? '' ) );
		$this->persistUiStyles( $formId, is_array( $data['ui_styles'] ?? null ) ? $data['ui_styles'] : array() );
	}

	private function deleteExtraMeta( int $formId ): void {
		$this->deleteDepartmentLabel( $formId );
		$this->deleteProviderLabel( $formId );
		$this->deleteUiStyles( $formId );
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
