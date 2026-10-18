<?php
/**
 * Wishlist on the storefront: heart buttons, the wishlist page, the counter and the no-JS requests.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\Wishlist;

use WooToolkit\Kit\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Hearts are printed without any per-visitor state, so product pages and loops stay cacheable; the
 * storefront script asks the REST endpoint which products are on the visitor's list and updates the
 * hearts and counters. Each heart is also a link (with a nonce) that works without JavaScript.
 * The wishlist page itself is personal and is excluded from page caches.
 */
final class Frontend {

	const NONCE      = 'wootoolkit_wishlist';
	const ADD_PARAM  = 'wootoolkit_wishlist_add';
	const LIST_PARAM = 'wootoolkit_wishlist_list';
	const SHARE_VAR  = 'wishlist';

	/**
	 * Products whose product-page heart was already printed in this request.
	 *
	 * @var array<int, bool>
	 */
	private static $single_rendered = array();

	/**
	 * Whether the script config was added.
	 *
	 * @var bool
	 */
	private static $configured = false;

	/**
	 * Hooked filters and actions: hook => [method, priority, accepted args].
	 *
	 * @return array<string, array{0: string, 1: int, 2: int}>
	 */
	public static function filters(): array {
		$filters = array(
			'wp_loaded'                  => array( 'handle_request', 15, 0 ), // Before WooCommerce's add-to-cart handler (20).
			'wp_enqueue_scripts'         => array( 'enqueue', 10, 0 ),
			'template_redirect'          => array( 'template_redirect', 10, 0 ),
			'admin_init'                 => array( 'maybe_create_page', 10, 0 ),
			'woocommerce_add_to_cart'    => array( 'remove_after_add_to_cart', 10, 4 ),
			'wp_login'                   => array( 'merge_on_login', 10, 2 ),
			'set_logged_in_cookie'       => array( 'merge_on_auth_cookie', 10, 4 ),
			'render_block_woocommerce/product-button' => array( 'render_loop_block', 10, 3 ),
			'render_block_woocommerce/add-to-cart-form' => array( 'render_single_block', 10, 3 ),
			'render_block_woocommerce/add-to-cart-with-options' => array( 'render_single_block', 10, 3 ),
			'woocommerce_after_shop_loop_item' => array( 'loop_button', 15, 0 ),
		);
		$position = Settings::position();
		if ( 'none' !== $position ) {
			$filters['woocommerce_single_product_summary'] = array( 'single_button', 'before' === $position ? 25 : 35, 0 );
		}
		return $filters;
	}

	/**
	 * Shortcodes: tag => method.
	 *
	 * @return array<string, string>
	 */
	public static function shortcodes(): array {
		return array(
			'wootoolkit_wishlist'        => 'render_page',
			'wootoolkit_wishlist_count'  => 'render_count',
			'wootoolkit_wishlist_button' => 'render_button_shortcode',
		);
	}

	/**
	 * Hooks the storefront.
	 */
	public static function hooks(): void {
		foreach ( self::filters() as $hook => $args ) {
			add_filter( $hook, array( self::class, $args[0] ), $args[1], $args[2] );
		}
		foreach ( self::shortcodes() as $tag => $method ) {
			add_shortcode( $tag, array( self::class, $method ) );
		}
		self::register_blocks();
	}

	/**
	 * Removes the storefront hooks (the kit switched off within one request, e.g. in tests).
	 */
	public static function remove_hooks(): void {
		foreach ( self::filters() as $hook => $args ) {
			remove_filter( $hook, array( self::class, $args[0] ), $args[1] );
		}
		foreach ( array_keys( self::shortcodes() ) as $tag ) {
			remove_shortcode( $tag );
		}
	}

	/**
	 * Forgets which product-page hearts were printed (tests, several renders).
	 */
	public static function reset(): void {
		self::$single_rendered = array();
		self::$configured      = false;
	}

