<?php
/**
 * UtmNormalizer — the house style for UTM values.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services\Support
 */

namespace Utmatra\Services\Support;

defined( 'ABSPATH' ) || exit;

/**
 * UtmNormalizer
 *
 * Shared by link and preset validation so the same value is never stored
 * in two spellings. Moved from RestController unchanged.
 *
 * @since 1.1.0
 */
class UtmNormalizer {

	/**
	 * Normalize a UTM value for consistent reporting.
	 *
	 * Lowercases the value and replaces whitespace runs with `-` so
	 * `My Newsletter` and `my-newsletter` never fragment into two report
	 * rows. Mirrors the normalization in assets/js/dashboard.js — keep in
	 * sync.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController::normalize_utm().
	 *
	 * @param string $value Sanitized UTM value.
	 *
	 * @return string
	 */
	public static function normalize( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		$value = (string) preg_replace( '/\s+/u', '-', $value );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
	}
}
