<?php
/**
 * Wholesale price calculation and the WooCommerce price filters.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\WholesalePrices;

defined( 'ABSPATH' ) || exit;

/**
 * Applies wholesale prices through WooCommerce's product price getters, so the same price shows in
 * classic templates, blocks, the Store API, the cart and checkout.
 *
 * Precedence: the product's (or variation's) own wholesale price, then the highest wholesale discount
 * of its categories, then the store-wide discount. With "show retail price" on, the wholesale price
 * is presented as a sale price, so WooCommerce shows the retail price crossed out.
 */
final class Pricing {

	const META          = '_wootoolkit_wholesale_price';
	const TERM_META     = 'wootoolkit_wholesale_discount';
	const OPT_DISCOUNT  = 'wootoolkit_wholesale_discount';
	const OPT_SHOW_RRP  = 'wootoolkit_wholesale_show_rrp';

	/**
	 * Per-request cache: product id => wholesale price, or false when none applies.
	 *
	 * @var array<int, float|false>
	 */
	private static $cache = array();

	/**
	 * Hooks the price filters.
	 */
	public static function hooks(): void {
		foreach ( array( 'woocommerce_product_get_', 'woocommerce_product_variation_get_' ) as $prefix ) {
			add_filter( $prefix . 'price', array( self::class, 'filter_price' ), 20, 2 );
			add_filter( $prefix . 'sale_price', array( self::class, 'filter_sale_price' ), 20, 2 );
			add_filter( $prefix . 'regular_price', array( self::class, 'filter_regular_price' ), 20, 2 );
		}
		add_filter( 'woocommerce_product_is_on_sale', array( self::class, 'filter_is_on_sale' ), 20, 2 );
		add_filter( 'woocommerce_sale_flash', array( self::class, 'filter_sale_flash' ), 20, 3 );
		add_filter( 'render_block_woocommerce/product-sale-badge', array( self::class, 'filter_sale_badge_block' ), 20, 3 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- core block hook.

		// Variable products: price ranges are computed from these and cached per hash.
		add_filter( 'woocommerce_variation_prices_price', array( self::class, 'filter_variation_range_price' ), 20, 2 );
		add_filter( 'woocommerce_variation_prices_sale_price', array( self::class, 'filter_variation_range_sale_price' ), 20, 2 );
		add_filter( 'woocommerce_variation_prices_regular_price', array( self::class, 'filter_variation_range_regular_price' ), 20, 2 );
		add_filter( 'woocommerce_get_variation_prices_hash', array( self::class, 'filter_variation_prices_hash' ), 20 );
	}

	/**
	 * Whether prices should be changed in this request for the current user.
	 */
	public static function applies(): bool {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false; // wp-admin always shows and edits retail prices.
		}
		return Customer::is_wholesale();
	}

	/**
	 * The wholesale price for a product, or null when none applies (retail price stands).
	 *
	 * @param \WC_Product $product Product or variation.
	 */
	public static function wholesale_price( \WC_Product $product ): ?float {
		$id = $product->get_id();
		if ( ! isset( self::$cache[ $id ] ) ) {
			self::$cache[ $id ] = self::calculate( $product );
		}
		return false === self::$cache[ $id ] ? null : self::$cache[ $id ];
	}

	/**
	 * Calculates the wholesale price. Reads raw ("edit" context) values, so it never re-enters the filters.
	 *
	 * @param \WC_Product $product Product or variation.
	 * @return float|false
	 */
	private static function calculate( \WC_Product $product ) {
		$regular = $product->get_regular_price( 'edit' );
		if ( '' === $regular || ! is_numeric( $regular ) ) {
			return false;
		}
		$regular = (float) $regular;

		$own = $product->get_meta( self::META, true, 'edit' );
		if ( '' !== $own && is_numeric( $own ) && (float) $own >= 0 ) {
			$price = (float) $own;
		} else {
			$discount = self::discount_for( $product );
			if ( $discount <= 0 ) {
				return false;
			}
			$price = round( $regular * ( 1 - $discount / 100 ), wc_get_price_decimals() );
		}

		// Wholesale customers never pay more than retail: a retail sale at or below the wholesale price wins.
		$sale   = self::active_sale_price( $product );
		$retail = null === $sale ? $regular : $sale;
		return $price < $retail ? $price : false;
	}

	/**
	 * The product's own (retail) sale price while the sale is running, read without any filters.
	 *
	 * @param \WC_Product $product Product or variation.
	 */
	public static function active_sale_price( \WC_Product $product ): ?float {
		$sale    = $product->get_sale_price( 'edit' );
		$regular = $product->get_regular_price( 'edit' );
		if ( '' === $sale || ! is_numeric( $sale ) || ! is_numeric( $regular ) || (float) $sale >= (float) $regular ) {
			return null;
		}
		$now  = time();
		$from = $product->get_date_on_sale_from( 'edit' );
		$to   = $product->get_date_on_sale_to( 'edit' );
		if ( ( $from && $from->getTimestamp() > $now ) || ( $to && $to->getTimestamp() < $now ) ) {
			return null;
		}
		return (float) $sale;
	}

	/**
	 * Whether the "Sale!" badge should be hidden: the product looks on sale only because the retail
	 * price is shown crossed out next to the wholesale price, not because of a real sale.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function hides_sale_badge( \WC_Product $product ): bool {
		if ( ! self::applies() ) {
			return false;
		}
		$products = $product->is_type( 'variable' ) ? array_filter( array_map( 'wc_get_product', $product->get_children() ) ) : array( $product );
		$discount = false;
		foreach ( $products as $item ) {
			if ( null !== self::active_sale_price( $item ) ) {
				return false;
			}
			$discount = $discount || null !== self::wholesale_price( $item );
		}
		return $discount;
	}

	/**
	 * Classic sale flash.
	 *
	 * @param string           $html    Badge HTML.
	 * @param mixed            $post    Post (unused).
	 * @param \WC_Product|null $product Product.
	 */
	public static function filter_sale_flash( $html, $post = null, $product = null ) {
		return $product instanceof \WC_Product && self::hides_sale_badge( $product ) ? '' : $html;
	}

