<?php
/**
 * LinksService — managed tracking links: list, create, update, delete.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services
 */

namespace Utmatra\Services;

use stdClass;
use Utmatra\DB\Schema;
use Utmatra\Services\Support\Result;
use Utmatra\Tracking\LinkManager;
use Utmatra\Tracking\Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * LinksService
 *
 * Lists, creates, updates and deletes managed links on top of the
 * LinkManager store, with LinkValidator applying the slug and input rules.
 * Moved from RestController; the REST controller and the link abilities
 * both call it.
 *
 * @since 1.1.0
 */
class LinksService {

	/**
	 * All managed links with their click counts, plus form suggestions and presets.
	 *
	 * Click counts for all links come from a single GROUP BY query instead
	 * of one COUNT query per link. The `suggestions` map holds previously
	 * used source/medium/campaign values for the add-link form datalists.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController::get_links(); returns the body array.
	 *
	 * @return array{links: array<int, array<string, mixed>>, suggestions: array<string, string[]>, presets: array<int, array<string, string>>}
	 */
	public static function all(): array {
		global $wpdb;
		$table  = esc_sql( Schema::table() );
		$links  = LinkManager::all();
		$result = array();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching
		$counts_raw = $wpdb->get_results(
			"SELECT link_id, COUNT(*) AS count FROM {$table} WHERE link_id != '' GROUP BY link_id",
			ARRAY_A
		);
		// phpcs:enable

		$counts = array();
		foreach ( (array) $counts_raw as $row ) {
			$counts[ (string) $row['link_id'] ] = (int) $row['count'];
		}

		foreach ( $links as $id => $link ) {
			$result[] = self::format( $id, $link, $counts[ $id ] ?? 0 );
		}

		return array(
			'links'       => $result,
			'suggestions' => self::suggestions( $links ),
			'presets'     => PresetsService::all(),
		);
	}

	/**
	 * Create a managed link.
	 *
	 * The slug is derived from the label when no explicit id is given.
	 * Fails when the slug is empty, reserved (`incoming`, `visit`, `order`
	 * and `lead` name event kinds in the events table), already in use, or
	 * the destination is not a valid http(s) URL.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController::create_link(); takes an input array and returns a Result.
	 *
	 * @param array<string, mixed> $input Keys `id`, `label`, `destination`, the six UTM fields and `custom`.
	 *
	 * @return Result The formatted link (201), or a 400 or 409 error.
	 */
	public static function create( array $input ): Result {
		$id = LinkValidator::slug( $input );
		if ( $id instanceof Result ) {
			return $id;
		}

		$link = LinkValidator::fields( $input );
		if ( $link instanceof Result ) {
			return $link;
		}

		if ( null !== LinkManager::get( $id ) ) {
			return Result::error(
				'utmatra_link_exists',
				__( 'A link with this slug already exists.', 'utmatra-utm-attribution-and-tracking' ),
				409
			);
		}

		if ( '' === $link['label'] ) {
			$link['label'] = $id;
		}

		LinkManager::save( $id, $link );

		return Result::ok( self::format( $id, $link, 0 ), 201 );
	}

	/**
	 * Update a managed link.
	 *
	 * Replaces the label, destination, the six UTM fields and the custom
	 * parameter values of an existing link. Stored values for custom
	 * parameters no longer configured are kept, so re-adding the parameter
	 * restores them. The slug is immutable — renaming would orphan recorded
	 * events.
	 *
	 * @since 1.0.0
	 * @since 1.0.0 Keeps stored values for custom parameters no longer configured.
	 * @since 1.1.0 Moved from RestController::update_link(); takes the id and an input array and returns a Result.
	 *
	 * @param string               $id    Link slug.
	 * @param array<string, mixed> $input Keys `label`, `destination`, the six UTM fields and `custom`.
	 *
	 * @return Result The formatted link (200), a 404 error for an unknown slug or a 400 error for an invalid destination.
	 */
	public static function update( string $id, array $input ): Result {
		global $wpdb;

		$stored = LinkManager::get( $id );
		if ( null === $stored ) {
			return self::not_found();
		}

		$link = LinkValidator::fields( $input );
		if ( $link instanceof Result ) {
			return $link;
		}

		if ( '' === $link['label'] ) {
			$link['label'] = $id;
		}

		// Values for parameters no longer configured are not in the form, so
		// they are carried over: re-adding the parameter brings them back.
		$kept = array_diff_key( is_array( $stored['custom'] ?? null ) ? $stored['custom'] : array(), array_flip( Tracker::custom_params() ) );
		if ( array() !== $kept ) {
			$link['custom'] = ( $link['custom'] ?? array() ) + $kept;
		}

		LinkManager::save( $id, $link );

		$table = esc_sql( Schema::table() );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching
		$clicks = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE link_id = %s", $id )
		);
		// phpcs:enable

