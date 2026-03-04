<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Notification;

use ERTAppointment\Domain\Appointment\Appointment;

/**
 * Dispatches notifications for appointment lifecycle events.
 *
 * Looks up all active notification rules for the given event,
 * resolves the recipient and channel, renders the template,
 * and sends via the appropriate channel.
 *
 * Pro add-on can add channels (SMS) by filtering 'erta_notification_channels'.
 */
final class NotificationService
{
    /**
     * @param ChannelInterface[] $channels
     */
    public function __construct(
        private readonly array           $channels,
        private readonly TemplateRenderer $templateRenderer,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Dispatches all configured notifications for an event.
     *
     * @param string      $event        e.g. 'appointment_confirmed'
     * @param Appointment $appointment
     */
    public function dispatch(string $event, Appointment $appointment): void
    {
        $rules = $this->loadRules($event);

        foreach ($rules as $rule) {
            $channel = $this->resolveChannel($rule['channel']);
            if ($channel === null) {
                continue;
            }

            $recipient = $this->resolveRecipient($rule['recipient_type'], $appointment);
            if ($recipient === null) {
                continue;
            }

            $rendered = $this->templateRenderer->render(
                $rule['subject'] ?? '',
                $rule['body'],
                $this->buildContext($appointment)
            );

            try {
                $channel->send($recipient, $rendered['subject'], $rendered['body']);
                $this->log($event, $appointment->id, $rule['channel'], $recipient, 'sent');
            } catch (\Throwable $e) {
                $this->log($event, $appointment->id, $rule['channel'], $recipient, 'failed', $e->getMessage());
            }
        }

        /**
         * Fires after all channels have been attempted for this event.
         * @param string      $event
         * @param Appointment $appointment
         */
        do_action('erta_notification_dispatched', $event, $appointment);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Loads active notification template rules from the database.
     *
     * @return list<array<string, mixed>>
     */
    private function loadRules(string $event): array
    {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erta_notification_templates
                 WHERE event_type = %s AND is_active = 1",
                $event
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Finds a channel by name from the registered channels list.
     */
    private function resolveChannel(string $name): ?ChannelInterface
    {
        foreach ($this->channels as $channel) {
            if ($channel->getName() === $name) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * Resolves the notification recipient address (email or phone).
     */
    private function resolveRecipient(string $type, Appointment $appointment): ?string
    {
        return match ($type) {
            'customer' => $appointment->customerEmail ?: null,
            'provider' => $this->loadProviderEmail($appointment->providerId),
            'admin'    => get_option('erta_admin_email', get_option('admin_email')),
            default    => null,
        };
    }

    /**
     * Builds the template context array for placeholder substitution.
     *
     * @return array<string, string>
     */
    private function buildContext(Appointment $appointment): array
    {
        $context = [
            'customer_name'       => $appointment->customerName,
            'customer_email'      => $appointment->customerEmail,
            'customer_phone'      => $appointment->customerPhone,
            'appointment_date'    => $appointment->formattedDate(get_option('date_format', 'Y-m-d')),
            'appointment_time'    => $appointment->formattedTime(get_option('time_format', 'H:i')),
            'provider_name'       => $this->loadProviderName($appointment->providerId),
            'arrival_buffer'      => (string) $appointment->arrivalBufferMinutes,
            'cancellation_reason' => $appointment->cancellationReason,
            'notes'               => $appointment->notes,
            'site_name'           => get_bloginfo('name'),
            'site_url'            => get_bloginfo('url'),
            'manage_url'          => home_url('/my-appointments/?id=' . $appointment->id),
            'booking_url'         => home_url('/booking/'),
            'admin_url'           => admin_url('admin.php?page=erta-appointments&id=' . $appointment->id),
        ];

        /**
         * Allows Pro add-on (or third parties) to inject extra placeholder values.
         *
         * ZoomHooks hooks here to add {{zoom_link}}, {{zoom_password}}, {{zoom_start_url}}.
         * Any other integration can append its own keys.
         *
         * @param array<string,string>                   $context     Current context array.
         * @param \ERTAppointment\Domain\Appointment\Appointment $appointment The appointment being notified.
         */
        return (array) apply_filters('erta_template_placeholders', $context, $appointment);
    }

    private function loadProviderName(int $providerId): string
    {
        global $wpdb;
        return (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}erta_providers WHERE id = %d",
                $providerId
            )
        );
    }

    private function loadProviderEmail(int $providerId): ?string
    {
        global $wpdb;
        $email = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT email FROM {$wpdb->prefix}erta_providers WHERE id = %d",
                $providerId
            )
        );

        // Fall back to the first assigned user's email.
        if (! $email) {
            $userId = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT user_id FROM {$wpdb->prefix}erta_provider_users
                     WHERE provider_id = %d ORDER BY id ASC LIMIT 1",
                    $providerId
                )
            );
            if ($userId) {
                $user  = get_userdata((int) $userId);
                $email = $user ? $user->user_email : null;
            }
        }

        return $email ?: null;
    }

    /**
     * Writes a delivery record to the notification log.
     */
    private function log(
        string  $event,
        ?int    $appointmentId,
        string  $channel,
        string  $recipient,
        string  $status,
        ?string $errorMessage = null
    ): void {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'erta_notification_logs',
            [
                'appointment_id' => $appointmentId,
                'channel'        => $channel,
                'recipient'      => $recipient,
                'event_type'     => $event,
                'status'         => $status,
                'error_message'  => $errorMessage,
                'sent_at'        => current_time('mysql'),
            ]
        );
    }
}
