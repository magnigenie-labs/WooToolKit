<?php
/**
 * Admin fields for wholesale prices, and order tagging.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\WholesalePrices;

defined( 'ABSPATH' ) || exit;

/**
 * Wholesale price on products and variations, wholesale discount on product categories,
 * and a "Wholesale order" marker on orders placed by wholesale customers.
 */
final class ProductFields {

	const ORDER_META = '_wootoolkit_wholesale_order';

	/**
	 * Hooks the fields.
	 */
	public static function hooks(): void {
		add_action( 'woocommerce_product_options_pricing', array( self::class, 'product_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save_product' ) );
		add_action( 'woocommerce_variation_options_pricing', array( self::class, 'variation_field' ), 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', array( self::class, 'save_variation' ), 10, 2 );

		add_action( 'product_cat_add_form_fields', array( self::class, 'category_add_field' ) );
		add_action( 'product_cat_edit_form_fields', array( self::class, 'category_edit_field' ) );
		add_action( 'created_product_cat', array( self::class, 'save_category' ) );
		add_action( 'edited_product_cat', array( self::class, 'save_category' ) );

		add_action( 'woocommerce_checkout_create_order', array( self::class, 'tag_order' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( self::class, 'tag_order' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( self::class, 'order_badge' ) );
	}

	/**
	 * Field label with the store currency.
	 */
	private static function label(): string {
		/* translators: %s: currency symbol */
		return sprintf( __( 'Wholesale price (%s)', 'wootoolkit' ), get_woocommerce_currency_symbol() );
	}

	/**
	 * Help text shared by product and variation fields.
	 */
	private static function help(): string {
		return __( 'Price for wholesale customers. Leave empty to use the category or store-wide wholesale discount.', 'wootoolkit' );
	}

	/**
	 * Simple product field (General tab, below the prices).
	 */
	public static function product_field(): void {
		woocommerce_wp_text_input(
			array(
				'id'          => Pricing::META,
				'label'       => self::label(),
				'data_type'   => 'price',
				'desc_tip'    => true,
				'description' => self::help(),
			)
		);
	}

	/**
	 * Saves the simple product field. WooCommerce has verified the product nonce and capability.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public static function save_product( $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this action.
		$raw = isset( $_POST[ Pricing::META ] ) ? sanitize_text_field( wp_unslash( $_POST[ Pricing::META ] ) ) : '';
		self::store( $product, $raw );
	}

	/**
	 * Variation field.
	 *
	 * @param int      $loop           Variation index.
	 * @param array    $variation_data Variation data (unused).
	 * @param \WP_Post $variation      Variation post.
	 */
	public static function variation_field( $loop, $variation_data, $variation ): void {
		$product = wc_get_product( $variation->ID );
		woocommerce_wp_text_input(
			array(
				'id'            => Pricing::META . '[' . (int) $loop . ']',
				'name'          => Pricing::META . '[' . (int) $loop . ']',
				'value'         => $product ? wc_format_localized_price( (string) $product->get_meta( Pricing::META, true, 'edit' ) ) : '',
				'label'         => self::label(),
				'data_type'     => 'price',
				'wrapper_class' => 'form-row form-row-full',
				'desc_tip'      => true,
				'description'   => self::help(),
			)
		);
	}

	/**
	 * Saves a variation's field. WooCommerce has verified the nonce and capability.
	 *
	 * @param \WC_Product_Variation $variation Variation being saved.
	 * @param int                   $index     Variation index in the request.
	 */
	public static function save_variation( $variation, $index ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this action.
		$values = isset( $_POST[ Pricing::META ] ) && is_array( $_POST[ Pricing::META ] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ Pricing::META ] ) ) : array();
		$raw    = isset( $values[ $index ] ) ? (string) $values[ $index ] : '';
		self::store( $variation, $raw );
	}

	/**
	 * Stores a wholesale price (empty clears it).
	 *
	 * @param \WC_Product $product Product or variation.
	 * @param string      $raw     Submitted value, possibly localized.
	 */
	private static function store( \WC_Product $product, string $raw ): void {
		$value = '' === $raw ? '' : wc_format_decimal( $raw );
		if ( '' === $value || ! is_numeric( $value ) || (float) $value < 0 ) {
			$product->delete_meta_data( Pricing::META );
		} else {
			$product->update_meta_data( Pricing::META, $value );
		}
		Pricing::flush_cache();
	}

	/**
	 * Category field on the "Add new category" form.
	 */
	public static function category_add_field(): void {
		?>
		<div class="form-field">
			<label for="<?php echo esc_attr( Pricing::TERM_META ); ?>"><?php esc_html_e( 'Wholesale discount (%)', 'wootoolkit' ); ?></label>
			<input type="number" min="0" max="100" step="0.01" name="<?php echo esc_attr( Pricing::TERM_META ); ?>" id="<?php echo esc_attr( Pricing::TERM_META ); ?>" value="" />
			<p class="description"><?php esc_html_e( 'Discount off the regular price for wholesale customers, for products in this category without their own wholesale price. Leave empty to use the store-wide discount.', 'wootoolkit' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Category field on the "Edit category" form.
	 *
	 * @param \WP_Term $term Category.
	 */
	public static function category_edit_field( $term ): void {
		$value = get_term_meta( $term->term_id, Pricing::TERM_META, true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="<?php echo esc_attr( Pricing::TERM_META ); ?>"><?php esc_html_e( 'Wholesale discount (%)', 'wootoolkit' ); ?></label></th>
			<td>
				<input type="number" min="0" max="100" step="0.01" name="<?php echo esc_attr( Pricing::TERM_META ); ?>" id="<?php echo esc_attr( Pricing::TERM_META ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" />
				<p class="description"><?php esc_html_e( 'Discount off the regular price for wholesale customers, for products in this category without their own wholesale price. Leave empty to use the store-wide discount.', 'wootoolkit' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Saves the category discount. WordPress has verified the term form nonce before these actions.
	 *
	 * @param int $term_id Category id.
	 */
	public static function save_category( $term_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WordPress before created_/edited_ term actions.
		if ( ! isset( $_POST[ Pricing::TERM_META ] ) || ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = sanitize_text_field( wp_unslash( $_POST[ Pricing::TERM_META ] ) );
		if ( '' === $raw ) {
			delete_term_meta( $term_id, Pricing::TERM_META );
		} else {
			update_term_meta( $term_id, Pricing::TERM_META, (string) Pricing::sanitize_percent( $raw ) );
		}
		// Cached variable product price ranges depend on category discounts.
		\WC_Cache_Helper::get_transient_version( 'product', true );
		Pricing::flush_cache();
	}

	/**
	 * Marks orders placed by wholesale customers (classic and block checkout).
	 *
	 * @param \WC_Order $order Order being created.
	 */
	public static function tag_order( $order ): void {
		if ( $order instanceof \WC_Order && Customer::is_wholesale() ) {
			$order->update_meta_data( self::ORDER_META, 'yes' );
		}
	}

	/**
	 * Shows the marker on the order screen.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function order_badge( $order ): void {
		if ( $order instanceof \WC_Order && 'yes' === $order->get_meta( self::ORDER_META ) ) {
			echo '<p class="form-field form-field-wide"><strong>' . esc_html__( 'Wholesale order', 'wootoolkit' ) . '</strong></p>';
		}
	}
}
