<?php
/**
 * SendTestEmail — the `utmatra/send-test-email` ability.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Abilities\Definitions
 */

namespace Utmatra\Abilities\Definitions;

use Utmatra\Abilities\AbilityDefinition;
use Utmatra\Abilities\OptIn;
use Utmatra\Capabilities;
use Utmatra\Services\NotificationsService;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * SendTestEmail
 *
 * Sends the weekly report email now. Needs the manage settings capability
 * and the site owner's opt-in.
 *
 * @since 1.1.0
 */
class SendTestEmail implements AbilityDefinition {

	/**
	 * The ability name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'utmatra/send-test-email';
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
			'label'               => __( 'Send a test Utmatra report email', 'utmatra-utm-attribution-and-tracking' ),
			'description'         => __( 'Sends the weekly UTM campaign report email to the report recipient now, even when the weekly email is off. Requires an administrator to allow AI agents to change settings. Returns sent: false when the email could not be sent.', 'utmatra-utm-attribution-and-tracking' ),
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'sent' => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the email was handed to the mailer.', 'utmatra-utm-attribution-and-tracking' ),
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
	 * Execute callback: send the email, return whether it was sent.
	 *
	 * Fires `utmatra_ability_executed` after the send, whether or not the
	 * mailer accepted the email.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $input Validated input (no fields).
	 *
	 * @return array{sent: bool}
	 */
	public static function execute( $input = array() ): array {
		$result = NotificationsService::send_test_email();

		/** This action is documented in src/Abilities/Definitions/UpdateSettings.php */
		do_action( 'utmatra_ability_executed', 'utmatra/send-test-email', get_current_user_id(), array_keys( (array) $input ) );

		return $result;
	}
}
