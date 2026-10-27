<?php
/**
 * Unit tests for the link and preset ability definitions.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Abilities\AbilitiesRegistrar;
use Utmatra\Abilities\LinkAbilities;
use Utmatra\Capabilities;
use Utmatra\Services\Support\Result;

/**
 * The link and preset abilities' registration arguments, keyed by name.
 *
 * @since 1.1.0
 *
 * @return array<string, array<string, mixed>>
 */
function utmatra_test_link_abilities(): array {
	$names = array( 'utmatra/list-links', 'utmatra/create-link', 'utmatra/update-link', 'utmatra/delete-link', 'utmatra/create-preset', 'utmatra/delete-preset' );
	$out   = array();
	foreach ( AbilitiesRegistrar::definitions() as $definition ) {
		if ( in_array( $definition->id(), $names, true ) ) {
			$out[ $definition->id() ] = AbilitiesRegistrar::ability_args( $definition );
		}
	}
	return $out;
}

return array(
	'all six abilities are defined in the utmatra category' => static function () {
		$abilities = utmatra_test_link_abilities();
		utmatra_assert_same( 6, count( $abilities ) );
		foreach ( $abilities as $name => $args ) {
			utmatra_assert_same( 'utmatra', $args['category'], $name );
			utmatra_assert_true( true === $args['meta']['show_in_rest'], $name . ' is shown in REST' );
			utmatra_assert_same( 'object', $args['input_schema']['type'] ?? null, $name . ' input schema' );
			utmatra_assert_same( 'object', $args['output_schema']['type'] ?? null, $name . ' output schema' );
		}
	},

	'only list-links is readonly and mcp public'           => static function () {
		foreach ( utmatra_test_link_abilities() as $name => $args ) {
			$read = 'utmatra/list-links' === $name;
			utmatra_assert_same( $read, $args['meta']['annotations']['readonly'], $name . ' readonly' );
			utmatra_assert_same( $read, $args['meta']['mcp']['public'], $name . ' mcp.public' );
		}
	},

	'deletes require confirm true and are destructive'     => static function () {
		$abilities = utmatra_test_link_abilities();
		foreach ( array( 'utmatra/delete-link', 'utmatra/delete-preset' ) as $name ) {
			$schema = $abilities[ $name ]['input_schema'];
			utmatra_assert_same( array( 'id', 'confirm' ), $schema['required'], $name );
			utmatra_assert_same( array( true ), $schema['properties']['confirm']['enum'], $name );
			utmatra_assert_false( $schema['additionalProperties'], $name );
			utmatra_assert_true( $abilities[ $name ]['meta']['annotations']['destructive'], $name );
		}
		utmatra_assert_true( $abilities['utmatra/update-link']['meta']['annotations']['idempotent'] );
	},

	'list needs view, writes need manage links'           => static function () {
		$abilities = utmatra_test_link_abilities();

		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::VIEW_REPORTS );
		utmatra_assert_true( call_user_func( $abilities['utmatra/list-links']['permission_callback'] ) );
		foreach ( $abilities as $name => $args ) {
			if ( 'utmatra/list-links' !== $name ) {
				utmatra_assert_false( call_user_func( $args['permission_callback'] ), $name . ' denied to view only' );
			}
		}

		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::MANAGE_LINKS );
		foreach ( $abilities as $name => $args ) {
			utmatra_assert_true( call_user_func( $args['permission_callback'] ), $name . ' allowed to manage links' );
		}

		$GLOBALS['utmatra_test']['caps'] = array();
		utmatra_assert_false( call_user_func( $abilities['utmatra/list-links']['permission_callback'] ) );
	},

	'a successful write fires the audit action with keys only' => static function () {
		$input = array(
			'id'      => 'secret-slug',
			'confirm' => true,
		);

		$out = LinkAbilities::written( 'utmatra/delete-link', $input, Result::ok( array( 'deleted' => true ) ) );

		utmatra_assert_same( array( 'deleted' => true ), $out );
		utmatra_assert_same(
			array( array( 'utmatra/delete-link', 7, array( 'id', 'confirm' ) ) ),
			$GLOBALS['utmatra_test']['did_actions']['utmatra_ability_executed'] ?? null
		);
	},

	'a failed write fires nothing and returns a wp error'  => static function () {
		$out = LinkAbilities::written( 'utmatra/delete-link', array( 'id' => 'x' ), Result::error( 'utmatra_link_not_found', 'Link not found.', 404 ) );

		utmatra_assert_instance_of( WP_Error::class, $out );
		utmatra_assert_same( 'utmatra_link_not_found', $out->get_error_code() );
		utmatra_assert_same( array( 'status' => 404 ), $out->get_error_data() );
		utmatra_assert_same( array(), $GLOBALS['utmatra_test']['did_actions'] );
	},
);
