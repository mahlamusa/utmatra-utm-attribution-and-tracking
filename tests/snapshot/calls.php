<?php
/**
 * Snapshot harness: call every utmatra/v1 route and record the responses.
 *
 * Run with `wp eval-file calls.php <out-dir>` right after seed.php. Each
 * call is dispatched in-process with rest_do_request() as the given user
 * (admin, a subscriber, or logged out), and written to
 * `<out-dir>/<NNN>-<name>.json` as `{ request, status, body }`.
 *
 * Masking keeps two runs identical: the site URL becomes `{home}` and
 * every Y-m-d date (also inside datetimes and keys) becomes `D-<n>`, its
 * distance in days from today, site-local. Seeded times are fixed, so the
 * time part of a datetime is left as is. Tied rows of the ranked stats
 * breakdowns are ordered by label (see utmatra_snapshot_sort_ties()).
 *
 * The calls run in order and the write calls change state, so the list is
 * only ever appended to or edited deliberately; reseed before every run.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

defined( 'ABSPATH' ) || exit;

$utmatra_snapshot_out = $args[0] ?? '';
if ( '' === $utmatra_snapshot_out || ! is_dir( $utmatra_snapshot_out ) ) {
	WP_CLI::error( 'Usage: wp eval-file calls.php <existing-out-dir>' );
}

/**
 * A site-local date `$days` days before today, Y-m-d.
 *
 * @since 1.1.0
 *
 * @param int $days Days ago.
 *
 * @return string
 */
function utmatra_snapshot_day( int $days ): string {
	return ( new DateTimeImmutable( 'today', wp_timezone() ) )->modify( "-{$days} days" )->format( 'Y-m-d' );
}

/**
 * Mask the site URL and dates in a string.
 *
 * @since 1.1.0
 *
 * @param string $value Raw string.
 *
 * @return string
 */
function utmatra_snapshot_mask_string( string $value ): string {
	$value = str_replace( array( untrailingslashit( home_url() ), untrailingslashit( site_url() ) ), '{home}', $value );

	return (string) preg_replace_callback(
		'/\b(\d{4}-\d{2}-\d{2})\b/',
		static function ( $match ) {
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $match[1], wp_timezone() );
			if ( false === $date ) {
				return $match[0];
			}
			$today = new DateTimeImmutable( 'today', wp_timezone() );
			$days  = (int) round( ( $today->getTimestamp() - $date->getTimestamp() ) / DAY_IN_SECONDS );
			return $days >= 0 ? 'D-' . $days : 'D+' . abs( $days );
		},
		$value
	);
}

/**
 * Order the ties of a ranked breakdown list by label.
 *
 * The stats breakdowns are `{ label, count }` (or `{ label, orders,
 * revenue }`) rows from `GROUP BY ... ORDER BY count DESC`, and MySQL
 * returns rows with an equal count in no defined order. Only runs of
 * adjacent rows sharing the same count (or revenue) are reordered, so a
 * change in the ranking itself still shows up as a diff.
 *
 * @since 1.1.0
 *
 * @param array<int, mixed> $items Masked list.
 *
 * @return array<int, mixed>
 */
function utmatra_snapshot_sort_ties( array $items ): array {
	foreach ( $items as $item ) {
		if ( ! is_object( $item ) || ! isset( $item->label ) || ! ( isset( $item->count ) || isset( $item->revenue ) ) ) {
			return $items;
		}
	}

	$sorted = array();
	$run    = array();
	$rank   = null;
	foreach ( $items as $item ) {
		$item_rank = (string) ( $item->count ?? $item->revenue );
		if ( null !== $rank && $item_rank !== $rank ) {
			usort( $run, 'utmatra_snapshot_compare_labels' );
			$sorted = array_merge( $sorted, $run );
			$run    = array();
		}
		$rank  = $item_rank;
		$run[] = $item;
	}
	usort( $run, 'utmatra_snapshot_compare_labels' );

	return array_merge( $sorted, $run );
}

/**
 * Compare two breakdown rows by label.
 *
 * @since 1.1.0
 *
 * @param object $a First row.
 * @param object $b Second row.
 *
 * @return int
 */
function utmatra_snapshot_compare_labels( $a, $b ): int {
	return strcmp( (string) $a->label, (string) $b->label );
}

