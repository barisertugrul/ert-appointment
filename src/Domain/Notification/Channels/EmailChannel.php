<?php

declare(strict_types=1);

namespace ERTAppointment\Domain\Notification\Channels;

use RuntimeException;
use ERTAppointment\Domain\Notification\ChannelInterface;

/**
 * Email delivery channel using wp_mail().
 * Included in Lite; SMS channel is Pro-only.
 */
final class EmailChannel implements ChannelInterface {

	public function getName(): string {
		return 'email';
	}

	/**
	 * Sends an HTML email via wp_mail.
	 *
	 * @throws RuntimeException When wp_mail returns false.
	 */
	public function send( string $recipient, string $subject, string $body ): void {
		$fromName    = get_option( 'erta_email_from_name', get_bloginfo( 'name' ) );
		$fromAddress = get_option( 'erta_email_from_address', get_option( 'admin_email' ) );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			"From: {$fromName} <{$fromAddress}>",
		);

		/**
		 * Filter: allows customising email headers before sending.
		 *
		 * @param string[] $headers
		 * @param string   $recipient
		 * @param string   $subject
		 */
		$headers = apply_filters( 'erta_email_headers', $headers, $recipient, $subject );

		$htmlBody = $this->wrapInTemplate( $body, $subject );

		$sent = wp_mail( $recipient, $subject, $htmlBody, $headers );

		if ( ! $sent ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: recipient email address */
					esc_html__( 'wp_mail failed to deliver email to "%s".', 'ert-appointment' ),
					esc_html( $recipient )
				)
			);
		}
	}

	// -------------------------------------------------------------------------
	// HTML template wrapper
	// -------------------------------------------------------------------------

	/**
	 * Wraps the plain notification body in a simple, branded HTML email shell.
	 * Operators can override this by filtering 'erta_email_html_template'.
	 */
	private function wrapInTemplate( string $body, string $subject ): string {
		$siteName = esc_html( get_bloginfo( 'name' ) );
		$siteUrl  = esc_url( get_bloginfo( 'url' ) );
		$title    = esc_html( $subject );
		$bodyHtml = nl2br( esc_html( $body ) );
		$year     = gmdate( 'Y' );

		$html  = '<!DOCTYPE html>';
		$html .= '<html lang="en">';
		$html .= '<head>';
		$html .= '<meta charset="UTF-8">';
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
		$html .= '<title>' . $title . '</title>';
		$html .= '<style>';
		$html .= 'body { margin: 0; padding: 0; background: #f4f4f4; font-family: Arial, sans-serif; }';
		$html .= '.wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 6px; overflow: hidden; }';
		$html .= '.header  { background: #2563eb; padding: 24px 32px; }';
		$html .= '.header a { color: #fff; text-decoration: none; font-size: 20px; font-weight: bold; }';
		$html .= '.body    { padding: 32px; color: #333; font-size: 15px; line-height: 1.7; }';
		$html .= '.footer  { background: #f9f9f9; padding: 16px 32px; text-align: center;';
		$html .= 'font-size: 12px; color: #999; border-top: 1px solid #eee; }';
		$html .= '</style>';
		$html .= '</head>';
		$html .= '<body>';
		$html .= '<div class="wrapper">';
		$html .= '<div class="header">';
		$html .= '<a href="' . $siteUrl . '">' . $siteName . '</a>';
		$html .= '</div>';
		$html .= '<div class="body">' . $bodyHtml . '</div>';
		$html .= '<div class="footer">';
		$html .= '&copy; ' . $year . ' <a href="' . $siteUrl . '" style="color:#999">' . $siteName . '</a>. ';
		$html .= 'All rights reserved.';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</body>';
		$html .= '</html>';

		/**
		 * Filter: override the entire HTML email template.
		 *
		 * @param string $html
		 * @param string $body    Plain body text (already nl2br'd in the default template)
		 * @param string $subject
		 */
		return apply_filters( 'erta_email_html_template', $html, $body, $subject );
	}
}
