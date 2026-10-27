<?php
/**
 * NotificationsService — sends the test weekly email and test webhook.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services
 */

namespace Utmatra\Services;

use Utmatra\Reports\ConversionWebhook;
use Utmatra\Reports\WeeklyEmail;

defined( 'ABSPATH' ) || exit;

/**
 * NotificationsService
 *
 * Holds the test send logic shared by the REST test routes and the test
 * send abilities. Takes plain values, never request objects, and returns
 * the `{ sent }` body the routes answer with.
 *
 * @since 1.1.0
 */
class NotificationsService {

	/**
	 * Send the weekly summary now, even when the weekly email is off.
	 *
	 * @since 1.1.0
	 *
	 * @return array{sent: bool} Whether wp_mail() accepted the email.
	 */
	public static function send_test_email(): array {
		return array( 'sent' => WeeklyEmail::send( true ) );
	}

	/**
	 * Send a synthetic payload to the configured webhook URL now.
	 *
	 * Sends nothing (sent:false) when no URL is configured or no event is
	 * enabled.
	 *
	 * @since 1.1.0
	 *
	 * @param string    $preset Overrides the saved preset when it is a known preset.
	 * @param bool|null $leads  Overrides the saved leads toggle when not null.
	 *
	 * @return array{sent: bool} Whether the receiver accepted the payload.
	 */
	public static function send_test_webhook( string $preset = '', ?bool $leads = null ): array {
		return array( 'sent' => ConversionWebhook::send_test( $preset, $leads ) );
	}
}
