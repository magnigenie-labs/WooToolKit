<?php
/**
 * The shopper's quote list, kept in the WooCommerce session.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\RequestAQuote;

defined( 'ABSPATH' ) || exit;

/**
 * Items the shopper wants a quote for: key => [product_id, variation_id, variation, quantity].
 * Unlike the cart, it accepts products that cannot be bought (catalog mode), since that is its job.
 */
final class QuoteList {

	const SESSION_KEY = 'wootoolkit_quote';

	/**
	 * Items in the list.
	 *
	 * @return array<string, array{product_id: int, variation_id: int, variation: array<string, string>, quantity: int}>
	 */
	public static function items(): array {
		if ( ! self::session() ) {
			return array();
		}
		$items = WC()->session->get( self::SESSION_KEY, array() );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Number of units in the list.
	 */
	public static function count(): int {
		return (int) array_sum( wp_list_pluck( self::items(), 'quantity' ) );
	}

	/**
	 * Adds a product (or a variation of a variable product).
	 *
	 * @param int                   $product_id   Product id (the parent for variations).
	 * @param int                   $quantity     Quantity.
	 * @param int                   $variation_id Variation id, 0 for none.
	 * @param array<string, string> $variation    Variation attributes (attribute_* => value).
	 * @return string|\WP_Error Item key, or why it could not be added.
	 */
	public static function add( int $product_id, int $quantity = 1, int $variation_id = 0, array $variation = array() ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== $product->get_status() || ! $product->is_visible() || ! in_array( $product->get_type(), self::supported_types(), true ) ) {
			return new \WP_Error( 'wootoolkit_quote_invalid_product', __( 'This product cannot be added to a quote.', 'wootoolkit' ) );
		}

		if ( $product->is_type( 'variable' ) ) {
			if ( ! $variation_id ) {
				$variation_id = self::find_variation( $product, $variation );
			}
			$child = $variation_id ? wc_get_product( $variation_id ) : null;
			if ( ! $child instanceof \WC_Product_Variation || $child->get_parent_id() !== $product_id ) {
				return new \WP_Error( 'wootoolkit_quote_choose_options', __( 'Please choose product options before adding this product to your quote.', 'wootoolkit' ) );
			}
			$variation = self::complete_variation( $child, $variation );
		} else {
			$variation_id = 0;
			$variation    = array();
		}

		if ( ! self::session( true ) ) {
			return new \WP_Error( 'wootoolkit_quote_no_session', __( 'Your quote could not be saved. Please try again.', 'wootoolkit' ) );
		}

		$quantity = max( 1, $quantity );
		$key      = md5( $product_id . '|' . $variation_id . '|' . wp_json_encode( $variation ) );
		$items    = self::items();
		if ( isset( $items[ $key ] ) ) {
			$items[ $key ]['quantity'] += $quantity;
		} else {
			$items[ $key ] = array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'variation'    => $variation,
				'quantity'     => $quantity,
			);
		}
		self::save( $items );
		return $key;
	}

	/**
	 * Sets an item's quantity; 0 or less removes it.
	 *
	 * @param string $key      Item key.
	 * @param int    $quantity New quantity.
	 */
	public static function set_quantity( string $key, int $quantity ): void {
		$items = self::items();
		if ( ! isset( $items[ $key ] ) ) {
			return;
		}
		if ( $quantity <= 0 ) {
			unset( $items[ $key ] );
		} else {
			$items[ $key ]['quantity'] = $quantity;
		}
		self::save( $items );
	}

	/**
	 * Removes an item.
	 *
	 * @param string $key Item key.
	 */
	public static function remove( string $key ): void {
		self::set_quantity( $key, 0 );
	}

	/**
	 * Empties the list.
	 */
	public static function clear(): void {
		if ( self::session() ) {
			WC()->session->set( self::SESSION_KEY, array() );
		}
	}

	/**
	 * Product types that can be quoted.
	 *
	 * @return string[]
	 */
	public static function supported_types(): array {
		/**
		 * Product types that can be added to a quote.
		 *
		 * @param string[] $types Product types.
		 */
		return (array) apply_filters( 'wootoolkit_quote_product_types', array( 'simple', 'variable' ) );
	}

	/**
	 * The product (or variation) of a list item, null when it no longer exists.
	 *
	 * @param array{product_id: int, variation_id: int} $item List item.
	 */
	public static function product( array $item ): ?\WC_Product {
		$product = wc_get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );
		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Finds the variation matching submitted attributes.
	 *
	 * @param \WC_Product          $product   Variable product.
	 * @param array<string, string> $variation Attributes (attribute_* => value).
	 */
	private static function find_variation( \WC_Product $product, array $variation ): int {
		if ( ! $variation ) {
			return 0;
		}
		/**
		 * Product data store.
		 *
		 * @var \WC_Product_Data_Store_CPT $data_store
		 */
		$data_store = \WC_Data_Store::load( 'product' );
		return (int) $data_store->find_matching_product_variation( $product, $variation );
	}

	/**
	 * The variation's attributes, with "any" attributes taken from the submission.
	 *
	 * @param \WC_Product_Variation $variation Variation.
	 * @param array<string, string> $submitted Submitted attributes.
	 * @return array<string, string>
	 */
	private static function complete_variation( \WC_Product_Variation $variation, array $submitted ): array {
		$attributes = array();
		foreach ( $variation->get_variation_attributes() as $name => $value ) {
			$attributes[ $name ] = '' === $value && isset( $submitted[ $name ] ) ? wc_clean( $submitted[ $name ] ) : (string) $value;
		}
		return $attributes;
	}

	/**
	 * Stores the list.
	 *
	 * @param array<string, mixed> $items Items.
	 */
	private static function save( array $items ): void {
		WC()->session->set( self::SESSION_KEY, $items );
	}

	/**
	 * Whether a WooCommerce session is available.
	 *
	 * @param bool $start Start the session cookie for a guest (only when writing, so browsing stays cacheable).
	 */
	private static function session( bool $start = false ): bool {
		if ( ! function_exists( 'WC' ) || null === WC()->session ) {
			return false;
		}
		$session = WC()->session;
		if ( $start && $session instanceof \WC_Session_Handler && ! $session->has_session() && ! headers_sent() ) {
			$session->set_customer_session_cookie( true );
		}
		return true;
	}
}
