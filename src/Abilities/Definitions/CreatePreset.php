<?php
/**
 * CreatePreset — the `utmatra/create-preset` ability.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Abilities\Definitions
 */

namespace Utmatra\Abilities\Definitions;

use Utmatra\Abilities\AbilityDefinition;
use Utmatra\Abilities\LinkAbilities;
use Utmatra\Services\PresetsService;

defined( 'ABSPATH' ) || exit;

/**
 * CreatePreset
 *
 * Mirrors POST utmatra/v1/presets.
 *
 * @since 1.1.0
 */
class CreatePreset implements AbilityDefinition {

	/**
	 * The ability name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'utmatra/create-preset';
	}

	/**
	 * The wp_register_ability() arguments.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function args(): array {
		$properties = array(
			'name' => array(
				'type'        => 'string',
				'description' => __( 'Preset name; its slug is the preset id.', 'utmatra-utm-attribution-and-tracking' ),
			),
		);
		foreach ( PresetsService::UTM_FIELDS as $key ) {
			$properties[ $key ] = array(
				'type'        => 'string',
				'description' => __( 'UTM value; lowercased with spaces turned into dashes.', 'utmatra-utm-attribution-and-tracking' ),
			);
		}

		return array(
			'label'               => __( 'Save UTM preset', 'utmatra-utm-attribution-and-tracking' ),
			'description'         => __( 'Saves a named source, medium, term and content combination for the add-link form. A preset with the same name is overwritten.', 'utmatra-utm-attribution-and-tracking' ),
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => $properties,
				'required'             => array( 'name' ),
				'additionalProperties' => false,
			),
			'output_schema'       => LinkAbilities::preset_output(),
			'execute_callback'    => function ( array $input ) {
				return LinkAbilities::written( $this->id(), $input, PresetsService::create( $input ) );
			},
			'permission_callback' => array( LinkAbilities::class, 'can_manage' ),
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => true,
				),
				'mcp'          => array( 'public' => false ),
			),
		);
	}
}
