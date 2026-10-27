<?php
/**
 * Stand-in for Utmatra\Reports\WeeklyEmail in unit tests.
 *
 * The real class schedules through Action Scheduler and renders a report,
 * neither of which the unit bootstrap stubs; saving settings only needs the
 * option name, the toggle and the schedule calls.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

namespace Utmatra\Reports;

/**
 * WeeklyEmail stub
 *
 * @since 1.1.0
 */
class WeeklyEmail {

	/**
	 * Option holding the weekly email toggle.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	public const OPTION = 'utmatra_weekly_email';

	/**
	 * Whether the weekly email is on.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return (bool) get_option( self::OPTION, false );
	}

	/**
	 * Schedule the weekly job (no-op).
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function schedule(): void {}

	/**
	 * Unschedule the weekly job (no-op).
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function unschedule(): void {}
}
