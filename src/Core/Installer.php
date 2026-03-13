<?php

declare(strict_types=1);

namespace ERTAppointment\Core;

use function esc_sql;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- installer intentionally manages plugin schema/data during lifecycle and repair.

use ERTAppointment\Settings\SettingsManager;

/**
 * Handles plugin activation, deactivation, and database schema management.
 * Uses dbDelta for safe incremental table updates.
 */
final class Installer {

	private const DB_VERSION_OPTION = 'erta_db_version';
	private const DB_VERSION        = '1.0.1';
	private const REQUIRED_TABLES   = array(
		'erta_departments',
		'erta_providers',
		'erta_provider_users',
		'erta_settings',
		'erta_working_hours',
		'erta_breaks',
		'erta_special_days',
		'erta_forms',
		'erta_appointments',
		'erta_notification_templates',
		'erta_notification_logs',
		'erta_oauth_tokens',
	);

	public function __construct(
		private readonly SettingsManager $settings
	) {}

	private function tableSql( string $tableName ): string {
		return esc_sql( $tableName );
	}

	// -------------------------------------------------------------------------
	// Lifecycle
	// -------------------------------------------------------------------------

	/**
	 * Runs on plugin activation.
	 * Creates/updates tables, sets default options, and flushes rewrite rules.
	 */
	public function activate(): void {
		$this->runMigrations();
		$this->repairDuplicateSettingsRows();
		$this->seedDefaultSettings();
		$this->seedDefaultNotificationTemplates();
		$this->createRoles();

		flush_rewrite_rules();

		update_option( 'erta_activated_at', current_time( 'mysql' ) );

		/**
		 * Fires after the plugin has been activated and tables are ready.
		 * Extensions (Pro add-on) can hook here to run their own migrations.
		 */
		do_action( 'erta_activated' );
	}

	/**
	 * Repairs installation when activation hook was missed or after updates.
	 * Lightweight checks run on init; heavy operations run only if needed.
	 */
	public function maybeRepairInstallation(): void {
		$installedVersion = (string) get_option( self::DB_VERSION_OPTION, '' );
		$providerRole     = get_role( 'erta_provider' );
		$hasTemplates     = $this->hasNotificationTemplates();

		if ( $installedVersion === self::DB_VERSION && $providerRole !== null && $hasTemplates ) {
			return;
		}

		$this->runMigrations();
		$this->repairDuplicateSettingsRows();
		$this->seedDefaultSettings();
		$this->seedDefaultNotificationTemplates();
		$this->createRoles();
	}

	/**
	 * Forces installation repair for missing tables/roles/capabilities.
	 */
	public function repairInstallation(): void {
		$this->runMigrations();
		$this->repairDuplicateSettingsRows();
		$this->seedDefaultSettings();
		$this->seedDefaultNotificationTemplates();
		$this->createRoles();
	}

	private function hasNotificationTemplates(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'erta_notification_templates';
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );

