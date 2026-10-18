<?php
/**
 * Variation swatches on the product page.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\VariationSwatches;

use WooToolkit\Kit\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Puts the swatches next to WooCommerce's variation dropdowns through
 * woocommerce_dropdown_variation_attribute_options_html, which both the classic variations form and
 * the block theme's Add to Cart Form block render. The <select> stays (visually hidden once the
 * script runs) so WooCommerce's variation script still finds the variation, price, image and stock.
 */
final class Storefront {

	const HANDLE = 'wootoolkit-variation-swatches';

	/**
	 * Hooks the storefront.
	 */
	public static function hooks(): void {
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( self::class, 'filter_dropdown' ), 20, 2 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_on_product_pages' ) );
		add_action( 'woocommerce_variation_set_stock_status', array( Swatches::class, 'flush_cache' ) );
	}

	/**
	 * Replaces the dropdown with the dropdown plus its swatches.
	 *
	 * @param string $html Dropdown HTML.
	 * @param array  $args Dropdown arguments.
	 */
	public static function filter_dropdown( $html, $args = array() ) {
		if ( ! Support::is_storefront() || ! is_array( $args ) || ! is_string( $html ) || false === strpos( $html, '<select' ) ) {
			return $html;
		}
		$swatches = Swatches::render_group( $args );
		if ( '' === $swatches ) {
			return $html;
		}
		self::enqueue_assets();
		$out = '<div class="wootoolkit-swatches-wrap">' . self::mark_select( $html ) . $swatches . '</div>';

		/**
		 * The dropdown with its swatches.
		 *
		 * @param string $out  Wrapper with WooCommerce's select and the swatch group.
		 * @param string $html WooCommerce's select.
		 * @param array  $args Dropdown arguments.
		 */
		return (string) apply_filters( 'wootoolkit_variation_swatches_html', $out, $html, $args );
	}

	/**
	 * Adds a class to WooCommerce's select, leaving its name, options and data attributes alone.
	 *
	 * @param string $html Select HTML.
	 */
	public static function mark_select( string $html ): string {
		if ( preg_match( '/<select\b[^>]*\bclass="/', $html ) ) {
			return (string) preg_replace( '/(<select\b[^>]*\bclass=")/', '$1wootoolkit-swatches-select ', $html, 1 );
		}
		return (string) preg_replace( '/<select\b/', '<select class="wootoolkit-swatches-select"', $html, 1 );
	}

	/**
	 * Loads the assets early on variable product pages, so the swatches render without a flash of the dropdown.
	 */
	public static function enqueue_on_product_pages(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( $product && $product->is_type( 'variable' ) ) {
			self::enqueue_assets();
		}
	}

	/**
	 * Styles and script. Public so other kits (the Pro kit's shop swatches) can load them anywhere.
	 */
	public static function enqueue_assets(): void {
		if ( wp_script_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}
		wp_enqueue_style( self::HANDLE, plugins_url( 'assets/css/variation-swatches.css', WOOTOOLKIT_FILE ), array(), WOOTOOLKIT_VERSION );

		// Marks the page as having JavaScript before it paints, so the CSS hides the dropdown at once.
		// Without JavaScript the dropdown stays and the swatches stay hidden.
		if ( ! did_action( 'wp_head' ) ) {
			wp_register_script( self::HANDLE . '-head', false, array(), WOOTOOLKIT_VERSION, false );
			wp_enqueue_script( self::HANDLE . '-head' );
			wp_add_inline_script( self::HANDLE . '-head', 'document.documentElement.classList.add("wootoolkit-swatches-js");' );
		}

		wp_enqueue_script( self::HANDLE, plugins_url( 'assets/js/variation-swatches.js', WOOTOOLKIT_FILE ), array( 'jquery' ), WOOTOOLKIT_VERSION, true );
		wp_localize_script(
			self::HANDLE,
			'wootoolkitSwatches',
			array(
				/* translators: %s: option name, e.g. "Red" */
				'outOfStock'  => __( '%s (out of stock)', 'wootoolkit' ),
				/* translators: %s: option name, e.g. "Red" */
				'unavailable' => __( '%s (not available)', 'wootoolkit' ),
			)
		);

		/**
		 * Fires after the swatch styles and script are enqueued.
		 */
		do_action( 'wootoolkit_variation_swatches_enqueue_assets' );
	}
}
