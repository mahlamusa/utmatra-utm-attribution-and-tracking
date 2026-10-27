<?php
/**
 * OptionLock — serialises read-modify-write changes to one option.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services\Support
 */

namespace Utmatra\Services\Support;

defined( 'ABSPATH' ) || exit;

/**
 * OptionLock
 *
 * Links and presets are stored as one array per option, so every write
 * reads the whole array, changes one key and writes it back; a settings
 * save reads every setting and writes them all back. Two
 * overlapping writes (parallel agent calls) would otherwise both succeed
 * while one change is lost. A MySQL named lock makes them run one at a
 * time.
 *
 * @since 1.1.0
 */
class OptionLock {

	/**
	 * Seconds to wait for the lock before giving up.
	 *
	 * @since 1.1.0
	 *
	 * @var int
	 */
	private const TIMEOUT = 3;

	/**
	 * Run a read-modify-write of an option while holding its lock.
	 *
	 * The option's cached value is dropped once the lock is held, so the
	 * callback reads what the previous holder wrote rather than the value
	 * this request loaded before it waited. When the database cannot take
	 * named locks (GET_LOCK returns NULL) the callback runs unlocked, as it
	 * did before.
	 *
	 * @since 1.1.0
	 *
	 * @param string   $option   Option name, or a shared name for a group of options saved together.
	 * @param callable $callback Returns the Result of the change.
	 *
	 * @return Result The callback's Result, or a 503 error when the lock is still held by another request after the timeout.
	 */
	public static function run( string $option, callable $callback ): Result {
		global $wpdb;

		// Replica-aware drop-ins (HyperDB, LudicrousDB) send SELECTs to
		// replicas; the lock and the re-read must use the primary.
		if ( method_exists( $wpdb, 'send_reads_to_masters' ) ) {
			$wpdb->send_reads_to_masters();
		}

		// Named locks are server-wide, so the name carries the options table
		// to keep sites sharing a MySQL server from blocking each other.
		$name     = 'utmatra:' . md5( $wpdb->dbname . '.' . $wpdb->options . '.' . $option );
		$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, self::TIMEOUT ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( null !== $acquired && 1 !== (int) $acquired ) {
			return Result::error(
				'utmatra_busy',
				__( 'Another change is being saved. Please try again.', 'utmatra-utm-attribution-and-tracking' ),
				503
			);
		}

		wp_cache_delete( $option, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		try {
			return $callback();
		} finally {
			if ( null !== $acquired ) {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		}
	}
}
