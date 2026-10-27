<?php
/**
 * JourneysService — conversion journeys and a single visitor's event list.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services
 */

namespace Utmatra\Services;

use Utmatra\DB\Schema;
use Utmatra\Services\Support\DateRange;
use Utmatra\Services\Support\Result;

defined( 'ABSPATH' ) || exit;

/**
 * JourneysService
 *
 * Holds the logic behind GET utmatra/v1/journeys and
 * GET utmatra/v1/journeys/<visitor_id> and the matching abilities. Events
 * are formatted by EventsService, so rows never include `ip_hash` or
 * `user_agent`.
 *
 * @since 1.1.0
 */
class JourneysService {

	/**
	 * Conversions with their preceding touches, newest first.
	 *
	 * One row per conversion that carries a visitor id, with the visitor's
	 * preceding events as touches. The touches are fetched one visitor at a
	 * time (indexed on visitor_id, LIMIT 1001, newest first and reversed in
	 * PHP) so one heavy visitor can never starve the others, as a single IN
	 * query with a global limit would. first_touch/last_touch/touch_count
	 * cover the whole fetched predecessor set (clicks and visits only —
	 * earlier conversions stay in the list but are not counted); the touches
	 * array is capped at the most recent 100 with a truncation flag.
	 * `per_page` is clamped to 1..100 and `page` forced to >= 1.
	 *
	 * @since 1.1.0 Moved from RestController::get_journeys().
	 *
	 * @param array<string, mixed> $args Keys: period, since, until, page, per_page.
	 *
	 * @return array{journeys: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int, without_visitor: int}
	 */
	public static function get_journeys( array $args ): array {
		global $wpdb;
		$table   = esc_sql( Schema::table() );
		$columns = EventsService::EVENT_COLUMNS;

		$since     = sanitize_text_field( (string) ( $args['since'] ?? '' ) );
		$until     = sanitize_text_field( (string) ( $args['until'] ?? '' ) );
		$now_local = DateRange::resolve_now_local( $until, wp_timezone() );
		$period    = DateRange::resolve_period( $since, $until, absint( $args['period'] ?? 30 ), $now_local );
		$where     = DateRange::build_where( $period, '', $now_local );

		$per_page = min( 100, max( 1, absint( $args['per_page'] ?? 50 ) ) );
		$page     = max( 1, absint( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$conv_where = "is_conversion = 1 AND visitor_id != '' AND {$where}";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- real-time analytics table; $columns is a class constant, $table/$where are built through esc_sql()/DateRange
		$conversions     = (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT {$columns} FROM {$table} WHERE {$conv_where} ORDER BY clicked_at DESC, id DESC LIMIT %d OFFSET %d", $per_page, $offset ),
			ARRAY_A
		);
		$total           = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$conv_where}" );
		$without_visitor = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_conversion = 1 AND visitor_id = '' AND {$where}" );
		// phpcs:enable

		$by_visitor = array();
		$journeys   = array();

		foreach ( $conversions as $conversion ) {
			$visitor_id = (string) $conversion['visitor_id'];

			// One fetch per distinct visitor on the page, bound to their
			// newest conversion here (the page is ordered newest first).
			if ( ! isset( $by_visitor[ $visitor_id ] ) ) {
				$by_visitor[ $visitor_id ] = self::visitor_rows( $visitor_id, $conversion );
			}

			$journeys[] = self::journey( $conversion, $by_visitor[ $visitor_id ] );
		}

		return array(
			'journeys'        => $journeys,
			'total'           => $total,
			'page'            => $page,
			'per_page'        => $per_page,
			'total_pages'     => (int) ceil( $total / $per_page ),
			'without_visitor' => $without_visitor,
		);
	}

	/**
	 * One visitor's full event list, ascending.
	 *
	 * Fetched newest-first with a LIMIT of 501 and reversed in PHP so a cut
	 * drops the oldest rows, never the newest. `conversions` counts the
	 * returned rows only.
	 *
	 * @since 1.1.0 Moved from RestController::get_journey().
	 *
	 * @param string $visitor_id Visitor UUID.
	 *
	 * @return Result The journey payload, or a 404 `utmatra_not_found` error when the visitor has no events.
	 */
	public static function get_journey( string $visitor_id ): Result {
		global $wpdb;
		$table   = esc_sql( Schema::table() );
		$columns = EventsService::EVENT_COLUMNS;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- real-time analytics table; $columns is a class constant, $table is escaped
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT {$columns} FROM {$table} WHERE visitor_id = %s ORDER BY clicked_at DESC, id DESC LIMIT 501", $visitor_id ),
			ARRAY_A
		);
		// phpcs:enable

		if ( array() === $rows ) {
			return Result::error(
				'utmatra_not_found',
				__( 'No events recorded for this visitor.', 'utmatra-utm-attribution-and-tracking' ),
				404
			);
		}

		$truncated = ( 501 === count( $rows ) );
		if ( $truncated ) {
			array_pop( $rows );
		}
		$rows = array_reverse( $rows );

		$events      = array();
		$conversions = 0;
		$first_seen  = '';
		$last_seen   = '';
		foreach ( $rows as $row ) {
			if ( ! empty( $row['is_conversion'] ) ) {
				++$conversions;
			}
			if ( '' === $first_seen ) {
				$first_seen = (string) $row['clicked_at'];
			}
			$last_seen = (string) $row['clicked_at'];
			$events[]  = EventsService::format_event( $row );
		}

		return Result::ok(
			array(
				'visitor_id'  => $visitor_id,
				'events'      => $events,
				'conversions' => $conversions,
				'first_seen'  => $first_seen,
				'last_seen'   => $last_seen,
				'truncated'   => $truncated,
			)
		);
	}

