<?php
/**
 * Wishlist kit.
 *
 * @package WooToolkit
 */

use WooToolkit\Kits\Wishlist\Account;
use WooToolkit\Kits\Wishlist\Frontend;
use WooToolkit\Kits\Wishlist\Guest;
use WooToolkit\Kits\Wishlist\Maintenance;
use WooToolkit\Kits\Wishlist\Owner;
use WooToolkit\Kits\Wishlist\Settings;
use WooToolkit\Kits\Wishlist\Store;

/**
 * Storage, REST, ownership, guests, sharing, rendering and housekeeping.
 */
class Test_Wishlist extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		wc_load_cart();
		wc_clear_notices();
		Frontend::reset();
		unset( $_COOKIE[ Guest::COOKIE ], $_COOKIE[ Guest::FLAG_COOKIE ] );
		add_filter( 'wootoolkit_wishlist_redirect', '__return_false' );
		wp_set_current_user( 0 );
	}

	public function tear_down() {
		wc_clear_notices();
		Frontend::reset();
		unset( $_COOKIE[ Guest::COOKIE ], $_COOKIE[ Guest::FLAG_COOKIE ], $GLOBALS['product'] );
		remove_filter( 'wootoolkit_wishlist_redirect', '__return_false' );
		remove_all_filters( 'wootoolkit_wishlist_max_items' );
		remove_all_filters( 'wootoolkit_wishlist_client_ip' );
		remove_all_filters( 'wootoolkit_wishlist_guest_hourly_limit' );
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	private function product( $name = 'Wish mug', $status = 'publish' ) {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( '20' );
		$product->set_status( $status );
		return $product->save();
	}

	private function variable_product() {
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( 'size' );
		$attribute->set_options( array( 'Small', 'Large' ) );
		$attribute->set_variation( true );
		$attribute->set_visible( true );
		$parent = new WC_Product_Variable();
		$parent->set_name( 'Wish shirt' );
		$parent->set_status( 'publish' );
		$parent->set_attributes( array( $attribute ) );
		$parent_id = $parent->save();
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent_id );
		$variation->set_attributes( array( 'size' => 'Large' ) );
		$variation->set_regular_price( '30' );
		$variation->set_status( 'publish' );
		$variation_id = $variation->save();
		WC_Product_Variable::sync( $parent_id );
		return array( $parent_id, $variation_id );
	}

	private function rest( $method, $route, array $params = array() ) {
		$request = new WP_REST_Request( $method, '/wootoolkit/v1/wishlist' . $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request );
	}

	private function customer() {
		return self::factory()->user->create( array( 'role' => 'customer' ) );
	}

	private function product_ids( $response ) {
		return array_map( 'intval', wp_list_pluck( $response->get_data()['items'], 'product_id' ) );
	}

	public function test_the_kit_is_registered_with_sensible_defaults() {
		$registry = WooToolkit\Plugin::instance()->registry();
		$this->assertTrue( $registry->is_active( 'wishlist' ) );
		$this->assertTrue( post_type_exists( Store::POST_TYPE ) );
		$this->assertArrayHasKey( '/wootoolkit/v1/wishlist/items', rest_get_server()->get_routes() );
		$this->assertSame( 'after', Settings::position() );
		$this->assertTrue( Settings::in_loops() );
		$this->assertTrue( Settings::guests_allowed() );
		$this->assertTrue( Settings::sharing() );
		$this->assertFalse( Settings::remove_on_add() );
		$this->assertSame( 30, Settings::guest_days() );
		$this->assertSame( 'Add to wishlist', Settings::add_text() );
	}

	public function test_a_customer_adds_and_removes_through_rest() {
		$user = $this->customer();
		wp_set_current_user( $user );
		$id = $this->product();

		$response = $this->rest( 'POST', '/items', array( 'product_id' => $id ) );
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 1, $response->get_data()['count'] );
		$this->assertSame( array( $id ), $this->product_ids( $response ) );
		$this->assertSame( 'no-store, private', $response->get_headers()['Cache-Control'] );

		$lists = Store::lists( Owner::user( $user ) );
		$this->assertCount( 1, $lists );
		$this->assertSame( $user, (int) get_post_field( 'post_author', $lists[0] ) );
		$this->assertSame( 'private', get_post_status( $lists[0] ) );
		$item = Store::items( $lists[0] )[ Store::key( $id ) ];
		$this->assertSame( '20', $item['price'], 'The price when added is kept.' );
		$this->assertGreaterThan( 0, $item['added'] );
		$this->assertSame( array( (string) $id ), get_post_meta( $lists[0], Store::META_PRODUCT, false ) );

		$this->rest( 'POST', '/items', array( 'product_id' => $id ) );
		$this->assertCount( 1, Store::items( $lists[0] ), 'Adding twice keeps one item.' );

		$response = $this->rest( 'DELETE', '/items/' . $id );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 0, $response->get_data()['count'] );
		$this->assertSame( array(), get_post_meta( $lists[0], Store::META_PRODUCT, false ) );
	}

	public function test_a_guest_gets_a_random_token_and_only_a_hash_is_stored() {
		$id       = $this->product();
		$response = $this->rest( 'POST', '/items', array( 'product_id' => $id ) );
		$this->assertSame( 201, $response->get_status() );

		$token = Guest::token();
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9]{32}$/', $token );
		$this->assertSame( '1', $_COOKIE[ Guest::FLAG_COOKIE ] );
		$lists = Store::lists( Owner::guest( $token ) );
		$this->assertCount( 1, $lists );
		$this->assertSame( 0, (int) get_post_field( 'post_author', $lists[0] ) );
		$this->assertSame( hash( 'sha256', $token ), get_post_meta( $lists[0], Store::META_OWNER, true ) );
		$this->assertNotSame( $token, get_post_meta( $lists[0], Store::META_SHARE, true ), 'The share token is not the owner token.' );

		$this->assertSame( array( $id ), $this->product_ids( $this->rest( 'GET', '' ) ) );
		unset( $_COOKIE[ Guest::COOKIE ] );
		$this->assertSame( 0, $this->rest( 'GET', '' )->get_data()['count'], 'Without the cookie the list is not visible.' );
	}

	public function test_a_customer_cannot_change_another_customers_list() {
		$id    = $this->product();
		$alice = $this->customer();
		wp_set_current_user( $alice );
		$this->rest( 'POST', '/items', array( 'product_id' => $id ) );
		$alice_list = Store::lists( Owner::user( $alice ) )[0];

		wp_set_current_user( $this->customer() );
		$other  = $this->product( 'Other' );
		$params = array(
			'product_id' => $other,
			'list_id'    => $alice_list,
		);
		$this->assertSame( 404, $this->rest( 'POST', '/items', $params )->get_status() );
		$this->assertSame( 404, $this->rest( 'DELETE', '/items/' . $id, array( 'list_id' => $alice_list ) )->get_status() );
		$this->rest( 'DELETE', '/items/' . $id );
		$this->assertSame( 0, $this->rest( 'GET', '' )->get_data()['count'], 'Nobody else\'s items are reported.' );

		$this->assertSame( array( Store::key( $id ) ), array_keys( Store::items( $alice_list ) ) );
		$this->assertFalse( Store::owns( Owner::current(), $alice_list ) );
	}

	public function test_guest_tokens_are_isolated() {
		$id = $this->product();
		$this->rest( 'POST', '/items', array( 'product_id' => $id ) );
		$first_list = Store::lists( Owner::current() )[0];

		// Another browser, with a made-up token of the right shape.
		$_COOKIE[ Guest::COOKIE ] = str_repeat( 'a', 32 );
		$this->assertSame( 0, $this->rest( 'GET', '' )->get_data()['count'] );
		$this->assertFalse( Store::owns( Owner::current(), $first_list ) );
		$this->assertSame( 404, $this->rest( 'DELETE', '/items/' . $id, array( 'list_id' => $first_list ) )->get_status() );

		// A user id cannot be passed off as a guest token either.
		$_COOKIE[ Guest::COOKIE ] = 'not a token';
		$this->assertSame( '', Guest::token() );
		$this->assertFalse( Owner::current()->exists() );
		$this->assertCount( 1, Store::items( $first_list ) );
	}

	public function test_guests_must_log_in_when_guest_wishlists_are_off() {
		update_option( Settings::OPT_GUESTS, 'no' );
		$response = $this->rest( 'POST', '/items', array( 'product_id' => $this->product() ) );
		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'wootoolkit_wishlist_login_required', $response->get_data()['code'] );
		$this->assertSame( '', Guest::token(), 'No token is issued.' );
		$this->assertSame( 200, $this->rest( 'GET', '' )->get_status() );
	}

	public function test_products_are_validated_and_variations_belong_to_their_parent() {
		wp_set_current_user( $this->customer() );
		$this->assertSame( 400, $this->rest( 'POST', '/items', array( 'product_id' => $this->product( 'Draft', 'draft' ) ) )->get_status() );
		$this->assertSame( 400, $this->rest( 'POST', '/items', array( 'product_id' => 999999 ) )->get_status() );

		list( $parent, $variation ) = $this->variable_product();

		$params = array(
			'product_id'   => $this->product(),
			'variation_id' => $variation,
		);
		$this->assertSame( 400, $this->rest( 'POST', '/items', $params )->get_status() );

		// A variation id given as the product is stored as parent + variation.
		$this->rest( 'POST', '/items', array( 'product_id' => $variation ) );
		$list = Store::default_list( Owner::current() );
		$this->assertArrayHasKey( Store::key( $parent, $variation ), Store::items( $list ) );

		// The heart removes the product with all its variations; a variation id removes only that one.
		$this->rest( 'POST', '/items', array( 'product_id' => $parent ) );
		$this->assertCount( 2, Store::items( $list ) );
		$this->rest( 'DELETE', '/items/' . $variation );
		$this->assertSame( array( Store::key( $parent ) ), array_keys( Store::items( $list ) ) );
		$this->rest( 'POST', '/items', array( 'product_id' => $variation ) );
		$this->rest( 'DELETE', '/items/' . $parent );
		$this->assertSame( array(), Store::items( $list ) );
	}

	public function test_lists_are_capped() {
		add_filter(
			'wootoolkit_wishlist_max_items',
			static function () {
				return 2;
			}
		);
		wp_set_current_user( $this->customer() );
		$this->rest( 'POST', '/items', array( 'product_id' => $this->product( 'One' ) ) );
		$this->rest( 'POST', '/items', array( 'product_id' => $this->product( 'Two' ) ) );
		$response = $this->rest( 'POST', '/items', array( 'product_id' => $this->product( 'Three' ) ) );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'wootoolkit_wishlist_full', $response->get_data()['code'] );

		$this->assertWPError( Store::create_list( Owner::current(), 'Second' ), 'The free kit keeps one list per owner.' );
	}

	public function test_new_guest_lists_are_rate_limited_per_ip() {
		add_filter(
			'wootoolkit_wishlist_client_ip',
			static function () {
				return '203.0.113.9';
			}
		);
		add_filter(
			'wootoolkit_wishlist_guest_hourly_limit',
			static function () {
				return 1;
			}
		);
		delete_transient( 'wtk_wl_rate_' . md5( '203.0.113.9' ) );
		$id = $this->product();
		$this->assertSame( 201, $this->rest( 'POST', '/items', array( 'product_id' => $id ) )->get_status() );
		$this->assertSame( 201, $this->rest( 'POST', '/items', array( 'product_id' => $this->product( 'Second' ) ) )->get_status(), 'The same guest keeps adding.' );

		unset( $_COOKIE[ Guest::COOKIE ] );
		$this->assertSame( 429, $this->rest( 'POST', '/items', array( 'product_id' => $id ) )->get_status() );
		$_COOKIE[ Guest::COOKIE ] = str_repeat( 'b', 32 ); // A made-up cookie does not skip the limit.
		$this->assertSame( 429, $this->rest( 'POST', '/items', array( 'product_id' => $id ) )->get_status() );
	}

	public function test_the_guest_list_is_merged_into_the_account_on_login() {
		$a = $this->product( 'A' );
		$b = $this->product( 'B' );
		$c = $this->product( 'C' );
		$this->rest( 'POST', '/items', array( 'product_id' => $a ) );
		$this->rest( 'POST', '/items', array( 'product_id' => $b ) );
		$guest_list = Store::lists( Owner::current() )[0];

		$user = $this->customer();
		$own  = Store::create_list( Owner::user( $user ) );
		Store::add_item( $own, $c );
		Store::add_item( $own, $a );

		$merged = array();
		add_action(
			'wootoolkit_wishlist_merged',
			static function ( $list_id, $user_id, $guest_lists ) use ( &$merged ) {
				$merged = array( $list_id, $user_id, $guest_lists );
			},
			10,
			3
		);
		do_action( 'wp_login', 'buyer', get_user_by( 'id', $user ) );

		$keys = array_keys( Store::items( $own ) );
		sort( $keys );
		$expected = array( Store::key( $a ), Store::key( $b ), Store::key( $c ) );
		sort( $expected );
		$this->assertSame( $expected, $keys );
		$this->assertNull( get_post( $guest_list ), 'The guest list is deleted.' );
		$this->assertSame( '', Guest::token(), 'The guest token is forgotten.' );
		$this->assertSame( array( $own, $user, array( $guest_list ) ), $merged );
	}

	public function test_the_share_link_shows_a_read_only_list() {
		update_option( Settings::OPT_PAGE, self::factory()->post->create( array( 'post_type' => 'page' ) ) );
		$owner = $this->customer();
		wp_set_current_user( $owner );
		$id = $this->product( 'Shared candle' );
		$this->rest( 'POST', '/items', array( 'product_id' => $id ) );
		$list = Store::default_list( Owner::current() );
		$url  = Store::share_url( $list );
		$this->assertStringContainsString( 'wishlist=' . Store::share_token( $list ), $url );
		$this->assertStringContainsString( 'wootoolkit-wishlist-share', Frontend::render_list( home_url( '/' ) ), 'The owner sees the share box.' );

		wp_set_current_user( $this->customer() );
		$_GET['wishlist'] = Store::share_token( $list );
		$html             = Frontend::render_list( home_url( '/' ) );
		$this->assertStringContainsString( 'Shared candle', $html );
		$this->assertStringContainsString( '<h2 class="wootoolkit-wishlist-title">Wishlist</h2>', $html, 'The list name, without "Private:".' );
		$this->assertStringNotContainsString( 'class="remove"', $html, 'Visitors cannot remove items.' );
		$this->assertStringNotContainsString( 'wootoolkit-wishlist-share-url', $html );
		$this->assertStringNotContainsString( Frontend::LIST_PARAM, $html );

		$_GET['wishlist'] = 'wrongtoken0123456789';
		$this->assertStringContainsString( 'This wishlist is not available', Frontend::render_list( home_url( '/' ) ) );

		update_option( Settings::OPT_SHARING, 'no' );
		$_GET['wishlist'] = Store::share_token( $list );
		$this->assertStringContainsString( 'This wishlist is not available', Frontend::render_list( home_url( '/' ) ) );
		$this->assertSame( '', Store::share_url( $list ) );
	}

	public function test_classic_hearts_carry_no_visitor_state() {
		$user = $this->customer();
		wp_set_current_user( $user );
		$id = $this->product();
		$this->rest( 'POST', '/items', array( 'product_id' => $id ) );

		$GLOBALS['product'] = wc_get_product( $id );
		ob_start();
		Frontend::single_button();
		Frontend::single_button();
		$single = ob_get_clean();
		$this->assertSame( 1, substr_count( $single, 'data-product-id=' ), 'One heart per product page.' );
		$this->assertStringContainsString( 'data-product-id="' . $id . '"', $single );
		$this->assertStringContainsString( 'aria-pressed="false"', $single, 'The state is filled in by the script, so the page stays cacheable.' );
		$this->assertStringContainsString( Frontend::ADD_PARAM . '=' . $id, html_entity_decode( $single ) );
		$this->assertStringContainsString( '_wpnonce=', $single, 'The no-JS link has a nonce.' );
		$this->assertTrue( wp_script_is( 'wootoolkit-wishlist', 'enqueued' ) );

		ob_start();
		Frontend::loop_button();
		$this->assertStringContainsString( 'wootoolkit-wishlist-button--loop', ob_get_clean() );

		update_option( Settings::OPT_LOOP, 'no' );
		ob_start();
		Frontend::loop_button();
		$this->assertSame( '', ob_get_clean() );

		$this->assertSame( 35, has_action( 'woocommerce_single_product_summary', array( Frontend::class, 'single_button' ) ) );
		$this->assertSame( 15, has_action( 'woocommerce_after_shop_loop_item', array( Frontend::class, 'loop_button' ) ) );
	}

	public function test_block_markup_gets_hearts() {
		$id    = $this->product();
		$block = new WP_Block( array( 'blockName' => 'woocommerce/product-button' ), array( 'postId' => $id ) );
		$html  = apply_filters( 'render_block_woocommerce/product-button', '<div class="wp-block-button"></div>', array(), $block ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- core block hook.
		$this->assertStringStartsWith( '<div class="wp-block-button"></div>', $html );
		$this->assertStringContainsString( 'data-product-id="' . $id . '"', $html );

		$form = new WP_Block( array( 'blockName' => 'woocommerce/add-to-cart-form' ), array( 'postId' => $id ) );
		$html = apply_filters( 'render_block_woocommerce/add-to-cart-form', '<form class="cart"></form>', array(), $form ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- core block hook.
		$this->assertStringStartsWith( '<form class="cart"></form><div class="wootoolkit-wishlist-wrap', $html );
		$again = apply_filters( 'render_block_woocommerce/add-to-cart-with-options', '<div></div>', array(), $form ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- core block hook.
		$this->assertSame( '<div></div>', $again, 'Not twice on one page.' );

		Frontend::reset();
		update_option( Settings::OPT_POSITION, 'before' );
		$html = apply_filters( 'render_block_woocommerce/add-to-cart-with-options', '<div class="atc"></div>', array(), $form ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- core block hook.
		$this->assertStringEndsWith( '<div class="atc"></div>', $html );
		$this->assertStringContainsString( 'wootoolkit-wishlist-button--single', $html );
	}

	public function test_the_no_js_link_needs_a_valid_nonce() {
		$id                          = $this->product( 'Link mug' );
		$_GET[ Frontend::ADD_PARAM ] = (string) $id;
		$_GET['_wpnonce']            = 'bad';
		Frontend::handle_request();
		$this->assertSame( array(), Store::owner_items( Owner::current() ) );
		$this->assertSame( 1, wc_notice_count( 'error' ) );

		wc_clear_notices();
		$_GET['_wpnonce'] = wp_create_nonce( Frontend::NONCE );
		Frontend::handle_request();
		$this->assertSame( array( $id ), array_map( 'intval', wp_list_pluck( Store::owner_items( Owner::current() ), 'product_id' ) ) );
		$this->assertSame( 1, wc_notice_count( 'success' ) );
	}

	public function test_the_wishlist_page_lists_items_and_removes_only_own_items() {
		$user = $this->customer();
		wp_set_current_user( $user );
		$id = $this->product( 'Table lamp' );
		$this->rest( 'POST', '/items', array( 'product_id' => $id ) );
		$list = Store::default_list( Owner::current() );

		$html = Frontend::render_list( home_url( '/wishlist/' ) );
		$this->assertStringContainsString( 'Table lamp', $html );
		$this->assertStringContainsString( 'add-to-cart=' . $id, $html );
		$this->assertStringContainsString( 'In stock', $html );
		$this->assertStringContainsString( 'class="remove"', $html );

		// Someone else's remove link (their nonce, this list) does nothing.
		wp_set_current_user( $this->customer() );
		$_GET = array(
			'wootoolkit_wishlist_action' => 'remove',
			'item'                       => Store::key( $id ),
			'list'                       => (string) $list,
			'_wpnonce'                   => wp_create_nonce( Frontend::NONCE ),
		);
		Frontend::handle_request();
		$this->assertCount( 1, Store::items( $list ) );

		wp_set_current_user( $user );
		$_GET['_wpnonce'] = wp_create_nonce( Frontend::NONCE );
		Frontend::handle_request();
		$this->assertCount( 0, Store::items( $list ) );
		$this->assertStringContainsString( 'Your wishlist is empty', Frontend::render_list( home_url( '/wishlist/' ) ) );
	}

	public function test_products_can_leave_the_list_when_added_to_the_cart_from_it() {
		wp_set_current_user( $this->customer() );
		$id = $this->product();
		$this->rest( 'POST', '/items', array( 'product_id' => $id ) );
		$list = Store::default_list( Owner::current() );

		WC()->cart->add_to_cart( $id );
		$this->assertCount( 1, Store::items( $list ), 'Off by default.' );

		update_option( Settings::OPT_REMOVE_ON_ADD, 'yes' );
		WC()->cart->add_to_cart( $id );
		$this->assertCount( 1, Store::items( $list ), 'Only when added from the wishlist page.' );

		$_REQUEST[ Frontend::LIST_PARAM ] = (string) $list;
		$_REQUEST['_wtk_wl']              = wp_create_nonce( Frontend::NONCE );
		WC()->cart->add_to_cart( $id );
		$this->assertCount( 0, Store::items( $list ) );
	}

	public function test_my_account_has_a_wishlist_page() {
		$items = apply_filters(
			'woocommerce_account_menu_items',
			array(
				'dashboard'       => 'Dashboard',
				'orders'          => 'Orders',
				'customer-logout' => 'Log out',
			)
		);
		$this->assertSame( array( 'dashboard', 'orders', Account::ENDPOINT, 'customer-logout' ), array_keys( $items ) );
		$this->assertArrayHasKey( Account::ENDPOINT, WC()->query->get_query_vars() );
	}

	public function test_the_wishlist_page_is_created_once_in_wp_admin() {
		delete_option( Settings::OPT_PAGE );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		Frontend::maybe_create_page();
		$page_id = (int) get_option( Settings::OPT_PAGE );
		$this->assertSame( '<!-- wp:wootoolkit/wishlist /-->', get_post_field( 'post_content', $page_id ) );
		wp_delete_post( $page_id, true );
		Frontend::maybe_create_page();
		$this->assertSame( $page_id, (int) get_option( Settings::OPT_PAGE ), 'A deleted page is not recreated.' );
		$this->assertSame( 0, Settings::page_id() );
	}

	public function test_old_guest_lists_are_cleaned_up() {
		global $wpdb;
		$id = $this->product();
		$this->rest( 'POST', '/items', array( 'product_id' => $id ) );
		$old_guest = Store::lists( Owner::current() )[0];
		unset( $_COOKIE[ Guest::COOKIE ] );
		$this->rest( 'POST', '/items', array( 'product_id' => $id ) );
		$new_guest = Store::lists( Owner::current() )[0];
		$old_user  = Store::create_list( Owner::user( $this->customer() ) );

		$long_ago = gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS );
		foreach ( array( $old_guest, $old_user ) as $list_id ) {
			$wpdb->update( $wpdb->posts, array( 'post_modified_gmt' => $long_ago ), array( 'ID' => $list_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $list_id );
		}
		$this->assertSame( 1, Maintenance::cleanup() );
		$this->assertNull( get_post( $old_guest ) );
		$this->assertNotNull( get_post( $new_guest ), 'Recent guest lists stay.' );
		$this->assertNotNull( get_post( $old_user ), 'Customer lists are never cleaned up.' );
	}

	public function test_nothing_is_output_when_the_kit_is_off() {
		$id    = $this->product();
		$block = new WP_Block( array( 'blockName' => 'woocommerce/product-button' ), array( 'postId' => $id ) );
		Frontend::remove_hooks();
		try {
			$this->assertSame( '<div></div>', apply_filters( 'render_block_woocommerce/product-button', '<div></div>', array(), $block ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- core block hook.
			$this->assertSame( '[wootoolkit_wishlist]', do_shortcode( '[wootoolkit_wishlist]' ) );
			$this->assertFalse( has_action( 'woocommerce_after_shop_loop_item', array( Frontend::class, 'loop_button' ) ) );
			$this->assertFalse( has_action( 'woocommerce_single_product_summary', array( Frontend::class, 'single_button' ) ) );
		} finally {
			Frontend::hooks();
		}

		$registry = new WooToolkit\Kit\Registry();
		$kit      = new WooToolkit_Test_Kit( 'wishlist' );
		$registry->register( $kit );
		update_option( WooToolkit\Kit\Registry::OPTION, array( 'wishlist' => 'no' ) );
		$registry->boot_active();
		$this->assertSame( 0, $kit->boots, 'A switched-off kit is never booted.' );
	}
}
