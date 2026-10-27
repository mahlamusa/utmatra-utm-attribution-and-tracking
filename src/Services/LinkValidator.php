<?php
/**
 * LinkValidator — slug rules and input validation for managed links.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services
 */

namespace Utmatra\Services;

use Utmatra\Services\Support\InputValue;
use Utmatra\Services\Support\Result;
use Utmatra\Services\Support\UtmNormalizer;
use Utmatra\Tracking\LinkManager;
use Utmatra\Tracking\Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * LinkValidator
 *
 * Turns untrusted link input into a slug and the stored link fields, or a
 * 400 Result. Used by LinksService; moved from RestController.
 *
 * @since 1.1.0
 * @since 1.1.0 Rejects the slug 0, which the redirect can never resolve.
 */
class LinkValidator {

	/**
	 * Slugs that name event kinds in the events table and cannot be links.
	 *
	 * @since 1.1.0
	 *
	 * @var string[]
	 */
	private const RESERVED_SLUGS = array( 'incoming', 'visit', 'order', 'lead' );

	/**
	 * The UTM fields a link stores.
	 *
	 * @since 1.1.0
	 *
	 * @var string[]
	 */
	public const UTM_FIELDS = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_term', 'utm_content' );

	/**
	 * The slug for a new link.
	 *
	 * Derived from the label when no explicit `id` is given. Fails when the
	 * slug is empty, `0` (the redirect handler treats it as no slug, so the
	 * link would never redirect or record a click) or reserved (`incoming`,
	 * `visit`, `order` and `lead` name event kinds in the events table).
	 * Whether the slug is free is the caller's check.
	 *
	 * @since 1.0.0
	 * @since 1.0.0 Caps the slug at 64 characters, the click column width,
	 *              suffixing a derived slug that collides with a different
	 *              label after the cut.
	 * @since 1.1.0 Moved from RestController::create_link().
	 * @since 1.1.0 Rejects the slug 0.
	 *
	 * @param array<string, mixed> $input Link input with `id` and `label`.
	 *
	 * @return string|Result The slug, or a 400 `utmatra_invalid_slug` error.
	 */
	public static function slug( array $input ) {
		$label    = sanitize_text_field( InputValue::string( $input, 'label' ) );
		$id_param = InputValue::string( $input, 'id' );
		$id       = sanitize_key( '' !== $id_param ? $id_param : sanitize_title( $label ) );
		// Clicks store link_id in a VARCHAR(64) column, so a longer slug would
		// redirect but never match its own click rows. Cap it here, before the
		// reserved and duplicate checks.
		if ( strlen( $id ) > 64 ) {
			$id = rtrim( substr( $id, 0, 64 ), '-_' );
			// Two different long labels can share their first 64 characters
			// (a duplicated link adds " (copy)" at the end), so a derived slug
			// that collides with another label after the cut gets a numeric
			// suffix. The same label again still fails as a duplicate.
			$existing = '' === $id_param ? LinkManager::get( $id ) : null;
			if ( null !== $existing && ( $existing['label'] ?? '' ) !== $label ) {
				$base = rtrim( substr( $id, 0, 60 ), '-_' );
				for ( $n = 2; $n < 100; $n++ ) {
					$id    = $base . '-' . $n;
					$taken = LinkManager::get( $id );
					// A slug already holding this label falls through to the
					// duplicate error.
					if ( null === $taken || ( $taken['label'] ?? '' ) === $label ) {
						break;
					}
				}
			}
		}

		if ( '' === $id || '0' === $id || in_array( $id, self::RESERVED_SLUGS, true ) ) {
			return Result::error(
				'utmatra_invalid_slug',
				__( 'Please provide a valid link slug (incoming, visit, order and lead are reserved).', 'utmatra-utm-attribution-and-tracking' ),
				400
			);
		}

		return $id;
	}

	/**
	 * Validate and sanitize the link fields shared by create and update.
	 *
	 * Sanitizes the label and the six UTM fields and validates the
	 * destination as an http(s) URL. UTM values are normalized for
	 * consistent reporting (lowercase, whitespace collapsed to `-`) — the
	 * dashboard JS applies the same normalization for immediate feedback,
	 * but this server-side pass is authoritative. Custom parameter values
	 * from the `custom` object are kept only for currently configured
	 * names, sanitized and capped at 255 characters but deliberately not
	 * normalized (coupon and affiliate codes are often case-sensitive);
	 * unknown keys are dropped silently.
	 *
	 * @since 1.0.0
	 * @since 1.0.0 Truncates by character, not byte.
	 * @since 1.1.0 Moved from RestController::validate_link_input(); takes an input array and returns a Result error.
	 *
	 * @param array<string, mixed> $input Link input.
	 *
	 * @return array<string, mixed>|Result Sanitized link fields, or a 400 error when the destination is invalid.
	 */
	public static function fields( array $input ) {
		$destination = esc_url_raw( InputValue::string( $input, 'destination' ), array( 'http', 'https' ) );

		if ( '' === $destination ) {
			return Result::error(
				'utmatra_invalid_destination',
				__( 'Please provide a valid destination URL (http or https).', 'utmatra-utm-attribution-and-tracking' ),
				400
			);
		}

		$link = array(
			'label'       => sanitize_text_field( InputValue::string( $input, 'label' ) ),
			'destination' => $destination,
		);
		foreach ( self::UTM_FIELDS as $key ) {
			$link[ $key ] = UtmNormalizer::normalize( sanitize_text_field( InputValue::string( $input, $key ) ) );
		}

		$custom_in = $input['custom'] ?? null;
		if ( is_array( $custom_in ) ) {
			$custom = array();
			foreach ( Tracker::custom_params() as $name ) {
				$value = $custom_in[ $name ] ?? '';
				if ( is_scalar( $value ) ) {
					$value = mb_substr( sanitize_text_field( (string) $value ), 0, 255 );
					if ( '' !== $value ) {
						$custom[ $name ] = $value;
					}
				}
			}
			if ( array() !== $custom ) {
				$link['custom'] = $custom;
			}
		}

		return $link;
	}
}
