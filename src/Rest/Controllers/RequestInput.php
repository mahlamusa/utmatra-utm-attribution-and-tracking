<?php
/**
 * RequestInput — builds service input arrays from a REST request.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Rest\Controllers
 */

namespace Utmatra\Rest\Controllers;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * RequestInput
 *
 * Reads each named field with WP_REST_Request::get_param() so a null
 * value in a higher-priority source (e.g. a JSON body) falls through to a
 * lower-priority one, exactly as the handlers did before they moved into
 * services. get_params() would let that null override instead.
 *
 * @since 1.1.0
 */
class RequestInput {

	/**
	 * Collect the named request params into a service input array.
	 *
	 * @since 1.1.0 Added to keep REST param resolution identical after the service extraction.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param string[]        $keys    Param names to read.
	 *
	 * @return array<string, mixed> Values keyed by param name; null when absent.
	 */
	public static function pick( WP_REST_Request $request, array $keys ): array {
		$input = array();
		foreach ( $keys as $key ) {
			$input[ $key ] = $request->get_param( $key );
		}
		return $input;
	}
}
