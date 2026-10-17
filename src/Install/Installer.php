<?php
/**
 * Install and upgrade routines.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Install;

use WooToolkit\Kit\Registry;

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

		if ( version_compare( $installed, '2.0.0-dev', '<' ) ) {
			self::migrate_active_kits();
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
