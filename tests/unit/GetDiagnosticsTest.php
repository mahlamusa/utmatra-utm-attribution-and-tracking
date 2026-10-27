<?php
/**
 * Unit tests for the utmatra/get-diagnostics ability and DiagnosticsService.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Abilities\AbilitiesRegistrar;
use Utmatra\Abilities\Definitions\GetDiagnostics;
use Utmatra\Capabilities;
use Utmatra\Diagnostics\Checks;
use Utmatra\Rest\Controllers\DiagnosticsController;
use Utmatra\Services\DiagnosticsService;

// The real checks read state the unit bootstrap does not stub.
if ( ! class_exists( Checks::class ) ) {
	require_once __DIR__ . '/fixtures/ChecksStub.php';
}
require_once dirname( __DIR__, 2 ) . '/src/Abilities/Definitions/GetDiagnostics.php';

return array(
	'id'                                           => static function () {
		utmatra_assert_same( 'utmatra/get-diagnostics', ( new GetDiagnostics() )->id() );
	},

	'read-only, shown in REST and MCP-public'      => static function () {
		$meta = ( new GetDiagnostics() )->args()['meta'];
		utmatra_assert_true( $meta['annotations']['readonly'] );
		utmatra_assert_true( $meta['show_in_rest'] );
		utmatra_assert_true( $meta['mcp']['public'] );
	},

	'description flags returned data as untrusted' => static function () {
		$description = ( new GetDiagnostics() )->args()['description'];
		utmatra_assert_true( false !== strpos( $description, 'untrusted data' ), 'description warns about untrusted data' );
	},

	'input schema takes no properties'             => static function () {
		$schema = ( new GetDiagnostics() )->args()['input_schema'];
		utmatra_assert_same( 'object', $schema['type'] );
		utmatra_assert_false( $schema['additionalProperties'] );
		utmatra_assert_false( array_key_exists( 'properties', $schema ) );
	},

	'output schema describes every check field'    => static function () {
		$schema = ( new GetDiagnostics() )->args()['output_schema'];
		utmatra_assert_same( array( 'checks' ), array_keys( $schema['properties'] ) );
		utmatra_assert_same(
			array( 'id', 'label', 'status', 'message', 'details' ),
			array_keys( $schema['properties']['checks']['items']['properties'] )
		);
	},

	'permission needs the view capability'         => static function () {
		$ability = new GetDiagnostics();
		utmatra_assert_false( $ability->permission() );

		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::VIEW_REPORTS );
		utmatra_assert_true( $ability->permission() );
	},

	'service wraps the checks under a checks key'  => static function () {
		$result = DiagnosticsService::run();
		utmatra_assert_same( array( 'checks' ), array_keys( $result ) );
		utmatra_assert_same( 'caching', $result['checks'][0]['id'] );
	},

	'execute and REST return the same body'        => static function () {
		$response = DiagnosticsController::get_diagnostics();
		utmatra_assert_same( 200, $response->get_status() );
		utmatra_assert_same( $response->get_data(), GetDiagnostics::execute() );
	},

	'the registrar discovers it in the utmatra category' => static function () {
		$found = null;
		foreach ( AbilitiesRegistrar::definitions() as $definition ) {
			if ( $definition instanceof GetDiagnostics ) {
				$found = $definition;
			}
		}
		utmatra_assert_instance_of( GetDiagnostics::class, $found );
		utmatra_assert_same( AbilitiesRegistrar::CATEGORY, AbilitiesRegistrar::ability_args( $found )['category'] );
	},
);
