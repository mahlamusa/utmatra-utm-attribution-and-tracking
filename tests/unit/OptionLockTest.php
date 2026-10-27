<?php
/**
 * Unit tests for OptionLock.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Services\LinksService;
use Utmatra\Services\PresetsService;
use Utmatra\Services\Support\OptionLock;
use Utmatra\Services\Support\Result;

return array(
	'acquired lock runs the callback, drops the cached option and releases' => static function () {
		$result = OptionLock::run(
			'utmatra_links',
			static function () {
				return Result::ok( array( 'ran' => true ), 201 );
			}
		);

		utmatra_assert_same( 201, $result->status() );
		utmatra_assert_same( array( 'options:utmatra_links', 'options:alloptions', 'options:notoptions' ), $GLOBALS['utmatra_test']['cache_deleted'] );
		$queries = utmatra_test_lock_queries();
		utmatra_assert_same( 2, count( $queries ) );
		utmatra_assert_same( 1, preg_match( "/^SELECT GET_LOCK\\('utmatra:[0-9a-f]{32}', 3\\)$/", $queries[0] ) );
		utmatra_assert_same( 0, strpos( $queries[1], 'SELECT RELEASE_LOCK(' ) );
	},

	'lock name differs per option'              => static function () {
		$noop = static function () {
			return Result::ok( array() );
		};
		OptionLock::run( 'utmatra_links', $noop );
		OptionLock::run( 'utmatra_presets', $noop );

		$queries = utmatra_test_lock_queries();
		utmatra_assert_true( $queries[0] !== $queries[2] );
	},

	'timed-out lock returns 503 without running the callback' => static function () {
		$GLOBALS['utmatra_test']['lock_var'] = '0';
		$ran                                 = false;

		$result = OptionLock::run(
			'utmatra_links',
			static function () use ( &$ran ) {
				$ran = true;
				return Result::ok( array() );
			}
		);

		utmatra_assert_false( $ran );
		utmatra_assert_same( 503, $result->status() );
		utmatra_assert_same( 'utmatra_busy', $result->code() );
		utmatra_assert_same( 1, count( utmatra_test_lock_queries() ) );
	},

	'database without named locks runs the callback unlocked' => static function () {
		$GLOBALS['utmatra_test']['lock_var'] = null;

		$result = OptionLock::run(
			'utmatra_links',
			static function () {
				return Result::ok( array(), 201 );
			}
		);

		utmatra_assert_same( 201, $result->status() );
		utmatra_assert_same( 1, count( utmatra_test_lock_queries() ) );
	},

	'lock is released when the callback throws' => static function () {
		$thrown = false;
		try {
			OptionLock::run(
				'utmatra_links',
				static function () {
					throw new RuntimeException( 'boom' );
				}
			);
		} catch ( RuntimeException $e ) {
			$thrown = true;
		}

		utmatra_assert_true( $thrown );
		$queries = utmatra_test_lock_queries();
		utmatra_assert_same( 0, strpos( $queries[1], 'SELECT RELEASE_LOCK(' ) );
	},

	'every link and preset write returns 503 and saves nothing when the lock times out' => static function () {
		$stored                                 = array(
			'utmatra_links'   => array( 'spring' => array( 'label' => 'Spring' ) ),
			'utmatra_presets' => array( 'story' => array( 'name' => 'Story' ) ),
		);
		$GLOBALS['utmatra_test']['options']   = $stored;
		$GLOBALS['utmatra_test']['lock_var']  = '0';
		$destination                            = array( 'destination' => 'https://example.com/' );

		$results = array(
			LinksService::create( array( 'id' => 'summer' ) + $destination ),
			LinksService::update( 'spring', $destination ),
			LinksService::delete( 'spring' ),
			PresetsService::create( array( 'name' => 'Reel' ) ),
			PresetsService::delete( 'story' ),
		);

		foreach ( $results as $result ) {
			utmatra_assert_same( 503, $result->status() );
		}
		utmatra_assert_same( $stored, $GLOBALS['utmatra_test']['options'] );
	},
);
