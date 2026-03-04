<?php

declare(strict_types=1);

namespace ERTAppointment\Admin;

use ERTAppointment\Container;

/**
 * Registers the admin menu structure and renders each page
 * by delegating to the appropriate controller.
 *
 * All admin pages are single-page-application shells that mount
 * the Vue admin bundle. The Vue router handles sub-navigation.
 */
final class AdminMenu
{
    public function __construct(private readonly Container $container) {}

    public function register(): void
    {
        // Only administrators (or users who can manage options) can access the admin panel.
        if (! current_user_can('manage_options')) {
            return;
        }

        add_menu_page(
            page_title: __('WP Appointment', 'ert-appointment'),
            menu_title: __('Appointments', 'ert-appointment'),
            capability: 'manage_options',
            menu_slug:  'erta-dashboard',
            callback:   [$this, 'renderAdminShell'],
            icon_url:   'dashicons-calendar-alt',
            position:   30
        );

        add_submenu_page(
            parent_slug:  'erta-dashboard',
            page_title:   __('Dashboard', 'ert-appointment'),
            menu_title:   __('Dashboard', 'ert-appointment'),
            capability:   'manage_options',
            menu_slug:    'erta-dashboard',
            callback:     [$this, 'renderAdminShell']
        );

        add_submenu_page(
            parent_slug:  'erta-dashboard',
            page_title:   __('Appointments', 'ert-appointment'),
            menu_title:   __('Appointments', 'ert-appointment'),
            capability:   'manage_options',
            menu_slug:    'erta-appointments',
            callback:     [$this, 'renderAdminShell']
        );

        add_submenu_page(
            parent_slug:  'erta-dashboard',
            page_title:   __('Departments', 'ert-appointment'),
            menu_title:   __('Departments', 'ert-appointment'),
            capability:   'manage_options',
            menu_slug:    'erta-departments',
            callback:     [$this, 'renderAdminShell']
        );

        add_submenu_page(
            parent_slug:  'erta-dashboard',
            page_title:   __('Providers', 'ert-appointment'),
            menu_title:   __('Providers', 'ert-appointment'),
            capability:   'manage_options',
            menu_slug:    'erta-providers',
            callback:     [$this, 'renderAdminShell']
        );

        add_submenu_page(
            parent_slug:  'erta-dashboard',
            page_title:   __('Forms', 'ert-appointment'),
            menu_title:   __('Forms', 'ert-appointment'),
            capability:   'manage_options',
            menu_slug:    'erta-forms',
            callback:     [$this, 'renderAdminShell']
        );

        add_submenu_page(
            parent_slug:  'erta-dashboard',
            page_title:   __('Notifications', 'ert-appointment'),
            menu_title:   __('Notifications', 'ert-appointment'),
            capability:   'manage_options',
            menu_slug:    'erta-notifications',
            callback:     [$this, 'renderAdminShell']
        );

        add_submenu_page(
            parent_slug:  'erta-dashboard',
            page_title:   __('Reports', 'ert-appointment'),
            menu_title:   __('Reports', 'ert-appointment'),
            capability:   'erta_view_reports',
            menu_slug:    'erta-reports',
            callback:     [$this, 'renderAdminShell']
        );

        add_submenu_page(
            parent_slug:  'erta-dashboard',
            page_title:   __('Settings', 'ert-appointment'),
            menu_title:   __('Settings', 'ert-appointment'),
            capability:   'erta_manage_settings',
            menu_slug:    'erta-settings',
            callback:     [$this, 'renderAdminShell']
        );

        /**
         * Fires after core admin menu pages are registered.
         * Pro add-on adds its own submenu pages here.
         */
        do_action('erta_admin_menu_registered', $this->container);
    }

    /**
     * Renders the single HTML shell that mounts the Vue admin SPA.
     * The Vue router reads the ?page= query param to determine which view to show.
     */
    public function renderAdminShell(): void
    {
        ?>
        <div id="erta-admin-app" data-page="<?php echo esc_attr($_GET['page'] ?? 'erta-dashboard'); ?>">
            <div class="erta-admin-loading">
                <span class="spinner is-active"></span>
                <?php esc_html_e('Loading…', 'ert-appointment'); ?>
            </div>
        </div>
        <?php
    }
}
