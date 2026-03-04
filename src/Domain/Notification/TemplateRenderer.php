<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Notification;

/**
 * Renders notification templates by replacing {{placeholder}} tokens
 * with their context values.
 *
 * Kept intentionally simple (no Twig/Blade dependency) so the plugin
 * ships without a template engine requirement.
 */
final class TemplateRenderer
{
    /**
     * Renders subject and body templates with the given context.
     *
     * @param string               $subject  Subject template (may be empty for SMS).
     * @param string               $body     Body template.
     * @param array<string, string> $context  Key-value pairs for placeholder substitution.
     * @return array{subject: string, body: string}
     */
    public function render(string $subject, string $body, array $context): array
    {
        return [
            'subject' => $this->substitute($subject, $context),
            'body'    => $this->substitute($body, $context),
        ];
    }

    /**
     * Returns a list of all recognised placeholder tokens with descriptions.
     * Used to populate the template editor hint list in admin.
     *
     * @return list<array{token: string, description: string}>
     */
    public function availablePlaceholders(): array
    {
        $hints = [
            ['token' => '{{customer_name}}',       'description' => __('Customer full name', 'ert-appointment')],
            ['token' => '{{customer_email}}',      'description' => __('Customer email address', 'ert-appointment')],
            ['token' => '{{customer_phone}}',      'description' => __('Customer phone number', 'ert-appointment')],
            ['token' => '{{appointment_date}}',    'description' => __('Appointment date', 'ert-appointment')],
            ['token' => '{{appointment_time}}',    'description' => __('Appointment time', 'ert-appointment')],
            ['token' => '{{provider_name}}',       'description' => __('Provider / unit name', 'ert-appointment')],
            ['token' => '{{arrival_buffer}}',      'description' => __('Minutes to arrive early', 'ert-appointment')],
            ['token' => '{{cancellation_reason}}', 'description' => __('Cancellation reason', 'ert-appointment')],
            ['token' => '{{notes}}',               'description' => __('Appointment notes', 'ert-appointment')],
            ['token' => '{{site_name}}',           'description' => __('Website name', 'ert-appointment')],
            ['token' => '{{site_url}}',            'description' => __('Website URL', 'ert-appointment')],
            ['token' => '{{manage_url}}',          'description' => __('Customer manage appointment URL', 'ert-appointment')],
            ['token' => '{{booking_url}}',         'description' => __('Booking page URL', 'ert-appointment')],
            ['token' => '{{admin_url}}',           'description' => __('Admin appointment detail URL', 'ert-appointment')],
        ];

        /**
         * Pro add-ons register extra placeholder hints here so the admin
         * notification template editor can display them in the hint list.
         *
         * @param list<array{token: string, description: string}> $hints
         */
        return (array) apply_filters('erta_available_placeholder_hints', $hints);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Replaces all {{key}} tokens in $template with values from $context.
     * Unknown tokens are left as-is (not replaced with empty string) to make
     * misconfiguration visible rather than silently hiding it.
     */
    private function substitute(string $template, array $context): string
    {
        $search  = [];
        $replace = [];

        foreach ($context as $key => $value) {
            $search[]  = '{{' . $key . '}}';
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, $template);
    }
}
