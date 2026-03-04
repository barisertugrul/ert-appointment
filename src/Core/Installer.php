<?php

declare(strict_types=1);

namespace ERTAppointment\Core;

use ERTAppointment\Settings\SettingsManager;

/**
 * Handles plugin activation, deactivation, and database schema management.
 * Uses dbDelta for safe incremental table updates.
 */
final class Installer
{
    private const DB_VERSION_OPTION = 'erta_db_version';
    private const DB_VERSION        = '1.0.0';

    public function __construct(
        private readonly SettingsManager $settings
    ) {}

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    /**
     * Runs on plugin activation.
     * Creates/updates tables, sets default options, and flushes rewrite rules.
     */
    public function activate(): void
    {
        $this->runMigrations();
        $this->seedDefaultSettings();
        $this->createRoles();

        flush_rewrite_rules();

        update_option('erta_activated_at', current_time('mysql'));

        /**
         * Fires after the plugin has been activated and tables are ready.
         * Extensions (Pro add-on) can hook here to run their own migrations.
         */
        do_action('erta_activated');
    }

    /**
     * Runs on plugin deactivation.
     */
    public function deactivate(): void
    {
        // Clear all scheduled WP-Cron events registered by this plugin.
        foreach (['erta_send_reminders', 'erta_cleanup_logs'] as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }

        flush_rewrite_rules();

        /**
         * Fires when the plugin is deactivated.
         * Pro add-on uses this to clear its own cron jobs.
         */
        do_action('erta_deactivated');
    }

    // -------------------------------------------------------------------------
    // Database schema
    // -------------------------------------------------------------------------

