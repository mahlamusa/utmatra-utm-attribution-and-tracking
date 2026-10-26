<?php
/**
 * Unit tests for Result and ResultAdapter.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Services\Support\Result;
use Utmatra\Services\Support\ResultAdapter;

return array(
	'ok carries data and status'                         => static function () {
		$result = Result::ok( array( 'id' => 'a' ), 201 );
		utmatra_assert_true( $result->is_ok() );
		utmatra_assert_false( $result->is_error() );
		utmatra_assert_same( array( 'id' => 'a' ), $result->data() );
		utmatra_assert_same( 201, $result->status() );
		utmatra_assert_same( '', $result->code() );
		utmatra_assert_same( '', $result->message() );
	},

	'ok defaults to null data and 200'                   => static function () {
		$result = Result::ok();
		utmatra_assert_same( null, $result->data() );
		utmatra_assert_same( 200, $result->status() );
	},

	'error carries code, message and status'             => static function () {
		$result = Result::error( 'utmatra_conflict', 'A link with this slug already exists.', 409 );
		utmatra_assert_true( $result->is_error() );
		utmatra_assert_false( $result->is_ok() );
		utmatra_assert_same( null, $result->data() );
		utmatra_assert_same( 409, $result->status() );
		utmatra_assert_same( 'utmatra_conflict', $result->code() );
		utmatra_assert_same( 'A link with this slug already exists.', $result->message() );
	},

	'error defaults to 400'                              => static function () {
		utmatra_assert_same( 400, Result::error( 'utmatra_invalid', 'Bad.' )->status() );
	},

	'rest response for ok is the payload and status'     => static function () {
		$response = ResultAdapter::to_rest_response( Result::ok( array( 'deleted' => true ), 200 ) );
		utmatra_assert_instance_of( WP_REST_Response::class, $response );
		utmatra_assert_same( array( 'deleted' => true ), $response->get_data() );
		utmatra_assert_same( 200, $response->get_status() );
	},

	'rest response for error is a message body'          => static function () {
		$response = ResultAdapter::to_rest_response( Result::error( 'utmatra_not_found', 'Link not found.', 404 ) );
		utmatra_assert_same( array( 'message' => 'Link not found.' ), $response->get_data() );
		utmatra_assert_same( 404, $response->get_status() );
	},

	'wp error keeps code, message and status'            => static function () {
		$error = ResultAdapter::to_wp_error( Result::error( 'utmatra_not_found', 'No events recorded for this visitor.', 404 ) );
		utmatra_assert_instance_of( WP_Error::class, $error );
		utmatra_assert_same( 'utmatra_not_found', $error->get_error_code() );
		utmatra_assert_same( 'No events recorded for this visitor.', $error->get_error_message() );
		utmatra_assert_same( array( 'status' => 404 ), $error->get_error_data() );
	},

	'ability result is the payload or a wp error'        => static function () {
		utmatra_assert_same( array( 'rows' => array() ), ResultAdapter::to_ability_result( Result::ok( array( 'rows' => array() ) ) ) );

		$error = ResultAdapter::to_ability_result( Result::error( 'utmatra_invalid', 'Please provide a preset name.', 400 ) );
		utmatra_assert_instance_of( WP_Error::class, $error );
		utmatra_assert_same( array( 'status' => 400 ), $error->get_error_data() );
	},
);
