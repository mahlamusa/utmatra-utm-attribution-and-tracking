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
);
