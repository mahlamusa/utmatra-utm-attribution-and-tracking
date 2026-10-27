<?php
/**
 * Unit tests for SettingsService validation and the settings abilities.
 *
 * Saving needs the full plugin and is checked in a sandbox; these cover
 * validation, masking, schemas and permissions.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Abilities\Definitions\GetSettings;
use Utmatra\Abilities\Definitions\UpdateSettings;
use Utmatra\Abilities\OptIn;
use Utmatra\Capabilities;
use Utmatra\Reports\ConversionWebhook;
use Utmatra\Reports\WeeklyEmail;
use Utmatra\Services\SettingsService;

// The real weekly email schedules through Action Scheduler.
if ( ! class_exists( WeeklyEmail::class ) ) {
	require_once __DIR__ . '/fixtures/WeeklyEmailStub.php';
}

require_once dirname( __DIR__, 2 ) . '/src/Abilities/Definitions/GetSettings.php';
require_once dirname( __DIR__, 2 ) . '/src/Abilities/Definitions/UpdateSettings.php';

return array(
	'custom params are lowercased and deduplicated'         => static function () {
		$result = SettingsService::validate_custom_params( 'Coupon, aff coupon' );
		utmatra_assert_true( $result->is_ok() );
		utmatra_assert_same( array( 'coupon', 'aff' ), $result->data() );
	},

	'an empty custom param list is valid'                   => static function () {
		utmatra_assert_same( array(), SettingsService::validate_custom_params( '  ' )->data() );
	},

	'a reserved custom param is a 400 naming it'            => static function () {
		$result = SettingsService::validate_custom_params( 'coupon, utm_extra' );
		utmatra_assert_same( 400, $result->status() );
		utmatra_assert_same( 'utm_extra is reserved and cannot be used as a custom parameter.', $result->message() );

		utmatra_assert_same( 'page is reserved and cannot be used as a custom parameter.', SettingsService::validate_custom_params( 'page' )->message() );
	},

	'an invalid custom param is a 400 naming it'            => static function () {
		$result = SettingsService::validate_custom_params( '1abc' );
		utmatra_assert_same( 400, $result->status() );
		utmatra_assert_true( false !== strpos( $result->message(), ': 1abc' ) );
	},

	'more than ten custom params is a 400'                  => static function () {
		$result = SettingsService::validate_custom_params( 'xa xb xc xd xe xf xg xh xi xj xk' );
		utmatra_assert_same( 'You can define up to 10 custom parameters.', $result->message() );
	},

	'the webhook host drops the scheme, path and query'     => static function () {
		utmatra_assert_same( 'hooks.example.test', SettingsService::webhook_host( 'https://user:pass@hooks.example.test:8443/T0K3N/x?key=secret' ) );
		utmatra_assert_same( '', SettingsService::webhook_host( '' ) );
	},

	'get-settings is a public read for the view tier'       => static function () {
		$args = ( new GetSettings() )->args();
		utmatra_assert_same( 'utmatra/get-settings', ( new GetSettings() )->id() );
		utmatra_assert_true( $args['meta']['annotations']['readonly'] );
		utmatra_assert_true( $args['meta']['mcp']['public'] );
		utmatra_assert_true( $args['meta']['show_in_rest'] );

		utmatra_assert_false( $args['permission_callback']() );
		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::VIEW_REPORTS );
		utmatra_assert_true( $args['permission_callback']() );
	},

	'get-settings output never names the secret or salt'    => static function () {
		$properties = array_keys( ( new GetSettings() )->args()['output_schema']['properties'] );
		utmatra_assert_false( in_array( 'webhook_secret', $properties, true ) );
		utmatra_assert_false( in_array( 'salt', $properties, true ) );
	},

	'update-settings is a write outside MCP'                => static function () {
		$args = ( new UpdateSettings() )->args();
		utmatra_assert_same( 'utmatra/update-settings', ( new UpdateSettings() )->id() );
		utmatra_assert_false( $args['meta']['annotations']['readonly'] );
		utmatra_assert_true( $args['meta']['annotations']['destructive'] );
		utmatra_assert_false( $args['meta']['mcp']['public'] );
		utmatra_assert_true( $args['meta']['show_in_rest'] );
	},

	'update-settings rejects webhook_url and the opt-in'    => static function () {
		$schema = ( new UpdateSettings() )->args()['input_schema'];
		utmatra_assert_false( $schema['additionalProperties'] );
		utmatra_assert_false( isset( $schema['properties']['webhook_url'] ) );
		utmatra_assert_false( isset( $schema['properties']['allow_agent_settings'] ) );
	},

	'update-settings needs manage settings, then the opt-in' => static function () {
		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::VIEW_REPORTS, Capabilities::MANAGE_LINKS );
		utmatra_assert_false( UpdateSettings::permission() );

		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::MANAGE_SETTINGS );
		$error                           = UpdateSettings::permission();
		utmatra_assert_instance_of( WP_Error::class, $error );
		utmatra_assert_same( 'utmatra_agent_settings_disabled', $error->get_error_code() );
		utmatra_assert_same( array( 'status' => 403 ), $error->get_error_data() );

		$GLOBALS['utmatra_test']['options'][ OptIn::OPTION ] = 1;
		utmatra_assert_true( UpdateSettings::permission() );
	},

	'retention 1 to 29 is rejected before anything is saved' => static function () {
		foreach ( array( 1, 29 ) as $days ) {
			$error = UpdateSettings::execute( array( 'retention_days' => $days ) );
			utmatra_assert_instance_of( WP_Error::class, $error );
			utmatra_assert_same( 'utmatra_retention_too_short', $error->get_error_code() );
			utmatra_assert_same( array( 'status' => 400 ), $error->get_error_data() );
		}
		utmatra_assert_same( array(), $GLOBALS['utmatra_test']['options'] );
		utmatra_assert_same( array(), $GLOBALS['utmatra_test']['did_actions'] );
	},

	'an invalid custom param list is a 400 wp error'       => static function () {
		$error = UpdateSettings::execute( array( 'custom_params' => '1abc' ) );
		utmatra_assert_instance_of( WP_Error::class, $error );
		utmatra_assert_same( 'utmatra_invalid_custom_param', $error->get_error_code() );
		utmatra_assert_same( array(), $GLOBALS['utmatra_test']['did_actions'] );
	},

	'get-settings input schema has no empty properties key' => static function () {
		utmatra_assert_false( array_key_exists( 'properties', ( new GetSettings() )->args()['input_schema'] ) );
	},

	'update-settings saves booleans sent as strings as false' => static function () {
		$GLOBALS['utmatra_test']['options'] = array(
			WeeklyEmail::OPTION    => 1,
			'utmatra_track_visits' => 1,
		);

		UpdateSettings::execute(
			array(
				'weekly_email' => 'false',
				'track_visits' => '0',
			)
		);

		utmatra_assert_same( 0, $GLOBALS['utmatra_test']['options'][ WeeklyEmail::OPTION ] );
		utmatra_assert_same( 0, $GLOBALS['utmatra_test']['options']['utmatra_track_visits'] );
	},

	'update-settings drops the opt-in and webhook url before saving' => static function () {
		$GLOBALS['utmatra_test']['options'] = array(
			OptIn::OPTION                 => 1,
			ConversionWebhook::OPTION_URL => 'https://hooks.example.test/saved',
		);

		UpdateSettings::execute(
			array(
				'allow_agent_settings' => false,
				'webhook_url'          => 'https://attacker.example.test/',
			)
		);

		utmatra_assert_same( 1, $GLOBALS['utmatra_test']['options'][ OptIn::OPTION ] );
		utmatra_assert_same( 'https://hooks.example.test/saved', $GLOBALS['utmatra_test']['options'][ ConversionWebhook::OPTION_URL ] );
	},
);