	/**
	 * Product Sale Badge block.
	 *
	 * @param string         $content  Block HTML.
	 * @param array          $block    Parsed block (unused).
	 * @param \WP_Block|null $instance Block instance.
	 */
	public static function filter_sale_badge_block( $content, $block, $instance = null ) {
		$product_id = $instance instanceof \WP_Block && isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : ( $GLOBALS['product'] ?? null );
		return $product instanceof \WC_Product && self::hides_sale_badge( $product ) ? '' : $content;
	}

	/**
	 * Discount percentage for a product: the highest of its categories', else the store-wide one.
	 *
	 * @param \WC_Product $product Product or variation.
	 */
	public static function discount_for( \WC_Product $product ): float {
		$product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$best       = 0.0;
		foreach ( wc_get_product_term_ids( $product_id, 'product_cat' ) as $term_id ) {
			$best = max( $best, self::sanitize_percent( get_term_meta( $term_id, self::TERM_META, true ) ) );
		}
		return $best > 0 ? $best : self::sanitize_percent( get_option( self::OPT_DISCOUNT, '' ) );
	}

	/**
	 * Clamps a percentage to 0-100; anything non-numeric is 0.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_percent( $value ): float {
		return is_numeric( $value ) ? max( 0.0, min( 100.0, (float) $value ) ) : 0.0;
	}

	/**
	 * Whether the retail price is shown crossed out next to the wholesale price.
	 */
	public static function show_rrp(): bool {
		return 'no' !== get_option( self::OPT_SHOW_RRP, 'yes' );
	}

	/**
	 * Forgets cached prices (after saving a product, and between tests).
	 */
	public static function flush_cache(): void {
		self::$cache = array();
	}

	/**
	 * Price getter.
	 *
	 * @param mixed       $price   Price.
	 * @param \WC_Product $product Product.
	 * @return mixed
	 */
	public static function filter_price( $price, $product ) {
		$wholesale = self::applies() && $product instanceof \WC_Product ? self::wholesale_price( $product ) : null;
		return null === $wholesale ? $price : wc_format_decimal( $wholesale );
	}

	/**
	 * Sale price getter: the wholesale price when the retail price is shown, otherwise none.
	 *
	 * @param mixed       $sale_price Sale price.
	 * @param \WC_Product $product    Product.
	 * @return mixed
	 */
	public static function filter_sale_price( $sale_price, $product ) {
		$wholesale = self::applies() && $product instanceof \WC_Product ? self::wholesale_price( $product ) : null;
		if ( null === $wholesale ) {
			return $sale_price;
		}
		return self::show_rrp() ? wc_format_decimal( $wholesale ) : '';
	}

	/**
	 * Regular price getter: unchanged when the retail price is shown, otherwise the wholesale price.
	 *
	 * @param mixed       $regular_price Regular price.
	 * @param \WC_Product $product       Product.
	 * @return mixed
	 */
	public static function filter_regular_price( $regular_price, $product ) {
		$wholesale = self::applies() && $product instanceof \WC_Product ? self::wholesale_price( $product ) : null;
		return ( null === $wholesale || self::show_rrp() ) ? $regular_price : wc_format_decimal( $wholesale );
	}

	/**
	 * On-sale flag: wholesale products count as on sale (ignoring sale dates) when the retail price is shown.
	 *
	 * @param bool        $on_sale Whether the product is on sale.
	 * @param \WC_Product $product Product.
	 */
	public static function filter_is_on_sale( $on_sale, $product ): bool {
		if ( ! self::applies() || ! $product instanceof \WC_Product || $product->is_type( 'variable' ) ) {
			return (bool) $on_sale;
		}
		$wholesale = self::wholesale_price( $product );
		if ( null === $wholesale ) {
			return (bool) $on_sale;
		}
		return self::show_rrp() && $wholesale < (float) $product->get_regular_price( 'edit' );
	}

	/**
	 * Variation price used for a variable product's range.
	 *
	 * @param mixed       $price     Price.
	 * @param \WC_Product $variation Variation.
	 * @return mixed
	 */
	public static function filter_variation_range_price( $price, $variation ) {
		return self::filter_price( $price, $variation );
	}

	/**
	 * Variation sale price used for a variable product's range.
	 *
	 * @param mixed       $sale_price Sale price.
	 * @param \WC_Product $variation  Variation.
	 * @return mixed
	 */
	public static function filter_variation_range_sale_price( $sale_price, $variation ) {
		return self::filter_sale_price( $sale_price, $variation );
	}

	/**
	 * Variation regular price used for a variable product's range.
	 *
	 * @param mixed       $regular_price Regular price.
	 * @param \WC_Product $variation     Variation.
	 * @return mixed
	 */
	public static function filter_variation_range_regular_price( $regular_price, $variation ) {
		return self::filter_regular_price( $regular_price, $variation );
	}

	/**
	 * Keeps wholesale and retail variation price ranges in separate caches.
	 *
	 * @param array<int|string, mixed> $hash Hash parts.
	 * @return array<int|string, mixed>
	 */
	public static function filter_variation_prices_hash( $hash ) {
		if ( self::applies() ) {
			$hash['wootoolkit_wholesale'] = array(
				'discount' => get_option( self::OPT_DISCOUNT, '' ),
				'rrp'      => self::show_rrp() ? 'yes' : 'no',
			);
		}
		return $hash;
	}
}