    /**
     * Creates or updates all plugin tables using dbDelta.
     * Safe to call multiple times; dbDelta only makes necessary changes.
     */
    public function runMigrations(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $p       = $wpdb->prefix;

        // --- Departments -----------------------------------------------------
        dbDelta("CREATE TABLE {$p}erta_departments (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name          VARCHAR(200)    NOT NULL,
            slug          VARCHAR(200)    NOT NULL,
            description   TEXT            NULL,
            status        VARCHAR(20)     NOT NULL DEFAULT 'active',
            sort_order    INT             NOT NULL DEFAULT 0,
            meta          LONGTEXT        NULL COMMENT 'JSON extra data',
            created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE  KEY  uq_slug (slug),
            KEY          idx_status (status)
        ) {$charset};");

        // --- Providers (individuals or units) --------------------------------
        dbDelta("CREATE TABLE {$p}erta_providers (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            department_id BIGINT UNSIGNED NULL,
            type          VARCHAR(20)     NOT NULL DEFAULT 'individual' COMMENT 'individual|unit',
            name          VARCHAR(200)    NOT NULL,
            email         VARCHAR(200)    NULL,
            phone         VARCHAR(50)     NULL,
            description   TEXT            NULL,
            avatar_url    VARCHAR(500)    NULL,
            status        VARCHAR(20)     NOT NULL DEFAULT 'active',
            sort_order    INT             NOT NULL DEFAULT 0,
            meta          LONGTEXT        NULL COMMENT 'JSON extra data',
            created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY          idx_department (department_id),
            KEY          idx_status (status)
        ) {$charset};");

        // --- Provider ↔ WP User assignments ----------------------------------
        dbDelta("CREATE TABLE {$p}erta_provider_users (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            provider_id BIGINT UNSIGNED NOT NULL,
            user_id     BIGINT UNSIGNED NOT NULL,
            role        VARCHAR(20)     NOT NULL DEFAULT 'staff' COMMENT 'manager|staff',
            PRIMARY KEY (id),
            UNIQUE  KEY uq_provider_user (provider_id, user_id),
            KEY         idx_user (user_id)
        ) {$charset};");

        // --- Settings (scoped key-value store) -------------------------------
        dbDelta("CREATE TABLE {$p}erta_settings (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope         VARCHAR(20)     NOT NULL COMMENT 'global|department|provider',
            scope_id      BIGINT UNSIGNED NULL,
            setting_key   VARCHAR(100)    NOT NULL,
            setting_value LONGTEXT        NULL,
            PRIMARY KEY (id),
            UNIQUE  KEY uq_scope_key (scope, scope_id, setting_key)
        ) {$charset};");

        // --- Working hours ---------------------------------------------------
        dbDelta("CREATE TABLE {$p}erta_working_hours (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope       VARCHAR(20)     NOT NULL COMMENT 'global|department|provider',
            scope_id    BIGINT UNSIGNED NULL,
            day_of_week TINYINT         NOT NULL COMMENT '1=Monday...7=Sunday (ISO-8601)',
            is_open     TINYINT(1)      NOT NULL DEFAULT 1,
            open_time   TIME            NOT NULL,
            close_time  TIME            NOT NULL,
            PRIMARY KEY (id),
            KEY         idx_scope (scope, scope_id),
            KEY         idx_day (day_of_week)
        ) {$charset};");

        // --- Breaks ----------------------------------------------------------
        dbDelta("CREATE TABLE {$p}erta_breaks (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope       VARCHAR(20)     NOT NULL COMMENT 'global|department|provider',
            scope_id    BIGINT UNSIGNED NULL,
            day_of_week TINYINT         NULL COMMENT 'NULL = every open day',
            start_time  TIME            NOT NULL,
            end_time    TIME            NOT NULL,
            name        VARCHAR(100)    NOT NULL DEFAULT '',
            break_type  VARCHAR(20)     NOT NULL DEFAULT 'custom' COMMENT 'short|lunch|custom',
            PRIMARY KEY (id),
            KEY         idx_scope (scope, scope_id)
        ) {$charset};");

        // --- Special days (holidays, custom open/close) ----------------------
        dbDelta("CREATE TABLE {$p}erta_special_days (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope            VARCHAR(20)     NOT NULL COMMENT 'global|department|provider',
            scope_id         BIGINT UNSIGNED NULL,
            date             DATE            NOT NULL,
            is_closed        TINYINT(1)      NOT NULL DEFAULT 1,
            custom_open_time TIME            NULL,
            custom_close_time TIME           NULL,
            name             VARCHAR(200)    NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY         idx_scope_date (scope, scope_id, date)
        ) {$charset};");

        // --- Forms -----------------------------------------------------------
        dbDelta("CREATE TABLE {$p}erta_forms (
            id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope      VARCHAR(20)     NOT NULL DEFAULT 'global' COMMENT 'global|department|provider',
            scope_id   BIGINT UNSIGNED NULL,
            name       VARCHAR(200)    NOT NULL,
            fields     LONGTEXT        NOT NULL COMMENT 'JSON field definitions',
            is_active  TINYINT(1)      NOT NULL DEFAULT 1,
            created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY        idx_scope (scope, scope_id),
            KEY        idx_active (is_active)
        ) {$charset};");

        // --- Appointments ----------------------------------------------------
        dbDelta("CREATE TABLE {$p}erta_appointments (
            id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            provider_id            BIGINT UNSIGNED NOT NULL,
            department_id          BIGINT UNSIGNED NULL,
            form_id                BIGINT UNSIGNED NULL,
            customer_user_id       BIGINT UNSIGNED NULL,
            customer_name          VARCHAR(200)    NOT NULL,
            customer_email         VARCHAR(200)    NOT NULL,
            customer_phone         VARCHAR(50)     NULL,
            start_datetime         DATETIME        NOT NULL,
            end_datetime           DATETIME        NOT NULL,
            duration_minutes       INT             NOT NULL,
            status                 VARCHAR(30)     NOT NULL DEFAULT 'pending'
                                   COMMENT 'pending|confirmed|cancelled|completed|no_show|rescheduled|waitlisted',
            payment_status         VARCHAR(20)     NOT NULL DEFAULT 'not_required'
                                   COMMENT 'not_required|pending|paid|refunded',
            payment_amount         DECIMAL(10,2)   NULL,
            payment_gateway        VARCHAR(50)     NULL,
            payment_transaction_id VARCHAR(200)    NULL,
            form_data              LONGTEXT        NULL COMMENT 'JSON submitted form values',
            notes                  TEXT            NULL,
            internal_notes         TEXT            NULL COMMENT 'Visible to admin/provider only',
            cancellation_reason    TEXT            NULL,
            rescheduled_from       BIGINT UNSIGNED NULL COMMENT 'Original appointment ID',
            group_id               BIGINT UNSIGNED NULL COMMENT 'Pro: group appointment reference',
            arrival_buffer_minutes INT             NOT NULL DEFAULT 0,
            created_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY         idx_provider_dt   (provider_id, start_datetime),
            KEY         idx_status        (status),
            KEY         idx_customer_email (customer_email),
            KEY         idx_customer_user  (customer_user_id),
            KEY         idx_group          (group_id)
        ) {$charset};");

        // --- Notification templates ------------------------------------------
        dbDelta("CREATE TABLE {$p}erta_notification_templates (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type     VARCHAR(100)    NOT NULL
                           COMMENT 'appointment_confirmed|cancelled|rescheduled|reminder|...',
            channel        VARCHAR(20)     NOT NULL COMMENT 'email|sms',
            recipient_type VARCHAR(20)     NOT NULL COMMENT 'customer|provider|admin',
            subject        VARCHAR(500)    NULL COMMENT 'Email subject',
            body           LONGTEXT        NOT NULL,
            is_active      TINYINT(1)      NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY         idx_event (event_type),
            KEY         idx_active (is_active)
        ) {$charset};");

        // --- Notification log ------------------------------------------------
        dbDelta("CREATE TABLE {$p}erta_notification_logs (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            appointment_id BIGINT UNSIGNED NULL,
            channel        VARCHAR(20)     NOT NULL,
            recipient      VARCHAR(200)    NOT NULL,
            event_type     VARCHAR(100)    NOT NULL,
            status         VARCHAR(20)     NOT NULL DEFAULT 'sent' COMMENT 'sent|failed',
            error_message  TEXT            NULL,
            sent_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY         idx_appointment (appointment_id),
            KEY         idx_event (event_type),
            KEY         idx_status (status)
        ) {$charset};");

        // OAuth tokens — used by Google Calendar integration (Pro).
        dbDelta("CREATE TABLE {$p}erta_oauth_tokens (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id       BIGINT UNSIGNED NOT NULL,
            service       VARCHAR(50)     NOT NULL COMMENT 'google_calendar',
            access_token  TEXT            NOT NULL,
            refresh_token TEXT            NOT NULL,
            expires_at    DATETIME        NOT NULL,
            created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY  uniq_user_service (user_id, service),
            KEY         idx_service (service)
        ) {$charset};");

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    // -------------------------------------------------------------------------
    // Default data
    // -------------------------------------------------------------------------

    /**
     * Inserts default plugin settings on first activation.
     * Skips if settings already exist (idempotent).
     */
    private function seedDefaultSettings(): void
    {
        // Guard: already seeded.
        if (get_option('erta_settings_seeded')) {
            return;
        }

        $defaults = [
            // Feature flags
            'departments_enabled'    => true,
            'provider_type'          => 'individual', // individual | unit
            'auto_confirm'           => true,
            'require_payment'        => false,

            // Scheduling defaults
            'slot_duration'          => 30,   // minutes
            'slot_interval'          => 30,   // minutes between slot starts
            'buffer_before'          => 0,    // minutes before each appointment
            'buffer_after'           => 0,    // minutes after each appointment
            'minimum_notice'         => 60,   // minutes in advance required to book
            'maximum_advance'        => 60,   // days in advance allowed to book
            'arrival_buffer'         => 0,    // minutes customer should arrive early

            // Working hours (Mon–Fri, 09:00–17:00)
            'default_working_hours'  => [
                1 => ['open' => true,  'start' => '09:00', 'end' => '17:00'],
                2 => ['open' => true,  'start' => '09:00', 'end' => '17:00'],
                3 => ['open' => true,  'start' => '09:00', 'end' => '17:00'],
                4 => ['open' => true,  'start' => '09:00', 'end' => '17:00'],
                5 => ['open' => true,  'start' => '09:00', 'end' => '17:00'],
                6 => ['open' => false, 'start' => '09:00', 'end' => '17:00'],
                7 => ['open' => false, 'start' => '09:00', 'end' => '17:00'],
            ],

            // Notifications
            'admin_email'            => get_option('admin_email'),
            'notify_admin_on_new'    => true,
            'notify_customer_on_new' => true,
            'email_from_name'        => get_bloginfo('name'),
            'email_from_address'     => get_option('admin_email'),

            // Misc
            'currency'               => 'USD',
            'currency_symbol'        => '$',
            'date_format'            => 'Y-m-d',
            'time_format'            => 'H:i',
            'remove_data_on_uninstall' => false,
        ];

        foreach ($defaults as $key => $value) {
            $this->settings->set('global', null, $key, $value);
        }

        $this->seedDefaultNotificationTemplates();
        $this->seedDefaultForm();

        update_option('erta_settings_seeded', true);
    }

    /**
     * Inserts built-in email notification templates.
     */
    private function seedDefaultNotificationTemplates(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'erta_notification_templates';

        $templates = [
            // --- Customer templates ------------------------------------------
            [
                'event'     => 'appointment_confirmed',
                'channel'   => 'email',
                'recipient' => 'customer',
                'subject'   => __('Your appointment is confirmed – {{appointment_date}}', 'ert-appointment'),
                'body'      => $this->getTemplateContent('customer-confirmed'),
                'is_active' => 1,
            ],
            [
                'event'     => 'appointment_cancelled',
                'channel'   => 'email',
                'recipient' => 'customer',
                'subject'   => __('Your appointment has been cancelled', 'ert-appointment'),
                'body'      => $this->getTemplateContent('customer-cancelled'),
                'is_active' => 1,
            ],
            [
                'event'     => 'appointment_rescheduled',
                'channel'   => 'email',
                'recipient' => 'customer',
                'subject'   => __('Your appointment has been rescheduled – {{appointment_date}}', 'ert-appointment'),
                'body'      => $this->getTemplateContent('customer-rescheduled'),
                'is_active' => 1,
            ],
            [
                'event'     => 'appointment_reminder_24h',
                'channel'   => 'email',
                'recipient' => 'customer',
                'subject'   => __('Reminder: Your appointment is tomorrow', 'ert-appointment'),
                'body'      => $this->getTemplateContent('customer-reminder'),
                'is_active' => 1,
            ],
            [
                'event'     => 'appointment_pending',
                'channel'   => 'email',
                'recipient' => 'customer',
                'subject'   => __('Your booking request has been received', 'ert-appointment'),
                'body'      => $this->getTemplateContent('customer-pending'),
                'is_active' => 1,
            ],
            // --- Admin / provider templates ----------------------------------
            [
                'event'     => 'appointment_confirmed',
                'channel'   => 'email',
                'recipient' => 'admin',
                'subject'   => __('New appointment booked – {{customer_name}}', 'ert-appointment'),
                'body'      => $this->getTemplateContent('admin-new-appointment'),
                'is_active' => 1,
            ],
            [
                'event'     => 'appointment_cancelled',
                'channel'   => 'email',
                'recipient' => 'provider',
                'subject'   => __('Appointment cancelled – {{customer_name}}', 'ert-appointment'),
                'body'      => $this->getTemplateContent('provider-cancelled'),
                'is_active' => 1,
            ],
        ];

        foreach ($templates as $template) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table} WHERE event = %s AND channel = %s AND recipient = %s",
                $template['event'],
                $template['channel'],
                $template['recipient']
            ));

            if (! $exists) {
                $wpdb->insert($table, array_merge($template, [
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]));
            }
        }
    }

    /**
     * Returns the raw template body for a given template slug.
     * Templates support {{placeholder}} syntax.
     */
    private function getTemplateContent(string $slug): string
    {
        $site = '{{site_name}}';
        $templates = [

            'customer-pending' => "Hi {{customer_name}},

We've received your booking request. It is currently pending confirmation.

📅 Date: {{appointment_date}}
🕐 Time: {{appointment_time}}
👤 Provider: {{provider_name}}

We'll send you another email once your appointment is confirmed.

To manage your booking: {{manage_url}}

Thanks,
{$site}",

            'customer-confirmed' => "Hi {{customer_name}},

Your appointment is confirmed! We look forward to seeing you.

📅 Date: {{appointment_date}}
🕐 Time: {{appointment_time}}
👤 Provider: {{provider_name}}

Please arrive {{arrival_buffer}} minutes early.

{{zoom_link}}

To cancel or reschedule: {{manage_url}}

Thanks,
{$site}",

            'customer-cancelled' => "Hi {{customer_name}},

Your appointment on {{appointment_date}} at {{appointment_time}} has been cancelled.

Reason: {{cancellation_reason}}

We'd love to see you again. Book a new appointment here:
{{booking_url}}

Thanks,
{$site}",

            'customer-rescheduled' => "Hi {{customer_name}},

Your appointment has been rescheduled.

📅 New Date: {{appointment_date}}
🕐 New Time: {{appointment_time}}
👤 Provider: {{provider_name}}

{{zoom_link}}

To manage your appointment: {{manage_url}}

Thanks,
{$site}",

            'customer-reminder' => "Hi {{customer_name}},

This is a friendly reminder about your appointment tomorrow.

📅 Date: {{appointment_date}}
🕐 Time: {{appointment_time}}
👤 Provider: {{provider_name}}

Please arrive {{arrival_buffer}} minutes early.

{{zoom_link}}

Need to reschedule? {{manage_url}}

See you soon,
{$site}",

            'admin-new-appointment' => "A new appointment has been booked.

👤 Customer: {{customer_name}}
📧 Email: {{customer_email}}
📞 Phone: {{customer_phone}}
📅 Date: {{appointment_date}}
🕐 Time: {{appointment_time}}
👨‍⚕️ Provider: {{provider_name}}
📝 Notes: {{notes}}

View in admin: {{admin_url}}",

            'provider-cancelled' => "An appointment has been cancelled.

👤 Customer: {{customer_name}}
📅 Date: {{appointment_date}}
🕐 Time: {{appointment_time}}
Reason: {{cancellation_reason}}

View in admin: {{admin_url}}",

        ];

        return $templates[$slug] ?? '';
    }

    /**
     * Seeds a minimal default booking form.
     */
    private function seedDefaultForm(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'erta_forms';

        $exists = $wpdb->get_var("SELECT id FROM {$table} WHERE scope = 'global' LIMIT 1");
        if ($exists) {
            return;
        }

        $fields = [
            ['id' => 'customer_name',  'type' => 'text',   'label' => 'Full Name',     'required' => true,  'system' => true],
            ['id' => 'customer_email', 'type' => 'email',  'label' => 'Email Address', 'required' => true,  'system' => true],
            ['id' => 'customer_phone', 'type' => 'tel',    'label' => 'Phone Number',  'required' => false, 'system' => true],
            ['id' => 'notes',          'type' => 'textarea','label' => 'Notes',         'required' => false, 'system' => false],
            ['id' => '__calendar__',   'type' => 'calendar','label' => 'Select Date',  'required' => true,  'system' => true,
                'placeholder' => true, 'description' => 'Date and time selector – renders the booking calendar widget'],
        ];

        $wpdb->insert($table, [
            'scope'     => 'global',
            'scope_id'  => null,
            'name'      => 'Default Booking Form',
            'fields'    => wp_json_encode($fields),
            'is_active' => 1,
        ]);
    }

    // -------------------------------------------------------------------------
    // Roles and capabilities
    // -------------------------------------------------------------------------

    /**
     * Adds the erta_provider role with limited capabilities.
     */
    private function createRoles(): void
    {
        // Remove first to always reset capabilities cleanly on activation.
        remove_role('erta_provider');

        add_role(
            'erta_provider',
            __('Appointment Provider', 'ert-appointment'),
            [
                'read'                   => true,
                'erta_view_appointments'  => true,
                'erta_manage_own_appointments' => true,
            ]
        );

        // Grant full access to admins.
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('erta_manage_all');
            $admin->add_cap('erta_view_reports');
            $admin->add_cap('erta_manage_settings');
        }
    }
}
