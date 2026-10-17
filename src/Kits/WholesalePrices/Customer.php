<?php
/**
 * Who counts as a wholesale customer.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\WholesalePrices;

defined( 'ABSPATH' ) || exit;

/**
 * The wholesale customer role.
 */
final class Customer {

	const ROLE = 'wholesale_customer';

	/**
	 * Per-request cache of user id => is wholesale.
	 *
	 * @var array<int, bool>
	 */
	private static $cache = array();

	/**
	 * Creates the role if it does not exist yet.
	 */
	public static function register_role(): void {
		if ( null === get_role( self::ROLE ) ) {
			add_role( self::ROLE, __( 'Wholesale customer', 'wootoolkit' ), array( 'read' => true ) );
		}
	}

	/**
	 * Whether a user (default: the current user) gets wholesale prices.
	 *
	 * @param int|null $user_id User id, null for the current user.
	 */
	public static function is_wholesale( ?int $user_id = null ): bool {
		$user_id = null === $user_id ? get_current_user_id() : $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( ! isset( self::$cache[ $user_id ] ) ) {
			$user                    = get_userdata( $user_id );
			self::$cache[ $user_id ] = $user && in_array( self::ROLE, (array) $user->roles, true );
		}

		/**
		 * Whether a user gets wholesale prices.
		 *
		 * @param bool $is_wholesale Whether the user has the wholesale role.
		 * @param int  $user_id      User id.
		 */
		return (bool) apply_filters( 'wootoolkit_is_wholesale_customer', self::$cache[ $user_id ], $user_id );
	}

	/**
	 * Forgets cached role lookups (after role changes, and between tests).
	 */
	public static function flush_cache(): void {
		self::$cache = array();
	}
}
