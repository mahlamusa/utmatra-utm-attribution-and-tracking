<?php
/**
 * Result — the outcome a service returns to its REST or ability adapter.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services\Support
 */

namespace Utmatra\Services\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Result
 *
 * An immutable value object: either a success carrying data and an HTTP
 * status, or an error carrying a code, a translated message and an HTTP
 * status. Services return it so they never build REST responses
 * themselves; ResultAdapter turns it into a WP_REST_Response or WP_Error.
 *
 * @since 1.1.0
 */
final class Result {

	/**
	 * Whether this is a success.
	 *
	 * @since 1.1.0
	 *
	 * @var bool
	 */
	private bool $ok;

	/**
	 * Success payload; null for an error.
	 *
	 * @since 1.1.0
	 *
	 * @var mixed
	 */
	private $data;

	/**
	 * HTTP status code.
	 *
	 * @since 1.1.0
	 *
	 * @var int
	 */
	private int $status;

	/**
	 * Machine-readable error code; empty for a success.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Human-readable error message; empty for a success.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * Use ok() or error().
	 *
	 * @since 1.1.0
	 *
	 * @param bool   $ok      Whether this is a success.
	 * @param mixed  $data    Success payload.
	 * @param int    $status  HTTP status code.
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 */
	private function __construct( bool $ok, $data, int $status, string $code, string $message ) {
		$this->ok      = $ok;
		$this->data    = $data;
		$this->status  = $status;
		$this->code    = $code;
		$this->message = $message;
	}

	/**
	 * A success.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $data   Payload, e.g. the REST response body.
	 * @param int   $status HTTP status code (200, 201, ...).
	 *
	 * @return Result
	 */
	public static function ok( $data = null, int $status = 200 ): Result {
		return new self( true, $data, $status, '', '' );
	}

	/**
	 * An error.
	 *
	 * @since 1.1.0
	 *
	 * @param string $code    Machine-readable code, e.g. `utmatra_not_found`.
	 * @param string $message Translated message shown to the user.
	 * @param int    $status  HTTP status code (400, 404, 409, ...).
	 *
	 * @return Result
	 */
	public static function error( string $code, string $message, int $status = 400 ): Result {
		return new self( false, null, $status, $code, $message );
	}

	/**
	 * Whether this is a success.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_ok(): bool {
		return $this->ok;
	}

	/**
	 * Whether this is an error.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_error(): bool {
		return ! $this->ok;
	}

	/**
	 * The success payload (null for an error).
	 *
	 * @since 1.1.0
	 *
	 * @return mixed
	 */
	public function data() {
		return $this->data;
	}

	/**
	 * The HTTP status code.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	public function status(): int {
		return $this->status;
	}

	/**
	 * The error code (empty for a success).
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function code(): string {
		return $this->code;
	}

	/**
	 * The error message (empty for a success).
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function message(): string {
		return $this->message;
	}
}
