<?php

declare(strict_types=1);

namespace ERTAppointment\Core;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- block editor form list intentionally reads plugin-owned custom table.

/**
 * Registers Gutenberg blocks for ERT Appointment.
 */
final class Blocks {

	public function __construct(
		private readonly Assets $assets,
		private readonly Shortcodes $shortcodes
	) {}

	public function register(): void {
		$this->registerEditorAssets();

		register_block_type(
			'ert-appointment/booking',
			array(
				'api_version'     => 2,
				'editor_script'   => 'erta-booking-block',
				'render_callback' => array( $this, 'renderBookingBlock' ),
				'attributes'      => array(
					'formId'          => array(
						'type'    => 'number',
						'default' => 0,
					),
					'bookingMode'     => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public function renderBookingBlock( array $attributes ): string {
		$this->assets->enqueueFrontendForced();

		$shortcodeAttributes = array();

		$formId = isset( $attributes['formId'] ) ? (int) $attributes['formId'] : 0;
		if ( $formId > 0 ) {
			$shortcodeAttributes['form'] = (string) $formId;
		}

		$bookingMode = isset( $attributes['bookingMode'] ) ? sanitize_key( (string) $attributes['bookingMode'] ) : '';
		if ( $bookingMode !== '' ) {
			$shortcodeAttributes['booking_mode'] = $bookingMode;
		}

		return $this->shortcodes->renderBookingWidget( $shortcodeAttributes );
	}

	private function registerEditorAssets(): void {
		wp_register_script(
			'erta-booking-block',
			ERTA_URL . 'assets/js/blocks/booking-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			ERTA_VERSION,
			true
		);

		wp_localize_script(
			'erta-booking-block',
			'ertaBookingBlockData',
			array(
				'forms' => $this->getRegisteredForms(),
			)
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function getRegisteredForms(): array {
		global $wpdb;

		if ( ! ( $wpdb instanceof \wpdb ) ) {
			return array();
		}

		$table = $wpdb->prefix . 'erta_forms';
		$rows  = $wpdb->get_results(
			$wpdb->prepare( 'SELECT id, name, scope, scope_id FROM %i ORDER BY scope ASC, id ASC', $table ),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_values(
			array_map(
				static function ( array $row ): array {
					return array(
						'id'       => isset( $row['id'] ) ? (int) $row['id'] : 0,
						'name'     => sanitize_text_field( (string) ( $row['name'] ?? '' ) ),
						'scope'    => sanitize_key( (string) ( $row['scope'] ?? 'global' ) ),
						'scope_id' => isset( $row['scope_id'] ) ? (int) $row['scope_id'] : 0,
					);
				},
				$rows
			)
		);
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
