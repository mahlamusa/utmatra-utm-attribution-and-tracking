<?php
/**
 * Unit tests for the utmatra/list-events and utmatra/export-events abilities.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Abilities\AbilitiesRegistrar;
use Utmatra\Abilities\Definitions\ExportEvents;
use Utmatra\Abilities\Definitions\ListEvents;
use Utmatra\Capabilities;

require_once dirname( __DIR__, 2 ) . '/src/Abilities/Definitions/ListEvents.php';
require_once dirname( __DIR__, 2 ) . '/src/Abilities/Definitions/ExportEvents.php';

return array(
	'event abilities are read only and mcp public'      => static function () {
		foreach ( array( new ListEvents(), new ExportEvents() ) as $definition ) {
			$args = AbilitiesRegistrar::ability_args( $definition );
			utmatra_assert_same( AbilitiesRegistrar::CATEGORY, $args['category'], $definition->id() );
			utmatra_assert_true( $args['meta']['show_in_rest'], $definition->id() );
			utmatra_assert_true( $args['meta']['annotations']['readonly'], $definition->id() );
			utmatra_assert_true( $args['meta']['mcp']['public'], $definition->id() );
			utmatra_assert_same( 'object', $args['input_schema']['type'], $definition->id() );
			utmatra_assert_same( 'object', $args['output_schema']['type'], $definition->id() );
		}
		utmatra_assert_same( 'utmatra/list-events', ( new ListEvents() )->id() );
		utmatra_assert_same( 'utmatra/export-events', ( new ExportEvents() )->id() );
	},

	'event abilities need the view reports capability'  => static function () {
		foreach ( array( new ListEvents(), new ExportEvents() ) as $definition ) {
			utmatra_assert_false( $definition->permission(), $definition->id() );
		}

		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::VIEW_REPORTS );
		foreach ( array( new ListEvents(), new ExportEvents() ) as $definition ) {
			utmatra_assert_true( $definition->permission(), $definition->id() );
		}
	},

	'list events schema mirrors the rest filters'       => static function () {
		$properties = ( new ListEvents() )->args()['input_schema']['properties'];
		utmatra_assert_same( array( 'click', 'visit', 'order', 'lead' ), $properties['kind']['enum'] );
		utmatra_assert_same( 'uuid', $properties['visitor_id']['format'] );
		utmatra_assert_same( 30, $properties['period']['default'] );
	},

	'list events output equals the service output'      => static function () {
		$GLOBALS['utmatra_test']['db_rows'] = array( array( 'id' => '1', 'link_id' => 'visit' ) );
		$GLOBALS['utmatra_test']['db_var']  = '1';
		$output                             = ( new ListEvents() )->execute( null );

		utmatra_assert_same( 1, $output['total'] );
		utmatra_assert_same( 'visit', $output['events'][0]['kind'] );
	},

	'export limit defaults to 500'                      => static function () {
		( new ExportEvents() )->execute( array() );
		utmatra_assert_true( false !== strpos( $GLOBALS['utmatra_test']['queries'][0], 'LIMIT 500' ) );
	},

	'export limit is clamped to 1 to 2000'              => static function () {
		foreach ( array(
			0     => 'LIMIT 1',
			-5    => 'LIMIT 1',
			1999  => 'LIMIT 1999',
			5000  => 'LIMIT 2000',
		) as $limit => $expected ) {
			$GLOBALS['utmatra_test']['queries'] = array();
			( new ExportEvents() )->execute( array( 'limit' => $limit ) );
			utmatra_assert_true( (bool) preg_match( '/' . $expected . '$/', $GLOBALS['utmatra_test']['queries'][0] ), (string) $limit );
		}
	},

	'export output is truncated with a hint'            => static function () {
		$GLOBALS['utmatra_test']['db_rows'] = array( array( 'link_id' => 'a' ) );
		$GLOBALS['utmatra_test']['db_var']  = '3';
		$output                             = ( new ExportEvents() )->execute( array( 'limit' => 1 ) );

		utmatra_assert_same( 1, $output['returned'] );
		utmatra_assert_same( 3, $output['total_rows'] );
		utmatra_assert_true( $output['truncated'] );
		utmatra_assert_true( false !== strpos( $output['hint'], 'utmatra/list-events' ) );
	},

	'export output has no hint when complete'           => static function () {
		$GLOBALS['utmatra_test']['db_rows'] = array( array( 'link_id' => 'a' ) );
		$output                             = ( new ExportEvents() )->execute( array() );

		utmatra_assert_false( $output['truncated'] );
		utmatra_assert_same( '', $output['hint'] );
		utmatra_assert_same( $GLOBALS['utmatra_test']['db_rows'], $output['rows'] );
	},

	'export include_visits sent as the string false excludes visits' => static function () {
		( new ExportEvents() )->execute( array( 'include_visits' => 'false' ) );
		utmatra_assert_true( false !== strpos( $GLOBALS['utmatra_test']['queries'][0], "link_id != 'visit'" ) );
	},

	'export include_visits sent as the string true includes visits' => static function () {
		( new ExportEvents() )->execute( array( 'include_visits' => 'true' ) );
		utmatra_assert_false( strpos( $GLOBALS['utmatra_test']['queries'][0], "link_id != 'visit'" ) );
	},
);
