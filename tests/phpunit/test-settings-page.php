<?php
/**
 * WooCommerce > Settings > WooToolkit.
 *
 * @package WooToolkit
 */

use WooToolkit\Admin\SettingsPage;
use WooToolkit\Kit\ClassicKit;
use WooToolkit\Kit\Registry;

/**
 * Settings tab tests.
 */
class Test_Settings_Page extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();
		include_once WC_ABSPATH . 'includes/admin/settings/class-wc-settings-page.php';
		include_once WC_ABSPATH . 'includes/admin/class-wc-admin-settings.php';
	}

	private function page_for( array $kits ) {
		$registry = new Registry();
		foreach ( $kits as $kit ) {
			$registry->register( $kit );
		}
		return array( new SettingsPage( $registry ), $registry );
	}

	public function test_kit_list_has_one_checkbox_per_kit() {
		list( $page ) = $this->page_for(
			array(
				new WooToolkit_Test_Kit( 'quotes' ),
				new ClassicKit( WOOTOOLKIT_PATH . 'kits/wootoolkit-catalog/wootoolkit-catalog.php' ),
			)
		);
		$checkboxes   = wp_list_filter( $page->get_settings_for_section( '' ), array( 'type' => 'checkbox' ) );

		$this->assertSame( array( 'wootoolkit_active_kits[quotes]', 'wootoolkit_active_kits[catalog]' ), array_values( wp_list_pluck( $checkboxes, 'id' ) ) );
		$catalog = array_values( $checkboxes )[1];
		$this->assertStringContainsString( 'Classic kit', $catalog['desc'] );
		$this->assertStringContainsString( 'not the blocks', $catalog['desc'] );
	}

	public function test_saving_the_kit_list_through_woocommerce_switches_kits() {
		list( $page, $registry ) = $this->page_for( array( new WooToolkit_Test_Kit( 'quotes' ), new WooToolkit_Test_Kit( 'wholesale' ) ) );
		delete_option( Registry::OPTION ); // WooCommerce merges array options into the saved value.

		WC_Admin_Settings::save_fields( $page->get_settings_for_section( '' ), array( Registry::OPTION => array( 'wholesale' => '1' ) ) );

		$this->assertSame( array( 'wholesale' ), $registry->active_ids() );
		$this->assertSame(
			array(
				'quotes'    => 'no',
				'wholesale' => 'yes',
			),
			get_option( Registry::OPTION )
		);
	}

	public function test_only_active_kits_with_settings_get_a_section() {
		$fields                  = array(
			array(
				'id'   => 'wootoolkit_quotes_title',
				'type' => 'text',
			),
		);
		list( $page, $registry ) = $this->page_for(
			array(
				new WooToolkit_Test_Kit( 'quotes', array(), $fields ),
				new WooToolkit_Test_Kit( 'plain' ),
			)
		);

		$registry->set_active( array( 'plain' ) );
		$this->assertSame( array( '' ), array_keys( $page->get_sections() ) );

		$registry->set_active( array( 'quotes', 'plain' ) );
		$this->assertSame( array( '', 'quotes' ), array_keys( $page->get_sections() ) );
		$this->assertSame( $fields, $page->get_settings_for_section( 'quotes' ) );
	}
}
