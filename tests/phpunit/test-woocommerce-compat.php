<?php
/**
 * WooCommerce feature declarations (HPOS, Cart & Checkout blocks).
 *
 * @package WooToolkit
 */

use Automattic\WooCommerce\Internal\Features\FeaturesController;

/**
 * Feature compatibility declarations.
 */
class Test_WooCommerce_Compat extends WP_UnitTestCase {

	private function compat( $feature ) {
		return wc_get_container()->get( FeaturesController::class )->get_compatible_plugins_for_feature( $feature, true );
	}

	public function test_blocks_are_declared_incompatible() {
		$this->assertContains( plugin_basename( WOOTOOLKIT_FILE ), $this->compat( 'cart_checkout_blocks' )['incompatible'] );
	}

	public function test_hpos_declaration_matches_sequential_kit_state() {
		// Declarations are made once at before_woocommerce_init; with the default
		// (all kits active) the Sequential Order Numbers kit makes HPOS incompatible.
		$sequential_active = in_array( 'wootoolkit-sequential-order-numbers/wootoolkit-sequential-order-numbers.php', (array) get_option( '_woo_active_kits', array() ), true );
		$compat            = $this->compat( 'custom_order_tables' );
		$bucket            = $sequential_active ? 'incompatible' : 'compatible';
		$this->assertContains( plugin_basename( WOOTOOLKIT_FILE ), $compat[ $bucket ] );
	}
}
