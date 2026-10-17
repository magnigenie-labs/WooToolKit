<?php
/**
 * Catalog mode settings and the "does it apply to this product" decision.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\CatalogMode;

use WooToolkit\Kits\WholesalePrices\Customer;

defined( 'ABSPATH' ) || exit;

/**
 * Who catalog mode applies to (audience), which products (scope), and what it does
 * (hide the price, block purchases, show a replacement button).
 */
final class Rules {

	const OPT_AUDIENCE       = 'wootoolkit_catalog_audience';
	const OPT_SCOPE          = 'wootoolkit_catalog_scope';
	const OPT_CATEGORIES     = 'wootoolkit_catalog_categories';
	const OPT_HIDE_PRICE     = 'wootoolkit_catalog_hide_price';
	const OPT_PRICE_TEXT     = 'wootoolkit_catalog_price_text';
	const OPT_BLOCK_PURCHASE = 'wootoolkit_catalog_block_purchase';
	const OPT_BUTTON         = 'wootoolkit_catalog_button';
	const OPT_BUTTON_TEXT    = 'wootoolkit_catalog_button_text';
	const OPT_BUTTON_URL     = 'wootoolkit_catalog_button_url';

	/**
	 * Product meta ('yes') that puts a product in catalog mode when the scope is "chosen products".
	 */
	const META = '_wootoolkit_catalog';

	/**
	 * Per-request cache: user id => product id => applies.
	 *
	 * @var array<int, array<int, bool>>
	 */
	private static $cache = array();

	/**
	 * Audience choices.
	 *
	 * @return array<string, string>
	 */
	public static function audiences(): array {
		return array(
			'all'       => __( 'Everyone', 'wootoolkit' ),
			'guests'    => __( 'Guests (visitors who are not logged in)', 'wootoolkit' ),
			'retail'    => __( 'Everyone except wholesale customers', 'wootoolkit' ),
			'logged_in' => __( 'Logged-in customers only', 'wootoolkit' ),
		);
	}

	/**
	 * Scope choices.
	 *
	 * @return array<string, string>
	 */
	public static function scopes(): array {
		return array(
			'all'        => __( 'All products', 'wootoolkit' ),
			'categories' => __( 'Products in the categories below', 'wootoolkit' ),
			'products'   => __( 'Chosen products (tick "Catalog mode" on the product\'s Advanced tab)', 'wootoolkit' ),
		);
	}

	/**
	 * Replacement button choices.
	 *
	 * @return array<string, string>
	 */
	public static function buttons(): array {
		/**
		 * Replacement button types, value => label. The Request a quote kit adds its own.
		 *
		 * @param array<string, string> $buttons Button types.
		 */
		return (array) apply_filters(
			'wootoolkit_catalog_button_types',
			array(
				'none'    => __( 'No button', 'wootoolkit' ),
				'product' => __( 'Link to the product page (in shop and category lists)', 'wootoolkit' ),
				'custom'  => __( 'Custom link', 'wootoolkit' ),
			)
		);
	}

	/**
	 * An option constrained to a list of choices.
	 *
	 * @param string               $option  Option name.
	 * @param array<string, mixed> $choices Allowed values as keys; the first is the default.
	 */
	private static function choice( string $option, array $choices ): string {
		$value = get_option( $option, '' );
		return is_string( $value ) && array_key_exists( $value, $choices ) ? $value : (string) array_key_first( $choices );
	}

	/**
	 * Whether catalog mode applies to a product for the current visitor.
	 *
	 * @param \WC_Product $product Product or variation.
	 */
	public static function applies_to( \WC_Product $product ): bool {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false; // wp-admin always shows and sells everything.
		}

		$user_id    = get_current_user_id();
		$product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		if ( ! isset( self::$cache[ $user_id ][ $product_id ] ) ) {
			$applies = self::audience_matches() && self::in_scope( $product_id );

			/**
			 * Whether catalog mode applies to a product for the current visitor.
			 *
			 * @param bool $applies    Result of the audience and scope settings.
			 * @param int  $product_id Product id (the parent, for variations).
			 */
			self::$cache[ $user_id ][ $product_id ] = (bool) apply_filters( 'wootoolkit_catalog_applies', $applies, $product_id );
		}
		return self::$cache[ $user_id ][ $product_id ];
	}

	/**
	 * Whether the product's price is hidden for the current visitor.
	 *
	 * @param \WC_Product $product Product or variation.
	 */
	public static function hides_price( \WC_Product $product ): bool {
		return 'yes' === get_option( self::OPT_HIDE_PRICE, 'yes' ) && self::applies_to( $product );
	}

	/**
	 * Whether the current visitor is prevented from buying the product.
	 *
	 * @param \WC_Product $product Product or variation.
	 */
	public static function blocks_purchase( \WC_Product $product ): bool {
		return 'yes' === get_option( self::OPT_BLOCK_PURCHASE, 'yes' ) && self::applies_to( $product );
	}

	/**
	 * Text shown in place of a hidden price.
	 */
	public static function price_text(): string {
		return (string) get_option( self::OPT_PRICE_TEXT, '' );
	}

	/**
	 * The replacement button type.
	 */
	public static function button(): string {
		return self::choice( self::OPT_BUTTON, self::buttons() );
	}

	/**
	 * Whether the current visitor is in the configured audience.
	 */
	private static function audience_matches(): bool {
		switch ( self::choice( self::OPT_AUDIENCE, self::audiences() ) ) {
			case 'guests':
				return ! is_user_logged_in();
			case 'retail':
				return ! Customer::is_wholesale();
			case 'logged_in':
				return is_user_logged_in();
			default:
				return true;
		}
	}

	/**
	 * Whether a product is in the configured scope.
	 *
	 * @param int $product_id Product id (never a variation).
	 */
	private static function in_scope( int $product_id ): bool {
		switch ( self::choice( self::OPT_SCOPE, self::scopes() ) ) {
			case 'categories':
				$chosen = array_map( 'intval', (array) get_option( self::OPT_CATEGORIES, array() ) );
				if ( ! $chosen ) {
					return false;
				}
				// A chosen category covers its subcategories.
				foreach ( wc_get_product_term_ids( $product_id, 'product_cat' ) as $term_id ) {
					$lineage = array_merge( array( (int) $term_id ), array_map( 'intval', get_ancestors( (int) $term_id, 'product_cat', 'taxonomy' ) ) );
					if ( array_intersect( $lineage, $chosen ) ) {
						return true;
					}
				}
				return false;
			case 'products':
				return 'yes' === get_post_meta( $product_id, self::META, true );
			default:
				return true;
		}
	}

	/**
	 * Forgets cached decisions (after settings or role changes, and between tests).
	 */
	public static function flush_cache(): void {
		self::$cache = array();
	}
}
