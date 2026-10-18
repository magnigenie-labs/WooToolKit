<?php
/**
 * Checkout fields kit.
 *
 * @package WooToolkit
 */

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields as WooCheckoutFields;
use Automattic\WooCommerce\Blocks\Domain\Services\CoreCheckoutFields;
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;
use WooToolkit\Kits\CheckoutFields\BlockCheckout;
use WooToolkit\Kits\CheckoutFields\ClassicCheckout;
use WooToolkit\Kits\CheckoutFields\CoreFields;
use WooToolkit\Kits\CheckoutFields\Display;
use WooToolkit\Kits\CheckoutFields\Fields;
use WooToolkit\Kits\CheckoutFields\SettingsField;

/**
 * Built-in field edits, extra fields in both checkouts, displays and settings.
 */
class Test_Checkout_Fields extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		$this->reload();
	}

	public function tear_down() {
		$_POST = array();
		remove_all_filters( 'woocommerce_store_api_disable_nonce_check' );
		remove_all_filters( 'wootoolkit_checkout_fields_types' );
		remove_all_actions( 'wootoolkit_checkout_fields_row_settings' );
		BlockCheckout::unregister();
		parent::tear_down();
		Fields::flush();
		wp_set_current_user( 0 );
	}

	/**
	 * Re-reads the settings and registers the block checkout fields again, as a new request would.
	 */
	private function reload() {
		Fields::flush();
		BlockCheckout::unregister();
		BlockCheckout::register();
	}

	/**
	 * Saves a section the way the settings screen does, then reloads.
	 *
	 * @param string $section Section.
	 * @param array  $posted  Posted rows.
	 */
	private function save( $section, array $posted ) {
		update_option( Fields::option( $section ), SettingsField::clean( $section, $posted ) );
		$this->reload();
	}

	/**
	 * Extra fields used by several tests: an order note (text), a contact checkbox, a dropdown and a VAT
	 * number in the address.
	 */
	private function extra_fields() {
		$this->save(
			'order',
			array(
				'custom' => array(
					array(
						'label'    => 'Gift message',
						'type'     => 'textarea',
						'required' => '1',
					),
					array(
						'label'   => 'Wrapping',
						'type'    => 'select',
						'options' => "plain | Plain paper\ngold | Gold paper",
					),
				),
			)
		);
		$this->save(
			'contact',
			array(
				'custom' => array(
					array(
						'label' => 'Newsletter',
						'type'  => 'checkbox',
					),
					array(
						'label' => 'Second email',
						'type'  => 'email',
					),
				),
			)
		);
		$this->save(
			'address',
			array(
				'custom' => array(
					array(
						'label' => 'VAT number',
						'key'   => 'vat',
					),
				),
			)
		);
	}

	private function block_fields() {
		return Package::container()->get( WooCheckoutFields::class )->get_additional_fields();
	}

	/**
	 * The classic checkout's fieldsets, built again for this request.
	 */
	private function classic_fieldsets() {
		wc_load_cart();
		$checkout = WC()->checkout();
		$property = new ReflectionProperty( WC_Checkout::class, 'fields' );
		$property->setAccessible( true );
		$property->setValue( $checkout, null );
		return $checkout->get_checkout_fields();
	}

	private function forget_store_api_draft_order() {
		foreach ( rest_get_server()->get_routes()['/wc/store/v1/checkout'] ?? array() as $handler ) {
			if ( is_array( $handler['callback'] ?? null ) && is_object( $handler['callback'][0] ) && property_exists( $handler['callback'][0], 'order' ) ) {
				$property = new ReflectionProperty( get_class( $handler['callback'][0] ), 'order' );
				$property->setAccessible( true );
				$property->setValue( $handler['callback'][0], null );
			}
		}
	}

	/**
	 * Places a Checkout block (Store API) order for a free virtual product.
	 *
	 * @param array $additional_fields Contact and order fields.
	 * @param array $billing           Billing address changes.
	 */
	private function store_api_checkout( array $additional_fields, array $billing = array() ) {
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
		$product = new WC_Product_Simple();
		$product->set_name( 'Fields test' );
		$product->set_regular_price( '0' );
		$product->set_virtual( true );
		$product->set_status( 'publish' );
		$product->save();

		wc_load_cart();
		WC()->session->set( 'store_api_draft_order', 0 );
		$this->forget_store_api_draft_order();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'billing_address'   => array_merge(
						array(
							'first_name' => 'Ada',
							'last_name'  => 'Buyer',
							'company'    => '',
							'address_1'  => '1 Main St',
							'city'       => 'Berlin',
							'postcode'   => '10115',
							'country'    => 'DE',
							'email'      => 'ada@example.com',
							'phone'      => '+49 30 1234567',
						),
						$billing
					),
					'additional_fields' => (object) $additional_fields,
					'payment_method'    => '',
				)
			)
		);
		return rest_do_request( $request );
	}

	public function test_without_settings_nothing_changes() {
		$this->assertSame( array(), Fields::all_custom_fields() );
		$this->assertSame( array(), CoreFields::locale_overrides() );
		foreach ( array_keys( $this->block_fields() ) as $id ) {
			$this->assertStringStartsNotWith( 'wootoolkit/', $id );
		}
		$sample = array(
			'billing' => array( 'billing_city' => array( 'label' => 'Town / City' ) ),
			'order'   => array( 'order_comments' => array( 'label' => 'Order notes' ) ),
		);
		$this->assertSame( $sample, apply_filters( 'woocommerce_checkout_fields', $sample ) );
		$this->assertFalse( CoreFields::visibility_option( false, 'woocommerce_checkout_company_field' ) );

		$order = wc_create_order();
		ob_start();
		do_action( 'woocommerce_email_customer_details', $order, false, false );
		do_action( 'woocommerce_order_details_after_order_table', $order );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_saving_built_in_fields_stores_only_what_changed() {
		$defaults = CoreFields::defaults()['address'];
		$clean    = SettingsField::clean(
			'address',
			array(
				'core' => array(
					'first_name' => array(
						'label'    => $defaults['first_name']['label'],
						'required' => '1',
						'priority' => (string) $defaults['first_name']['priority'],
					),
					'city'       => array(
						'label'    => 'Town <script>alert(1)</script>',
						'required' => '1',
						'priority' => '15',
					),
					'postcode'   => array(
						'label' => '',
					),
					'country'    => array(
						'label'    => 'Nation',
						'hidden'   => '1',
						'required' => '',
					),
					'nonsense'   => array( 'label' => 'X' ),
				),
			)
		);
		$this->assertArrayNotHasKey( 'first_name', $clean['core'], 'Unchanged defaults are not stored.' );
		$this->assertSame(
			array(
				'label'    => 'Town',
				'priority' => 15,
			),
			$clean['core']['city']
		);
		$this->assertArrayHasKey( 'required', $clean['core']['postcode'], 'An unticked Required box is a change.' );
		$this->assertFalse( $clean['core']['postcode']['required'] );
		$this->assertSame( array( 'label' => 'Nation' ), $clean['core']['country'], 'The country cannot be hidden or made optional.' );
		$this->assertArrayNotHasKey( 'nonsense', $clean['core'] );
	}

	public function test_extra_fields_are_cleaned_and_get_unique_keys() {
		update_option(
			Fields::option( 'contact' ),
			array(
				'core'   => array(),
				'custom' => array(
					array(
						'key'   => 'gift',
						'label' => 'Gift',
					),
				),
			)
		);
		$clean = SettingsField::clean(
			'order',
			array(
				'custom' => array(
					array(
						'label'   => 'Gift <img src=x onerror=alert(1)>',
						'type'    => 'evil',
						'options' => 'a | A',
					),
					array( 'label' => '' ),
					array(
						'label'   => 'Size',
						'type'    => 'select',
						'options' => "s | Small\nLarge\ns | Duplicate\n | empty",
					),
					'not an array',
				),
			)
		);
		$this->assertCount( 2, $clean['custom'], 'Rows without a label are dropped.' );
		$gift = $clean['custom'][0];
		$this->assertSame( 'gift-2', $gift['key'], 'Keys are unique across sections.' );
		$this->assertSame( 'Gift', $gift['label'] );
		$this->assertSame( 'text', $gift['type'], 'Unknown types become text.' );
		$this->assertSame( array(), $gift['options'], 'Only choice types keep choices.' );
		$this->assertSame(
			array(
				array(
					'value' => 's',
					'label' => 'Small',
				),
				array(
					'value' => 'Large',
					'label' => 'Large',
				),
			),
			$clean['custom'][1]['options']
		);
		$this->assertSame( 'size', $clean['custom'][1]['key'] );
	}

	public function test_reset_to_defaults_empties_the_section() {
		$_POST[ SettingsField::RESET ] = 'address';
		$this->assertTrue( SettingsField::save_on_reset( false ) );
		$saved = SettingsField::sanitize(
			null,
			array( 'section' => 'address' ),
			array( 'core' => array( 'city' => array( 'label' => 'Town' ) ) )
		);
		$this->assertSame(
			array(
				'core'   => array(),
				'custom' => array(),
			),
			$saved
		);
		$other = SettingsField::sanitize( null, array( 'section' => 'order' ), array( 'core' => array( 'order_comments' => array( 'label' => 'Notes' ) ) ) );
		$this->assertSame( 'Notes', $other['core']['order_comments']['label'], 'Other sections save normally.' );

		$_POST = array();
		$this->assertFalse( SettingsField::save_on_reset( false ) );
	}

	public function test_classic_checkout_applies_built_in_field_edits() {
		$this->save(
			'address',
			array(
				'core' => array(
					'city'    => array(
						'label'    => 'Suburb',
						'required' => '1',
						'priority' => '15',
					),
					'phone'   => array(
						'label'    => 'Mobile',
						'required' => '1',
					),
					'state'   => array( 'hidden' => '1' ),
					'company' => array( 'required' => '1' ),
				),
			)
		);
		$this->save( 'contact', array( 'core' => array( 'email' => array( 'label' => 'Your email' ) ) ) );
		$this->save( 'order', array( 'core' => array( 'order_comments' => array( 'hidden' => '1' ) ) ) );

		$fields = $this->classic_fieldsets();
		$this->assertSame( 'Suburb', $fields['billing']['billing_city']['label'] );
		$this->assertSame( 15, $fields['billing']['billing_city']['priority'] );
		$this->assertSame( 'Suburb', $fields['shipping']['shipping_city']['label'], 'Edits apply to both addresses.' );
		$this->assertSame( 'Mobile', $fields['billing']['billing_phone']['label'] );
		$this->assertTrue( $fields['billing']['billing_phone']['required'] );
		$this->assertArrayNotHasKey( 'billing_state', $fields['billing'] );
		$this->assertArrayNotHasKey( 'shipping_state', $fields['shipping'] );
		$this->assertTrue( $fields['billing']['billing_company']['required'] );
		$this->assertSame( 'Your email', $fields['billing']['billing_email']['label'] );
		$this->assertArrayNotHasKey( 'order_comments', $fields['order'] ?? array() );
	}

	public function test_block_checkout_gets_the_edits_through_the_country_locale() {
		$this->save(
			'address',
			array(
				'core' => array(
					'city'     => array(
						'label'    => 'Suburb',
						'priority' => '15',
					),
					'postcode' => array( 'hidden' => '1' ),
					'company'  => array( 'required' => '1' ),
				),
			)
		);
		$locale = WC()->countries->get_country_locale();
		foreach ( array( 'US', 'DE', 'AU' ) as $country ) {
			$this->assertSame( 'Suburb', $locale[ $country ]['city']['label'], $country );
			$this->assertTrue( $locale[ $country ]['postcode']['hidden'], $country );
			$this->assertFalse( $locale[ $country ]['postcode']['required'], $country );
		}
		$this->assertSame( 'State', $locale['AU']['state']['label'], 'Country rules for unchanged fields stay.' );

		$data = CartCheckoutUtils::get_country_data();
		$this->assertSame( 15, $data['DE']['locale']['city']['index'], 'Priority is the block checkout\'s index.' );

		$this->assertSame( 'required', CartCheckoutUtils::get_company_field_visibility() );
		$this->assertTrue( CoreCheckoutFields::get_fields()['company']['required'] );
		$this->assertFalse( CoreCheckoutFields::get_fields()['company']['hidden'] );
	}

	public function test_extra_fields_are_registered_with_the_block_checkout() {
		$this->extra_fields();
		$fields = $this->block_fields();

		$gift = $fields['wootoolkit/gift-message'];
		$this->assertSame( 'order', $gift['location'] );
		$this->assertSame( 'text', $gift['type'], 'Text areas are text inputs in the block checkout.' );
		$this->assertTrue( $gift['required'] );

		$wrap = $fields['wootoolkit/wrapping'];
		$this->assertSame( 'select', $wrap['type'] );
		$this->assertSame( array( 'plain', 'gold' ), wp_list_pluck( $wrap['options'], 'value' ) );

		$this->assertSame( 'contact', $fields['wootoolkit/newsletter']['location'] );
		$this->assertSame( 'checkbox', $fields['wootoolkit/newsletter']['type'] );
		$this->assertSame( 'email', $fields['wootoolkit/second-email']['attributes']['autocomplete'] );
		$this->assertSame( 'address', $fields['wootoolkit/vat']['location'] );

		$locale = WC()->countries->get_country_locale();
		$this->assertSame( 110, $locale['DE']['wootoolkit/vat']['priority'], 'Extra address fields are sorted with the built-in ones.' );
	}

	public function test_hidden_and_choiceless_fields_are_not_offered() {
		$this->save(
			'order',
			array(
				'custom' => array(
					array(
						'label'  => 'Old question',
						'hidden' => '1',
					),
					array(
						'label' => 'Empty dropdown',
						'type'  => 'select',
					),
				),
			)
		);
		$fields = $this->block_fields();
		$this->assertArrayNotHasKey( 'wootoolkit/old-question', $fields );
		$this->assertArrayNotHasKey( 'wootoolkit/empty-dropdown', $fields );
		$this->assertArrayNotHasKey( 'wootoolkit_old_question', $this->classic_fieldsets()['order'] ?? array() );

		add_filter( 'wootoolkit_checkout_fields_field_applies', '__return_false' );
		$this->save( 'order', array( 'custom' => array( array( 'label' => 'Gift' ) ) ) );
		remove_filter( 'wootoolkit_checkout_fields_field_applies', '__return_false' );
		$this->assertArrayNotHasKey( 'wootoolkit/gift', $this->block_fields(), 'The applies filter can skip a field.' );
	}

	public function test_store_api_checkout_validates_and_saves_extra_fields() {
		$this->extra_fields();

		$response = $this->store_api_checkout( array( 'wootoolkit/wrapping' => 'gold' ) );
		$this->assertSame( 400, $response->get_status(), 'The required gift message is missing.' );
		$this->assertStringContainsString( 'gift-message', wp_json_encode( $response->get_data() ) );

		$response = $this->store_api_checkout(
			array(
				'wootoolkit/gift-message' => 'Happy birthday',
				'wootoolkit/second-email' => 'not-an-email',
			)
		);
		$this->assertSame( 400, $response->get_status(), 'An invalid email is refused.' );

		$response = $this->store_api_checkout(
			array(
				'wootoolkit/gift-message' => 'Happy <b>birthday</b>',
				'wootoolkit/wrapping'     => 'gold',
				'wootoolkit/newsletter'   => true,
				'wootoolkit/second-email' => 'gift@example.com',
			),
			array( 'wootoolkit/vat' => 'DE123456789' )
		);
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );

		$order = wc_get_order( $response->get_data()['order_id'] );
		$this->assertSame( 'Happy birthday', $order->get_meta( '_wc_other/wootoolkit/gift-message' ) );
		$this->assertSame( 'gold', $order->get_meta( '_wc_other/wootoolkit/wrapping' ) );
		$this->assertSame( '1', $order->get_meta( '_wc_other/wootoolkit/newsletter' ) );
		$this->assertSame( 'DE123456789', $order->get_meta( '_wc_billing/wootoolkit/vat' ) );

		$this->assertTrue( Display::handled_by_woocommerce( $order ) );
		ob_start();
		do_action( 'woocommerce_order_details_after_order_table', $order );
		$this->assertStringNotContainsString( 'wootoolkit-checkout-fields', ob_get_clean(), 'WooCommerce shows block checkout orders itself.' );
	}

	public function test_classic_checkout_adds_validates_and_saves_extra_fields() {
		$this->extra_fields();
		$fields = $this->classic_fieldsets();

		$this->assertSame( 'textarea', $fields['order']['wootoolkit_gift_message']['type'] );
		$this->assertTrue( $fields['order']['wootoolkit_gift_message']['required'] );
		$this->assertSame( array( '', 'plain', 'gold' ), array_keys( $fields['order']['wootoolkit_wrapping']['options'] ) );
		$this->assertSame( 'checkbox', $fields['billing']['wootoolkit_newsletter']['type'] );
		$this->assertArrayHasKey( 'billing_wootoolkit_vat', $fields['billing'] );
		$this->assertArrayHasKey( 'shipping_wootoolkit_vat', $fields['shipping'] );

		$data   = array(
			'wootoolkit_gift_message' => "Line one\nLine two",
			'wootoolkit_wrapping'     => 'diamond',
			'wootoolkit_newsletter'   => 1,
			'wootoolkit_second_email' => 'nope',
			'billing_wootoolkit_vat'  => 'GB123',
			'shipping_wootoolkit_vat' => 'GB123',
		);
		$errors = new WP_Error();
		do_action( 'woocommerce_after_checkout_validation', $data, $errors );
		$this->assertEqualSets( array( 'wootoolkit_wrapping_validation', 'wootoolkit_second_email_validation' ), $errors->get_error_codes() );

		$data['wootoolkit_wrapping']     = 'plain';
		$data['wootoolkit_second_email'] = '';
		$errors                          = new WP_Error();
		do_action( 'woocommerce_after_checkout_validation', $data, $errors );
		$this->assertFalse( $errors->has_errors() );

		$order = wc_create_order();
		$order->update_meta_data( '_billing_wootoolkit_vat', 'GB123' ); // What WooCommerce saves for billing_ inputs.
		do_action( 'woocommerce_checkout_create_order', $order, $data );
		$order->save();

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( "Line one\nLine two", $order->get_meta( '_wc_other/wootoolkit/gift-message' ) );
		$this->assertSame( 'plain', $order->get_meta( '_wc_other/wootoolkit/wrapping' ) );
		$this->assertSame( '1', $order->get_meta( '_wc_other/wootoolkit/newsletter' ) );
		$this->assertSame( 'GB123', $order->get_meta( '_wc_shipping/wootoolkit/vat' ) );
		$this->assertSame( '', $order->get_meta( '_billing_wootoolkit_vat' ), 'One copy of the value.' );
		$this->assertSame( '', $order->get_meta( '_wc_other/wootoolkit/second-email' ), 'Empty values are not stored.' );
	}

	public function test_customers_get_their_saved_values_back() {
		$this->extra_fields();
		$user = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $user );
		$customer = new WC_Customer( $user );
		ClassicCheckout::save_customer(
			$customer,
			array(
				'billing_wootoolkit_vat'  => 'FR999',
				'wootoolkit_gift_message' => 'Not remembered',
			)
		);
		$customer->save();

		$this->assertSame( 'FR999', ( new WC_Customer( $user ) )->get_meta( '_wc_billing/wootoolkit/vat' ), 'Stored where the block checkout keeps it.' );
		$this->assertSame( 'FR999', ClassicCheckout::default_value( null, 'billing_wootoolkit_vat' ) );
		$this->assertNull( ClassicCheckout::default_value( null, 'wootoolkit_gift_message' ), 'Order fields are asked each time.' );
		$this->assertSame( 'x', ClassicCheckout::default_value( 'x', 'billing_wootoolkit_vat' ) );
	}

	public function test_classic_orders_show_the_values_in_emails_my_account_and_wp_admin() {
		$this->extra_fields();
		$order = wc_create_order();
		$order->set_created_via( 'checkout' );
		$order->update_meta_data( '_wc_other/wootoolkit/gift-message', '<script>x</script>Hi' );
		$order->update_meta_data( '_wc_other/wootoolkit/wrapping', 'gold' );
		$order->update_meta_data( '_wc_other/wootoolkit/newsletter', '1' );
		$order->update_meta_data( '_wc_billing/wootoolkit/vat', 'DE1' );
		$order->save();
		$order = wc_get_order( $order->get_id() );

		ob_start();
		do_action( 'woocommerce_email_customer_details', $order, false, false );
		$html = ob_get_clean();
		$this->assertStringContainsString( '<strong>Wrapping</strong>: Gold paper', $html );
		$this->assertStringContainsString( '<strong>Newsletter</strong>: Yes', $html );
		$this->assertStringNotContainsString( '<script>', $html, 'Values are escaped.' );

		ob_start();
		do_action( 'woocommerce_email_customer_address_section', 'billing', $order, false, true );
		$this->assertStringContainsString( 'VAT number: DE1', ob_get_clean() );

		ob_start();
		do_action( 'woocommerce_order_details_after_order_table', $order );
		$page = ob_get_clean();
		$this->assertStringContainsString( 'VAT number (billing)', $page );
		$this->assertStringContainsString( 'Gold paper', $page );

		$admin = apply_filters( 'woocommerce_admin_billing_fields', array(), $order, 'view' );
		$this->assertSame( 'DE1', $admin['_wc_billing/wootoolkit/vat']['value'] );
		$this->assertArrayHasKey( '_wc_other/wootoolkit/newsletter', $admin );
		$shipping = apply_filters( 'woocommerce_admin_shipping_fields', array(), $order, 'edit' );
		$this->assertSame( 'gold', $shipping['_wc_other/wootoolkit/wrapping']['value'] );
		$this->assertArrayHasKey( '_wc_shipping/wootoolkit/vat', $shipping, 'Empty fields can be filled in on the order screen.' );
	}

	public function test_order_screen_edits_need_the_capability_and_are_cleaned() {
		$this->extra_fields();
		$order = wc_create_order();
		$order->save();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		Display::admin_update( '_wc_other/wootoolkit/wrapping', 'gold', $order );
		$this->assertSame( '', $order->get_meta( '_wc_other/wootoolkit/wrapping' ), 'Customers cannot edit orders.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		Display::admin_update( '_wc_other/wootoolkit/wrapping', 'diamond', $order );
		$this->assertSame( '', $order->get_meta( '_wc_other/wootoolkit/wrapping' ), 'Values outside the choices are refused.' );
		Display::admin_update( '_wc_other/wootoolkit/wrapping', 'gold', $order );
		$_POST['_wc_other/wootoolkit/gift-message'] = "Two\nlines <em>x</em>";
		Display::admin_update( '_wc_other/wootoolkit/gift-message', 'ignored', $order );
		$order->save();

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'gold', $order->get_meta( '_wc_other/wootoolkit/wrapping' ) );
		$this->assertSame( "Two\nlines x", $order->get_meta( '_wc_other/wootoolkit/gift-message' ) );
	}

	public function test_the_settings_table_renders_escaped_rows_and_extension_hooks() {
		$this->extra_fields();
		update_option(
			Fields::option( 'order' ),
			array(
				'core'   => array( 'order_comments' => array( 'label' => '"><script>alert(1)</script>' ) ),
				'custom' => get_option( Fields::option( 'order' ) )['custom'],
			)
		);
		Fields::flush();
		$seen = array();
		add_action(
			'wootoolkit_checkout_fields_row_settings',
			static function ( $row, $prefix ) use ( &$seen ) {
				$seen[] = $prefix;
			},
			10,
			2
		);
		ob_start();
		SettingsField::render(
			array(
				'section' => 'order',
				'title'   => 'Order',
			)
		);
		$html = ob_get_clean();
		$this->assertStringNotContainsString( '<script>alert(1)', $html );
		$this->assertStringContainsString( 'wootoolkit/gift-message', $html );
		$this->assertStringContainsString( 'name="' . SettingsField::RESET . '"', $html );
		$this->assertContains( Fields::option( 'order' ) . '[custom][0]', $seen );
		$this->assertContains( Fields::option( 'order' ) . '[custom][__i__]', $seen, 'The new-row template gets the hook too.' );
	}

	public function test_types_can_be_extended() {
		add_filter(
			'wootoolkit_checkout_fields_types',
			static function ( $types ) {
				$types['date'] = array(
					'label' => 'Date',
					'block' => 'date',
				);
				return $types;
			}
		);
		$this->save(
			'order',
			array(
				'custom' => array(
					array(
						'label' => 'Delivery',
						'type'  => 'date',
					),
				),
			)
		);
		$this->assertSame( 'date', Fields::all_custom_fields()[0]['type'] );
		$this->assertSame( 'date', $this->block_fields()['wootoolkit/delivery']['type'] );
		remove_all_filters( 'wootoolkit_checkout_fields_types' );
	}
}
