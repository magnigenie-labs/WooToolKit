<?php
/**
 * Wholesale prices kit.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\WholesalePrices;

use WooToolkit\Kit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Wholesale prices for a "Wholesale customer" role.
 */
final class WholesalePricesKit implements Kit {

	/**
	 * Stable kit id.
	 */
	public function id(): string {
		return 'wholesale-prices';
	}

	/**
	 * Kit name.
	 */
	public function name(): string {
		return __( 'Wholesale prices', 'wootoolkit' );
	}

	/**
	 * Kit description.
	 */
	public function description(): string {
		return __( 'Give customers with the Wholesale customer role their own prices: per product, per category or store-wide.', 'wootoolkit' );
	}

	/**
	 * A 2.0 kit.
	 */
	public function is_classic(): bool {
		return false;
	}

	/**
	 * Works with HPOS (orders are tagged through the CRUD API) and the blocks (prices come from the getters).
	 *
	 * @return array<string, bool>
	 */
	public function compat(): array {
		return array(
			'hpos'   => true,
			'blocks' => true,
		);
	}

	/**
	 * Settings section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings(): array {
		return array(
			array(
				'id'    => 'wootoolkit_wholesale',
				'type'  => 'title',
				'title' => __( 'Wholesale prices', 'wootoolkit' ),
				'desc'  => sprintf(
					/* translators: %s: link to the Users screen */
					__( 'Customers with the Wholesale customer role see wholesale prices. Assign the role under %s. Each product can have its own wholesale price (General tab, or per variation), and each product category its own discount.', 'wootoolkit' ),
					'<a href="' . esc_url( admin_url( 'users.php?role=' . Customer::ROLE ) ) . '">' . esc_html__( 'Users', 'wootoolkit' ) . '</a>'
				),
			),
			array(
				'id'                => Pricing::OPT_DISCOUNT,
				'type'              => 'number',
				'title'             => __( 'Store-wide wholesale discount (%)', 'wootoolkit' ),
				'desc'              => __( 'Used for products that have no wholesale price of their own and no category discount. Leave empty or 0 to charge wholesale customers the regular price for those products.', 'wootoolkit' ),
				'desc_tip'          => false,
				'default'           => '',
				'custom_attributes' => array(
					'min'  => '0',
					'max'  => '100',
					'step' => '0.01',
				),
			),
			array(
				'id'      => Pricing::OPT_SHOW_RRP,
				'type'    => 'checkbox',
				'title'   => __( 'Retail price', 'wootoolkit' ),
				'desc'    => __( 'Show the retail price crossed out next to the wholesale price', 'wootoolkit' ),
				'default' => 'yes',
			),
			array(
				'id'   => 'wootoolkit_wholesale',
				'type' => 'sectionend',
			),
		);
	}

	/**
	 * Hooks the kit.
	 */
	public function boot(): void {
		Customer::register_role();
		add_action( 'set_user_role', array( Customer::class, 'flush_cache' ) );
		add_action( 'add_user_role', array( Customer::class, 'flush_cache' ) );
		add_action( 'remove_user_role', array( Customer::class, 'flush_cache' ) );
		add_action( 'woocommerce_update_options_wootoolkit_' . $this->id(), array( Pricing::class, 'flush_cache' ) );
		Pricing::hooks();
		ProductFields::hooks();
	}
}
