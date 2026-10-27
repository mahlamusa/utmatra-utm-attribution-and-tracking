<?php
/**
 * REST Controller — exposes stats and links data to the dashboard.
 *
 * Routes:
 *   GET    utmatra/v1/stats       — aggregated click data
 *   GET    utmatra/v1/links       — managed links with per-link click counts
 *   POST   utmatra/v1/links       — create a managed link
 *   PUT    utmatra/v1/links/<id>  — update a managed link
 *   DELETE utmatra/v1/links/<id>  — delete a managed link
 *   GET    utmatra/v1/export      — raw event rows for CSV export
 *   POST   utmatra/v1/presets     — save a UTM preset
 *   DELETE utmatra/v1/presets/<id> — delete a UTM preset
 *   POST   utmatra/v1/test-email  — send a test weekly report email
 *   POST   utmatra/v1/test-webhook — send a test webhook
 *   POST   utmatra/v1/settings    — save the dashboard settings
 *   GET    utmatra/v1/diagnostics — Tracking Doctor diagnostic check results
 *   GET    utmatra/v1/events      — paginated, filterable event rows
 *   GET    utmatra/v1/journeys    — conversions with their preceding touches
 *   GET    utmatra/v1/journeys/<visitor_id> — one visitor's full event list
 *
 * Reads require `utmatra_view_reports`; link and preset writes require
 * `utmatra_manage_links`; settings, test email and test webhook require
 * `utmatra_manage_settings` (see Capabilities for the hierarchy).
 *
 * @since 1.0.0
 * @since 1.0.0 Keeps a link's stored values for parameters no longer configured.
 * @since 1.1.0 Date range helpers delegate to Services\Support\DateRange.
 * @since 1.1.0 Routes check the custom capabilities instead of manage_options.
 * @since 1.1.0 The settings route delegates to SettingsController and accepts `allow_agent_settings`.
 * @since 1.1.0 GET /stats is served by Rest\Controllers\StatsController.
 *
 * @package Utmatra\Admin
 */

namespace Utmatra\Admin;

use DateTimeImmutable;
use DateTimeZone;
use stdClass;
use Utmatra\Capabilities;
use Utmatra\DB\Schema;
use Utmatra\Diagnostics\Checks;
use Utmatra\Rest\Controllers\SettingsController;
use Utmatra\Reports\ConversionWebhook;
use Utmatra\Reports\WeeklyEmail;
use Utmatra\Rest\Controllers\StatsController;
use Utmatra\Services\Support\DateRange;
use Utmatra\Tracking\LinkManager;
use Utmatra\Tracking\Tracker;
use Utmatra\Tracking\TrafficSource;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * RestController
 *
 * @since 1.0.0
 * @since 1.1.0 Date range helpers delegate to Services\Support\DateRange.
 * @since 1.1.0 Routes check the custom capabilities instead of manage_options.
 * @since 1.1.0 The settings route delegates to SettingsController and accepts `allow_agent_settings`.
 * @since 1.1.0 GET /stats is served by Rest\Controllers\StatsController.
 */
class RestController {

