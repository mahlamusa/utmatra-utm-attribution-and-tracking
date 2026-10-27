<?php
/**
 * Unit tests for EventsController: route parameters reach EventsService.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Rest\Controllers\EventsController;

return array(
	'events forwards the filters and pagination'      => static function () {
		$request = new WP_REST_Request();
		$request->set_param( 'kind', 'visit' );
		$request->set_param( 'channel', 'email' );
		$request->set_param( 'visitor_id', '0b5f0c8e-2c1a-4e7a-9f0e-6c1f2a3b4c5d' );
		$request->set_param( 'per_page', 3 );
		$request->set_param( 'page', 2 );

		$response = EventsController::get_events( $request );
		$query    = $GLOBALS['utmatra_test']['queries'][0];

		utmatra_assert_same( 200, $response->get_status() );
		utmatra_assert_same( 3, $response->get_data()['per_page'] );
		utmatra_assert_true( false !== strpos( $query, "AND link_id = 'visit'" ) );
		utmatra_assert_true( false !== strpos( $query, "AND channel = 'email'" ) );
		utmatra_assert_true( false !== strpos( $query, "AND visitor_id = '0b5f0c8e-2c1a-4e7a-9f0e-6c1f2a3b4c5d'" ) );
		utmatra_assert_true( false !== strpos( $query, 'LIMIT 3 OFFSET 3' ) );
	},

	'export forwards include visits and keeps 10,000' => static function () {
		$request = new WP_REST_Request();
		$request->set_param( 'include_visits', true );

		$response = EventsController::export_events( $request );
		$query    = $GLOBALS['utmatra_test']['queries'][0];

		utmatra_assert_same( array( 'rows' => array() ), $response->get_data() );
		utmatra_assert_false( strpos( $query, "link_id != 'visit'" ) );
		utmatra_assert_true( false !== strpos( $query, 'LIMIT 10000' ) );
	},
);
