<?php
/**
 * EventsService — paginated event rows, the export sample and event formatting.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services
 */

namespace Utmatra\Services;

use stdClass;
use Utmatra\DB\Schema;
use Utmatra\Services\Support\DateRange;
use Utmatra\Tracking\Tracker;
use Utmatra\Tracking\TrafficSource;

defined( 'ABSPATH' ) || exit;

/**
 * EventsService
 *
 * Holds the logic behind GET utmatra/v1/events and GET utmatra/v1/export
 * and the matching abilities. Arguments are plain arrays keyed like the
 * REST parameters and are sanitised here, so REST and abilities share one
 * code path. Rows never include `ip_hash` or `user_agent`.
 *
 * @since 1.1.0
 */
class EventsService {

	/**
	 * The event columns the read routes select and return.
	 *
	 * The privacy-light set: `ip_hash` and `user_agent` are never included.
	 * Order matches the events table and is shared by events and journeys.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	public const EVENT_COLUMNS = 'id, link_id, visitor_id, clicked_at, utm_source, utm_medium, utm_campaign, utm_id, utm_term, utm_content, gclid, fbclid, msclkid, ttclid, li_fat_id, twclid, destination, referrer, is_conversion, touch, order_total, device, channel, custom, meta';

	/**
	 * Row cap of the REST export.
	 *
	 * @since 1.1.0
	 *
	 * @var int
	 */
	public const REST_EXPORT_LIMIT = 10000;

	/**
	 * Paginated, filterable event rows, newest first.
	 *
	 * The date range matches the stats report, so the two partition the same
	 * rows; `channel` filters the stored column only (legacy rows hold '' and
	 * never match); `kind` filters on the derived kind (see event_kind());
	 * `visitor_id` accepts any UUID version, so a non-v4 value simply matches
	 * no rows. `per_page` is clamped to 1..200 and `page` forced to >= 1, so
	 * an out-of-range request degrades instead of erroring.
	 *
	 * @since 1.1.0 Moved from RestController::get_events().
	 *
	 * @param array<string, mixed> $args Keys: period, campaign, since, until, channel, kind, visitor_id, page, per_page.
	 *
	 * @return array{events: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
	 */
	public static function get_events( array $args ): array {
		global $wpdb;
		$table   = esc_sql( Schema::table() );
		$columns = self::EVENT_COLUMNS;
		$where   = self::date_where( $args );

		$channel = sanitize_key( (string) ( $args['channel'] ?? '' ) );
		if ( '' !== $channel ) {
			$where .= $wpdb->prepare( ' AND channel = %s', $channel );
		}

		$visitor_id = sanitize_text_field( (string) ( $args['visitor_id'] ?? '' ) );
		if ( '' !== $visitor_id ) {
			$where .= $wpdb->prepare( ' AND visitor_id = %s', $visitor_id );
		}

		switch ( sanitize_key( (string) ( $args['kind'] ?? '' ) ) ) {
			case 'lead':
				$where .= " AND link_id = 'lead'";
				break;
			case 'order':
				$where .= " AND is_conversion = 1 AND link_id != 'lead'";
				break;
			case 'visit':
				$where .= " AND link_id = 'visit'";
				break;
			case 'click':
				$where .= " AND is_conversion = 0 AND link_id != 'visit'";
				break;
		}

		$per_page = min( 200, max( 1, absint( $args['per_page'] ?? 50 ) ) );
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- real-time analytics table; $columns is a class constant, $table/$where are built above through esc_sql()/$wpdb->prepare()
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT {$columns} FROM {$table} WHERE {$where} ORDER BY clicked_at DESC, id DESC LIMIT %d OFFSET %d", $per_page, $offset ),
			ARRAY_A
		);
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
		// phpcs:enable

		$events = array();
		foreach ( (array) $rows as $row ) {
			$events[] = self::format_event( (array) $row );
		}

