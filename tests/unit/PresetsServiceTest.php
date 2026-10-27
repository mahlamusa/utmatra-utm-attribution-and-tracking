<?php

use Utmatra\Services\PresetsService;

return array(
	'non-ASCII name gets an id the delete route accepts'   => static function () {
		$GLOBALS['utmatra_test']['options'] = array();

		$result = PresetsService::create( array( 'name' => '日本 Campaign' ) );
		$id     = $result->data()['id'];

		utmatra_assert_same( 201, $result->status() );
		utmatra_assert_same( 1, preg_match( '/^[a-z0-9_-]+$/', $id ) );
		utmatra_assert_true( PresetsService::delete( $id )->is_ok() );
		utmatra_assert_same( array(), get_option( 'utmatra_presets' ) );
	},

	'ASCII name keeps its slug id'                         => static function () {
		$GLOBALS['utmatra_test']['options'] = array();

		utmatra_assert_same( 'spring-promo', PresetsService::create( array( 'name' => 'Spring Promo' ) )->data()['id'] );
	},

	'empty name is rejected'                               => static function () {
		$GLOBALS['utmatra_test']['options'] = array();

		utmatra_assert_same( 400, PresetsService::create( array( 'name' => '   ' ) )->status() );
	},

	'emoji-only name still gets a valid id'                => static function () {
		$GLOBALS['utmatra_test']['options'] = array();

		$result = PresetsService::create( array( 'name' => '🎉' ) );

		utmatra_assert_same( 201, $result->status() );
		utmatra_assert_same( 1, preg_match( '/^[a-z0-9_-]+$/', $result->data()['id'] ) );
	},

	'numeric name can be created and deleted'              => static function () {
		$GLOBALS['utmatra_test']['options'] = array();

		$id = PresetsService::create( array( 'name' => '2024' ) )->data()['id'];

		utmatra_assert_same( '2024', $id );
		utmatra_assert_true( PresetsService::delete( $id )->is_ok() );
	},

	'saving does not remove a legacy preset with another name' => static function () {
		$legacy                             = sanitize_title( '日' );
		$GLOBALS['utmatra_test']['options'] = array(
			'utmatra_presets' => array( $legacy => array( 'name' => '日' ) ),
		);

		$id = PresetsService::create( array( 'name' => '本' ) )->data()['id'];

		utmatra_assert_same( array( $legacy, $id ), array_keys( get_option( 'utmatra_presets' ) ) );
	},

	're-saving replaces the legacy percent-encoded entry' => static function () {
		$legacy                             = sanitize_title( '日本 Campaign' );
		$GLOBALS['utmatra_test']['options'] = array(
			'utmatra_presets' => array(
				$legacy        => array( 'name' => '日本 Campaign' ),
				'spring-promo' => array( 'name' => 'Spring Promo' ),
			),
		);

		$id = PresetsService::create( array( 'name' => '日本 Campaign' ) )->data()['id'];

		utmatra_assert_true( $legacy !== $id );
		utmatra_assert_same( array( 'spring-promo', $id ), array_keys( get_option( 'utmatra_presets' ) ) );
	},
);
