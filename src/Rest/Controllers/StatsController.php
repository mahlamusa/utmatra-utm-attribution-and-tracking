<?php
/**
 * StatsController — thin REST adapter for GET utmatra/v1/stats.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Rest\Controllers
 */

namespace Utmatra\Rest\Controllers;

use Utmatra\Services\StatsService;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * StatsController
 *
 * The route is registered by RestController with its args and permission
 * callback; this class only maps the sanitised params to StatsService.
 *
 * @since 1.1.0
 */
class StatsController {

	/**
	 * GET utmatra/v1/stats
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_stats( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			StatsService::get(
				(int) $request->get_param( 'period' ),
				(string) $request->get_param( 'campaign' ),
				(string) $request->get_param( 'since' ),
				(string) $request->get_param( 'until' )
			),
			200
		);
	}
}
