<?php
/**
 * AbilityDefinition — the contract every Utmatra ability class implements.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Abilities
 */

namespace Utmatra\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * AbilityDefinition
 *
 * One implementation per ability, in src/Abilities/Definitions/, where
 * AbilitiesRegistrar discovers it. The class must be constructible
 * without arguments.
 *
 * @since 1.1.0
 */
interface AbilityDefinition {

	/**
	 * The ability name, e.g. `utmatra/get-stats`.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * The arguments passed to wp_register_ability().
	 *
	 * Label, description, input and output schemas, execute and permission
	 * callbacks and meta. The `category` key may be omitted; the registrar
	 * defaults it to the `utmatra` category.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function args(): array;
}
