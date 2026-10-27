<?php
/**
 * Unit tests for SettingsController: the agent opt-in is saved only from the dashboard.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Abilities\OptIn;
use Utmatra\Reports\WeeklyEmail;
use Utmatra\Rest\Controllers\SettingsController;

// The real weekly email needs WordPress the unit bootstrap does not stub.
if ( ! class_exists( WeeklyEmail::class ) ) {
	require_once __DIR__ . '/fixtures/WeeklyEmailStub.php';
}

/**
 * A settings request turning the agent opt-in on, with an optional X-WP-Nonce header.
 *
 * @since 1.1.0
 *
 * @param string|null $nonce Nonce header value, or null to send none.
 *
 * @return WP_REST_Request
 */
function utmatra_test_opt_in_request( $nonce ): WP_REST_Request {
	$request = new WP_REST_Request();
	$request->set_param( 'retention_days', 90 );
	$request->set_param( 'allow_agent_settings', true );
	if ( null !== $nonce ) {
		$request->set_header( 'X-WP-Nonce', $nonce );
	}
	return $request;
}

return array(
	'the opt-in is saved with a valid dashboard nonce'       => static function () {
		$response = SettingsController::save_settings( utmatra_test_opt_in_request( 'valid-nonce' ) );
		utmatra_assert_same( 200, $response->get_status() );
		utmatra_assert_same( 1, $GLOBALS['utmatra_test']['options'][ OptIn::OPTION ] ?? null );
		utmatra_assert_false( array_key_exists( 'allow_agent_settings', $response->get_data() ) );
		utmatra_assert_same( 90, $GLOBALS['utmatra_test']['options']['utmatra_retention_days'] );
	},

	'the opt-in is ignored without a nonce'                  => static function () {
		$response = SettingsController::save_settings( utmatra_test_opt_in_request( null ) );
		utmatra_assert_same( 200, $response->get_status() );
		utmatra_assert_false( array_key_exists( OptIn::OPTION, $GLOBALS['utmatra_test']['options'] ) );
		utmatra_assert_same( 90, $GLOBALS['utmatra_test']['options']['utmatra_retention_days'] );
	},

	'the opt-in is ignored with an invalid nonce'            => static function () {
		$response = SettingsController::save_settings( utmatra_test_opt_in_request( 'forged-nonce' ) );
		utmatra_assert_same( 200, $response->get_status() );
		utmatra_assert_false( array_key_exists( OptIn::OPTION, $GLOBALS['utmatra_test']['options'] ) );
		utmatra_assert_same( 90, $GLOBALS['utmatra_test']['options']['utmatra_retention_days'] );
	},

	'custom params sent as a string are sanitized as before' => static function () {
		utmatra_assert_same( sanitize_text_field( ' Coupon, <b>aff</b> ' ), SettingsController::sanitize_custom_params( ' Coupon, <b>aff</b> ' ) );
		utmatra_assert_same( '', SettingsController::sanitize_custom_params( '' ) );
	},

	'custom params sent as a list are joined with commas'    => static function () {
		utmatra_assert_same( 'coupon,aff', SettingsController::sanitize_custom_params( array( 'coupon', 'aff' ) ) );
		utmatra_assert_same( '', SettingsController::sanitize_custom_params( array() ) );
	},

	'a custom params list is saved instead of clearing them' => static function () {
		$GLOBALS['utmatra_test']['options']['utmatra_custom_params'] = array( 'promo' );
		$request = new WP_REST_Request();
		$request->set_param( 'custom_params', SettingsController::sanitize_custom_params( array( 'Coupon', 'aff' ) ) );
		$response = SettingsController::save_settings( $request );
		utmatra_assert_same( 200, $response->get_status() );
		utmatra_assert_same( array( 'coupon', 'aff' ), $GLOBALS['utmatra_test']['options']['utmatra_custom_params'] );
	},

	'a custom params list with a reserved name is rejected'  => static function () {
		$GLOBALS['utmatra_test']['options']['utmatra_custom_params'] = array( 'promo' );
		$request = new WP_REST_Request();
		$request->set_param( 'custom_params', SettingsController::sanitize_custom_params( array( 'coupon', 'utm_extra' ) ) );
		$response = SettingsController::save_settings( $request );
		utmatra_assert_same( 400, $response->get_status() );
		utmatra_assert_same( array( 'promo' ), $GLOBALS['utmatra_test']['options']['utmatra_custom_params'] );
	},

	'a custom params list with a non-string item is a 400'   => static function () {
		foreach ( array( array( 'aff' ), false, true, null, 1 ) as $item ) {
			$error = SettingsController::sanitize_custom_params( array( 'coupon', $item ) );
			utmatra_assert_instance_of( WP_Error::class, $error );
			utmatra_assert_same( 'rest_invalid_param', $error->get_error_code() );
			utmatra_assert_same( 400, $error->get_error_data()['status'] );
		}
	},
);
