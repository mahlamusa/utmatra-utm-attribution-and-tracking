<?php
/**
 * Unit tests for Capabilities.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Capabilities;

return array(
	'constants name the three tiers'               => static function () {
		utmatra_assert_same( 'utmatra_view_reports', Capabilities::VIEW_REPORTS );
		utmatra_assert_same( 'utmatra_manage_links', Capabilities::MANAGE_LINKS );
		utmatra_assert_same( 'utmatra_manage_settings', Capabilities::MANAGE_SETTINGS );
	},

	'a user without caps holds none'               => static function () {
		utmatra_assert_false( Capabilities::can( Capabilities::VIEW_REPORTS ) );
		utmatra_assert_false( Capabilities::can( Capabilities::MANAGE_LINKS ) );
		utmatra_assert_false( Capabilities::can( Capabilities::MANAGE_SETTINGS ) );
	},

	'administrator caps do not imply the tiers'    => static function () {
		$GLOBALS['utmatra_test']['caps'] = array( 'manage_options' );
		utmatra_assert_false( Capabilities::can( Capabilities::VIEW_REPORTS ) );
	},

	'view only grants view, not manage'            => static function () {
		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::VIEW_REPORTS );
		utmatra_assert_true( Capabilities::can( Capabilities::VIEW_REPORTS ) );
		utmatra_assert_false( Capabilities::can( Capabilities::MANAGE_LINKS ) );
		utmatra_assert_false( Capabilities::can( Capabilities::MANAGE_SETTINGS ) );
	},

	'manage links implies view only'               => static function () {
		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::MANAGE_LINKS );
		utmatra_assert_true( Capabilities::can( Capabilities::VIEW_REPORTS ) );
		utmatra_assert_true( Capabilities::can( Capabilities::MANAGE_LINKS ) );
		utmatra_assert_false( Capabilities::can( Capabilities::MANAGE_SETTINGS ) );
	},

	'manage settings implies view only'            => static function () {
		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::MANAGE_SETTINGS );
		utmatra_assert_true( Capabilities::can( Capabilities::VIEW_REPORTS ) );
		utmatra_assert_false( Capabilities::can( Capabilities::MANAGE_LINKS ) );
		utmatra_assert_true( Capabilities::can( Capabilities::MANAGE_SETTINGS ) );
	},

	'an unknown capability is never granted'       => static function () {
		$GLOBALS['utmatra_test']['caps'] = array( 'manage_options', 'utmatra_other' );
		utmatra_assert_false( Capabilities::can( 'utmatra_other' ) );
		utmatra_assert_false( Capabilities::can( 'manage_options' ) );
	},

	'the map filter remaps a tier'                 => static function () {
		add_filter(
			'utmatra_capability_map',
			static function ( $map ) {
				$map[ Capabilities::VIEW_REPORTS ] = 'edit_posts';
				return $map;
			}
		);
		$GLOBALS['utmatra_test']['caps'] = array( 'edit_posts' );
		utmatra_assert_true( Capabilities::can( Capabilities::VIEW_REPORTS ) );
		utmatra_assert_false( Capabilities::can( Capabilities::MANAGE_LINKS ) );

		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::VIEW_REPORTS );
		utmatra_assert_false( Capabilities::can( Capabilities::VIEW_REPORTS ), 'the original cap no longer grants view' );
	},

	'a remapped manage tier still implies view'    => static function () {
		add_filter(
			'utmatra_capability_map',
			static function ( $map ) {
				$map[ Capabilities::MANAGE_LINKS ] = 'edit_pages';
				return $map;
			}
		);
		$GLOBALS['utmatra_test']['caps'] = array( 'edit_pages' );
		utmatra_assert_true( Capabilities::can( Capabilities::MANAGE_LINKS ) );
		utmatra_assert_true( Capabilities::can( Capabilities::VIEW_REPORTS ) );
	},

	'a non-array map filter result is ignored'     => static function () {
		add_filter(
			'utmatra_capability_map',
			static function () {
				return false;
			}
		);
		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::VIEW_REPORTS );
		utmatra_assert_true( Capabilities::can( Capabilities::VIEW_REPORTS ) );
	},

	'a filter dropping a tier keeps the default'   => static function () {
		add_filter(
			'utmatra_capability_map',
			static function () {
				return array();
			}
		);
		utmatra_assert_same( Capabilities::MANAGE_LINKS, Capabilities::map()[ Capabilities::MANAGE_LINKS ] );
	},

	'non-string values and unknown keys are ignored' => static function () {
		add_filter(
			'utmatra_capability_map',
			static function ( $map ) {
				$map[ Capabilities::VIEW_REPORTS ] = array( 'edit_posts' );
				$map['utmatra_other']              = 'read';
				return $map;
			}
		);
		utmatra_assert_same(
			array(
				Capabilities::VIEW_REPORTS    => Capabilities::VIEW_REPORTS,
				Capabilities::MANAGE_LINKS    => Capabilities::MANAGE_LINKS,
				Capabilities::MANAGE_SETTINGS => Capabilities::MANAGE_SETTINGS,
			),
			Capabilities::map()
		);
		$GLOBALS['utmatra_test']['caps'] = array( 'read' );
		utmatra_assert_false( Capabilities::can( 'utmatra_other' ) );
	},

	'granting cap names the cap the user holds'    => static function () {
		utmatra_assert_same( '', Capabilities::granting_cap( Capabilities::VIEW_REPORTS ) );
		$GLOBALS['utmatra_test']['caps'] = array( Capabilities::MANAGE_SETTINGS );
		utmatra_assert_same( Capabilities::MANAGE_SETTINGS, Capabilities::granting_cap( Capabilities::VIEW_REPORTS ) );
		utmatra_assert_same( '', Capabilities::granting_cap( Capabilities::MANAGE_LINKS ) );
		utmatra_assert_same( '', Capabilities::granting_cap( 'utmatra_other' ) );
	},

	'register grants administrators and records the version' => static function () {
		Capabilities::register();
		$roles = $GLOBALS['utmatra_test']['roles'];
		utmatra_assert_true( $roles['administrator'][ Capabilities::VIEW_REPORTS ] ?? false );
		utmatra_assert_true( $roles['administrator'][ Capabilities::MANAGE_LINKS ] ?? false );
		utmatra_assert_true( $roles['administrator'][ Capabilities::MANAGE_SETTINGS ] ?? false );
		utmatra_assert_false( isset( $roles['editor'][ Capabilities::VIEW_REPORTS ] ), 'other roles are untouched' );
		utmatra_assert_same( '1.1.0', get_option( Capabilities::VERSION_OPTION ) );
	},

	'maybe upgrade grants a site upgraded from 1.0.0' => static function () {
		Capabilities::maybe_upgrade();
		utmatra_assert_true( $GLOBALS['utmatra_test']['roles']['administrator'][ Capabilities::VIEW_REPORTS ] ?? false );
	},

	'register refreshes a logged-in user\'s caps'  => static function () {
		$GLOBALS['utmatra_test']['logged_in'] = true;
		Capabilities::register();
		utmatra_assert_same( 1, $GLOBALS['utmatra_test']['role_caps_refreshed'] );
	},

	'register skips the refresh when logged out'   => static function () {
		Capabilities::register();
		utmatra_assert_same( 0, $GLOBALS['utmatra_test']['role_caps_refreshed'] );
	},

	'maybe upgrade skips the refresh once granted' => static function () {
		$GLOBALS['utmatra_test']['logged_in']                               = true;
		$GLOBALS['utmatra_test']['options'][ Capabilities::VERSION_OPTION ] = '1.1.0';
		Capabilities::maybe_upgrade();
		utmatra_assert_same( 0, $GLOBALS['utmatra_test']['role_caps_refreshed'] );
	},

	'register without an administrator role retries later' => static function () {
		unset( $GLOBALS['utmatra_test']['roles']['administrator'] );
		$GLOBALS['utmatra_test']['logged_in'] = true;
		Capabilities::register();
		utmatra_assert_false( get_option( Capabilities::VERSION_OPTION ) );
		utmatra_assert_same( 0, $GLOBALS['utmatra_test']['role_caps_refreshed'], 'nothing granted, nothing refreshed' );
	},

	'maybe upgrade skips a site already granted'   => static function () {
		$GLOBALS['utmatra_test']['options'][ Capabilities::VERSION_OPTION ] = '1.1.0';
		Capabilities::maybe_upgrade();
		utmatra_assert_false( isset( $GLOBALS['utmatra_test']['roles']['administrator'][ Capabilities::VIEW_REPORTS ] ) );
	},

	'revoke removes the tiers from every role'     => static function () {
		Capabilities::register();
		$GLOBALS['utmatra_test']['roles']['editor'][ Capabilities::VIEW_REPORTS ] = true;
		Capabilities::revoke();
		utmatra_assert_same( array( 'manage_options' => true ), $GLOBALS['utmatra_test']['roles']['administrator'] );
		utmatra_assert_same( array( 'edit_posts' => true ), $GLOBALS['utmatra_test']['roles']['editor'] );
		utmatra_assert_false( get_option( Capabilities::VERSION_OPTION ) );
	},
);