	/**
	 * The event columns the read routes select and return.
	 *
	 * The privacy-light set: `ip_hash` and `user_agent` are never included.
	 * Order matches the events table and is shared by /events, /journeys
	 * and /journeys/{visitor_id}.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private const EVENT_COLUMNS = 'id, link_id, visitor_id, clicked_at, utm_source, utm_medium, utm_campaign, utm_id, utm_term, utm_content, gclid, fbclid, msclkid, ttclid, li_fat_id, twclid, destination, referrer, is_conversion, touch, order_total, device, channel, custom, meta';

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 The settings route uses SettingsController and accepts `allow_agent_settings`.
	 * @since 1.1.0 GET /stats delegates to StatsController.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			'utmatra/v1',
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( StatsController::class, 'get_stats' ),
				'permission_callback' => array( self::class, 'check_permission' ),
				'args'                => array(
					'period'   => array(
						'default'           => 30,
						'sanitize_callback' => 'absint',
					),
					'campaign' => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'since'    => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'until'    => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/links',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'get_links' ),
					'permission_callback' => array( self::class, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'create_link' ),
					'permission_callback' => array( self::class, 'check_links_permission' ),
				),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/links/(?P<id>[a-z0-9_\-]+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( self::class, 'update_link' ),
					'permission_callback' => array( self::class, 'check_links_permission' ),
					'args'                => array(
						'id' => array(
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( self::class, 'delete_link' ),
					'permission_callback' => array( self::class, 'check_links_permission' ),
					'args'                => array(
						'id' => array(
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'export_events' ),
				'permission_callback' => array( self::class, 'check_permission' ),
				'args'                => array(
					'period'         => array(
						'default'           => 30,
						'sanitize_callback' => 'absint',
					),
					'campaign'       => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'since'          => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'until'          => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'include_visits' => array(
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/events',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_events' ),
				'permission_callback' => array( self::class, 'check_permission' ),
				'args'                => array_merge(
					self::pagination_args(),
					array(
						'period'     => array(
							'default'           => 30,
							'sanitize_callback' => 'absint',
						),
						'campaign'   => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'since'      => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'until'      => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'channel'    => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
						'kind'       => array(
							'type'              => 'string',
							'enum'              => array( 'click', 'visit', 'order', 'lead' ),
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'visitor_id' => array(
							'type'              => 'string',
							'format'            => 'uuid',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
					)
				),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/journeys',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_journeys' ),
				'permission_callback' => array( self::class, 'check_permission' ),
				'args'                => array_merge(
					self::pagination_args(),
					array(
						'period' => array(
							'default'           => 30,
							'sanitize_callback' => 'absint',
						),
						'since'  => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'until'  => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					)
				),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/journeys/(?P<visitor_id>[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_journey' ),
				'permission_callback' => array( self::class, 'check_permission' ),
				'args'                => array(
					'visitor_id' => array(
						'type'              => 'string',
						'format'            => 'uuid',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/presets',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'create_preset' ),
				'permission_callback' => array( self::class, 'check_links_permission' ),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/presets/(?P<id>[a-z0-9_\-]+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( self::class, 'delete_preset' ),
				'permission_callback' => array( self::class, 'check_links_permission' ),
				'args'                => array(
					'id' => array(
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/test-email',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'send_test_email' ),
				'permission_callback' => array( self::class, 'check_settings_permission' ),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/test-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'send_test_webhook' ),
				'permission_callback' => array( self::class, 'check_settings_permission' ),
				'args'                => array(
					'preset' => array(
						'sanitize_callback' => 'sanitize_key',
					),
					'leads'  => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/settings',
			array(
				'methods'             => 'POST',
				'callback'            => array( SettingsController::class, 'save_settings' ),
				'permission_callback' => array( self::class, 'check_settings_permission' ),
				'args'                => array(
					'retention_days'       => array(
						'sanitize_callback' => 'absint',
					),
					'weekly_email'         => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'cookie_days'          => array(
						'sanitize_callback' => 'absint',
					),
					'webhook_url'          => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'webhook_preset'       => array(
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static function ( $value ) {
							return in_array( $value, ConversionWebhook::PRESETS, true );
						},
					),
					'webhook_orders'       => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'webhook_leads'        => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'consent_category'     => array(
						'sanitize_callback' => 'sanitize_key',
					),
					'track_visits'         => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'custom_params'        => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'allow_agent_settings' => array(
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/diagnostics',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_diagnostics' ),
				'permission_callback' => array( self::class, 'check_permission' ),
			)
		);
	}

	/**
	 * Permission check for the read routes — view reports (any tier implies it).
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Checks the view reports capability instead of manage_options.
	 *
	 * @return bool
	 */
	public static function check_permission(): bool {
		return Capabilities::can( Capabilities::VIEW_REPORTS );
	}

	/**
	 * Permission check for the link and preset write routes — manage links.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public static function check_links_permission(): bool {
		return Capabilities::can( Capabilities::MANAGE_LINKS );
	}

	/**
	 * Permission check for the settings, test email and test webhook routes — manage settings.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public static function check_settings_permission(): bool {
		return Capabilities::can( Capabilities::MANAGE_SETTINGS );
	}

	/**
	 * Resolve the reference "now" instant a stats/export request anchors to.
	 *
	 * @see DateRange::resolve_now_local()
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Delegates to DateRange.
	 * @param WP_REST_Request $request  REST request.
	 * @param DateTimeZone    $timezone Site timezone.
	 * @return DateTimeImmutable
	 */
	private static function resolve_now_local( WP_REST_Request $request, DateTimeZone $timezone ): DateTimeImmutable {
		return DateRange::resolve_now_local( (string) $request->get_param( 'until' ), $timezone );
	}