/**
 * Mask a decoded response recursively (keys and string values).
 *
 * @since 1.1.0
 *
 * @param mixed $value Decoded JSON value.
 *
 * @return mixed
 */
function utmatra_snapshot_mask( $value ) {
	if ( is_string( $value ) ) {
		return utmatra_snapshot_mask_string( $value );
	}
	if ( is_array( $value ) ) {
		$masked = array();
		foreach ( $value as $key => $item ) {
			$masked[ is_string( $key ) ? utmatra_snapshot_mask_string( $key ) : $key ] = utmatra_snapshot_mask( $item );
		}
		return array_keys( $masked ) === range( 0, count( $masked ) - 1 ) ? utmatra_snapshot_sort_ties( $masked ) : $masked;
	}
	if ( is_object( $value ) ) {
		return (object) utmatra_snapshot_mask( (array) $value );
	}
	return $value;
}

$utmatra_snapshot_admin      = get_user_by( 'login', 'admin' );
$utmatra_snapshot_subscriber = get_user_by( 'login', 'snapshot_subscriber' );
if ( ! $utmatra_snapshot_admin ) {
	$utmatra_snapshot_admins = get_users(
		array(
			'role'    => 'administrator',
			'orderby' => 'ID',
			'number'  => 1,
		)
	);
	$utmatra_snapshot_admin  = $utmatra_snapshot_admins[0] ?? null;
}
if ( ! $utmatra_snapshot_admin || ! $utmatra_snapshot_subscriber ) {
	WP_CLI::error( 'Missing the admin or snapshot_subscriber user; run seed.php first.' );
}
$utmatra_snapshot_users = array(
	'admin'      => (int) $utmatra_snapshot_admin->ID,
	'subscriber' => (int) $utmatra_snapshot_subscriber->ID,
	'anon'       => 0,
);

$utmatra_snapshot_v1      = '1b4e28ba-2fa1-41d2-883f-0016d3cca427';
$utmatra_snapshot_v2      = '2c5f39cb-3ab2-42e3-994a-1127e4ddb538';
$utmatra_snapshot_unknown = '9a9a9a9a-9a9a-4a9a-9a9a-9a9a9a9a9a9a';
$utmatra_snapshot_since   = utmatra_snapshot_day( 10 );
$utmatra_snapshot_until   = utmatra_snapshot_day( 3 );
$utmatra_snapshot_single  = utmatra_snapshot_day( 2 );

