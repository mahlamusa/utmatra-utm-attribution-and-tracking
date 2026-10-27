<?php
/**
 * SettingsSchema — the JSON Schema of the settings abilities' output.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Abilities
 */

namespace Utmatra\Abilities;

use Utmatra\Reports\ConversionWebhook;
use Utmatra\Tracking\Attribution;

defined( 'ABSPATH' ) || exit;

/**
 * SettingsSchema
 *
 * `utmatra/get-settings` and `utmatra/update-settings` both return
 * SettingsService::read(), so they share this output schema.
 *
 * @since 1.1.0
 */
class SettingsSchema {

	/**
	 * The output schema: the current settings, webhook URL masked.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public static function output(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'retention_days'       => array(
					'type'        => 'integer',
					'description' => __( 'Days events are kept; 0 keeps them forever.', 'utmatra-utm-attribution-and-tracking' ),
				),
				'weekly_email'         => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the weekly summary email is sent.', 'utmatra-utm-attribution-and-tracking' ),
				),
				'cookie_days'          => array(
					'type'        => 'integer',
					'description' => __( 'Attribution window in days (lifetime of the attribution cookie).', 'utmatra-utm-attribution-and-tracking' ),
				),
				'webhook_url'          => array(
					'type'        => 'string',
					'description' => __( 'Host of the conversion webhook URL only, never the full URL; empty when no webhook is set or the user cannot manage settings.', 'utmatra-utm-attribution-and-tracking' ),
				),
				'webhook_preset'       => array(
					'type' => 'string',
					'enum' => ConversionWebhook::PRESETS,
				),
				'webhook_orders'       => array(
					'type'        => 'boolean',
					'description' => __( 'Whether WooCommerce orders are sent to the webhook.', 'utmatra-utm-attribution-and-tracking' ),
				),
				'webhook_leads'        => array(
					'type'        => 'boolean',
					'description' => __( 'Whether form leads are sent to the webhook.', 'utmatra-utm-attribution-and-tracking' ),
				),
				'consent_category'     => array(
					'type' => 'string',
					'enum' => Attribution::CONSENT_CATEGORIES,
				),
				'track_visits'         => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the first page of untagged visits is recorded.', 'utmatra-utm-attribution-and-tracking' ),
				),
				'custom_params'        => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => __( 'Custom URL parameter names captured alongside the UTM tags.', 'utmatra-utm-attribution-and-tracking' ),
				),
				'allow_agent_settings' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether AI agents may change settings. Only a user who can manage settings can change this, in the dashboard.', 'utmatra-utm-attribution-and-tracking' ),
				),
			),
		);
	}
}
