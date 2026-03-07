<?php

declare(strict_types=1);

namespace ERTAppointment\Core;

/**
 * Manages script and style enqueueing for both frontend and admin.
 *
 * Frontend: Vue 3 booking widget (loaded only on pages with [erta_booking] shortcode).
 * Admin: Separate Vue admin panel bundle.
 */
final class Assets {

	// -------------------------------------------------------------------------
	// Frontend
	// -------------------------------------------------------------------------

	/**
	 * Enqueues frontend assets.
	 * Scripts are only enqueued when the booking shortcode is present on the page.
	 */
	public function enqueueFrontend(): void {
		if ( ! $this->pageHasBookingShortcode() ) {
			return;
		}

		wp_enqueue_style(
			'erta-frontend',
			ERTA_URL . 'assets/css/frontend.css',
			array(),
			ERTA_VERSION
		);

		wp_enqueue_script(
			'erta-frontend',
			ERTA_URL . 'assets/dist/frontend.js',
			array(),
			ERTA_VERSION,
			true
		);

		wp_localize_script( 'erta-frontend', 'ertaData', $this->frontendConfig() );
	}

	// -------------------------------------------------------------------------
	// Admin
	// -------------------------------------------------------------------------

	/**
	 * Enqueues admin assets only on plugin admin pages.
	 */
	public function enqueueAdmin( string $hookSuffix ): void {
		if ( ! $this->isPluginAdminPage( $hookSuffix ) ) {
			return;
		}

		wp_enqueue_style(
			'erta-admin',
			ERTA_URL . 'assets/css/admin.css',
			array(),
			ERTA_VERSION
		);

		wp_enqueue_script(
			'erta-admin',
			ERTA_URL . 'assets/dist/admin.js',
			array(),
			ERTA_VERSION,
			true
		);

		wp_localize_script( 'erta-admin', 'ertaAdminData', $this->adminConfig() );
	}

	// -------------------------------------------------------------------------
	// Filter
	// -------------------------------------------------------------------------
	/**
	 * script_loader_tag filtresi ile Vite bundle'larını ES module olarak işaretler.
	 *
	 * @param string $tag    Orijinal script tag'i.
	 * @param string $handle Script handle adı.
	 * @param string $src    Script kaynak URL'si.
	 *
	 * @return string Düzenlenmiş script tag'i.
	 */
	public function filterScriptTag( string $tag, string $handle, string $src ): string {
		if ( $handle !== 'erta-admin' && $handle !== 'erta-frontend' ) {
			return $tag;
		}

		// Zaten type attribute'u varsa module'a çevir.
		if ( str_contains( $tag, 'type=' ) ) {
			return (string) preg_replace( '/type=("|\')[^"\']+("|\')/', 'type="module"', $tag );
		}

		// Aksi halde type="module" ekle.
		return str_replace( '<script ', '<script type="module" ', $tag );
	}

	// -------------------------------------------------------------------------
	// Config objects (passed to JS via wp_localize_script)
	// -------------------------------------------------------------------------

