<?php
/**
 * Smoke tests: the plugin loads next to WooCommerce and registers the Classic kits.
 *
 * @package WooToolkit
 */

use WooToolkit\Plugin;

/**
 * Plugin bootstrap tests.
 */
class Test_Plugin extends WP_UnitTestCase {

	public function test_woocommerce_and_wootoolkit_are_loaded() {
		$this->assertTrue( class_exists( 'WooCommerce' ), 'WooCommerce should be loaded.' );
		$this->assertInstanceOf( Plugin::class, Plugin::instance() );
		$this->assertSame( '2.0.0-dev', WOOTOOLKIT_VERSION );
	}

	public function test_the_2x_kits_are_registered_first() {
		$this->assertSame( array( 'catalog-mode', 'request-a-quote', 'wholesale-prices' ), array_slice( array_keys( Plugin::instance()->registry()->all() ), 0, 3 ) );
	}

	public function test_the_nine_classic_kits_are_registered_with_stable_ids() {
		$classic = array_filter(
			Plugin::instance()->registry()->all(),
			static function ( $kit ) {
				return $kit->is_classic();
			}
		);
		$ids     = array_keys( $classic );
		sort( $ids );
		$this->assertSame(
			array( 'accordions', 'continue-shopping', 'custom-product-tabs', 'empty-cart', 'mailchimp', 'product-sorting-options', 'products-per-page', 'sequential-order-numbers', 'woo-customizer' ),
			$ids
		);
		foreach ( $classic as $id => $kit ) {
			$this->assertNotSame( $id, $kit->name(), "{$id} should read its name from the Kit Name header." );
		}
	}

	public function test_active_classic_kits_are_booted_on_init() {
		$this->assertTrue( class_exists( 'WT_Accordions' ), 'The Accordions kit should have been loaded.' );
		$this->assertArrayHasKey( 'wootoolkit_custom_product_tabs', $GLOBALS );
	}

	public function test_classic_settings_screen_is_available_while_a_classic_kit_is_active() {
		$this->assertTrue( Plugin::instance()->registry()->has_active_classic() );
		$this->assertTrue( class_exists( 'WooToolkit_Settings_API' ) );
	}
}
