<?php
/**
 * "Catalog mode" checkbox on products.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\CatalogMode;

defined( 'ABSPATH' ) || exit;

/**
 * Lets the owner choose products one by one (used when the scope is "chosen products").
 */
final class ProductField {

	/**
	 * Hooks the field.
	 */
	public static function hooks(): void {
		add_action( 'woocommerce_product_options_advanced', array( self::class, 'field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save' ) );
	}

	/**
	 * Advanced tab checkbox (the Advanced tab exists for every product type).
	 */
	public static function field(): void {
		woocommerce_wp_checkbox(
			array(
				'id'          => Rules::META,
				'label'       => __( 'Catalog mode', 'wootoolkit' ),
				'description' => __( 'Show this product in catalog mode when catalog mode is set to "Chosen products"', 'wootoolkit' ),
			)
		);
	}

	/**
	 * Saves the checkbox. WooCommerce has verified the product nonce and capability.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public static function save( $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this action.
		$product->update_meta_data( Rules::META, isset( $_POST[ Rules::META ] ) ? 'yes' : 'no' );
	}
}
