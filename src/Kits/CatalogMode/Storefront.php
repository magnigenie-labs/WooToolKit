<?php
/**
 * Catalog mode on the storefront.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\CatalogMode;

defined( 'ABSPATH' ) || exit;

/**
 * Makes catalog products unpurchasable through `woocommerce_is_purchasable`, so WooCommerce itself
 * refuses them everywhere (add-to-cart links, the Store API, carts restored from a session), then
 * tidies the display: prices, buttons, structured data and Store API product prices.
 */
final class Storefront {

	/**
	 * Add-to-cart blocks on the single product page, replaced as a whole.
	 */
	const ADD_TO_CART_BLOCKS = array( 'woocommerce/add-to-cart-form', 'woocommerce/add-to-cart-with-options' );

	/**
	 * Hooked filters and actions: hook => [method, priority, accepted args].
	 *
	 * @return array<string, array{0: string, 1: int, 2: int}>
	 */
	public static function filters(): array {
		$filters = array(
			'woocommerce_is_purchasable'           => array( 'filter_purchasable', 20, 2 ),
			'woocommerce_variation_is_purchasable' => array( 'filter_purchasable', 20, 2 ),
			'woocommerce_get_price_html'           => array( 'filter_price_html', 20, 2 ),
			'woocommerce_loop_add_to_cart_link'    => array( 'filter_loop_button', 20, 2 ),
			'woocommerce_structured_data_product'  => array( 'filter_structured_data', 20, 2 ),
			'woocommerce_single_product_summary'   => array( 'single_product_button', 29, 0 ),
			'rest_request_after_callbacks'         => array( 'filter_store_api_products', 20, 3 ),
		);
		foreach ( self::ADD_TO_CART_BLOCKS as $block ) {
			$filters[ 'render_block_' . $block ] = array( 'filter_add_to_cart_block', 20, 3 );
		}
		return $filters;
	}

	/**
	 * Hooks the storefront.
	 */
	public static function hooks(): void {
		foreach ( self::filters() as $hook => $args ) {
			add_filter( $hook, array( self::class, $args[0] ), $args[1], $args[2] );
		}
	}

	/**
	 * Catalog products cannot be bought.
	 *
	 * @param bool        $purchasable Whether WooCommerce would sell it.
	 * @param \WC_Product $product     Product or variation.
	 */
	public static function filter_purchasable( $purchasable, $product ) {
		if ( $purchasable && $product instanceof \WC_Product && Rules::blocks_purchase( $product ) ) {
			return false;
		}
		return $purchasable;
	}

