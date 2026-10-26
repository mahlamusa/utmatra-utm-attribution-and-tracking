<?php
/**
 * Unit tests for OptIn.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Abilities\OptIn;

return array(
	'option name'                              => static function () {
		utmatra_assert_same( 'utmatra_allow_agent_settings', OptIn::OPTION );
	},

	'off by default with a 403 error'          => static function () {
		utmatra_assert_false( OptIn::allowed() );

		$error = OptIn::permission();
		utmatra_assert_instance_of( WP_Error::class, $error );
		utmatra_assert_same( 'utmatra_agent_settings_disabled', $error->get_error_code() );
		utmatra_assert_same( array( 'status' => 403 ), $error->get_error_data() );
		utmatra_assert_true( false !== strpos( $error->get_error_message(), 'Allow AI agents to change settings' ), 'message names the setting' );
	},

	'the option turns it on'                   => static function () {
		$GLOBALS['utmatra_test']['options'][ OptIn::OPTION ] = '1';
		utmatra_assert_true( OptIn::allowed() );
		utmatra_assert_true( OptIn::permission() );
	},

	'option 0 keeps it off'                    => static function () {
		$GLOBALS['utmatra_test']['options'][ OptIn::OPTION ] = 0;
		utmatra_assert_false( OptIn::allowed() );
	},

	'the filter overrides the option either way' => static function () {
		add_filter(
			'utmatra_abilities_allow_settings_writes',
			static function () {
				return true;
			}
		);
		utmatra_assert_true( OptIn::permission() );

		utmatra_test_reset();
		$GLOBALS['utmatra_test']['options'][ OptIn::OPTION ] = 1;
		add_filter(
			'utmatra_abilities_allow_settings_writes',
			static function () {
				return false;
			}
		);
		utmatra_assert_instance_of( WP_Error::class, OptIn::permission() );
	},

	'the filter receives the option state'     => static function () {
		$seen = array();
		add_filter(
			'utmatra_abilities_allow_settings_writes',
			static function ( $allowed ) use ( &$seen ) {
				$seen[] = $allowed;
				return $allowed;
			}
		);
		OptIn::allowed();
		$GLOBALS['utmatra_test']['options'][ OptIn::OPTION ] = 1;
		OptIn::allowed();
		utmatra_assert_same( array( false, true ), $seen );
	},
);