		return $count > 0;
	}

	/**
	 * One-time repair migration for duplicated settings rows.
	 *
	 * - Normalizes legacy global rows from NULL scope_id to 0
	 * - Keeps only the most recent row for each (scope, scope_id, setting_key)
	 */
	private function repairDuplicateSettingsRows(): void {
		if ( get_option( 'erta_settings_repaired_v1' ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'erta_settings';
		$tableSql = $table;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET scope_id = 0 WHERE scope = 'global' AND scope_id IS NULL",
				$tableSql
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE s1 FROM %i s1
			 INNER JOIN %i s2
			   ON s1.scope = s2.scope
			  AND ((s1.scope_id = s2.scope_id) OR (s1.scope_id IS NULL AND s2.scope_id IS NULL))
			  AND s1.setting_key = s2.setting_key
			  AND s1.id < s2.id",
				$tableSql,
				$tableSql
			)
		);

		update_option( 'erta_settings_repaired_v1', true );
	}

	/**
	 * Returns installation integrity checklist for admin diagnostics.
	 * Used by Settings page to show missing tables/roles/capabilities.
	 *
	 * @return array{all_ok:bool,db_version:string,installed_version:string,items:array<int,array{key:string,label:string,ok:bool}>}
	 */
	public function getInstallationChecklist(): array {
		global $wpdb;

		$items = array();

		foreach ( self::REQUIRED_TABLES as $tableSuffix ) {
			$tableName = $wpdb->prefix . $tableSuffix;
			$exists    = (string) $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $tableName )
			) === $tableName;

			$items[] = array(
				'key'   => 'table_' . $tableSuffix,
				'label' => sprintf( 'Database table: %s', $tableName ),
				'ok'    => $exists,
			);
		}

		$provider = get_role( 'erta_provider' );
		$items[]  = array(
			'key'   => 'role_erta_provider',
			'label' => 'Role: erta_provider',
			'ok'    => $provider !== null,
		);

		$providerCaps = array( 'erta_view_appointments', 'erta_manage_own_appointments' );
		foreach ( $providerCaps as $cap ) {
			$items[] = array(
				'key'   => 'provider_cap_' . $cap,
				'label' => sprintf( 'Provider capability: %s', $cap ),
				'ok'    => $provider !== null && $provider->has_cap( $cap ),
			);
		}

		$admin     = get_role( 'administrator' );
		$adminCaps = array( 'erta_manage_all', 'erta_view_reports', 'erta_manage_settings' );
		foreach ( $adminCaps as $cap ) {
			$items[] = array(
				'key'   => 'admin_cap_' . $cap,
				'label' => sprintf( 'Administrator capability: %s', $cap ),
				'ok'    => $admin !== null && $admin->has_cap( $cap ),
			);
		}

		$installedVersion = (string) get_option( self::DB_VERSION_OPTION, '' );
		$items[]          = array(
			'key'   => 'db_version_match',
			'label' => sprintf( 'Database version option matches plugin (%s)', self::DB_VERSION ),
			'ok'    => $installedVersion === self::DB_VERSION,
		);

		$allOk = ! array_filter( $items, static fn( array $item ): bool => $item['ok'] !== true );

		return array(
			'all_ok'            => $allOk,
			'db_version'        => self::DB_VERSION,
			'installed_version' => $installedVersion,
			'items'             => $items,
		);
	}

	/**
	 * Runs on plugin deactivation.
	 */
	public function deactivate(): void {
		// Clear all scheduled WP-Cron events registered by this plugin.
		foreach ( array( 'erta_send_reminders', 'erta_cleanup_logs' ) as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}

		flush_rewrite_rules();

		/**
		 * Fires when the plugin is deactivated.
		 * Pro add-on uses this to clear its own cron jobs.
		 */
		do_action( 'erta_deactivated' );
	}

	// -------------------------------------------------------------------------
	// Database schema
	// -------------------------------------------------------------------------

	/**
	 * Creates or updates all plugin tables using dbDelta.
	 * Safe to call multiple times; dbDelta only makes necessary changes.
	 */
	public function runMigrations(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		// --- Departments -----------------------------------------------------
		dbDelta(
			"CREATE TABLE {$p}erta_departments (
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
        ) {$charset};"
		);

		// --- Providers (individuals or units) --------------------------------
		dbDelta(
			"CREATE TABLE {$p}erta_providers (
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
        ) {$charset};"
		);

		// --- Provider ↔ WP User assignments ----------------------------------
		dbDelta(
			"CREATE TABLE {$p}erta_provider_users (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            provider_id BIGINT UNSIGNED NOT NULL,
            user_id     BIGINT UNSIGNED NOT NULL,
            role        VARCHAR(20)     NOT NULL DEFAULT 'staff' COMMENT 'manager|staff',
            PRIMARY KEY (id),
            UNIQUE  KEY uq_provider_user (provider_id, user_id),
            KEY         idx_user (user_id)
        ) {$charset};"
		);

		// --- Settings (scoped key-value store) -------------------------------
		dbDelta(
			"CREATE TABLE {$p}erta_settings (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope         VARCHAR(20)     NOT NULL COMMENT 'global|department|provider',
            scope_id      BIGINT UNSIGNED NULL,
            setting_key   VARCHAR(100)    NOT NULL,
            setting_value LONGTEXT        NULL,
            PRIMARY KEY (id),
            UNIQUE  KEY uq_scope_key (scope, scope_id, setting_key)
        ) {$charset};"
		);

		// --- Working hours ---------------------------------------------------
		dbDelta(
			"CREATE TABLE {$p}erta_working_hours (
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
        ) {$charset};"
		);

		// --- Breaks ----------------------------------------------------------
		dbDelta(
			"CREATE TABLE {$p}erta_breaks (
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
        ) {$charset};"
		);

		// --- Special days (holidays, custom open/close) ----------------------
		dbDelta(
			"CREATE TABLE {$p}erta_special_days (
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
        ) {$charset};"
		);

		// --- Forms -----------------------------------------------------------
		dbDelta(
			"CREATE TABLE {$p}erta_forms (
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
        ) {$charset};"
		);

		// --- Appointments ----------------------------------------------------
		dbDelta(
		"CREATE TABLE {$p}erta_appointments (
			id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			provider_id            BIGINT UNSIGNED NULL,  // <-- NULL olarak tanımlı
			department_id          BIGINT UNSIGNED NULL,
			form_id                BIGINT UNSIGNED NULL,
			customer_user_id       BIGINT UNSIGNED NULL,
			customer_name          VARCHAR(200)    NOT NULL,
			customer_email         VARCHAR(200)    NOT NULL,
			customer_phone         VARCHAR(50)     NULL,
			start_datetime         DATETIME        NOT NULL,
			end_datetime           DATETIME        NOT NULL,
			duration_minutes       INT             NOT NULL,
			status                 VARCHAR(30)     NOT NULL DEFAULT 'pending',
			payment_status         VARCHAR(20)     NOT NULL DEFAULT 'not_required',
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
		) {$charset};"
	);

		// --- Notification templates ------------------------------------------
		dbDelta(
			"CREATE TABLE {$p}erta_notification_templates (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_type     VARCHAR(100)    NOT NULL,
			channel        VARCHAR(20)     NOT NULL,
			recipient_type VARCHAR(20)     NOT NULL,
			subject        VARCHAR(500)    NULL,
            body           LONGTEXT        NOT NULL,
            is_active      TINYINT(1)      NOT NULL DEFAULT 1,
			created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY         idx_event (event_type),
            KEY         idx_active (is_active)
        ) {$charset};"
		);

		// --- Notification log ------------------------------------------------
		dbDelta(
			"CREATE TABLE {$p}erta_notification_logs (
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
        ) {$charset};"
		);

		// OAuth tokens — used by Google Calendar integration (Pro).
		dbDelta(
			"CREATE TABLE {$p}erta_oauth_tokens (
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
        ) {$charset};"
		);

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	// -------------------------------------------------------------------------
	// Default data
	// -------------------------------------------------------------------------

	/**
	 * Inserts default plugin settings on first activation.
	 * Skips if settings already exist (idempotent).
	 */
	private function seedDefaultSettings(): void {
		// Guard: already seeded.
		if ( get_option( 'erta_settings_seeded' ) ) {
			return;
		}

		$defaults = array(
			// Feature flags
			'departments_enabled'      => true,
			'provider_type'            => 'individual', // individual | unit
			'auto_confirm'             => true,
			'require_payment'          => false,

			// Scheduling defaults
			'slot_duration'            => 30,   // minutes
			'slot_interval'            => 30,   // minutes between slot starts
			'buffer_before'            => 0,    // minutes before each appointment
			'buffer_after'             => 0,    // minutes after each appointment
			'minimum_notice'           => 60,   // minutes in advance required to book
			'maximum_advance'          => 60,   // days in advance allowed to book
			'arrival_buffer'           => 0,    // minutes customer should arrive early

			// Working hours (Mon–Fri, 09:00–17:00)
			'default_working_hours'    => array(
				1 => array(
					'open'  => true,
					'start' => '09:00',
					'end'   => '17:00',
				),
				2 => array(
					'open'  => true,
					'start' => '09:00',
					'end'   => '17:00',
				),
				3 => array(
					'open'  => true,
					'start' => '09:00',
					'end'   => '17:00',
				),
				4 => array(
					'open'  => true,
					'start' => '09:00',
					'end'   => '17:00',
				),
				5 => array(
					'open'  => true,
					'start' => '09:00',
					'end'   => '17:00',
				),
				6 => array(
					'open'  => false,
					'start' => '09:00',
					'end'   => '17:00',
				),
				7 => array(
					'open'  => false,
					'start' => '09:00',
					'end'   => '17:00',
				),
			),

			// Notifications
			'admin_email'              => get_option( 'admin_email' ),
			'notify_admin_on_new'      => true,
			'notify_customer_on_new'   => true,
			'email_from_name'          => get_bloginfo( 'name' ),
			'email_from_address'       => get_option( 'admin_email' ),

			// Misc
			'currency'                 => 'USD',
			'currency_symbol'          => '$',
			'date_format'              => 'Y-m-d',
			'time_format'              => 'H:i',
			'remove_data_on_uninstall' => false,
		);

		foreach ( $defaults as $key => $value ) {
			$this->settings->set( 'global', null, $key, $value );
		}

		$this->seedDefaultNotificationTemplates();
		$this->seedDefaultForm();

		update_option( 'erta_settings_seeded', true );
	}

	/**
	 * Inserts built-in email notification templates.
	 */
	private function seedDefaultNotificationTemplates(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'erta_notification_templates';
		$tableSql = esc_sql( $table );

		$templates = array(
			// --- Customer templates ------------------------------------------
			array(
				'event_type'     => 'appointment_confirmed',
				'channel'   => 'email',
				'recipient_type' => 'customer',
				'subject'   => __( 'Your appointment is confirmed – {{appointment_date}}', 'ert-appointment' ),
				'body'      => $this->getTemplateContent( 'customer-confirmed' ),
				'is_active' => 1,
			),
			array(
				'event_type'     => 'appointment_cancelled',
				'channel'   => 'email',
				'recipient_type' => 'customer',
				'subject'   => __( 'Your appointment has been cancelled', 'ert-appointment' ),
				'body'      => $this->getTemplateContent( 'customer-cancelled' ),
				'is_active' => 1,
			),
			array(
				'event_type'     => 'appointment_rescheduled',
				'channel'   => 'email',
				'recipient_type' => 'customer',
				'subject'   => __( 'Your appointment has been rescheduled – {{appointment_date}}', 'ert-appointment' ),
				'body'      => $this->getTemplateContent( 'customer-rescheduled' ),
				'is_active' => 1,
			),
			array(
				'event_type'     => 'appointment_reminder_24h',
				'channel'   => 'email',
				'recipient_type' => 'customer',
				'subject'   => __( 'Reminder: Your appointment is tomorrow', 'ert-appointment' ),
				'body'      => $this->getTemplateContent( 'customer-reminder' ),
				'is_active' => 1,
			),
			array(
				'event_type'     => 'appointment_pending',
				'channel'   => 'email',
				'recipient_type' => 'customer',
				'subject'   => __( 'Your booking request has been received', 'ert-appointment' ),
				'body'      => $this->getTemplateContent( 'customer-pending' ),
				'is_active' => 1,
			),
			// --- Admin / provider templates ----------------------------------
			array(
				'event_type'     => 'appointment_confirmed',
				'channel'   => 'email',
				'recipient_type' => 'admin',
				'subject'   => __( 'New appointment booked – {{customer_name}}', 'ert-appointment' ),
				'body'      => $this->getTemplateContent( 'admin-new-appointment' ),
				'is_active' => 1,
			),
			array(
				'event_type'     => 'appointment_cancelled',
				'channel'   => 'email',
				'recipient_type' => 'provider',
				'subject'   => __( 'Appointment cancelled – {{customer_name}}', 'ert-appointment' ),
				'body'      => $this->getTemplateContent( 'provider-cancelled' ),
				'is_active' => 1,
			),
		);

		foreach ( $templates as $template ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM %i WHERE event_type = %s AND channel = %s AND recipient_type = %s',
					$tableSql,
					$template['event_type'],
					$template['channel'],
					$template['recipient_type']
				)
			);

			if ( ! $exists ) {
				$wpdb->insert(
					$table,
					array_merge(
						$template,
						array(
							'created_at' => current_time( 'mysql' ),
							'updated_at' => current_time( 'mysql' ),
						)
					)
				);
			}
		}
	}

	/**
	 * Returns the raw template body for a given template slug.
	 * Templates support {{placeholder}} syntax.
	 */
	private function getTemplateContent( string $slug ): string {
		$site      = '{{site_name}}';
		$templates = array(

			'customer-pending'      => "Hi {{customer_name}},

We've received your booking request. It is currently pending confirmation.

📅 Date: {{appointment_date}}
🕐 Time: {{appointment_time}}
👤 Provider: {{provider_name}}

We'll send you another email once your appointment is confirmed.

To manage your booking: {{manage_url}}

Thanks,
{$site}",

			'customer-confirmed'    => "Hi {{customer_name}},

Your appointment is confirmed! We look forward to seeing you.

📅 Date: {{appointment_date}}
🕐 Time: {{appointment_time}}
👤 Provider: {{provider_name}}

Please arrive {{arrival_buffer}} minutes early.

{{zoom_link}}

To cancel or reschedule: {{manage_url}}

Thanks,
{$site}",

			'customer-cancelled'    => "Hi {{customer_name}},

Your appointment on {{appointment_date}} at {{appointment_time}} has been cancelled.

Reason: {{cancellation_reason}}

We'd love to see you again. Book a new appointment here:
{{booking_url}}

Thanks,
{$site}",

			'customer-rescheduled'  => "Hi {{customer_name}},

Your appointment has been rescheduled.

📅 New Date: {{appointment_date}}
🕐 New Time: {{appointment_time}}
👤 Provider: {{provider_name}}

{{zoom_link}}

To manage your appointment: {{manage_url}}

Thanks,
{$site}",

			'customer-reminder'     => "Hi {{customer_name}},

This is a friendly reminder about your appointment tomorrow.

📅 Date: {{appointment_date}}
🕐 Time: {{appointment_time}}
👤 Provider: {{provider_name}}

Please arrive {{arrival_buffer}} minutes early.

{{zoom_link}}

Need to reschedule? {{manage_url}}

See you soon,
{$site}",

			'admin-new-appointment' => 'A new appointment has been booked.

👤 Customer: {{customer_name}}
📧 Email: {{customer_email}}
📞 Phone: {{customer_phone}}
📅 Date: {{appointment_date}}
🕐 Time: {{appointment_time}}
👨‍⚕️ Provider: {{provider_name}}
📝 Notes: {{notes}}

View in admin: {{admin_url}}',

			'provider-cancelled'    => 'An appointment has been cancelled.

👤 Customer: {{customer_name}}
📅 Date: {{appointment_date}}
🕐 Time: {{appointment_time}}
Reason: {{cancellation_reason}}

View in admin: {{admin_url}}',

		);

		return $templates[ $slug ] ?? '';
	}

	/**
	 * Seeds a minimal default booking form.
	 */
	private function seedDefaultForm(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'erta_forms';

		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM %i WHERE scope = %s LIMIT 1', $table, 'global' )
		);
		if ( $exists ) {
			return;
		}

		$fields = array(
			array(
				'id'       => 'customer_name',
				'type'     => 'text',
				'label'    => __( 'Full Name', 'ert-appointment' ),
				'required' => true,
				'system'   => true,
			),
			array(
				'id'       => 'customer_email',
				'type'     => 'email',
				'label'    => __( 'Email Address', 'ert-appointment' ),
				'required' => true,
				'system'   => true,
			),
			array(
				'id'       => 'customer_phone',
				'type'     => 'tel',
				'label'    => __( 'Phone Number', 'ert-appointment' ),
				'required' => false,
				'system'   => true,
			),
			array(
				'id'       => 'notes',
				'type'     => 'textarea',
				'label'    => __( 'Notes', 'ert-appointment' ),
				'required' => false,
				'system'   => false,
			),
			array(
				'id'          => '__calendar__',
				'type'        => 'calendar',
				'label'       => __( 'Select Date', 'ert-appointment' ),
				'required'    => true,
				'system'      => true,
				'placeholder' => true,
				'description' => __( 'Date and time selector – renders the booking calendar widget', 'ert-appointment' ),
			),
		);

		$wpdb->insert(
			$table,
			array(
				'scope'     => 'global',
				'scope_id'  => null,
				'name'      => 'Default Booking Form',
				'fields'    => wp_json_encode( $fields ),
				'is_active' => 1,
			)
		);
	}

	// -------------------------------------------------------------------------
	// Roles and capabilities
	// -------------------------------------------------------------------------

	/**
	 * Adds the erta_provider role with limited capabilities.
	 */
	private function createRoles(): void {
		// Remove first to always reset capabilities cleanly on activation.
		remove_role( 'erta_provider' );

		add_role(
			'erta_provider',
			__( 'Appointment Provider', 'ert-appointment' ),
			array(
				'read'                         => true,
				'erta_view_appointments'       => true,
				'erta_manage_own_appointments' => true,
			)
		);

		// Grant full access to admins.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'erta_manage_all' );
			$admin->add_cap( 'erta_view_reports' );
			$admin->add_cap( 'erta_manage_settings' );
		}
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