	/**
	 * Resolve the period length in days for a stats/export request.
	 *
	 * @see DateRange::resolve_period()
	 *
	 * @since 1.0.0
	 * @since 1.0.0 A single-day custom range resolves to 0, not 1.
	 * @since 1.1.0 Delegates to DateRange.
	 * @param WP_REST_Request   $request   REST request.
	 * @param DateTimeImmutable $now_local Resolved reference instant.
	 * @return int
	 */
	private static function resolve_period( WP_REST_Request $request, DateTimeImmutable $now_local ): int {
		return DateRange::resolve_period(
			(string) $request->get_param( 'since' ),
			(string) $request->get_param( 'until' ),
			(int) $request->get_param( 'period' ),
			$now_local
		);
	}

	/**
	 * Build the prepared WHERE clause shared by the stats and export queries.
	 *
	 * @see DateRange::build_where()
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Delegates to DateRange.
	 * @param int               $period    Period length in days.
	 * @param string            $campaign  Optional utm_campaign filter; empty for all.
	 * @param DateTimeImmutable $now_local Reference instant (real "now", or a resolved custom `until`).
	 * @return string Prepared SQL WHERE clause (without the WHERE keyword).
	 */
	private static function build_where( int $period, string $campaign, DateTimeImmutable $now_local ): string {
		return DateRange::build_where( $period, $campaign, $now_local );
	}