// Name, method, route, params (query for GET/DELETE, JSON body otherwise), user.
$utmatra_snapshot_calls = array(
	// Reads.
	array( 'stats-default', 'GET', '/utmatra/v1/stats', array(), 'admin' ),
	array( 'stats-period-7', 'GET', '/utmatra/v1/stats', array( 'period' => 7 ), 'admin' ),
	array( 'stats-period-90', 'GET', '/utmatra/v1/stats', array( 'period' => 90 ), 'admin' ),
	array( 'stats-period-clamped', 'GET', '/utmatra/v1/stats', array( 'period' => 9999 ), 'admin' ),
	array( 'stats-campaign', 'GET', '/utmatra/v1/stats', array( 'campaign' => 'spring-sale' ), 'admin' ),
	array( 'stats-custom-range', 'GET', '/utmatra/v1/stats', array( 'since' => $utmatra_snapshot_since, 'until' => $utmatra_snapshot_until ), 'admin' ),
	array( 'stats-single-day', 'GET', '/utmatra/v1/stats', array( 'since' => $utmatra_snapshot_single, 'until' => $utmatra_snapshot_single ), 'admin' ),
	array( 'stats-bad-dates', 'GET', '/utmatra/v1/stats', array( 'since' => 'yesterday', 'until' => '2999-01-01' ), 'admin' ),
	array( 'links-list', 'GET', '/utmatra/v1/links', array(), 'admin' ),
	array( 'events-default', 'GET', '/utmatra/v1/events', array(), 'admin' ),
	array( 'events-page-2', 'GET', '/utmatra/v1/events', array( 'per_page' => 3, 'page' => 2 ), 'admin' ),
	array( 'events-per-page-clamped', 'GET', '/utmatra/v1/events', array( 'per_page' => 500 ), 'admin' ),
	array( 'events-period-90', 'GET', '/utmatra/v1/events', array( 'period' => 90 ), 'admin' ),
	array( 'events-campaign', 'GET', '/utmatra/v1/events', array( 'campaign' => 'summer-launch' ), 'admin' ),
	array( 'events-channel', 'GET', '/utmatra/v1/events', array( 'channel' => 'paid-search' ), 'admin' ),
	array( 'events-kind-click', 'GET', '/utmatra/v1/events', array( 'kind' => 'click' ), 'admin' ),
	array( 'events-kind-visit', 'GET', '/utmatra/v1/events', array( 'kind' => 'visit' ), 'admin' ),
	array( 'events-kind-order', 'GET', '/utmatra/v1/events', array( 'kind' => 'order' ), 'admin' ),
	array( 'events-kind-lead', 'GET', '/utmatra/v1/events', array( 'kind' => 'lead' ), 'admin' ),
	array( 'events-visitor', 'GET', '/utmatra/v1/events', array( 'visitor_id' => $utmatra_snapshot_v1 ), 'admin' ),
	array( 'events-custom-range', 'GET', '/utmatra/v1/events', array( 'since' => $utmatra_snapshot_since, 'until' => $utmatra_snapshot_until ), 'admin' ),
	array( 'events-kind-invalid', 'GET', '/utmatra/v1/events', array( 'kind' => 'bogus' ), 'admin' ),
	array( 'events-visitor-invalid', 'GET', '/utmatra/v1/events', array( 'visitor_id' => 'not-a-uuid' ), 'admin' ),
	array( 'journeys-default', 'GET', '/utmatra/v1/journeys', array(), 'admin' ),
	array( 'journeys-page-2', 'GET', '/utmatra/v1/journeys', array( 'per_page' => 1, 'page' => 2 ), 'admin' ),
	array( 'journeys-custom-range', 'GET', '/utmatra/v1/journeys', array( 'since' => $utmatra_snapshot_since, 'until' => $utmatra_snapshot_until ), 'admin' ),
	array( 'journey-visitor', 'GET', '/utmatra/v1/journeys/' . $utmatra_snapshot_v1, array(), 'admin' ),
	array( 'journey-visitor-lead', 'GET', '/utmatra/v1/journeys/' . $utmatra_snapshot_v2, array(), 'admin' ),
	array( 'journey-unknown-404', 'GET', '/utmatra/v1/journeys/' . $utmatra_snapshot_unknown, array(), 'admin' ),
	array( 'journey-bad-id-no-route', 'GET', '/utmatra/v1/journeys/NOT-A-UUID', array(), 'admin' ),
	array( 'export-default', 'GET', '/utmatra/v1/export', array(), 'admin' ),
	array( 'export-include-visits', 'GET', '/utmatra/v1/export', array( 'include_visits' => true ), 'admin' ),
	array( 'export-campaign', 'GET', '/utmatra/v1/export', array( 'campaign' => 'spring-sale', 'period' => 90 ), 'admin' ),
	array( 'export-custom-range', 'GET', '/utmatra/v1/export', array( 'since' => $utmatra_snapshot_since, 'until' => $utmatra_snapshot_until ), 'admin' ),
	array( 'diagnostics', 'GET', '/utmatra/v1/diagnostics', array(), 'admin' ),

	// Links.
	array( 'link-create', 'POST', '/utmatra/v1/links', array( 'label' => 'Black Friday', 'destination' => 'https://example.test/bf/', 'utm_source' => 'Mail Chimp', 'utm_medium' => 'Email', 'utm_campaign' => 'Black Friday 2025', 'custom' => array( 'coupon' => 'BF25', 'unknown' => 'dropped' ) ), 'admin' ),
	array( 'link-create-explicit-id', 'POST', '/utmatra/v1/links', array( 'id' => 'Explicit_ID', 'label' => '', 'destination' => 'https://example.test/x/' ), 'admin' ),
	array( 'link-create-long-label', 'POST', '/utmatra/v1/links', array( 'label' => str_repeat( 'Very long campaign label ', 4 ), 'destination' => 'https://example.test/long/' ), 'admin' ),
	array( 'link-create-duplicate-409', 'POST', '/utmatra/v1/links', array( 'label' => 'Black Friday', 'destination' => 'https://example.test/bf/' ), 'admin' ),
	array( 'link-create-reserved-400', 'POST', '/utmatra/v1/links', array( 'id' => 'visit', 'destination' => 'https://example.test/' ), 'admin' ),
	array( 'link-create-empty-slug-400', 'POST', '/utmatra/v1/links', array( 'label' => '', 'destination' => 'https://example.test/' ), 'admin' ),
	array( 'link-create-bad-destination-400', 'POST', '/utmatra/v1/links', array( 'label' => 'Bad', 'destination' => 'ftp://example.test/' ), 'admin' ),
	array( 'link-create-array-label', 'POST', '/utmatra/v1/links', array( 'label' => array( 'x' ), 'id' => 'array-label', 'destination' => 'https://example.test/' ), 'admin' ),
	array( 'link-update', 'PUT', '/utmatra/v1/links/black-friday', array( 'label' => 'Black Friday Sale', 'destination' => 'https://example.test/bf-sale/', 'utm_source' => 'mailchimp', 'utm_term' => 'Big Deals', 'custom' => array( 'coupon' => 'BF50' ) ), 'admin' ),
	array( 'link-update-seeded', 'PUT', '/utmatra/v1/links/spring-newsletter', array( 'label' => 'Spring Newsletter', 'destination' => 'https://example.test/spring/' ), 'admin' ),
	array( 'link-update-missing-404', 'PUT', '/utmatra/v1/links/no-such-link', array( 'destination' => 'https://example.test/' ), 'admin' ),
	array( 'link-update-bad-destination-400', 'PUT', '/utmatra/v1/links/black-friday', array( 'destination' => 'javascript:alert(1)' ), 'admin' ),
	array( 'link-delete', 'DELETE', '/utmatra/v1/links/explicit_id', array(), 'admin' ),
	array( 'link-delete-missing-404', 'DELETE', '/utmatra/v1/links/explicit_id', array(), 'admin' ),

	// Presets.
	array( 'preset-create', 'POST', '/utmatra/v1/presets', array( 'name' => 'Spring Promo', 'utm_source' => 'Insta Gram', 'utm_medium' => 'Social', 'utm_term' => '', 'utm_content' => 'Story Ad' ), 'admin' ),
	array( 'preset-overwrite', 'POST', '/utmatra/v1/presets', array( 'name' => 'Newsletter', 'utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_content' => 'footer' ), 'admin' ),
	array( 'preset-create-empty-400', 'POST', '/utmatra/v1/presets', array( 'name' => '   ' ), 'admin' ),
	array( 'preset-delete', 'DELETE', '/utmatra/v1/presets/spring-promo', array(), 'admin' ),
	array( 'preset-delete-missing-404', 'DELETE', '/utmatra/v1/presets/spring-promo', array(), 'admin' ),

	// Settings.
	array( 'settings-partial', 'POST', '/utmatra/v1/settings', array( 'retention_days' => 90, 'custom_params' => 'Coupon, aff coupon' ), 'admin' ),
	array( 'settings-full', 'POST', '/utmatra/v1/settings', array( 'retention_days' => 365, 'weekly_email' => true, 'cookie_days' => 1000, 'webhook_url' => 'https://hooks.example.test/new', 'webhook_preset' => 'zapier', 'webhook_orders' => false, 'webhook_leads' => true, 'consent_category' => 'nonsense', 'track_visits' => false, 'custom_params' => 'coupon aff' ), 'admin' ),
	array( 'settings-reserved-param-400', 'POST', '/utmatra/v1/settings', array( 'custom_params' => 'coupon, utm_extra' ), 'admin' ),
	array( 'settings-invalid-param-400', 'POST', '/utmatra/v1/settings', array( 'custom_params' => '1abc' ), 'admin' ),
	array( 'settings-too-many-params-400', 'POST', '/utmatra/v1/settings', array( 'custom_params' => 'xa xb xc xd xe xf xg xh xi xj xk' ), 'admin' ),
	array( 'settings-bad-preset-400', 'POST', '/utmatra/v1/settings', array( 'webhook_preset' => 'bogus' ), 'admin' ),
	array( 'test-email', 'POST', '/utmatra/v1/test-email', array(), 'admin' ),
	array( 'test-webhook', 'POST', '/utmatra/v1/test-webhook', array(), 'admin' ),
	array( 'test-webhook-preset-leads', 'POST', '/utmatra/v1/test-webhook', array( 'preset' => 'hubspot', 'leads' => true ), 'admin' ),

	// State after the writes.
	array( 'links-list-after', 'GET', '/utmatra/v1/links', array(), 'admin' ),
	array( 'export-after-settings', 'GET', '/utmatra/v1/export', array( 'period' => 90, 'include_visits' => true ), 'admin' ),
);

