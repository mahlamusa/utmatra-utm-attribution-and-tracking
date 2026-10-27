<?php
/**
 * Unit tests for DateRange.
 *
 * The fixed reference instant is 2025-03-10 23:59:59 in Africa/Johannesburg
 * (UTC+2, no DST), i.e. 2025-03-10 21:59:59 UTC.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Services\Support\DateRange;

/**
 * The fixed reference instant used by the WHERE clause tests.
 *
 * @since 1.1.0
 *
 * @return DateTimeImmutable
 */
function utmatra_test_now(): DateTimeImmutable {
	return new DateTimeImmutable( '2025-03-10 23:59:59', new DateTimeZone( 'Africa/Johannesburg' ) );
}

return array(
	'now is real now without until'                   => static function () {
		$timezone = new DateTimeZone( 'Africa/Johannesburg' );
		$before   = time();
		$now      = DateRange::resolve_now_local( '', $timezone );
		utmatra_assert_true( $now->getTimestamp() >= $before && $now->getTimestamp() <= time() );
		utmatra_assert_same( 'Africa/Johannesburg', $now->getTimezone()->getName() );
	},

	'now ignores a malformed until'                   => static function () {
		$timezone = new DateTimeZone( 'UTC' );
		foreach ( array( '2025-3-10', '10-03-2025', 'yesterday', '2025-03-10 10:00:00' ) as $until ) {
			$now = DateRange::resolve_now_local( $until, $timezone );
			utmatra_assert_true( abs( $now->getTimestamp() - time() ) <= 1, $until );
		}
	},

	'now ignores a future until'                      => static function () {
		$now = DateRange::resolve_now_local( '2999-01-01', new DateTimeZone( 'UTC' ) );
		utmatra_assert_true( abs( $now->getTimestamp() - time() ) <= 1 );
	},

	'now is the end of a past until day'              => static function () {
		$now = DateRange::resolve_now_local( '2025-03-10', new DateTimeZone( 'Africa/Johannesburg' ) );
		utmatra_assert_same( '2025-03-10 23:59:59 +02:00', $now->format( 'Y-m-d H:i:s P' ) );
	},

	'period counts the days between since and until'  => static function () {
		utmatra_assert_same( 9, DateRange::resolve_period( '2025-03-01', '2025-03-10', 30, utmatra_test_now() ) );
	},

	'period is 0 for a single-day range'              => static function () {
		utmatra_assert_same( 0, DateRange::resolve_period( '2025-03-10', '2025-03-10', 30, utmatra_test_now() ) );
	},

	'period range is capped at 365'                   => static function () {
		utmatra_assert_same( 365, DateRange::resolve_period( '2023-01-01', '2025-03-10', 30, utmatra_test_now() ) );
	},

	'period falls back when since is after until'     => static function () {
		utmatra_assert_same( 30, DateRange::resolve_period( '2025-03-11', '2025-03-10', 30, utmatra_test_now() ) );
	},

	'period falls back without a full range'          => static function () {
		utmatra_assert_same( 7, DateRange::resolve_period( '2025-03-01', '', 7, utmatra_test_now() ) );
		utmatra_assert_same( 7, DateRange::resolve_period( '', '2025-03-10', 7, utmatra_test_now() ) );
		utmatra_assert_same( 7, DateRange::resolve_period( '2025-3-1', '2025-03-10', 7, utmatra_test_now() ) );
	},

	'period is clamped to 1..365'                     => static function () {
		utmatra_assert_same( 1, DateRange::resolve_period( '', '', 0, utmatra_test_now() ) );
		utmatra_assert_same( 1, DateRange::resolve_period( '', '', -5, utmatra_test_now() ) );
		utmatra_assert_same( 365, DateRange::resolve_period( '', '', 500, utmatra_test_now() ) );
	},

	'where spans local midnight period days ago to now' => static function () {
		utmatra_assert_same(
			"clicked_at >= '2025-03-02 22:00:00' AND clicked_at <= '2025-03-10 21:59:59'",
			DateRange::build_where( 7, '', utmatra_test_now() )
		);
	},

	'where appends the campaign filter'               => static function () {
		utmatra_assert_same(
			"clicked_at >= '2025-03-08 22:00:00' AND clicked_at <= '2025-03-10 21:59:59' AND utm_campaign = 'spring-sale'",
			DateRange::build_where( 1, 'spring-sale', utmatra_test_now() )
		);
	},

	'where from args resolves a custom range'         => static function () {
		utmatra_assert_same(
			"clicked_at >= '2025-03-01 00:00:00' AND clicked_at <= '2025-03-10 23:59:59' AND utm_campaign = 'spring-sale'",
			DateRange::where_from_args(
				array(
					'since'    => '2025-03-01',
					'until'    => '2025-03-10',
					'campaign' => 'spring-sale',
				)
			)
		);
	},

	'where from args matches build where'             => static function () {
		$now = DateRange::resolve_now_local( '2025-03-10', wp_timezone() );
		utmatra_assert_same( DateRange::build_where( 30, '', $now ), DateRange::where_from_args( array( 'until' => '2025-03-10' ) ) );
		utmatra_assert_same(
			DateRange::build_where( 7, 'x', $now ),
			DateRange::where_from_args(
				array(
					'period'   => '7',
					'campaign' => 'x',
					'until'    => '2025-03-10',
				)
			)
		);
	},

	'previous day where is the day-aligned window'    => static function () {
		utmatra_assert_same(
			"clicked_at >= '2025-02-22 22:00:00' AND clicked_at < '2025-03-02 22:00:00'",
			DateRange::build_previous_day_where( 7, '', utmatra_test_now() )
		);
		utmatra_assert_same(
			"clicked_at >= '2025-02-22 22:00:00' AND clicked_at < '2025-03-02 22:00:00' AND utm_campaign = 'x'",
			DateRange::build_previous_day_where( 7, 'x', utmatra_test_now() )
		);
	},

	'previous where spans the same elapsed duration'  => static function () {
		utmatra_assert_same(
			"clicked_at >= '2025-02-22 22:00:00' AND clicked_at < '2025-03-02 21:59:59'",
			DateRange::build_previous_where( 7, '', utmatra_test_now() )
		);

		$midday = new DateTimeImmutable( '2025-03-10 12:00:00', new DateTimeZone( 'UTC' ) );
		utmatra_assert_same(
			"clicked_at >= '2025-03-07 00:00:00' AND clicked_at < '2025-03-08 12:00:00' AND utm_campaign = 'x'",
			DateRange::build_previous_where( 1, 'x', $midday )
		);
	},
);
