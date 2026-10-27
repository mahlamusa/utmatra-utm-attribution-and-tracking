<?php
/**
 * StatsBreakdowns — the ranked GROUP BY breakdowns of the stats payload.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services
 */

namespace Utmatra\Services;

use Utmatra\Tracking\TrafficSource;

defined( 'ABSPATH' ) || exit;

/**
 * StatsBreakdowns
 *
 * One query per breakdown (source, campaign, medium, device, channel,
 * landing page, platform, referrer, revenue by campaign), moved from
 * RestController::get_stats() with the SQL unchanged. Used by StatsService.
 *
 * @since 1.1.0
 */
class StatsBreakdowns {

	/**
	 * Run every breakdown for a period.
	 *
	 * @since 1.1.0
	 *
	 * @param string $table       Escaped events table name.
	 * @param string $where       Prepared period WHERE clause.
	 * @param string $click_where Prepared period WHERE clause limited to clicks.
	 *
	 * @return array<string, array> Keyed `by_source`, `by_campaign`, `by_medium`, `by_device`,
	 *                              `by_channel`, `by_landing`, `by_platform`, `by_referrer`
	 *                              and `revenue_by_campaign`, in that order.
	 */
	public static function get( string $table, string $where, string $click_where ): array {
		global $wpdb;

		// By source.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- real-time analytics table, no cache layer by design; WHERE clauses are built by DateRange through $wpdb->prepare()
		$by_source = $wpdb->get_results(
			"SELECT utm_source AS label, COUNT(*) AS count
			 FROM {$table}
			 WHERE {$click_where} AND utm_source != ''
			 GROUP BY utm_source
			 ORDER BY count DESC
			 LIMIT 10",
			ARRAY_A
		);
		// phpcs:enable

		// By campaign.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$by_campaign = $wpdb->get_results(
			"SELECT utm_campaign AS label, COUNT(*) AS count
			 FROM {$table}
			 WHERE {$click_where} AND utm_campaign != ''
			 GROUP BY utm_campaign
			 ORDER BY count DESC
			 LIMIT 10",
			ARRAY_A
		);
		// phpcs:enable

