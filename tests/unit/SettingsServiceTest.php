<?php
/**
 * Unit tests for SettingsService::save under the settings lock.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Reports\WeeklyEmail;
use Utmatra\Services\SettingsService;

// The real weekly email needs WordPress the unit bootstrap does not stub.
if ( ! class_exists( WeeklyEmail::class ) ) {
	require_once __DIR__ . '/fixtures/WeeklyEmailStub.php';
}

return array(
	'a partial save reads the stored settings after taking the lock'                    => static function () {
		$GLOBALS['utmatra_test']['options'] = array( 'utmatra_cookie_days' => 90 );
		// Another save finishes while this one waits: its values land when the lock drops the cache.
		$GLOBALS['utmatra_test']['on_cache_delete'] = static function ( $group, $key ) {
			if ( 'options:alloptions' === $group . ':' . $key ) {
				$GLOBALS['utmatra_test']['options']['utmatra_cookie_days']    = 45;
				$GLOBALS['utmatra_test']['options']['utmatra_webhook_events'] = array(
					'orders' => false,
					'leads'  => true,
				);
			}
		};

		$result = SettingsService::save( array( 'webhook_leads' => false ) );

		utmatra_assert_same( 200, $result->status() );
		utmatra_assert_same( 45, get_option( 'utmatra_cookie_days' ) );
		utmatra_assert_same(
			array(
				'orders' => false,
				'leads'  => false,
			),
			get_option( 'utmatra_webhook_events' )
		);
		utmatra_assert_same( 2, count( utmatra_test_lock_queries() ) );
		utmatra_assert_true( in_array( 'options:utmatra_cookie_days', $GLOBALS['utmatra_test']['cache_deleted'], true ) );
	},

	'a timed-out lock returns 503 and saves nothing'                                     => static function () {
		$GLOBALS['utmatra_test']['options']  = array( 'utmatra_retention_days' => 180 );
		$GLOBALS['utmatra_test']['lock_var'] = '0';

		$result = SettingsService::save(
			array(
				'retention_days' => 30,
				'custom_params'  => 'coupon',
			)
		);

		utmatra_assert_same( 503, $result->status() );
		utmatra_assert_same( 'utmatra_busy', $result->code() );
		utmatra_assert_same( array( 'utmatra_retention_days' => 180 ), $GLOBALS['utmatra_test']['options'] );
	},

	'a rejected custom parameter returns 400 without taking the lock'                    => static function () {
		$result = SettingsService::save( array( 'custom_params' => 'utm_source' ) );

		utmatra_assert_same( 400, $result->status() );
		utmatra_assert_same( 0, count( utmatra_test_lock_queries() ) );
		utmatra_assert_same( array(), $GLOBALS['utmatra_test']['options'] );
	},
);
