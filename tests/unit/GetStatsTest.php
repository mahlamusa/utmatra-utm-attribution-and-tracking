<?php
/**
 * Unit tests for the utmatra/get-stats ability definition.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Abilities\AbilitiesRegistrar;
use Utmatra\Abilities\Definitions\GetStats;
use Utmatra\Capabilities;

require_once dirname( __DIR__, 2 ) . '/src/Abilities/Definitions/GetStats.php';

return array(
	'id'                                       => static function () {
		utmatra_assert_same( 'utmatra/get-stats', ( new GetStats() )->id() );
	},

	'read-only, shown in REST and MCP-public'  => static function () {
		$meta = ( new GetStats() )->args()['meta'];
		utmatra_assert_true( $meta['annotations']['readonly'] );
		utmatra_assert_true( $meta['show_in_rest'] );
		utmatra_assert_true( $meta['mcp']['public'] );
	},

	'description flags returned data as untrusted' => static function () {
		$description = ( new GetStats() )->args()['description'];
		utmatra_assert_true( false !== strpos( $description, 'untrusted data' ), 'description warns about untrusted data' );
	},

	'input schema mirrors the REST args'       => static function () {
		$schema = ( new GetStats() )->args()['input_schema'];
		utmatra_assert_same( 'object', $schema['type'] );
		utmatra_assert_false( $schema['additionalProperties'] );
		utmatra_assert_same( array( 'period', 'campaign', 'since', 'until' ), array_keys( $schema['properties'] ) );
		utmatra_assert_same( 30, $schema['properties']['period']['default'] );
		utmatra_assert_same( 1, preg_match( '/' . $schema['properties']['since']['pattern'] . '/', '2025-03-10' ) );
		utmatra_assert_same( 1, preg_match( '/' . $schema['properties']['until']['pattern'] . '/', '' ) );
		utmatra_assert_same( 0, preg_match( '/' . $schema['properties']['since']['pattern'] . '/', 'yesterday' ) );
	},

	'output schema lists every stats key'      => static function () {
		$schema = ( new GetStats() )->args()['output_schema'];
		utmatra_assert_same(
			array( 'clicks_by_day', 'by_source', 'by_campaign', 'by_medium', 'by_device', 'by_channel', 'by_landing', 'by_platform', 'by_referrer', 'revenue_by_campaign', 'total', 'previous_total', 'previous_clicks_by_day', 'conversions', 'all_time_total', 'campaigns' ),
			array_keys( $schema['properties'] )
		);
	},

	'permission needs the view capability'     => static function () {
		$ability = new GetStats();
		utmatra_assert_false( $ability->permission() );

		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::VIEW_REPORTS );
		utmatra_assert_true( $ability->permission() );
	},

	'execute returns every output schema key'  => static function () {
		$ability = new GetStats();
		$out     = $ability->execute( array() );
		utmatra_assert_same( array_keys( $ability->args()['output_schema']['properties'] ), array_keys( $out ) );
		utmatra_assert_same( 31, count( $out['clicks_by_day'] ), 'default period of 30 days' );
	},

	'execute maps period and campaign'         => static function () {
		$out = ( new GetStats() )->execute(
			array(
				'period'   => 7,
				'campaign' => 'spring-sale',
			)
		);
		utmatra_assert_same( 8, count( $out['clicks_by_day'] ) );
		utmatra_assert_true( false !== strpos( implode( ' ', $GLOBALS['utmatra_test']['queries'] ), "utm_campaign = 'spring-sale'" ), 'campaign filter reaches the queries' );
	},

	'execute maps since and until in order'    => static function () {
		$out = ( new GetStats() )->execute(
			array(
				'since' => '2025-03-01',
				'until' => '2025-03-10',
			)
		);
		utmatra_assert_same( '2025-03-01', $out['clicks_by_day'][0]['date'] );
		utmatra_assert_same( '2025-03-10', end( $out['clicks_by_day'] )['date'] );
	},

	'the registrar discovers it in the utmatra category' => static function () {
		$found = null;
		foreach ( AbilitiesRegistrar::definitions() as $definition ) {
			if ( $definition instanceof GetStats ) {
				$found = $definition;
			}
		}
		utmatra_assert_instance_of( GetStats::class, $found );
		utmatra_assert_same( AbilitiesRegistrar::CATEGORY, AbilitiesRegistrar::ability_args( $found )['category'] );
	},
);
