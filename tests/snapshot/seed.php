<?php
/**
 * Snapshot harness: reset the sandbox to a fixed, known Utmatra state.
 *
 * Run with `wp eval-file seed.php`. Every run produces the same state: all
 * Utmatra options except the DB version and salt are deleted and rewritten,
 * the events table is truncated (so ids restart at 1) and refilled. Event
 * times are fixed site-local clock times on days counted back from today,
 * so reports cover the same rows whatever the date; calls.php masks the
 * dates relative to today. No seeded event falls on the day the 7- or
 * 30-day previous-period window ends, whose cut-off depends on the time of
 * day of the run.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Capabilities;
use Utmatra\DB\Schema;

defined( 'ABSPATH' ) || exit;

global $wpdb;

update_option( 'timezone_string', 'Africa/Johannesburg' );
update_option( 'gmt_offset', '' );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'admin_email', 'admin@example.test' );
update_option( 'blogname', 'Utmatra Snapshot' );
flush_rewrite_rules();

if ( ! get_user_by( 'login', 'snapshot_subscriber' ) ) {
	wp_insert_user(
		array(
			'user_login' => 'snapshot_subscriber',
			'user_pass'  => wp_generate_password(),
			'user_email' => 'subscriber@example.test',
			'role'       => 'subscriber',
		)
	);
}

// A role holding only the view tier, recreated so its capabilities are exact.
remove_role( 'snapshot_viewer' );
add_role( 'snapshot_viewer', 'Snapshot Viewer', array( Capabilities::VIEW_REPORTS => true ) );
if ( ! get_user_by( 'login', 'snapshot_viewer' ) ) {
	wp_insert_user(
		array(
			'user_login' => 'snapshot_viewer',
			'user_pass'  => wp_generate_password(),
			'user_email' => 'viewer@example.test',
			'role'       => 'snapshot_viewer',
		)
	);
}

// Options: everything Utmatra stores, except the schema version and salt.
$utmatra_snapshot_keep = array( 'utmatra_db_version', 'utmatra_salt' );
foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", 'utmatra\_%', '\_transient\_utmatra\_%', '\_transient\_timeout\_utmatra\_%' ) ) as $utmatra_snapshot_option ) {
	if ( ! in_array( $utmatra_snapshot_option, $utmatra_snapshot_keep, true ) ) {
		delete_option( $utmatra_snapshot_option );
	}
}

update_option( 'utmatra_retention_days', 180 );
update_option( 'utmatra_weekly_email', 0 );
update_option( 'utmatra_cookie_days', 90 );
update_option( 'utmatra_custom_params', array( 'coupon' ) );
update_option( 'utmatra_consent_category', 'marketing' );
update_option( 'utmatra_track_visits', 1 );
update_option( 'utmatra_webhook_url', 'https://hooks.example.test/utmatra' );
update_option( 'utmatra_webhook_secret', 'snapshot-secret' );
update_option( 'utmatra_webhook_preset', 'generic' );
update_option(
	'utmatra_webhook_events',
	array(
		'orders' => true,
		'leads'  => true,
	)
);

update_option(
	'utmatra_links',
	array(
		'spring-newsletter' => array(
			'label'        => 'Spring Newsletter',
			'destination'  => 'https://example.test/spring/',
			'utm_source'   => 'newsletter',
			'utm_medium'   => 'email',
			'utm_campaign' => 'spring-sale',
			'utm_id'       => 'nl-01',
			'utm_term'     => '',
			'utm_content'  => 'header-button',
			'custom'       => array( 'coupon' => 'SPRING10' ),
		),
		'summer-social'     => array(
			'label'        => 'Summer Social',
			'destination'  => 'https://example.test/summer/#pricing',
			'utm_source'   => 'facebook',
			'utm_medium'   => 'paid-social',
			'utm_campaign' => 'summer-launch',
			'utm_id'       => '',
			'utm_term'     => '',
			'utm_content'  => '',
		),
		'partner-blog'      => array(
			'label'        => 'Partner Blog',
			'destination'  => 'https://example.test/?ref=partner',
			'utm_source'   => 'partner',
			'utm_medium'   => 'referral',
			'utm_campaign' => 'spring-sale',
			'utm_id'       => '',
			'utm_term'     => 'review',
			'utm_content'  => '',
		),
	)
);

update_option(
	'utmatra_presets',
	array(
		'newsletter'   => array(
			'name'        => 'Newsletter',
			'utm_source'  => 'newsletter',
			'utm_medium'  => 'email',
			'utm_term'    => '',
			'utm_content' => '',
		),
		'facebook-ads' => array(
			'name'        => 'Facebook Ads',
			'utm_source'  => 'facebook',
			'utm_medium'  => 'paid-social',
			'utm_term'    => '',
			'utm_content' => 'carousel',
		),
	)
);

// Events.
$utmatra_snapshot_table = Schema::table();
$wpdb->query( "TRUNCATE TABLE {$utmatra_snapshot_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$utmatra_snapshot_v1 = '1b4e28ba-2fa1-41d2-883f-0016d3cca427';
$utmatra_snapshot_v2 = '2c5f39cb-3ab2-42e3-994a-1127e4ddb538';
$utmatra_snapshot_v3 = '3d6a4adc-4bc3-43f4-a55b-2238f5eec649';
$utmatra_snapshot_v4 = '4e7b5bed-5cd4-44a5-b66c-3349a6ffd75a';
$utmatra_snapshot_v5 = '5f8c6cfe-6de5-45b6-877d-445ab7aae86b';
$utmatra_snapshot_v6 = '6a9d7d0f-7ef6-46c7-988e-556bc8bf97c0';
$utmatra_snapshot_order_url = 'https://example.test/checkout/order-received/104/?key=wc_order_snap104';

// Days ago, site-local time, then the row fields.
$utmatra_snapshot_events = array(
	array( 50, '10:00', array( 'link_id' => 'spring-newsletter', 'visitor_id' => $utmatra_snapshot_v4, 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'spring-sale', 'channel' => 'email', 'device' => 'desktop', 'destination' => 'https://example.test/spring/' ) ),
	array( 40, '10:00', array( 'link_id' => 'spring-newsletter', 'visitor_id' => $utmatra_snapshot_v4, 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'spring-sale', 'channel' => 'email', 'device' => 'mobile', 'destination' => 'https://example.test/spring/' ) ),
	array( 20, '08:00', array( 'link_id' => 'partner-blog', 'visitor_id' => $utmatra_snapshot_v3, 'utm_source' => 'partner', 'utm_medium' => 'referral', 'utm_campaign' => 'spring-sale', 'utm_term' => 'review', 'channel' => 'referral', 'device' => 'desktop', 'referrer' => 'https://partner.example.org/review/', 'destination' => 'https://example.test/?ref=partner' ) ),
	array( 12, '10:00', array( 'link_id' => 'spring-newsletter', 'visitor_id' => $utmatra_snapshot_v1, 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'spring-sale', 'utm_id' => 'nl-01', 'utm_content' => 'header-button', 'channel' => 'email', 'device' => 'desktop', 'destination' => 'https://example.test/spring/', 'custom' => '{"coupon":"SPRING10"}' ) ),
	array( 12, '10:05', array( 'link_id' => 'visit', 'visitor_id' => $utmatra_snapshot_v1, 'channel' => 'organic_search', 'device' => 'desktop', 'referrer' => 'https://www.google.com/', 'destination' => 'https://example.test/shop/' ) ),
	array( 12, '11:00', array( 'link_id' => 'order', 'visitor_id' => $utmatra_snapshot_v1, 'is_conversion' => 1, 'touch' => 'last', 'order_total' => '129.5000', 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'spring-sale', 'channel' => 'email', 'device' => 'desktop', 'meta' => '{"order_id":101,"currency":"ZAR"}' ) ),
	array( 9, '12:00', array( 'link_id' => 'summer-social', 'visitor_id' => $utmatra_snapshot_v5, 'utm_source' => 'facebook', 'utm_medium' => 'paid-social', 'utm_campaign' => 'summer-launch', 'channel' => 'paid_social', 'device' => 'mobile', 'fbclid' => 'fb.snap.5', 'destination' => 'https://example.test/summer/' ) ),
	array( 6, '15:30', array( 'link_id' => 'incoming', 'visitor_id' => $utmatra_snapshot_v5, 'utm_source' => 'chatgpt.com', 'channel' => 'ai_assistant', 'device' => 'desktop', 'referrer' => 'https://chatgpt.com/', 'destination' => 'https://example.test/guide/' ) ),
	array( 5, '14:00', array( 'link_id' => 'visit', 'visitor_id' => $utmatra_snapshot_v3, 'channel' => 'organic_social', 'device' => 'tablet', 'referrer' => 'https://t.co/', 'destination' => 'https://example.test/' ) ),
	array( 4, '13:00', array( 'link_id' => 'order', 'visitor_id' => '', 'is_conversion' => 1, 'touch' => 'last', 'order_total' => '19.9900', 'channel' => 'direct', 'device' => 'desktop', 'meta' => '{"order_id":102,"currency":"ZAR"}' ) ),
	array( 3, '09:00', array( 'link_id' => 'summer-social', 'visitor_id' => $utmatra_snapshot_v2, 'utm_source' => 'facebook', 'utm_medium' => 'paid-social', 'utm_campaign' => 'summer-launch', 'channel' => 'paid_social', 'device' => 'mobile', 'fbclid' => 'fb.snap.2', 'destination' => 'https://example.test/summer/', 'custom' => '{"coupon":"SUMMER"}' ) ),
	array( 2, '09:30', array( 'link_id' => 'incoming', 'visitor_id' => $utmatra_snapshot_v2, 'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'summer-launch', 'utm_term' => 'utm tracking', 'gclid' => 'gcl.snap.2', 'channel' => 'paid_search', 'device' => 'mobile', 'destination' => 'https://example.test/summer/' ) ),
	array( 2, '09:45', array( 'link_id' => 'lead', 'visitor_id' => $utmatra_snapshot_v2, 'is_conversion' => 1, 'touch' => 'last', 'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'summer-launch', 'channel' => 'paid_search', 'device' => 'mobile', 'meta' => '{"form":"Contact","plugin":"cf7"}' ) ),
	array( 2, '18:00', array( 'link_id' => 'visit', 'visitor_id' => '', 'channel' => 'direct', 'device' => 'desktop', 'destination' => 'https://example.test/about/' ) ),
	array( 1, '10:00', array( 'link_id' => 'spring-newsletter', 'visitor_id' => $utmatra_snapshot_v1, 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'spring-sale', 'channel' => 'email', 'device' => 'desktop', 'destination' => 'https://example.test/spring/' ) ),
	array( 1, '16:00', array( 'link_id' => 'order', 'visitor_id' => $utmatra_snapshot_v3, 'is_conversion' => 1, 'touch' => 'last', 'order_total' => '59.0000', 'utm_source' => 'partner', 'utm_medium' => 'referral', 'utm_campaign' => 'spring-sale', 'channel' => 'referral', 'device' => 'tablet', 'meta' => '{"order_id":103,"currency":"ZAR"}' ) ),
	// WooCommerce order keys in destinations and referrers, older than every
	// other call's window so only the order-key calls return them.
	array( 800, '10:00', array( 'link_id' => 'visit', 'visitor_id' => $utmatra_snapshot_v6, 'channel' => 'organic_search', 'device' => 'desktop', 'referrer' => 'https://www.google.com/', 'destination' => $utmatra_snapshot_order_url . '&lang=en' ) ),
	array( 800, '10:05', array( 'link_id' => 'incoming', 'visitor_id' => $utmatra_snapshot_v6, 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'spring-sale', 'channel' => 'email', 'device' => 'desktop', 'referrer' => $utmatra_snapshot_order_url, 'destination' => 'https://example.test/?download_file=9&order=wc_order_snap104&key=foo' ) ),
	array( 800, '10:10', array( 'link_id' => 'lead', 'visitor_id' => $utmatra_snapshot_v6, 'is_conversion' => 1, 'touch' => 'last', 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'spring-sale', 'channel' => 'email', 'device' => 'desktop', 'destination' => 'https://example.test/checkout/order-pay/104/?pay_for_order=true&key=wc_order_snap104', 'meta' => '{"form":"Feedback","plugin":"cf7"}' ) ),
	array( 800, '10:15', array( 'link_id' => 'order', 'visitor_id' => $utmatra_snapshot_v6, 'is_conversion' => 1, 'touch' => 'last', 'order_total' => '42.0000', 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'spring-sale', 'channel' => 'email', 'device' => 'desktop', 'destination' => $utmatra_snapshot_order_url, 'meta' => '{"order_id":104,"currency":"ZAR"}' ) ),
);

$utmatra_snapshot_today = new DateTimeImmutable( 'today', wp_timezone() );
foreach ( $utmatra_snapshot_events as $utmatra_snapshot_event ) {
	list( $utmatra_snapshot_days, $utmatra_snapshot_time, $utmatra_snapshot_row ) = $utmatra_snapshot_event;

	$utmatra_snapshot_local                = $utmatra_snapshot_today->modify( "-{$utmatra_snapshot_days} days {$utmatra_snapshot_time}" );
	$utmatra_snapshot_row['clicked_at']    = $utmatra_snapshot_local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	$utmatra_snapshot_row['ip_hash']       = hash( 'sha256', 'snapshot' );
	$utmatra_snapshot_row['user_agent']    = 'SnapshotHarness/1.0';
	$utmatra_snapshot_row['is_conversion'] = $utmatra_snapshot_row['is_conversion'] ?? 0;

	// Explicit formats, as Tracker::record() passes: without them core types
	// link_id as %d (its wp_links column) and stores every slug as 0.
	$utmatra_snapshot_formats = array();
	foreach ( array_keys( $utmatra_snapshot_row ) as $utmatra_snapshot_column ) {
		if ( 'is_conversion' === $utmatra_snapshot_column ) {
			$utmatra_snapshot_formats[] = '%d';
		} elseif ( 'order_total' === $utmatra_snapshot_column ) {
			$utmatra_snapshot_formats[] = '%f';
		} else {
			$utmatra_snapshot_formats[] = '%s';
		}
	}

	$wpdb->insert( $utmatra_snapshot_table, $utmatra_snapshot_row, $utmatra_snapshot_formats );
}

echo 'Seeded ' . count( $utmatra_snapshot_events ) . " events.\n";