		return Result::ok( self::format( $id, $link, $clicks ) );
	}

	/**
	 * Delete a managed link.
	 *
	 * Recorded click events are kept for historical stats; only the managed
	 * link definition is removed.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController::delete_link(); takes the id and returns a Result.
	 *
	 * @param string $id Link slug.
	 *
	 * @return Result `{ deleted: true }`, or a 404 error for an unknown slug.
	 */
	public static function delete( string $id ): Result {
		if ( null === LinkManager::get( $id ) ) {
			return self::not_found();
		}

		LinkManager::delete( $id );

		return Result::ok( array( 'deleted' => true ) );
	}

	/**
	 * The 404 error for an unknown link slug.
	 *
	 * @since 1.1.0
	 *
	 * @return Result
	 */
	private static function not_found(): Result {
		return Result::error(
			'utmatra_link_not_found',
			__( 'Link not found.', 'utmatra-utm-attribution-and-tracking' ),
			404
		);
	}

	/**
	 * Collect previously used UTM values for the add-link form datalists.
	 *
	 * Merges distinct values from recorded events (any row type — a value is
	 * a useful suggestion regardless of whether it came from a click or a
	 * conversion) with the values stored on managed links, deduped and capped
	 * at 20 per field.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController::suggestions().
	 *
	 * @param array<string, array<string, string>> $links Managed links keyed by slug.
	 *
	 * @return array<string, string[]> Keys `sources`, `mediums`, `campaigns`.
	 */
	private static function suggestions( array $links ): array {
		global $wpdb;
		$table = esc_sql( Schema::table() );

		$columns = array(
			'sources'   => 'utm_source',
			'mediums'   => 'utm_medium',
			'campaigns' => 'utm_campaign',
		);

		$out = array();
		foreach ( $columns as $key => $column ) {
			// $column comes from the fixed map above, never from user input.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching
			$values = (array) $wpdb->get_col(
				"SELECT DISTINCT {$column} FROM {$table} WHERE {$column} != '' ORDER BY {$column} LIMIT 20"
			);
			// phpcs:enable

			foreach ( $links as $link ) {
				if ( ! empty( $link[ $column ] ) ) {
					$values[] = (string) $link[ $column ];
				}
			}

			$out[ $key ] = array_slice( array_values( array_unique( array_map( 'strval', $values ) ) ), 0, 20 );
		}

		return $out;
	}

	/**
	 * Format a managed link for a response.
	 *
	 * Includes both the built destination (UTM parameters and link-level
	 * custom parameter values appended) and the raw stored fields so the
	 * dashboard edit form can pre-fill correctly.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Moved from RestController::format_link().
	 *
	 * @param string               $id     Link slug.
	 * @param array<string, mixed> $link   Stored link fields.
	 * @param int                  $clicks Recorded click count for the slug.
	 *
	 * @return array<string, int|string|array<string, string>|stdClass> The custom entry is a stdClass (JSON object over REST).
	 */
	private static function format( string $id, array $link, int $clicks ): array {
		return array(
			'id'              => $id,
			'label'           => $link['label'] ?? $id,
			'destination'     => LinkManager::build_url( $link ),
			'destination_raw' => $link['destination'] ?? '',
			'redirect_url'    => LinkManager::redirect_url( $id ),
			'clicks'          => $clicks,
			'utm_campaign'    => $link['utm_campaign'] ?? '',
			'utm_source'      => $link['utm_source'] ?? '',
			'utm_medium'      => $link['utm_medium'] ?? '',
			'utm_id'          => $link['utm_id'] ?? '',
			'utm_term'        => $link['utm_term'] ?? '',
			'utm_content'     => $link['utm_content'] ?? '',
			'custom'          => (object) ( isset( $link['custom'] ) && is_array( $link['custom'] ) ? $link['custom'] : array() ),
		);
	}
}
