<?php
/**
 * Unit tests for StatsService.
 *
 * The test database returns the same rows for every query, so each row
 * also carries the keys the revenue breakdown reads.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Services\StatsService;

return array(
	'clicks_by_day keeps rows outside the scaffold out of the series' => static function () {
		$GLOBALS['utmatra_test']['db_rows'] = array(
			array(
				'day'     => '2025-02-28',
				'count'   => '3',
				'label'   => '',
				'orders'  => '0',
				'revenue' => '0',
			),
			array(
				'day'     => '2025-03-05',
				'count'   => '2',
				'label'   => '',
				'orders'  => '0',
				'revenue' => '0',
			),
		);

		$days = StatsService::get( 30, '', '2025-03-01', '2025-03-07' )['clicks_by_day'];

		utmatra_assert_same( 7, count( $days ), 'one entry per scaffold day' );
		utmatra_assert_same( '2025-03-01', $days[0]['date'] );
		utmatra_assert_same( '2025-03-07', $days[6]['date'] );
		utmatra_assert_same( array( 0, 0, 0, 0, 2, 0, 0 ), array_column( $days, 'count' ), 'the out-of-scaffold row is dropped' );
	},
);
