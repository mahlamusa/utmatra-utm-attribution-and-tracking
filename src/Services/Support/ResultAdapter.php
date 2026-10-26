<?php
/**
 * ResultAdapter — turns a service Result into a REST response or WP_Error.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services\Support
 */

namespace Utmatra\Services\Support;

use WP_Error;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * ResultAdapter
 *
 * The REST adapter keeps the 1.0.0 response shapes: a success body is the
 * payload itself, an error body is `{ "message": "..." }`, both with the
 * Result's status. The WP_Error adapter is what abilities (and REST routes
 * that already answer with a WP_Error) return.
 *
 * @since 1.1.0
 */
class ResultAdapter {

	/**
	 * Convert a Result to the WP_REST_Response the utmatra/v1 routes return.
	 *
	 * @since 1.1.0
	 *
	 * @param Result $result Service outcome.
	 *
	 * @return WP_REST_Response
	 */
	public static function to_rest_response( Result $result ): WP_REST_Response {
		if ( $result->is_ok() ) {
			return new WP_REST_Response( $result->data(), $result->status() );
		}

		return new WP_REST_Response( array( 'message' => $result->message() ), $result->status() );
	}

	/**
	 * Convert an error Result to `WP_Error( code, message, array( 'status' => N ) )`.
	 *
	 * Only call it with an error Result; use to_ability_result() when the
	 * Result may be a success.
	 *
	 * @since 1.1.0
	 *
	 * @param Result $result Error outcome.
	 *
	 * @return WP_Error
	 */
	public static function to_wp_error( Result $result ): WP_Error {
		return new WP_Error( $result->code(), $result->message(), array( 'status' => $result->status() ) );
	}

	/**
	 * Convert a Result to an ability execute-callback return value.
	 *
	 * @since 1.1.0
	 *
	 * @param Result $result Service outcome.
	 *
	 * @return mixed|WP_Error The payload on success, a WP_Error otherwise.
	 */
	public static function to_ability_result( Result $result ) {
		return $result->is_ok() ? $result->data() : self::to_wp_error( $result );
	}
}
