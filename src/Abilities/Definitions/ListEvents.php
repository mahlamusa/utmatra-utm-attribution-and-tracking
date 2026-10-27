<?php
/**
 * ListEvents — the `utmatra/list-events` ability.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Abilities\Definitions
 */

namespace Utmatra\Abilities\Definitions;

use Utmatra\Abilities\AbilityDefinition;
use Utmatra\Abilities\EventSchema;
use Utmatra\Capabilities;
use Utmatra\Services\EventsService;
use Utmatra\Tracking\TrafficSource;

defined( 'ABSPATH' ) || exit;

/**
 * ListEvents
 *
 * Paginated, filterable event rows; the ability twin of GET utmatra/v1/events.
 *
 * @since 1.1.0
 */
class ListEvents implements AbilityDefinition {

	/**
	 * The ability name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'utmatra/list-events';
	}

	/**
	 * The wp_register_ability() arguments.
	 *
	 * The channel input is limited to the known channel slugs so an unknown
	 * label is rejected as invalid input instead of returning no events.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function args(): array {
		return array(
			'label'               => __( 'List events', 'utmatra-utm-attribution-and-tracking' ),
			'description'         => __( 'Lists tracked events (clicks, visits, orders and leads), newest first, with pagination and filters. per_page is clamped to 1-200.', 'utmatra-utm-attribution-and-tracking' ) . ' ' . EventSchema::untrusted_note(),
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => array(),
				'additionalProperties' => false,
				'properties'           => EventSchema::date_range_properties() + array(
					'channel'    => array(
						'type'        => 'string',
						'description' => __( 'Only events in this traffic channel slug, the same slugs as the label values in get-stats by_channel. Empty means every channel.', 'utmatra-utm-attribution-and-tracking' ),
						'enum'        => array_merge( array( '' ), TrafficSource::CHANNELS ),
						'default'     => '',
					),
					'kind'       => array(
						'type'        => 'string',
						'description' => __( 'Only events of this kind.', 'utmatra-utm-attribution-and-tracking' ),
						'enum'        => array( 'click', 'visit', 'order', 'lead' ),
					),
					'visitor_id' => array(
						'type'        => 'string',
						'description' => __( 'Only events of this visitor (UUID).', 'utmatra-utm-attribution-and-tracking' ),
						'format'      => 'uuid',
					),
					'page'       => array(
						'type'        => 'integer',
						'description' => __( 'Page number, starting at 1.', 'utmatra-utm-attribution-and-tracking' ),
						'default'     => 1,
					),
					'per_page'   => array(
						'type'        => 'integer',
						'description' => __( 'Events per page, clamped to 1-200.', 'utmatra-utm-attribution-and-tracking' ),
						'default'     => 50,
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'events'      => array(
						'type'  => 'array',
						'items' => EventSchema::event(),
					),
					'total'       => array( 'type' => 'integer' ),
					'page'        => array( 'type' => 'integer' ),
					'per_page'    => array( 'type' => 'integer' ),
					'total_pages' => array( 'type' => 'integer' ),
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
		return EventsService::get_events( (array) $input );
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
