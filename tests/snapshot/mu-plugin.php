<?php
/**
 * Plugin Name: Utmatra snapshot harness stubs
 * Description: Installed into snapshot sandboxes only. Answers every outgoing HTTP request with a fixed 200 and every wp_mail() with success, so webhook and email routes are deterministic and never leave the machine.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'pre_http_request',
	static function () {
		return array(
			'headers'  => array(),
			'body'     => 'ok',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	PHP_INT_MAX
);

add_filter(
	'pre_wp_mail',
	static function () {
		return true;
	},
	PHP_INT_MAX
);
