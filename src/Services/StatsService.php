<?php
/**
 * StatsService — the aggregated attribution stats behind GET utmatra/v1/stats.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services
 */

namespace Utmatra\Services;

use DateTimeImmutable;
use Utmatra\DB\Schema;
use Utmatra\Services\Support\DateRange;

defined( 'ABSPATH' ) || exit;

/**
 * StatsService
 *
 * Builds the stats payload: the daily time series, the ranked breakdowns
 * (see StatsBreakdowns) and the totals. Moved from RestController::get_stats()
 * with the SQL unchanged; the inputs are passed as values instead of being
 * read from a request.
 *
 * Rows are stored in UTC; day grouping and the period boundary use the
 * site timezone by shifting `clicked_at` with the site's current UTC
 * offset. Known limitation: rows recorded under a different DST offset
 * than today's can shift by an hour — acceptable for daily aggregation.
 *
 * Click-oriented queries exclude conversion rows (`is_conversion = 1`,
 * written by the WooCommerce integration and the lead recorder) so a
 * converting visit is not double-counted as a click, and untagged
 * landing visits (`link_id = 'visit'`), which only the `by_channel`
 * breakdown reports.
 *
 * @since 1.1.0
 */
class StatsService {

	/**
	 * Appended to a period WHERE clause to keep click rows only: conversions
	 * and untagged landing visits are excluded.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	private const CLICK_FILTER = " AND is_conversion = 0 AND link_id != 'visit'";

	/**
	 * Aggregated stats for a period.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $period   Period length in days (clamped to 1-365); ignored when `since` and `until` form a valid range.
	 * @param string $campaign Optional utm_campaign filter; empty for all.
	 * @param string $since    Custom range start (Y-m-d) or empty.
	 * @param string $until    Custom range end (Y-m-d) or empty.
	 *
	 * @return array<string, mixed> The same keys and values GET utmatra/v1/stats returns.
	 */
	public static function get( int $period, string $campaign, string $since, string $until ): array {
		global $wpdb;
		$table = esc_sql( Schema::table() );

		$timezone  = wp_timezone();
		$now_local = DateRange::resolve_now_local( $until, $timezone );
		$period    = DateRange::resolve_period( $since, $until, $period, $now_local );
		$offset    = (int) $timezone->getOffset( $now_local );

		$where = DateRange::build_where( $period, $campaign, $now_local );
		// Untagged landing visits are not clicks: every click metric
		// excludes them; only the Channels breakdown includes them.
		$click_where = $where . self::CLICK_FILTER;

		$clicks_by_day          = self::clicks_by_day( $table, $where, $click_where, $offset, $period, $now_local );
		$previous_clicks_by_day = self::previous_clicks_by_day( $table, $campaign, $offset, $period, $now_local );
		$breakdowns             = StatsBreakdowns::get( $table, $where, $click_where );

		// Total.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$click_where}" );

		// All-time event count — drives the dashboard's first-run onboarding.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_time_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		// Conversions in the period (WooCommerce order rows plus form lead
		// rows) for the totals row.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$conversions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where} AND is_conversion = 1" );

		// Previous-period total — the same-length window immediately before
		// this one, for the trend indicator next to the total.
		$prev_where = DateRange::build_previous_where( $period, $campaign, $now_local ) . self::CLICK_FILTER;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$previous_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$prev_where}" );

		// Distinct campaigns for filter dropdown.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching
		$campaigns = $wpdb->get_col(
			"SELECT DISTINCT utm_campaign FROM {$table} WHERE is_conversion = 0 AND utm_campaign != '' ORDER BY utm_campaign"
		);
		// phpcs:enable