	/**
	 * Hidden prices become the configured text (or nothing).
	 *
	 * @param string      $html    Price HTML.
	 * @param \WC_Product $product Product.
	 */
	public static function filter_price_html( $html, $product ) {
		if ( ! $product instanceof \WC_Product || ! Rules::hides_price( $product ) ) {
			return $html;
		}
		$text = Rules::price_text();
		return '' === $text ? '' : '<span class="wootoolkit-catalog-price">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Shop and category lists (classic templates and the Product Button block): the replacement button.
	 *
	 * @param string      $html    Add-to-cart link.
	 * @param \WC_Product $product Product.
	 */
	public static function filter_loop_button( $html, $product ) {
		if ( ! $product instanceof \WC_Product || ! Rules::blocks_purchase( $product ) ) {
			return $html;
		}
		return self::button_html( $product, 'loop' );
	}

	/**
	 * Classic single product page: swaps the add-to-cart form for the replacement button.
	 * Runs just before WooCommerce's form (priority 30) and puts it back afterwards, so other
	 * products rendered later in the same request are unaffected.
	 */
	public static function single_product_button(): void {
		global $product;
		if ( ! $product instanceof \WC_Product || ! Rules::blocks_purchase( $product ) ) {
			return;
		}
		if ( false === remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 ) ) {
			return;
		}
		$restore = static function () use ( &$restore ) {
			add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
			remove_action( 'woocommerce_single_product_summary', $restore, 31 );
		};
		add_action( 'woocommerce_single_product_summary', $restore, 31 );
		echo self::button_html( $product, 'single' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in button_html().
	}

	/**
	 * Block themes: the add-to-cart blocks become the replacement button.
	 *
	 * @param string         $content Block HTML.
	 * @param array          $block   Parsed block (unused).
	 * @param \WP_Block|null $instance Block instance.
	 */
	public static function filter_add_to_cart_block( $content, $block, $instance = null ) {
		$product_id = $instance instanceof \WP_Block && isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : ( $GLOBALS['product'] ?? null );
		if ( ! $product instanceof \WC_Product || ! Rules::blocks_purchase( $product ) ) {
			return $content;
		}
		return self::button_html( $product, 'single' );
	}

	/**
	 * Search engines are not given a hidden price.
	 *
	 * @param array       $markup  Product schema.
	 * @param \WC_Product $product Product.
	 */
	public static function filter_structured_data( $markup, $product ) {
		if ( is_array( $markup ) && $product instanceof \WC_Product && Rules::hides_price( $product ) ) {
			unset( $markup['offers'] );
		}
		return $markup;
	}

	/**
	 * Store API product responses (used by the product blocks) do not reveal hidden prices.
	 *
	 * Runs inside dispatch, so internal requests (block preloading, rest_do_request) are covered too.
	 *
	 * @param mixed            $response Response.
	 * @param array            $handler  Route handler (unused).
	 * @param \WP_REST_Request $request  Request.
	 */
	public static function filter_store_api_products( $response, $handler, $request ) {
		if ( ! $response instanceof \WP_REST_Response || ! $request instanceof \WP_REST_Request
			|| ! preg_match( '#^/wc/store(/v\d+)?/products(/\d+)?$#', $request->get_route() ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return $response;
		}
		if ( isset( $data['id'] ) ) {
			$response->set_data( self::redact_product( $data ) );
		} else {
			$response->set_data( array_map( array( self::class, 'redact_product' ), $data ) );
		}
		return $response;
	}

	/**
	 * Blanks the prices of one Store API product when they are hidden.
	 *
	 * @param mixed $item Store API product.
	 * @return mixed
	 */
	private static function redact_product( $item ) {
		if ( ! is_array( $item ) || empty( $item['id'] ) ) {
			return $item;
		}
		$product = wc_get_product( (int) $item['id'] );
		if ( ! $product || ! Rules::hides_price( $product ) ) {
			return $item;
		}
		if ( isset( $item['prices'] ) && ( is_array( $item['prices'] ) || is_object( $item['prices'] ) ) ) {
			$prices = (array) $item['prices'];
			foreach ( array( 'price', 'regular_price', 'sale_price' ) as $key ) {
				$prices[ $key ] = '';
			}
			$prices['price_range'] = null;
			$item['prices']        = is_object( $item['prices'] ) ? (object) $prices : $prices;
		}
		$item['price_html'] = self::filter_price_html( '', $product );
		$item['on_sale']    = false;
		return $item;
	}

	/**
	 * The replacement button, or nothing.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $context 'loop' (shop and category lists) or 'single' (product page).
	 */
	private static function button_html( \WC_Product $product, string $context ): string {
		$type = Rules::button();
		$text = (string) get_option( Rules::OPT_BUTTON_TEXT, '' );
		$url  = '';

		if ( 'product' === $type && 'loop' === $context ) {
			$url  = $product->get_permalink();
			$text = '' === $text ? __( 'View product', 'wootoolkit' ) : $text;
		} elseif ( 'custom' === $type ) {
			$url  = (string) get_option( Rules::OPT_BUTTON_URL, '' );
			$text = '' === $text ? __( 'Contact us', 'wootoolkit' ) : $text;
		}

		$html = '';
		if ( '' !== $url ) {
			$class = 'button wootoolkit-catalog-button' . ( 'single' === $context ? ' alt' : '' );
			if ( function_exists( 'wp_theme_get_element_class_name' ) ) {
				$class .= ' ' . wp_theme_get_element_class_name( 'button' );
			}
			$html = sprintf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( trim( $class ) ), esc_html( $text ) );
			if ( 'single' === $context ) {
				$html = '<div class="wootoolkit-catalog-actions">' . $html . '</div>';
			}
		}

		/**
		 * The button shown in place of Add to cart in catalog mode.
		 *
		 * @param string      $html    Button HTML (empty for none).
		 * @param \WC_Product $product Product.
		 * @param string      $context 'loop' or 'single'.
		 * @param string      $type    Button type setting.
		 */
		return (string) apply_filters( 'wootoolkit_catalog_button_html', $html, $product, $context, $type );
	}
}
