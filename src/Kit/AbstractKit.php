<?php
/**
 * Base class for kits.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Sensible defaults so a kit only declares what makes it different: id, name, description and boot().
 * New kits support HPOS and the Cart & Checkout blocks; override compat() only to say otherwise.
 */
abstract class AbstractKit implements Kit {

	/**
	 * A 2.0 kit.
	 */
	public function is_classic(): bool {
		return false;
	}

	/**
	 * HPOS and blocks supported.
	 *
	 * @return array<string, bool>
	 */
	public function compat(): array {
		return array(
			'hpos'   => true,
			'blocks' => true,
		);
	}

	/**
	 * No settings section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings(): array {
		return array();
	}
}
