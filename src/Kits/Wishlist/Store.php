<?php
/**
 * Wishlist storage.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * Lists are private posts of the type `wtk_wishlist` (no new tables):
 *
 * - post_author: the customer, 0 for guests (then `_wtk_wishlist_owner` holds the hash of the guest token);
 * - `_wtk_wishlist_items`: item key => product_id, variation_id, added (timestamp), price (when added);
 * - `_wtk_wishlist_product`: one row per product (parent id) on the list, so lists can be found and
 *   counted per product with a meta query;
 * - `_wtk_wishlist_share`: the share token of the read-only link (separate from the owner token).
 *
 * Everything here is the public storage API that the Pro kit and other code build on.
 */
final class Store {

	const POST_TYPE    = 'wtk_wishlist';
	const STATUS       = 'private';
	const META_ITEMS   = '_wtk_wishlist_items';
	const META_PRODUCT = '_wtk_wishlist_product';
	const META_OWNER   = '_wtk_wishlist_owner';
	const META_SHARE   = '_wtk_wishlist_share';

	/**
	 * Default item cap per list (filter `wootoolkit_wishlist_max_items`).
	 */
	const MAX_ITEMS = 100;

	/**
	 * Share token length.
	 */
	const SHARE_LENGTH = 20;

	/**
	 * Registers the post type (on init).
	 */
	public static function register(): void {
		if ( post_type_exists( self::POST_TYPE ) ) {
			return;
		}
		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => __( 'Wishlists', 'wootoolkit' ),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'query_var'           => false,
				'rewrite'             => false,
				'supports'            => array( 'title', 'author' ),
				'can_export'          => false,
			)
		);
	}

	/**
	 * Ids of an owner's lists, oldest first.
	 *
	 * @param Owner $owner Owner.
	 * @return int[]
	 */
	public static function lists( Owner $owner ): array {
		if ( ! $owner->exists() ) {
			return array();
		}
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => self::STATUS,
			'fields'         => 'ids',
			'posts_per_page' => 100,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		if ( $owner->is_user() ) {
			$args['author'] = $owner->user_id();
		} else {
			$args['author__in'] = array( 0 );
			$args['meta_key']   = self::META_OWNER; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- guest lists are found by their token hash.
			$args['meta_value'] = $owner->token_hash(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- as above.
		}
		return array_map( 'intval', get_posts( $args ) );
	}

	/**
	 * The owner's default list (the one the heart adds to): the oldest, unless filtered.
	 *
	 * @param Owner $owner  Owner.
	 * @param bool  $create Create it when the owner has none.
	 * @return int|\WP_Error List id, 0 when there is none.
	 */
	public static function default_list( Owner $owner, bool $create = false ) {
		$lists   = self::lists( $owner );
		$list_id = $lists ? $lists[0] : 0;
		/**
		 * The list the heart button adds to.
		 *
		 * @param int   $list_id List id (0 when the owner has none).
		 * @param Owner $owner   Owner.
		 * @param int[] $lists   All the owner's list ids.
		 */
		$list_id = (int) apply_filters( 'wootoolkit_wishlist_default_list', $list_id, $owner, $lists );
		if ( $list_id && ! self::owns( $owner, $list_id ) ) {
			$list_id = $lists ? $lists[0] : 0;
		}
		if ( $list_id || ! $create ) {
			return $list_id;
		}
		return self::create_list( $owner );
	}

	/**
	 * Creates a list.
	 *
	 * @param Owner  $owner Owner (a user, or a guest with a token).
	 * @param string $title List name.
	 * @return int|\WP_Error List id.
	 */
	public static function create_list( Owner $owner, string $title = '' ) {
		if ( ! $owner->exists() ) {
			return new \WP_Error( 'wootoolkit_wishlist_no_owner', __( 'Your wishlist could not be saved. Please try again.', 'wootoolkit' ), array( 'status' => 400 ) );
		}
		$count = count( self::lists( $owner ) );
		/**
		 * Lists one owner may have. The free kit uses one; the Pro kit raises it.
		 *
		 * @param int   $max   Maximum.
		 * @param Owner $owner Owner.
		 */
		$max = (int) apply_filters( 'wootoolkit_wishlist_max_lists', 1, $owner );
		if ( $count > 0 && $count >= $max ) {
			return new \WP_Error( 'wootoolkit_wishlist_too_many_lists', __( 'You cannot create more wishlists.', 'wootoolkit' ), array( 'status' => 400 ) );
		}
		$meta = array(
			self::META_ITEMS => array(),
			self::META_SHARE => wp_generate_password( self::SHARE_LENGTH, false, false ),
		);
		if ( ! $owner->is_user() ) {
			$meta[ self::META_OWNER ] = $owner->token_hash();
		}
		$title = trim( wp_strip_all_tags( $title ) );
		$id    = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => self::STATUS,
				'post_title'  => '' === $title ? __( 'Wishlist', 'wootoolkit' ) : mb_substr( $title, 0, 100 ),
				'post_author' => $owner->user_id(),
				'meta_input'  => $meta,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		/**
		 * Fires after a wishlist is created.
		 *
		 * @param int   $list_id List id.
		 * @param Owner $owner   Owner.
		 */
		do_action( 'wootoolkit_wishlist_list_created', (int) $id, $owner );
		return (int) $id;
	}

	/**
	 * A list post, or null.
	 *
	 * @param int $list_id List id.
	 */
	public static function get( int $list_id ): ?\WP_Post {
		$post = $list_id > 0 ? get_post( $list_id ) : null;
		return $post instanceof \WP_Post && self::POST_TYPE === $post->post_type ? $post : null;
	}

	/**
	 * A list's name (the raw title: get_the_title() would add "Private:").
	 *
	 * @param int $list_id List id.
	 */
	public static function title( int $list_id ): string {
		$post = self::get( $list_id );
		return $post ? (string) $post->post_title : '';
	}

	/**
	 * Whether a list belongs to an owner. Guests own a list only with the token it was created for.
	 *
	 * @param Owner $owner   Owner.
	 * @param int   $list_id List id.
	 */
	public static function owns( Owner $owner, int $list_id ): bool {
		$post = self::get( $list_id );
		if ( ! $post || ! $owner->exists() ) {
			return false;
		}
		if ( $owner->is_user() ) {
			return (int) $post->post_author === $owner->user_id();
		}
		$stored = (string) get_post_meta( $list_id, self::META_OWNER, true );
		return 0 === (int) $post->post_author && '' !== $stored && hash_equals( $stored, $owner->token_hash() );
	}

	/**
	 * The customer a list belongs to, 0 for guest lists.
	 *
	 * @param int $list_id List id.
	 */
	public static function user_id( int $list_id ): int {
		$post = self::get( $list_id );
		return $post ? (int) $post->post_author : 0;
	}

	/**
	 * Items on a list, newest first.
	 *
	 * @param int $list_id List id.
	 * @return array<string, array{product_id: int, variation_id: int, added: int, price: string}>
	 */
	public static function items( int $list_id ): array {
		$items = self::get( $list_id ) ? get_post_meta( $list_id, self::META_ITEMS, true ) : array();
		$out   = array();
		foreach ( is_array( $items ) ? $items : array() as $key => $item ) {
			if ( ! is_array( $item ) || empty( $item['product_id'] ) ) {
				continue;
			}
			$out[ (string) $key ] = array(
				'product_id'   => (int) $item['product_id'],
				'variation_id' => (int) ( $item['variation_id'] ?? 0 ),
				'added'        => (int) ( $item['added'] ?? 0 ),
				'price'        => (string) ( $item['price'] ?? '' ),
			);
		}
		uasort(
			$out,
			static function ( $a, $b ) {
				return $b['added'] <=> $a['added'];
			}
		);
		return $out;
	}

	/**
	 * Item key for a product (and variation).
	 *
	 * @param int $product_id   Product id.
	 * @param int $variation_id Variation id.
	 */
	public static function key( int $product_id, int $variation_id = 0 ): string {
		return $product_id . '-' . $variation_id;
	}

	/**
	 * Maximum items on a list.
	 *
	 * @param int $list_id List id.
	 */
	public static function max_items( int $list_id ): int {
		/**
		 * Maximum number of items on one wishlist (stops abuse of the storage).
		 *
		 * @param int $max     Maximum.
		 * @param int $list_id List id.
		 */
		return max( 1, (int) apply_filters( 'wootoolkit_wishlist_max_items', self::MAX_ITEMS, $list_id ) );
	}

	/**
	 * Resolves what to store for a product id (a variation id given as the product becomes its parent
	 * plus the variation). Returns [product, product_id, variation_id] or an error.
	 *
	 * @param int $product_id   Product (or variation) id.
	 * @param int $variation_id Variation id.
	 * @return array{0: \WC_Product, 1: int, 2: int}|\WP_Error
	 */
	public static function resolve( int $product_id, int $variation_id = 0 ) {
		$invalid = new \WP_Error( 'wootoolkit_wishlist_invalid_product', __( 'This product cannot be added to a wishlist.', 'wootoolkit' ), array( 'status' => 400 ) );
		$product = $product_id > 0 ? wc_get_product( $product_id ) : null;
		if ( $product instanceof \WC_Product_Variation ) {
			$variation_id = $product->get_id();
			$product_id   = $product->get_parent_id();
			$product      = wc_get_product( $product_id );
		}
		if ( ! $product instanceof \WC_Product || $product instanceof \WC_Product_Variation || 'publish' !== $product->get_status() || post_password_required( $product_id ) ) {
			return $invalid;
		}
		$stored = $product;
		if ( $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation instanceof \WC_Product_Variation || $variation->get_parent_id() !== $product_id ) {
				return $invalid;
			}
			$stored = $variation;
		}
		return array( $stored, $product_id, $variation_id );
	}

	/**
	 * Adds a product to a list. Adding one that is already there keeps the original date and price.
	 *
	 * @param int $list_id      List id.
	 * @param int $product_id   Product id.
	 * @param int $variation_id Variation id, 0 for none.
	 * @return string|\WP_Error Item key.
	 */
	public static function add_item( int $list_id, int $product_id, int $variation_id = 0 ) {
		if ( ! self::get( $list_id ) ) {
			return new \WP_Error( 'wootoolkit_wishlist_no_list', __( 'This wishlist does not exist.', 'wootoolkit' ), array( 'status' => 404 ) );
		}
		$resolved = self::resolve( $product_id, $variation_id );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		list( $product, $product_id, $variation_id ) = $resolved;

		$items = self::items( $list_id );
		$key   = self::key( $product_id, $variation_id );
		if ( isset( $items[ $key ] ) ) {
			return $key;
		}
		if ( count( $items ) >= self::max_items( $list_id ) ) {
			return new \WP_Error( 'wootoolkit_wishlist_full', __( 'Your wishlist is full. Remove a product to add another.', 'wootoolkit' ), array( 'status' => 400 ) );
		}
		/**
		 * Whether a product may be added to a list. Return a WP_Error to refuse it.
		 *
		 * @param true|\WP_Error $allowed True to allow.
		 * @param \WC_Product    $product Product (or variation).
		 * @param int            $list_id List id.
		 */
		$allowed = apply_filters( 'wootoolkit_wishlist_can_add', true, $product, $list_id );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$item          = array(
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'added'        => time(),
			'price'        => (string) $product->get_price( 'edit' ),
		);
		$items[ $key ] = $item;
		self::save( $list_id, $items );

		/**
		 * Fires after a product is added to a wishlist.
		 *
		 * @param int                  $list_id List id.
		 * @param string               $key     Item key.
		 * @param array<string, mixed> $item    Item (product_id, variation_id, added, price).
		 */
		do_action( 'wootoolkit_wishlist_item_added', $list_id, $key, $item );
		return $key;
	}

	/**
	 * Removes an item from a list.
	 *
	 * @param int    $list_id List id.
	 * @param string $key     Item key.
	 */
	public static function remove_item( int $list_id, string $key ): bool {
		$items = self::items( $list_id );
		if ( ! isset( $items[ $key ] ) ) {
			return false;
		}
		$item = $items[ $key ];
		unset( $items[ $key ] );
		self::save( $list_id, $items );

		/**
		 * Fires after a product is removed from a wishlist.
		 *
		 * @param int                  $list_id List id.
		 * @param string               $key     Item key.
		 * @param array<string, mixed> $item    The removed item.
		 */
		do_action( 'wootoolkit_wishlist_item_removed', $list_id, $key, $item );
		return true;
	}

	/**
	 * Removes a product from one of the owner's lists, or from all of them. Without a variation, the
	 * product and all of its variations are removed (what the heart button means). Returns how many
	 * items were removed.
	 *
	 * @param Owner $owner        Owner.
	 * @param int   $product_id   Product (or variation) id.
	 * @param int   $variation_id Variation id, 0 for the product and all its variations.
	 * @param int   $list_id      One list (must be the owner's), 0 for all.
	 */
	public static function remove_product( Owner $owner, int $product_id, int $variation_id = 0, int $list_id = 0 ): int {
		$product = wc_get_product( $product_id );
		if ( $product instanceof \WC_Product_Variation ) {
			$variation_id = $product->get_id();
			$product_id   = $product->get_parent_id();
		}
		$lists   = $list_id ? ( self::owns( $owner, $list_id ) ? array( $list_id ) : array() ) : self::lists( $owner );
		$removed = 0;
		foreach ( $lists as $id ) {
			foreach ( self::items( $id ) as $key => $item ) {
				if ( $item['product_id'] === $product_id && ( ! $variation_id || $item['variation_id'] === $variation_id ) && self::remove_item( $id, $key ) ) {
					++$removed;
				}
			}
		}
		return $removed;
	}

	/**
	 * Stores a list's items, keeps the per-product rows in step and marks the list as changed.
	 *
	 * @param int                                $list_id List id.
	 * @param array<string, array<string, mixed>> $items   Items.
	 */
	public static function save( int $list_id, array $items ): void {
		update_post_meta( $list_id, self::META_ITEMS, $items );
		delete_post_meta( $list_id, self::META_PRODUCT );
		foreach ( array_unique( array_map( 'intval', wp_list_pluck( $items, 'product_id' ) ) ) as $product_id ) {
			add_post_meta( $list_id, self::META_PRODUCT, $product_id );
		}
		wp_update_post( array( 'ID' => $list_id ) ); // Updates post_modified, which the guest clean-up reads.
	}

	/**
	 * Deletes a list.
	 *
	 * @param int $list_id List id.
	 */
	public static function delete_list( int $list_id ): bool {
		if ( ! self::get( $list_id ) ) {
			return false;
		}
		/**
		 * Fires before a wishlist is deleted.
		 *
		 * @param int $list_id List id.
		 */
		do_action( 'wootoolkit_wishlist_list_deleted', $list_id );
		return (bool) wp_delete_post( $list_id, true );
	}

	/**
	 * All products on an owner's lists (for the heart buttons and the counter).
	 *
	 * @param Owner $owner Owner.
	 * @return array<int, array{key: string, product_id: int, variation_id: int, list_id: int}>
	 */
	public static function owner_items( Owner $owner ): array {
		$out = array();
		foreach ( self::lists( $owner ) as $list_id ) {
			foreach ( self::items( $list_id ) as $key => $item ) {
				$out[] = array(
					'key'          => $key,
					'product_id'   => $item['product_id'],
					'variation_id' => $item['variation_id'],
					'list_id'      => $list_id,
				);
			}
		}
		return $out;
	}

	/**
	 * Whether a list can be opened with its share link.
	 *
	 * @param int $list_id List id.
	 */
	public static function is_shared( int $list_id ): bool {
		/**
		 * Whether a list can be seen with its share link. Default: the Sharing setting.
		 *
		 * @param bool $shared  Shared.
		 * @param int  $list_id List id.
		 */
		return (bool) apply_filters( 'wootoolkit_wishlist_is_shared', Settings::sharing(), $list_id );
	}

	/**
	 * The share token of a list (created on first use for older lists).
	 *
	 * @param int $list_id List id.
	 */
	public static function share_token( int $list_id ): string {
		$token = (string) get_post_meta( $list_id, self::META_SHARE, true );
		if ( '' === $token && self::get( $list_id ) ) {
			$token = wp_generate_password( self::SHARE_LENGTH, false, false );
			update_post_meta( $list_id, self::META_SHARE, $token );
		}
		return $token;
	}

	/**
	 * The read-only link to a list, '' when sharing is off or there is no wishlist page.
	 *
	 * @param int $list_id List id.
	 */
	public static function share_url( int $list_id ): string {
		$page = Settings::page_url();
		if ( '' === $page || ! self::is_shared( $list_id ) ) {
			return '';
		}
		return add_query_arg( 'wishlist', self::share_token( $list_id ), $page );
	}

	/**
	 * The list a share token opens, 0 when none (or sharing is off for it).
	 *
	 * @param string $token Share token.
	 */
	public static function find_by_share( string $token ): int {
		if ( ! preg_match( '/^[A-Za-z0-9]{' . self::SHARE_LENGTH . '}$/', $token ) ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => self::STATUS,
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_key'       => self::META_SHARE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- lookup by share token.
				'meta_value'     => $token, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- as above.
			)
		);
		$list_id = $ids ? (int) $ids[0] : 0;
		return $list_id && self::is_shared( $list_id ) ? $list_id : 0;
	}

	/**
	 * Moves a guest's items into a customer's default list and deletes the guest lists.
	 * Returns the customer's list id (0 when there was nothing to merge).
	 *
	 * @param Owner $guest   Guest owner.
	 * @param int   $user_id Customer.
	 */
	public static function merge( Owner $guest, int $user_id ): int {
		if ( $guest->is_user() || ! $guest->exists() || $user_id <= 0 ) {
			return 0;
		}
		$guest_lists = self::lists( $guest );
		if ( ! $guest_lists ) {
			return 0;
		}
		$user    = Owner::user( $user_id );
		$list_id = self::default_list( $user, true );
		if ( is_wp_error( $list_id ) || ! $list_id ) {
			return 0;
		}
		$items = self::items( $list_id );
		$added = array();
		$max   = self::max_items( $list_id );
		foreach ( $guest_lists as $guest_list ) {
			foreach ( array_reverse( self::items( $guest_list ), true ) as $key => $item ) {
				if ( isset( $items[ $key ] ) || count( $items ) >= $max ) {
					continue;
				}
				$items[ $key ] = $item;
				$added[ $key ] = $item;
			}
		}
		if ( $added ) {
			self::save( $list_id, $items );
			foreach ( $added as $key => $item ) {
				/** This action is documented in Store::add_item(). */
				do_action( 'wootoolkit_wishlist_item_added', $list_id, $key, $item );
			}
		}
		foreach ( $guest_lists as $guest_list ) {
			self::delete_list( $guest_list );
		}
		/**
		 * Fires after a guest's wishlist was merged into a customer's on login.
		 *
		 * @param int   $list_id     The customer's list id.
		 * @param int   $user_id     Customer.
		 * @param int[] $guest_lists Deleted guest list ids.
		 */
		do_action( 'wootoolkit_wishlist_merged', $list_id, $user_id, $guest_lists );
		return $list_id;
	}

	/**
	 * Number of lists that hold a product (any variation), for counts and reports.
	 *
	 * @param int $product_id Product id.
	 */
	public static function count_lists_with( int $product_id ): int {
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => self::STATUS,
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'meta_key'       => self::META_PRODUCT, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- counting lists per product.
				'meta_value'     => (string) $product_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- as above.
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Ids of lists that hold a product, a page at a time (for batched jobs such as alerts).
	 *
	 * @param int  $product_id  Product id (parent).
	 * @param int  $after_id    Only lists with a higher id.
	 * @param int  $limit       Page size.
	 * @param bool $users_only  Only customers' lists (not guests').
	 * @return int[]
	 */
	public static function lists_with( int $product_id, int $after_id = 0, int $limit = 100, bool $users_only = false ): array {
		$filter = static function ( $where ) use ( $after_id ) {
			global $wpdb;
			return $where . $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $after_id );
		};
		$args = array(
			'post_type'        => self::POST_TYPE,
			'post_status'      => self::STATUS,
			'fields'           => 'ids',
			'posts_per_page'   => $limit,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'meta_key'         => self::META_PRODUCT, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- lists per product.
			'meta_value'       => (string) $product_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- as above.
		);
		if ( $users_only ) {
			$args['author__not_in'] = array( 0 );
		}
		add_filter( 'posts_where', $filter );
		$ids = get_posts( $args );
		remove_filter( 'posts_where', $filter );
		return array_map( 'intval', $ids );
	}
}
