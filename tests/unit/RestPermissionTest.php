<?php
/**
 * Unit tests for the capability tier each REST route and method needs.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Admin\RestController;
use Utmatra\Capabilities;

/**
 * The tier every registered route and method must require.
 *
 * @since 1.1.0
 *
 * @return array<string, string> "METHOD route" => logical capability.
 */
function utmatra_test_route_tiers(): array {
	$id      = '/(?P<id>[a-z0-9_\-]+)';
	$visitor = '/(?P<visitor_id>[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})';

	return array(
		'GET /utmatra/v1/stats'                => Capabilities::VIEW_REPORTS,
		'GET /utmatra/v1/links'                => Capabilities::VIEW_REPORTS,
		'GET /utmatra/v1/export'               => Capabilities::VIEW_REPORTS,
		'GET /utmatra/v1/events'               => Capabilities::VIEW_REPORTS,
		'GET /utmatra/v1/journeys'             => Capabilities::VIEW_REPORTS,
		'GET /utmatra/v1/journeys' . $visitor  => Capabilities::VIEW_REPORTS,
		'GET /utmatra/v1/diagnostics'          => Capabilities::VIEW_REPORTS,
		'POST /utmatra/v1/links'               => Capabilities::MANAGE_LINKS,
		'PUT /utmatra/v1/links' . $id          => Capabilities::MANAGE_LINKS,
		'DELETE /utmatra/v1/links' . $id       => Capabilities::MANAGE_LINKS,
		'POST /utmatra/v1/presets'             => Capabilities::MANAGE_LINKS,
		'DELETE /utmatra/v1/presets' . $id     => Capabilities::MANAGE_LINKS,
		'POST /utmatra/v1/settings'            => Capabilities::MANAGE_SETTINGS,
		'POST /utmatra/v1/test-email'          => Capabilities::MANAGE_SETTINGS,
		'POST /utmatra/v1/test-webhook'        => Capabilities::MANAGE_SETTINGS,
	);
}

return array(
	'every route is covered'                    => static function () {
		RestController::register_routes();
		$registered = array_keys( $GLOBALS['utmatra_test']['routes'] );
		$expected   = array_keys( utmatra_test_route_tiers() );
		sort( $registered );
		sort( $expected );
		utmatra_assert_same( $expected, $registered );
	},

	'each route requires its tier and no other' => static function () {
		RestController::register_routes();
		$tiers = array( Capabilities::VIEW_REPORTS, Capabilities::MANAGE_LINKS, Capabilities::MANAGE_SETTINGS );

		foreach ( utmatra_test_route_tiers() as $key => $required ) {
			$check = $GLOBALS['utmatra_test']['routes'][ $key ]['permission_callback'];

			foreach ( $tiers as $held ) {
				$GLOBALS['utmatra_test']['caps'] = array( $held );
				// View is implied by either manage tier; a manage tier only by itself.
				$allowed = $held === $required || Capabilities::VIEW_REPORTS === $required;
				utmatra_assert_same( $allowed, call_user_func( $check ), "$held on $key" );
			}

			$GLOBALS['utmatra_test']['caps'] = array( 'manage_options' );
			utmatra_assert_false( call_user_func( $check ), "manage_options on $key" );
		}
	},
);
