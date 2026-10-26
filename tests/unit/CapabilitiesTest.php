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
);