		return array_merge(
			array( 'clicks_by_day' => $clicks_by_day ),
			$breakdowns,
			array(
				'total'                  => $total,
				'previous_total'         => $previous_total,
				'previous_clicks_by_day' => $previous_clicks_by_day,
				'conversions'            => $conversions,
				'all_time_total'         => $all_time_total,
				'campaigns'              => array_values( (array) $campaigns ),
			)
		);
	}

	/**
	 * Clicks and conversions per site-local day, oldest first.
	 *
	 * Every day of the period appears, with zero counts for days without
	 * events.
	 *
	 * @since 1.1.0
	 *
	 * @param string            $table       Escaped events table name.
	 * @param string            $where       Prepared period WHERE clause.
	 * @param string            $click_where Prepared period WHERE clause limited to clicks.
	 * @param int               $offset      Site UTC offset in seconds.
	 * @param int               $period      Resolved period length in days.
	 * @param DateTimeImmutable $now_local   Reference instant.
	 *
	 * @return array<int, array{date: string, count: int, conversions: int}>
	 */
	private static function clicks_by_day( string $table, string $where, string $click_where, int $offset, int $period, DateTimeImmutable $now_local ): array {
		global $wpdb;

		// Clicks by day, grouped on the site-local date.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- real-time analytics table, no cache layer by design; WHERE clauses are built by DateRange through $wpdb->prepare()
		$by_day_raw = $wpdb->get_results(
			"SELECT DATE( DATE_ADD( clicked_at, INTERVAL {$offset} SECOND ) ) AS day, COUNT(*) AS count
			 FROM {$table}
			 WHERE {$click_where}
			 GROUP BY DATE( DATE_ADD( clicked_at, INTERVAL {$offset} SECOND ) )
			 ORDER BY day ASC",
			ARRAY_A
		);
		// phpcs:enable

		// Conversions by day (WooCommerce order rows plus form lead rows), same local-date grouping.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$conv_day_raw = $wpdb->get_results(
			"SELECT DATE( DATE_ADD( clicked_at, INTERVAL {$offset} SECOND ) ) AS day, COUNT(*) AS count
			 FROM {$table}
			 WHERE {$where} AND is_conversion = 1
			 GROUP BY DATE( DATE_ADD( clicked_at, INTERVAL {$offset} SECOND ) )
			 ORDER BY day ASC",
			ARRAY_A
		);
		// phpcs:enable

		// Build a full date scaffold so days with zero clicks still appear.
		$by_day   = array();
		$conv_day = array();
		for ( $i = $period; $i >= 0; $i-- ) {
			$day              = $now_local->modify( "-{$i} days" )->format( 'Y-m-d' );
			$by_day[ $day ]   = 0;
			$conv_day[ $day ] = 0;
		}
		// Rows the current-offset grouping puts outside the scaffold (a
		// different DST offset at the period edge) are dropped, so the series
		// always has one entry per scaffold day in date order.
		foreach ( (array) $by_day_raw as $row ) {
			if ( isset( $row['day'], $by_day[ $row['day'] ] ) ) {
				$by_day[ $row['day'] ] = (int) $row['count'];
			}
		}
		foreach ( (array) $conv_day_raw as $row ) {
			if ( isset( $row['day'], $conv_day[ $row['day'] ] ) ) {
				$conv_day[ $row['day'] ] = (int) $row['count'];
			}
		}

		$clicks_by_day = array();
		foreach ( $by_day as $date => $count ) {
			$clicks_by_day[] = array(
				'date'        => $date,
				'count'       => $count,
				'conversions' => $conv_day[ $date ] ?? 0,
			);
		}

		return $clicks_by_day;
	}

	/**
	 * Previous-period clicks per day, for the chart overlay.
	 *
	 * A full calendar-day window exactly `$period` days earlier than the
	 * current one (distinct from build_previous_where()'s elapsed-duration
	 * window used by the trend indicator, which needs an equal-duration
	 * comparison rather than day-aligned buckets). Positions align with
	 * the clicks-by-day series (both oldest-to-newest, `$period + 1`
	 * entries) rather than actual dates, since the two windows cover
	 * different calendar days.
	 *
	 * @since 1.1.0
	 *
	 * @param string            $table     Escaped events table name.
	 * @param string            $campaign  Optional utm_campaign filter; empty for all.
	 * @param int               $offset    Site UTC offset in seconds.
	 * @param int               $period    Resolved period length in days.
	 * @param DateTimeImmutable $now_local Reference instant.
	 *
	 * @return int[]
	 */
	private static function previous_clicks_by_day( string $table, string $campaign, int $offset, int $period, DateTimeImmutable $now_local ): array {
		global $wpdb;

		$prev_day_where = DateRange::build_previous_day_where( $period, $campaign, $now_local ) . self::CLICK_FILTER;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$prev_day_raw = $wpdb->get_results(
			"SELECT DATE( DATE_ADD( clicked_at, INTERVAL {$offset} SECOND ) ) AS day, COUNT(*) AS count
			 FROM {$table}
			 WHERE {$prev_day_where}
			 GROUP BY DATE( DATE_ADD( clicked_at, INTERVAL {$offset} SECOND ) )
			 ORDER BY day ASC",
			ARRAY_A
		);
		// phpcs:enable

		$prev_day = array();
		for ( $i = $period; $i >= 0; $i-- ) {
			$prev_day[ $now_local->modify( '-' . ( $i + $period + 1 ) . ' days' )->format( 'Y-m-d' ) ] = 0;
		}
		foreach ( (array) $prev_day_raw as $row ) {
			if ( isset( $row['day'], $prev_day[ $row['day'] ] ) ) {
				$prev_day[ $row['day'] ] = (int) $row['count'];
			}
		}

		return array_values( $prev_day );
	}
}
