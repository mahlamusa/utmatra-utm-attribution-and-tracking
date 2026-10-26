<?php
/**
 * Capabilities — the three Utmatra access tiers and their hierarchy.
 *
 * @since 1.1.0
 *
 * @package Utmatra
 */

namespace Utmatra;

defined( 'ABSPATH' ) || exit;

/**
 * Capabilities
 *
 * Names the three logical capabilities and answers whether the current
 * user holds one. A manage capability implies the view capability, so a
 * role granted only `utmatra_manage_links` can still read reports. Sites
 * can remap a logical capability to another one through the
 * `utmatra_capability_map` filter.
 *
 * @since 1.1.0
 */
class Capabilities {

	/**
	 * Read reports, events, journeys, exports, diagnostics and links.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	public const VIEW_REPORTS = 'utmatra_view_reports';

	/**
	 * Create, update and delete links and presets.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	public const MANAGE_LINKS = 'utmatra_manage_links';

	/**
	 * Save settings and send the test email and webhook.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	public const MANAGE_SETTINGS = 'utmatra_manage_settings';

	/**
	 * Map each logical capability to the capability actually checked.
	 *
	 * Unfiltered, every logical capability maps to itself. Filtered values
	 * that are not strings, and keys that are not one of the three tiers,
	 * are ignored.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string> Logical capability => checked capability.
	 */
	public static function map(): array {
		$map = array(
			self::VIEW_REPORTS    => self::VIEW_REPORTS,
			self::MANAGE_LINKS    => self::MANAGE_LINKS,
			self::MANAGE_SETTINGS => self::MANAGE_SETTINGS,
		);

		/**
		 * Filters the capability each logical Utmatra capability checks.
		 *
		 * Lets a site remap a tier to another capability, e.g. map
		 * `utmatra_view_reports` to `edit_posts`.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, string> $map Logical capability => checked capability.
		 */
		$filtered = apply_filters( 'utmatra_capability_map', $map );
		if ( ! is_array( $filtered ) ) {
			return $map;
		}

		// A tier can be remapped but never removed or set to a non-string.
		return array_merge( $map, array_intersect_key( array_filter( $filtered, 'is_string' ), $map ) );
	}

	/**
	 * Whether the current user holds a logical capability.
	 *
	 * Either manage capability also grants the view capability. An unknown
	 * capability name is never granted.
	 *
	 * @since 1.1.0
	 *
	 * @param string $cap One of the class constants.
	 *
	 * @return bool
	 */
	public static function can( string $cap ): bool {
		$map = self::map();
		if ( ! isset( $map[ $cap ] ) ) {
			return false;
		}

		$granting = self::VIEW_REPORTS === $cap ? array_keys( $map ) : array( $cap );

		foreach ( $granting as $logical ) {
			if ( current_user_can( $map[ $logical ] ) ) {
				return true;
			}
		}

		return false;
	}
}
