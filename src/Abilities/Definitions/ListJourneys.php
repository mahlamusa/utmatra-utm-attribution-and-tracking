<?php
/**
 * ListJourneys — the `utmatra/list-journeys` ability.
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

defined( 'ABSPATH' ) || exit;

/**
 * ListJourneys
 *
 * Conversions with their preceding touches; the ability twin of
 * GET utmatra/v1/journeys.
 *
 * @since 1.1.0
 */
class ListJourneys implements AbilityDefinition {

	/**
	 * The ability name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'utmatra/list-journeys';
	}

	/**
	 * The wp_register_ability() arguments.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function args(): array {
		$date_range = EventSchema::date_range_properties();
		unset( $date_range['campaign'] );

		$event          = EventSchema::event();
		$nullable_event = array( 'type' => array( 'object', 'null' ) ) + $event;

		return array(
			'label'               => __( 'List journeys', 'utmatra-utm-attribution-and-tracking' ),
			'description'         => __( 'Lists conversions (orders and leads) that carry a visitor id, newest first, each with the visitor\'s preceding touches, first and last touch. per_page is clamped to 1-100.', 'utmatra-utm-attribution-and-tracking' ) . ' ' . EventSchema::untrusted_note(),
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => array(),
				'additionalProperties' => false,
				'properties'           => $date_range + array(
					'page'     => array(
						'type'        => 'integer',
						'description' => __( 'Page number, starting at 1.', 'utmatra-utm-attribution-and-tracking' ),
						'default'     => 1,
					),
					'per_page' => array(
						'type'        => 'integer',
						'description' => __( 'Journeys per page, clamped to 1-100.', 'utmatra-utm-attribution-and-tracking' ),
						'default'     => 50,
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'journeys'        => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'conversion'        => $event,
								'visitor_id'        => array( 'type' => 'string' ),
								'first_touch'       => $nullable_event,
								'last_touch'        => $nullable_event,
								'touch_count'       => array( 'type' => 'integer' ),
								'touches'           => array(
									'type'  => 'array',
									'items' => $event,
								),
								'touches_truncated' => array( 'type' => 'boolean' ),
							),
						),
					),
					'total'           => array( 'type' => 'integer' ),
					'page'            => array( 'type' => 'integer' ),
					'per_page'        => array( 'type' => 'integer' ),
					'total_pages'     => array( 'type' => 'integer' ),
					'without_visitor' => array( 'type' => 'integer' ),
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
	 * @return array<string, mixed>
	 */
	public function execute( $input = null ): array {
		return JourneysService::get_journeys( (array) $input );
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
