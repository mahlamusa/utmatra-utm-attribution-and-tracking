<?php
/**
 * Unit tests for LinksService writes under the links option lock.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Services\LinksService;

return array(
	'creating the same slug twice returns 201 then 409' => static function () {
		$input = array(
			'id'          => 'spring',
			'destination' => 'https://example.com/',
		);

		utmatra_assert_same( 201, LinksService::create( $input )->status() );
		$second = LinksService::create( $input );

		utmatra_assert_same( 409, $second->status() );
		utmatra_assert_same( 'utmatra_link_exists', $second->code() );
		utmatra_assert_same( array( 'spring' ), array_keys( get_option( 'utmatra_links' ) ) );
	},

	'update returns the saved link with its click count and keeps unconfigured custom values' => static function () {
		$GLOBALS['utmatra_test']['options']['utmatra_links'] = array(
			'spring' => array(
				'label'       => 'Spring',
				'destination' => 'https://example.com/',
				'custom'      => array( 'coupon' => 'SAVE10' ),
			),
		);
		$GLOBALS['utmatra_test']['db_var']                   = '7';

		$result = LinksService::update(
			'spring',
			array(
				'label'       => 'Spring sale',
				'destination' => 'https://example.com/sale',
			)
		);

		utmatra_assert_same( 200, $result->status() );
		utmatra_assert_same( 'Spring sale', $result->data()['label'] );
		utmatra_assert_same( 7, $result->data()['clicks'] );
		utmatra_assert_same( array( 'coupon' => 'SAVE10' ), get_option( 'utmatra_links' )['spring']['custom'] );
	},

	'update and delete of an unknown slug return 404'   => static function () {
		utmatra_assert_same( 404, LinksService::update( 'missing', array( 'destination' => 'https://example.com/' ) )->status() );
		utmatra_assert_same( 404, LinksService::delete( 'missing' )->status() );
	},

	'delete removes the link'                           => static function () {
		$GLOBALS['utmatra_test']['options']['utmatra_links'] = array( 'spring' => array( 'label' => 'Spring' ) );

		utmatra_assert_same( array( 'deleted' => true ), LinksService::delete( 'spring' )->data() );
		utmatra_assert_same( array(), get_option( 'utmatra_links' ) );
	},
);