// Every route and method as a subscriber (403) and logged out (401).
$utmatra_snapshot_routes = array(
	array( 'stats', 'GET', '/utmatra/v1/stats' ),
	array( 'links', 'GET', '/utmatra/v1/links' ),
	array( 'link-create', 'POST', '/utmatra/v1/links' ),
	array( 'link-update', 'PUT', '/utmatra/v1/links/spring-newsletter' ),
	array( 'link-delete', 'DELETE', '/utmatra/v1/links/spring-newsletter' ),
	array( 'export', 'GET', '/utmatra/v1/export' ),
	array( 'events', 'GET', '/utmatra/v1/events' ),
	array( 'journeys', 'GET', '/utmatra/v1/journeys' ),
	array( 'journey', 'GET', '/utmatra/v1/journeys/' . $utmatra_snapshot_v1 ),
	array( 'preset-create', 'POST', '/utmatra/v1/presets' ),
	array( 'preset-delete', 'DELETE', '/utmatra/v1/presets/newsletter' ),
	array( 'test-email', 'POST', '/utmatra/v1/test-email' ),
	array( 'test-webhook', 'POST', '/utmatra/v1/test-webhook' ),
	array( 'settings', 'POST', '/utmatra/v1/settings' ),
	array( 'diagnostics', 'GET', '/utmatra/v1/diagnostics' ),
);
foreach ( array( 'subscriber', 'anon' ) as $utmatra_snapshot_user ) {
	foreach ( $utmatra_snapshot_routes as $utmatra_snapshot_route ) {
		$utmatra_snapshot_calls[] = array( 'denied-' . $utmatra_snapshot_user . '-' . $utmatra_snapshot_route[0], $utmatra_snapshot_route[1], $utmatra_snapshot_route[2], array(), $utmatra_snapshot_user );
	}
}

