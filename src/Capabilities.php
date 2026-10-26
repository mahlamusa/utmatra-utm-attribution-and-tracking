<?php
/**
 * Capabilities — the three Utmatra access tiers and their hierarchy.
 *
 * Also grants the tiers to administrators on activation and upgrade, and
 * removes them from every role on uninstall.
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
	 * Option recording the capabilities version granted on this site.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	public const VERSION_OPTION = 'utmatra_capabilities_version';

	/**
	 * Current capabilities version. Bump it to re-run register() on
	 * existing sites.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	private const VERSION = '1.1.0';

	/**
	 * Grant the three tiers to the administrator role and record the version.
	 *
	 * Runs on activation and, through maybe_upgrade(), once per site on
	 * upgrade, so existing administrators keep full access.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function register(): void {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			// Nothing granted, so leave the version unset and retry next load.
			return;
		}

		foreach ( self::all() as $cap ) {
			$role->add_cap( $cap );
		}

		update_option( self::VERSION_OPTION, self::VERSION );
	}

	/**
	 * Run register() when this site has not been granted the current version.
	 *
	 * Hooked on `init`. Options are per site, so on multisite each site
	 * upgrades on its first load, including sites created later.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( version_compare( (string) get_option( self::VERSION_OPTION, '0' ), self::VERSION, '<' ) ) {
			self::register();
		}
	}

	/**
	 * Remove the three tiers from every role and delete the version option.
	 *
	 * Called from uninstall.php for each site.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function revoke(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( self::all() as $cap ) {
				if ( isset( $role->capabilities[ $cap ] ) ) {
					$role->remove_cap( $cap );
				}
			}
		}

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * The three logical capability names.
	 *
	 * @since 1.1.0
	 *
	 * @return string[]
	 */
	private static function all(): array {
		return array( self::VIEW_REPORTS, self::MANAGE_LINKS, self::MANAGE_SETTINGS );
	}

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
		return '' !== self::granting_cap( $cap );
	}

	/**
	 * The capability through which the current user holds a logical one.
	 *
	 * For an API that takes a single capability string, such as
	 * add_menu_page(), so it honours the hierarchy and the map.
	 *
	 * @since 1.1.0
	 *
	 * @param string $cap One of the class constants.
	 *
	 * @return string The checked capability the user holds, or '' when none grants $cap.
	 */
	public static function granting_cap( string $cap ): string {
		$map = self::map();
		if ( ! isset( $map[ $cap ] ) ) {
			return '';
		}

		$granting = self::VIEW_REPORTS === $cap ? array_keys( $map ) : array( $cap );

		foreach ( $granting as $logical ) {
			if ( current_user_can( $map[ $logical ] ) ) {
				return $map[ $logical ];
			}
		}

		return '';
	}
}
