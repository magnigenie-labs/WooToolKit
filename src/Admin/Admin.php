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
 * Wires the WooToolkit menu and its pages, the plugin row link, the old-URL redirects and the two
 * notices WooToolkit shows outside its own pages.
 */
final class Admin {

	/**
	 * Option holding the capability needed to manage WooToolkit.
	 */
	const CAP_OPTION = 'wootoolkit_manage_cap';

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
		add_filter( 'plugin_action_links_' . WOOTOOLKIT_BASE, array( $this, 'plugin_action_links' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$kits     = new KitsPage( $this->registry );
		$kit      = new KitSettingsPage( $this->registry );
		$settings = new SettingsPage( $this->registry );
		$menu     = new Menu( new DashboardPage( $this->registry ), $kits, $kit, $settings, new HelpPage( $this->registry ) );

		$menu->hooks();
		Screen::hooks();
		HelpPage::hooks();
		$kits->hooks();
		$kit->hooks();
		$settings->hooks();
		( new DashboardWidget( $this->registry ) )->hooks();
		add_action( 'admin_init', array( self::class, 'redirect_legacy' ) );
	}

	/**
	 * The capability needed to switch kits on or off and change their settings.
	 * The licence and Import/export always need manage_options.
	 */
	public static function capability(): string {
		$cap = (string) get_option( self::CAP_OPTION, 'manage_woocommerce' );
		return in_array( $cap, array( 'manage_woocommerce', 'manage_options' ), true ) ? $cap : 'manage_woocommerce';
	}

	/**
	 * URL of the Kits page, or of one kit's settings page.
	 *
	 * @param string $kit_id Kit id, '' for the Kits page.
	 */
	public static function settings_url( string $kit_id = '' ): string {
		$args = array( 'page' => 'wootoolkit-kits' );
		if ( '' !== $kit_id ) {
			$args['kit'] = $kit_id;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Where an old WooCommerce > Settings > WooToolkit URL now lives ('' when the query is not one).
	 *
	 * @param array<string, mixed> $query Request query ($_GET).
	 */
	public static function legacy_url( array $query ): string {
		if ( ! isset( $query['page'], $query['tab'] ) || 'wc-settings' !== $query['page'] || 'wootoolkit' !== $query['tab'] ) {
			return '';
		}
		$section = isset( $query['section'] ) && is_string( $query['section'] ) ? sanitize_key( $query['section'] ) : '';
		return self::settings_url( $section );
	}

	/**
	 * Redirects the old settings tab to the new pages (admin_init).
	 */
	public static function redirect_legacy(): void {
		$query = array();
		foreach ( array( 'page', 'tab', 'section' ) as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect of a page URL.
			if ( isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ) {
				$query[ $key ] = sanitize_key( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		$url = self::legacy_url( $query );
		if ( '' !== $url && ! wp_doing_ajax() ) {
			wp_safe_redirect( $url );
			exit;
		}
	}

	/**
	 * Adds a Settings link (to the Kits page) to the plugin row.
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
	 * Missing-WooCommerce and upgraded-from-1.x notices (WooToolkit's own pages hide every notice).
	 */
	public function notices(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'WooToolkit needs WooCommerce. Install and activate WooCommerce to use its kits.', 'wootoolkit' ) . '</p></div>';
			}
			return;
		}

		if ( 'yes' !== get_option( Installer::UPGRADE_NOTICE_OPTION ) || ! current_user_can( self::capability() ) || Screen::is_wootoolkit_screen() ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'WooToolkit 2.0: your kits and their settings have moved to the new WooToolkit menu. Everything you had switched on is still on.', 'wootoolkit' ),
			esc_url( admin_url( 'admin.php?page=wootoolkit' ) ),
			esc_html__( 'Open WooToolkit', 'wootoolkit' )
		);
	}
}
