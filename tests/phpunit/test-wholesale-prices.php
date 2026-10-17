<?php
/**
 * Wholesale prices kit.
 *
 * @package WooToolkit
 */

use WooToolkit\Kits\WholesalePrices\Customer;
use WooToolkit\Kits\WholesalePrices\Pricing;
use WooToolkit\Kits\WholesalePrices\ProductFields;

/**
 * Wholesale price behaviour.
 */
class Test_Wholesale_Prices extends WP_UnitTestCase {

	private $wholesale_user;
	private $retail_user;

	public function set_up() {
		parent::set_up();
		Pricing::flush_cache();
		Customer::flush_cache();
		update_option( Pricing::OPT_DISCOUNT, '' );
		update_option( Pricing::OPT_SHOW_RRP, 'yes' );
		$this->wholesale_user = self::factory()->user->create( array( 'role' => Customer::ROLE ) );
		$this->retail_user    = self::factory()->user->create( array( 'role' => 'customer' ) );
	}

	public function tear_down() {
		$GLOBALS['current_screen'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$_POST                     = array();
		parent::tear_down();
	}

	private function product( $regular = '100', $wholesale = null, array $categories = array() ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Wholesale test' );
		$product->set_regular_price( $regular );
		$product->set_status( 'publish' );
		if ( null !== $wholesale ) {
			$product->update_meta_data( Pricing::META, $wholesale );
		}
		if ( $categories ) {
			$product->set_category_ids( $categories );
		}
		return $product->save();
	}

	private function as_user( $user_id ) {
		wp_set_current_user( $user_id );
		Pricing::flush_cache();
	}

	private function category( $discount ) {
		$term = wp_insert_term( 'Cat ' . wp_generate_password( 6, false ), 'product_cat' );
		update_term_meta( $term['term_id'], Pricing::TERM_META, $discount );
		return $term['term_id'];
	}

	public function test_the_wholesale_role_exists() {
		$this->assertNotNull( get_role( Customer::ROLE ) );
	}

	public function test_retail_customers_and_guests_see_the_regular_price() {
		$id = $this->product( '100', '70' );
		$this->as_user( $this->retail_user );
		$this->assertSame( '100', wc_get_product( $id )->get_price() );
		$this->as_user( 0 );
		$this->assertSame( '100', wc_get_product( $id )->get_price() );
	}

	public function test_wholesale_customers_see_the_product_wholesale_price_with_the_retail_price_crossed_out() {
		$id = $this->product( '100', '70' );
		$this->as_user( $this->wholesale_user );
		$product = wc_get_product( $id );

		$this->assertSame( '70', $product->get_price() );
		$this->assertSame( '70', $product->get_sale_price() );
		$this->assertSame( '100', $product->get_regular_price() );
		$this->assertTrue( $product->is_on_sale() );
		$html = $product->get_price_html();
		$this->assertStringContainsString( '<del', $html );
		$this->assertStringContainsString( '100', $html );
		$this->assertStringContainsString( '70', $html );
	}

	public function test_hiding_the_retail_price_shows_only_the_wholesale_price() {
		update_option( Pricing::OPT_SHOW_RRP, 'no' );
		$id = $this->product( '100', '70' );
		$this->as_user( $this->wholesale_user );
		$product = wc_get_product( $id );

		$this->assertSame( '70', $product->get_price() );
		$this->assertSame( '70', $product->get_regular_price() );
		$this->assertSame( '', $product->get_sale_price() );
		$this->assertFalse( $product->is_on_sale() );
		$this->assertStringNotContainsString( '<del', $product->get_price_html() );
	}

	public function test_store_wide_discount_applies_without_a_product_price() {
		update_option( Pricing::OPT_DISCOUNT, '10' );
		$id = $this->product( '100' );
		$this->as_user( $this->wholesale_user );
		$this->assertSame( '90', wc_get_product( $id )->get_price() );
	}

	public function test_the_highest_category_discount_beats_the_store_wide_discount() {
		update_option( Pricing::OPT_DISCOUNT, '10' );
		$id = $this->product( '100', null, array( $this->category( '25' ), $this->category( '40' ) ) );
		$this->as_user( $this->wholesale_user );
		$this->assertSame( '60', wc_get_product( $id )->get_price() );
	}

	public function test_a_product_wholesale_price_beats_category_discounts() {
		$id = $this->product( '100', '95', array( $this->category( '40' ) ) );
		$this->as_user( $this->wholesale_user );
		$this->assertSame( '95', wc_get_product( $id )->get_price() );
	}

	public function test_a_wholesale_price_above_retail_is_capped_at_retail() {
		$id = $this->product( '100', '150' );
		$this->as_user( $this->wholesale_user );
		$product = wc_get_product( $id );
		$this->assertSame( '100', $product->get_price() );
		$this->assertFalse( $product->is_on_sale() );
	}

	public function test_no_discount_and_no_product_price_keeps_retail() {
		update_option( Pricing::OPT_DISCOUNT, '0' );
		$id = $this->product( '100' );
		$this->as_user( $this->wholesale_user );
		$this->assertSame( '100', wc_get_product( $id )->get_price() );
		$this->assertFalse( wc_get_product( $id )->is_on_sale() );
	}

	public function test_variable_product_ranges_are_cached_separately_for_wholesale_and_retail() {
		update_option( Pricing::OPT_DISCOUNT, '10' );
		$parent = new WC_Product_Variable();
		$parent->set_name( 'Variable wholesale' );
		$parent->set_status( 'publish' );
		$parent_id = $parent->save();
		foreach ( array( array( '50', '30' ), array( '80', null ) ) as $i => $prices ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $parent_id );
			$variation->set_regular_price( $prices[0] );
			$variation->set_attributes( array( 'size' => 'size-' . $i ) );
			if ( null !== $prices[1] ) {
				$variation->update_meta_data( Pricing::META, $prices[1] );
			}
			$variation->save();
		}
		WC_Product_Variable::sync( $parent_id );

		$this->as_user( $this->retail_user );
		$this->assertEquals( 50, (float) wc_get_product( $parent_id )->get_variation_price( 'min' ) );

		$this->as_user( $this->wholesale_user );
		$this->assertEquals( 30, (float) wc_get_product( $parent_id )->get_variation_price( 'min' ) );
		$this->assertEquals( 72, (float) wc_get_product( $parent_id )->get_variation_price( 'max' ) );

		$this->as_user( $this->retail_user );
		$this->assertEquals( 80, (float) wc_get_product( $parent_id )->get_variation_price( 'max' ), 'Retail must not get the cached wholesale range.' );
	}

