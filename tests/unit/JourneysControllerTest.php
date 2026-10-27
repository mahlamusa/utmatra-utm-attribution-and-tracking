<?php
/**
 * Unit tests for JourneysController: route parameters reach JourneysService.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Rest\Controllers\JourneysController;

return array(
	'journeys forwards the pagination'           => static function () {
		$request = new WP_REST_Request();
		$request->set_param( 'per_page', 3 );
		$request->set_param( 'page', 2 );

		$response = JourneysController::get_journeys( $request );

		utmatra_assert_same( 200, $response->get_status() );
		utmatra_assert_same( 3, $response->get_data()['per_page'] );
		utmatra_assert_true( false !== strpos( $GLOBALS['utmatra_test']['queries'][0], 'LIMIT 3 OFFSET 3' ) );
	},

	'journey answers an unknown visitor with a 404 wp error' => static function () {
		$request = new WP_REST_Request();
		$request->set_param( 'visitor_id', '0b5f0c8e-2c1a-4e7a-9f0e-6c1f2a3b4c5d' );

		$error = JourneysController::get_journey( $request );

		utmatra_assert_instance_of( WP_Error::class, $error );
		utmatra_assert_same( 'utmatra_not_found', $error->get_error_code() );
		utmatra_assert_same( array( 'status' => 404 ), $error->data );
	},

	'journey returns the events with a 200'      => static function () {
		$GLOBALS['utmatra_test']['db_rows'] = array(
			array(
				'id'         => '1',
				'link_id'    => 'visit',
				'clicked_at' => '2026-01-01 10:00:00',
			),
		);
		$request                            = new WP_REST_Request();
		$request->set_param( 'visitor_id', '0b5f0c8e-2c1a-4e7a-9f0e-6c1f2a3b4c5d' );

		$response = JourneysController::get_journey( $request );

		utmatra_assert_same( 200, $response->get_status() );
		utmatra_assert_same( '0b5f0c8e-2c1a-4e7a-9f0e-6c1f2a3b4c5d', $response->get_data()['visitor_id'] );
	},
);
