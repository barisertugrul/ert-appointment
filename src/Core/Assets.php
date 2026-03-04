<?php

declare(strict_types=1);

namespace ERTAppointment\Core;

/**
 * Manages script and style enqueueing for both frontend and admin.
 *
 * Frontend: Vue 3 booking widget (loaded only on pages with [erta_booking] shortcode).
 * Admin: Separate Vue admin panel bundle.
 */
final class Assets
{
    // -------------------------------------------------------------------------
    // Frontend
    // -------------------------------------------------------------------------

    /**
     * Enqueues frontend assets.
     * Scripts are only enqueued when the booking shortcode is present on the page.
     */
    public function enqueueFrontend(): void
    {
        if (! $this->pageHasBookingShortcode()) {
            return;
        }

        wp_enqueue_style(
            'erta-frontend',
            ERTA_URL . 'assets/css/frontend.css',
            [],
            ERTA_VERSION
        );

        wp_enqueue_script(
            'erta-frontend',
            ERTA_URL . 'assets/dist/frontend.js',
            [],
            ERTA_VERSION,
            true
        );

        wp_localize_script('erta-frontend', 'ertaData', $this->frontendConfig());
    }

    // -------------------------------------------------------------------------
    // Admin
    // -------------------------------------------------------------------------

    /**
     * Enqueues admin assets only on plugin admin pages.
     */
    public function enqueueAdmin(string $hookSuffix): void
    {
        if (! $this->isPluginAdminPage($hookSuffix)) {
            return;
        }

        wp_enqueue_style(
            'erta-admin',
            ERTA_URL . 'assets/css/admin.css',
            [],
            ERTA_VERSION
        );

        wp_enqueue_script(
            'erta-admin',
            ERTA_URL . 'assets/dist/admin.js',
            [],
            ERTA_VERSION,
            true
        );

        wp_localize_script('erta-admin', 'ertaAdminData', $this->adminConfig());
    }

