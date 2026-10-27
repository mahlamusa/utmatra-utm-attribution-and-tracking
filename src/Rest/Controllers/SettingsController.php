<?php
/**
 * SettingsController — the REST adapter for POST utmatra/v1/settings.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Rest\Controllers
 */

namespace Utmatra\Rest\Controllers;

use Utmatra\Services\SettingsService;
use Utmatra\Services\Support\ResultAdapter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * SettingsController
 *
 * Passes the sent settings to SettingsService and returns its Result as
 * the REST response. The route itself is registered by RestController.
 *
 * @since 1.1.0
 */
class SettingsController {

	/**
	 * Settings the route accepts.
	 *
	 * @since 1.1.0
	 *
	 * @var string[]
	 */
	private const KEYS = array(
		'retention_days',
		'weekly_email',
		'cookie_days',
		'webhook_url',
		'webhook_preset',
		'webhook_orders',
		'webhook_leads',
		'consent_category',
		'track_visits',
		'custom_params',
		'allow_agent_settings',
	);

	/**
	 * POST utmatra/v1/settings
	 *
	 * Only the settings actually sent are changed (see SettingsService::save()).
	 * `allow_agent_settings` is saved only from the dashboard, so an agent
	 * calling the route with the user's credentials (an application
	 * password, say) cannot opt itself in.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController; delegates to SettingsService and accepts `allow_agent_settings`.
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function save_settings( WP_REST_Request $request ): WP_REST_Response {
		$params = array();
		$keys   = self::from_dashboard( $request ) ? self::KEYS : array_diff( self::KEYS, array( 'allow_agent_settings' ) );
		// Only sent params: SettingsService::save() keeps a setting whose key is
		// absent, so RequestInput::pick() (null for absent keys) would reset it.
		foreach ( $keys as $key ) {
			if ( $request->has_param( $key ) ) {
				$params[ $key ] = $request->get_param( $key );
			}
		}

		return ResultAdapter::to_rest_response( SettingsService::save( $params ) );
	}

	/**
	 * Sanitize callback for the `custom_params` route argument.
	 *
	 * Accepts the comma/space separated string the dashboard sends, or a
	 * list of names (the shape the settings are read back in), which is
	 * joined with commas. Both then go through the same sanitize_text_field()
	 * and SettingsService validation, so a list is never turned into an
	 * empty string that removes every custom parameter; only an empty list
	 * removes them all, like an empty string.
	 *
	 * @since 1.1.0 Accepts a list of names as well as the comma separated string.
	 *
	 * @param mixed $value Raw argument value.
	 *
	 * @return string|WP_Error The sanitized string, or a 400 error for a list holding anything but strings.
	 */
	public static function sanitize_custom_params( $value ) {
		if ( is_array( $value ) ) {
			if ( count( array_filter( $value, 'is_string' ) ) !== count( $value ) ) {
				return new WP_Error(
					'rest_invalid_param',
					__( 'custom_params must be a comma separated string or a list of parameter names.', 'utmatra-utm-attribution-and-tracking' ),
					array( 'status' => 400 )
				);
			}
			$value = implode( ',', $value );
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Whether the request comes from the dashboard: it carries a valid REST nonce.
	 *
	 * Only a logged-in browser session has one; application passwords and
	 * other credential-based authentication do not.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return bool
	 */
	private static function from_dashboard( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return is_string( $nonce ) && false !== wp_verify_nonce( $nonce, 'wp_rest' );
	}
}
