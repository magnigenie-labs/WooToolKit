<?php
/**
 * Request a quote kit.
 *
 * @package WooToolkit
 */

use WooToolkit\Kits\CatalogMode\Rules;
use WooToolkit\Kits\RequestAQuote\Frontend;
use WooToolkit\Kits\RequestAQuote\QuoteList;
use WooToolkit\Kits\RequestAQuote\Statuses;
use WooToolkit\Kits\RequestAQuote\Submission;

/**
 * Quote list, quote orders, sending and paying.
 */
class Test_Request_A_Quote extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		wc_load_cart();
		QuoteList::clear();
		wc_clear_notices();
		add_filter( 'wootoolkit_quote_redirect', '__return_false' );
		reset_phpmailer_instance();
		wp_set_current_user( 0 );
	}

	public function tear_down() {
		QuoteList::clear();
		wc_clear_notices();
		remove_filter( 'wootoolkit_quote_redirect', '__return_false' );
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	private function product( $price = '40' ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Quote test' );
		$product->set_regular_price( $price );
		$product->set_status( 'publish' );
		return $product->save();
	}

	private function variable_product() {
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( 'size' );
		$attribute->set_options( array( 'Small', 'Large' ) );
		$attribute->set_variation( true );
		$attribute->set_visible( true );

		$parent = new WC_Product_Variable();
		$parent->set_name( 'Quote shirt' );
		$parent->set_status( 'publish' );
		$parent->set_attributes( array( $attribute ) );
		$parent_id = $parent->save();

		$ids    = array();
		$prices = array(
			'Small' => '10',
			'Large' => '12',
		);
		foreach ( $prices as $size => $price ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $parent_id );
			$variation->set_attributes( array( 'size' => $size ) );
			$variation->set_regular_price( $price );
			$variation->set_status( 'publish' );
			$ids[ $size ] = $variation->save();
		}
		WC_Product_Variable::sync( $parent_id );
		return array( $parent_id, $ids );
	}

	private function request( array $fields = array() ) {
		return Submission::create(
			array_merge(
				array(
					'first_name' => 'Ada',
					'last_name'  => 'Buyer',
					'email'      => 'ada@example.com',
					'company'    => 'Acme Ltd',
					'message'    => "Need 50 units.\nDelivery in May.",
				),
				$fields
			)
		);
	}

	public function test_the_quote_statuses_are_registered() {
		$statuses = wc_get_order_statuses();
		$this->assertArrayHasKey( 'wc-' . Statuses::REQUESTED, $statuses );
		$this->assertArrayHasKey( 'wc-' . Statuses::SENT, $statuses );
		$keys = array_keys( $statuses );
		$this->assertSame( array_search( 'wc-pending', $keys, true ) + 1, array_search( 'wc-' . Statuses::REQUESTED, $keys, true ), 'Quote statuses follow Pending payment.' );
	}

	public function test_adding_to_the_quote_list_merges_quantities() {
		$id = $this->product();
		QuoteList::add( $id, 2 );
		QuoteList::add( $id, 3 );
		$this->assertCount( 1, QuoteList::items() );
		$this->assertSame( 5, QuoteList::count() );
	}

	public function test_products_that_cannot_be_bought_can_still_be_quoted() {
		update_option( Rules::OPT_SCOPE, 'all' );
		Rules::flush_cache();
		$id = $this->product();
		$this->assertFalse( wc_get_product( $id )->is_purchasable() );
		$this->assertIsString( QuoteList::add( $id ) );
	}

	public function test_draft_and_unsupported_products_are_refused() {
		$draft = new WC_Product_Simple();
		$draft->set_status( 'draft' );
		$this->assertWPError( QuoteList::add( $draft->save() ) );

		$external = new WC_Product_External();
		$external->set_status( 'publish' );
		$this->assertWPError( QuoteList::add( $external->save() ) );
	}

	public function test_variable_products_need_a_variation_found_by_attributes() {
		list( $parent_id, $ids ) = $this->variable_product();
		$this->assertWPError( QuoteList::add( $parent_id ) );

		$key  = QuoteList::add( $parent_id, 1, 0, array( 'attribute_size' => 'Large' ) );
		$item = QuoteList::items()[ $key ];
		$this->assertSame( $ids['Large'], $item['variation_id'] );
	}

	public function test_the_add_request_ignores_the_add_to_cart_field() {
		$id                                  = $this->product();
		$_REQUEST['wootoolkit_add_to_quote'] = (string) $id;
		$_REQUEST['add-to-cart']             = (string) $id;
		$_REQUEST['quantity']                = '4';
		WC()->cart->empty_cart();

		Frontend::handle_request();

		$this->assertArrayNotHasKey( 'add-to-cart', $_REQUEST ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->assertSame( 4, QuoteList::count() );
		$this->assertTrue( WC()->cart->is_empty() );
		$this->assertSame( 1, wc_notice_count( 'success' ) );
	}

	public function test_updating_and_removing_items() {
		$key = QuoteList::add( $this->product(), 2 );
		QuoteList::set_quantity( $key, 7 );
		$this->assertSame( 7, QuoteList::count() );
		QuoteList::remove( $key );
		$this->assertSame( array(), QuoteList::items() );
	}

	public function test_a_request_creates_a_quote_order_and_empties_the_list() {
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $customer );
		$id = $this->product( '40' );
		QuoteList::add( $id, 3 );

		$order = $this->request();

		$this->assertInstanceOf( WC_Order::class, $order );
		$order = wc_get_order( $order->get_id() );
		$this->assertTrue( $order->has_status( Statuses::REQUESTED ) );
		$this->assertSame( 'yes', $order->get_meta( Statuses::META ) );
		$this->assertSame( 'wootoolkit_quote', $order->get_created_via() );
		$this->assertSame( $customer, $order->get_customer_id() );
		$this->assertSame( 'ada@example.com', $order->get_billing_email() );
		$this->assertSame( 'Acme Ltd', $order->get_billing_company() );
		$this->assertSame( "Need 50 units.\nDelivery in May.", $order->get_customer_note() );
		$this->assertEquals( 120, (float) $order->get_total() );
		$item = current( $order->get_items() );
		$this->assertSame( $id, $item->get_product_id() );
		$this->assertSame( 3, $item->get_quantity() );
		$this->assertTrue( $order->is_editable(), 'The merchant can edit a requested quote.' );
		$this->assertFalse( $order->needs_payment(), 'A requested quote cannot be paid yet.' );
		$this->assertSame( array(), QuoteList::items() );
	}

	public function test_the_store_is_emailed_about_a_new_request() {
		QuoteList::add( $this->product() );
		$order = $this->request();
		$mail  = tests_retrieve_phpmailer_instance()->get_sent( 0 );
		$this->assertNotFalse( $mail );
		$this->assertStringContainsString( get_option( 'admin_email' ), $mail->to[0][0] );
		$this->assertStringContainsString( 'New quote request #' . $order->get_order_number(), $mail->subject );
	}

	public function test_invalid_requests_are_refused() {
		$this->assertSame( 'wootoolkit_quote_empty', $this->request()->get_error_code() );

		QuoteList::add( $this->product() );
		$this->assertSame( 'wootoolkit_quote_required', $this->request( array( 'first_name' => '' ) )->get_error_code() );
		$this->assertSame( 'wootoolkit_quote_email', $this->request( array( 'email' => 'not-an-email' ) )->get_error_code() );
		$this->assertNotEmpty( QuoteList::items(), 'A refused request keeps the list.' );
	}

	public function test_the_honeypot_blocks_bots() {
		QuoteList::add( $this->product() );
		$back = Frontend::process_submit(
			array(
				'quote_first_name' => 'Bot',
				'quote_email'      => 'bot@example.com',
				Frontend::HONEYPOT => 'http://spam.example',
			),
			'http://example.org/quote/'
		);
		$this->assertSame( 'http://example.org/quote/', $back );
		$this->assertNotEmpty( QuoteList::items() );
		$this->assertSame( 0, count( wc_get_orders( array( 'status' => Statuses::REQUESTED ) ) ) );
	}

	public function test_sending_a_quote_recalculates_it_emails_the_customer_and_makes_it_payable() {
		QuoteList::add( $this->product( '40' ), 2 );
		$order = $this->request();
		reset_phpmailer_instance();

		// The merchant lowers the price in the order screen.
		$item = current( $order->get_items() );
		$item->set_subtotal( 60 );
		$item->set_total( 60 );
		$item->save();

		$this->assertArrayHasKey( Statuses::ACTION, Statuses::order_actions( array(), $order ) );
		do_action( 'woocommerce_order_action_' . Statuses::ACTION, $order );

		$order = wc_get_order( $order->get_id() );
		$this->assertTrue( $order->has_status( Statuses::SENT ) );
		$this->assertEquals( 60, (float) $order->get_total() );
		$this->assertTrue( $order->needs_payment() );

		$mail = tests_retrieve_phpmailer_instance()->get_sent( 0 );
		$this->assertSame( 'ada@example.com', $mail->to[0][0] );
		$this->assertStringContainsString( 'Your quote #' . $order->get_order_number(), $mail->subject );
		$this->assertStringContainsString( 'order-pay=' . $order->get_id(), $mail->body );
		$this->assertStringContainsString( 'key=' . $order->get_order_key(), $mail->body );
	}

	public function test_paying_a_sent_quote_completes_it() {
		QuoteList::add( $this->product() );
		$order = $this->request();
		Statuses::send( $order );

		$order = wc_get_order( $order->get_id() );
		$order->payment_complete( 'txn_123' );

		$order = wc_get_order( $order->get_id() );
		$this->assertTrue( $order->has_status( 'processing' ) );
		$this->assertTrue( $order->is_paid() );
		$this->assertSame( 'txn_123', $order->get_transaction_id() );
	}

	public function test_normal_orders_get_no_send_quote_action() {
		$order = wc_create_order();
		$this->assertArrayNotHasKey( Statuses::ACTION, Statuses::order_actions( array(), $order ) );
	}

	public function test_quotes_are_excluded_from_analytics() {
		$excluded = apply_filters( 'woocommerce_analytics_excluded_statuses', array() );
		$this->assertContains( Statuses::REQUESTED, $excluded );
		$this->assertContains( Statuses::SENT, $excluded );
	}

	public function test_catalog_mode_can_use_add_to_quote_as_its_button() {
		update_option( Rules::OPT_SCOPE, 'all' );
		update_option( Rules::OPT_BUTTON, 'quote' );
		Rules::flush_cache();
		$product = wc_get_product( $this->product() );

		$this->assertArrayHasKey( 'quote', Rules::buttons() );
		$loop = apply_filters( 'woocommerce_loop_add_to_cart_link', '<a>Add to cart</a>', $product, array() );
		$this->assertStringContainsString( 'wootoolkit_add_to_quote=' . $product->get_id(), $loop );

		$GLOBALS['product'] = $product;
		ob_start();
		do_action( 'woocommerce_single_product_summary' );
		$single = ob_get_clean();
		unset( $GLOBALS['product'] );
		$this->assertStringContainsString( 'name="wootoolkit_add_to_quote" value="' . $product->get_id() . '"', $single );
	}

	public function test_the_quote_page_lists_items_and_the_form() {
		$id = $this->product();
		QuoteList::add( $id, 2 );
		$html = $this->render();
		$this->assertStringContainsString( 'Quote test', $html );
		$this->assertStringContainsString( 'value="2"', $html );
		$this->assertStringContainsString( 'name="quote_email"', $html );
		$this->assertStringContainsString( 'name="' . Frontend::HONEYPOT . '"', $html );
	}

	public function test_the_thank_you_message_needs_the_order_key() {
		QuoteList::add( $this->product() );
		$order = $this->request();

		$_GET = array(
			'quote-received' => (string) $order->get_id(),
			'key'            => 'wrong',
		);
		$this->assertStringNotContainsString( 'We have received your quote request', $this->render() );

		$_GET['key'] = $order->get_order_key();
		$this->assertStringContainsString( 'We have received your quote request #' . $order->get_order_number(), $this->render() );
	}

	private function render() {
		return Frontend::render_page();
	}
}
