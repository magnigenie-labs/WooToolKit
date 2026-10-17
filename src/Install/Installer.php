<?php
/**
 * Install and upgrade routines.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Install;

use WooToolkit\Kit\Registry;
use WooToolkit\Kits\CatalogMode\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on activation and, cheaply, on every load (so sites that auto-update are migrated before
 * their first front-end request, not on the next admin visit).
 */
final class Installer {

	const VERSION_OPTION = 'wootoolkit_version';

	/**
	 * Set when a 1.x site is migrated; clears once the owner has seen the WooToolkit settings tab.
	 */
	const UPGRADE_NOTICE_OPTION = 'wootoolkit_2x_upgrade_notice';

	/**
	 * Option where 1.x stored active kits, as kit file paths.
	 */
	const LEGACY_ACTIVE_OPTION = '_woo_active_kits';

	/**
	 * Option where the 1.x Catalog kit stored its settings.
	 */
	const LEGACY_CATALOG_OPTION = 'wootoolkit_catalog';

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		if ( self::installed_version() === null ) {
			// Fresh install: Classic kits start off; new kits are switched on by the owner.
			add_option( Registry::OPTION, array() );
			update_option( self::VERSION_OPTION, WOOTOOLKIT_VERSION );
			return;
		}
		self::maybe_upgrade();
	}

	/**
	 * Upgrades from whatever version is installed. Safe to call on every request.
	 */
	public static function maybe_upgrade(): void {
		$installed = self::installed_version();
		if ( null === $installed ) {
			return; // Not installed yet; activate() handles it.
		}
		if ( version_compare( $installed, WOOTOOLKIT_VERSION, '>=' ) ) {
			return;
		}

		if ( version_compare( $installed, '1.0.3', '<' ) ) {
			// 1.0.2 and earlier kept customer emails here; Mailchimp handles de-duplication now.
			delete_option( 'wt_mailchimp_mails' );
		}

		if ( version_compare( $installed, '2.0.0', '<' ) ) {
			self::migrate_active_kits();
			self::migrate_catalog();
		}

		update_option( self::VERSION_OPTION, WOOTOOLKIT_VERSION );
	}

	/**
	 * Maps the 1.x active kit list (file paths) to 2.0 kit ids. Never overwrites an existing 2.0 list,
	 * and keeps the 1.x option so a downgrade to 1.0.x still finds its kits.
	 */
	public static function migrate_active_kits(): void {
		if ( false !== get_option( Registry::OPTION, false ) ) {
			return;
		}

		$active = array();
		foreach ( (array) get_option( self::LEGACY_ACTIVE_OPTION, array() ) as $file ) {
			if ( is_string( $file ) && preg_match( '#^wootoolkit-([a-z0-9-]+)/wootoolkit-\1\.php$#', $file, $match ) ) {
				$active[ $match[1] ] = 'yes';
			}
		}

		update_option( Registry::OPTION, $active );
		update_option( self::UPGRADE_NOTICE_OPTION, 'yes' );
	}

	/**
	 * Moves the 1.x Catalog kit to the 2.0 Catalog mode kit. The 1.x kit applied only while its own
	 * "Catalog mode?" switch was on, so the new kit is switched on only in that case. The 1.x
	 * settings are kept for a downgrade, and existing 2.0 settings are never overwritten.
	 */
	public static function migrate_catalog(): void {
		$active = get_option( Registry::OPTION, array() );
		$active = is_array( $active ) ? $active : array();
		$legacy = get_option( self::LEGACY_CATALOG_OPTION, array() );
		$legacy = is_array( $legacy ) ? $legacy : array();
		$on     = static function ( $key ) use ( $legacy ): bool {
			return isset( $legacy[ $key ] ) && 'on' === $legacy[ $key ];
		};

		if ( array_key_exists( 'catalog', $active ) ) {
			if ( 'yes' === $active['catalog'] && $on( 'catalog_mode' ) && ! isset( $active['catalog-mode'] ) ) {
				$active['catalog-mode'] = 'yes';
			}
			unset( $active['catalog'] );
			update_option( Registry::OPTION, $active );
		}

		if ( ! $legacy ) {
			return;
		}

		$audiences  = array(
			'all'                  => 'all',
			'registered_users'     => 'logged_in',
			'non_registered_users' => 'guests',
		);
		$categories = array_values( array_filter( array_map( 'absint', (array) ( $legacy['categories'] ?? array() ) ) ) );
		$all        = ! $categories || in_array( 'all', (array) ( $legacy['categories'] ?? array() ), true );
		$button     = 'none';
		if ( $on( 'add_custom_button' ) ) {
			$button = 'custom_button_type_custom' === ( $legacy['custom_button_type'] ?? '' ) ? 'custom' : 'product';
		}

		$settings = array(
			Rules::OPT_AUDIENCE       => $audiences[ $legacy['catalog_groups'] ?? 'all' ] ?? 'all',
			Rules::OPT_SCOPE          => $all ? 'all' : 'categories',
			Rules::OPT_CATEGORIES     => $all ? array() : $categories,
			Rules::OPT_BLOCK_PURCHASE => $on( 'remove_add_to_cart_button' ) ? 'yes' : 'no',
			Rules::OPT_HIDE_PRICE     => $on( 'remove_price' ) ? 'yes' : 'no',
			Rules::OPT_BUTTON         => $button,
			Rules::OPT_BUTTON_TEXT    => sanitize_text_field( (string) ( $legacy['custom_button_text'] ?? '' ) ),
			Rules::OPT_BUTTON_URL     => esc_url_raw( (string) ( $legacy['custom_button_link'] ?? '' ) ),
		);
		foreach ( $settings as $option => $value ) {
			add_option( $option, $value ); // No-op when the option already exists.
		}
	}

	/**
	 * Installed version, null when WooToolkit has never been installed on this site.
	 * 1.0.2 and earlier stored no version, so the 1.x active-kits option identifies them.
	 */
	public static function installed_version(): ?string {
		$version = get_option( self::VERSION_OPTION, false );
		if ( is_string( $version ) && '' !== $version ) {
			return $version;
		}
		return false !== get_option( self::LEGACY_ACTIVE_OPTION, false ) ? '1.0.2' : null;
	}
}
