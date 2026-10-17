<?php
/**
 * Kit contract.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * A kit is one feature that a store owner switches on or off.
 */
interface Kit {

	/**
	 * Stable identifier, used as the key in the active-kits option. Never change it once released.
	 */
	public function id(): string;

	/**
	 * Name shown to store owners.
	 */
	public function name(): string;

	/**
	 * One-line description shown in the kit list.
	 */
	public function description(): string;

	/**
	 * Classic kits are the 1.x kits: kept working, bug fixes only.
	 */
	public function is_classic(): bool;

	/**
	 * WooCommerce feature support, as array( 'hpos' => bool, 'blocks' => bool ).
	 *
	 * @return array<string, bool>
	 */
	public function compat(): array;

	/**
	 * WooCommerce settings fields for the kit's section of the WooToolkit tab. Empty for no section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings(): array;

	/**
	 * Hooks the kit into WordPress. Called once, on init, for active kits only.
	 */
	public function boot(): void;
}
