<?php
/**
 * PresetsController — REST adapter for the UTM preset routes.
 *
 * Routes (registered by RestController):
 *   POST   utmatra/v1/presets      — save a UTM preset
 *   DELETE utmatra/v1/presets/<id> — delete a UTM preset
 *
 * Presets are listed by GET utmatra/v1/links (see LinksController).
 *
 * @since 1.1.0
 *
 * @package Utmatra\Rest\Controllers
 */

namespace Utmatra\Rest\Controllers;

use Utmatra\Services\PresetsService;
use Utmatra\Services\Support\ResultAdapter;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * PresetsController
 *
 * Passes the request params to PresetsService and returns its Result as
 * the REST response.
 *
 * @since 1.1.0
 */
class PresetsController {

	/**
	 * POST utmatra/v1/presets — save a named UTM preset.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController; delegates to PresetsService.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function create_preset( WP_REST_Request $request ): WP_REST_Response {
		return ResultAdapter::to_rest_response( PresetsService::create( RequestInput::pick( $request, array_merge( array( 'name' ), PresetsService::UTM_FIELDS ) ) ) );
	}

	/**
	 * DELETE utmatra/v1/presets/<id> — delete a saved UTM preset.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController; delegates to PresetsService.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function delete_preset( WP_REST_Request $request ): WP_REST_Response {
		return ResultAdapter::to_rest_response( PresetsService::delete( (string) $request->get_param( 'id' ) ) );
	}
}
