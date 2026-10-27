<?php
/**
 * Unit tests for UtmNormalizer and InputValue.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Services\Support\InputValue;
use Utmatra\Services\Support\UtmNormalizer;

return array(
	'lowercases and dashes whitespace runs' => static function () {
		utmatra_assert_same( 'my-big-newsletter', UtmNormalizer::normalize( '  My  Big	Newsletter ' ) );
		utmatra_assert_same( 'été-promo', UtmNormalizer::normalize( 'ÉTÉ Promo' ) );
	},

	'empty stays empty'                     => static function () {
		utmatra_assert_same( '', UtmNormalizer::normalize( '   ' ) );
	},

	'scalars read as strings'               => static function () {
		$input = array(
			'a' => 'text',
			'b' => 12,
			'c' => true,
		);
		utmatra_assert_same( 'text', InputValue::string( $input, 'a' ) );
		utmatra_assert_same( '12', InputValue::string( $input, 'b' ) );
		utmatra_assert_same( '1', InputValue::string( $input, 'c' ) );
	},

	'missing, null and non-scalar read as empty' => static function () {
		$input = array(
			'n' => null,
			'a' => array( 'x' ),
		);
		utmatra_assert_same( '', InputValue::string( $input, 'missing' ) );
		utmatra_assert_same( '', InputValue::string( $input, 'n' ) );
		utmatra_assert_same( '', InputValue::string( $input, 'a' ) );
	},
);
