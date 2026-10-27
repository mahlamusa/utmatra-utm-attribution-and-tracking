<?php
/**
 * LinksController — REST adapter for the managed link routes.
 *
 * Routes (registered by RestController):
 *   GET    utmatra/v1/links       — managed links with per-link click counts
 *   POST   utmatra/v1/links       — create a managed link
 *   PUT    utmatra/v1/links/<id>  — update a managed link
 *   DELETE utmatra/v1/links/<id>  — delete a managed link
 *
 * @since 1.1.0
 *
 * @package Utmatra\Rest\Controllers
 */

namespace Utmatra\Rest\Controllers;

use Utmatra\Services\LinkValidator;
use Utmatra\Services\LinksService;
use Utmatra\Services\Support\Result;
use Utmatra\Services\Support\ResultAdapter;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * LinksController
 *
 * Passes the request params to LinksService and returns its Result as the
 * REST response.
 *
 * @since 1.1.0
 */
class LinksController {

	/**
	 * GET utmatra/v1/links
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController; delegates to LinksService.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_links(): WP_REST_Response {
		return ResultAdapter::to_rest_response( Result::ok( LinksService::all() ) );
	}

	/**
	 * POST utmatra/v1/links — create a managed link.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController; delegates to LinksService.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function create_link( WP_REST_Request $request ): WP_REST_Response {
		return ResultAdapter::to_rest_response( LinksService::create( RequestInput::pick( $request, array_merge( array( 'id', 'label', 'destination', 'custom' ), LinkValidator::UTM_FIELDS ) ) ) );
	}

	/**
	 * PUT utmatra/v1/links/<id> — update a managed link.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController; delegates to LinksService.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function update_link( WP_REST_Request $request ): WP_REST_Response {
		return ResultAdapter::to_rest_response( LinksService::update( (string) $request->get_param( 'id' ), RequestInput::pick( $request, array_merge( array( 'label', 'destination', 'custom' ), LinkValidator::UTM_FIELDS ) ) ) );
	}

	/**
	 * DELETE utmatra/v1/links/<id> — delete a managed link.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController; delegates to LinksService.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function delete_link( WP_REST_Request $request ): WP_REST_Response {
		return ResultAdapter::to_rest_response( LinksService::delete( (string) $request->get_param( 'id' ) ) );
	}
}
