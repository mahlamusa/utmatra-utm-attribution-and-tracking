<?php
/**
 * AbilitiesRegistrar — registers the Utmatra category and abilities.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Abilities
 */

namespace Utmatra\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * AbilitiesRegistrar
 *
 * Hooks the WordPress Abilities API (core since 6.9). Every class in
 * src/Abilities/Definitions/ that implements AbilityDefinition is
 * registered, so each ability ships as one new file and no shared list is
 * edited. Definition files are only loaded inside `wp_abilities_api_init`,
 * which core fires when abilities are first requested.
 *
 * @since 1.1.0
 */
class AbilitiesRegistrar {

	/**
	 * The ability category slug.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	public const CATEGORY = 'utmatra';

	/**
	 * Namespace of the discovered definition classes.
	 *
	 * @since 1.1.0
	 *
	 * @var string
	 */
	private const DEFINITIONS_NAMESPACE = __NAMESPACE__ . '\\Definitions\\';

	/**
	 * Hook the category and ability registration.
	 *
	 * Does nothing when the Abilities API is unavailable.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	/**
	 * Register the `utmatra` ability category.
	 *
	 * `wp_abilities_api_categories_init` callback.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Utmatra', 'utmatra-utm-attribution-and-tracking' ),
				'description' => __( 'UTM attribution reports, tracked links, presets and settings.', 'utmatra-utm-attribution-and-tracking' ),
			)
		);
	}

	/**
	 * Register every discovered ability.
	 *
	 * `wp_abilities_api_init` callback.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		foreach ( self::definitions() as $definition ) {
			wp_register_ability( $definition->id(), self::ability_args( $definition ) );
		}
	}

	/**
	 * The wp_register_ability() arguments for a definition.
	 *
	 * The definition's own arguments, with `category` defaulting to the
	 * `utmatra` category.
	 *
	 * @since 1.1.0
	 *
	 * @param AbilityDefinition $definition Ability definition.
	 *
	 * @return array<string, mixed>
	 */
	public static function ability_args( AbilityDefinition $definition ): array {
		return $definition->args() + array( 'category' => self::CATEGORY );
	}

	/**
	 * Load and instantiate the ability definitions in a directory.
	 *
	 * Files load in name order. Each file must declare a class named after
	 * the file in the Utmatra\Abilities\Definitions namespace; a class that
	 * does not implement AbilityDefinition is skipped.
	 *
	 * @since 1.1.0
	 *
	 * @param string $dir Directory to scan; src/Abilities/Definitions by default.
	 *
	 * @return AbilityDefinition[]
	 */
	public static function definitions( string $dir = __DIR__ . '/Definitions' ): array {
		$files = glob( $dir . '/*.php' );
		if ( ! is_array( $files ) ) {
			return array();
		}
		sort( $files );

		$definitions = array();
		foreach ( $files as $file ) {
			require_once $file;

			$class = self::DEFINITIONS_NAMESPACE . basename( $file, '.php' );
			if ( is_subclass_of( $class, AbilityDefinition::class ) ) {
				$definitions[] = new $class();
			}
		}

		return $definitions;
	}
}
