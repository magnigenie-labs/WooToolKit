<?php
/**
 * Helpers shared by kits.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Small, stable helpers that many kits need, so separately distributed kits do not each re-implement them.
 */
final class Support {

	/**
	 * Pseudo-role for visitors who are not logged in.
	 */
	const GUEST = 'guest';

	/**
	 * Roles for a settings multiselect: slug => name, with "Guests" first.
	 *
	 * @return array<string, string>
	 */
	public static function role_options(): array {
		$roles = array( self::GUEST => __( 'Guests (not logged in)', 'wootoolkit' ) );
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$roles[ $slug ] = translate_user_role( $name );
		}
		return $roles;
	}

	/**
	 * Roles of a user (default: the current visitor); "guest" for logged-out visitors.
	 *
	 * @param int|null $user_id User id, null for the current user.
	 * @return string[]
	 */
	public static function roles( ?int $user_id = null ): array {
		$user_id = null === $user_id ? get_current_user_id() : $user_id;
		if ( $user_id <= 0 ) {
			return array( self::GUEST );
		}
		$user = get_userdata( $user_id );
		return $user ? array_values( (array) $user->roles ) : array( self::GUEST );
	}

	/**
	 * Whether a user has any of the given roles. An empty list matches everyone.
	 *
	 * @param string[] $roles   Role slugs (may include "guest").
	 * @param int|null $user_id User id, null for the current user.
	 */
	public static function user_has_role( array $roles, ?int $user_id = null ): bool {
		$roles = array_filter( array_map( 'strval', $roles ) );
		return ! $roles || (bool) array_intersect( $roles, self::roles( $user_id ) );
	}

	/**
	 * Whether the request is a storefront request (not wp-admin, except AJAX), where kits change behaviour.
	 */
	public static function is_storefront(): bool {
		return ! is_admin() || wp_doing_ajax();
	}

	/**
	 * Product categories for a settings multiselect: term id => name.
	 *
	 * @return array<int, string>
	 */
	public static function category_options(): array {
		$out   = array();
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$out[ (int) $term->term_id ] = $term->name;
			}
		}
		return $out;
	}

	/**
	 * Whether a product (or its parent, for variations) is in any of the categories, subcategories included.
	 *
	 * @param \WC_Product $product    Product.
	 * @param int[]       $categories Term ids.
	 */
	public static function product_in_categories( \WC_Product $product, array $categories ): bool {
		$categories = array_map( 'intval', $categories );
		if ( ! $categories ) {
			return false;
		}
		$product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		foreach ( wc_get_product_term_ids( $product_id, 'product_cat' ) as $term_id ) {
			$lineage = array_merge( array( (int) $term_id ), array_map( 'intval', get_ancestors( (int) $term_id, 'product_cat', 'taxonomy' ) ) );
			if ( array_intersect( $lineage, $categories ) ) {
				return true;
			}
		}
		return false;
	}
}
