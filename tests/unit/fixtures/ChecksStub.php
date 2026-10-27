<?php
/**
 * Stand-in for Utmatra\Diagnostics\Checks in unit tests.
 *
 * The real checks read plugin, form and WooCommerce state that the unit
 * bootstrap does not stub; the diagnostics tests only need a fixed result.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

namespace Utmatra\Diagnostics;

/**
 * Checks stub
 *
 * @since 1.1.0
 */
class Checks {

	/**
	 * A fixed single-row result in the shape of the real run_all().
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function run_all(): array {
		return array(
			array(
				'id'      => 'caching',
				'label'   => 'Caching',
				'status'  => 'pass',
				'message' => 'No page cache detected.',
				'details' => array(),
			),
		);
	}
}