		return array(
			'events'      => $events,
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Raw event rows for the CSV export, newest first, capped at `$limit`.
	 *
	 * Conversion rows are included and flagged via the `is_conversion`
	 * column. Untagged landing visits are left out unless `include_visits`
	 * is set; every row carries its `channel`, its `visitor_id` (directly
	 * after `order_total`) and one key per configured custom parameter
	 * (empty string when the row has no value; the raw `custom` JSON column
	 * is never returned).
	 *
	 * @since 1.1.0 Moved from RestController::export_events(); the row cap is an argument.
	 *
	 * @param array<string, mixed> $args  Keys: period, campaign, since, until, include_visits.
	 * @param int                  $limit Maximum number of rows (the REST export uses REST_EXPORT_LIMIT).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function export( array $args, int $limit ): array {
		return self::export_rows( self::export_where( $args ), $limit );
	}

	/**
	 * A capped export sample with the totals an agent needs to judge it.
	 *
	 * Counts the matching rows only when the sample is full, since a shorter
	 * sample already holds every match.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $args  Same keys as export().
	 * @param int                  $limit Maximum number of rows.
	 *
	 * @return array{rows: array<int, array<string, mixed>>, returned: int, total_rows: int, truncated: bool}
	 */
	public static function export_sample( array $args, int $limit ): array {
		global $wpdb;
		$where    = self::export_where( $args );
		$rows     = self::export_rows( $where, $limit );
		$returned = count( $rows );
		$total    = $returned;

		if ( $returned >= $limit ) {
			$table = esc_sql( Schema::table() );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- real-time analytics table; $table/$where are built through esc_sql()/$wpdb->prepare()
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
		}

		return array(
			'rows'       => $rows,
			'returned'   => $returned,
			'total_rows' => $total,
			'truncated'  => $total > $returned,
		);
	}

	/**
	 * Derive an event's kind from its row.
	 *
	 * Never stored: `lead` rows are form submissions, `order` covers the
	 * WooCommerce rows plus legacy seeded conversions (which were only ever
	 * orders), `visit` is an untagged landing, and everything else —
	 * `incoming` tagged visits and short-link slugs — is a click, matching
	 * how every existing click metric counts them.
	 *
	 * @since 1.1.0 Moved from RestController::event_kind().
	 *
	 * @param array<string, mixed> $row Event row (ARRAY_A).
	 *
	 * @return string One of 'click', 'visit', 'order', 'lead'.
	 */
	public static function event_kind( array $row ): string {
		$link_id = (string) ( $row['link_id'] ?? '' );

		if ( 'lead' === $link_id ) {
			return 'lead';
		}
		if ( 'order' === $link_id || ! empty( $row['is_conversion'] ) ) {
			return 'order';
		}
		if ( 'visit' === $link_id ) {
			return 'visit';
		}
		return 'click';
	}

	/**
	 * Serialise one event row for the read routes and abilities.
	 *
	 * Casts id/is_conversion/order_total, leaves clicked_at exactly as stored
	 * (UTC `Y-m-d H:i:s`) and emits `channel` as stored ('' on legacy rows).
	 * The two JSON columns are always objects: decoded without `assoc` and
	 * replaced with an empty stdClass when NULL, empty or undecodable, so the
	 * response encodes `{}` and never `[]`. `ip_hash` and `user_agent` are
	 * never selected (see EVENT_COLUMNS).
	 *
	 * @since 1.1.0 Moved from RestController::format_event().
	 *
	 * @param array<string, mixed> $row Event row (ARRAY_A).
	 *
	 * @return array<string, mixed>
	 */
	public static function format_event( array $row ): array {
		$custom = json_decode( (string) ( $row['custom'] ?? '' ) );
		$meta   = json_decode( (string) ( $row['meta'] ?? '' ) );

		return array(
			'id'            => (int) $row['id'],
			'link_id'       => (string) ( $row['link_id'] ?? '' ),
			'visitor_id'    => (string) ( $row['visitor_id'] ?? '' ),
			'clicked_at'    => (string) ( $row['clicked_at'] ?? '' ),
			'utm_source'    => (string) ( $row['utm_source'] ?? '' ),
			'utm_medium'    => (string) ( $row['utm_medium'] ?? '' ),
			'utm_campaign'  => (string) ( $row['utm_campaign'] ?? '' ),
			'utm_id'        => (string) ( $row['utm_id'] ?? '' ),
			'utm_term'      => (string) ( $row['utm_term'] ?? '' ),
			'utm_content'   => (string) ( $row['utm_content'] ?? '' ),
			'gclid'         => (string) ( $row['gclid'] ?? '' ),
			'fbclid'        => (string) ( $row['fbclid'] ?? '' ),
			'msclkid'       => (string) ( $row['msclkid'] ?? '' ),
			'ttclid'        => (string) ( $row['ttclid'] ?? '' ),
			'li_fat_id'     => (string) ( $row['li_fat_id'] ?? '' ),
			'twclid'        => (string) ( $row['twclid'] ?? '' ),
			'destination'   => (string) ( $row['destination'] ?? '' ),
			'referrer'      => (string) ( $row['referrer'] ?? '' ),
			'is_conversion' => (bool) (int) ( $row['is_conversion'] ?? 0 ),
			'touch'         => (string) ( $row['touch'] ?? '' ),
			'order_total'   => ( isset( $row['order_total'] ) && null !== $row['order_total'] && '' !== $row['order_total'] ) ? (float) $row['order_total'] : null,
			'device'        => (string) ( $row['device'] ?? '' ),
			'channel'       => (string) ( $row['channel'] ?? '' ),
			'custom'        => ( $custom instanceof stdClass ) ? $custom : new stdClass(),
			'meta'          => ( $meta instanceof stdClass ) ? $meta : new stdClass(),
			'kind'          => self::event_kind( $row ),
		);
	}

	/**
	 * Run the export query for a WHERE clause and spread the custom parameters.
	 *
	 * @since 1.1.0
	 *
	 * @param string $where Prepared SQL WHERE clause (without the WHERE keyword).
	 * @param int    $limit Maximum number of rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function export_rows( string $where, int $limit ): array {
		global $wpdb;
		$table        = esc_sql( Schema::table() );
		$channel_case = TrafficSource::sql_channel_expression();
		$limit        = max( 1, $limit );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			"SELECT clicked_at, link_id, {$channel_case} AS channel, utm_source, utm_medium, utm_campaign, utm_id, utm_term, utm_content,
			        gclid, fbclid, msclkid, ttclid, li_fat_id, twclid, destination, referrer, is_conversion, order_total, visitor_id, custom
			 FROM {$table}
			 WHERE {$where}
			 ORDER BY clicked_at DESC
			 LIMIT {$limit}",
			ARRAY_A
		);
		// phpcs:enable

		// Spread the `custom` JSON object into one plain key per configured
		// custom parameter name (empty string when the row carries no value),
		// so the client-side CSV gains a column per custom parameter. Plain
		// names are safe as keys: every events-table column name is reserved.
		$custom_params = Tracker::custom_params();
		foreach ( (array) $rows as $index => $row ) {
			$decoded = json_decode( (string) ( $row['custom'] ?? '' ), true );
			unset( $rows[ $index ]['custom'] );
			foreach ( $custom_params as $name ) {
				$rows[ $index ][ $name ] = isset( $decoded[ $name ] ) ? (string) $decoded[ $name ] : '';
			}
		}

		return array_values( (array) $rows );
	}

	/**
	 * The export WHERE clause: the date range, minus untagged landing visits
	 * unless `include_visits` is set.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $args Keys: period, campaign, since, until, include_visits.
	 *
	 * @return string Prepared SQL WHERE clause (without the WHERE keyword).
	 */
	private static function export_where( array $args ): string {
		$where = self::date_where( $args );
		if ( empty( $args['include_visits'] ) ) {
			$where .= " AND link_id != 'visit'";
		}
		return $where;
	}

	/**
	 * The date range and campaign WHERE clause shared with the stats report.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $args Keys: period (default 30), campaign, since, until.
	 *
	 * @return string Prepared SQL WHERE clause (without the WHERE keyword).
	 */
	private static function date_where( array $args ): string {
		$since     = sanitize_text_field( (string) ( $args['since'] ?? '' ) );
		$until     = sanitize_text_field( (string) ( $args['until'] ?? '' ) );
		$now_local = DateRange::resolve_now_local( $until, wp_timezone() );
		$period    = DateRange::resolve_period( $since, $until, absint( $args['period'] ?? 30 ), $now_local );

		return DateRange::build_where( $period, sanitize_text_field( (string) ( $args['campaign'] ?? '' ) ), $now_local );
	}
}
