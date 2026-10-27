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
 * @since 1.1.0 Date range helpers moved to Services\Support\DateRange.
 * @since 1.1.0 Routes check the custom capabilities instead of manage_options.
 * @since 1.1.0 The settings route delegates to SettingsController and accepts `allow_agent_settings`.
 * @since 1.1.0 GET /stats is served by Rest\Controllers\StatsController.
 * @since 1.1.0 The events and export handlers moved to EventsController and EventsService.
 * @since 1.1.0 Link and preset routes delegate to LinksController and PresetsController.
 * @since 1.1.0 The test email and test webhook routes delegate to NotificationsController.
 * @since 1.1.0 GET /diagnostics is served by Rest\Controllers\DiagnosticsController.
 * @since 1.1.0 The journeys handlers moved to JourneysController and JourneysService.
 *
 * @package Utmatra\Admin
 */

namespace Utmatra\Admin;

use Utmatra\Capabilities;
use Utmatra\Rest\Controllers\NotificationsController;
use Utmatra\Rest\Controllers\SettingsController;
use Utmatra\Reports\ConversionWebhook;
use Utmatra\Rest\Controllers\DiagnosticsController;
use Utmatra\Rest\Controllers\EventsController;
use Utmatra\Rest\Controllers\JourneysController;
use Utmatra\Rest\Controllers\StatsController;
use Utmatra\Rest\Controllers\LinksController;
use Utmatra\Rest\Controllers\PresetsController;

defined( 'ABSPATH' ) || exit;

/**
 * RestController
 *
 * @since 1.0.0
 * @since 1.1.0 Date range helpers moved to Services\Support\DateRange.
 * @since 1.1.0 Routes check the custom capabilities instead of manage_options.
 * @since 1.1.0 The settings route delegates to SettingsController and accepts `allow_agent_settings`.
 * @since 1.1.0 GET /stats is served by Rest\Controllers\StatsController.
 * @since 1.1.0 The events and export handlers moved to EventsController and EventsService.
 * @since 1.1.0 Link and preset routes delegate to LinksController and PresetsController.
 * @since 1.1.0 The test email and test webhook routes delegate to NotificationsController.
 * @since 1.1.0 GET /diagnostics is served by Rest\Controllers\DiagnosticsController.
 * @since 1.1.0 The journeys handlers moved to JourneysController and JourneysService.
 */
class RestController {

	/**
	 * Register REST routes.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 The settings route uses SettingsController and accepts `allow_agent_settings`.
	 * @since 1.1.0 GET /stats delegates to StatsController.
	 * @since 1.1.0 Link and preset routes delegate to LinksController and PresetsController.
	 * @since 1.1.0 The test email and test webhook routes use NotificationsController.
	 * @since 1.1.0 GET /diagnostics delegates to DiagnosticsController.
	 * @since 1.1.0 The journeys routes delegate to JourneysController.
	 * @since 1.1.0 The date-ranged routes share their arguments through date_args().
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
				'args'                => self::date_args(),
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
				'args'                => array_merge(
					self::date_args(),
					array(
						'include_visits' => array(
							'default'           => false,
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
					)
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
					self::date_args(),
					array(
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
				'callback'            => array( JourneysController::class, 'get_journeys' ),
				'permission_callback' => array( self::class, 'check_permission' ),
				'args'                => array_merge(
					self::pagination_args(),
					self::date_args( false )
				),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/journeys/(?P<visitor_id>[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})',
			array(
				'methods'             => 'GET',
				'callback'            => array( JourneysController::class, 'get_journey' ),
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
				'callback'            => array( NotificationsController::class, 'send_test_email' ),
				'permission_callback' => array( self::class, 'check_settings_permission' ),
			)
		);

		register_rest_route(
			'utmatra/v1',
			'/test-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( NotificationsController::class, 'send_test_webhook' ),
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
				'callback'            => array( DiagnosticsController::class, 'get_diagnostics' ),
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
	 * Shared period/campaign/since/until argument definitions for the date-ranged read routes.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $with_campaign Whether to include the campaign filter (the journeys route has none).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function date_args( bool $with_campaign = true ): array {
		$args = array(
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
		);

		if ( ! $with_campaign ) {
			unset( $args['campaign'] );
		}

		return $args;
	}
}
