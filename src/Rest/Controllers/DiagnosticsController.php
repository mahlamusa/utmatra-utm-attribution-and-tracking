<?php
/**
 * DiagnosticsController — thin REST adapter for GET utmatra/v1/diagnostics.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Rest\Controllers
 */

namespace Utmatra\Rest\Controllers;

use Utmatra\Services\DiagnosticsService;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * DiagnosticsController
 *
 * The route is registered by RestController with its permission callback;
 * this class only wraps DiagnosticsService's result in a response.
 *
 * @since 1.1.0
 */
class DiagnosticsController {

	/**
	 * GET utmatra/v1/diagnostics — run the Tracking Doctor checks.
	 *
	 * @since 1.1.0
	 *
	 * @return WP_REST_Response
	 */
	public static function get_diagnostics(): WP_REST_Response {
		return new WP_REST_Response( DiagnosticsService::run(), 200 );
	}
}
