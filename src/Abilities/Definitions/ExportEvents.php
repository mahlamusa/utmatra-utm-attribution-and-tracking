<?php
/**
 * ExportEvents — the `utmatra/export-events` ability.
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

defined( 'ABSPATH' ) || exit;

/**
 * ExportEvents
 *
 * A capped sample of the CSV export rows; the ability twin of
 * GET utmatra/v1/export, which returns up to 10,000 rows through the same
 * service method.
 *
 * @since 1.1.0
 */
class ExportEvents implements AbilityDefinition {

	/**
	 * Default number of rows.
	 *
	 * @since 1.1.0
	 *
	 * @var int
	 */
	private const DEFAULT_LIMIT = 500;

	/**
	 * Largest allowed number of rows.
	 *
	 * @since 1.1.0
	 *
	 * @var int
	 */
	private const MAX_LIMIT = 2000;

	/**
	 * The ability name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'utmatra/export-events';
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
			'label'               => __( 'Export events', 'utmatra-utm-attribution-and-tracking' ),
			'description'         => __( 'Returns the raw rows of the CSV export, newest first, capped at limit (default 500, clamped to 1-2000). truncated is true when more rows match; narrow the date range or page through utmatra/list-events for the rest. IP hashes and user agents are never included.', 'utmatra-utm-attribution-and-tracking' ) . ' ' . EventSchema::untrusted_note(),
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => array(),
				'additionalProperties' => false,
				'properties'           => EventSchema::date_range_properties() + array(
					'include_visits' => array(
						'type'        => 'boolean',
						'description' => __( 'Include untagged landing visits.', 'utmatra-utm-attribution-and-tracking' ),
						'default'     => false,
					),
					'limit'          => array(
						'type'        => 'integer',
						'description' => __( 'Maximum number of rows, clamped to 1-2000.', 'utmatra-utm-attribution-and-tracking' ),
						'default'     => self::DEFAULT_LIMIT,
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'rows'       => array(
						'type'  => 'array',
						'items' => EventSchema::export_row(),
					),
					'returned'   => array( 'type' => 'integer' ),
					'total_rows' => array( 'type' => 'integer' ),
					'truncated'  => array( 'type' => 'boolean' ),
					'hint'       => array( 'type' => 'string' ),
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
		$input  = (array) $input;
		$limit  = min( self::MAX_LIMIT, max( 1, (int) ( $input['limit'] ?? self::DEFAULT_LIMIT ) ) );
		$result = EventsService::export_sample( $input, $limit );

		$result['hint'] = $result['truncated']
			? __( 'More rows match than were returned. Narrow the date range (since, until, period or campaign) or page through utmatra/list-events.', 'utmatra-utm-attribution-and-tracking' )
			: '';

		return $result;
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
