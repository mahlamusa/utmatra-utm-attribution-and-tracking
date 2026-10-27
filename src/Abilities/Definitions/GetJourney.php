<?php
/**
 * GetJourney — the `utmatra/get-journey` ability.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Abilities\Definitions
 */

namespace Utmatra\Abilities\Definitions;

use Utmatra\Abilities\AbilityDefinition;
use Utmatra\Abilities\EventSchema;
use Utmatra\Capabilities;
use Utmatra\Services\JourneysService;
use Utmatra\Services\Support\ResultAdapter;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * GetJourney
 *
 * One visitor's full event list; the ability twin of
 * GET utmatra/v1/journeys/<visitor_id>.
 *
 * @since 1.1.0
 */
class GetJourney implements AbilityDefinition {

	/**
	 * The ability name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'utmatra/get-journey';
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
			'label'               => __( 'Get journey', 'utmatra-utm-attribution-and-tracking' ),
			'description'         => __( 'Returns one visitor\'s events in ascending order (the newest 500 at most), with their conversion count and first and last seen times. Fails with utmatra_not_found when the visitor has no events.', 'utmatra-utm-attribution-and-tracking' ) . ' ' . EventSchema::untrusted_note(),
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'visitor_id' ),
				'properties'           => array(
					'visitor_id' => array(
						'type'        => 'string',
						'description' => __( 'The visitor id (UUID).', 'utmatra-utm-attribution-and-tracking' ),
						'format'      => 'uuid',
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'visitor_id'  => array( 'type' => 'string' ),
					'events'      => array(
						'type'  => 'array',
						'items' => EventSchema::event(),
					),
					'conversions' => array( 'type' => 'integer' ),
					'first_seen'  => array( 'type' => 'string' ),
					'last_seen'   => array( 'type' => 'string' ),
					'truncated'   => array( 'type' => 'boolean' ),
				),
			),
			'execute_callback'    => array( $this, 'execute' ),
			'permission_callback' => array( $this, 'permission' ),
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'mcp'          => array( 'public' => true ),
			),
		);
	}

	/**
	 * Execute callback.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed>|null $input Ability input.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute( $input = null ) {
		$input = (array) $input;

		return ResultAdapter::to_ability_result( JourneysService::get_journey( (string) ( $input['visitor_id'] ?? '' ) ) );
	}

	/**
	 * Permission callback: view reports.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function permission(): bool {
		return Capabilities::can( Capabilities::VIEW_REPORTS );
	}
}