	/**
	 * Fetch a visitor's events up to and including a conversion, ascending.
	 *
	 * Capped at the newest 1000 rows; the fetch cap cuts the oldest rows,
	 * which is what keeps the conversion and its nearest touches.
	 *
	 * @since 1.1.0
	 *
	 * @param string               $visitor_id Visitor UUID.
	 * @param array<string, mixed> $conversion The visitor's newest conversion row on the page.
	 *
	 * @return array{rows: array<int, array<string, mixed>>, truncated: bool}
	 */
	private static function visitor_rows( string $visitor_id, array $conversion ): array {
		global $wpdb;
		$table   = esc_sql( Schema::table() );
		$columns = EventsService::EVENT_COLUMNS;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- real-time analytics table; $columns is a class constant, $table is escaped
		$fetched = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$columns} FROM {$table} WHERE visitor_id = %s AND ( clicked_at < %s OR ( clicked_at = %s AND id <= %d ) ) ORDER BY clicked_at DESC, id DESC LIMIT 1001",
				$visitor_id,
				(string) $conversion['clicked_at'],
				(string) $conversion['clicked_at'],
				(int) $conversion['id']
			),
			ARRAY_A
		);
		// phpcs:enable

		$truncated = ( 1001 === count( $fetched ) );
		if ( $truncated ) {
			array_pop( $fetched );
		}

		return array(
			'rows'      => array_reverse( $fetched ),
			'truncated' => $truncated,
		);
	}

	/**
	 * Build one journey from a conversion and its visitor's fetched rows.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed>                                           $conversion Conversion row (ARRAY_A).
	 * @param array{rows: array<int, array<string, mixed>>, truncated: bool} $visitor    The visitor_rows() result.
	 *
	 * @return array<string, mixed>
	 */
	private static function journey( array $conversion, array $visitor ): array {
		$predecessors = array();
		foreach ( $visitor['rows'] as $row ) {
			if ( (string) $row['clicked_at'] < (string) $conversion['clicked_at']
				|| ( (string) $row['clicked_at'] === (string) $conversion['clicked_at'] && (int) $row['id'] < (int) $conversion['id'] ) ) {
				$predecessors[] = $row;
			}
		}

		$first_touch = null;
		$last_touch  = null;
		$touch_count = 0;
		foreach ( $predecessors as $row ) {
			$kind = EventsService::event_kind( $row );
			if ( 'click' !== $kind && 'visit' !== $kind ) {
				continue;
			}
			++$touch_count;
			if ( null === $first_touch ) {
				$first_touch = $row;
			}
			$last_touch = $row;
		}

		$touches = count( $predecessors ) > 100 ? array_slice( $predecessors, -100 ) : $predecessors;

		return array(
			'conversion'        => EventsService::format_event( $conversion ),
			'visitor_id'        => (string) $conversion['visitor_id'],
			'first_touch'       => null === $first_touch ? null : EventsService::format_event( $first_touch ),
			'last_touch'        => null === $last_touch ? null : EventsService::format_event( $last_touch ),
			'touch_count'       => $touch_count,
			'touches'           => array_map( array( EventsService::class, 'format_event' ), $touches ),
			'touches_truncated' => count( $predecessors ) > 100 || $visitor['truncated'],
		);
	}
}
