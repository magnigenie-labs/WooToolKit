<?php
/**
 * Smoke tests: the plugin loads next to WooCommerce and registers its kits.
 *
 * @package WooToolkit
 */

/**
 * Plugin bootstrap tests.
 */
class Test_Plugin extends WP_UnitTestCase {

	public function test_woocommerce_and_wootoolkit_are_loaded() {
		$this->assertTrue( class_exists( 'WooCommerce' ), 'WooCommerce should be loaded.' );
		$this->assertInstanceOf( 'WooToolkit', $GLOBALS['wootoolkit'] );
		$this->assertTrue( defined( 'WOOTOOLKIT_VERSION' ) );
	}

	public function test_all_bundled_kits_are_discovered() {
		$kits = $GLOBALS['wootoolkit']->get_kits();
		$this->assertCount( 10, $kits );
		foreach ( $kits as $file => $kit ) {
			$this->assertStringEndsWith( '.php', $file );
			$this->assertNotEmpty( $kit['Name'], "Kit {$file} has no name." );
		}
	}

	public function test_install_activates_every_kit() {
		delete_option( '_woo_active_kits' );
		$GLOBALS['wootoolkit']->install();
		$this->assertSame( array_keys( $GLOBALS['wootoolkit']->get_kits() ), get_option( '_woo_active_kits' ) );
	}

	public function test_upgrade_removes_legacy_customer_email_list() {
		update_option( 'wt_mailchimp_mails', array( 'old@example.com' ) );
		update_option( 'wootoolkit_version', '1.0.2' );
		woo_tk_maybe_upgrade();
		$this->assertFalse( get_option( 'wt_mailchimp_mails' ) );
		$this->assertSame( WOOTOOLKIT_VERSION, get_option( 'wootoolkit_version' ) );
	}
}