	/**
	 * @return array<string, mixed>
	 */
	private function frontendConfig(): array {
		return array(
			'restUrl'    => esc_url_raw( rest_url( 'erta/v1/' ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'siteUrl'    => get_site_url(),
			'dateFormat' => get_option( 'date_format', 'Y-m-d' ),
			'timeFormat' => get_option( 'time_format', 'H:i' ),
			'locale'     => determine_locale(),
			'i18n'       => $this->frontendI18n(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function adminConfig(): array {
		return array(
			'restUrl'    => esc_url_raw( rest_url( 'erta/v1/' ) ),
			'adminUrl'   => admin_url( 'admin.php' ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'dateFormat' => get_option( 'date_format', 'Y-m-d' ),
			'timeFormat' => get_option( 'time_format', 'H:i' ),
			'locale'     => determine_locale(),
			'isPro'      => $this->isProActive(),
			'i18n'       => $this->adminI18n(),
		);
	}

	// -------------------------------------------------------------------------
	// Internals
	// -------------------------------------------------------------------------

	/**
	 * Checks if the current page contains the booking shortcode.
	 * Uses the global $post object.
	 */
	private function pageHasBookingShortcode(): bool {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'erta_booking' )
			|| has_shortcode( $post->post_content, 'erta-booking' )
			|| has_shortcode( $post->post_content, 'erta_my_appointments' )
			|| has_shortcode( $post->post_content, 'erta-my-appointments' );
	}

	/**
	 * Checks if the current screen is a plugin admin page.
	 */
	private function isPluginAdminPage( string $hookSuffix ): bool {
		// Plugin pages all have 'erta-' in their hook suffix.
		return str_contains( $hookSuffix, 'erta-' )
			|| str_contains( $hookSuffix, 'ert-appointment' );
	}

	/**
	 * Returns whether the Pro add-on is active.
	 */
	private function isProActive(): bool {
		return apply_filters( 'erta_is_pro_active', false );
	}

	/**
	 * Frontend translation strings (passed to Vue i18n).
	 *
	 * @return array<string, string>
	 */
	private function frontendI18n(): array {
		return array(
			// ── Booking steps ──────────────────────────────────────────────
			'selectDepartment'      => __( 'Select a Department', 'ert-appointment' ),
			'selectProvider'        => __( 'Select a Provider', 'ert-appointment' ),
			'selectDate'            => __( 'Choose a Date', 'ert-appointment' ),
			'selectTime'            => __( 'Choose a Time', 'ert-appointment' ),
			'fillDetails'           => __( 'Your Details', 'ert-appointment' ),
			'book'                  => __( 'Book Now', 'ert-appointment' ),
			'submit'                => __( 'Submit', 'ert-appointment' ),
			'select'                => __( 'Select', 'ert-appointment' ),
			'back'                  => __( 'Back', 'ert-appointment' ),
			'next'                  => __( 'Next', 'ert-appointment' ),
			'cancel'                => __( 'Cancel', 'ert-appointment' ),
			'confirm'               => __( 'Confirm', 'ert-appointment' ),
			'loading'               => __( 'Loading…', 'ert-appointment' ),

			// ── Booking outcome ────────────────────────────────────────────
			'bookingSuccess'        => __( 'Your appointment has been booked!', 'ert-appointment' ),
			'confirmationEmailSent' => __( 'A confirmation email has been sent to you.', 'ert-appointment' ),
			'bookAnother'           => __( 'Book Another Appointment', 'ert-appointment' ),
			'book-again'            => __( 'Book Again', 'ert-appointment' ),

			// ── Payment ────────────────────────────────────────────────────
			'goToPayment'           => __( 'Go to Payment', 'ert-appointment' ),
			'completePayment'       => __( 'Complete Payment', 'ert-appointment' ),
			'redirectingToPayment'  => __( 'Redirecting to payment…', 'ert-appointment' ),

			// ── Availability ───────────────────────────────────────────────
			'noSlots'               => __( 'No available slots on this day.', 'ert-appointment' ),

			// ── Errors / validation ────────────────────────────────────────
			'bookingError'          => __( 'Something went wrong. Please try again.', 'ert-appointment' ),
			'slotUnavailable'       => __( 'This slot is no longer available. Please choose another.', 'ert-appointment' ),
			'required'              => __( 'This field is required.', 'ert-appointment' ),
			'invalidEmail'          => __( 'Please enter a valid email address.', 'ert-appointment' ),

			// ── My Appointments ────────────────────────────────────────────
			'myAppointments'        => __( 'My Appointments', 'ert-appointment' ),
			'upcoming'              => __( 'Upcoming', 'ert-appointment' ),
			'past'                  => __( 'Past', 'ert-appointment' ),
			'noAppointments'        => __( 'You have no appointments.', 'ert-appointment' ),
			'reschedule'            => __( 'Reschedule', 'ert-appointment' ),
			'rescheduleAppointment' => __( 'Reschedule Appointment', 'ert-appointment' ),
			'cancelAppointment'     => __( 'Cancel Appointment', 'ert-appointment' ),
			'cancelReason'          => __( 'Reason for cancellation…', 'ert-appointment' ),
			'newDateTime'           => __( 'New Date & Time', 'ert-appointment' ),

			// ── Common fields in success / detail ─────────────────────────
			'date'                  => __( 'Date', 'ert-appointment' ),
			'time'                  => __( 'Time', 'ert-appointment' ),
			'status'                => __( 'Status', 'ert-appointment' ),
		);
	}

	/**
	 * Admin translation strings (passed to Vue via wp_localize_script).
	 *
	 * All values must be proper translatable sentences/words — never pass
	 * the key name itself as the translation string.
	 *
	 * @return array<string, string>
	 */
	private function adminI18n(): array {
		return array(
			// ── Navigation ─────────────────────────────────────────────────
			'dashboard'               => __( 'Dashboard', 'ert-appointment' ),
			'appointments'            => __( 'Appointments', 'ert-appointment' ),
			'departments'             => __( 'Departments', 'ert-appointment' ),
			'providers'               => __( 'Staff', 'ert-appointment' ),
			'forms'                   => __( 'Forms', 'ert-appointment' ),
			'workingHours'            => __( 'Working Hours', 'ert-appointment' ),
			'notifications'           => __( 'Notifications', 'ert-appointment' ),
			'reports'                 => __( 'Reports', 'ert-appointment' ),
			'settings'                => __( 'Settings', 'ert-appointment' ),
			'proBadge'                => __( 'PRO', 'ert-appointment' ),

			// ── Actions ────────────────────────────────────────────────────
			'save'                    => __( 'Save', 'ert-appointment' ),
			'cancel'                  => __( 'Cancel', 'ert-appointment' ),
			'edit'                    => __( 'Edit', 'ert-appointment' ),
			'delete'                  => __( 'Delete', 'ert-appointment' ),
			'confirm'                 => __( 'Confirm', 'ert-appointment' ),
			'undoConfirm'             => __( 'Undo Confirm', 'ert-appointment' ),
			'close'                   => __( 'Close', 'ert-appointment' ),
			'back'                    => __( 'Back', 'ert-appointment' ),
			'next'                    => __( 'Next', 'ert-appointment' ),
			'prev'                    => __( 'Prev', 'ert-appointment' ),
			'apply'                   => __( 'Apply', 'ert-appointment' ),
			'select'                  => __( 'Select', 'ert-appointment' ),
			'loading'                 => __( 'Loading…', 'ert-appointment' ),
			'saved'                   => __( 'Saved.', 'ert-appointment' ),
			'error'                   => __( 'An error occurred.', 'ert-appointment' ),
			'deleteConfirm'           => __( 'Are you sure you want to delete this item?', 'ert-appointment' ),
			'confirmCancel'           => __( 'Are you sure you want to cancel this appointment?', 'ert-appointment' ),

			// ── Fields ─────────────────────────────────────────────────────
			'name'                    => __( 'Name', 'ert-appointment' ),
			'email'                   => __( 'Email', 'ert-appointment' ),
			'phone'                   => __( 'Phone', 'ert-appointment' ),
			'description'             => __( 'Description', 'ert-appointment' ),
			'status'                  => __( 'Status', 'ert-appointment' ),
			'actions'                 => __( 'Actions', 'ert-appointment' ),
			'date'                    => __( 'Date', 'ert-appointment' ),
			'time'                    => __( 'Time', 'ert-appointment' ),
			'datetime'                => __( 'Date & Time', 'ert-appointment' ),
			'day'                     => __( 'Day', 'ert-appointment' ),
			'notes'                   => __( 'Notes', 'ert-appointment' ),
			'type'                    => __( 'Type', 'ert-appointment' ),
			'duration'                => __( 'Duration', 'ert-appointment' ),
			'page'                    => __( 'Page', 'ert-appointment' ),

			// ── Settings ───────────────────────────────────────────────────
			'general'                 => __( 'General', 'ert-appointment' ),
			'payment'                 => __( 'Payment', 'ert-appointment' ),
			'integrations'            => __( 'Integrations', 'ert-appointment' ),
			'slotDuration'            => __( 'Slot Duration (minutes)', 'ert-appointment' ),
			'bufferBefore'            => __( 'Buffer Before (minutes)', 'ert-appointment' ),
			'bufferAfter'             => __( 'Buffer After (minutes)', 'ert-appointment' ),
			'minNotice'               => __( 'Minimum Notice (hours)', 'ert-appointment' ),
			'minNoticeDesc'           => __( 'How many hours in advance a booking must be made.', 'ert-appointment' ),
			'maxAdvance'              => __( 'Maximum Advance (days)', 'ert-appointment' ),
			'autoConfirm'             => __( 'Auto-confirm bookings', 'ert-appointment' ),
			'currency'                => __( 'Currency', 'ert-appointment' ),
			'paymentRequired'         => __( 'Require payment to confirm', 'ert-appointment' ),
			'paymentAmount'           => __( 'Payment Amount', 'ert-appointment' ),
			'paymentGateway'          => __( 'Payment Gateway', 'ert-appointment' ),
			'settingsSaved'           => __( 'Settings saved.', 'ert-appointment' ),
			'msg'                     => __( 'Message', 'ert-appointment' ),

			// ── Appointment statuses ───────────────────────────────────────
			'pending'                 => __( 'Pending', 'ert-appointment' ),
			'confirmed'               => __( 'Confirmed', 'ert-appointment' ),
			'cancelled'               => __( 'Cancelled', 'ert-appointment' ),
			'completed'               => __( 'Completed', 'ert-appointment' ),
			'paid'                    => __( 'Paid', 'ert-appointment' ),
			'appointment'             => __( 'Appointment', 'ert-appointment' ),
			'newAppointment'          => __( 'New Appointment', 'ert-appointment' ),
			'noAppointments'          => __( 'No appointments found.', 'ert-appointment' ),
			'allStatuses'             => __( 'All Statuses', 'ert-appointment' ),
			'allDepartments'          => __( 'All Departments', 'ert-appointment' ),
			'searchCustomer'          => __( 'Search customer…', 'ert-appointment' ),
			'cancelAppointment'       => __( 'Cancel Appointment', 'ert-appointment' ),
			'cancelReason'            => __( 'Reason for cancellation…', 'ert-appointment' ),
			'todayAppointments'       => __( 'Today\'s Appointments', 'ert-appointment' ),
			'upcomingAppointments'    => __( 'Upcoming Appointments', 'ert-appointment' ),
			'noUpcomingAppointments'  => __( 'No upcoming appointments.', 'ert-appointment' ),
			'thisMonth'               => __( 'This Month', 'ert-appointment' ),
			'appointmentConfirmed'    => __( 'Appointment confirmed.', 'ert-appointment' ),
			'appointmentCancelled'    => __( 'Appointment cancelled.', 'ert-appointment' ),
			'appointmentUnconfirmed'  => __( 'Appointment moved back to pending.', 'ert-appointment' ),

			// ── Departments ────────────────────────────────────────────────
			'department'              => __( 'Department', 'ert-appointment' ),
			'newDepartment'           => __( 'New Department', 'ert-appointment' ),
			'addDepartment'           => __( 'Add Department', 'ert-appointment' ),
			'editDepartment'          => __( 'Edit Department', 'ert-appointment' ),
			'noDepartments'           => __( 'No departments found.', 'ert-appointment' ),
			'departmentsProOnly'      => __( 'Departments management is available in Pro.', 'ert-appointment' ),
			'departmentProOnly'       => __( 'Department features are available in Pro.', 'ert-appointment' ),

			// ── Providers ──────────────────────────────────────────────────
			'provider'                => __( 'Staff Member', 'ert-appointment' ),
			'customer'                => __( 'Customer', 'ert-appointment' ),
			'newProvider'             => __( 'New Staff Member', 'ert-appointment' ),
			'addProvider'             => __( 'Add Staff Member', 'ert-appointment' ),
			'editProvider'            => __( 'Edit Staff Member', 'ert-appointment' ),
			'noProviders'             => __( 'No staff members found.', 'ert-appointment' ),
			'providerTypeIndividual'  => __( 'Individual', 'ert-appointment' ),
			'providerTypeUnit'        => __( 'Unit', 'ert-appointment' ),
			'departmentRequiredForUnit' => __( 'Please select a department when type is Unit.', 'ert-appointment' ),

			// ── Forms ──────────────────────────────────────────────────────
			'newForm'                 => __( 'New Form', 'ert-appointment' ),
			'editForm'                => __( 'Edit Form', 'ert-appointment' ),
			'noForms'                 => __( 'No forms found.', 'ert-appointment' ),
			'formName'                => __( 'Form Name', 'ert-appointment' ),
			'fields'                  => __( 'Fields', 'ert-appointment' ),
			'addField'                => __( 'Add Field', 'ert-appointment' ),
			'fieldLabel'              => __( 'Field Label', 'ert-appointment' ),
			'fieldId'                 => __( 'Field ID', 'ert-appointment' ),
			'placeholder'             => __( 'Placeholder', 'ert-appointment' ),
			'required'                => __( 'Required', 'ert-appointment' ),
			'helpText'                => __( 'Help Text', 'ert-appointment' ),
			'helpTextPlaceholder'     => __( 'Optional help text shown below the field…', 'ert-appointment' ),
			'options'                 => __( 'Options', 'ert-appointment' ),
			'optionLabel'             => __( 'Option Label', 'ert-appointment' ),
			'optionValue'             => __( 'Option Value', 'ert-appointment' ),
			'addOption'               => __( 'Add Option', 'ert-appointment' ),
			'preview'                 => __( 'Preview', 'ert-appointment' ),
			'noFieldsYet'             => __( 'No fields added yet.', 'ert-appointment' ),
			'untitledField'           => __( 'Untitled Field', 'ert-appointment' ),

			// ── Working Hours ──────────────────────────────────────────────
			'global'                  => __( 'Global', 'ert-appointment' ),
			'scope'                   => __( 'Scope', 'ert-appointment' ),
			'editScope'               => __( 'Edit Scope', 'ert-appointment' ),
			'globalScopeHint'         => __( 'These hours apply to all providers by default.', 'ert-appointment' ),
			'departmentScopeHint'     => __( 'These hours override the global hours for this department.', 'ert-appointment' ),
			'providerScopeHint'       => __( 'These hours override department and global hours for this provider.', 'ert-appointment' ),
			'workingHoursHint'        => __( 'Set the days and hours when appointments can be booked.', 'ert-appointment' ),
			'breaksHint'              => __( 'Breaks block off time within working hours (e.g. lunch).', 'ert-appointment' ),
			'specialDaysHint'         => __( 'Special days override regular hours for a specific date.', 'ert-appointment' ),
			'breaks'                  => __( 'Breaks', 'ert-appointment' ),
			'specialDays'             => __( 'Special Days', 'ert-appointment' ),
			'closed'                  => __( 'Closed', 'ert-appointment' ),
			'everyDay'                => __( 'Every Day', 'ert-appointment' ),
			'breakName'               => __( 'Break Name', 'ert-appointment' ),
			'newBreak'                => __( 'New Break', 'ert-appointment' ),
			'addBreak'                => __( 'Add Break', 'ert-appointment' ),
			'noBreaks'                => __( 'No breaks defined.', 'ert-appointment' ),
			'specialDayName'          => __( 'Special Day Name', 'ert-appointment' ),
			'addSpecialDay'           => __( 'Add Special Day', 'ert-appointment' ),
			'noSpecialDays'           => __( 'No special days defined.', 'ert-appointment' ),
			'customHours'             => __( 'Custom Hours', 'ert-appointment' ),
			'calendar'                => __( 'Calendar', 'ert-appointment' ),

			// ── Integrations ───────────────────────────────────────────────
			'callbackUrl'             => __( 'Callback URL', 'ert-appointment' ),
			'connected'               => __( 'Connected', 'ert-appointment' ),
			'notConnected'            => __( 'Not connected', 'ert-appointment' ),
			'configured'              => __( 'Configured', 'ert-appointment' ),
			'notConfigured'           => __( 'Not configured', 'ert-appointment' ),
			'connectGoogle'           => __( 'Connect Google Account', 'ert-appointment' ),
			'google'                  => __( 'Google Calendar', 'ert-appointment' ),
			'googleConnectedAs'       => __( 'Connected as', 'ert-appointment' ),
			'googleCalendarDesc'      => __( 'Sync appointments to provider Google Calendars automatically.', 'ert-appointment' ),
			'googleClientIdHelp'      => __( 'Create OAuth 2.0 credentials in Google Cloud Console.', 'ert-appointment' ),
			'googleCallbackHelp'      => __( 'Add this URI to your Google OAuth allowed redirect URIs.', 'ert-appointment' ),
			'googleAuthUrlFailed'     => __( 'Could not get Google auth URL. Check credentials and try again.', 'ert-appointment' ),
			'zoomDesc'                => __( 'Automatically create Zoom meetings for confirmed appointments.', 'ert-appointment' ),
			'paytrDesc'               => __( 'Accept online payments via PayTR.', 'ert-appointment' ),
			'disconnect'              => __( 'Disconnect', 'ert-appointment' ),
			'testConnection'          => __( 'Test Connection', 'ert-appointment' ),
			'connectionOk'            => __( 'Connection successful.', 'ert-appointment' ),
			'connectionFailed'        => __( 'Connection failed. Check your credentials.', 'ert-appointment' ),
			'autoCreateMeeting'       => __( 'Auto-create Zoom meetings', 'ert-appointment' ),
			'autoCreateMeetingDesc'   => __( 'Automatically create a Zoom meeting when an appointment is confirmed.', 'ert-appointment' ),
			'testMode'                => __( 'Test / Sandbox Mode', 'ert-appointment' ),

			// ── Notifications ──────────────────────────────────────────────
			'addTemplate'             => __( 'Add Template', 'ert-appointment' ),
			'quickTemplateCombos'      => __( 'Quick Template Combos', 'ert-appointment' ),
			'createCustomerAdminEmail' => __( 'Create Customer + Admin Email', 'ert-appointment' ),
			'templateGroup'           => __( 'Template Group', 'ert-appointment' ),
			'templateChannel'         => __( 'Template Channel', 'ert-appointment' ),
			'templateRecipient'       => __( 'Template Recipient', 'ert-appointment' ),
			'channelEmail'            => __( 'Email', 'ert-appointment' ),
			'channelSms'              => __( 'SMS', 'ert-appointment' ),
			'recipientCustomer'       => __( 'Customer', 'ert-appointment' ),
			'recipientProvider'       => __( 'Provider', 'ert-appointment' ),
			'recipientAdmin'          => __( 'Admin', 'ert-appointment' ),
			'notifEventPending'       => __( 'New Booking (Pending)', 'ert-appointment' ),
			'notifEventConfirmed'     => __( 'Booking Confirmed', 'ert-appointment' ),
			'notifEventCancelled'     => __( 'Booking Cancelled', 'ert-appointment' ),
			'notifEventRescheduled'   => __( 'Booking Rescheduled', 'ert-appointment' ),
			'notifEventCompleted'     => __( 'Appointment Completed', 'ert-appointment' ),
			'notifEventNoShow'        => __( 'No-Show Marked', 'ert-appointment' ),
			'notifEventReminder'      => __( 'Reminder', 'ert-appointment' ),
			'notifEventReminder24h'   => __( '24h Reminder', 'ert-appointment' ),
			'notifEventReminder1h'    => __( '1h Reminder', 'ert-appointment' ),
			'notifEventWaitlist'      => __( 'Waitlist Slot Available', 'ert-appointment' ),
			'subject'                 => __( 'Subject', 'ert-appointment' ),
			'body'                    => __( 'Body', 'ert-appointment' ),
			'active'                  => __( 'Active', 'ert-appointment' ),
			'inactive'                => __( 'Inactive', 'ert-appointment' ),
			'activate'                => __( 'Activate', 'ert-appointment' ),
			'deactivate'              => __( 'Deactivate', 'ert-appointment' ),
			'event'                   => __( 'Event', 'ert-appointment' ),
			'channel'                 => __( 'Channel', 'ert-appointment' ),
			'recipient'               => __( 'Recipient', 'ert-appointment' ),
			'noTemplates'             => __( 'No notification templates found.', 'ert-appointment' ),
			'availablePlaceholders'   => __( 'Available Placeholders', 'ert-appointment' ),
			'clickToInsert'           => __( 'Click to insert', 'ert-appointment' ),
			'templateBodyHint'        => __( 'Use the placeholders above to personalise the message.', 'ert-appointment' ),
			'emailSubjectPlaceholder' => __( 'Enter email subject…', 'ert-appointment' ),

			// ── Reports ────────────────────────────────────────────────────
			'totalAppointments'       => __( 'Total Appointments', 'ert-appointment' ),
			'cancellationRate'        => __( 'Cancellation Rate', 'ert-appointment' ),
			'revenue'                 => __( 'Revenue', 'ert-appointment' ),
			'topProviders'            => __( 'Top Providers', 'ert-appointment' ),
			'reportsProDesc'          => __( 'Detailed analytics are available with ERT Appointment Pro.', 'ert-appointment' ),
			'proFeature'              => __( 'Pro Feature', 'ert-appointment' ),
			'upgradeToPro'            => __( 'Upgrade to Pro', 'ert-appointment' ),

			// ── Misc ───────────────────────────────────────────────────────
			'myPanel'                 => __( 'My Panel', 'ert-appointment' ),
			'siteName'                => get_bloginfo( 'name' ),
			'proRequired'             => __( 'This feature requires ERT Appointment Pro.', 'ert-appointment' ),
		);
	}
}
