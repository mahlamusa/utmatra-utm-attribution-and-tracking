<?php
/**
 * LinkAbilities — schemas and plumbing shared by the link and preset abilities.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Abilities
 */

namespace Utmatra\Abilities;

use Utmatra\Capabilities;
use Utmatra\Services\LinkValidator;
use Utmatra\Services\PresetsService;
use Utmatra\Services\Support\Result;
use Utmatra\Services\Support\ResultAdapter;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * LinkAbilities
 *
 * The link and preset ability definitions in src/Abilities/Definitions/
 * build their schemas and results from here, so the six of them describe
 * links and presets the same way the utmatra/v1 REST routes return them.
 *
 * @since 1.1.0
 */
class LinkAbilities {

	/**
	 * Pattern of a link or preset id, as the REST routes accept it.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	private const ID_PATTERN = '^[a-z0-9_-]+$';

	/**
	 * Permission callback of the write abilities: manage links.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return Capabilities::can( Capabilities::MANAGE_LINKS );
	}

	/**
	 * Turn a write ability's service Result into its return value.
	 *
	 * On success fires `utmatra_ability_executed` with the input keys only,
	 * never the values.
	 *
	 * @since 1.1.0
	 *
	 * @param string               $ability Ability name.
	 * @param array<string, mixed> $input   Validated ability input.
	 * @param Result               $result  Service outcome.
	 *
	 * @return mixed|WP_Error The payload on success, a WP_Error otherwise.
	 */
	public static function written( string $ability, array $input, Result $result ) {
		if ( $result->is_ok() ) {
			/**
			 * Fires after a Utmatra write ability changed something.
			 *
			 * Utmatra keeps no log itself; hook here to record agent writes.
			 *
			 * @since 1.1.0
			 *
			 * @param string   $ability    Ability name, e.g. `utmatra/create-link`.
			 * @param int      $user_id    The user the ability ran as.
			 * @param string[] $input_keys Names of the input fields sent (no values).
			 */
			do_action( 'utmatra_ability_executed', $ability, get_current_user_id(), array_keys( $input ) );
		}

		return ResultAdapter::to_ability_result( $result );
	}

	/**
	 * Input schema of a link or preset id.
	 *
	 * @since 1.1.0
	 *
	 * @param string $description Field description.
	 *
	 * @return array<string, mixed>
	 */
	public static function id_input( string $description ): array {
		return array(
			'type'        => 'string',
			'pattern'     => self::ID_PATTERN,
			'description' => $description,
		);
	}

	/**
	 * Input schema of the `confirm` flag every delete ability requires.
	 *
	 * Only `true` validates, so a missing or false flag fails before the
	 * ability runs.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public static function confirm_input(): array {
		return array(
			'type'        => 'boolean',
			'enum'        => array( true ),
			'description' => __( 'Must be true to confirm the deletion.', 'utmatra-utm-attribution-and-tracking' ),
		);
	}

	/**
	 * Input properties shared by create-link and update-link.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function link_fields_input(): array {
		$fields = array(
			'label'       => array(
				'type'        => 'string',
				'description' => __( 'Link label; the slug is used when empty.', 'utmatra-utm-attribution-and-tracking' ),
			),
			'destination' => array(
				'type'        => 'string',
				'description' => __( 'Destination URL (http or https).', 'utmatra-utm-attribution-and-tracking' ),
			),
		);
		foreach ( LinkValidator::UTM_FIELDS as $key ) {
			$fields[ $key ] = array(
				'type'        => 'string',
				'description' => __( 'UTM value; lowercased with spaces turned into dashes.', 'utmatra-utm-attribution-and-tracking' ),
			);
		}
		$fields['custom'] = array(
			'type'                 => 'object',
			'description'          => __( 'Values for the configured custom parameters, keyed by parameter name; unknown names are dropped.', 'utmatra-utm-attribution-and-tracking' ),
			'additionalProperties' => array( 'type' => 'string' ),
		);

		return $fields;
	}

	/**
	 * Output schema of one managed link.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public static function link_output(): array {
		$properties = array(
			'id'              => array( 'type' => 'string' ),
			'label'           => array( 'type' => 'string' ),
			'destination'     => array( 'type' => 'string' ),
			'destination_raw' => array( 'type' => 'string' ),
			'redirect_url'    => array( 'type' => 'string' ),
			'clicks'          => array( 'type' => 'integer' ),
		);
		foreach ( LinkValidator::UTM_FIELDS as $key ) {
			$properties[ $key ] = array( 'type' => 'string' );
		}
		$properties['custom'] = array(
			'type'                 => 'object',
			'additionalProperties' => array( 'type' => 'string' ),
		);

		return array(
			'type'       => 'object',
			'properties' => $properties,
		);
	}

	/**
	 * Output schema of one UTM preset.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public static function preset_output(): array {
		$properties = array();
		foreach ( array_merge( array( 'id', 'name' ), PresetsService::UTM_FIELDS ) as $key ) {
			$properties[ $key ] = array( 'type' => 'string' );
		}

		return array(
			'type'       => 'object',
			'properties' => $properties,
		);
	}

	/**
	 * Output schema of a delete ability.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public static function deleted_output(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'deleted' => array( 'type' => 'boolean' ),
			),
		);
	}
}
