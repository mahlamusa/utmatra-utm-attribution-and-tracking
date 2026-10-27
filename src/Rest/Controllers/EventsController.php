<?php
/**
 * EventsController — the GET utmatra/v1/events and utmatra/v1/export handlers.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Rest\Controllers
 */

namespace Utmatra\Rest\Controllers;

use Utmatra\Services\EventsService;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * EventsController
 *
 * Thin REST adapter: reads the route parameters and returns the
 * EventsService result. The routes are registered by RestController.
 *
 * @since 1.1.0
 */
class EventsController {

	/**
	 * GET utmatra/v1/events — paginated, filterable event rows.
	 *
	 * @see EventsService::get_events()
	 *
	 * @since 1.1.0 Moved from RestController::get_events().
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_events( WP_REST_Request $request ): WP_REST_Response {
		$args = RequestInput::pick( $request, array( 'period', 'campaign', 'since', 'until', 'channel', 'kind', 'visitor_id', 'page', 'per_page' ) );

		return new WP_REST_Response( EventsService::get_events( $args ), 200 );
	}

	/**
	 * GET utmatra/v1/export — raw event rows for the dashboard's CSV export.
	 *
	 * Capped at the newest EventsService::REST_EXPORT_LIMIT rows.
	 *
	 * @see EventsService::export()
	 *
	 * @since 1.1.0 Moved from RestController::export_events().
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function export_events( WP_REST_Request $request ): WP_REST_Response {
		$args = RequestInput::pick( $request, array( 'period', 'campaign', 'since', 'until', 'include_visits' ) );

		return new WP_REST_Response( array( 'rows' => EventsService::export( $args, EventsService::REST_EXPORT_LIMIT ) ), 200 );
	}
}
