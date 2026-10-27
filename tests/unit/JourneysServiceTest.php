<?php
/**
 * Unit tests for JourneysService.
 *
 * $wpdb is the bootstrap stub: queries are recorded in the `queries` test
 * state and every read returns the `db_rows` / `db_var` state.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Services\JourneysService;

return array(
	'get journeys clamps per page and page'               => static function () {
		$GLOBALS['utmatra_test']['db_var'] = '201';
		$result                            = JourneysService::get_journeys(
			array(
				'per_page' => 500,
				'page'     => 0,
			)
		);

		utmatra_assert_same( 100, $result['per_page'] );
		utmatra_assert_same( 1, $result['page'] );
		utmatra_assert_same( 201, $result['total'] );
		utmatra_assert_same( 3, $result['total_pages'] );
		utmatra_assert_same( 201, $result['without_visitor'] );
		utmatra_assert_same( array(), $result['journeys'] );
		utmatra_assert_true( false !== strpos( $GLOBALS['utmatra_test']['queries'][0], "is_conversion = 1 AND visitor_id != ''" ) );
		utmatra_assert_true( false !== strpos( $GLOBALS['utmatra_test']['queries'][0], 'LIMIT 100 OFFSET 0' ) );
	},

	'get journeys builds a journey per conversion'        => static function () {
		$GLOBALS['utmatra_test']['db_rows'] = array(
			array(
				'id'            => '9',
				'link_id'       => 'order',
				'visitor_id'    => '0b5f0c8e-2c1a-4e7a-9f0e-6c1f2a3b4c5d',
				'clicked_at'    => '2026-01-02 10:00:00',
				'is_conversion' => '1',
			),
		);
		$result                             = JourneysService::get_journeys( array() );
		$journey                            = $result['journeys'][0];

		utmatra_assert_same( 50, $result['per_page'] );
		utmatra_assert_same( 9, $journey['conversion']['id'] );
		utmatra_assert_same( '0b5f0c8e-2c1a-4e7a-9f0e-6c1f2a3b4c5d', $journey['visitor_id'] );
		utmatra_assert_same( null, $journey['first_touch'] );
		utmatra_assert_same( 0, $journey['touch_count'] );
		utmatra_assert_same( array(), $journey['touches'] );
		utmatra_assert_false( $journey['touches_truncated'] );
		utmatra_assert_true( false !== strpos( $GLOBALS['utmatra_test']['queries'][3], 'LIMIT 1001' ) );
	},

	'get journeys counts preceding clicks and visits'     => static function () {
		$visitor                            = '0b5f0c8e-2c1a-4e7a-9f0e-6c1f2a3b4c5d';
		$GLOBALS['utmatra_test']['db_rows'] = array(
			array(
				'id'         => '1',
				'link_id'    => 'visit',
				'visitor_id' => $visitor,
				'clicked_at' => '2026-01-01 10:00:00',
			),
			array(
				'id'            => '2',
				'link_id'       => 'order',
				'visitor_id'    => $visitor,
				'clicked_at'    => '2026-01-02 10:00:00',
				'is_conversion' => '1',
			),
		);
		$journey                            = JourneysService::get_journeys( array() )['journeys'][1];

		utmatra_assert_same( 2, $journey['conversion']['id'] );
		utmatra_assert_same( 1, $journey['touch_count'] );
		utmatra_assert_same( 1, $journey['first_touch']['id'] );
		utmatra_assert_same( 1, $journey['last_touch']['id'] );
		utmatra_assert_same( 1, count( $journey['touches'] ) );
	},

	'get journey is a 404 error for an unknown visitor'  => static function () {
		$result = JourneysService::get_journey( '0b5f0c8e-2c1a-4e7a-9f0e-6c1f2a3b4c5d' );

		utmatra_assert_true( $result->is_error() );
		utmatra_assert_same( 'utmatra_not_found', $result->code() );
		utmatra_assert_same( 404, $result->status() );
	},

	'get journey returns the events ascending'           => static function () {
		$GLOBALS['utmatra_test']['db_rows'] = array(
			array(
				'id'            => '2',
				'link_id'       => 'lead',
				'clicked_at'    => '2026-01-02 10:00:00',
				'is_conversion' => '1',
			),
			array(
				'id'         => '1',
				'link_id'    => 'visit',
				'clicked_at' => '2026-01-01 10:00:00',
			),
		);
		$data                               = JourneysService::get_journey( '0b5f0c8e-2c1a-4e7a-9f0e-6c1f2a3b4c5d' )->data();

		utmatra_assert_same( 1, $data['events'][0]['id'] );
		utmatra_assert_same( 'lead', $data['events'][1]['kind'] );
		utmatra_assert_same( 1, $data['conversions'] );
		utmatra_assert_same( '2026-01-01 10:00:00', $data['first_seen'] );
		utmatra_assert_same( '2026-01-02 10:00:00', $data['last_seen'] );
		utmatra_assert_false( $data['truncated'] );
		utmatra_assert_true( false !== strpos( $GLOBALS['utmatra_test']['queries'][0], 'LIMIT 501' ) );
	},

	'get journey keeps the newest 500 events'             => static function () {
		$rows = array();
		for ( $id = 501; $id >= 1; $id-- ) {
			$rows[] = array(
				'id'         => (string) $id,
				'link_id'    => 'visit',
				'clicked_at' => '2026-01-01 10:00:00',
			);
		}
		$GLOBALS['utmatra_test']['db_rows'] = $rows;
		$data                               = JourneysService::get_journey( '0b5f0c8e-2c1a-4e7a-9f0e-6c1f2a3b4c5d' )->data();

		utmatra_assert_true( $data['truncated'] );
		utmatra_assert_same( 500, count( $data['events'] ) );
		utmatra_assert_same( 2, $data['events'][0]['id'] );
		utmatra_assert_same( 501, $data['events'][499]['id'] );
	},
);
