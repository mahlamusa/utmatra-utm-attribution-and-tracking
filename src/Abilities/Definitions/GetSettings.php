<?php
/**
 * GetSettings — the `utmatra/get-settings` ability.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Abilities\Definitions
 */

namespace Utmatra\Abilities\Definitions;

use Utmatra\Abilities\AbilityDefinition;
use Utmatra\Abilities\SettingsSchema;
use Utmatra\Capabilities;
use Utmatra\Services\SettingsService;

defined( 'ABSPATH' ) || exit;

/**
 * GetSettings
 *
 * Reads the current settings so an agent can check them before writing.
 * The webhook URL is reduced to its host; the webhook secret and the salt
 * are never returned.
 *
 * @since 1.1.0
 */
class GetSettings implements AbilityDefinition {

	/**
	 * The ability name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'utmatra/get-settings';
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
			'label'               => __( 'Get Utmatra settings', 'utmatra-utm-attribution-and-tracking' ),
			'description'         => __( 'Returns the Utmatra settings: event retention, weekly email, attribution window, webhook preset and events, consent category, untagged visit tracking, custom parameters and whether AI agents may change settings. The webhook URL is reduced to its host.', 'utmatra-utm-attribution-and-tracking' ),
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => SettingsSchema::output(),
			'execute_callback'    => array( self::class, 'execute' ),
			'permission_callback' => static function () {
				return Capabilities::can( Capabilities::VIEW_REPORTS );
			},
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'mcp'          => array( 'public' => true ),
			),
		);
	}

	/**
	 * Execute callback: the current settings.
	 *
	 * Users who cannot manage settings get an empty webhook URL, as on the
	 * dashboard, because some receivers put their token in the host.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public static function execute(): array {
		$settings = SettingsService::read();
		if ( ! Capabilities::can( Capabilities::MANAGE_SETTINGS ) ) {
			$settings['webhook_url'] = '';
		}

		return $settings;
	}
}