	/**
	 * GET utmatra/v1/export
	 *
	 * Returns raw event rows for the period as JSON; the dashboard assembles
	 * the CSV client-side. Exports are privacy-light by design: `ip_hash` and
	 * `user_agent` are never included. Capped at the newest 10,000 rows.
	 * Conversion rows are included and flagged via the `is_conversion` column.
	 * Untagged landing visits are left out unless `include_visits` is set;
	 * every row carries its `channel`, its `visitor_id` (directly after
	 * `order_total`) and one key per configured custom parameter (empty
	 * string when the row has no value; the raw `custom` JSON column is
	 * never returned).
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function export_events( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$table    = esc_sql( Schema::table() );
		$campaign = (string) $request->get_param( 'campaign' );

		$now_local = self::resolve_now_local( $request, wp_timezone() );
		$period    = self::resolve_period( $request, $now_local );
		$where     = self::build_where( $period, $campaign, $now_local );
		if ( ! $request->get_param( 'include_visits' ) ) {
			$where .= " AND link_id != 'visit'";
		}
		$channel_case = TrafficSource::sql_channel_expression();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			"SELECT clicked_at, link_id, {$channel_case} AS channel, utm_source, utm_medium, utm_campaign, utm_id, utm_term, utm_content,
			        gclid, fbclid, msclkid, ttclid, li_fat_id, twclid, destination, referrer, is_conversion, order_total, visitor_id, custom
			 FROM {$table}
			 WHERE {$where}
			 ORDER BY clicked_at DESC
			 LIMIT 10000",
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

		return new WP_REST_Response( array( 'rows' => array_values( (array) $rows ) ), 200 );
	}

	/**
	 * Shared page/per_page argument definitions for the paginated read routes.
	 *
	 * The handlers clamp the values (1..200 for /events, 1..100 for
	 * /journeys) and force page >= 1, so an out-of-range request degrades
	 * instead of erroring.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>>
	 */
	private static function pagination_args(): array {
		return array(
			'page'     => array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'default'           => 50,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * GET utmatra/v1/events
	 *
	 * Paginated, filterable event rows, newest first. The date range matches
	 * /stats (resolve_now_local/resolve_period/build_where), so the two
	 * partition the same rows; `channel` filters the stored column only
	 * (legacy rows hold '' and never match); `kind` filters on the derived
	 * kind (see event_kind()); `visitor_id` accepts any UUID version, so a
	 * non-v4 value simply matches no rows.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_events( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$table    = esc_sql( Schema::table() );
		$columns  = self::EVENT_COLUMNS;
		$campaign = (string) $request->get_param( 'campaign' );

		$now_local = self::resolve_now_local( $request, wp_timezone() );
		$period    = self::resolve_period( $request, $now_local );

		$where = self::build_where( $period, $campaign, $now_local );

		$channel = (string) $request->get_param( 'channel' );
		if ( '' !== $channel ) {
			$where .= $wpdb->prepare( ' AND channel = %s', $channel );
		}

		$visitor_id = (string) $request->get_param( 'visitor_id' );
		if ( '' !== $visitor_id ) {
			$where .= $wpdb->prepare( ' AND visitor_id = %s', $visitor_id );
		}

		switch ( (string) $request->get_param( 'kind' ) ) {
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

		$per_page = min( 200, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
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

		return new WP_REST_Response(
			array(
				'events'      => $events,
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			),
			200
		);
	}

	/**
	 * GET utmatra/v1/journeys
	 *
	 * One row per conversion that carries a visitor id, newest first, with
	 * the visitor's preceding events as touches. The touches are fetched one
	 * visitor at a time (indexed on visitor_id, LIMIT 1001, newest first and
	 * reversed in PHP) so one heavy visitor can never starve the others, as a
	 * single IN query with a global limit would. first_touch/last_touch/
	 * touch_count cover the whole fetched predecessor set (clicks and visits
	 * only — earlier conversions stay in the list but are not counted); the
	 * touches array is capped at the most recent 100 with a truncation flag.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_journeys( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$table   = esc_sql( Schema::table() );
		$columns = self::EVENT_COLUMNS;

		$now_local = self::resolve_now_local( $request, wp_timezone() );
		$period    = self::resolve_period( $request, $now_local );
		$where     = self::build_where( $period, '', $now_local );

		$per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$conv_where = "is_conversion = 1 AND visitor_id != '' AND {$where}";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- real-time analytics table; see get_events()
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
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- see get_events()
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
				$visitor_truncated = ( 1001 === count( $fetched ) );
				if ( $visitor_truncated ) {
					array_pop( $fetched );
				}
				// Reverse to ascending: the fetch cap cut the oldest rows,
				// which is what keeps the conversion and its nearest touches.
				$by_visitor[ $visitor_id ] = array(
					'rows'      => array_reverse( $fetched ),
					'truncated' => $visitor_truncated,
				);
			}

			$visitor = $by_visitor[ $visitor_id ];

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
				$kind = self::event_kind( $row );
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

			$journey = array(
				'conversion'        => self::format_event( $conversion ),
				'visitor_id'        => $visitor_id,
				'first_touch'       => null === $first_touch ? null : self::format_event( $first_touch ),
				'last_touch'        => null === $last_touch ? null : self::format_event( $last_touch ),
				'touch_count'       => $touch_count,
				'touches'           => array(),
				'touches_truncated' => count( $predecessors ) > 100 || $visitor['truncated'],
			);
			foreach ( $touches as $touch ) {
				$journey['touches'][] = self::format_event( $touch );
			}
			$journeys[] = $journey;
		}

		return new WP_REST_Response(
			array(
				'journeys'        => $journeys,
				'total'           => $total,
				'page'            => $page,
				'per_page'        => $per_page,
				'total_pages'     => (int) ceil( $total / $per_page ),
				'without_visitor' => $without_visitor,
			),
			200
		);
	}

	/**
	 * GET utmatra/v1/journeys/<visitor_id>
	 *
	 * The visitor's full event list, ascending. Fetched newest-first with a
	 * LIMIT of 501 and reversed in PHP so a cut drops the oldest rows, never
	 * the newest. `conversions` counts the returned rows only.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_journey( WP_REST_Request $request ) {
		global $wpdb;
		$table      = esc_sql( Schema::table() );
		$columns    = self::EVENT_COLUMNS;
		$visitor_id = (string) $request->get_param( 'visitor_id' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- see get_events()
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT {$columns} FROM {$table} WHERE visitor_id = %s ORDER BY clicked_at DESC, id DESC LIMIT 501", $visitor_id ),
			ARRAY_A
		);
		// phpcs:enable

		if ( array() === $rows ) {
			return new WP_Error(
				'utmatra_not_found',
				__( 'No events recorded for this visitor.', 'utmatra-utm-attribution-and-tracking' ),
				array( 'status' => 404 )
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
			$events[]  = self::format_event( $row );
		}

		return new WP_REST_Response(
			array(
				'visitor_id'  => $visitor_id,
				'events'      => $events,
				'conversions' => $conversions,
				'first_seen'  => $first_seen,
				'last_seen'   => $last_seen,
				'truncated'   => $truncated,
			),
			200
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
	 * @since 1.0.0
	 * @param array<string, mixed> $row Event row (ARRAY_A).
	 * @return string One of 'click', 'visit', 'order', 'lead'.
	 */
	private static function event_kind( array $row ): string {
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
	 * Serialise one event row for the read routes.
	 *
	 * Casts id/is_conversion/order_total, leaves clicked_at exactly as stored
	 * (UTC `Y-m-d H:i:s`) and emits `channel` as stored ('' on legacy rows).
	 * The two JSON columns are always objects: decoded without `assoc` and
	 * replaced with an empty stdClass when NULL, empty or undecodable, so the
	 * response encodes `{}` and never `[]`. `ip_hash` and `user_agent` are
	 * never selected (see EVENT_COLUMNS).
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $row Event row (ARRAY_A).
	 * @return array<string, mixed>
	 */
	private static function format_event( array $row ): array {
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
	 * POST utmatra/v1/test-email — send the weekly summary now.
	 *
	 * Sends the report regardless of the weekly toggle so admins can preview
	 * the email and confirm delivery works before enabling the schedule.
	 *
	 * @since 1.0.0
	 * @return WP_REST_Response
	 */
	public static function send_test_email(): WP_REST_Response {
		return new WP_REST_Response( array( 'sent' => WeeklyEmail::send( true ) ), 200 );
	}

	/**
	 * POST utmatra/v1/test-webhook — send a synthetic payload now.
	 *
	 * Lets the admin verify their receiver and HMAC signature checking work
	 * before relying on it for real events. The optional `preset` and
	 * `leads` args forward the live settings-form values so a preset can be
	 * tested before it is saved. No-ops (returns sent:false) when no URL is
	 * configured or no event is enabled.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function send_test_webhook( WP_REST_Request $request ): WP_REST_Response {
		$sent = ConversionWebhook::send_test(
			(string) $request->get_param( 'preset' ),
			$request->has_param( 'leads' ) ? (bool) $request->get_param( 'leads' ) : null
		);

		return new WP_REST_Response( array( 'sent' => $sent ), 200 );
	}

	/**
	 * GET utmatra/v1/diagnostics — run the Tracking Doctor checks.
	 *
	 * Computed fresh on every request; every check is a cheap local read
	 * (option/meta reads, a bounded orders query), never cached.
	 *
	 * @since 1.0.0
	 * @return WP_REST_Response
	 */
	public static function get_diagnostics(): WP_REST_Response {
		return new WP_REST_Response( array( 'checks' => Checks::run_all() ), 200 );
	}

	/**
	 * GET utmatra/v1/links
	 *
	 * Click counts for all links come from a single GROUP BY query instead
	 * of one COUNT query per link. The response also carries a `suggestions`
	 * map of previously used source/medium/campaign values for the add-link
	 * form datalists.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function get_links( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$table  = esc_sql( Schema::table() );
		$links  = LinkManager::all();
		$result = array();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching
		$counts_raw = $wpdb->get_results(
			"SELECT link_id, COUNT(*) AS count FROM {$table} WHERE link_id != '' GROUP BY link_id",
			ARRAY_A
		);
		// phpcs:enable

		$counts = array();
		foreach ( (array) $counts_raw as $row ) {
			$counts[ (string) $row['link_id'] ] = (int) $row['count'];
		}

		foreach ( $links as $id => $link ) {
			$result[] = self::format_link( $id, $link, $counts[ $id ] ?? 0 );
		}

		return new WP_REST_Response(
			array(
				'links'       => $result,
				'suggestions' => self::suggestions( $links ),
				'presets'     => self::presets(),
			),
			200
		);
	}

	/**
	 * Return the saved UTM presets for the add-link form's preset dropdown.
	 *
	 * @since 1.0.0
	 * @return array<int, array<string, string>>
	 */
	private static function presets(): array {
		$out = array();
		foreach ( (array) get_option( 'utmatra_presets', array() ) as $id => $preset ) {
			if ( ! is_array( $preset ) ) {
				continue;
			}
			$out[] = array(
				'id'          => (string) $id,
				'name'        => (string) ( $preset['name'] ?? $id ),
				'utm_source'  => (string) ( $preset['utm_source'] ?? '' ),
				'utm_medium'  => (string) ( $preset['utm_medium'] ?? '' ),
				'utm_term'    => (string) ( $preset['utm_term'] ?? '' ),
				'utm_content' => (string) ( $preset['utm_content'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * POST utmatra/v1/presets — save a named UTM preset.
	 *
	 * A preset is a reusable source/medium/term/content combination (e.g.
	 * "Instagram Story") the admin can apply to the add-link form instead of
	 * retyping the same values for every new campaign link. Saving a name
	 * that already exists overwrites that preset — this is an explicit,
	 * low-stakes admin action, not data with history worth preserving.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function create_preset( WP_REST_Request $request ): WP_REST_Response {
		$name = sanitize_text_field( self::param_string( $request, 'name' ) );
		$id   = sanitize_title( $name );

		if ( '' === $id ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Please provide a preset name.', 'utmatra-utm-attribution-and-tracking' ) ),
				400
			);
		}

		$presets        = (array) get_option( 'utmatra_presets', array() );
		$presets[ $id ] = array(
			'name'        => $name,
			'utm_source'  => self::normalize_utm( sanitize_text_field( self::param_string( $request, 'utm_source' ) ) ),
			'utm_medium'  => self::normalize_utm( sanitize_text_field( self::param_string( $request, 'utm_medium' ) ) ),
			'utm_term'    => self::normalize_utm( sanitize_text_field( self::param_string( $request, 'utm_term' ) ) ),
			'utm_content' => self::normalize_utm( sanitize_text_field( self::param_string( $request, 'utm_content' ) ) ),
		);
		update_option( 'utmatra_presets', $presets );

		return new WP_REST_Response( array_merge( array( 'id' => $id ), $presets[ $id ] ), 201 );
	}

	/**
	 * DELETE utmatra/v1/presets/<id> — delete a saved UTM preset.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function delete_preset( WP_REST_Request $request ): WP_REST_Response {
		$id      = (string) $request->get_param( 'id' );
		$presets = (array) get_option( 'utmatra_presets', array() );

		if ( ! isset( $presets[ $id ] ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Preset not found.', 'utmatra-utm-attribution-and-tracking' ) ),
				404
			);
		}

		unset( $presets[ $id ] );
		update_option( 'utmatra_presets', $presets );

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Collect previously used UTM values for the add-link form datalists.
	 *
	 * Merges distinct values from recorded events (any row type — a value is
	 * a useful suggestion regardless of whether it came from a click or a
	 * conversion) with the values stored on managed links, deduped and capped
	 * at 20 per field.
	 *
	 * @since 1.0.0
	 * @param array<string, array<string, string>> $links Managed links keyed by slug.
	 * @return array<string, string[]> Keys `sources`, `mediums`, `campaigns`.
	 */
	private static function suggestions( array $links ): array {
		global $wpdb;
		$table = esc_sql( Schema::table() );

		$columns = array(
			'sources'   => 'utm_source',
			'mediums'   => 'utm_medium',
			'campaigns' => 'utm_campaign',
		);

		$out = array();
		foreach ( $columns as $key => $column ) {
			// $column comes from the fixed map above, never from user input.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching
			$values = (array) $wpdb->get_col(
				"SELECT DISTINCT {$column} FROM {$table} WHERE {$column} != '' ORDER BY {$column} LIMIT 20"
			);
			// phpcs:enable

			foreach ( $links as $link ) {
				if ( ! empty( $link[ $column ] ) ) {
					$values[] = (string) $link[ $column ];
				}
			}

			$out[ $key ] = array_slice( array_values( array_unique( array_map( 'strval', $values ) ) ), 0, 20 );
		}

		return $out;
	}

	/**
	 * Format a managed link for a REST response.
	 *
	 * Includes both the built destination (UTM parameters and link-level
	 * custom parameter values appended) and the raw stored fields so the
	 * dashboard edit form can pre-fill correctly.
	 *
	 * @since 1.0.0
	 * @param string               $id     Link slug.
	 * @param array<string, mixed> $link   Stored link fields.
	 * @param int                  $clicks Recorded click count for the slug.
	 * @return array<string, int|string|array<string, string>|stdClass> The custom entry is a stdClass (JSON object over REST).
	 */
	private static function format_link( string $id, array $link, int $clicks ): array {
		return array(
			'id'              => $id,
			'label'           => $link['label'] ?? $id,
			'destination'     => LinkManager::build_url( $link ),
			'destination_raw' => $link['destination'] ?? '',
			'redirect_url'    => LinkManager::redirect_url( $id ),
			'clicks'          => $clicks,
			'utm_campaign'    => $link['utm_campaign'] ?? '',
			'utm_source'      => $link['utm_source'] ?? '',
			'utm_medium'      => $link['utm_medium'] ?? '',
			'utm_id'          => $link['utm_id'] ?? '',
			'utm_term'        => $link['utm_term'] ?? '',
			'utm_content'     => $link['utm_content'] ?? '',
			'custom'          => (object) ( isset( $link['custom'] ) && is_array( $link['custom'] ) ? $link['custom'] : array() ),
		);
	}

	/**
	 * Read a request parameter as a string, rejecting non-scalar values.
	 *
	 * A JSON body can carry arrays/objects for any parameter; casting those
	 * with `(string)` raises an Array-to-string warning and stores the
	 * literal `Array`. Non-scalar values are treated as absent instead.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request.
	 * @param string          $key     Parameter name.
	 * @return string
	 */
	private static function param_string( WP_REST_Request $request, string $key ): string {
		$value = $request->get_param( $key );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Validate and sanitize the link fields shared by create and update.
	 *
	 * Sanitizes the label and the six UTM fields and validates the
	 * destination as an http(s) URL. UTM values are normalized for
	 * consistent reporting (lowercase, whitespace collapsed to `-`) — the
	 * dashboard JS applies the same normalization for immediate feedback,
	 * but this server-side pass is authoritative. Custom parameter values
	 * from the `custom` object are kept only for currently configured
	 * names, sanitized and capped at 255 characters but deliberately not
	 * normalized (coupon and affiliate codes are often case-sensitive);
	 * unknown keys are dropped silently.
	 *
	 * @since 1.0.0
	 * @since 1.0.0 Truncates by character, not byte.
	 * @param WP_REST_Request $request REST request.
	 * @return array<string, mixed>|WP_REST_Response Sanitized link fields, or a 400 response when the destination is invalid.
	 */
	private static function validate_link_input( WP_REST_Request $request ) {
		$destination = esc_url_raw( self::param_string( $request, 'destination' ), array( 'http', 'https' ) );

		if ( '' === $destination ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Please provide a valid destination URL (http or https).', 'utmatra-utm-attribution-and-tracking' ) ),
				400
			);
		}

		$link = array(
			'label'       => sanitize_text_field( self::param_string( $request, 'label' ) ),
			'destination' => $destination,
		);
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_term', 'utm_content' ) as $key ) {
			$link[ $key ] = self::normalize_utm( sanitize_text_field( self::param_string( $request, $key ) ) );
		}

		$custom_in = $request->get_param( 'custom' );
		if ( is_array( $custom_in ) ) {
			$custom = array();
			foreach ( Tracker::custom_params() as $name ) {
				$value = $custom_in[ $name ] ?? '';
				if ( is_scalar( $value ) ) {
					$value = mb_substr( sanitize_text_field( (string) $value ), 0, 255 );
					if ( '' !== $value ) {
						$custom[ $name ] = $value;
					}
				}
			}
			if ( array() !== $custom ) {
				$link['custom'] = $custom;
			}
		}

		return $link;
	}

	/**
	 * Normalize a UTM value for consistent reporting.
	 *
	 * Lowercases the value and replaces whitespace runs with `-` so
	 * `My Newsletter` and `my-newsletter` never fragment into two report
	 * rows. Mirrors the normalization in assets/js/dashboard.js — keep in
	 * sync.
	 *
	 * @since 1.0.0
	 * @param string $value Sanitized UTM value.
	 * @return string
	 */
	private static function normalize_utm( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		$value = (string) preg_replace( '/\s+/u', '-', $value );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
	}

	/**
	 * POST utmatra/v1/links — create a managed link.
	 *
	 * The slug is derived from the submitted label when no explicit id is
	 * given. Fails when the slug is empty, reserved (`incoming`, `visit`,
	 * `order` and `lead` name event kinds in the events table), already in
	 * use, or the destination is not a valid http(s) URL.
	 *
	 * @since 1.0.0
	 * @since 1.0.0 Caps the slug at 64 characters, the click column width,
	 *              suffixing a derived slug that collides with a different
	 *              label after the cut.
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function create_link( WP_REST_Request $request ): WP_REST_Response {
		$label    = sanitize_text_field( self::param_string( $request, 'label' ) );
		$id_param = self::param_string( $request, 'id' );
		$id       = sanitize_key( '' !== $id_param ? $id_param : sanitize_title( $label ) );
		// Clicks store link_id in a VARCHAR(64) column, so a longer slug would
		// redirect but never match its own click rows. Cap it here, before the
		// reserved and duplicate checks.
		if ( strlen( $id ) > 64 ) {
			$id = rtrim( substr( $id, 0, 64 ), '-_' );
			// Two different long labels can share their first 64 characters
			// (a duplicated link adds " (copy)" at the end), so a derived slug
			// that collides with another label after the cut gets a numeric
			// suffix. The same label again still fails as a duplicate.
			$existing = '' === $id_param ? LinkManager::get( $id ) : null;
			if ( null !== $existing && ( $existing['label'] ?? '' ) !== $label ) {
				$base = rtrim( substr( $id, 0, 60 ), '-_' );
				for ( $n = 2; $n < 100; $n++ ) {
					$id    = $base . '-' . $n;
					$taken = LinkManager::get( $id );
					// A slug already holding this label falls through to the
					// duplicate error below.
					if ( null === $taken || ( $taken['label'] ?? '' ) === $label ) {
						break;
					}
				}
			}
		}

		if ( '' === $id || in_array( $id, array( 'incoming', 'visit', 'order', 'lead' ), true ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Please provide a valid link slug (incoming, visit, order and lead are reserved).', 'utmatra-utm-attribution-and-tracking' ) ),
				400
			);
		}

		$link = self::validate_link_input( $request );
		if ( $link instanceof WP_REST_Response ) {
			return $link;
		}

		if ( null !== LinkManager::get( $id ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'A link with this slug already exists.', 'utmatra-utm-attribution-and-tracking' ) ),
				409
			);
		}

		if ( '' === $link['label'] ) {
			$link['label'] = $id;
		}

		LinkManager::save( $id, $link );

		return new WP_REST_Response( self::format_link( $id, $link, 0 ), 201 );
	}

	/**
	 * PUT utmatra/v1/links/<id> — update a managed link.
	 *
	 * Updates the label, destination, the six UTM fields and the custom
	 * parameter values of an existing link. Stored values for custom
	 * parameters no longer configured are kept, so re-adding the parameter
	 * restores them. The slug is immutable — renaming would orphan recorded
	 * events. Returns 404 for an unknown slug and 400 for an invalid
	 * destination.
	 *
	 * @since 1.0.0
	 * @since 1.0.0 Keeps stored values for custom parameters no longer configured.
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function update_link( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$id = (string) $request->get_param( 'id' );

		$stored = LinkManager::get( $id );
		if ( null === $stored ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Link not found.', 'utmatra-utm-attribution-and-tracking' ) ),
				404
			);
		}

		$link = self::validate_link_input( $request );
		if ( $link instanceof WP_REST_Response ) {
			return $link;
		}

		if ( '' === $link['label'] ) {
			$link['label'] = $id;
		}

		// Values for parameters no longer configured are not in the form, so
		// they are carried over: re-adding the parameter brings them back.
		$kept = array_diff_key( is_array( $stored['custom'] ?? null ) ? $stored['custom'] : array(), array_flip( Tracker::custom_params() ) );
		if ( array() !== $kept ) {
			$link['custom'] = ( $link['custom'] ?? array() ) + $kept;
		}

		LinkManager::save( $id, $link );

		$table = esc_sql( Schema::table() );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching
		$clicks = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE link_id = %s", $id )
		);
		// phpcs:enable

		return new WP_REST_Response( self::format_link( $id, $link, $clicks ), 200 );
	}

	/**
	 * DELETE utmatra/v1/links/<id> — delete a managed link.
	 *
	 * Recorded click events are kept for historical stats; only the managed
	 * link definition is removed.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function delete_link( WP_REST_Request $request ): WP_REST_Response {
		$id = (string) $request->get_param( 'id' );

		if ( null === LinkManager::get( $id ) ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Link not found.', 'utmatra-utm-attribution-and-tracking' ) ),
				404
			);
		}

		LinkManager::delete( $id );

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}
}
