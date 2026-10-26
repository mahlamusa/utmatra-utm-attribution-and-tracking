<?php
/**
 * DateRange — the reporting window shared by stats, events, journeys and export.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services\Support
 */

namespace Utmatra\Services\Support;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * DateRange
 *
 * Resolves the reference instant and period length from the raw `since`,
 * `until` and `period` inputs and builds the prepared `clicked_at` WHERE
 * clauses. Moved from RestController unchanged, except that the inputs are
 * passed as values instead of being read from a request.
 *
 * @since 1.1.0
 */
class DateRange {

	/**
	 * Resolve the reference "now" instant a stats/export request anchors to.
	 *
	 * Normally the actual current moment. When a valid `until` date (Y-m-d,
	 * not in the future) is supplied for a custom range, the reference point
	 * becomes 23:59:59 site-local on that date instead — every date-relative
	 * calculation downstream (`build_where()`'s upper bound, the day
	 * scaffolds, the previous-period comparisons) is written in terms of this
	 * single anchor, so a custom range reuses the exact same logic a preset
	 * period does. An invalid or future `until` is ignored and real "now" is
	 * used, so malformed input degrades to normal preset behaviour rather
	 * than erroring.
	 *
	 * @since 1.1.0
	 *
	 * @param string       $until_raw Raw `until` input; empty when absent.
	 * @param DateTimeZone $timezone  Site timezone.
	 *
	 * @return DateTimeImmutable
	 */
	public static function resolve_now_local( string $until_raw, DateTimeZone $timezone ): DateTimeImmutable {
		$now_local = new DateTimeImmutable( 'now', $timezone );

		if ( '' === $until_raw || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until_raw ) ) {
			return $now_local;
		}

