<?php
/**
 * Catalog mode kit.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\CatalogMode;

use WooToolkit\Kit\Kit;
use WooToolkit\Kits\WholesalePrices\Customer;

defined( 'ABSPATH' ) || exit;

/**
 * Hide prices and stop purchases, for chosen visitors and products. Replaces the 1.x Catalog kit.
 */
final class CatalogModeKit implements Kit {

	/**
	 * Stable kit id.
	 */
	public function id(): string {
		return 'catalog-mode';
	}

	/**
	 * Kit name.
	 */
	public function name(): string {
		return __( 'Catalog mode', 'wootoolkit' );
	}

	/**
	 * Kit description.
	 */
	public function description(): string {
		return __( 'Hide prices and stop purchases for everyone, guests or retail customers, on all products or chosen ones. Add-to-cart links and the Store API are blocked too.', 'wootoolkit' );
	}

	/**
	 * A 2.0 kit.
	 */
	public function is_classic(): bool {
		return false;
	}

	/**
	 * Works with HPOS (touches no orders) and the blocks (purchases are refused by WooCommerce itself).
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
		$categories = array();
		$terms      = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[ $term->term_id ] = $term->name;
			}
		}

		return array(
			array(
				'id'    => 'wootoolkit_catalog',
				'type'  => 'title',
				'title' => __( 'Catalog mode', 'wootoolkit' ),
				'desc'  => __( 'Products in catalog mode can be browsed but not bought. WooCommerce refuses them everywhere, including direct add-to-cart links and the Store API. Store managers in wp-admin are not affected.', 'wootoolkit' ),
			),
			array(
				'id'      => Rules::OPT_AUDIENCE,
				'type'    => 'select',
				'title'   => __( 'Applies to', 'wootoolkit' ),
				'desc'    => sprintf(
					/* translators: %s: name of the Wholesale prices kit */
					__( '"Everyone except wholesale customers" uses the Wholesale customer role from the %s kit.', 'wootoolkit' ),
					__( 'Wholesale prices', 'wootoolkit' )
				),
				'class'   => 'wc-enhanced-select',
				'options' => Rules::audiences(),
				'default' => 'all',
			),
			array(
				'id'      => Rules::OPT_SCOPE,
				'type'    => 'select',
				'title'   => __( 'Products', 'wootoolkit' ),
				'class'   => 'wc-enhanced-select',
				'options' => Rules::scopes(),
				'default' => 'all',
			),
			array(
				'id'      => Rules::OPT_CATEGORIES,
				'type'    => 'multiselect',
				'title'   => __( 'Categories', 'wootoolkit' ),
				'desc'    => __( 'Used when Products is set to categories. Subcategories are included.', 'wootoolkit' ),
				'class'   => 'wc-enhanced-select',
				'options' => $categories,
				'default' => array(),
			),
			array(
				'id'      => Rules::OPT_BLOCK_PURCHASE,
				'type'    => 'checkbox',
				'title'   => __( 'Purchases', 'wootoolkit' ),
				'desc'    => __( 'Stop these products from being bought, and remove the Add to cart button', 'wootoolkit' ),
				'default' => 'yes',
			),
			array(
				'id'      => Rules::OPT_HIDE_PRICE,
				'type'    => 'checkbox',
				'title'   => __( 'Prices', 'wootoolkit' ),
				'desc'    => __( 'Hide prices (also from search engines and the Store API)', 'wootoolkit' ),
				'default' => 'yes',
			),
			array(
				'id'          => Rules::OPT_PRICE_TEXT,
				'type'        => 'text',
				'title'       => __( 'Text instead of the price', 'wootoolkit' ),
				'desc'        => __( 'Optional, for example "Log in to see prices".', 'wootoolkit' ),
				'desc_tip'    => false,
				'default'     => '',
			),
			array(
				'id'      => Rules::OPT_BUTTON,
				'type'    => 'select',
				'title'   => __( 'Button instead of Add to cart', 'wootoolkit' ),
				'class'   => 'wc-enhanced-select',
				'options' => Rules::buttons(),
				'default' => 'none',
			),
			array(
				'id'          => Rules::OPT_BUTTON_TEXT,
				'type'        => 'text',
				'title'       => __( 'Button text', 'wootoolkit' ),
				'placeholder' => __( 'View product', 'wootoolkit' ),
				'default'     => '',
			),
			array(
				'id'          => Rules::OPT_BUTTON_URL,
				'type'        => 'url',
				'title'       => __( 'Custom link', 'wootoolkit' ),
				'placeholder' => 'https://',
				'default'     => '',
			),
			array(
				'id'   => 'wootoolkit_catalog',
				'type' => 'sectionend',
			),
		);
	}

	/**
	 * Hooks the kit.
	 */
	public function boot(): void {
		add_action( 'woocommerce_update_options_wootoolkit_' . $this->id(), array( Rules::class, 'flush_cache' ) );
		add_action( 'set_user_role', array( Rules::class, 'flush_cache' ) );
		add_action( 'set_user_role', array( Customer::class, 'flush_cache' ) );
		add_action( 'wp_login', array( Rules::class, 'flush_cache' ) );
		Storefront::hooks();
		ProductField::hooks();
	}
}
