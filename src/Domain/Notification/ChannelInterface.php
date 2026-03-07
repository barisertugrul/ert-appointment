<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Notification;

/**
 * Contract for notification delivery channels.
 * Implementations: EmailChannel (lite), SmsChannel (pro).
 */
interface ChannelInterface {

	/**
	 * Returns the channel identifier used in notification rules.
	 * e.g. 'email', 'sms'
	 */
	public function getName(): string;

	/**
	 * Delivers a message to the given recipient.
	 *
	 * @throws \RuntimeException On delivery failure.
	 */
	public function send( string $recipient, string $subject, string $body ): void;
}
