<?php
/**
 * UpdateLink — the `utmatra/update-link` ability.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Abilities\Definitions
 */

namespace Utmatra\Abilities\Definitions;

use Utmatra\Abilities\AbilityDefinition;
use Utmatra\Abilities\LinkAbilities;
use Utmatra\Services\LinksService;

defined( 'ABSPATH' ) || exit;

/**
 * UpdateLink
 *
 * Mirrors PUT utmatra/v1/links/<id>.
 *
 * @since 1.1.0
 */
class UpdateLink implements AbilityDefinition {

	/**
	 * The ability name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'utmatra/update-link';
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
			'label'               => __( 'Update tracked link', 'utmatra-utm-attribution-and-tracking' ),
			'description'         => __( 'Replaces the label, destination, UTM values and custom parameter values of a managed link; omitted fields are cleared, so send every value to keep. The slug cannot change.', 'utmatra-utm-attribution-and-tracking' ),
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'id' => LinkAbilities::id_input( __( 'Slug of the link to update.', 'utmatra-utm-attribution-and-tracking' ) ),
				) + LinkAbilities::link_fields_input(),
				'required'             => array( 'id', 'destination' ),
				'additionalProperties' => false,
			),
			'output_schema'       => LinkAbilities::link_output(),
			'execute_callback'    => function ( array $input ) {
				return LinkAbilities::written( $this->id(), $input, LinksService::update( $input['id'], $input ) );
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
