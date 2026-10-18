<?php
/**
 * Kits that need other kits.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * A kit that only works when other kits are switched on (for example, per-role rules need the
 * Wholesale prices kit's role). It does not boot until they are, and the Kits screen says so.
 */
interface HasRequirements {

	/**
	 * Ids of the kits this kit needs.
	 *
	 * @return string[]
	 */
	public function requires(): array;
}
