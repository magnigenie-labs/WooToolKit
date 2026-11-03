<?php
/**
 * The WooToolkit admin menu.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Top-level "WooToolkit" menu: Dashboard, Kits, Settings, (Licence, added by Pro), Help and, in the
 * free edition, "Get Pro".
 */
final class Menu {

	/**
	 * Top-level menu slug.
	 */
	const SLUG = 'wootoolkit';

	/**
	 * Dashboard page.
	 *
	 * @var DashboardPage
	 */
	private $dashboard;

	/**
	 * Kits page.
	 *
	 * @var KitsPage
	 */
	private $kits;

	/**
	 * Kit settings page.
	 *
	 * @var KitSettingsPage
	 */
	private $kit;

	/**
	 * Settings page.
	 *
	 * @var SettingsPage
	 */
	private $settings;

	/**
	 * Help page.
	 *
	 * @var HelpPage
	 */
	private $help;

	/**
	 * Constructor.
	 *
	 * @param DashboardPage   $dashboard Dashboard page.
	 * @param KitsPage        $kits      Kits page.
	 * @param KitSettingsPage $kit       Kit settings page.
	 * @param SettingsPage    $settings  Settings page.
	 * @param HelpPage        $help      Help page.
	 */
	public function __construct( DashboardPage $dashboard, KitsPage $kits, KitSettingsPage $kit, SettingsPage $settings, HelpPage $help ) {
		$this->dashboard = $dashboard;
		$this->kits      = $kits;
		$this->kit       = $kit;
		$this->settings  = $settings;
		$this->help      = $help;
	}

	/**
	 * Registers the hooks. Priority 9, so WooToolkit Pro (default priority) can insert its Licence page.
	 */
	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'register' ), 9 );
	}

	/**
	 * Adds the menu and its pages.
	 */
	public function register(): void {
		$cap = Admin::capability();

		add_menu_page( __( 'WooToolkit', 'wootoolkit' ), __( 'WooToolkit', 'wootoolkit' ), $cap, self::SLUG, array( $this->dashboard, 'render' ), self::icon(), 56 );
		add_submenu_page( self::SLUG, __( 'WooToolkit dashboard', 'wootoolkit' ), __( 'Dashboard', 'wootoolkit' ), $cap, self::SLUG, array( $this->dashboard, 'render' ) );
		add_submenu_page( self::SLUG, __( 'WooToolkit kits', 'wootoolkit' ), __( 'Kits', 'wootoolkit' ), $cap, 'wootoolkit-kits', array( $this, 'kits' ) );
		add_submenu_page( self::SLUG, __( 'WooToolkit settings', 'wootoolkit' ), __( 'Settings', 'wootoolkit' ), $cap, 'wootoolkit-settings', array( $this->settings, 'render' ) );
		add_submenu_page( self::SLUG, __( 'WooToolkit help', 'wootoolkit' ), __( 'Help', 'wootoolkit' ), $cap, 'wootoolkit-help', array( $this->help, 'render' ) );

		if ( Screen::is_free() ) {
			// A plain link (no page callback): WordPress uses the slug as the URL.
			add_submenu_page( self::SLUG, __( 'Get WooToolkit Pro', 'wootoolkit' ), '<span class="wtk-menu-pro">' . esc_html__( 'Get Pro', 'wootoolkit' ) . '</span>', $cap, 'admin.php?page=wootoolkit-kits&view=pro' );
		}
	}

	/**
	 * The Kits page, or one kit's settings page with `&kit=<id>`.
	 */
	public function kits(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects the view.
		$kit = isset( $_GET['kit'] ) && is_string( $_GET['kit'] ) ? sanitize_key( wp_unslash( $_GET['kit'] ) ) : '';
		if ( '' !== $kit ) {
			$this->kit->render( $kit );
			return;
		}
		$this->kits->render();
	}

	/**
	 * The menu icon: the WooToolkit mark, white on transparent, as an SVG data URI. It uses style
	 * attributes rather than fill attributes, so WordPress's icon painter leaves it as drawn.
	 */
	public static function icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="67 68 125 125">'
			. '<defs><mask id="wtk-m" maskUnits="userSpaceOnUse" x="0" y="0" width="256" height="256">'
			. '<rect x="0" y="0" width="256" height="256" style="fill:#fff"/>'
			. '<path d="M128 66V124Q128 150 152 150" style="fill:none;stroke:#000;stroke-width:34;stroke-linecap:round"/>'
			. '</mask></defs>'
			. '<g transform="translate(0 22)">'
			. '<path d="M88 66V122Q88 151 117 151H142Q171 151 171 122V66" mask="url(#wtk-m)" style="fill:none;stroke:#fff;stroke-width:22;stroke-linecap:round"/>'
			. '<path d="M128 66V124Q128 150 152 150" style="fill:none;stroke:#fff;stroke-width:21;stroke-linecap:round"/>'
			. '<path d="M128 83H146" style="fill:none;stroke:#fff;stroke-width:18;stroke-linecap:round"/>'
			. '</g></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- data URI for the menu icon.
	}
}
