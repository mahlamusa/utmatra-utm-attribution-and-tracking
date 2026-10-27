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
 * @since 1.1.0 The events and export handlers moved to EventsController and EventsService.
 * @since 1.1.0 Link and preset routes delegate to LinksController and PresetsController.
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
use Utmatra\Rest\Controllers\EventsController;
use Utmatra\Rest\Controllers\StatsController;
use Utmatra\Services\EventsService;
use Utmatra\Rest\Controllers\LinksController;
use Utmatra\Rest\Controllers\PresetsController;
use Utmatra\Services\Support\DateRange;
use Utmatra\Tracking\Tracker;
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
 * @since 1.1.0 The events and export handlers moved to EventsController and EventsService.
 * @since 1.1.0 Link and preset routes delegate to LinksController and PresetsController.
 */
class RestController {

	/**
	 * The event columns the read routes select and return.
	 *
	 * @see EventsService::EVENT_COLUMNS
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Aliases EventsService::EVENT_COLUMNS.
	 *
	 * @var string
	 */
	private const EVENT_COLUMNS = EventsService::EVENT_COLUMNS;

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 The settings route uses SettingsController and accepts `allow_agent_settings`.
	 * @since 1.1.0 GET /stats delegates to StatsController.
	 * @since 1.1.0 Link and preset routes delegate to LinksController and PresetsController.
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
					'callback'            => array( LinksController::class, 'get_links' ),
					'permission_callback' => array( self::class, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( LinksController::class, 'create_link' ),
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
					'callback'            => array( LinksController::class, 'update_link' ),
					'permission_callback' => array( self::class, 'check_links_permission' ),
					'args'                => array(
						'id' => array(
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( LinksController::class, 'delete_link' ),
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
				'callback'            => array( EventsController::class, 'export_events' ),
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
				'callback'            => array( EventsController::class, 'get_events' ),
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
				'callback'            => array( PresetsController::class, 'create_preset' ),
				'permission_callback' => array( self::class, 'check_links_permission' ),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/presets/(?P<id>[a-z0-9_\-]+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( PresetsController::class, 'delete_preset' ),
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
	 * @see EventsService::event_kind()
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Delegates to EventsService.
	 * @param array<string, mixed> $row Event row (ARRAY_A).
	 * @return string One of 'click', 'visit', 'order', 'lead'.
	 */
	private static function event_kind( array $row ): string {
		return EventsService::event_kind( $row );
	}

	/**
	 * Serialise one event row for the read routes.
	 *
	 * @see EventsService::format_event()
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Delegates to EventsService.
	 * @param array<string, mixed> $row Event row (ARRAY_A).
	 * @return array<string, mixed>
	 */
	private static function format_event( array $row ): array {
		return EventsService::format_event( $row );
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
}
