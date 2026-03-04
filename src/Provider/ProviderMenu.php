<?php

declare(strict_types=1);

namespace ERTAppointment\Provider;

use ERTAppointment\Container;

/**
 * Registers the reduced-capability admin menu for the erta_provider role.
 * Providers can only see their own appointments and basic profile settings.
 */
final class ProviderMenu
{
    public function __construct(private readonly Container $container) {}

    public function register(): void
    {
        // Skip if the user has full admin access (they see the full admin menu).
        if (current_user_can('erta_manage_all')) {
            return;
        }

        // Only show to users with provider-level capability.
        if (! current_user_can('erta_view_appointments')) {
            return;
        }

        add_menu_page(
            page_title: __('My Appointments', 'ert-appointment'),
            menu_title: __('My Appointments', 'ert-appointment'),
            capability: 'erta_view_appointments',
            menu_slug:  'erta-provider-dashboard',
            callback:   [$this, 'renderProviderShell'],
            icon_url:   'dashicons-calendar-alt',
            position:   30
        );

        add_submenu_page(
            parent_slug:  'erta-provider-dashboard',
            page_title:   __('Upcoming Appointments', 'ert-appointment'),
            menu_title:   __('Upcoming', 'ert-appointment'),
            capability:   'erta_view_appointments',
            menu_slug:    'erta-provider-dashboard',
            callback:     [$this, 'renderProviderShell']
        );

        add_submenu_page(
            parent_slug:  'erta-provider-dashboard',
            page_title:   __('Past Appointments', 'ert-appointment'),
            menu_title:   __('Past', 'ert-appointment'),
            capability:   'erta_view_appointments',
            menu_slug:    'erta-provider-past',
            callback:     [$this, 'renderProviderShell']
        );

        do_action('erta_provider_menu_registered', $this->container);
    }

    /**
     * Renders the SPA shell for the provider panel.
     * Shares the same Vue bundle; the router detects the page slug.
     */
    public function renderProviderShell(): void
    {
        ?>
        <div id="erta-provider-app"
             data-page="<?php echo esc_attr($_GET['page'] ?? 'erta-provider-dashboard'); ?>"
             data-user-id="<?php echo esc_attr(get_current_user_id()); ?>">
            <div class="erta-admin-loading">
                <span class="spinner is-active"></span>
                <?php esc_html_e('Loading…', 'ert-appointment'); ?>
            </div>
        </div>
        <?php
    }
}
