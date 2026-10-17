<?php
/**
 * Catalog mode kit.
 *
 * @package WooToolkit
 */

use WooToolkit\Kits\CatalogMode\ProductField;
use WooToolkit\Kits\CatalogMode\Rules;
use WooToolkit\Kits\WholesalePrices\Customer;

/**
 * Catalog mode behaviour. The bootstrap switches the kit on with the "chosen products" scope.
 */
class Test_Catalog_Mode extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		Rules::flush_cache();
		Customer::flush_cache();
		update_option( Rules::OPT_SCOPE, 'all' );
		update_option( Rules::OPT_AUDIENCE, 'all' );
		update_option( Rules::OPT_BLOCK_PURCHASE, 'yes' );
		update_option( Rules::OPT_HIDE_PRICE, 'yes' );
		update_option( Rules::OPT_PRICE_TEXT, '' );
		update_option( Rules::OPT_BUTTON, 'none' );
		wp_set_current_user( 0 );
	}

	public function tear_down() {
		$GLOBALS['current_screen'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$_POST                     = array();
		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}
		parent::tear_down();
	}

	private function product( array $categories = array(), $flagged = false ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Catalog test' );
		$product->set_regular_price( '25' );
		$product->set_status( 'publish' );
		$product->set_category_ids( $categories );
		if ( $flagged ) {
			$product->update_meta_data( Rules::META, 'yes' );
		}
		return wc_get_product( $product->save() );
	}

	private function as_user( $user_id ) {
		wp_set_current_user( $user_id );
		Rules::flush_cache();
		Customer::flush_cache();
	}

	public function test_catalog_products_are_not_purchasable_and_have_no_price() {
		$product = $this->product();
		$this->assertFalse( $product->is_purchasable() );
		$this->assertSame( '', $product->get_price_html() );
		$this->assertSame( '25', $product->get_price(), 'The stored price is untouched.' );
	}

	public function test_the_price_can_be_replaced_by_text() {
		update_option( Rules::OPT_PRICE_TEXT, 'Log in <b>to see</b> prices' );
		$html = $this->product()->get_price_html();
		$this->assertStringContainsString( 'Log in &lt;b&gt;to see&lt;/b&gt; prices', $html );
	}

	public function test_prices_can_stay_visible_while_purchases_are_blocked() {
		update_option( Rules::OPT_HIDE_PRICE, 'no' );
		$product = $this->product();
		$this->assertFalse( $product->is_purchasable() );
		$this->assertStringContainsString( '25', $product->get_price_html() );
	}

	public function test_purchases_can_stay_open_while_prices_are_hidden() {
		update_option( Rules::OPT_BLOCK_PURCHASE, 'no' );
		$this->assertTrue( $this->product()->is_purchasable() );
	}

	public function test_the_cart_refuses_catalog_products() {
		$product = $this->product();
		wc_load_cart();
		WC()->cart->empty_cart();
		$this->assertFalse( WC()->cart->add_to_cart( $product->get_id() ) );
		$this->assertTrue( WC()->cart->is_empty() );
		wc_clear_notices();
	}

	public function test_the_store_api_refuses_catalog_products_and_hides_their_price() {
		$product = $this->product();

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wc/store/v1/products/' . $product->get_id() ) );
		$this->assertSame( 200, $response->get_status() );
		$data   = $response->get_data();
		$prices = (array) $data['prices'];
		$this->assertFalse( $data['is_purchasable'] );
		$this->assertSame( '', $prices['price'] );
		$this->assertSame( '', $prices['regular_price'] );

		$list = rest_do_request( new WP_REST_Request( 'GET', '/wc/store/v1/products' ) )->get_data();
		$item = current( wp_list_filter( $list, array( 'id' => $product->get_id() ) ) );
		$this->assertSame( '', ( (array) $item['prices'] )['price'] );
	}

	public function test_audience_guests_only() {
		update_option( Rules::OPT_AUDIENCE, 'guests' );
		$product = $this->product();
		$this->assertFalse( $product->is_purchasable() );
		$this->as_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$this->assertTrue( wc_get_product( $product->get_id() )->is_purchasable() );
	}

	public function test_audience_everyone_except_wholesale_customers() {
		update_option( Rules::OPT_AUDIENCE, 'retail' );
		$product = $this->product();
		$this->as_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$this->assertFalse( wc_get_product( $product->get_id() )->is_purchasable() );
		$this->as_user( self::factory()->user->create( array( 'role' => Customer::ROLE ) ) );
		$this->assertTrue( wc_get_product( $product->get_id() )->is_purchasable() );
	}

	public function test_scope_categories_includes_subcategories() {
		$parent = wp_insert_term( 'Catalog parent', 'product_cat' )['term_id'];
		$child  = wp_insert_term( 'Catalog child', 'product_cat', array( 'parent' => $parent ) )['term_id'];
		$other  = wp_insert_term( 'Shop', 'product_cat' )['term_id'];
		update_option( Rules::OPT_SCOPE, 'categories' );
		update_option( Rules::OPT_CATEGORIES, array( (string) $parent ) );

		$this->assertFalse( $this->product( array( $child ) )->is_purchasable() );
		$this->assertTrue( $this->product( array( $other ) )->is_purchasable() );
	}

	public function test_scope_chosen_products() {
		update_option( Rules::OPT_SCOPE, 'products' );
		$this->assertFalse( $this->product( array(), true )->is_purchasable() );
		$this->assertTrue( $this->product()->is_purchasable() );
	}

	public function test_variations_follow_their_parent() {
		update_option( Rules::OPT_SCOPE, 'products' );
		$parent = new WC_Product_Variable();
		$parent->set_name( 'Catalog variable' );
		$parent->update_meta_data( Rules::META, 'yes' );
		$parent_id = $parent->save();
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent_id );
		$variation->set_regular_price( '10' );
		$variation->set_status( 'publish' );
		$variation_id = $variation->save();

		$this->assertFalse( wc_get_product( $variation_id )->is_purchasable() );
	}

	public function test_wp_admin_is_never_affected() {
		$product = $this->product();
		set_current_screen( 'edit-product' );
		Rules::flush_cache();
		$this->assertTrue( wc_get_product( $product->get_id() )->is_purchasable() );
	}

	public function test_loop_button_is_replaced() {
		$product = $this->product();
		$this->assertSame( '', apply_filters( 'woocommerce_loop_add_to_cart_link', '<a>Add to cart</a>', $product, array() ) );

		update_option( Rules::OPT_BUTTON, 'product' );
		$html = apply_filters( 'woocommerce_loop_add_to_cart_link', '<a>Add to cart</a>', $product, array() );
		$this->assertStringContainsString( 'href="' . esc_url( $product->get_permalink() ) . '"', $html );
		$this->assertStringContainsString( 'View product', $html );

		update_option( Rules::OPT_BUTTON, 'custom' );
		update_option( Rules::OPT_BUTTON_URL, 'https://example.com/contact' );
		update_option( Rules::OPT_BUTTON_TEXT, 'Ask us' );
		$html = apply_filters( 'woocommerce_loop_add_to_cart_link', '<a>Add to cart</a>', $product, array() );
		$this->assertStringContainsString( 'href="https://example.com/contact"', $html );
		$this->assertStringContainsString( 'Ask us', $html );
	}

	public function test_add_to_cart_blocks_are_replaced_on_the_product_page() {
		$product = $this->product();
		update_option( Rules::OPT_BUTTON, 'custom' );
		update_option( Rules::OPT_BUTTON_URL, 'https://example.com/contact' );
		$block = new WP_Block(
			array( 'blockName' => 'woocommerce/add-to-cart-form' ),
			array( 'postId' => $product->get_id() )
		);
		$html  = apply_filters( 'render_block_woocommerce/add-to-cart-form', '<form class="cart"></form>', array(), $block ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- core block hook.
		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringContainsString( 'https://example.com/contact', $html );
	}

	public function test_classic_product_page_drops_the_form_and_restores_it_afterwards() {
		$GLOBALS['product'] = $this->product();
		ob_start();
		do_action( 'woocommerce_single_product_summary' );
		ob_end_clean();
		$this->assertSame( 30, has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart' ) );
		unset( $GLOBALS['product'] );
	}

	public function test_structured_data_has_no_offer_when_the_price_is_hidden() {
		$product = $this->product();
		$markup  = apply_filters(
			'woocommerce_structured_data_product',
			array(
				'name'   => 'x',
				'offers' => array( 'price' => '25' ),
			),
			$product
		);
		$this->assertArrayNotHasKey( 'offers', $markup );
	}

	public function test_saving_the_product_checkbox() {
		$product              = $this->product();
		$_POST[ Rules::META ] = 'yes';
		ProductField::save( $product );
		$this->assertSame( 'yes', $product->get_meta( Rules::META ) );
		$_POST = array();
		ProductField::save( $product );
		$this->assertSame( 'no', $product->get_meta( Rules::META ) );
	}
}
