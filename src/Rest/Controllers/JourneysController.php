<?php
/**
 * JourneysController — the GET utmatra/v1/journeys and utmatra/v1/journeys/<visitor_id> handlers.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Rest\Controllers
 */

namespace Utmatra\Rest\Controllers;

use Utmatra\Services\JourneysService;
use Utmatra\Services\Support\ResultAdapter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * JourneysController
 *
 * Thin REST adapter: reads the route parameters and returns the
 * JourneysService result. The routes are registered by RestController.
 *
 * @since 1.1.0
 */
class JourneysController {

	/**
	 * GET utmatra/v1/journeys — conversions with their preceding touches.
	 *
	 * @see JourneysService::get_journeys()
	 *
	 * @since 1.1.0 Moved from RestController::get_journeys().
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_journeys( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( JourneysService::get_journeys( RequestInput::pick( $request, array( 'period', 'since', 'until', 'page', 'per_page' ) ) ), 200 );
	}

	/**
	 * GET utmatra/v1/journeys/<visitor_id> — one visitor's full event list.
	 *
	 * Answers an unknown visitor with a core-style WP_Error (404), as 1.0.0 did.
	 *
	 * @see JourneysService::get_journey()
	 *
	 * @since 1.1.0 Moved from RestController::get_journey().
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_journey( WP_REST_Request $request ) {
		$result = JourneysService::get_journey( (string) $request->get_param( 'visitor_id' ) );

		return $result->is_ok() ? ResultAdapter::to_rest_response( $result ) : ResultAdapter::to_wp_error( $result );
	}
}
