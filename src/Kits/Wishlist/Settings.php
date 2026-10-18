<?php
/**
 * Wishlist settings.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * Option names and their defaults, read in one place.
 */
final class Settings {

	const OPT_PAGE          = 'wootoolkit_wishlist_page_id';
	const OPT_POSITION      = 'wootoolkit_wishlist_position';
	const OPT_LOOP          = 'wootoolkit_wishlist_loop';
	const OPT_STYLE         = 'wootoolkit_wishlist_style';
	const OPT_ADD_TEXT      = 'wootoolkit_wishlist_add_text';
	const OPT_ADDED_TEXT    = 'wootoolkit_wishlist_added_text';
	const OPT_GUESTS        = 'wootoolkit_wishlist_guests';
	const OPT_REMOVE_ON_ADD = 'wootoolkit_wishlist_remove_on_add';
	const OPT_SHARING       = 'wootoolkit_wishlist_sharing';
	const OPT_ACCOUNT       = 'wootoolkit_wishlist_account';
	const OPT_GUEST_DAYS    = 'wootoolkit_wishlist_guest_days';

	/**
	 * Product page positions: value => label.
	 *
	 * @return array<string, string>
	 */
	public static function positions(): array {
		return array(
			'after'  => __( 'After the Add to cart button', 'wootoolkit' ),
			'before' => __( 'Before the Add to cart button', 'wootoolkit' ),
			'none'   => __( 'Do not show on product pages', 'wootoolkit' ),
		);
	}

	/**
	 * Button styles: value => label.
	 *
	 * @return array<string, string>
	 */
	public static function styles(): array {
		return array(
			'icon_text' => __( 'Heart icon and text', 'wootoolkit' ),
			'icon'      => __( 'Heart icon only', 'wootoolkit' ),
			'text'      => __( 'Text only', 'wootoolkit' ),
		);
	}

	/**
	 * Product page position.
	 */
	public static function position(): string {
		$position = (string) get_option( self::OPT_POSITION, 'after' );
		return isset( self::positions()[ $position ] ) ? $position : 'after';
	}

	/**
	 * Button style.
	 */
	public static function style(): string {
		$style = (string) get_option( self::OPT_STYLE, 'icon_text' );
		return isset( self::styles()[ $style ] ) ? $style : 'icon_text';
	}

	/**
	 * Whether hearts show in product loops (shop, categories, product blocks).
	 */
	public static function in_loops(): bool {
		return 'yes' === get_option( self::OPT_LOOP, 'yes' );
	}

	/**
	 * Text of the button when the product is not on the list.
	 */
	public static function add_text(): string {
		$text = trim( (string) get_option( self::OPT_ADD_TEXT, '' ) );
		return '' === $text ? __( 'Add to wishlist', 'wootoolkit' ) : $text;
	}

	/**
	 * Text of the button when the product is on the list.
	 */
	public static function added_text(): string {
		$text = trim( (string) get_option( self::OPT_ADDED_TEXT, '' ) );
		return '' === $text ? __( 'In your wishlist', 'wootoolkit' ) : $text;
	}

	/**
	 * Whether visitors who are not logged in can keep a wishlist.
	 */
	public static function guests_allowed(): bool {
		return 'yes' === get_option( self::OPT_GUESTS, 'yes' );
	}

	/**
	 * Whether a product leaves the wishlist when it is added to the cart from the wishlist page.
	 */
	public static function remove_on_add(): bool {
		return 'yes' === get_option( self::OPT_REMOVE_ON_ADD, 'no' );
	}

	/**
	 * Whether wishlists can be shared with a read-only link.
	 */
	public static function sharing(): bool {
		return 'yes' === get_option( self::OPT_SHARING, 'yes' );
	}

	/**
	 * Whether My Account has a Wishlist page.
	 */
	public static function account_endpoint(): bool {
		return 'yes' === get_option( self::OPT_ACCOUNT, 'yes' );
	}

	/**
	 * Days a guest wishlist is kept after its last change.
	 */
	public static function guest_days(): int {
		return max( 1, min( 365, (int) get_option( self::OPT_GUEST_DAYS, 30 ) ) );
	}

	/**
	 * The wishlist page id, 0 when there is none.
	 */
	public static function page_id(): int {
		$page_id = (int) get_option( self::OPT_PAGE, 0 );
		return $page_id > 0 && 'publish' === get_post_status( $page_id ) ? $page_id : 0;
	}

	/**
	 * The wishlist page URL, empty when there is none.
	 */
	public static function page_url(): string {
		$page_id = self::page_id();
		return $page_id ? (string) get_permalink( $page_id ) : '';
	}
}