		$until_local = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $until_raw . ' 23:59:59', $timezone );
		if ( false === $until_local || $until_local > $now_local ) {
			return $now_local;
		}

		return $until_local;
	}

	/**
	 * Resolve the period length in days for a stats/export request.
	 *
	 * When valid `since` and `until` dates (Y-m-d, since <= until) are both
	 * supplied, the period is the number of days `since` lies before `until`
	 * (0 for a single day), capped at 365; otherwise falls back to the
	 * `period` integer parameter, clamped to [1, 365]. `$now_local` must
	 * already reflect {@see resolve_now_local()} (its date is the effective
	 * `until` when a custom range is in play).
	 *
	 * @since 1.1.0
	 *
	 * @param string            $since_raw Raw `since` input; empty when absent.
	 * @param string            $until_raw Raw `until` input; empty when absent.
	 * @param int               $period    The `period` input, as an integer.
	 * @param DateTimeImmutable $now_local Resolved reference instant.
	 *
	 * @return int
	 */
	public static function resolve_period( string $since_raw, string $until_raw, int $period, DateTimeImmutable $now_local ): int {
		if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $since_raw ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until_raw ) ) {
			$since_date = DateTimeImmutable::createFromFormat( 'Y-m-d', $since_raw, $now_local->getTimezone() );
			if ( false !== $since_date && $since_date->format( 'Y-m-d' ) <= $now_local->format( 'Y-m-d' ) ) {
				$days = (int) $since_date->setTime( 0, 0, 0 )->diff( $now_local->setTime( 0, 0, 0 ) )->days;
				if ( $days >= 0 ) {
					// 0 is a single-day range (since equals until); flooring it at 1
					// would pull in the day before.
					return min( 365, $days );
				}
			}
		}

		return max( 1, min( 365, $period ) );
	}

	/**
	 * Build the prepared WHERE clause shared by the stats and export queries.
	 *
	 * The period boundary is midnight (site-local) `$period` days before
	 * `$now_local`, converted to UTC to match the stored `clicked_at` values.
	 * An upper bound of `$now_local` itself is also applied — for a live
	 * (non-custom-range) request this is a no-op, since no row's `clicked_at`
	 * can be later than the actual current moment, but it is what correctly
	 * bounds a custom past date range instead of leaking events after it. An
	 * optional campaign filter is appended.
	 *
	 * @since 1.1.0
	 *
	 * @param int               $period    Period length in days.
	 * @param string            $campaign  Optional utm_campaign filter; empty for all.
	 * @param DateTimeImmutable $now_local Reference instant (real "now", or a resolved custom `until`).
	 *
	 * @return string Prepared SQL WHERE clause (without the WHERE keyword).
	 */
	public static function build_where( int $period, string $campaign, DateTimeImmutable $now_local ): string {
		global $wpdb;

		$utc         = new DateTimeZone( 'UTC' );
		$since_local = $now_local->modify( "-{$period} days" )->setTime( 0, 0, 0 );
		$since       = $since_local->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		$until       = $now_local->setTimezone( $utc )->format( 'Y-m-d H:i:s' );

		$where = $wpdb->prepare( 'clicked_at >= %s AND clicked_at <= %s', $since, $until );
		if ( ! empty( $campaign ) ) {
			$where .= $wpdb->prepare( ' AND utm_campaign = %s', $campaign );
		}

		return $where;
	}

	/**
	 * Build the prepared WHERE clause for the calendar-day window that lines
	 * up with the chart's previous-period overlay.
	 *
	 * Covers the `$period + 1` site-local calendar days immediately before
	 * the current window's days — a day-aligned window, distinct from
	 * {@see build_previous_where()}'s equal-elapsed-duration window used by
	 * the trend indicator (that window can end mid-day; this one always ends
	 * at a local midnight so it lines up with $by_day's date buckets one-for-one).
	 *
	 * @since 1.1.0
	 *
	 * @param int               $period    Period length in days.
	 * @param string            $campaign  Optional utm_campaign filter; empty for all.
	 * @param DateTimeImmutable $now_local Reference instant (real "now", or a resolved custom `until`).
	 *
	 * @return string Prepared SQL WHERE clause (without the WHERE keyword).
	 */
	public static function build_previous_day_where( int $period, string $campaign, DateTimeImmutable $now_local ): string {
		global $wpdb;

		$utc = new DateTimeZone( 'UTC' );

		$since_local = $now_local->modify( '-' . ( 2 * $period + 1 ) . ' days' )->setTime( 0, 0, 0 );
		$until_local = $now_local->modify( "-{$period} days" )->setTime( 0, 0, 0 );

		$where = $wpdb->prepare(
			'clicked_at >= %s AND clicked_at < %s',
			$since_local->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			$until_local->setTimezone( $utc )->format( 'Y-m-d H:i:s' )
		);
		if ( ! empty( $campaign ) ) {
			$where .= $wpdb->prepare( ' AND utm_campaign = %s', $campaign );
		}

		return $where;
	}

	/**
	 * Build the prepared WHERE clause for the period immediately before the
	 * requested one.
	 *
	 * Starts at site-local midnight `2 × $period + 1` days ago (the current
	 * window covers `$period + 1` calendar days, so this ends just before it
	 * starts) and spans exactly the same duration as the current window has
	 * elapsed so far (period days plus the partial current day). Comparing
	 * equal durations keeps the trend percentage fair early in the day, when
	 * the current window has only a few hours of today in it. Same optional
	 * campaign filter as
	 * {@see build_where()}.
	 *
	 * @since 1.1.0
	 *
	 * @param int               $period    Period length in days.
	 * @param string            $campaign  Optional utm_campaign filter; empty for all.
	 * @param DateTimeImmutable $now_local Reference instant (real "now", or a resolved custom `until`).
	 *
	 * @return string Prepared SQL WHERE clause (without the WHERE keyword).
	 */
	public static function build_previous_where( int $period, string $campaign, DateTimeImmutable $now_local ): string {
		global $wpdb;

		$utc    = new DateTimeZone( 'UTC' );
		$double = 2 * $period + 1;

		$since_current = $now_local->modify( "-{$period} days" )->setTime( 0, 0, 0 );
		$since_prev    = $now_local->modify( "-{$double} days" )->setTime( 0, 0, 0 );
		$elapsed       = $now_local->getTimestamp() - $since_current->getTimestamp();
		$prev_end      = $since_prev->modify( '+' . $elapsed . ' seconds' );

		$where = $wpdb->prepare(
			'clicked_at >= %s AND clicked_at < %s',
			$since_prev->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
			$prev_end->setTimezone( $utc )->format( 'Y-m-d H:i:s' )
		);
		if ( ! empty( $campaign ) ) {
			$where .= $wpdb->prepare( ' AND utm_campaign = %s', $campaign );
		}

		return $where;
	}
}
