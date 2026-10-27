<?php
/**
 * OptIn — the site owner's switch for agent settings writes.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Abilities
 */

namespace Utmatra\Abilities;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * OptIn
 *
 * The settings-tier abilities (update settings, test email, test webhook)
 * stay unavailable until the site owner allows AI agents to change
 * settings. Off by default.
 *
 * @since 1.1.0
 */
class OptIn {

	/**
	 * Option holding the opt-in (1 allowed, 0 not).
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	public const OPTION = 'utmatra_allow_agent_settings';

	/**
	 * Whether AI agents may change settings.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public static function allowed(): bool {
		/**
		 * Filters whether the settings-tier abilities may run.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $allowed Whether the `utmatra_allow_agent_settings` option is on.
		 */
		return (bool) apply_filters( 'utmatra_abilities_allow_settings_writes', 1 === (int) get_option( self::OPTION, 0 ) );
	}

	/**
	 * The opt-in part of a settings-tier ability's permission callback.
	 *
	 * Checks the opt-in only. Callers must also require
	 * `Capabilities::can( Capabilities::MANAGE_SETTINGS )`; never use this
	 * alone as a permission callback.
	 *
	 * @since 1.1.0
	 *
	 * @return true|WP_Error True when allowed, a 403 error naming where to enable it otherwise.
	 */
	public static function permission() {
		if ( self::allowed() ) {
			return true;
		}

		return new WP_Error(
			'utmatra_agent_settings_disabled',
			__( 'AI agents are not allowed to change Utmatra settings. A user who can manage Utmatra settings can turn on "Allow AI agents to change settings" in the Settings card of the Utmatra dashboard.', 'utmatra-utm-attribution-and-tracking' ),
			array( 'status' => 403 )
		);
	}
}
