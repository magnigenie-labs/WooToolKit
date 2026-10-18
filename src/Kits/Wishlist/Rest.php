<?php
/**
 * Wishlist REST endpoints.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * `wootoolkit/v1/wishlist`: the visitor's wishlist state, add and remove. Logged-in customers are
 * authenticated by WordPress' cookie + REST nonce; guests by their own token cookie, so a request
 * can only ever read or change the caller's own lists.
 *
 * GET    /wootoolkit/v1/wishlist                       State: { count, items: [{ key, product_id, variation_id, list_id }] }
 * POST   /wootoolkit/v1/wishlist/items                 Body: product_id, variation_id?, list_id?  Returns the state.
 * DELETE /wootoolkit/v1/wishlist/items/<product_id>    Query: variation_id?, list_id? (all lists when omitted). Returns the state.
 */
final class Rest {

	const NAMESPACE_V1 = 'wootoolkit/v1';

	/**
	 * Adds the hooks.
	 */
	public static function hooks(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Registers the routes.
	 */
	public static function register_routes(): void {
		$id_arg = array(
			'type'              => 'integer',
			'minimum'           => 0,
			'sanitize_callback' => 'absint',
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/wishlist',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_state' ),
				'permission_callback' => '__return_true', // Returns only the caller's own lists.
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/wishlist/items',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'add_item' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'args'                => array(
					'product_id'   => array_merge( $id_arg, array( 'required' => true ) ),
					'variation_id' => $id_arg,
					'list_id'      => $id_arg,
				),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/wishlist/items/(?P<product_id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( self::class, 'remove_item' ),
				'permission_callback' => array( self::class, 'can_write' ),
				'args'                => array(
					'product_id'   => $id_arg,
					'variation_id' => $id_arg,
					'list_id'      => $id_arg,
				),
			)
		);
	}

	/**
	 * Whether the caller may change a wishlist: logged in, or a guest when guests are allowed.
	 *
	 * @return true|\WP_Error
	 */
	public static function can_write() {
		if ( is_user_logged_in() || Settings::guests_allowed() ) {
			return true;
		}
		return new \WP_Error(
			'wootoolkit_wishlist_login_required',
			__( 'Please log in to use the wishlist.', 'wootoolkit' ),
			array(
				'status'    => 401,
				'login_url' => wc_get_page_permalink( 'myaccount' ),
			)
		);
	}

	/**
	 * GET: the caller's state.
	 */
	public static function get_state(): \WP_REST_Response {
		return self::response( Owner::current() );
	}

	/**
	 * POST: adds a product.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function add_item( \WP_REST_Request $request ) {
		$result = self::add_for_current( (int) $request['product_id'], (int) $request['variation_id'], (int) $request['list_id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = self::response( Owner::current() );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * DELETE: removes a product.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function remove_item( \WP_REST_Request $request ) {
		$owner   = Owner::current();
		$list_id = (int) $request['list_id'];
		if ( $list_id && ! Store::owns( $owner, $list_id ) ) {
			return self::not_yours();
		}
		Store::remove_product( $owner, (int) $request['product_id'], (int) $request['variation_id'], $list_id );
		return self::response( $owner );
	}

	/**
	 * Adds a product for the current visitor, issuing a guest token when needed. Shared by the REST
	 * endpoint and the no-JS link.
	 *
	 * @param int $product_id   Product id.
	 * @param int $variation_id Variation id.
	 * @param int $list_id      Target list (must be the visitor's), 0 for the default list.
	 * @return array{list_id: int, key: string}|\WP_Error
	 */
	public static function add_for_current( int $product_id, int $variation_id = 0, int $list_id = 0 ) {
		$allowed = self::can_write();
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		$resolved = Store::resolve( $product_id, $variation_id );
		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}
		$owner = Owner::current();
		$fresh = false;
		if ( $list_id ) {
			if ( ! Store::owns( $owner, $list_id ) ) {
				return self::not_yours();
			}
		} else {
			// A guest without a list gets a new token, within the per-IP limit (also when the browser
			// sends a token of its own making, so a made-up cookie cannot skip the limit).
			if ( ! $owner->is_user() && ! Store::lists( $owner ) ) {
				$fresh = true;
				$token = Guest::create();
				if ( is_wp_error( $token ) ) {
					return $token;
				}
				$owner = Owner::guest( $token );
			}
			$list_id = Store::default_list( $owner, true );
			if ( is_wp_error( $list_id ) ) {
				return $list_id;
			}
		}
		$key = Store::add_item( (int) $list_id, $product_id, $variation_id );
		if ( is_wp_error( $key ) ) {
			return $key;
		}
		if ( ! $owner->is_user() && ! $fresh ) {
			Guest::remember( Guest::token() ); // Keeps the cookie alive as long as the list is used.
		}
		return array(
			'list_id' => (int) $list_id,
			'key'     => $key,
		);
	}

	/**
	 * The error for someone else's list (404, so list ids are not confirmed to strangers).
	 */
	private static function not_yours(): \WP_Error {
		return new \WP_Error( 'wootoolkit_wishlist_not_found', __( 'This wishlist does not exist.', 'wootoolkit' ), array( 'status' => 404 ) );
	}

	/**
	 * The state response for an owner. Never cached by the browser or proxies.
	 *
	 * @param Owner $owner Owner.
	 */
	public static function response( Owner $owner ): \WP_REST_Response {
		$response = new \WP_REST_Response( self::state( $owner ) );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	/**
	 * The state of an owner's wishlist: what the heart buttons and counters need.
	 *
	 * @param Owner $owner Owner.
	 * @return array<string, mixed>
	 */
	public static function state( Owner $owner ): array {
		$items = Store::owner_items( $owner );
		/**
		 * The wishlist state sent to the storefront script.
		 *
		 * @param array<string, mixed> $state State: count, items, page_url.
		 * @param Owner                $owner Owner.
		 */
		return (array) apply_filters(
			'wootoolkit_wishlist_rest_state',
			array(
				'count'    => count( $items ),
				'items'    => $items,
				'page_url' => Settings::page_url(),
			),
			$owner
		);
	}
}
