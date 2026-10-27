<?php
/**
 * PresetsService — saved UTM presets for the add-link form.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services
 */

namespace Utmatra\Services;

use Utmatra\Services\Support\InputValue;
use Utmatra\Services\Support\Result;
use Utmatra\Services\Support\UtmNormalizer;

defined( 'ABSPATH' ) || exit;

/**
 * PresetsService
 *
 * A preset is a reusable source/medium/term/content combination (e.g.
 * "Instagram Story") the admin can apply to the add-link form instead of
 * retyping the same values for every new campaign link. Presets are kept
 * in the `utmatra_presets` option, keyed by the slug of their name.
 *
 * @since 1.1.0
 */
class PresetsService {

	/**
	 * Option holding the presets.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	private const OPTION = 'utmatra_presets';

	/**
	 * The UTM fields a preset stores.
	 *
	 * @since 1.1.0
	 *
	 * @var string[]
	 */
	public const UTM_FIELDS = array( 'utm_source', 'utm_medium', 'utm_term', 'utm_content' );

	/**
	 * Return the saved UTM presets for the add-link form's preset dropdown.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController::presets().
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function all(): array {
		$out = array();
		foreach ( (array) get_option( self::OPTION, array() ) as $id => $preset ) {
			if ( ! is_array( $preset ) ) {
				continue;
			}
			$out[] = array(
				'id'          => (string) $id,
				'name'        => (string) ( $preset['name'] ?? $id ),
				'utm_source'  => (string) ( $preset['utm_source'] ?? '' ),
				'utm_medium'  => (string) ( $preset['utm_medium'] ?? '' ),
				'utm_term'    => (string) ( $preset['utm_term'] ?? '' ),
				'utm_content' => (string) ( $preset['utm_content'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Save a named UTM preset.
	 *
	 * Saving a name that already exists overwrites that preset — this is an
	 * explicit, low-stakes admin action, not data with history worth
	 * preserving.
	 *
	 * The id is limited to `[a-z0-9_-]` so the delete route and ability can
	 * reach it: sanitize_title() alone percent-encodes non-ASCII names. A
	 * preset stored under an older id for the same name is replaced on save.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController::create_preset(); takes an input array and returns a Result.
	 * @since 1.1.0 Restricts the id to `[a-z0-9_-]` and replaces a preset stored under an older id for the same name.
	 *
	 * @param array<string, mixed> $input Keys `name`, `utm_source`, `utm_medium`, `utm_term`, `utm_content`.
	 *
	 * @return Result The saved preset with its `id` (201), or a 400 error when the name is empty.
	 */
	public static function create( array $input ): Result {
		$name = sanitize_text_field( InputValue::string( $input, 'name' ) );
		$id   = sanitize_key( sanitize_title( $name ) );

		if ( '' === $id ) {
			return Result::error(
				'utmatra_invalid_preset_name',
				__( 'Please provide a preset name.', 'utmatra-utm-attribution-and-tracking' ),
				400
			);
		}

		$preset = array( 'name' => $name );
		foreach ( self::UTM_FIELDS as $key ) {
			$preset[ $key ] = UtmNormalizer::normalize( sanitize_text_field( InputValue::string( $input, $key ) ) );
		}

		$presets = (array) get_option( self::OPTION, array() );
		foreach ( $presets as $key => $stored ) {
			if ( (string) $key !== $id && is_array( $stored ) && sanitize_key( sanitize_title( (string) ( $stored['name'] ?? '' ) ) ) === $id ) {
				unset( $presets[ $key ] );
			}
		}
		$presets[ $id ] = $preset;
		update_option( self::OPTION, $presets );

		return Result::ok( array_merge( array( 'id' => $id ), $preset ), 201 );
	}

	/**
	 * Delete a saved UTM preset.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController::delete_preset(); takes the id and returns a Result.
	 *
	 * @param string $id Preset id.
	 *
	 * @return Result `{ deleted: true }`, or a 404 error for an unknown id.
	 */
	public static function delete( string $id ): Result {
		$presets = (array) get_option( self::OPTION, array() );

		if ( ! isset( $presets[ $id ] ) ) {
			return Result::error(
				'utmatra_preset_not_found',
				__( 'Preset not found.', 'utmatra-utm-attribution-and-tracking' ),
				404
			);
		}

		unset( $presets[ $id ] );
		update_option( self::OPTION, $presets );

		return Result::ok( array( 'deleted' => true ) );
	}
}