foreach ( $utmatra_snapshot_calls as $utmatra_snapshot_index => $utmatra_snapshot_call ) {
	list( $utmatra_snapshot_name, $utmatra_snapshot_method, $utmatra_snapshot_path, $utmatra_snapshot_params, $utmatra_snapshot_user ) = $utmatra_snapshot_call;

	wp_set_current_user( $utmatra_snapshot_users[ $utmatra_snapshot_user ] );

	$utmatra_snapshot_request = new WP_REST_Request( $utmatra_snapshot_method, $utmatra_snapshot_path );
	if ( in_array( $utmatra_snapshot_method, array( 'GET', 'DELETE' ), true ) ) {
		$utmatra_snapshot_request->set_query_params( $utmatra_snapshot_params );
	} else {
		$utmatra_snapshot_request->set_header( 'content-type', 'application/json' );
		$utmatra_snapshot_request->set_body( (string) wp_json_encode( (object) $utmatra_snapshot_params ) );
	}

	$utmatra_snapshot_response = rest_do_request( $utmatra_snapshot_request );
	$utmatra_snapshot_body     = rest_get_server()->response_to_data( $utmatra_snapshot_response, false );

	$utmatra_snapshot_record = array(
		'request' => array(
			'method' => $utmatra_snapshot_method,
			'route'  => $utmatra_snapshot_path,
			'params' => (object) $utmatra_snapshot_params,
			'user'   => $utmatra_snapshot_user,
		),
		'status'  => $utmatra_snapshot_response->get_status(),
		// Round-trip through JSON so objects and arrays mask uniformly.
		'body'    => json_decode( (string) wp_json_encode( $utmatra_snapshot_body ) ),
	);

	$utmatra_snapshot_file = sprintf( '%s/%03d-%s.json', untrailingslashit( $utmatra_snapshot_out ), $utmatra_snapshot_index + 1, $utmatra_snapshot_name );
	file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$utmatra_snapshot_file,
		wp_json_encode( utmatra_snapshot_mask( $utmatra_snapshot_record ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
	);
}

wp_set_current_user( 0 );
echo 'Recorded ' . count( $utmatra_snapshot_calls ) . ' calls in ' . $utmatra_snapshot_out . "\n";
