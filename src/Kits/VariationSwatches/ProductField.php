<?php
/**
 * Per-product setting for custom attributes.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\VariationSwatches;

defined( 'ABSPATH' ) || exit;

/**
 * Custom product attributes (typed on the product, not global ones) have no attribute screen, so
 * each variable product chooses whether they show as buttons or as the dropdown.
 */
final class ProductField {

	/**
	 * Hooks the field.
	 */
	public static function hooks(): void {
		add_action( 'woocommerce_product_options_attributes', array( self::class, 'field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save' ) );
	}

	/**
	 * Choices.
	 *
	 * @return array<string, string>
	 */
	public static function choices(): array {
		$types = Swatches::types();
		return array(
			/* translators: %s: the store-wide default, e.g. "Dropdown (WooCommerce default)" */
			''       => sprintf( __( 'Store default (%s)', 'wootoolkit' ), $types[ Swatches::default_type() ] ?? '' ),
			'select' => $types['select'],
			'label'  => $types['label'] ?? __( 'Buttons (text)', 'wootoolkit' ),
		);
	}

	/**
	 * Select in the Attributes tab (shown for variable products).
	 */
	public static function field(): void {
		echo '<div class="options_group show_if_variable">';
		woocommerce_wp_select(
			array(
				'id'          => Swatches::PRODUCT_META,
				'label'       => __( 'Custom attributes shown as', 'wootoolkit' ),
				'options'     => self::choices(),
				'desc_tip'    => true,
				'description' => __( 'How this product\'s custom attributes are chosen on the product page. Global attributes use the swatch type set under Products > Attributes.', 'wootoolkit' ),
			)
		);
		echo '</div>';
	}

	/**
	 * Saves the select. WooCommerce has verified the product nonce and capability.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public static function save( $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this action.
		$value = isset( $_POST[ Swatches::PRODUCT_META ] ) ? sanitize_key( wp_unslash( $_POST[ Swatches::PRODUCT_META ] ) ) : '';
		if ( in_array( $value, array( 'select', 'label' ), true ) ) {
			$product->update_meta_data( Swatches::PRODUCT_META, $value );
		} else {
			$product->delete_meta_data( Swatches::PRODUCT_META );
		}
	}
}
