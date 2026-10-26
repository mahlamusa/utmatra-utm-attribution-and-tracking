<?php
/**
 * Assertion helpers for the unit test runner.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

/**
 * Fail unless two values are identical (===).
 *
 * @since 1.1.0
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Optional context.
 *
 * @return void
 * @throws RuntimeException When the values differ.
 */
function utmatra_assert_same( $expected, $actual, string $message = '' ): void {
	if ( $expected !== $actual ) {
		throw new RuntimeException( trim( $message . ' expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
	}
}

/**
 * Fail unless a value is true.
 *
 * @since 1.1.0
 *
 * @param mixed  $actual  Actual value.
 * @param string $message Optional context.
 *
 * @return void
 * @throws RuntimeException When the value is not true.
 */
function utmatra_assert_true( $actual, string $message = '' ): void {
	utmatra_assert_same( true, $actual, $message );
}

/**
 * Fail unless a value is false.
 *
 * @since 1.1.0
 *
 * @param mixed  $actual  Actual value.
 * @param string $message Optional context.
 *
 * @return void
 * @throws RuntimeException When the value is not false.
 */
function utmatra_assert_false( $actual, string $message = '' ): void {
	utmatra_assert_same( false, $actual, $message );
}

/**
 * Fail unless a value is an instance of a class.
 *
 * @since 1.1.0
 *
 * @param string $class_name Expected class.
 * @param mixed  $actual     Actual value.
 *
 * @return void
 * @throws RuntimeException When the value is not an instance.
 */
function utmatra_assert_instance_of( string $class_name, $actual ): void {
	if ( ! $actual instanceof $class_name ) {
		throw new RuntimeException( 'expected an instance of ' . $class_name . ', got ' . ( is_object( $actual ) ? get_class( $actual ) : gettype( $actual ) ) );
	}
}
