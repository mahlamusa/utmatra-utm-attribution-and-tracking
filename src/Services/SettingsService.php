<?php
/**
 * SettingsService — reads and saves the plugin settings.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services
 */

namespace Utmatra\Services;

use Utmatra\Abilities\OptIn;
use Utmatra\Reports\ConversionWebhook;
use Utmatra\Reports\WeeklyEmail;
use Utmatra\Services\Support\InputValue;
use Utmatra\Services\Support\Result;
use Utmatra\Tracking\Attribution;
use Utmatra\Tracking\Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * SettingsService
 *
 * Holds the settings logic shared by the REST settings route and the
 * settings abilities: the current values, the partial save and the
 * custom parameter validation. Takes plain arrays, never request objects.
 *
 * @since 1.1.0
 */
class SettingsService {

	/**
	 * The current settings, safe to hand to an AI agent.
	 *
	 * Same keys as the save response, plus whether agents may change
	 * settings. The webhook URL is reduced to its host because its path can
	 * embed a token; the webhook secret and the salt are never included.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public static function read(): array {
		$events = ConversionWebhook::events();

		return array(
			'retention_days'       => (int) get_option( 'utmatra_retention_days', 180 ),
			'weekly_email'         => WeeklyEmail::enabled(),
			'cookie_days'          => (int) get_option( 'utmatra_cookie_days', 90 ),
			'webhook_url'          => self::webhook_host( ConversionWebhook::url() ),
			'webhook_preset'       => ConversionWebhook::preset(),
			'webhook_orders'       => (bool) $events['orders'],
			'webhook_leads'        => (bool) $events['leads'],
			'consent_category'     => Attribution::consent_category(),
			'track_visits'         => 1 === (int) get_option( 'utmatra_track_visits', 1 ),
			'custom_params'        => Tracker::custom_params(),
			'allow_agent_settings' => OptIn::allowed(),
		);
	}

	/**
	 * The host of a webhook URL, or '' when there is none.
	 *
	 * @since 1.1.0
	 *
	 * @param string $url Webhook URL.
	 *
	 * @return string
	 */
	public static function webhook_host( string $url ): string {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) ? $host : '';
	}

	/**
	 * Save the settings present in `$params`.
	 *
	 * Saves the retention window (0 = keep forever), the weekly email
	 * toggle, the webhook settings (URL — empty disables it; preset; the
	 * order/lead event toggles), the consent category that gates the
	 * attribution cookie (allow-listed, anything else falls back to
	 * marketing), the untagged-visit tracking switch, the custom parameter
	 * names (comma/space separated here; stored as a validated array) and,
	 * when sent, the AI agent opt-in. The custom names are validated before
	 * any option is written, so a rejected list leaves every setting at its
	 * previous value. The weekly report job is scheduled or unscheduled
	 * here, at save time; Scheduler::heal() only recreates it if it goes
	 * missing.
	 *
	 * Only the settings actually sent are changed: a key missing from
	 * `$params` keeps its stored value, so a partial call (e.g. only
	 * `weekly_email`) never resets the others to defaults.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Sent setting name => raw value.
	 *
	 * @return Result The saved settings (without the opt-in), or a 400 naming a rejected custom parameter.
	 */
	public static function save( array $params ): Result {
		$custom_names = Tracker::custom_params();
		if ( array_key_exists( 'custom_params', $params ) ) {
			$validated = self::validate_custom_params( InputValue::string( $params, 'custom_params' ) );
			if ( $validated->is_error() ) {
				return $validated;
			}
			$custom_names = $validated->data();
		}

		$events    = ConversionWebhook::events();
		$retention = array_key_exists( 'retention_days', $params ) ? absint( $params['retention_days'] ) : (int) get_option( 'utmatra_retention_days', 180 );
		$weekly    = array_key_exists( 'weekly_email', $params ) ? (bool) $params['weekly_email'] : WeeklyEmail::enabled();
		$cookie    = array_key_exists( 'cookie_days', $params ) ? absint( $params['cookie_days'] ) : (int) get_option( 'utmatra_cookie_days', 90 );
		$cookie    = min( 730, max( 1, $cookie ? $cookie : 90 ) );
		$webhook   = array_key_exists( 'webhook_url', $params ) ? esc_url_raw( InputValue::string( $params, 'webhook_url' ), array( 'http', 'https' ) ) : ConversionWebhook::url();
		$preset    = array_key_exists( 'webhook_preset', $params ) ? InputValue::string( $params, 'webhook_preset' ) : ConversionWebhook::preset();
		if ( ! in_array( $preset, ConversionWebhook::PRESETS, true ) ) {
			$preset = 'generic';
		}
		$orders  = array_key_exists( 'webhook_orders', $params ) ? (bool) $params['webhook_orders'] : (bool) $events['orders'];
		$leads   = array_key_exists( 'webhook_leads', $params ) ? (bool) $params['webhook_leads'] : (bool) $events['leads'];
		$consent = array_key_exists( 'consent_category', $params ) ? InputValue::string( $params, 'consent_category' ) : Attribution::consent_category();
		if ( ! in_array( $consent, Attribution::CONSENT_CATEGORIES, true ) ) {
			$consent = 'marketing';
		}
		$visits = array_key_exists( 'track_visits', $params ) ? (bool) $params['track_visits'] : 1 === (int) get_option( 'utmatra_track_visits', 1 );

		update_option( 'utmatra_retention_days', $retention );
		update_option( WeeklyEmail::OPTION, $weekly ? 1 : 0 );
		update_option( 'utmatra_cookie_days', $cookie );
		update_option( ConversionWebhook::OPTION_URL, $webhook );
		update_option( ConversionWebhook::OPTION_PRESET, $preset );
		update_option(
			ConversionWebhook::OPTION_EVENTS,
			array(
				'orders' => $orders,
				'leads'  => $leads,
			)
		);
		update_option( Attribution::OPTION_CONSENT_CATEGORY, $consent );
		update_option( 'utmatra_track_visits', $visits ? 1 : 0 );
		update_option( 'utmatra_custom_params', $custom_names );
		if ( array_key_exists( 'allow_agent_settings', $params ) ) {
			update_option( OptIn::OPTION, $params['allow_agent_settings'] ? 1 : 0 );
		}

		if ( $weekly ) {
			WeeklyEmail::schedule();
		} else {
			WeeklyEmail::unschedule();
		}

		return Result::ok(
			array(
				'retention_days'   => $retention,
				'weekly_email'     => $weekly,
				'cookie_days'      => $cookie,
				'webhook_url'      => $webhook,
				'webhook_preset'   => $preset,
				'webhook_orders'   => $orders,
				'webhook_leads'    => $leads,
				'consent_category' => $consent,
				'track_visits'     => $visits,
				'custom_params'    => $custom_names,
			)
		);
	}

	/**
	 * Parse and validate the custom parameter names from the settings form.
	 *
	 * The raw input is a comma/space separated list; input is lowercased and
	 * trimmed, duplicates collapse silently. Every name must pass
	 * Tracker::is_valid_custom_param() with the query-var check on (save
	 * time), and a list longer than Tracker::MAX_CUSTOM_PARAMS is
	 * rejected. The first rejected name is named in the error message; an
	 * empty input saves an empty list (all custom parameters removed).
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController; returns a Result.
	 *
	 * @param string $raw Comma/space separated names, e.g. "Coupon, aff, promo2".
	 *
	 * @return Result The validated names, or a 400 naming the rejected entry.
	 */
	public static function validate_custom_params( string $raw ): Result {
		$names = preg_split( '/[\s,]+/', strtolower( trim( $raw ) ), -1, PREG_SPLIT_NO_EMPTY );
		$names = array_values( array_unique( is_array( $names ) ? $names : array() ) );

		foreach ( $names as $name ) {
			if ( Tracker::is_valid_custom_param( $name, true ) ) {
				continue;
			}

			$reserved = in_array( $name, Tracker::reserved_param_names( true ), true )
				|| str_starts_with( $name, 'utm_' )
				|| str_starts_with( $name, 'utmatra_' )
				|| str_starts_with( $name, 'first_' )
				|| str_ends_with( $name, '_first' );

			return Result::error(
				'utmatra_invalid_custom_param',
				$reserved
					? sprintf(
						/* translators: %s: the rejected custom parameter name. */
						__( '%s is reserved and cannot be used as a custom parameter.', 'utmatra-utm-attribution-and-tracking' ),
						$name
					)
					: sprintf(
						/* translators: %s: the rejected custom parameter name. */
						__( 'Custom parameter names must start with a letter and may only use lowercase letters, digits and underscores, up to 32 characters: %s', 'utmatra-utm-attribution-and-tracking' ),
						$name
					)
			);
		}

		if ( count( $names ) > Tracker::MAX_CUSTOM_PARAMS ) {
			return Result::error(
				'utmatra_too_many_custom_params',
				sprintf(
					/* translators: %d: maximum number of custom parameters. */
					__( 'You can define up to %d custom parameters.', 'utmatra-utm-attribution-and-tracking' ),
					Tracker::MAX_CUSTOM_PARAMS
				)
			);
		}

		return Result::ok( $names );
	}
}
