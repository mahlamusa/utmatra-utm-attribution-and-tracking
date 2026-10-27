<?php
/**
 * Unit test bootstrap: minimal WordPress stubs plus the classes under test.
 *
 * Only what the tested classes call is stubbed. State lives in
 * $GLOBALS['utmatra_test'] and is reset before every test by
 * utmatra_test_reset().
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

define( 'ABSPATH', __DIR__ . '/' );

/**
 * Reset the stubbed WordPress state.
 *
 * @since 1.1.0
 *
 * @return void
 */
function utmatra_test_reset(): void {
	$GLOBALS['utmatra_test'] = array(
		'options'    => array(),
		'caps'       => array(),
		'filters'    => array(),
		'actions'    => array(),
		'categories' => array(),
		'abilities'  => array(),
		'routes'     => array(),
		'did_actions' => array(),
		'queries'    => array(),
		'db_rows'    => array(),
		'db_var'     => '0',
		'roles'      => array(
			'administrator' => array( 'manage_options' => true ),
			'editor'        => array( 'edit_posts' => true ),
		),
	);
}
utmatra_test_reset();

/**
 * Stub of add_filter().
 *
 * @since 1.1.0
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Ignored.
 * @param int      $args     Ignored.
 *
 * @return true
 */
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['utmatra_test']['filters'][ $hook ][] = $callback;
	return true;
}

/**
 * Stub of add_action(); records the hook and callback.
 *
 * @since 1.1.0
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Ignored.
 * @param int      $args     Ignored.
 *
 * @return true
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['utmatra_test']['actions'][ $hook ][] = $callback;
	return true;
}

/**
 * Stub of apply_filters().
 *
 * @since 1.1.0
 *
 * @param string $hook  Hook name.
 * @param mixed  $value Value to filter.
 * @param mixed  ...$args Extra arguments.
 *
 * @return mixed
 */
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['utmatra_test']['filters'][ $hook ] ?? array() as $callback ) {
		$value = $callback( $value, ...$args );
	}
	return $value;
}

/**
 * Stub of get_option().
 *
 * @since 1.1.0
 *
 * @param string $name    Option name.
 * @param mixed  $default Default value.
 *
 * @return mixed
 */
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['utmatra_test']['options'] ) ? $GLOBALS['utmatra_test']['options'][ $name ] : $default;
}

/**
 * Stub of current_user_can(); true for capabilities in the test state.
 *
 * @since 1.1.0
 *
 * @param string $cap Capability.
 *
 * @return bool
 */
function current_user_can( $cap ) {
	return in_array( $cap, $GLOBALS['utmatra_test']['caps'], true );
}

/**
 * Stub of update_option().
 *
 * @since 1.1.0
 *
 * @param string $name  Option name.
 * @param mixed  $value Option value.
 *
 * @return true
 */
function update_option( $name, $value ) {
	$GLOBALS['utmatra_test']['options'][ $name ] = $value;
	return true;
}

/**
 * Stub of delete_option().
 *
 * @since 1.1.0
 *
 * @param string $name Option name.
 *
 * @return true
 */
function delete_option( $name ) {
	unset( $GLOBALS['utmatra_test']['options'][ $name ] );
	return true;
}

/**
 * Stub of get_role(); roles live in the test state.
 *
 * @since 1.1.0
 *
 * @param string $name Role name.
 *
 * @return Utmatra_Test_Role|null
 */
function get_role( $name ) {
	return isset( $GLOBALS['utmatra_test']['roles'][ $name ] ) ? new Utmatra_Test_Role( $name ) : null;
}

/**
 * Stub of wp_roles(); exposes every test role as `role_objects`.
 *
 * @since 1.1.0
 *
 * @return object
 */
function wp_roles() {
	$roles = array();
	foreach ( array_keys( $GLOBALS['utmatra_test']['roles'] ) as $name ) {
		$roles[ $name ] = new Utmatra_Test_Role( $name );
	}
	return (object) array( 'role_objects' => $roles );
}