    // -------------------------------------------------------------------------
    // Config objects (passed to JS via wp_localize_script)
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function frontendConfig(): array
    {
        return [
            'restUrl'   => esc_url_raw(rest_url('erta/v1/')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'siteUrl'   => get_site_url(),
            'dateFormat' => get_option('date_format', 'Y-m-d'),
            'timeFormat' => get_option('time_format', 'H:i'),
            'locale'     => determine_locale(),
            'i18n'       => $this->frontendI18n(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminConfig(): array
    {
        return [
            'restUrl'    => esc_url_raw(rest_url('erta/v1/')),
            'adminUrl'   => admin_url('admin.php'),
            'nonce'      => wp_create_nonce('wp_rest'),
            'dateFormat' => get_option('date_format', 'Y-m-d'),
            'timeFormat' => get_option('time_format', 'H:i'),
            'locale'     => determine_locale(),
            'isPro'      => $this->isProActive(),
            'i18n'       => $this->adminI18n(),
        ];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Checks if the current page contains the booking shortcode.
     * Uses the global $post object.
     */
    private function pageHasBookingShortcode(): bool
    {
        global $post;

        if (! $post instanceof \WP_Post) {
            return false;
        }

        return has_shortcode($post->post_content, 'erta_booking')
            || has_shortcode($post->post_content, 'erta_my_appointments');
    }

    /**
     * Checks if the current screen is a plugin admin page.
     */
    private function isPluginAdminPage(string $hookSuffix): bool
    {
        // Plugin pages all have 'erta-' in their hook suffix.
        return str_contains($hookSuffix, 'erta-')
            || str_contains($hookSuffix, 'ert-appointment');
    }

    /**
     * Returns whether the Pro add-on is active.
     */
    private function isProActive(): bool
    {
        return apply_filters('erta_is_pro_active', false);
    }

    /**
     * Frontend translation strings (passed to Vue i18n).
     *
     * @return array<string, string>
     */
    private function frontendI18n(): array
    {
        return [
            'selectDepartment'  => __('Select a Department', 'ert-appointment'),
            'selectProvider'    => __('Select a Provider', 'ert-appointment'),
            'selectDate'        => __('Choose a Date', 'ert-appointment'),
            'selectTime'        => __('Choose a Time', 'ert-appointment'),
            'fillDetails'       => __('Your Details', 'ert-appointment'),
            'confirm'           => __('Confirm Appointment', 'ert-appointment'),
            'book'              => __('Book Now', 'ert-appointment'),
            'noSlots'           => __('No available slots on this day.', 'ert-appointment'),
            'loading'           => __('Loading…', 'ert-appointment'),
            'bookingSuccess'    => __('Your appointment has been booked!', 'ert-appointment'),
            'bookingError'      => __('Something went wrong. Please try again.', 'ert-appointment'),
            'slotUnavailable'   => __('This slot is no longer available. Please choose another.', 'ert-appointment'),
            'cancel'            => __('Cancel', 'ert-appointment'),
            'reschedule'        => __('Reschedule', 'ert-appointment'),
            'back'              => __('Back', 'ert-appointment'),
            'next'              => __('Next', 'ert-appointment'),
            'required'          => __('This field is required.', 'ert-appointment'),
            'invalidEmail'      => __('Please enter a valid email address.', 'ert-appointment'),
        ];
    }

    /**
     * Admin translation strings.
     *
     * @return array<string, string>
     */
    private function adminI18n(): array
    {
        return [
            // Navigation
            'dashboard'           => __('dashboard', 'ert-appointment'),
            'appointments'        => __('appointments', 'ert-appointment'),
            'upcomingAppointments'=> __('upcomingAppointments', 'ert-appointment'),
            'todayAppointments'   => __('todayAppointments', 'ert-appointment'),
            'thisMonth'           => __('thisMonth', 'ert-appointment'),
            'departments'         => __('departments', 'ert-appointment'),
            'providers'           => __('providers', 'ert-appointment'),
            'forms'               => __('forms', 'ert-appointment'),
            'workingHours'        => __('workingHours', 'ert-appointment'),
            'notifications'       => __('notifications', 'ert-appointment'),
            'reports'             => __('reports', 'ert-appointment'),
            'settings'            => __('settings', 'ert-appointment'),
            // Actions
            'save'          => __('save', 'ert-appointment'),
            'cancel'        => __('cancel', 'ert-appointment'),
            'edit'          => __('edit', 'ert-appointment'),
            'delete'        => __('delete', 'ert-appointment'),
            'confirm'       => __('confirm', 'ert-appointment'),
            'close'         => __('close', 'ert-appointment'),
            'loading'       => __('loading', 'ert-appointment'),
            'saved'         => __('saved', 'ert-appointment'),
            'error'         => __('error', 'ert-appointment'),
            'deleteConfirm' => __('deleteConfirm', 'ert-appointment'),
            // Fields / filters
            'name'           => __('name', 'ert-appointment'),
            'email'          => __('email', 'ert-appointment'),
            'phone'          => __('phone', 'ert-appointment'),
            'status'         => __('status', 'ert-appointment'),
            'actions'        => __('actions', 'ert-appointment'),
            'date'           => __('date', 'ert-appointment'),
            'time'           => __('time', 'ert-appointment'),
            'notes'          => __('notes', 'ert-appointment'),
            'searchCustomer' => __('searchCustomer', 'ert-appointment'),
            'allStatuses'    => __('allStatuses', 'ert-appointment'),
            // Settings tabs
            'general'       => __('general', 'ert-appointment'),
            'payment'       => __('payment', 'ert-appointment'),
            'integrations'  => __('integrations', 'ert-appointment'),
            'slotDuration'  => __('slotDuration', 'ert-appointment'),
            'bufferBefore'  => __('bufferBefore', 'ert-appointment'),
            'bufferAfter'   => __('bufferAfter', 'ert-appointment'),
            'minNotice'     => __('minNotice', 'ert-appointment'),
            'minNoticeDesc' => __('minNoticeDesc', 'ert-appointment'),
            'maxAdvance'    => __('maxAdvance', 'ert-appointment'),
            'autoConfirm'   => __('autoConfirm', 'ert-appointment'),
            'currency'      => __('currency', 'ert-appointment'),
            'paymentRequired' => __('paymentRequired', 'ert-appointment'),
            'paymentAmount'   => __('paymentAmount', 'ert-appointment'),
            'paymentGateway'  => __('paymentGateway', 'ert-appointment'),
            'paytrMerchantId' => __('paytrMerchantId', 'ert-appointment'),
            'paytrMerchantKey' => __('paytrMerchantKey', 'ert-appointment'),
            'paytrMerchantSalt' => __('paytrMerchantSalt', 'ert-appointment'),
            'paytrTestMode' => __('paytrTestMode', 'ert-appointment'),
            'stripeSecretKey' => __('stripeSecretKey', 'ert-appointment'),
            'stripeWebhookSecret' => __('stripeWebhookSecret', 'ert-appointment'),
            'iyzicoApiKey' => __('iyzicoApiKey', 'ert-appointment'),
            'iyzicoSecretKey' => __('iyzicoSecretKey', 'ert-appointment'),
            'iyzicoSandbox' => __('iyzicoSandbox', 'ert-appointment'),
            'googleClientId' => __('googleClientId', 'ert-appointment'),
            'googleClientSecret' => __('googleClientSecret', 'ert-appointment'),
            'zoomAccountId' => __('zoomAccountId', 'ert-appointment'),
            'zoomClientId' => __('zoomClientId', 'ert-appointment'),
            'zoomClientSecret' => __('zoomClientSecret', 'ert-appointment'),
            'zoomAutoCreate' => __('zoomAutoCreate', 'ert-appointment'),
            'paytrMerchantIdPlaceholder' => __('paytrMerchantIdPlaceholder', 'ert-appointment'),
            'paytrMerchantKeyPlaceholder' => __('paytrMerchantKeyPlaceholder', 'ert-appointment'),
            'paytrMerchantSaltPlaceholder' => __('paytrMerchantSaltPlaceholder', 'ert-appointment'),
            'stripeSecretKeyPlaceholder' => __('stripeSecretKeyPlaceholder', 'ert-appointment'),
            'stripeWebhookSecretPlaceholder' => __('stripeWebhookSecretPlaceholder', 'ert-appointment'),
            'iyzicoApiKeyPlaceholder' => __('iyzicoApiKeyPlaceholder', 'ert-appointment'),
            'iyzicoSecretKeyPlaceholder' => __('iyzicoSecretKeyPlaceholder', 'ert-appointment'),
            'googleClientIdPlaceholder' => __('googleClientIdPlaceholder', 'ert-appointment'),
            'googleClientSecretPlaceholder' => __('googleClientSecretPlaceholder', 'ert-appointment'),
            'zoomAccountIdPlaceholder' => __('zoomAccountIdPlaceholder', 'ert-appointment'),
            'zoomClientIdPlaceholder' => __('zoomClientIdPlaceholder', 'ert-appointment'),
            'zoomClientSecretPlaceholder' => __('zoomClientSecretPlaceholder', 'ert-appointment'),
            'zoomAutoCreatePlaceholder' => __('zoomAutoCreatePlaceholder', 'ert-appointment'),
            'paytrTestModePlaceholder' => __('paytrTestModePlaceholder', 'ert-appointment'),
            'stripeTestModePlaceholder' => __('stripeTestModePlaceholder', 'ert-appointment'),
            'iyzicoSandboxPlaceholder' => __('iyzicoSandboxPlaceholder', 'ert-appointment'),
            'googleAuthUrlFailed' => __('googleAuthUrlFailed', 'ert-appointment'),
            'zoomConfigured' => __('zoomConfigured', 'ert-appointment'),
            'paytrMerchantId' => __('paytrMerchantId', 'ert-appointment'),
            'paytrMerchantKey' => __('paytrMerchantKey', 'ert-appointment'),
            'paytrMerchantSalt' => __('paytrMerchantSalt', 'ert-appointment'),
            'paytrTestMode' => __('paytrTestMode', 'ert-appointment'),
            'stripeSecretKey' => __('stripeSecretKey', 'ert-appointment'),
            'stripeWebhookSecret' => __('stripeWebhookSecret', 'ert-appointment'),
            'iyzicoApiKey' => __('iyzicoApiKey', 'ert-appointment'),
            'iyzicoSecretKey' => __('iyzicoSecretKey', 'ert-appointment'),
            'iyzicoSandbox' => __('iyzicoSandbox', 'ert-appointment'),
            'googleClientId' => __('googleClientId', 'ert-appointment'),
            'googleClientSecret' => __('googleClientSecret', 'ert-appointment'),
            'zoomAccountId' => __('zoomAccountId', 'ert-appointment'),
            'zoomClientId' => __('zoomClientId', 'ert-appointment'),
            'zoomClientSecret' => __('zoomClientSecret', 'ert-appointment'),
            'zoomAutoCreate' => __('zoomAutoCreate', 'ert-appointment'),
            'paytrMerchantIdPlaceholder' => __('paytrMerchantIdPlaceholder', 'ert-appointment'),
            'paytrMerchantKeyPlaceholder' => __('paytrMerchantKeyPlaceholder', 'ert-appointment'),
            'paytrMerchantSaltPlaceholder' => __('paytrMerchantSaltPlaceholder', 'ert-appointment'),
            'stripeSecretKeyPlaceholder' => __('stripeSecretKeyPlaceholder', 'ert-appointment'),
            'stripeWebhookSecretPlaceholder' => __('stripeWebhookSecretPlaceholder', 'ert-appointment'),
            'iyzicoApiKeyPlaceholder' => __('iyzicoApiKeyPlaceholder', 'ert-appointment'),
            'iyzicoSecretKeyPlaceholder' => __('iyzicoSecretKeyPlaceholder', 'ert-appointment'),
            'iyzicoSandboxPlaceholder' => __('iyzicoSandboxPlaceholder', 'ert-appointment'),
            'googleClientIdPlaceholder' => __('googleClientIdPlaceholder', 'ert-appointment'),
            // Appointment statuses
            'pending'       => __('pending', 'ert-appointment'),
            'confirmed'     => __('confirmed', 'ert-appointment'),
            'cancelled'     => __('cancelled', 'ert-appointment'),
            'completed'     => __('completed', 'ert-appointment'),
            'newAppointment' => __('newAppointment', 'ert-appointment'),
            'noAppointments' => __('noAppointments', 'ert-appointment'),
            // Departments
            'department'      => __('department', 'ert-appointment'),
            'newDepartment'   => __('newDepartment', 'ert-appointment'),
            'editDepartment'  => __('editDepartment', 'ert-appointment'),
            'noDepartments'   => __('noDepartments', 'ert-appointment'),
            'addDepartment'   => __('addDepartment', 'ert-appointment'),
            // Providers
            'provider'        => __('provider', 'ert-appointment'),
            'customer'        => __('customer', 'ert-appointment'),
            'newProvider'     => __('newProvider', 'ert-appointment'),
            'editProvider'    => __('editProvider', 'ert-appointment'),
            'noProviders'     => __('noProviders', 'ert-appointment'),
            'addProvider'     => __('addProvider', 'ert-appointment'),
            'type'            => __('type', 'ert-appointment'),
            'Individual'      => __('Individual', 'ert-appointment'),
            'Unit'            => __('Unit', 'ert-appointment'),
            // Forms
            'newForm'         => __('newForm', 'ert-appointment'),
            'editForm'        => __('editForm', 'ert-appointment'),
            'noForms'         => __('noForms', 'ert-appointment'),
            'formName'        => __('formName', 'ert-appointment'),
            'fields'          => __('fields', 'ert-appointment'),
            'addField'        => __('addField', 'ert-appointment'),
            'fieldLabel'      => __('fieldLabel', 'ert-appointment'),
            'fieldId'         => __('fieldId', 'ert-appointment'),
            'placeholder'     => __('placeholder', 'ert-appointment'),
            'required'        => __('required', 'ert-appointment'),
            'helpText'        => __('helpText', 'ert-appointment'),
            'helpTextPlaceholder' => __('helpTextPlaceholder', 'ert-appointment'),
            'options'         => __('options', 'ert-appointment'),
            'optionLabel'     => __('optionLabel', 'ert-appointment'),
            'optionValue'     => __('optionValue', 'ert-appointment'),
            'addOption'       => __('addOption', 'ert-appointment'),
            'preview'         => __('preview', 'ert-appointment'),
            'noFieldsYet'     => __('noFieldsYet', 'ert-appointment'),
            'untitledField'   => __('untitledField', 'ert-appointment'),
            // Working hours
            'global'              => __('global', 'ert-appointment'),
            'scope'               => __('scope', 'ert-appointment'),
            'editScope'           => __('editScope', 'ert-appointment'),
            'globalScopeHint'     => __('globalScopeHint', 'ert-appointment'),
            'departmentScopeHint' => __('departmentScopeHint', 'ert-appointment'),
            'providerScopeHint'   => __('providerScopeHint', 'ert-appointment'),
            'workingHoursHint'    => __('workingHoursHint', 'ert-appointment'),
            'breaksHint'          => __('breaksHint', 'ert-appointment'),
            'specialDaysHint'     => __('specialDaysHint', 'ert-appointment'),
            'breaks'              => __('breaks', 'ert-appointment'),
            'specialDays'         => __('specialDays', 'ert-appointment'),
            'closed'              => __('closed', 'ert-appointment'),
            'everyDay'            => __('everyDay', 'ert-appointment'),
            'breakName'           => __('breakName', 'ert-appointment'),
            'newBreak'            => __('newBreak', 'ert-appointment'),
            'addBreak'            => __('addBreak', 'ert-appointment'),
            'noBreaks'            => __('noBreaks', 'ert-appointment'),
            'specialDayName'      => __('specialDayName', 'ert-appointment'),
            'addSpecialDay'       => __('addSpecialDay', 'ert-appointment'),
            'noSpecialDays'       => __('noSpecialDays', 'ert-appointment'),
            'customHours'         => __('customHours', 'ert-appointment'),
            'Monday'              => __('Monday', 'ert-appointment'),
            'Tuesday'             => __('Tuesday', 'ert-appointment'),
            'Wednesday'           => __('Wednesday', 'ert-appointment'),
            'Thursday'            => __('Thursday', 'ert-appointment'),
            'Friday'              => __('Friday', 'ert-appointment'),
            'Saturday'            => __('Saturday', 'ert-appointment'),
            'Sunday'              => __('Sunday', 'ert-appointment'),
            // Integrations
            'callbackUrl'         => __('callbackUrl', 'ert-appointment'),
            'connected'           => __('connected', 'ert-appointment'),
            'notConnected'        => __('notConnected', 'ert-appointment'),
            'configured'          => __('configured', 'ert-appointment'),
            'notConfigured'       => __('notConfigured', 'ert-appointment'),
            'connectGoogle'       => __('connectGoogle', 'ert-appointment'),
            'googleConnectedAs'   => __('Connected as', 'ert-appointment'),
            'googleCalendarDesc'  => __('Sync appointments to provider Google Calendars automatically.', 'ert-appointment'),
            'googleClientIdHelp'  => __('Create OAuth 2.0 credentials in Google Cloud Console.', 'ert-appointment'),
            'googleCallbackHelp'  => __('Add this URI to your Google OAuth allowed redirect URIs.', 'ert-appointment'),
            'googleAuthUrlFailed' => __('Could not get Google auth URL. Check credentials and try again.', 'ert-appointment'),
            'zoomDesc'            => __('Automatically create Zoom meetings for confirmed appointments.', 'ert-appointment'),
            'paytrDesc'           => __('Accept online payments via PayTR.', 'ert-appointment'),
            'disconnect'          => __('disconnect', 'ert-appointment'),
            'testConnection'      => __('testConnection', 'ert-appointment'),
            'connectionOk'        => __('connectionOk', 'ert-appointment'),
            'connectionFailed'    => __('connectionFailed', 'ert-appointment'),
            'autoCreateMeeting'   => __('autoCreateMeeting', 'ert-appointment'),
            'autoCreateMeetingDesc' => __('autoCreateMeetingDesc', 'ert-appointment'),
            'testMode'            => __('testMode', 'ert-appointment'),
            // Notifications
            'subject'             => __('subject', 'ert-appointment'),
            'body'                => __('body', 'ert-appointment'),
            'active'              => __('active', 'ert-appointment'),
            'event'               => __('event', 'ert-appointment'),
            'channel'             => __('channel', 'ert-appointment'),
            'recipient'           => __('recipient', 'ert-appointment'),
            'noTemplates'         => __('noTemplates', 'ert-appointment'),
            'availablePlaceholders' => __('availablePlaceholders', 'ert-appointment'),
            'clickToInsert'       => __('clickToInsert', 'ert-appointment'),
            'templateBodyHint'    => __('templateBodyHint', 'ert-appointment'),
            'emailSubjectPlaceholder' => __('Enter email subject…', 'ert-appointment'),
            'noUpcomingAppointments'  => __('noUpcomingAppointments', 'ert-appointment'),
            'New Booking (Pending)'   => __('New Booking (Pending)', 'ert-appointment'),
            'Booking Confirmed'       => __('Booking Confirmed', 'ert-appointment'),
            'Booking Cancelled'       => __('Booking Cancelled', 'ert-appointment'),
            'Booking Rescheduled'     => __('Booking Rescheduled', 'ert-appointment'),
            'Appointment Completed'   => __('Appointment Completed', 'ert-appointment'),
            'No-Show Marked'          => __('No-Show Marked', 'ert-appointment'),
            'Reminder'                => __('Reminder', 'ert-appointment'),
            '24h Reminder'            => __('24h Reminder', 'ert-appointment'),
            '1h Reminder'             => __('1h Reminder', 'ert-appointment'),
            'Waitlist Slot Available' => __('Waitlist Slot Available', 'ert-appointment'),
            // Misc
            'siteName'    => get_bloginfo('name'),
            'proRequired' => __('This feature requires WP Appointment Pro.', 'ert-appointment'),
        ];
    }

    /**
     * script_loader_tag filtresi ile Vite bundle'larını ES module olarak işaretler.
     *
     * @param string $tag    Orijinal script tag'i.
     * @param string $handle Script handle adı.
     * @param string $src    Script kaynak URL'si.
     *
     * @return string Düzenlenmiş script tag'i.
     */
    public function filterScriptTag(string $tag, string $handle, string $src): string
    {
        if ($handle !== 'erta-admin' && $handle !== 'erta-frontend') {
            return $tag;
        }

        // Zaten type attribute'u varsa module'a çevir.
        if (str_contains($tag, 'type=')) {
            return (string) preg_replace('/type=("|\')[^"\']+("|\')/', 'type="module"', $tag);
        }

        // Aksi halde type="module" ekle.
        return str_replace('<script ', '<script type="module" ', $tag);
    }
}
