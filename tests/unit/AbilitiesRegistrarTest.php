<?php
/**
 * Unit tests for AbilitiesRegistrar.
 *
 * Discovery runs against temporary fixture directories, never against
 * src/Abilities/Definitions, so real definitions are not loaded here.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

use Utmatra\Abilities\AbilitiesRegistrar;
use Utmatra\Abilities\AbilityDefinition;

/**
 * Write fixture files to a new temporary directory, run a callback with
 * its path, then remove the directory.
 *
 * @since 1.1.0
 *
 * @param array<string, string> $files    File name => PHP source after the opening tag.
 * @param callable              $callback Receives the directory path.
 *
 * @return void
 */
function utmatra_test_with_definitions( array $files, callable $callback ): void {
	$dir = sys_get_temp_dir() . '/utmatra-unit-' . bin2hex( random_bytes( 6 ) );
	mkdir( $dir );
	foreach ( $files as $name => $body ) {
		file_put_contents( $dir . '/' . $name, "<?php\n" . $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	try {
		$callback( $dir );
	} finally {
		array_map( 'unlink', (array) glob( $dir . '/*' ) );
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}

/**
 * An in-memory ability definition returning fixed arguments.
 *
 * @since 1.1.0
 *
 * @param array<string, mixed> $args Ability arguments.
 *
 * @return AbilityDefinition
 */
function utmatra_test_definition( array $args ): AbilityDefinition {
	return new class( $args ) implements AbilityDefinition {
		/**
		 * Ability arguments.
		 *
		 * @var array<string, mixed>
		 */
		private $args;

		/**
		 * Store the arguments.
		 *
		 * @param array<string, mixed> $args Ability arguments.
		 */
		public function __construct( array $args ) {
			$this->args = $args;
		}

		/**
		 * Ability name.
		 *
		 * @return string
		 */
		public function id(): string {
			return 'utmatra/fixture';
		}

		/**
		 * Ability arguments.
		 *
		 * @return array<string, mixed>
		 */
		public function args(): array {
			return $this->args;
		}
	};
}

return array(
	'register hooks both abilities api actions'      => static function () {
		AbilitiesRegistrar::register();
		utmatra_assert_same(
			array( array( AbilitiesRegistrar::class, 'register_category' ) ),
			$GLOBALS['utmatra_test']['actions']['wp_abilities_api_categories_init'] ?? null
		);
		utmatra_assert_same(
			array( array( AbilitiesRegistrar::class, 'register_abilities' ) ),
			$GLOBALS['utmatra_test']['actions']['wp_abilities_api_init'] ?? null
		);
	},

	'registers the utmatra category'                 => static function () {
		AbilitiesRegistrar::register_category();
		$args = $GLOBALS['utmatra_test']['categories']['utmatra'] ?? null;
		utmatra_assert_same( 'Utmatra', $args['label'] ?? null );
		utmatra_assert_true( '' !== ( $args['description'] ?? '' ), 'description is required by core' );
	},

	'an empty or missing directory has no definitions' => static function () {
		utmatra_test_with_definitions(
			array(),
			static function ( $dir ) {
				utmatra_assert_same( array(), AbilitiesRegistrar::definitions( $dir ) );
				utmatra_assert_same( array(), AbilitiesRegistrar::definitions( $dir . '/missing' ) );
			}
		);
	},

	'discovers definitions in name order'            => static function () {
		$template = 'namespace Utmatra\Abilities\Definitions;
			use Utmatra\Abilities\AbilityDefinition;
			class %1$s implements AbilityDefinition {
				public function id(): string { return "utmatra/%2$s"; }
				public function args(): array { return array(); }
			}';
		utmatra_test_with_definitions(
			array(
				'ZzUnitFixtureB.php' => sprintf( $template, 'ZzUnitFixtureB', 'fixture-b' ),
				'ZzUnitFixtureA.php' => sprintf( $template, 'ZzUnitFixtureA', 'fixture-a' ),
				'ZzUnitFixtureC.php' => 'namespace Utmatra\Abilities\Definitions; class ZzUnitFixtureC {}',
			),
			static function ( $dir ) {
				$ids = array_map(
					static function ( $definition ) {
						return $definition->id();
					},
					AbilitiesRegistrar::definitions( $dir )
				);
				utmatra_assert_same( array( 'utmatra/fixture-a', 'utmatra/fixture-b' ), $ids, 'non-definitions are skipped' );
			}
		);
	},

	'category defaults to utmatra'                   => static function () {
		$definition = utmatra_test_definition( array( 'label' => 'Fixture' ) );
		utmatra_assert_same(
			array(
				'label'    => 'Fixture',
				'category' => 'utmatra',
			),
			AbilitiesRegistrar::ability_args( $definition )
		);
	},

	'a definition may set its own category'          => static function () {
		$definition = utmatra_test_definition( array( 'category' => 'site' ) );
		utmatra_assert_same( array( 'category' => 'site' ), AbilitiesRegistrar::ability_args( $definition ) );
	},
);
