<?php
/**
 * Wishlist kit.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\Wishlist;

use WooToolkit\Kit\AbstractKit;

defined( 'ABSPATH' ) || exit;

/**
 * "Add to wishlist" hearts on product pages and loops, for customers and guests, a wishlist page
 * (block and shortcode), a header counter, share links and a My Account page.
 *
 * Extension points for the Pro kit and custom code (all prefixed `wootoolkit_wishlist_`) are
 * documented in README.md under "Wishlist kit hooks".
 */
final class WishlistKit extends AbstractKit {

	/**
	 * Stable kit id.
	 */
	public function id(): string {
		return 'wishlist';
	}

	/**
	 * Kit name.
	 */
	public function name(): string {
		return __( 'Wishlist', 'wootoolkit' );
	}

	/**
	 * Kit description.
	 */
	public function description(): string {
		return __( 'Shoppers save products to a wishlist with a heart on product pages and in the shop, as guests or logged in, and share it with a read-only link.', 'wootoolkit' );
	}

	/**
	 * Settings section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings(): array {
		return array(
			array(
				'id'    => 'wootoolkit_wishlist',
				'type'  => 'title',
				'title' => __( 'Wishlist', 'wootoolkit' ),
				'desc'  => __( 'Add the counter to your header with the "Wishlist counter" block or the [wootoolkit_wishlist_count] shortcode. Put a heart anywhere with [wootoolkit_wishlist_button id="123"].', 'wootoolkit' ),
			),
			array(
				'id'       => Settings::OPT_PAGE,
				'type'     => 'single_select_page',
				'title'    => __( 'Wishlist page', 'wootoolkit' ),
				'desc'     => __( 'The page with the Wishlist block or the [wootoolkit_wishlist] shortcode. Exclude it from page caching if your cache plugin does not do that automatically.', 'wootoolkit' ),
				'desc_tip' => false,
				'class'    => 'wc-enhanced-select',
				'default'  => '',
			),
			array(
				'id'      => Settings::OPT_POSITION,
				'type'    => 'select',
				'title'   => __( 'Button on product pages', 'wootoolkit' ),
				'options' => Settings::positions(),
				'default' => 'after',
			),
			array(
				'id'      => Settings::OPT_LOOP,
				'type'    => 'checkbox',
				'title'   => __( 'Shop and category pages', 'wootoolkit' ),
				'desc'    => __( 'Show the button in product lists and product blocks', 'wootoolkit' ),
				'default' => 'yes',
			),
			array(
				'id'      => Settings::OPT_STYLE,
				'type'    => 'select',
				'title'   => __( 'Button style', 'wootoolkit' ),
				'options' => Settings::styles(),
				'default' => 'icon_text',
			),
			array(
				'id'          => Settings::OPT_ADD_TEXT,
				'type'        => 'text',
				'title'       => __( 'Button text', 'wootoolkit' ),
				'placeholder' => __( 'Add to wishlist', 'wootoolkit' ),
				'default'     => '',
			),
			array(
				'id'          => Settings::OPT_ADDED_TEXT,
				'type'        => 'text',
				'title'       => __( 'Button text when added', 'wootoolkit' ),
				'placeholder' => __( 'In your wishlist', 'wootoolkit' ),
				'default'     => '',
			),
			array(
				'id'      => Settings::OPT_GUESTS,
				'type'    => 'checkbox',
				'title'   => __( 'Guests', 'wootoolkit' ),
				'desc'    => __( 'Let visitors who are not logged in keep a wishlist (merged into their account when they log in)', 'wootoolkit' ),
				'default' => 'yes',
			),
			array(
				'id'                => Settings::OPT_GUEST_DAYS,
				'type'              => 'number',
				'title'             => __( 'Keep guest wishlists for', 'wootoolkit' ),
				'desc'              => __( 'days after their last change', 'wootoolkit' ),
				'custom_attributes' => array(
					'min'  => '1',
					'max'  => '365',
					'step' => '1',
				),
				'default'           => '30',
			),
			array(
				'id'      => Settings::OPT_REMOVE_ON_ADD,
				'type'    => 'checkbox',
				'title'   => __( 'Add to cart', 'wootoolkit' ),
				'desc'    => __( 'Remove a product from the wishlist when it is added to the cart from the wishlist page', 'wootoolkit' ),
				'default' => 'no',
			),
			array(
				'id'      => Settings::OPT_SHARING,
				'type'    => 'checkbox',
				'title'   => __( 'Sharing', 'wootoolkit' ),
				'desc'    => __( 'Shoppers can share their wishlist with a private, read-only link', 'wootoolkit' ),
				'default' => 'yes',
			),
			array(
				'id'      => Settings::OPT_ACCOUNT,
				'type'    => 'checkbox',
				'title'   => __( 'My Account', 'wootoolkit' ),
				'desc'    => __( 'Add a Wishlist page to My Account', 'wootoolkit' ),
				'default' => 'yes',
			),
			array(
				'id'   => 'wootoolkit_wishlist',
				'type' => 'sectionend',
			),
		);
	}

	/**
	 * Hooks the kit.
	 */
	public function boot(): void {
		Store::register();
		Rest::hooks();
		Frontend::hooks();
		Account::hooks();
		Maintenance::hooks();
		add_action( 'woocommerce_update_options_wootoolkit_' . $this->id(), array( self::class, 'settings_saved' ) );
	}

	/**
	 * After the settings are saved: flush the rewrite rules on the next request (My Account endpoint).
	 */
	public static function settings_saved(): void {
		delete_option( Account::OPT_FLUSHED );
	}
}
