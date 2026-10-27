<?php
/**
 * CreateLink — the `utmatra/create-link` ability.
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
 * CreateLink
 *
 * Mirrors POST utmatra/v1/links.
 *
 * @since 1.1.0
 */
class CreateLink implements AbilityDefinition {

	/**
	 * The ability name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'utmatra/create-link';
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
			'label'               => __( 'Create tracked link', 'utmatra-utm-attribution-and-tracking' ),
			'description'         => __( 'Creates a managed tracking link that redirects to the destination with its UTM parameters. The slug is derived from the label unless id is given; incoming, visit, order and lead are reserved, and an existing slug is rejected.', 'utmatra-utm-attribution-and-tracking' ),
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'id' => array(
						'type'        => 'string',
						'description' => __( 'Link slug; derived from the label when omitted.', 'utmatra-utm-attribution-and-tracking' ),
					),
				) + LinkAbilities::link_fields_input(),
				'required'             => array( 'destination' ),
				'additionalProperties' => false,
			),
			'output_schema'       => LinkAbilities::link_output(),
			'execute_callback'    => function ( array $input ) {
				return LinkAbilities::written( $this->id(), $input, LinksService::create( $input ) );
			},
			'permission_callback' => array( LinkAbilities::class, 'can_manage' ),
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
				'mcp'          => array( 'public' => false ),
			),
		);
	}
}
