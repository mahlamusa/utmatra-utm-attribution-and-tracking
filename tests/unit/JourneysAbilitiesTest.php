<?php
/**
 * Unit tests for the utmatra/list-journeys and utmatra/get-journey abilities.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Abilities\AbilitiesRegistrar;
use Utmatra\Abilities\Definitions\GetJourney;
use Utmatra\Abilities\Definitions\ListJourneys;
use Utmatra\Capabilities;
use Utmatra\Services\JourneysService;

require_once dirname( __DIR__, 2 ) . '/src/Abilities/Definitions/ListJourneys.php';
require_once dirname( __DIR__, 2 ) . '/src/Abilities/Definitions/GetJourney.php';

return array(
	'journey abilities are read only and mcp public'     => static function () {
		foreach ( array( new ListJourneys(), new GetJourney() ) as $definition ) {
			$args = AbilitiesRegistrar::ability_args( $definition );
			utmatra_assert_same( AbilitiesRegistrar::CATEGORY, $args['category'], $definition->id() );
			utmatra_assert_true( $args['meta']['show_in_rest'], $definition->id() );
			utmatra_assert_true( $args['meta']['annotations']['readonly'], $definition->id() );
			utmatra_assert_true( $args['meta']['mcp']['public'], $definition->id() );
			utmatra_assert_same( 'object', $args['input_schema']['type'], $definition->id() );
			utmatra_assert_same( 'object', $args['output_schema']['type'], $definition->id() );
		}
		utmatra_assert_same( 'utmatra/list-journeys', ( new ListJourneys() )->id() );
		utmatra_assert_same( 'utmatra/get-journey', ( new GetJourney() )->id() );
	},

	'journey abilities need the view reports capability' => static function () {
		foreach ( array( new ListJourneys(), new GetJourney() ) as $definition ) {
			utmatra_assert_false( $definition->permission(), $definition->id() );
		}

		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::VIEW_REPORTS );
		foreach ( array( new ListJourneys(), new GetJourney() ) as $definition ) {
			utmatra_assert_true( $definition->permission(), $definition->id() );
		}
	},

	'journey schemas mirror the rest args'               => static function () {
		$list = ( new ListJourneys() )->args()['input_schema']['properties'];
		utmatra_assert_same( array( 'period', 'since', 'until', 'page', 'per_page' ), array_keys( $list ) );
		utmatra_assert_same( ListJourneys::DEFAULT_PER_PAGE, $list['per_page']['default'] );
		utmatra_assert_same( 10, ListJourneys::DEFAULT_PER_PAGE );

		$get = ( new GetJourney() )->args()['input_schema'];
		utmatra_assert_same( array( 'visitor_id' ), $get['required'] );
		utmatra_assert_same( 'uuid', $get['properties']['visitor_id']['format'] );
	},

	'list journeys output equals the service output'     => static function () {
		$GLOBALS['utmatra_test']['db_var'] = '4';
		$output                            = ( new ListJourneys() )->execute( null );

		utmatra_assert_same( JourneysService::get_journeys( array( 'per_page' => ListJourneys::DEFAULT_PER_PAGE ) ), $output );
		utmatra_assert_same( 4, $output['total'] );
		utmatra_assert_same( ListJourneys::DEFAULT_PER_PAGE, $output['per_page'] );
	},

	'list journeys keeps an explicit per page'           => static function () {
		$output = ( new ListJourneys() )->execute( array( 'per_page' => 50 ) );

		utmatra_assert_same( 50, $output['per_page'] );
	},

	'get journey returns the 404 as a wp error'          => static function () {
		$error = ( new GetJourney() )->execute( array( 'visitor_id' => '0b5f0c8e-2c1a-4e7a-9f0e-6c1f2a3b4c5d' ) );

		utmatra_assert_instance_of( WP_Error::class, $error );
		utmatra_assert_same( 'utmatra_not_found', $error->get_error_code() );
	},
);
