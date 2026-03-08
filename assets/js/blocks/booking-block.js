( function () {
	const { registerBlockType } = wp.blocks;
	const { __ } = wp.i18n;
	const { createElement: el, Fragment } = wp.element;
	const blockEditor = wp.blockEditor || wp.editor;
	const { InspectorControls, useBlockProps } = blockEditor;
	const { PanelBody, SelectControl } = wp.components;

	const forms = Array.isArray( window.ertaBookingBlockData?.forms )
		? window.ertaBookingBlockData.forms
		: [];

	const formOptions = [
		{ label: __( 'Auto (Global/Scope Resolution)', 'ert-appointment' ), value: 0 },
		...forms.map( ( form ) => {
			const scopeLabel = form.scope === 'global'
				? __( 'Global', 'ert-appointment' )
				: form.scope === 'department'
					? __( 'Department', 'ert-appointment' )
					: __( 'Provider', 'ert-appointment' );

			return {
				label: `#${ form.id } — ${ form.name } (${ scopeLabel })`,
				value: Number( form.id ) || 0,
			};
		} ),
	];

	registerBlockType( 'ert-appointment/booking', {
		apiVersion: 2,
		title: __( 'ERT Appointment Booking', 'ert-appointment' ),
		description: __( 'Embed the ERT booking form and optionally choose a saved form.', 'ert-appointment' ),
		icon: 'calendar-alt',
		category: 'widgets',
		supports: {
			html: false,
		},
		attributes: {
			formId: {
				type: 'number',
				default: 0,
			},
			bookingMode: {
				type: 'string',
				default: '',
			},
		},
		edit: ( { attributes, setAttributes } ) => {
			const { formId, bookingMode } = attributes;
			const blockProps = useBlockProps( {
				className: 'erta-booking-block-preview',
			} );

			const selectedForm = forms.find( ( form ) => Number( form.id ) === Number( formId ) );
			const previewText = selectedForm
				? __( 'Selected form:', 'ert-appointment' ) + ' ' + selectedForm.name
				: __( 'Selected form: automatic resolution (global/department/provider).', 'ert-appointment' );

			const modeOptions = [
				{ label: __( 'Auto (from plugin settings)', 'ert-appointment' ), value: '' },
				{ label: __( 'General booking (no department, no provider)', 'ert-appointment' ), value: 'general' },
				{ label: __( 'Department-based (without provider selection)', 'ert-appointment' ), value: 'department_no_provider' },
				{ label: __( 'Department-based (with provider selection)', 'ert-appointment' ), value: 'department_with_provider' },
				{ label: __( 'Provider-based (without department step)', 'ert-appointment' ), value: 'provider_only' },
			];

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Booking Block Settings', 'ert-appointment' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Form', 'ert-appointment' ),
							value: Number( formId ) || 0,
							options: formOptions,
							onChange: ( value ) => setAttributes( { formId: Number( value ) || 0 } ),
						} ),
						el( SelectControl, {
							label: __( 'Booking Mode Override', 'ert-appointment' ),
							value: bookingMode || '',
							options: modeOptions,
							onChange: ( value ) => setAttributes( { bookingMode: value || '' } ),
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( 'strong', null, __( 'ERT Appointment Booking', 'ert-appointment' ) ),
					el( 'p', null, previewText )
				)
			);
		},
		save: () => null,
	} );
}() );
