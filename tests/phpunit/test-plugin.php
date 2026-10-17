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

	public function test_the_ten_classic_kits_are_registered_with_stable_ids() {
		$ids = array_keys( Plugin::instance()->registry()->all() );
		sort( $ids );
		$this->assertSame(
			array( 'accordions', 'catalog', 'continue-shopping', 'custom-product-tabs', 'empty-cart', 'mailchimp', 'product-sorting-options', 'products-per-page', 'sequential-order-numbers', 'woo-customizer' ),
			$ids
		);
		foreach ( Plugin::instance()->registry()->all() as $id => $kit ) {
			$this->assertTrue( $kit->is_classic(), "{$id} should be a Classic kit." );
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
