<?php
/**
 * Kit registry.
 *
 * @package WooToolkit
 */

use WooToolkit\Kit\ClassicKit;
use WooToolkit\Kit\Registry;

/**
 * Registry tests.
 */
class Test_Registry extends WP_UnitTestCase {

	private function registry_with( array $kits ) {
		$registry = new Registry();
		foreach ( $kits as $kit ) {
			$registry->register( $kit );
		}
		return $registry;
	}

	public function test_set_active_ignores_unknown_ids_and_records_the_rest_as_off() {
		$registry = $this->registry_with( array( new WooToolkit_Test_Kit( 'a' ), new WooToolkit_Test_Kit( 'b' ) ) );
		$registry->set_active( array( 'a', 'not-a-kit' ) );

		$this->assertSame( array( 'a' ), $registry->active_ids() );
		$this->assertSame(
			array(
				'a' => 'yes',
				'b' => 'no',
			),
			get_option( Registry::OPTION )
		);
	}

	public function test_only_active_kits_boot_and_only_once() {
		$on       = new WooToolkit_Test_Kit( 'on' );
		$off      = new WooToolkit_Test_Kit( 'off' );
		$registry = $this->registry_with( array( $on, $off ) );
		$registry->set_active( array( 'on' ) );

		$registry->boot_active();
		$registry->boot_active();

		$this->assertSame( 1, $on->boots );
		$this->assertSame( 0, $off->boots );
	}

	public function test_feature_support_is_the_and_of_active_kits() {
		$modern   = new WooToolkit_Test_Kit( 'modern' );
		$classic  = new ClassicKit( WOOTOOLKIT_PATH . 'kits/wootoolkit-empty-cart/wootoolkit-empty-cart.php' );
		$registry = $this->registry_with( array( $modern, $classic ) );

		$registry->set_active( array( 'modern' ) );
		$this->assertTrue( $registry->supports( 'hpos' ) );
		$this->assertTrue( $registry->supports( 'blocks' ) );

		$registry->set_active( array( 'modern', 'empty-cart' ) );
		$this->assertTrue( $registry->supports( 'hpos' ) );
		$this->assertFalse( $registry->supports( 'blocks' ), 'Classic kits do not support the blocks.' );
	}

	public function test_no_active_kits_supports_everything() {
		$registry = $this->registry_with( array( new WooToolkit_Test_Kit( 'a', array( 'hpos' => false ) ) ) );
		$registry->set_active( array() );
		$this->assertTrue( $registry->supports( 'hpos' ) );
	}

	public function test_new_kits_are_listed_before_classic_kits() {
		$registry = $this->registry_with(
			array(
				new ClassicKit( WOOTOOLKIT_PATH . 'kits/wootoolkit-accordions/wootoolkit-accordions.php' ),
				new WooToolkit_Test_Kit( 'zzz' ),
			)
		);
		$this->assertSame( array( 'zzz', 'accordions' ), array_keys( $registry->all() ) );
	}

	public function test_a_later_registration_replaces_a_kit_with_the_same_id() {
		$first    = new WooToolkit_Test_Kit( 'same' );
		$second   = new WooToolkit_Test_Kit( 'same' );
		$registry = $this->registry_with( array( $first, $second ) );
		$this->assertSame( $second, $registry->get( 'same' ) );
	}

	public function test_registered_add_on_kits_appear_in_the_plugin_registry() {
		$kit = new WooToolkit_Test_Kit( 'addon' );
		add_action(
			'wootoolkit_register_kits',
			static function ( Registry $registry ) use ( $kit ) {
				$registry->register( $kit );
			}
		);
		WooToolkit\Plugin::instance()->register_kits();
		$this->assertSame( $kit, WooToolkit\Plugin::instance()->registry()->get( 'addon' ) );
	}
}
