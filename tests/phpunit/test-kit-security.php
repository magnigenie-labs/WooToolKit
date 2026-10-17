<?php
/**
 * Regression tests for the 1.0.3 kit fixes: settings tab, product tabs,
 * products per page, Mailchimp.
 *
 * @package WooToolkit
 */

/**
 * Kit-level security fixes.
 */
class Test_Kit_Security extends WP_UnitTestCase {

	public function tear_down() {
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	public function test_settings_tab_parameter_is_not_reflected() {
		add_filter(
			'wootoolkit_setting_tabs',
			static function ( $tabs ) {
				$tabs[] = array(
					'id'    => 'wootoolkit_accordion',
					'title' => 'Accordion',
				);
				return $tabs;
			}
		);
		$_GET = array(
			'page' => 'woo-toolkit-settings',
			'tab'  => '"><script>alert(1)</script>',
		);

		$settings = new WooToolkit_Settings_API();
		$settings->admin_init();
		ob_start();
		$settings->show_navigation();
		$settings->show_forms();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '<script>alert(1)', $html );
		$this->assertNotSame( '', $settings->get_current_tab() );
	}

	public function test_product_tab_is_sanitised_for_users_without_unfiltered_html() {
		$this->assertArrayHasKey( 'wootoolkit_custom_product_tabs', $GLOBALS, 'Custom Product Tabs kit should be active.' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter(
			'user_has_cap',
			static function ( $caps ) {
				$caps['unfiltered_html'] = false;
				return $caps;
			}
		);
		$product = WC_Helper_Product_Minimal::create();
		$_POST   = array(
			'_wt_custom_product_tabs_tab_title'   => 'Size <img src=x onerror=alert(1)> Guide',
			'_wt_custom_product_tabs_tab_content' => '<p>Fits true</p><script>alert(2)</script>',
		);
		$GLOBALS['wootoolkit_custom_product_tabs']->product_save_data( $product, get_post( $product ) );
		$saved = maybe_unserialize( get_post_meta( $product, 'frs_woo_product_tabs', true ) );

		$this->assertStringNotContainsString( '<', $saved[0]['title'] );
		$this->assertStringNotContainsString( '<script', $saved[0]['content'] );
		$this->assertStringContainsString( '<p>Fits true</p>', $saved[0]['content'] );
	}

	public function test_mailchimp_bad_key_never_throws_and_stores_no_emails() {
		// A key without a datacenter suffix throws inside the client before any network call.
		delete_option( 'wt_mailchimp_mails' );
		update_option( 'wootoolkit_mailchimp', array( 'double_optin' => 'off' ) );
		$front = new WT_Mailchimp_Front_End();
		$front->wt_mailchimp_subscribe_user(
			array(
				'user_email' => 'qa@example.com',
				'first_name' => 'Q',
				'last_name'  => 'A',
			),
			'badkey',
			'list'
		);
		$this->assertFalse( get_option( 'wt_mailchimp_mails' ) );
	}
}