/**
 * Minimal WP_Role whose capabilities are stored in the test state.
 *
 * @since 1.1.0
 */
class Utmatra_Test_Role {

	/**
	 * Role name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Capability => grant, as read when the role was fetched.
	 *
	 * @var array<string, bool>
	 */
	public $capabilities;

	/**
	 * Load a role from the test state.
	 *
	 * @since 1.1.0
	 *
	 * @param string $name Role name.
	 */
	public function __construct( $name ) {
		$this->name         = $name;
		$this->capabilities = $GLOBALS['utmatra_test']['roles'][ $name ];
	}

	/**
	 * Grant a capability.
	 *
	 * @since 1.1.0
	 *
	 * @param string $cap Capability.
	 *
	 * @return void
	 */
	public function add_cap( $cap ) {
		$GLOBALS['utmatra_test']['roles'][ $this->name ][ $cap ] = true;
		$this->capabilities[ $cap ]                              = true;
	}

	/**
	 * Remove a capability.
	 *
	 * @since 1.1.0
	 *
	 * @param string $cap Capability.
	 *
	 * @return void
	 */
	public function remove_cap( $cap ) {
		unset( $GLOBALS['utmatra_test']['roles'][ $this->name ][ $cap ], $this->capabilities[ $cap ] );
	}
}

/**
 * Stub of register_rest_route(); records each method's endpoint by "METHOD route".
 *
 * @since 1.1.0
 *
 * @param string $route_namespace Route namespace.
 * @param string $route           Route pattern.
 * @param array  $args            One endpoint, or a list of endpoints.
 *
 * @return true
 */
function register_rest_route( $route_namespace, $route, $args ) {
	$endpoints = isset( $args['methods'] ) ? array( $args ) : $args;
	foreach ( $endpoints as $endpoint ) {
		$GLOBALS['utmatra_test']['routes'][ $endpoint['methods'] . ' /' . $route_namespace . $route ] = $endpoint;
	}
	return true;
}

/**
 * Stub of __(); returns the text untranslated.
 *
 * @since 1.1.0
 *
 * @param string $text   Text.
 * @param string $domain Text domain.
 *
 * @return string
 */
function __( $text, $domain = 'default' ) {
	return $text;
}

/**
 * Stub of wp_register_ability_category(); records the call.
 *
 * @since 1.1.0
 *
 * @param string $slug Category slug.
 * @param array  $args Category arguments.
 *
 * @return null
 */
function wp_register_ability_category( $slug, $args ) {
	$GLOBALS['utmatra_test']['categories'][ $slug ] = $args;
	return null;
}

/**
 * Stub of wp_register_ability(); records the call.
 *
 * @since 1.1.0
 *
 * @param string $name Ability name.
 * @param array  $args Ability arguments.
 *
 * @return null
 */
function wp_register_ability( $name, $args ) {
	$GLOBALS['utmatra_test']['abilities'][ $name ] = $args;
	return null;
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Stub of sanitize_key().
	 *
	 * @since 1.1.0
	 *
	 * @param string $key Key.
	 *
	 * @return string
	 */
	function sanitize_key( $key ) {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Minimal WP_REST_Request: parameters only.
	 *
	 * @since 1.1.0
	 */
	class WP_REST_Request {

		/**
		 * Parameters.
		 *
		 * @var array<string, mixed>
		 */
		private $params = array();

		/**
		 * Set a parameter.
		 *
		 * @since 1.1.0
		 *
		 * @param string $key   Parameter name.
		 * @param mixed  $value Parameter value.
		 *
		 * @return void
		 */
		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}

		/**
		 * Get a parameter; null when absent.
		 *
		 * @since 1.1.0
		 *
		 * @param string $key Parameter name.
		 *
		 * @return mixed
		 */
		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}
	}
}

