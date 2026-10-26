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
	 * @return string
	 */
	public function get_error_code() {
		return $this->code;
	}

	/**
	 * Error message.
	 *
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}

	/**
	 * Error data.
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
	 * @return mixed
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * HTTP status.
	 *
	 * @return int
	 */
	public function get_status() {
		return $this->status;
	}
}

/**
 * Minimal $wpdb: prepare() quotes %s and casts %d like wpdb does.
 *
 * @since 1.1.0
 */
class Utmatra_Test_Wpdb {

	/**
	 * Substitute the placeholders.
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
}
$GLOBALS['wpdb'] = new Utmatra_Test_Wpdb();

$utmatra_src = dirname( __DIR__, 2 ) . '/src/';
require_once $utmatra_src . 'Capabilities.php';
require_once $utmatra_src . 'Services/Support/Result.php';
require_once $utmatra_src . 'Services/Support/ResultAdapter.php';
require_once $utmatra_src . 'Services/Support/DateRange.php';
require_once $utmatra_src . 'Abilities/AbilityDefinition.php';
require_once $utmatra_src . 'Abilities/OptIn.php';
require_once $utmatra_src . 'Abilities/AbilitiesRegistrar.php';
