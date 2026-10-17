<?php
/**
 * Test helper.
 *
 * @package WooToolkit
 */

/**
 * Tiny product factory so tests do not depend on WooCommerce's test helpers.
 */
class WC_Helper_Product_Minimal {

	public static function create() {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test product' );
		$product->set_regular_price( '10' );
		$product->set_status( 'publish' );
		return $product->save();
	}
}