	public function test_wp_admin_always_shows_retail_prices() {
		$id = $this->product( '100', '70' );
		$this->as_user( $this->wholesale_user );
		set_current_screen( 'edit-product' );
		$this->assertSame( '100', wc_get_product( $id )->get_price() );
	}

	public function test_the_cart_charges_the_wholesale_price() {
		$id = $this->product( '100', '70' );
		$this->as_user( $this->wholesale_user );
		wc_load_cart();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $id, 2 );
		WC()->cart->calculate_totals();
		$this->assertEquals( 140, (float) WC()->cart->get_subtotal() );
		WC()->cart->empty_cart();
	}

	public function test_the_store_api_used_by_the_blocks_returns_wholesale_prices() {
		$id = $this->product( '100', '70' );
		$this->as_user( $this->wholesale_user );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/wc/store/v1/products/' . $id ) );
		$this->assertSame( 200, $response->get_status() );
		$prices = (array) $response->get_data()['prices'];
		$this->assertSame( '7000', $prices['price'] );
		$this->assertSame( '10000', $prices['regular_price'] );
		$this->assertSame( '7000', $prices['sale_price'] );
	}

	public function test_orders_from_wholesale_customers_are_tagged() {
		$order = wc_create_order();
		$this->as_user( $this->retail_user );
		ProductFields::tag_order( $order );
		$this->assertSame( '', $order->get_meta( ProductFields::ORDER_META ) );

		$this->as_user( $this->wholesale_user );
		ProductFields::tag_order( $order );
		$this->assertSame( 'yes', $order->get_meta( ProductFields::ORDER_META ) );
	}

	public function test_saving_the_product_field_stores_and_clears_the_price() {
		$product = wc_get_product( $this->product( '100' ) );

		$_POST[ Pricing::META ] = '12.50';
		ProductFields::save_product( $product );
		$this->assertSame( '12.50', $product->get_meta( Pricing::META ) );

		$_POST[ Pricing::META ] = '';
		ProductFields::save_product( $product );
		$this->assertSame( '', $product->get_meta( Pricing::META ) );

		$_POST[ Pricing::META ] = '-5';
		ProductFields::save_product( $product );
		$this->assertSame( '', $product->get_meta( Pricing::META ), 'Negative prices are rejected.' );
	}

	public function test_category_discounts_are_clamped_to_0_100() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$term                        = wp_insert_term( 'Clamp', 'product_cat' );
		$_POST[ Pricing::TERM_META ] = '150';
		ProductFields::save_category( $term['term_id'] );
		$this->assertSame( '100', get_term_meta( $term['term_id'], Pricing::TERM_META, true ) );
	}

	public function test_a_retail_sale_below_the_wholesale_price_wins() {
		$product = new WC_Product_Simple();
		$product->set_regular_price( '100' );
		$product->set_sale_price( '60' );
		$product->set_status( 'publish' );
		$product->update_meta_data( Pricing::META, '70' );
		$id = $product->save();

		$this->as_user( $this->wholesale_user );
		$product = wc_get_product( $id );
		$this->assertSame( '60', $product->get_price() );
		$this->assertTrue( $product->is_on_sale() );
		$this->assertSame( '<span>Sale!</span>', apply_filters( 'woocommerce_sale_flash', '<span>Sale!</span>', null, $product ), 'A real sale keeps its badge.' );
	}

	public function test_the_crossed_out_retail_price_has_no_sale_badge() {
		$id = $this->product( '100', '70' );
		$this->as_user( $this->wholesale_user );
		$product = wc_get_product( $id );
		$this->assertTrue( $product->is_on_sale() );
		$this->assertSame( '', apply_filters( 'woocommerce_sale_flash', '<span>Sale!</span>', null, $product ) );

		$this->as_user( $this->retail_user );
		$this->assertSame( '<span>Sale!</span>', apply_filters( 'woocommerce_sale_flash', '<span>Sale!</span>', null, wc_get_product( $id ) ) );
	}
}