/**
 * Stub of wp_parse_url(); parse_url() without the PHP < 5.4.7 fixes.
 *
 * @since 1.1.0
 *
 * @param string $url       URL.
 * @param int    $component Component, or -1 for all.
 *
 * @return mixed
 */
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
}

/**
 * Stub of sanitize_text_field(); strips tags and trims.
 *
 * @since 1.1.0
 *
 * @param string $value Value.
 *
 * @return string
 */
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
}

/**
 * Stub of do_action(); records each call's arguments under `did_actions`.
 *
 * @since 1.1.0
 *
 * @param string $hook    Hook name.
 * @param mixed  ...$args Arguments.
 *
 * @return void
 */
function do_action( $hook, ...$args ) {
	$GLOBALS['utmatra_test']['did_actions'][ $hook ][] = $args;
}

/**
 * Stub of get_current_user_id().
 *
 * @since 1.1.0
 *
 * @return int
 */
function get_current_user_id() {
	return 7;
}

/**
 * Minimal WP: the public query vars Tracker reserves.
 *
 * @since 1.1.0
 */
class WP {

	/**
	 * Public query variables.
	 *
	 * @var string[]
	 */
	public $public_query_vars = array( 'p', 'page', 's' );
}

/**
 * Minimal WP_Error.
 *
 * @since 1.1.0
 */
class WP_Error {

	/**
	 * Error code.
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Error data.
	 *
	 * @var mixed
	 */
	public $data;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param mixed  $data    Error data.
	 */
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	/**
	 * Error code.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function get_error_code() {
		return $this->code;
	}

	/**
	 * Error message.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}

	/**
	 * Error data.
	 *
	 * @since 1.1.0
	 *
	 * @return mixed
	 */
	public function get_error_data() {
		return $this->data;
	}
}

/**
 * Minimal WP_REST_Response.
 *
 * @since 1.1.0
 */
class WP_REST_Response {

	/**
	 * Response data.
	 *
	 * @var mixed
	 */
	private $data;

	/**
	 * HTTP status.
	 *
	 * @var int
	 */
	private $status;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $data   Response data.
	 * @param int   $status HTTP status.
	 */
	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	/**
	 * Response data.
	 *
	 * @since 1.1.0
	 *
	 * @return mixed
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * HTTP status.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	public function get_status() {
		return $this->status;
	}
}

/**
 * Minimal $wpdb: prepare() quotes %s and casts %d like wpdb does; the
 * read methods record each query in the `queries` test state.
 *
 * @since 1.1.0
 */
class Utmatra_Test_Wpdb {

	/**
	 * Table prefix.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * Substitute the placeholders.
	 *
	 * @since 1.1.0
	 *
	 * @param string $query Query with %s / %d placeholders.
	 * @param mixed  ...$args Values.
	 *
	 * @return string
	 */
	public function prepare( $query, ...$args ) {
		return (string) preg_replace_callback(
			'/%([sd])/',
			static function ( $match ) use ( &$args ) {
				$value = array_shift( $args );
				return 'd' === $match[1] ? (string) (int) $value : "'" . addslashes( (string) $value ) . "'";
			},
			$query
		);
	}

	/**
	 * Record the query and return the `db_rows` test state (no rows by default).
	 *
	 * @since 1.1.0
	 *
	 * @param string $query  SQL.
	 * @param string $output Ignored.
	 *
	 * @return array
	 */
	public function get_results( $query, $output = 'OBJECT' ) {
		$GLOBALS['utmatra_test']['queries'][] = $query;
		return $GLOBALS['utmatra_test']['db_rows'];
	}

	/**
	 * Record the query and return the `db_var` test state (a zero count by default).
	 *
	 * @since 1.1.0
	 *
	 * @param string $query SQL.
	 *
	 * @return mixed
	 */
	public function get_var( $query ) {
		$GLOBALS['utmatra_test']['queries'][] = $query;
		return $GLOBALS['utmatra_test']['db_var'];
	}

