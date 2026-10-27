<?php
/**
 * Unit tests for the test email and test webhook abilities.
 *
 * Sending needs the full plugin and is checked in a sandbox; these cover
 * schemas, metadata and permissions.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Abilities\Definitions\SendTestEmail;
use Utmatra\Abilities\Definitions\SendTestWebhook;
use Utmatra\Abilities\OptIn;
use Utmatra\Capabilities;
use Utmatra\Reports\ConversionWebhook;

require_once dirname( __DIR__, 2 ) . '/src/Abilities/Definitions/SendTestEmail.php';
require_once dirname( __DIR__, 2 ) . '/src/Abilities/Definitions/SendTestWebhook.php';

return array(
	'test sends are writes outside MCP'                      => static function () {
		utmatra_assert_same( 'utmatra/send-test-email', ( new SendTestEmail() )->id() );
		utmatra_assert_same( 'utmatra/send-test-webhook', ( new SendTestWebhook() )->id() );

		foreach ( array( new SendTestEmail(), new SendTestWebhook() ) as $definition ) {
			$args = $definition->args();
			utmatra_assert_false( $args['meta']['annotations']['readonly'] );
			utmatra_assert_false( $args['meta']['mcp']['public'] );
			utmatra_assert_true( $args['meta']['show_in_rest'] );
			utmatra_assert_false( $args['input_schema']['additionalProperties'] );
			utmatra_assert_same( array( 'sent' ), array_keys( $args['output_schema']['properties'] ) );
		}
	},

	'test webhook input is the preset and leads, no URL'     => static function () {
		$schema = ( new SendTestWebhook() )->args()['input_schema'];
		utmatra_assert_same( array( 'preset', 'leads' ), array_keys( $schema['properties'] ) );
		utmatra_assert_same( ConversionWebhook::PRESETS, $schema['properties']['preset']['enum'] );
		utmatra_assert_same( 'boolean', $schema['properties']['leads']['type'] );
	},

	'test sends need manage settings, then the opt-in'       => static function () {
		foreach ( array( SendTestEmail::class, SendTestWebhook::class ) as $class ) {
			$GLOBALS['utmatra_test']['options'] = array();
			$GLOBALS['utmatra_test']['caps']    = array( Capabilities::VIEW_REPORTS, Capabilities::MANAGE_LINKS );
			utmatra_assert_false( $class::permission() );

			$GLOBALS['utmatra_test']['options'][ OptIn::OPTION ] = 1;
			utmatra_assert_false( $class::permission() );

			$GLOBALS['utmatra_test']['options'] = array();
			$GLOBALS['utmatra_test']['caps']    = array( Capabilities::MANAGE_SETTINGS );
			$error                              = $class::permission();
			utmatra_assert_instance_of( WP_Error::class, $error );
			utmatra_assert_same( 'utmatra_agent_settings_disabled', $error->get_error_code() );
			utmatra_assert_same( array( 'status' => 403 ), $error->get_error_data() );

			$GLOBALS['utmatra_test']['options'][ OptIn::OPTION ] = 1;
			utmatra_assert_true( $class::permission() );
		}
	},

	'test email input schema has no empty properties key' => static function () {
		utmatra_assert_false( array_key_exists( 'properties', ( new SendTestEmail() )->args()['input_schema'] ) );
	},

	'test webhook leads sent as the string false runs the order test' => static function () {
		$GLOBALS['utmatra_test']['options'][ ConversionWebhook::OPTION_EVENTS ] = array(
			'orders' => false,
			'leads'  => true,
		);

		utmatra_assert_same( array( 'sent' => false ), SendTestWebhook::execute( array( 'leads' => 'false' ) ) );
		utmatra_assert_same( 'Enable at least one event to send a test', $GLOBALS['utmatra_test']['options']['utmatra_webhook_last']['detail'] );
	},
);
