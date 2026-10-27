<?php
/**
 * SendTestWebhook — the `utmatra/send-test-webhook` ability.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Abilities\Definitions
 */

namespace Utmatra\Abilities\Definitions;

use Utmatra\Abilities\AbilityDefinition;
use Utmatra\Abilities\OptIn;
use Utmatra\Capabilities;
use Utmatra\Reports\ConversionWebhook;
use Utmatra\Services\NotificationsService;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * SendTestWebhook
 *
 * Sends a test payload to the configured webhook URL now. Needs the manage
 * settings capability and the site owner's opt-in. The URL itself cannot
 * be set here, only the saved one is used.
 *
 * @since 1.1.0
 */
class SendTestWebhook implements AbilityDefinition {

	/**
	 * The ability name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'utmatra/send-test-webhook';
	}

	/**
	 * The wp_register_ability() arguments.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function args(): array {
		return array(
			'label'               => __( 'Send a test Utmatra webhook', 'utmatra-utm-attribution-and-tracking' ),
			'description'         => __( 'Sends a test payload to the saved conversion webhook URL now. preset and leads override the saved webhook preset and form lead toggle for this test only. Requires an administrator to allow AI agents to change settings. Returns sent: false when no webhook URL is saved, no event is enabled or the receiver did not accept the payload.', 'utmatra-utm-attribution-and-tracking' ),
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'preset' => array(
						'type'        => 'string',
						'enum'        => ConversionWebhook::PRESETS,
						'description' => __( 'Payload format to test. Defaults to the saved preset.', 'utmatra-utm-attribution-and-tracking' ),
					),
					'leads'  => array(
						'type'        => 'boolean',
						'description' => __( 'Send the form lead test (true) or the order test (false). Defaults to the saved form lead toggle.', 'utmatra-utm-attribution-and-tracking' ),
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'sent' => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the receiver accepted the payload.', 'utmatra-utm-attribution-and-tracking' ),
					),
				),
			),
			'execute_callback'    => array( self::class, 'execute' ),
			'permission_callback' => array( self::class, 'permission' ),
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
				'mcp'          => array( 'public' => false ),
			),
		);
	}

	/**
	 * Permission callback: manage settings plus the site owner's opt-in.
	 *
	 * @since 1.1.0
	 *
	 * @return bool|WP_Error False without the capability, the opt-in's 403 when it is off.
	 */
	public static function permission() {
		if ( ! Capabilities::can( Capabilities::MANAGE_SETTINGS ) ) {
			return false;
		}

		return OptIn::permission();
	}

	/**
	 * Execute callback: send the test payload, return whether it was sent.
	 *
	 * Fires `utmatra_ability_executed` with the input keys (never values)
	 * when the payload was sent.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $input Validated input.
	 *
	 * @return array{sent: bool}
	 */
	public static function execute( $input = array() ): array {
		$input  = (array) $input;
		$result = NotificationsService::send_test_webhook(
			isset( $input['preset'] ) ? (string) $input['preset'] : '',
			isset( $input['leads'] ) ? (bool) $input['leads'] : null
		);

		if ( $result['sent'] ) {
			/** This action is documented in src/Abilities/Definitions/UpdateSettings.php */
			do_action( 'utmatra_ability_executed', 'utmatra/send-test-webhook', get_current_user_id(), array_keys( $input ) );
		}

		return $result;
	}
}
