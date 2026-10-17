<?php
/**
 * Admin integration.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Admin;

use WooToolkit\Install\Installer;
use WooToolkit\Kit\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Settings tab, plugin row links and admin notices.
 */
final class Admin {

	/**
	 * Kit registry.
	 *
	 * @var Registry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param Registry $registry Kit registry.
	 */
	public function __construct( Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Registers admin hooks.
	 */
	public function hooks(): void {
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_settings_page' ) );
		add_filter( 'plugin_action_links_' . WOOTOOLKIT_BASE, array( $this, 'plugin_action_links' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/**
	 * URL of the WooToolkit settings tab.
	 *
	 * @param string $section Section id, '' for the kit list.
	 */
	public static function settings_url( string $section = '' ): string {
		$args = array(
			'page' => 'wc-settings',
			'tab'  => 'wootoolkit',
		);
		if ( '' !== $section ) {
			$args['section'] = $section;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Adds the WooToolkit tab to WooCommerce > Settings.
	 *
	 * @param array<int, mixed> $pages Settings pages.
	 * @return array<int, mixed>
	 */
	public function add_settings_page( $pages ) {
		$pages[] = new SettingsPage( $this->registry );
		return $pages;
	}

	/**
	 * Adds a Settings link to the plugin row.
	 *
	 * @param array<string|int, string> $links Plugin action links.
	 * @return array<string|int, string>
	 */
	public function plugin_action_links( $links ) {
		if ( class_exists( 'WooCommerce' ) ) {
			array_unshift( $links, '<a href="' . esc_url( self::settings_url() ) . '">' . esc_html__( 'Settings', 'wootoolkit' ) . '</a>' );
		}
		return $links;
	}

	/**
	 * Missing-WooCommerce and upgraded-from-1.x notices.
	 */
	public function notices(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'WooToolkit needs WooCommerce. Install and activate WooCommerce to use its kits.', 'wootoolkit' ) . '</p></div>';
			}
			return;
		}

		if ( 'yes' !== get_option( Installer::UPGRADE_NOTICE_OPTION ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'woocommerce_page_wc-settings' === $screen->id ) {
			return; // The settings tab clears the notice itself.
		}

		printf(
			'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'WooToolkit 2.0: your kits and their settings have moved to WooCommerce > Settings > WooToolkit. Everything you had switched on is still on.', 'wootoolkit' ),
			esc_url( self::settings_url() ),
			esc_html__( 'Open WooToolkit settings', 'wootoolkit' )
		);
	}
}
