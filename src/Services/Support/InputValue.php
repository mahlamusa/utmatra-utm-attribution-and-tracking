<?php
/**
 * InputValue — reads untyped service input safely.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services\Support
 */

namespace Utmatra\Services\Support;

defined( 'ABSPATH' ) || exit;

/**
 * InputValue
 *
 * Services receive their input as a plain array (REST request params or
 * ability input), so a value can be of any type.
 *
 * @since 1.1.0
 */
class InputValue {

	/**
	 * Read an input value as a string, rejecting non-scalar values.
	 *
	 * A JSON body can carry arrays/objects for any parameter; casting those
	 * with `(string)` raises an Array-to-string warning and stores the
	 * literal `Array`. Non-scalar values are treated as absent instead.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController::param_string(); reads an input array instead of a request.
	 *
	 * @param array<string, mixed> $input Service input.
	 * @param string               $key   Parameter name.
	 *
	 * @return string
	 */
	public static function string( array $input, string $key ): string {
		$value = $input[ $key ] ?? null;
		return is_scalar( $value ) ? (string) $value : '';
	}
}
