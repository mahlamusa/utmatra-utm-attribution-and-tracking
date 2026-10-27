<?php
/**
 * DiagnosticsService — the Tracking Doctor results behind GET utmatra/v1/diagnostics.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Services
 */

namespace Utmatra\Services;

use Utmatra\Diagnostics\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * DiagnosticsService
 *
 * Wraps Checks::run_all() so the REST route and the get-diagnostics ability
 * share one entry point. The checks themselves are unchanged.
 *
 * @since 1.1.0
 */
class DiagnosticsService {

	/**
	 * Run every Tracking Doctor check.
	 *
	 * Computed fresh on every call, never cached. Every check reads local
	 * state only: options and meta, the last 10 orders, and each active
	 * form plugin's full form list.
	 *
	 * @since 1.1.0
	 *
	 * @return array{checks: array<int, array{id: string, label: string, status: string, message: string, details: array}>} The same body GET utmatra/v1/diagnostics returns.
	 */
	public static function run(): array {
		return array( 'checks' => Checks::run_all() );
	}
}
