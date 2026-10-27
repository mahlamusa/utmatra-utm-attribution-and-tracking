<?php
/**
 * NotificationsController — the REST adapter for the test email and test webhook routes.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Rest\Controllers
 */

namespace Utmatra\Rest\Controllers;

use Utmatra\Services\NotificationsService;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * NotificationsController
 *
 * Passes the route arguments to NotificationsService and returns its
 * `{ sent }` body. The routes themselves are registered by RestController.
 *
 * @since 1.1.0
 */
class NotificationsController {

	/**
	 * POST utmatra/v1/test-email — send the weekly summary now.
	 *
	 * Sends the report regardless of the weekly toggle so admins can preview
	 * the email and confirm delivery works before enabling the schedule.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController; delegates to NotificationsService.
	 *
	 * @return WP_REST_Response
	 */
	public static function send_test_email(): WP_REST_Response {
		return new WP_REST_Response( NotificationsService::send_test_email(), 200 );
	}

	/**
	 * POST utmatra/v1/test-webhook — send a synthetic payload now.
	 *
	 * Lets the admin verify their receiver and HMAC signature checking work
	 * before relying on it for real events. The optional `preset` and
	 * `leads` args forward the live settings-form values so a preset can be
	 * tested before it is saved. No-ops (returns sent:false) when no URL is
	 * configured or no event is enabled.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController; delegates to NotificationsService.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function send_test_webhook( WP_REST_Request $request ): WP_REST_Response {
		$body = NotificationsService::send_test_webhook(
			(string) $request->get_param( 'preset' ),
			$request->has_param( 'leads' ) ? (bool) $request->get_param( 'leads' ) : null
		);

		return new WP_REST_Response( $body, 200 );
	}
}
