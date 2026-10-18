<?php
/**
 * Guest wishlist tokens.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * A guest's list is found by a random secret in an HttpOnly cookie. A second, readable cookie only
 * says "this browser has a list", so the storefront script can skip the REST call for everyone else
 * and pages stay cacheable.
 */
final class Guest {

	const COOKIE      = 'wootoolkit_wishlist';
	const FLAG_COOKIE = 'wootoolkit_wishlist_has';

	/**
	 * New guest lists allowed per IP address per hour (filterable).
	 */
	const HOURLY_LIMIT = 20;

	/**
	 * Token length (letters and digits).
	 */
	const LENGTH = 32;

	/**
	 * The guest token from the cookie, '' when there is none or it is malformed.
	 */
	public static function token(): string {
		$token = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		return self::is_valid_token( $token ) ? $token : '';
	}

	/**
	 * Whether a string looks like a token this kit issued.
	 *
	 * @param string $token Token.
	 */
	public static function is_valid_token( string $token ): bool {
		return (bool) preg_match( '/^[A-Za-z0-9]{' . self::LENGTH . '}$/', $token );
	}

	/**
	 * Issues a token to this browser (for its first wishlist item), within the per-IP hourly limit.
	 *
	 * @return string|\WP_Error Token.
	 */
	public static function create() {
		$ip = self::client_ip();
		if ( '' !== $ip ) {
			$key   = 'wtk_wl_rate_' . md5( $ip );
			$count = (int) get_transient( $key );
			/**
			 * New guest wishlists allowed per IP address per hour.
			 *
			 * @param int $limit Limit.
			 */
			$limit = (int) apply_filters( 'wootoolkit_wishlist_guest_hourly_limit', self::HOURLY_LIMIT );
			if ( $limit > 0 && $count >= $limit ) {
				return new \WP_Error( 'wootoolkit_wishlist_rate_limited', __( 'Too many wishlists were started from your network. Please try again later or log in.', 'wootoolkit' ), array( 'status' => 429 ) );
			}
			set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		}
		$token = wp_generate_password( self::LENGTH, false, false );
		self::remember( $token );
		return $token;
	}

	/**
	 * Sets (or refreshes) the cookies for a token, and makes it visible to the rest of this request.
	 *
	 * @param string $token Token.
	 */
	public static function remember( string $token ): void {
		$_COOKIE[ self::COOKIE ]      = $token;
		$_COOKIE[ self::FLAG_COOKIE ] = '1';
		$expires                      = time() + Settings::guest_days() * DAY_IN_SECONDS;
		self::set_cookie( self::COOKIE, $token, $expires, true );
		self::set_cookie( self::FLAG_COOKIE, '1', $expires, false );
	}

	/**
	 * Removes the cookies (after the guest list was merged into an account).
	 */
	public static function forget(): void {
		unset( $_COOKIE[ self::COOKIE ], $_COOKIE[ self::FLAG_COOKIE ] );
		self::set_cookie( self::COOKIE, '', time() - YEAR_IN_SECONDS, true );
		self::set_cookie( self::FLAG_COOKIE, '', time() - YEAR_IN_SECONDS, false );
	}

	/**
	 * Sends a cookie (Lax, Secure on HTTPS).
	 *
	 * @param string $name     Name.
	 * @param string $value    Value.
	 * @param int    $expires  Expiry timestamp.
	 * @param bool   $httponly Hide it from scripts.
	 */
	private static function set_cookie( string $name, string $value, int $expires, bool $httponly ): void {
		if ( headers_sent() ) {
			return;
		}
		/**
		 * Options for the wishlist cookies (see setcookie()).
		 *
		 * @param array<string, mixed> $options Cookie options.
		 * @param string               $name    Cookie name.
		 */
		$options = (array) apply_filters(
			'wootoolkit_wishlist_cookie_options',
			array(
				'expires'  => $expires,
				'path'     => defined( 'COOKIEPATH' ) && constant( 'COOKIEPATH' ) ? (string) constant( 'COOKIEPATH' ) : '/',
				'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => $httponly,
				'samesite' => 'Lax',
			),
			$name
		);
		setcookie( $name, $value, $options ); // Not wc_setcookie(): it cannot set SameSite.
	}

	/**
	 * The visitor's IP address: the connection address, since forwarding headers can be forged.
	 * Behind a proxy or CDN, pass the real address with the filter.
	 */
	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		/**
		 * The visitor's IP address, for the guest wishlist limit.
		 *
		 * @param string $ip REMOTE_ADDR.
		 */
		$ip = (string) apply_filters( 'wootoolkit_wishlist_client_ip', $ip );
		return false === filter_var( $ip, FILTER_VALIDATE_IP ) ? '' : $ip;
	}
}
