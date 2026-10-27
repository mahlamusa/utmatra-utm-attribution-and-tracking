<?php
/**
 * Unit tests for EventsService.
 *
 * $wpdb is the bootstrap stub: queries are recorded in the `queries` test
 * state and the read methods return the `db_rows` / `db_var` state.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Services\EventsService;

return array(
	'event kind derives lead order visit and click'      => static function () {
		utmatra_assert_same( 'lead', EventsService::event_kind( array( 'link_id' => 'lead', 'is_conversion' => '1' ) ) );
		utmatra_assert_same( 'order', EventsService::event_kind( array( 'link_id' => 'order' ) ) );
		utmatra_assert_same( 'order', EventsService::event_kind( array( 'link_id' => 'spring', 'is_conversion' => '1' ) ) );
		utmatra_assert_same( 'visit', EventsService::event_kind( array( 'link_id' => 'visit' ) ) );
		utmatra_assert_same( 'click', EventsService::event_kind( array( 'link_id' => 'incoming' ) ) );
	},

	'format event casts values and keeps json objects'   => static function () {
		$event = EventsService::format_event(
			array(
				'id'            => '7',
				'link_id'       => 'order',
				'is_conversion' => '1',
				'order_total'   => '12.50',
				'custom'        => '{"promo":"x"}',
				'meta'          => '[]',
			)
		);

		utmatra_assert_same( 7, $event['id'] );
		utmatra_assert_true( $event['is_conversion'] );
		utmatra_assert_same( 12.5, $event['order_total'] );
		utmatra_assert_same( 'x', $event['custom']->promo );
		utmatra_assert_instance_of( stdClass::class, $event['meta'] );
		utmatra_assert_same( '', $event['utm_source'] );
		utmatra_assert_same( 'order', $event['kind'] );
		utmatra_assert_false( array_key_exists( 'ip_hash', $event ) );
		utmatra_assert_false( array_key_exists( 'user_agent', $event ) );
	},

	'format event order total is null when empty'        => static function () {
		utmatra_assert_same( null, EventsService::format_event( array( 'id' => 1, 'order_total' => '' ) )['order_total'] );
		utmatra_assert_same( null, EventsService::format_event( array( 'id' => 1 ) )['order_total'] );
	},

	'format event strips the order key from order destinations' => static function () {
		$order = EventsService::format_event(
			array(
				'id'          => 1,
				'link_id'     => 'order',
				'destination' => 'https://example.test/checkout/order-received/5/?key=wc_order_abc&lang=en#top',
			)
		);
		$legacy = EventsService::format_event(
			array(
				'id'            => 2,
				'link_id'       => 'spring',
				'is_conversion' => '1',
				'destination'   => 'https://example.test/?page_id=7&order-received=5&key=wc_order_abc',
			)
		);
		$keyless = 'https://example.test/checkout/order-received/5/?a=b%20c';

		utmatra_assert_same( 'https://example.test/checkout/order-received/5/?lang=en#top', $order['destination'] );
		utmatra_assert_same( 'https://example.test/?page_id=7&order-received=5', $legacy['destination'] );
		utmatra_assert_same( $keyless, EventsService::format_event( array( 'id' => 3, 'link_id' => 'order', 'destination' => $keyless ) )['destination'] );
	},

	'format event keeps the key on non order rows'       => static function () {
		$url = 'https://example.test/?key=abc&ref=x';

		foreach ( array( 'incoming', 'visit', 'spring' ) as $link_id ) {
			utmatra_assert_same( $url, EventsService::format_event( array( 'id' => 1, 'link_id' => $link_id, 'destination' => $url ) )['destination'] );
		}
		utmatra_assert_same( $url, EventsService::format_event( array( 'id' => 1, 'link_id' => 'lead', 'is_conversion' => '1', 'destination' => $url ) )['destination'] );
	},

	'format event strips a woocommerce order key from every row' => static function () {
		$order_url = 'https://example.test/checkout/order-received/5/?key=wc_order_abc&lang=en';
		$stripped  = 'https://example.test/checkout/order-received/5/?lang=en';
		$rows      = array(
			array( 'link_id' => 'visit' ),
			array( 'link_id' => 'incoming' ),
			array( 'link_id' => 'spring' ),
			array(
				'link_id'       => 'lead',
				'is_conversion' => '1',
			),
		);

		foreach ( $rows as $row ) {
			$event = EventsService::format_event( $row + array( 'id' => 1, 'destination' => $order_url, 'referrer' => $order_url ) );
			utmatra_assert_same( $stripped, $event['destination'] );
			utmatra_assert_same( $stripped, $event['referrer'] );
		}
	},

	'format event keeps a non woocommerce key in the referrer' => static function () {
		$url = 'https://example.test/?key=foo&ref=x';

		foreach ( array( 'spring', 'visit', 'order' ) as $link_id ) {
			utmatra_assert_same( $url, EventsService::format_event( array( 'id' => 1, 'link_id' => $link_id, 'referrer' => $url ) )['referrer'] );
		}
	},

	'get events clamps per page and page'                => static function () {
		$GLOBALS['utmatra_test']['db_var'] = '401';
		$result                            = EventsService::get_events(
			array(
				'per_page' => 500,
				'page'     => 0,
			)
		);

		utmatra_assert_same( 200, $result['per_page'] );
		utmatra_assert_same( 1, $result['page'] );
		utmatra_assert_same( 401, $result['total'] );
		utmatra_assert_same( 3, $result['total_pages'] );
		utmatra_assert_true( false !== strpos( $GLOBALS['utmatra_test']['queries'][0], 'LIMIT 200 OFFSET 0' ) );
	},

	'get events applies the filters'                     => static function () {
		$GLOBALS['utmatra_test']['db_rows'] = array( array( 'id' => '3', 'link_id' => 'lead' ) );
		$result                             = EventsService::get_events(
			array(
				'kind'       => 'lead',
				'channel'    => 'Paid-Search',
				'visitor_id' => '0b5f0c8e-2c1a-4e7a-9f0e-6c1f2a3b4c5d',
			)
		);

		$query = $GLOBALS['utmatra_test']['queries'][0];
		utmatra_assert_true( false !== strpos( $query, "AND channel = 'paid-search'" ) );
		utmatra_assert_true( false !== strpos( $query, "AND visitor_id = '0b5f0c8e-2c1a-4e7a-9f0e-6c1f2a3b4c5d'" ) );
		utmatra_assert_true( false !== strpos( $query, "AND link_id = 'lead'" ) );
		utmatra_assert_same( 50, $result['per_page'] );
		utmatra_assert_same( 'lead', $result['events'][0]['kind'] );
	},

	'export spreads custom params and drops the raw json' => static function () {
		$GLOBALS['utmatra_test']['options']['utmatra_custom_params'] = array( 'promo' );
		$GLOBALS['utmatra_test']['db_rows']                          = array(
			array(
				'link_id' => 'spring',
				'custom'  => '{"promo":"x"}',
			),
			array(
				'link_id' => 'spring',
				'custom'  => null,
			),
		);

		$rows = EventsService::export( array(), EventsService::REST_EXPORT_LIMIT );

		utmatra_assert_same( 'x', $rows[0]['promo'] );
		utmatra_assert_same( '', $rows[1]['promo'] );
		utmatra_assert_false( array_key_exists( 'custom', $rows[0] ) );
		utmatra_assert_true( false !== strpos( $GLOBALS['utmatra_test']['queries'][0], 'LIMIT 10000' ) );
		utmatra_assert_true( false !== strpos( $GLOBALS['utmatra_test']['queries'][0], "AND link_id != 'visit'" ) );
	},

	'export keeps a non woocommerce key on non order rows' => static function () {
		$GLOBALS['utmatra_test']['db_rows'] = array(
			array(
				'link_id'       => 'order',
				'is_conversion' => '1',
				'destination'   => 'https://example.test/checkout/order-received/5/?key=wc_order_abc',
			),
			array(
				'link_id'       => 'incoming',
				'is_conversion' => '0',
				'destination'   => 'https://example.test/?key=abc',
			),
		);

		$rows = EventsService::export( array(), 10 );

		utmatra_assert_same( 'https://example.test/checkout/order-received/5/', $rows[0]['destination'] );
		utmatra_assert_same( 'https://example.test/?key=abc', $rows[1]['destination'] );
	},

	'export strips a woocommerce order key from destinations and referrers' => static function () {
		$GLOBALS['utmatra_test']['db_rows'] = array(
			array(
				'link_id'       => 'visit',
				'is_conversion' => '0',
				'destination'   => 'https://example.test/checkout/order-pay/5/?pay_for_order=true&key=wc_order_abc',
				'referrer'      => 'https://example.test/?key=foo',
			),
			array(
				'link_id'       => 'spring',
				'is_conversion' => '0',
				'destination'   => 'https://example.test/spring/?key=foo',
				'referrer'      => 'https://example.test/checkout/order-received/5/?key=wc_order_abc',
			),
		);

		$rows = EventsService::export( array( 'include_visits' => true ), 10 );

		utmatra_assert_same( 'https://example.test/checkout/order-pay/5/?pay_for_order=true', $rows[0]['destination'] );
		utmatra_assert_same( 'https://example.test/?key=foo', $rows[0]['referrer'] );
		utmatra_assert_same( 'https://example.test/spring/?key=foo', $rows[1]['destination'] );
		utmatra_assert_same( 'https://example.test/checkout/order-received/5/', $rows[1]['referrer'] );
	},

	'export include visits keeps landing visits'         => static function () {
		EventsService::export( array( 'include_visits' => true ), 10 );
		utmatra_assert_false( strpos( $GLOBALS['utmatra_test']['queries'][0], "link_id != 'visit'" ) );
	},

	'export sample skips the count when not full'        => static function () {
		$GLOBALS['utmatra_test']['db_rows'] = array( array( 'link_id' => 'a' ), array( 'link_id' => 'b' ) );
		$sample                             = EventsService::export_sample( array(), 5 );

		utmatra_assert_same( 2, $sample['returned'] );
		utmatra_assert_same( 2, $sample['total_rows'] );
		utmatra_assert_false( $sample['truncated'] );
		utmatra_assert_same( 1, count( $GLOBALS['utmatra_test']['queries'] ) );
	},

	'export sample is truncated when more rows match'    => static function () {
		$GLOBALS['utmatra_test']['db_rows'] = array( array( 'link_id' => 'a' ), array( 'link_id' => 'b' ) );
		$GLOBALS['utmatra_test']['db_var']  = '9';
		$sample                             = EventsService::export_sample( array(), 2 );

		utmatra_assert_same( 2, $sample['returned'] );
		utmatra_assert_same( 9, $sample['total_rows'] );
		utmatra_assert_true( $sample['truncated'] );
		utmatra_assert_true( 0 === strpos( $GLOBALS['utmatra_test']['queries'][1], 'SELECT COUNT(*)' ) );
	},

	'export sample is not truncated on an exact fit'     => static function () {
		$GLOBALS['utmatra_test']['db_rows'] = array( array( 'link_id' => 'a' ), array( 'link_id' => 'b' ) );
		$GLOBALS['utmatra_test']['db_var']  = '2';

		utmatra_assert_false( EventsService::export_sample( array(), 2 )['truncated'] );
	},
);