		// By medium.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$by_medium = $wpdb->get_results(
			"SELECT utm_medium AS label, COUNT(*) AS count
			 FROM {$table}
			 WHERE {$click_where} AND utm_medium != ''
			 GROUP BY utm_medium
			 ORDER BY count DESC
			 LIMIT 10",
			ARRAY_A
		);
		// phpcs:enable

		// By device type — computed once at insert time (Tracker::device_type),
		// so this is a plain GROUP BY like every other breakdown.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$by_device = $wpdb->get_results(
			"SELECT device AS label, COUNT(*) AS count
			 FROM {$table}
			 WHERE {$click_where} AND device != ''
			 GROUP BY device
			 ORDER BY count DESC",
			ARRAY_A
		);
		// phpcs:enable

		// By channel — the stored write-time channel, with rows written before
		// the column existed mapped through the legacy rule set. This is the
		// one breakdown that includes untagged landing visits, so it uses the
		// plain period WHERE rather than $click_where.
		$channel_case = TrafficSource::sql_channel_expression();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- CASE expression built only from class constants passed through esc_sql()
		$by_channel = $wpdb->get_results(
			"SELECT {$channel_case} AS label, COUNT(*) AS count
			 FROM {$table}
			 WHERE {$where} AND is_conversion = 0
			 GROUP BY label
			 ORDER BY count DESC",
			ARRAY_A
		);
		// phpcs:enable

		// Top landing pages — passive incoming visits only (redirect-link rows
		// store the outbound destination, not a landing page). The query string
		// is stripped so UTM variants of one URL group into a single row.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$by_landing = $wpdb->get_results(
			"SELECT SUBSTRING_INDEX( destination, '?', 1 ) AS label, COUNT(*) AS count
			 FROM {$table}
			 WHERE {$click_where} AND link_id = 'incoming' AND destination != ''
			 GROUP BY SUBSTRING_INDEX( destination, '?', 1 )
			 ORDER BY count DESC
			 LIMIT 10",
			ARRAY_A
		);
		// phpcs:enable

		// Top referrers — grouped by the referrer's host so every path on a
		// referring site collapses into one row; self-referrals (internal
		// navigation) are excluded.
		$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$by_referrer = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT SUBSTRING_INDEX( SUBSTRING_INDEX( referrer, '/', 3 ), '//', -1 ) AS label, COUNT(*) AS count
				 FROM {$table}
				 WHERE {$click_where} AND referrer != ''
				   AND SUBSTRING_INDEX( SUBSTRING_INDEX( referrer, '/', 3 ), '//', -1 ) != %s
				 GROUP BY label
				 ORDER BY count DESC
				 LIMIT 10",
				$site_host
			),
			ARRAY_A
		);
		// phpcs:enable

		// Ad-platform breakdown — derived from which ad click ID a click
		// carried. Rows without any click ID (organic/UTM-only traffic) are
		// excluded; the dashboard maps the keys to display names.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$by_platform = $wpdb->get_results(
			"SELECT CASE
				WHEN gclid != '' THEN 'google'
				WHEN fbclid != '' THEN 'facebook'
				WHEN msclkid != '' THEN 'microsoft'
				WHEN ttclid != '' THEN 'tiktok'
				WHEN li_fat_id != '' THEN 'linkedin'
				WHEN twclid != '' THEN 'twitter'
			 END AS label, COUNT(*) AS count
			 FROM {$table}
			 WHERE {$click_where} AND ( gclid != '' OR fbclid != '' OR msclkid != '' OR ttclid != '' OR li_fat_id != '' OR twclid != '' )
			 GROUP BY label
			 ORDER BY count DESC",
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'by_source'           => array_values( (array) $by_source ),
			'by_campaign'         => array_values( (array) $by_campaign ),
			'by_medium'           => array_values( (array) $by_medium ),
			'by_device'           => array_values( (array) $by_device ),
			'by_channel'          => array_values( (array) $by_channel ),
			'by_landing'          => array_values( (array) $by_landing ),
			'by_platform'         => array_values( (array) $by_platform ),
			'by_referrer'         => array_values( (array) $by_referrer ),
			'revenue_by_campaign' => self::revenue_by_campaign( $table, $where ),
		);
	}

	/**
	 * Orders and revenue per campaign, highest revenue first.
	 *
	 * Order conversion rows only (WooCommerce). Lead conversions carry no
	 * order total and must not inflate the order counts; legacy conversion
	 * rows seeded with other link ids keep counting, matching the Journeys
	 * tab's kind derivation.
	 *
	 * @since 1.1.0
	 *
	 * @param string $table Escaped events table name.
	 * @param string $where Prepared period WHERE clause.
	 *
	 * @return array<int, array{label: string, orders: int, revenue: float}>
	 */
	private static function revenue_by_campaign( string $table, string $where ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$revenue_raw = $wpdb->get_results(
			"SELECT utm_campaign AS label, COUNT(*) AS orders, SUM(order_total) AS revenue
			 FROM {$table}
			 WHERE {$where} AND is_conversion = 1 AND link_id != 'lead' AND utm_campaign != ''
			 GROUP BY utm_campaign
			 ORDER BY revenue DESC
			 LIMIT 10",
			ARRAY_A
		);
		// phpcs:enable

		$revenue_by_campaign = array();
		foreach ( (array) $revenue_raw as $row ) {
			$revenue_by_campaign[] = array(
				'label'   => (string) $row['label'],
				'orders'  => (int) $row['orders'],
				'revenue' => round( (float) $row['revenue'], 2 ),
			);
		}

		return $revenue_by_campaign;
	}
}
