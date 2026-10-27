<?php
/**
 * UpdateSettings — the `utmatra/update-settings` ability.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Abilities\Definitions
 */

namespace Utmatra\Abilities\Definitions;

use Utmatra\Abilities\AbilityDefinition;
use Utmatra\Abilities\OptIn;
use Utmatra\Abilities\SettingsSchema;
use Utmatra\Capabilities;
use Utmatra\Reports\ConversionWebhook;
use Utmatra\Services\SettingsService;
use Utmatra\Services\Support\ResultAdapter;
use Utmatra\Tracking\Attribution;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * UpdateSettings
 *
 * Saves the settings sent, keeping the others. Needs the manage settings
 * capability and the site owner's opt-in. The webhook URL and the opt-in
 * itself are not writable here, and the retention window cannot be set
 * below 30 days (0, keep forever, is allowed).
 *
 * @since 1.1.0
 */
class UpdateSettings implements AbilityDefinition {

	/**
	 * Shortest retention window, in days, an agent may set (other than 0).
	 *
	 * @since 1.1.0
	 *
	 * @var int
	 */
	private const MIN_RETENTION_DAYS = 30;

	/**
	 * The ability name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'utmatra/update-settings';
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
			'label'               => __( 'Update Utmatra settings', 'utmatra-utm-attribution-and-tracking' ),
			'description'         => __( 'Changes the Utmatra settings sent and keeps the others. Requires an administrator to allow AI agents to change settings. The webhook URL cannot be changed here. retention_days is 0 (keep forever) or 30 to 3650; events older than the window are deleted permanently.', 'utmatra-utm-attribution-and-tracking' ),
			'input_schema'        => self::input_schema(),
			'output_schema'       => SettingsSchema::output(),
			'execute_callback'    => array( self::class, 'execute' ),
			'permission_callback' => array( self::class, 'permission' ),
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
				'mcp'          => array( 'public' => false ),
			),
		);
	}

	/**
	 * The input schema: every writable setting, nothing else.
	 *
	 * `webhook_url` and `allow_agent_settings` are left out on purpose and
	 * `additionalProperties: false` rejects them.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	private static function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'retention_days'   => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'maximum'     => 3650,
					'description' => __( 'Days to keep events: 0 keeps them forever, otherwise 30 to 3650.', 'utmatra-utm-attribution-and-tracking' ),
				),
				'weekly_email'     => array(
					'type' => 'boolean',
				),
				'cookie_days'      => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 730,
					'description' => __( 'Attribution window in days.', 'utmatra-utm-attribution-and-tracking' ),
				),
				'webhook_preset'   => array(
					'type' => 'string',
					'enum' => ConversionWebhook::PRESETS,
				),
				'webhook_orders'   => array(
					'type' => 'boolean',
				),
				'webhook_leads'    => array(
					'type' => 'boolean',
				),
				'consent_category' => array(
					'type' => 'string',
					'enum' => Attribution::CONSENT_CATEGORIES,
				),
				'track_visits'     => array(
					'type' => 'boolean',
				),
				'custom_params'    => array(
					'type'        => 'string',
					'description' => __( 'Comma separated custom parameter names (up to 10; lowercase letters, digits and underscores, starting with a letter). Replaces the current list; an empty string removes them all.', 'utmatra-utm-attribution-and-tracking' ),
				),
			),
			'additionalProperties' => false,
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
	 * Execute callback: apply the retention floor, save, return the settings.
	 *
	 * Fires `utmatra_ability_executed` with the input keys (never values)
	 * after a successful save.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $input Validated input.
	 *
	 * @return array<string, mixed>|WP_Error The settings after the save, or the validation error.
	 */
	public static function execute( $input = array() ) {
		// Core validates against the schema first; this keeps the guard
		// even if the input was changed after validation.
		$input = array_intersect_key( (array) $input, self::input_schema()['properties'] );

		if ( isset( $input['retention_days'] ) && $input['retention_days'] > 0 && $input['retention_days'] < self::MIN_RETENTION_DAYS ) {
			return new WP_Error(
				'utmatra_retention_too_short',
				sprintf(
					/* translators: %d: shortest retention window in days. */
					__( 'retention_days must be 0 (keep forever) or at least %d days.', 'utmatra-utm-attribution-and-tracking' ),
					self::MIN_RETENTION_DAYS
				),
				array( 'status' => 400 )
			);
		}

		// Same sanitising as the REST route's argument.
		if ( isset( $input['custom_params'] ) ) {
			$input['custom_params'] = sanitize_text_field( $input['custom_params'] );
		}

		$result = SettingsService::save( $input );
		if ( $result->is_error() ) {
			return ResultAdapter::to_wp_error( $result );
		}

		/**
		 * Fires after an Utmatra write ability ran successfully.
		 *
		 * @since 1.1.0
		 *
		 * @param string   $ability_id Ability name.
		 * @param int      $user_id    Current user ID.
		 * @param string[] $input_keys Names of the input fields sent (no values).
		 */
		do_action( 'utmatra_ability_executed', 'utmatra/update-settings', get_current_user_id(), array_keys( $input ) );

		return SettingsService::read();
	}
}
