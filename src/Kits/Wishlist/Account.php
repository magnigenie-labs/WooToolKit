<?php
/**
 * My Account > Wishlist.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * A "Wishlist" page in My Account showing the same table as the wishlist page.
 */
final class Account {

	const ENDPOINT    = 'wishlist';
	const OPT_FLUSHED = 'wootoolkit_wishlist_rewrite_flushed';

	/**
	 * Adds the hooks.
	 */
	public static function hooks(): void {
		if ( ! Settings::account_endpoint() ) {
			return;
		}
		add_filter( 'woocommerce_get_query_vars', array( self::class, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( self::class, 'menu_items' ), 20 );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( self::class, 'title' ), 10, 0 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( self::class, 'render' ) );
		add_action( 'init', array( self::class, 'maybe_flush_rewrite_rules' ), 99 );
	}

	/**
	 * Registers the endpoint with WooCommerce (which adds the rewrite endpoint and the query var).
	 *
	 * @param array<string, string> $vars Query vars.
	 * @return array<string, string>
	 */
	public static function query_vars( $vars ): array {
		$vars                   = (array) $vars;
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * Adds "Wishlist" after "Orders" (or before "Log out").
	 *
	 * @param array<string, string> $items Menu items.
	 * @return array<string, string>
	 */
	public static function menu_items( $items ): array {
		$out   = array();
		$added = false;
		foreach ( (array) $items as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'orders' === $key ) {
				$out[ self::ENDPOINT ] = self::title();
				$added                 = true;
			}
		}
		if ( ! $added ) {
			$logout = isset( $out['customer-logout'] ) ? array( 'customer-logout' => $out['customer-logout'] ) : array();
			unset( $out['customer-logout'] );
			$out[ self::ENDPOINT ] = self::title();
			$out                  += $logout;
		}
		return $out;
	}

	/**
	 * Page and menu title.
	 */
	public static function title(): string {
		return __( 'Wishlist', 'wootoolkit' );
	}

	/**
	 * The endpoint content.
	 */
	public static function render(): void {
		echo Frontend::render_list( wc_get_account_endpoint_url( self::ENDPOINT ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.
	}

	/**
	 * Flushes rewrite rules once, so the new endpoint URL works.
	 */
	public static function maybe_flush_rewrite_rules(): void {
		if ( 'yes' !== get_option( self::OPT_FLUSHED ) ) {
			flush_rewrite_rules( false );
			update_option( self::OPT_FLUSHED, 'yes', false );
		}
	}
}