	/**
	 * Registers the Wishlist and Wishlist counter blocks (rendered on the server).
	 */
	private static function register_blocks(): void {
		wp_register_script(
			'wootoolkit-wishlist-blocks',
			plugins_url( 'assets/js/wishlist-blocks.js', WOOTOOLKIT_FILE ),
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor' ),
			WOOTOOLKIT_VERSION,
			true
		);
		$blocks = array(
			'wootoolkit/wishlist'       => 'render_page',
			'wootoolkit/wishlist-count' => 'render_count',
		);
		foreach ( $blocks as $name => $method ) {
			if ( \WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				continue;
			}
			register_block_type(
				$name,
				array(
					'api_version'     => '3',
					'editor_script'   => 'wootoolkit-wishlist-blocks',
					'render_callback' => array( self::class, $method ),
					'supports'        => array( 'html' => false ),
				)
			);
		}
	}

	/**
	 * Registers the assets, and enqueues them on shop pages and the wishlist page. Other pages get
	 * them when a heart or counter is printed.
	 */
	public static function enqueue(): void {
		wp_register_style( 'wootoolkit-wishlist', plugins_url( 'assets/css/wishlist.css', WOOTOOLKIT_FILE ), array(), WOOTOOLKIT_VERSION );
		wp_register_script( 'wootoolkit-wishlist', plugins_url( 'assets/js/wishlist.js', WOOTOOLKIT_FILE ), array(), WOOTOOLKIT_VERSION, true );
		if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_account_page() || self::is_wishlist_page() ) ) {
			self::enqueue_assets();
		}
	}

	/**
	 * Enqueues the style and script (with its config, once).
	 */
	public static function enqueue_assets(): void {
		if ( ! wp_script_is( 'wootoolkit-wishlist', 'registered' ) ) {
			self::enqueue();
		}
		wp_enqueue_style( 'wootoolkit-wishlist' );
		wp_enqueue_script( 'wootoolkit-wishlist' );
		if ( self::$configured ) {
			return;
		}
		self::$configured = true;
		$config           = array(
			'rest'       => esc_url_raw( rest_url( Rest::NAMESPACE_V1 . '/wishlist' ) ),
			// Only for logged-in visitors, whose pages caches do not keep; refreshed by the script if stale.
			'nonce'      => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'loggedIn'   => is_user_logged_in(),
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'loginUrl'   => wc_get_page_permalink( 'myaccount' ),
			'pageUrl'    => Settings::page_url(),
			'flagCookie' => Guest::FLAG_COOKIE,
			'i18n'       => array(
				'added'   => __( 'Added to your wishlist.', 'wootoolkit' ),
				'removed' => __( 'Removed from your wishlist.', 'wootoolkit' ),
				'error'   => __( 'Your wishlist could not be updated. Please try again.', 'wootoolkit' ),
				'copied'  => __( 'Link copied.', 'wootoolkit' ),
			),
		);
		/**
		 * The storefront script's settings.
		 *
		 * @param array<string, mixed> $config Config.
		 */
		$config = (array) apply_filters( 'wootoolkit_wishlist_script_config', $config );
		wp_add_inline_script( 'wootoolkit-wishlist', 'window.wootoolkitWishlist = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Whether the current request is the wishlist page.
	 */
	public static function is_wishlist_page(): bool {
		$page_id = Settings::page_id();
		return $page_id > 0 && is_page( $page_id );
	}

	/**
	 * The wishlist page is personal: keep it out of page caches. A share link view is not indexed.
	 */
	public static function template_redirect(): void {
		if ( ! self::is_wishlist_page() ) {
			return;
		}
		wc_nocache_headers();
		if ( '' !== self::share_token_from_request() ) {
			add_filter( 'wp_robots', 'wp_robots_no_robots' );
		}
	}

	/**
	 * Creates the wishlist page the first time the kit runs in wp-admin. Never recreates it, so a page
	 * the owner deleted stays deleted (they can choose another in the settings).
	 */
	public static function maybe_create_page(): void {
		if ( false !== get_option( Settings::OPT_PAGE, false ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Wishlist', 'wootoolkit' ),
				'post_name'    => 'wishlist',
				'post_content' => '<!-- wp:wootoolkit/wishlist /-->',
			)
		);
		update_option( Settings::OPT_PAGE, is_int( $page_id ) ? $page_id : 0 );
	}

	/**
	 * The heart button for a product.
	 *
	 * @param \WC_Product $product Product (a variation is shown as its parent).
	 * @param string      $context 'single', 'loop' or 'shortcode'.
	 */
	public static function button( \WC_Product $product, string $context = 'loop' ): string {
		if ( ! Support::is_storefront() ) {
			return '';
		}
		$product_id = $product instanceof \WC_Product_Variation ? $product->get_parent_id() : $product->get_id();
		if ( 'publish' !== get_post_status( $product_id ) ) {
			return '';
		}
		self::enqueue_assets();

		$style = Settings::style();
		$url   = add_query_arg(
			array(
				self::ADD_PARAM => $product_id,
				'_wpnonce'      => wp_create_nonce( self::NONCE ),
			),
			(string) get_permalink( $product_id )
		);
		$class = 'wootoolkit-wishlist-button wootoolkit-wishlist-button--' . $context . ' wootoolkit-wishlist-button--' . str_replace( '_', '-', $style );
		$icon  = 'text' === $style ? '' : '<svg class="wootoolkit-wishlist-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M12 20.7l-1.3-1.2C5.6 14.9 2.5 12.1 2.5 8.6 2.5 5.8 4.7 3.6 7.5 3.6c1.6 0 3.1.7 4.5 2.1 1.4-1.4 2.9-2.1 4.5-2.1 2.8 0 5 2.2 5 5 0 3.5-3.1 6.3-8.2 10.9L12 20.7z"/></svg>';
		$html  = sprintf(
			'<a href="%1$s" class="%2$s" role="button" aria-pressed="false" rel="nofollow" data-product-id="%3$d" data-add-text="%4$s" data-added-text="%5$s">%6$s<span class="wootoolkit-wishlist-label%7$s">%8$s</span></a>',
			esc_url( $url ),
			esc_attr( $class ),
			$product_id,
			esc_attr( Settings::add_text() ),
			esc_attr( Settings::added_text() ),
			$icon,
			'icon' === $style ? ' screen-reader-text' : '',
			esc_html( Settings::add_text() )
		);
		$html = '<div class="wootoolkit-wishlist-wrap wootoolkit-wishlist-wrap--' . esc_attr( $context ) . '">' . $html . '</div>';

		/**
		 * The heart button HTML. It must not contain per-visitor state (pages with it are cached).
		 *
		 * @param string      $html    Button HTML.
		 * @param \WC_Product $product Product.
		 * @param string      $context 'single', 'loop' or 'shortcode'.
		 */
		return (string) apply_filters( 'wootoolkit_wishlist_button_html', $html, $product, $context );
	}

	/**
	 * Classic product page heart (woocommerce_single_product_summary).
	 */
	public static function single_button(): void {
		global $product;
		if ( $product instanceof \WC_Product ) {
			echo self::single_once( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in button().
		}
	}

	/**
	 * The product-page heart, once per product per request (classic templates inside the add-to-cart
	 * block would otherwise print it twice).
	 *
	 * @param \WC_Product $product Product.
	 */
	private static function single_once( \WC_Product $product ): string {
		if ( isset( self::$single_rendered[ $product->get_id() ] ) ) {
			return '';
		}
		$html = self::button( $product, 'single' );
		if ( '' !== $html ) {
			self::$single_rendered[ $product->get_id() ] = true;
		}
		return $html;
	}

	/**
	 * Block themes: the heart before or after the Add to cart form / Add to cart with options block.
	 *
	 * @param string               $content  Block HTML.
	 * @param array<string, mixed> $block    Parsed block.
	 * @param \WP_Block|null       $instance Block instance.
	 */
	public static function render_single_block( $content, $block = array(), $instance = null ): string {
		unset( $block );
		$content  = (string) $content;
		$position = Settings::position();
		if ( 'none' === $position ) {
			return $content;
		}
		$product = self::block_product( $instance );
		if ( ! $product ) {
			return $content;
		}
		$html = self::single_once( $product );
		return 'before' === $position ? $html . $content : $content . $html;
	}

	/**
	 * Classic product loops (shop, categories, [products] shortcode).
	 */
	public static function loop_button(): void {
		global $product;
		if ( Settings::in_loops() && $product instanceof \WC_Product ) {
			echo self::button( $product, 'loop' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in button().
		}
	}

	/**
	 * Product blocks (Product Collection, All Products etc.): after the Add to cart button block.
	 *
	 * @param string               $content  Block HTML.
	 * @param array<string, mixed> $block    Parsed block.
	 * @param \WP_Block|null       $instance Block instance.
	 */
	public static function render_loop_block( $content, $block = array(), $instance = null ): string {
		unset( $block );
		$content = (string) $content;
		if ( ! Settings::in_loops() ) {
			return $content;
		}
		$product = self::block_product( $instance );
		return $product ? $content . self::button( $product, 'loop' ) : $content;
	}

	/**
	 * The product a block is about: its postId context, else the global product.
	 *
	 * @param \WP_Block|null $instance Block instance.
	 */
	private static function block_product( $instance ): ?\WC_Product {
		$product = null;
		if ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) {
			$product = wc_get_product( (int) $instance->context['postId'] );
		}
		if ( ! $product instanceof \WC_Product && isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof \WC_Product ) {
			$product = $GLOBALS['product'];
		}
		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * [wootoolkit_wishlist_button id="123"]: a heart anywhere (page builders, custom layouts).
	 *
	 * @param array<string, string>|string $atts Attributes.
	 */
	public static function render_button_shortcode( $atts = array() ): string {
		$atts    = shortcode_atts( array( 'id' => '' ), (array) $atts, 'wootoolkit_wishlist_button' );
		$product = wc_get_product( '' !== $atts['id'] ? absint( $atts['id'] ) : get_the_ID() );
		return $product instanceof \WC_Product ? self::button( $product, 'shortcode' ) : '';
	}

	/**
	 * The header counter (shortcode and block). The number is filled in by the script, so cached
	 * pages never show another visitor's count.
	 *
	 * @param array<string, string>|string $atts Attributes.
	 */
	public static function render_count( $atts = array() ): string {
		if ( ! Support::is_storefront() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return '';
		}
		$atts = shortcode_atts( array( 'label' => __( 'Wishlist', 'wootoolkit' ) ), (array) $atts, 'wootoolkit_wishlist_count' );
		self::enqueue_assets();
		$url = Settings::page_url();
		return sprintf(
			'<a class="wootoolkit-wishlist-counter" href="%1$s"><svg class="wootoolkit-wishlist-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M12 20.7l-1.3-1.2C5.6 14.9 2.5 12.1 2.5 8.6 2.5 5.8 4.7 3.6 7.5 3.6c1.6 0 3.1.7 4.5 2.1 1.4-1.4 2.9-2.1 4.5-2.1 2.8 0 5 2.2 5 5 0 3.5-3.1 6.3-8.2 10.9L12 20.7z"/></svg><span class="wootoolkit-wishlist-counter-label">%2$s</span> <span class="wootoolkit-wishlist-count" data-wootoolkit-wishlist-count aria-live="polite"></span></a>',
			esc_url( '' !== $url ? $url : home_url( '/' ) ),
			esc_html( (string) $atts['label'] )
		);
	}

	/**
	 * The share token in the request, '' when there is none.
	 */
	public static function share_token_from_request(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view, the token is the secret.
		$token = isset( $_GET[ self::SHARE_VAR ] ) && is_string( $_GET[ self::SHARE_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::SHARE_VAR ] ) ) : '';
		return preg_match( '/^[A-Za-z0-9]{1,64}$/', $token ) ? $token : '';
	}

	/**
	 * The wishlist page (shortcode and block).
	 */
	public static function render_page(): string {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return '<p>' . esc_html__( 'The shopper\'s wishlist appears here.', 'wootoolkit' ) . '</p>';
		}
		$base = self::is_wishlist_page() || '' === Settings::page_url() ? (string) get_permalink() : Settings::page_url();
		return self::render_list( '' !== $base ? $base : home_url( '/' ) );
	}

	/**
	 * The wishlist table for the current visitor, or the shared list in the request.
	 *
	 * @param string $base_url URL of the page it is shown on (for the remove links and forms).
	 */
	public static function render_list( string $base_url ): string {
		self::enqueue_assets();
		$owner     = Owner::current();
		$share     = self::share_token_from_request();
		$read_only = '' !== $share;
		$list_id   = 0;
		if ( $read_only ) {
			$list_id = Store::find_by_share( $share );
		} else {
			$list_id = Store::default_list( $owner );
			$list_id = is_wp_error( $list_id ) ? 0 : (int) $list_id;
			/**
			 * The list the wishlist page shows to its owner (the Pro kit switches between lists).
			 *
			 * @param int   $list_id List id (0 when the visitor has none yet).
			 * @param Owner $owner   Owner.
			 */
			$list_id = (int) apply_filters( 'wootoolkit_wishlist_current_list', $list_id, $owner );
			if ( $list_id && ! Store::owns( $owner, $list_id ) ) {
				$list_id = 0;
			}
		}
		$is_owner = $list_id > 0 && Store::owns( $owner, $list_id );
		$nonce    = wp_create_nonce( self::NONCE );
		$args     = array(
			'list'      => $list_id ? Store::get( $list_id ) : null,
			'list_id'   => $list_id,
			'rows'      => $list_id ? self::rows( $list_id, $is_owner, $base_url, $nonce ) : array(),
			'read_only' => $read_only && ! $is_owner,
			'shared'    => $read_only,
			'is_owner'  => $is_owner,
			'owner'     => $owner,
			'share_url' => $is_owner ? Store::share_url( $list_id ) : '',
			'action'    => $base_url,
			'nonce'     => $nonce,
			'shop_url'  => wc_get_page_permalink( 'shop' ),
			'login_url' => ! is_user_logged_in() && $is_owner ? wc_get_page_permalink( 'myaccount' ) : '',
		);
		/**
		 * The data passed to the wishlist template (wishlist/wishlist.php).
		 *
		 * @param array<string, mixed> $args Template arguments.
		 */
		$args                  = (array) apply_filters( 'wootoolkit_wishlist_template_data', $args );
		$args['template_args'] = $args;

		ob_start();
		if ( function_exists( 'wc_print_notices' ) && ! wp_doing_ajax() ) {
			wc_print_notices();
		}
		wc_get_template( 'wishlist/wishlist.php', $args, 'wootoolkit/', WOOTOOLKIT_PATH . 'templates/' );
		return (string) ob_get_clean();
	}

	/**
	 * Table rows for a list. Items whose product was deleted are skipped (and dropped from the
	 * owner's list).
	 *
	 * @param int    $list_id  List id.
	 * @param bool   $is_owner The visitor owns the list (remove links, add-to-cart removal).
	 * @param string $base_url Page URL.
	 * @param string $nonce    Nonce.
	 * @return array<int, array<string, mixed>>
	 */
	public static function rows( int $list_id, bool $is_owner, string $base_url, string $nonce ): array {
		$rows = array();
		foreach ( Store::items( $list_id ) as $key => $item ) {
			$product = wc_get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );
			if ( ! $product instanceof \WC_Product || 'publish' !== get_post_status( $item['product_id'] ) ) {
				if ( $is_owner && ! $product ) {
					Store::remove_item( $list_id, $key );
				}
				continue;
			}
			$direct  = $product->is_purchasable() && $product->is_in_stock() && ( $product->is_type( 'variation' ) || $product->supports( 'ajax_add_to_cart' ) );
			$cart    = $product->add_to_cart_url();
			if ( $direct && $is_owner ) {
				$cart = add_query_arg(
					array(
						self::LIST_PARAM => $list_id,
						'_wtk_wl'        => $nonce,
					),
					$cart
				);
			}
			$current = (float) $product->get_price();
			$was     = '' !== $item['price'] ? (float) $item['price'] : $current;
			$avail   = $product->get_availability();
			$row     = array(
				'key'         => $key,
				'item'        => $item,
				'product'     => $product,
				'name'        => $product->get_name(),
				'permalink'   => $product->get_permalink(),
				'image'       => $product->get_image( 'woocommerce_thumbnail' ),
				'price_html'  => $product->get_price_html(),
				'price_added' => $was > $current && $current > 0 ? wc_price( wc_get_price_to_display( $product, array( 'price' => $was ) ) ) : '',
				'in_stock'    => $product->is_in_stock(),
				'stock_text'  => ! empty( $avail['availability'] ) ? (string) $avail['availability'] : ( $product->is_in_stock() ? __( 'In stock', 'wootoolkit' ) : __( 'Out of stock', 'wootoolkit' ) ),
				'added'       => $item['added'] ? date_i18n( (string) get_option( 'date_format' ), $item['added'] ) : '',
				'cart_url'    => $cart,
				'cart_text'   => $direct ? __( 'Add to cart', 'wootoolkit' ) : $product->add_to_cart_text(),
				'cart_direct' => $direct,
				'remove_url'  => $is_owner ? add_query_arg(
					array(
						'wootoolkit_wishlist_action' => 'remove',
						'item'                       => $key,
						'list'                       => $list_id,
						'_wpnonce'                   => $nonce,
					),
					$base_url
				) : '',
			);
			/**
			 * One row of the wishlist table.
			 *
			 * @param array<string, mixed> $row     Row data.
			 * @param int                  $list_id List id.
			 */
			$rows[] = (array) apply_filters( 'wootoolkit_wishlist_row_data', $row, $list_id );
		}
		return $rows;
	}

	/**
	 * Handles the no-JS add link and the remove links. Each one redirects, so a reload does not repeat it.
	 */
	public static function handle_request(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- checked just below.
		$adding = isset( $_GET[ self::ADD_PARAM ] ) ? absint( $_GET[ self::ADD_PARAM ] ) : 0;
		$action = isset( $_GET['wootoolkit_wishlist_action'] ) ? sanitize_key( wp_unslash( $_GET['wootoolkit_wishlist_action'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! $adding && 'remove' !== $action ) {
			return;
		}
		$referer = wp_get_referer();
		$valid   = isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), self::NONCE );

		if ( $adding ) {
			$back = $referer ? $referer : (string) get_permalink( $adding );
			$back = remove_query_arg( array( self::ADD_PARAM, '_wpnonce' ), $back ? $back : home_url( '/' ) );
			if ( ! $valid ) {
				wc_add_notice( __( 'Your session has expired. Please try again.', 'wootoolkit' ), 'error' );
				self::redirect( $back );
				return;
			}
			$result = Rest::add_for_current( $adding );
			if ( is_wp_error( $result ) ) {
				wc_add_notice( $result->get_error_message(), 'error' );
				if ( 'wootoolkit_wishlist_login_required' === $result->get_error_code() ) {
					$back = wc_get_page_permalink( 'myaccount' );
				}
			} else {
				$product = wc_get_product( $adding );
				/* translators: %s: product name */
				$message = sprintf( __( '&ldquo;%s&rdquo; has been added to your wishlist.', 'wootoolkit' ), $product ? esc_html( $product->get_name() ) : '' );
				if ( Settings::page_url() ) {
					$message = sprintf( '<a href="%s" tabindex="1" class="button wc-forward">%s</a> %s', esc_url( Settings::page_url() ), esc_html__( 'View wishlist', 'wootoolkit' ), $message );
				}
				wc_add_notice( $message );
			}
			self::redirect( $back );
			return;
		}

		$back = remove_query_arg( array( 'wootoolkit_wishlist_action', 'item', 'list', '_wpnonce' ), $referer ? $referer : ( Settings::page_url() ? Settings::page_url() : home_url( '/' ) ) );
		if ( ! $valid ) {
			wc_add_notice( __( 'Your session has expired. Please try again.', 'wootoolkit' ), 'error' );
			self::redirect( $back );
			return;
		}
		$list_id = isset( $_GET['list'] ) ? absint( $_GET['list'] ) : 0;
		$key     = isset( $_GET['item'] ) ? sanitize_key( wp_unslash( $_GET['item'] ) ) : '';
		if ( $list_id && Store::owns( Owner::current(), $list_id ) && Store::remove_item( $list_id, $key ) ) {
			wc_add_notice( __( 'Product removed from your wishlist.', 'wootoolkit' ) );
		}
		self::redirect( $back );
	}

	/**
	 * "Remove after add to cart": drops the product from the list it was added from on the wishlist page.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id    Product id.
	 * @param int    $quantity      Quantity.
	 * @param int    $variation_id  Variation id.
	 */
	public static function remove_after_add_to_cart( $cart_item_key, $product_id = 0, $quantity = 0, $variation_id = 0 ): void {
		unset( $cart_item_key, $quantity );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- checked just below.
		if ( ! Settings::remove_on_add() || empty( $_REQUEST[ self::LIST_PARAM ] ) || empty( $_REQUEST['_wtk_wl'] ) ) {
			return;
		}
		$list_id = absint( $_REQUEST[ self::LIST_PARAM ] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['_wtk_wl'] ) ), self::NONCE ) || ! Store::owns( Owner::current(), $list_id ) ) {
			return;
		}
		Store::remove_item( $list_id, Store::key( (int) $product_id, (int) $variation_id ) );
	}

	/**
	 * Merges the guest list into the account when a guest logs in.
	 *
	 * @param string        $user_login Login.
	 * @param \WP_User|null $user       User.
	 */
	public static function merge_on_login( $user_login, $user = null ): void {
		unset( $user_login );
		if ( $user instanceof \WP_User ) {
			self::merge_guest_into( (int) $user->ID );
		}
	}

	/**
	 * Same, when an account is logged in without the login form (registration at checkout or in My Account).
	 *
	 * @param string $cookie     Cookie.
	 * @param int    $expire     Expiry.
	 * @param int    $expiration Expiration.
	 * @param int    $user_id    User id.
	 */
	public static function merge_on_auth_cookie( $cookie, $expire = 0, $expiration = 0, $user_id = 0 ): void {
		unset( $cookie, $expire, $expiration );
		self::merge_guest_into( (int) $user_id );
	}

	/**
	 * Moves this browser's guest list into a customer's list and forgets the guest token.
	 *
	 * @param int $user_id Customer.
	 */
	public static function merge_guest_into( int $user_id ): void {
		$token = Guest::token();
		if ( $user_id <= 0 || '' === $token ) {
			return;
		}
		Store::merge( Owner::guest( $token ), $user_id );
		Guest::forget();
	}

	/**
	 * Redirects and stops (filterable for tests).
	 *
	 * @param string $url Target.
	 */
	private static function redirect( string $url ): void {
		/**
		 * Whether to redirect after a wishlist request (false in tests).
		 *
		 * @param bool   $redirect Redirect.
		 * @param string $url      Target.
		 */
		if ( ! apply_filters( 'wootoolkit_wishlist_redirect', true, $url ) ) {
			return;
		}
		wp_safe_redirect( $url );
		exit;
	}
}
