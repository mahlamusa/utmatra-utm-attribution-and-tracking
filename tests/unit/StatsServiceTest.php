<?php

use Utmatra\Services\StatsService;

return array(
	'clicks_by_day keeps rows outside the scaffold out of the series' => static function () {
		$GLOBALS['utmatra_test']['db_rows'] = array(
			array(
				'day'   => '2025-02-28',
				'count' => '3',
			),
			array(
				'day'   => '2025-03-05',
				'count' => '2',
			),
		);

		$days = StatsService::get( 30, '', '2025-03-01', '2025-03-07' )['clicks_by_day'];

		utmatra_assert_same( 7, count( $days ), 'one entry per scaffold day' );
		utmatra_assert_same( '2025-03-01', $days[0]['date'] );
		utmatra_assert_same( '2025-03-07', $days[6]['date'] );
		utmatra_assert_same( 2, $days[4]['count'] );
		utmatra_assert_same( array( 2 ), array_values( array_filter( array_column( $days, 'count' ) ) ), 'the out-of-scaffold row is dropped' );
	},
);