	/**
	 * Record the query; return no values.
	 *
	 * @since 1.1.0
	 *
	 * @param string $query SQL.
	 *
	 * @return array
	 */
	public function get_col( $query ) {
		$GLOBALS['utmatra_test']['queries'][] = $query;
		return array();
	}
}
$GLOBALS['wpdb'] = new Utmatra_Test_Wpdb();

define( 'ARRAY_A', 'ARRAY_A' );

/**
 * Stub of wp_timezone(); the site runs on UTC.
 *
 * @since 1.1.0
 *
 * @return DateTimeZone
 */
function wp_timezone() {
	return new DateTimeZone( 'UTC' );
}

/**
 * Stub of esc_sql().
 *
 * @since 1.1.0
 *
 * @param string $value Value.
 *
 * @return string
 */
function esc_sql( $value ) {
	return addslashes( (string) $value );
}

/**
 * Stub of home_url().
 *
 * @since 1.1.0
 *
 * @return string
 */
function home_url() {
	return 'https://example.test';
}

/**
 * Stub of absint().
 *
 * @since 1.1.0
 *
 * @param mixed $value Value.
 *
 * @return int
 */
function absint( $value ) {
	return abs( (int) $value );
}

$utmatra_src = dirname( __DIR__, 2 ) . '/src/';
require_once $utmatra_src . 'Tracking/Attribution.php';
require_once $utmatra_src . 'Tracking/Tracker.php';
require_once $utmatra_src . 'Reports/ConversionWebhook.php';
require_once $utmatra_src . 'Capabilities.php';
require_once $utmatra_src . 'Services/Support/Result.php';
require_once $utmatra_src . 'Services/Support/ResultAdapter.php';
require_once $utmatra_src . 'Services/Support/DateRange.php';
require_once $utmatra_src . 'Services/Support/InputValue.php';
require_once $utmatra_src . 'Services/Support/UtmNormalizer.php';
require_once $utmatra_src . 'Services/LinkValidator.php';
require_once $utmatra_src . 'Services/PresetsService.php';
require_once $utmatra_src . 'Services/LinksService.php';
require_once $utmatra_src . 'Rest/Controllers/RequestInput.php';
require_once $utmatra_src . 'Rest/Controllers/LinksController.php';
require_once $utmatra_src . 'Rest/Controllers/PresetsController.php';
require_once $utmatra_src . 'Services/SettingsService.php';
require_once $utmatra_src . 'Rest/Controllers/SettingsController.php';
require_once $utmatra_src . 'DB/Schema.php';
require_once $utmatra_src . 'Tracking/TrafficSource.php';
require_once $utmatra_src . 'Services/StatsBreakdowns.php';
require_once $utmatra_src . 'Services/StatsService.php';
require_once $utmatra_src . 'Rest/Controllers/StatsController.php';
require_once $utmatra_src . 'Services/EventsService.php';
require_once $utmatra_src . 'Rest/Controllers/EventsController.php';
require_once $utmatra_src . 'Services/NotificationsService.php';
require_once $utmatra_src . 'Rest/Controllers/NotificationsController.php';
require_once $utmatra_src . 'Services/DiagnosticsService.php';
require_once $utmatra_src . 'Rest/Controllers/DiagnosticsController.php';
require_once $utmatra_src . 'Services/JourneysService.php';
require_once $utmatra_src . 'Rest/Controllers/JourneysController.php';
require_once $utmatra_src . 'Abilities/AbilityDefinition.php';
require_once $utmatra_src . 'Abilities/EventSchema.php';
require_once $utmatra_src . 'Abilities/OptIn.php';
require_once $utmatra_src . 'Abilities/SettingsSchema.php';
require_once $utmatra_src . 'Abilities/LinkAbilities.php';
require_once $utmatra_src . 'Abilities/AbilitiesRegistrar.php';
require_once $utmatra_src . 'Admin/RestController.php';
