<?php
/**
 * Unit tests for LeadAction, the public utmatra_record_lead action.
 *
 * Recorder::record() writes to the database, so it is not loaded here: the
 * record() cases cover the paths that must stop before reaching it (a
 * call that did reach it would fail on the missing class), and the mapping
 * is tested through to_record().
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Insights\LeadAction;

return array(
	'hook name'                                         => static function () {
		utmatra_assert_same( 'utmatra_record_lead', LeadAction::HOOK );
	},

	'register hooks record on the public action'        => static function () {
		LeadAction::register();
		utmatra_assert_same( array( array( LeadAction::class, 'record' ) ), $GLOBALS['utmatra_test']['actions']['utmatra_record_lead'] );
	},

	'maps the documented shape onto the record'         => static function () {
		$posted = array(
			'utm_source' => 'google',
			'ch'         => 'paid_search',
		);
		$fields = array( 'Name' => 'Ann' );
		$before = time();

		$record = LeadAction::to_record(
			array(
				'source'     => 'My-Theme',
				'form_id'    => 7,
				'form_title' => '<b>Contact</b>',
				'lead_id'    => 42,
				'url'        => 'https://example.test/contact/',
				'fields'     => $fields,
				'unknown'    => 'dropped',
			),
			$posted
		);

		utmatra_assert_same( 'my-theme', $record['integration'] );
		utmatra_assert_same( array( 'form_id', 'form_title', 'posted', 'created_at', 'lead_id', 'page_url', 'fields' ), array_keys( $record['data'] ) );
		utmatra_assert_same( '7', $record['data']['form_id'] );
		utmatra_assert_same( 'Contact', $record['data']['form_title'] );
		utmatra_assert_same( $posted, $record['data']['posted'] );
		utmatra_assert_true( $record['data']['created_at'] >= $before );
		utmatra_assert_same( '42', $record['data']['lead_id'] );
		utmatra_assert_same( 'https://example.test/contact/', $record['data']['page_url'] );
		utmatra_assert_same( $fields, $record['data']['fields'] );
	},

	'optional keys default to empty and are left out'   => static function () {
		$record = LeadAction::to_record( array( 'source' => 'my-theme' ), array() );

		utmatra_assert_same( '', $record['data']['form_id'] );
		utmatra_assert_same( '', $record['data']['form_title'] );
		utmatra_assert_same( '', $record['data']['lead_id'] );
		utmatra_assert_false( array_key_exists( 'page_url', $record['data'] ), 'no url means the referer is used' );
		utmatra_assert_false( array_key_exists( 'fields', $record['data'] ), 'no fields key' );
	},

	'non-scalar values become empty strings'            => static function () {
		$record = LeadAction::to_record(
			array(
				'source'  => 'my-theme',
				'form_id' => array( 1 ),
				'lead_id' => new stdClass(),
				'url'     => array( 'https://example.test/' ),
				'fields'  => 'not-an-array',
			),
			array()
		);

		utmatra_assert_same( '', $record['data']['form_id'] );
		utmatra_assert_same( '', $record['data']['lead_id'] );
		utmatra_assert_false( array_key_exists( 'page_url', $record['data'] ) );
		utmatra_assert_false( array_key_exists( 'fields', $record['data'] ) );
	},

	'lead id is capped at 64 characters'                => static function () {
		$record = LeadAction::to_record(
			array(
				'source'  => 'my-theme',
				'lead_id' => str_repeat( 'a', 100 ),
			),
			array()
		);

		utmatra_assert_same( 64, strlen( $record['data']['lead_id'] ) );
	},

	'a missing, empty or non-string source is rejected' => static function () {
		utmatra_assert_same( null, LeadAction::to_record( array( 'form_id' => 'contact' ), array() ) );
		utmatra_assert_same( null, LeadAction::to_record( array( 'source' => '' ), array() ) );
		utmatra_assert_same( null, LeadAction::to_record( array( 'source' => '!!!' ), array() ) );
		utmatra_assert_same( null, LeadAction::to_record( array( 'source' => 5 ), array() ) );
	},

	'record ignores a lead that is not an array'        => static function () {
		LeadAction::record( 'my-theme' );
		LeadAction::record( null );
		utmatra_assert_same( array(), $GLOBALS['utmatra_test']['options'] );
	},

	'record skips a visitor without attribution data'   => static function () {
		LeadAction::record( array( 'source' => 'my-theme' ) );
		utmatra_assert_same( array(), $GLOBALS['utmatra_test']['options'] );
	},
);
