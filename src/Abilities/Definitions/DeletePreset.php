<?php
/**
 * DeletePreset — the `utmatra/delete-preset` ability.
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
 * DeletePreset
 *
 * Mirrors DELETE utmatra/v1/presets/<id>. Requires `confirm: true`.
 *
 * @since 1.1.0
 */
class DeletePreset implements AbilityDefinition {

	/**
	 * The ability name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'utmatra/delete-preset';
	}

	/**
	 * The wp_register_ability() arguments.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function args(): array {
		return array(
			'label'               => __( 'Delete UTM preset', 'utmatra-utm-attribution-and-tracking' ),
			'description'         => __( 'Deletes a saved UTM preset. Links created from it are unchanged. Requires confirm set to true.', 'utmatra-utm-attribution-and-tracking' ),
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'id'      => LinkAbilities::id_input( __( 'Id of the preset to delete.', 'utmatra-utm-attribution-and-tracking' ) ),
					'confirm' => LinkAbilities::confirm_input(),
				),
				'required'             => array( 'id', 'confirm' ),
				'additionalProperties' => false,
			),
			'output_schema'       => LinkAbilities::deleted_output(),
			'execute_callback'    => function ( array $input ) {
				return LinkAbilities::written( $this->id(), $input, PresetsService::delete( $input['id'] ) );
			},
			'permission_callback' => array( LinkAbilities::class, 'can_manage' ),
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
				'mcp'          => array( 'public' => false ),
			),
		);
	}
}
