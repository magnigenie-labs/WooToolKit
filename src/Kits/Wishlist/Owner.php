<?php
/**
 * Who a wishlist belongs to.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * A logged-in customer (by user id) or a guest (by the secret token in their cookie). Only a hash
 * of a guest token is stored, so the database never holds a working token.
 */
final class Owner {

	/**
	 * User id, 0 for a guest.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Guest token, '' for users and for guests without a list.
	 *
	 * @var string
	 */
	private $token;

	/**
	 * Constructor.
	 *
	 * @param int    $user_id User id.
	 * @param string $token   Guest token.
	 */
	private function __construct( int $user_id, string $token ) {
		$this->user_id = max( 0, $user_id );
		$this->token   = $this->user_id ? '' : $token;
	}

	/**
	 * The current visitor.
	 */
	public static function current(): self {
		$user_id = get_current_user_id();
		return new self( $user_id, $user_id ? '' : Guest::token() );
	}

	/**
	 * A customer.
	 *
	 * @param int $user_id User id.
	 */
	public static function user( int $user_id ): self {
		return new self( $user_id, '' );
	}

	/**
	 * A guest.
	 *
	 * @param string $token Guest token.
	 */
	public static function guest( string $token ): self {
		return new self( 0, Guest::is_valid_token( $token ) ? $token : '' );
	}

	/**
	 * User id, 0 for guests.
	 */
	public function user_id(): int {
		return $this->user_id;
	}

	/**
	 * Whether this is a logged-in customer.
	 */
	public function is_user(): bool {
		return $this->user_id > 0;
	}

	/**
	 * Whether the owner can have lists at all (a user, or a guest with a token).
	 */
	public function exists(): bool {
		return $this->is_user() || '' !== $this->token;
	}

	/**
	 * Hash of the guest token, as stored on the guest's lists. '' for users.
	 */
	public function token_hash(): string {
		return '' === $this->token ? '' : self::hash( $this->token );
	}

	/**
	 * Hash of a guest token.
	 *
	 * @param string $token Token.
	 */
	public static function hash( string $token ): string {
		return hash( 'sha256', $token );
	}
}
