<?php
/**
 * Class autoloader for Utmatra.
 *
 * Plugin classes load via explicit require_once calls (no Composer
 * autoloader). The one Composer dependency, Action Scheduler, is loaded
 * from the main plugin file; without it, recurring jobs fall back to WP-Cron.
 *
 * @since 1.0.0
 * @since 1.1.0 Loads the capabilities, service support and abilities classes.
 * @since 1.1.0 Loads the stats service and controller.
 * @since 1.1.0 Loads the events service, controller and schema.
 * @since 1.1.0 Loads the links and presets services, controllers and ability helpers.
 * @since 1.1.0 Loads the notifications service and controller.
 * @since 1.1.0 Loads the diagnostics service and controller.
 * @since 1.1.0 Loads the journeys service and controller.
 *
 * @package Utmatra
 */

namespace Utmatra;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap — loads all plugin classes in dependency order.
 *
 * @since 1.0.0
 * @since 1.1.0 Loads the capabilities, service support and abilities classes.
 * @since 1.1.0 Loads the stats service and controller.
 * @since 1.1.0 Loads the events service, controller and schema.
 * @since 1.1.0 Loads the links and presets services, controllers and ability helpers.
 * @since 1.1.0 Loads the notifications service and controller.
 * @since 1.1.0 Loads the diagnostics service and controller.
 * @since 1.1.0 Loads the journeys service and controller.
 */
class Bootstrap {

	/**
	 * Require all source files.
	 *
	 * Ability definitions in src/Abilities/Definitions/ are not listed here;
	 * AbilitiesRegistrar loads them when the Abilities API initialises.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Loads the capabilities, service support and abilities classes.
	 * @since 1.1.0 Loads the stats service and controller.
	 * @since 1.1.0 Loads the events service, controller and schema.
	 * @since 1.1.0 Loads the links and presets services, controllers and ability helpers.
	 * @since 1.1.0 Loads the notifications service and controller.
	 * @since 1.1.0 Loads the diagnostics service and controller.
	 * @since 1.1.0 Loads the journeys service and controller.
	 * @return void
	 */
	public static function load(): void {
		$base = UTMATRA_PATH . 'src/';

		require_once $base . 'DB/Schema.php';
		require_once $base . 'DB/Retention.php';
		require_once $base . 'Scheduler.php';
		require_once $base . 'Privacy/PrivacyPolicy.php';
		require_once $base . 'Tracking/TrafficSource.php';
		require_once $base . 'Tracking/LinkManager.php';
		require_once $base . 'Tracking/Attribution.php';
		require_once $base . 'Tracking/Tracker.php';
		require_once $base . 'Tracking/Rewrites.php';
		require_once $base . 'Reports/WeeklyEmail.php';
		require_once $base . 'Reports/ConversionWebhook.php';
		require_once $base . 'Insights/Recorder.php';
		require_once $base . 'Insights/Insights.php';
		require_once $base . 'Rest/CollectController.php';
		require_once $base . 'Integrations/WooCommerce.php';
		require_once $base . 'Integrations/Forms/ContactForm7.php';
		require_once $base . 'Integrations/Forms/GravityForms.php';
		require_once $base . 'Integrations/Forms/NinjaForms.php';
		require_once $base . 'Integrations/Forms/Elementor.php';
		require_once $base . 'Integrations/Forms/WPForms.php';
		require_once $base . 'Integrations/Forms/FluentForms.php';
		require_once $base . 'Integrations/Forms/Formidable.php';
		require_once $base . 'Integrations/Forms/Forminator.php';
		require_once $base . 'Integrations/Forms/Bricks.php';
		require_once $base . 'Admin/Dashboard.php';
		require_once $base . 'Admin/DashboardWidget.php';
		require_once $base . 'Diagnostics/Checks.php';
		require_once $base . 'Admin/RestController.php';
		require_once $base . 'Shortcode/TrackedLink.php';
		require_once $base . 'Capabilities.php';
		require_once $base . 'Services/Support/Result.php';
		require_once $base . 'Services/Support/ResultAdapter.php';
		require_once $base . 'Services/Support/DateRange.php';
		require_once $base . 'Services/Support/InputValue.php';
		require_once $base . 'Services/Support/UtmNormalizer.php';
		require_once $base . 'Services/LinkValidator.php';
		require_once $base . 'Services/PresetsService.php';
		require_once $base . 'Services/LinksService.php';
		require_once $base . 'Rest/Controllers/RequestInput.php';
		require_once $base . 'Rest/Controllers/LinksController.php';
		require_once $base . 'Rest/Controllers/PresetsController.php';
		require_once $base . 'Services/SettingsService.php';
		require_once $base . 'Rest/Controllers/SettingsController.php';
		require_once $base . 'Services/StatsBreakdowns.php';
		require_once $base . 'Services/StatsService.php';
		require_once $base . 'Rest/Controllers/StatsController.php';
		require_once $base . 'Services/EventsService.php';
		require_once $base . 'Rest/Controllers/EventsController.php';
		require_once $base . 'Services/NotificationsService.php';
		require_once $base . 'Rest/Controllers/NotificationsController.php';
		require_once $base . 'Services/DiagnosticsService.php';
		require_once $base . 'Rest/Controllers/DiagnosticsController.php';
		require_once $base . 'Services/JourneysService.php';
		require_once $base . 'Rest/Controllers/JourneysController.php';
		require_once $base . 'Abilities/AbilityDefinition.php';
		require_once $base . 'Abilities/EventSchema.php';
		require_once $base . 'Abilities/OptIn.php';
		require_once $base . 'Abilities/SettingsSchema.php';
		require_once $base . 'Abilities/LinkAbilities.php';
		require_once $base . 'Abilities/AbilitiesRegistrar.php';
		require_once $base . 'Plugin.php';
	}
}
