<?php
/**
 * WooToolkit admin pages: menu, kit settings, redirects, settings file, reset, uninstall, editions, links.
 *
 * @package WooToolkit
 */

use WooToolkit\Admin\Admin;
use WooToolkit\Admin\DashboardPage;
use WooToolkit\Admin\HelpPage;
use WooToolkit\Admin\KitSettingsPage;
use WooToolkit\Admin\KitsPage;
use WooToolkit\Admin\Menu;
use WooToolkit\Admin\Screen;
use WooToolkit\Admin\SettingsPage;
use WooToolkit\Kit\HasRequirements;
use WooToolkit\Kit\ProKit;
use WooToolkit\Kit\Registry;
use WooToolkit\Plugin;

/**
 * Admin page tests.
 */
class Test_Admin extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		KitSettingsPage::load_wc_settings();
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( ! has_action( 'wootoolkit_admin_header', array( Screen::class, 'header' ) ) ) {
			add_action( 'wootoolkit_admin_header', array( Screen::class, 'header' ) );
		}
	}

	public function tear_down() {
		$_GET  = array();
		$_POST = array();
		parent::tear_down();
	}

	private function menu(): Menu {
		$registry = Plugin::instance()->registry();
		return new Menu( new DashboardPage( $registry ), new KitsPage( $registry ), new KitSettingsPage( $registry ), new SettingsPage( $registry ), new HelpPage( $registry ) );
	}

	private function submenu(): array {
		global $submenu, $menu;
		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$menu    = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->menu()->register();
		return isset( $submenu['wootoolkit'] ) ? $submenu['wootoolkit'] : array();
	}

	private function render( callable $callback ): string {
		ob_start();
		$callback();
		return (string) ob_get_clean();
	}

	public function test_menu_is_registered_with_the_manage_capability() {
		$items = $this->submenu();
		$this->assertSame(
			array( 'wootoolkit', 'wootoolkit-kits', 'wootoolkit-settings', 'wootoolkit-help', 'admin.php?page=wootoolkit-kits&view=pro' ),
			wp_list_pluck( $items, 2 )
		);
		$this->assertSame( array( 'manage_woocommerce' ), array_values( array_unique( wp_list_pluck( $items, 1 ) ) ) );
		$this->assertSame( 'Dashboard', $items[0][0] );

		update_option( Admin::CAP_OPTION, 'manage_options' );
		$this->assertSame( array( 'manage_options' ), array_values( array_unique( wp_list_pluck( $this->submenu(), 1 ) ) ) );

		update_option( Admin::CAP_OPTION, 'edit_posts' ); // Not allowed: falls back.
		$this->assertSame( 'manage_woocommerce', Admin::capability() );
	}

	public function test_get_pro_is_only_in_the_free_edition() {
		add_filter(
			'wootoolkit_admin_edition',
			static function () {
				return 'pro';
			}
		);
		$this->assertNotContains( 'admin.php?page=wootoolkit-kits&view=pro', wp_list_pluck( $this->submenu(), 2 ) );
	}

	public function test_settings_url_points_at_the_new_pages() {
		$this->assertStringContainsString( 'admin.php?page=wootoolkit-kits', Admin::settings_url() );
		$this->assertStringContainsString( 'page=wootoolkit-kits&kit=wishlist', Admin::settings_url( 'wishlist' ) );
	}

	public function test_old_settings_tab_urls_redirect() {
		$old = array(
			'page' => 'wc-settings',
			'tab'  => 'wootoolkit',
		);
		$this->assertSame( Admin::settings_url(), Admin::legacy_url( $old ) );
		$this->assertSame( Admin::settings_url( 'catalog-mode' ), Admin::legacy_url( array_merge( $old, array( 'section' => 'catalog-mode' ) ) ) );
		$this->assertSame( '', Admin::legacy_url( array_merge( $old, array( 'tab' => 'general' ) ) ) );

		$_GET = array(
			'page'    => 'wc-settings',
			'tab'     => 'wootoolkit',
			'section' => 'wishlist',
		);
		add_filter(
			'wp_redirect',
			static function ( $location ) {
				throw new RuntimeException( esc_url_raw( $location ) );
			}
		);
		try {
			Admin::redirect_legacy();
			$this->fail( 'Expected a redirect.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'page=wootoolkit-kits&kit=wishlist', $e->getMessage() );
		}
	}

	/**
	 * A registry with a kit that has a setting and a Pro kit that extends it.
	 */
	private function kit_registry(): Registry {
		$registry = new Registry();
		$registry->register(
			new WooToolkit_Test_Kit(
				'quotes',
				array(),
				array(
					array(
						'id'    => 'wootoolkit_test_section',
						'type'  => 'title',
						'title' => 'Quotes',
					),
					array(
						'id'      => 'wootoolkit_test_quote_title',
						'type'    => 'text',
						'title'   => 'Title',
						'default' => '',
					),
					array(
						'id'   => 'wootoolkit_test_section',
						'type' => 'sectionend',
					),
				)
			)
		);
		$registry->register(
			new class( 'quotes-pro', array(), array(
				array(
					'id'      => 'wootoolkit_test_tab_field',
					'type'    => 'text',
					'title'   => 'Extra',
					'default' => '',
				),
			) ) extends WooToolkit_Test_Kit implements ProKit, HasRequirements {
				public function requires(): array {
					return array( 'quotes' );
				}
			}
		);
		$registry->set_active( array( 'quotes', 'quotes-pro' ) );
		return $registry;
	}

	public function test_pro_kit_that_needs_only_this_kit_is_a_tab() {
		$page = new KitSettingsPage( $this->kit_registry() );
		$this->assertSame( array( 'quotes-pro' ), $page->tab_kits( 'quotes' ) );
		$this->assertSame( array(), $page->tab_kits( 'quotes-pro' ) );
	}

	public function test_a_tab_kit_url_opens_its_parent_page_on_its_tab() {
		$page = new KitSettingsPage( $this->kit_registry() );
		$this->assertSame( 'quotes', $page->parent_of( 'quotes-pro' ) );
		$this->assertSame( '', $page->parent_of( 'quotes' ) );
		$html = $this->render(
			static function () use ( $page ) {
				$page->render( 'quotes-pro' );
			}
		);
		$this->assertStringContainsString( 'name="kit" value="quotes"', $html );
		$this->assertMatchesRegularExpression( '/aria-selected="true"[^>]*data-wtk-tab="quotes-pro"/', $html );
		$this->assertStringContainsString( 'name="tab" value="quotes-pro"', $html );
	}

	public function test_only_wootoolkit_notices_survive_on_wootoolkit_pages() {
		$html = '<div class="notice notice-warning"><p>Another plugin</p></div>'
			. '<div class="notice notice-warning wootoolkit-notice wtk-notice"><p>Activate your <a href="#">licence</a></p></div>'
			. '<div class="update-nag">Update WordPress</div>';
		$kept = Screen::keep_own_notices( $html );
		$this->assertStringContainsString( 'Activate your', $kept );
		$this->assertStringNotContainsString( 'Another plugin', $kept );
		$this->assertStringNotContainsString( 'Update WordPress', $kept );
		$this->assertSame( '', Screen::keep_own_notices( '<div class="notice">x</div>' ) );
	}

	public function test_free_support_box_is_a_named_removable_callback() {
		HelpPage::hooks();
		$this->assertSame( 10, has_action( 'wootoolkit_help_support', array( HelpPage::class, 'default_support' ) ) );
		remove_action( 'wootoolkit_help_support', array( HelpPage::class, 'default_support' ) );
		$html = $this->render( array( new HelpPage( Plugin::instance()->registry() ), 'render' ) );
		$this->assertStringNotContainsString( 'https://wordpress.org/support/plugin/wootoolkit/', $html );
		HelpPage::hooks();
	}

	public function test_kit_settings_save_fires_the_update_hook_and_checks_the_nonce() {
		$page = new KitSettingsPage( $this->kit_registry() );
		$main = did_action( 'woocommerce_update_options_wootoolkit_quotes' );
		$tab  = did_action( 'woocommerce_update_options_wootoolkit_quotes-pro' );

		$_POST = array(
			'_wpnonce'                    => 'not-a-nonce',
			'wootoolkit_test_quote_title' => 'Hello',
			'wootoolkit_test_tab_field'   => 'Extra',
		);
		$this->assertFalse( $page->save( 'quotes' ) );
		$this->assertFalse( get_option( 'wootoolkit_test_quote_title' ) );
		$this->assertSame( $main, did_action( 'woocommerce_update_options_wootoolkit_quotes' ) );

		$_POST['_wpnonce'] = wp_create_nonce( 'wootoolkit_kit_settings_quotes-pro' ); // Another kit's nonce.
		$this->assertFalse( $page->save( 'quotes' ) );

		$_POST['_wpnonce'] = wp_create_nonce( KitSettingsPage::nonce_action( 'quotes' ) );
		$this->assertTrue( $page->save( 'quotes' ) );
		$this->assertSame( 'Hello', get_option( 'wootoolkit_test_quote_title' ) );
		$this->assertSame( 'Extra', get_option( 'wootoolkit_test_tab_field' ) );
		$this->assertSame( $main + 1, did_action( 'woocommerce_update_options_wootoolkit_quotes' ) );
		$this->assertSame( $tab + 1, did_action( 'woocommerce_update_options_wootoolkit_quotes-pro' ) );
	}

	public function test_kit_settings_save_needs_the_capability() {
		$page = new KitSettingsPage( $this->kit_registry() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST = array(
			'_wpnonce'                    => wp_create_nonce( KitSettingsPage::nonce_action( 'quotes' ) ),
			'wootoolkit_test_quote_title' => 'Hello',
		);
		$this->assertFalse( $page->save( 'quotes' ) );
		$this->assertFalse( get_option( 'wootoolkit_test_quote_title' ) );
	}

	public function test_settings_file_round_trip_leaves_out_site_data_and_secrets() {
		update_option( 'wootoolkit_wishlist_add_text', 'Save it' );
		update_option( 'wootoolkit_quote_page_id', 5 );
		update_option( 'wootoolkit_pro_licence_key', 'abc' );
		update_option(
			'wootoolkit_mailchimp',
			array(
				'mailchimp_api_key' => 'secret-123',
				'list'              => 'news',
			)
		);

		$file = json_decode( (string) wp_json_encode( SettingsPage::export_data() ), true );
		$this->assertSame( 'wootoolkit', $file['plugin'] );
		$this->assertSame( 'Save it', $file['options']['wootoolkit_wishlist_add_text'] );
		$this->assertArrayHasKey( Registry::OPTION, $file['options'] );
		foreach ( array( 'wootoolkit_version', 'wootoolkit_quote_page_id', 'wootoolkit_pro_licence_key', Admin::CAP_OPTION ) as $name ) {
			$this->assertArrayNotHasKey( $name, $file['options'] );
		}
		$this->assertSame( array( 'list' => 'news' ), $file['options']['wootoolkit_mailchimp'] );
		$this->assertStringNotContainsString( 'secret-123', (string) wp_json_encode( $file ) );

		update_option( 'wootoolkit_wishlist_add_text', 'Changed' );
		$parsed = SettingsPage::parse_import( $file );
		$this->assertIsArray( $parsed );
		$page = new SettingsPage( Plugin::instance()->registry() );
		$this->assertGreaterThan( 0, $page->apply_import( $parsed['options'] ) );
		$this->assertSame( 'Save it', get_option( 'wootoolkit_wishlist_add_text' ) );
		$this->assertSame( 5, (int) get_option( 'wootoolkit_quote_page_id' ) );
	}

	public function test_import_rejects_foreign_and_site_specific_options() {
		$parsed = SettingsPage::parse_import(
			array(
				'plugin'  => 'wootoolkit',
				'options' => array(
					'siteurl'                      => 'https://example.invalid',
					'users_can_register'           => '1',
					'wootoolkit_version'           => '0.1',
					'wootoolkit_pro_licence_key'   => 'stolen',
					'wootoolkit_quote_page_id'     => 9,
					Admin::CAP_OPTION              => 'read',
					'wootoolkit_wishlist_add_text' => 'Keep',
				),
			)
		);
		$this->assertSame( array( 'wootoolkit_wishlist_add_text' => 'Keep' ), $parsed['options'] );
		$this->assertContains( 'siteurl', $parsed['rejected'] );
		$this->assertContains( 'wootoolkit_pro_licence_key', $parsed['rejected'] );
		$this->assertContains( Admin::CAP_OPTION, $parsed['rejected'] );

		$this->assertWPError( SettingsPage::parse_import( array( 'options' => array() ) ) );
		$this->assertWPError( SettingsPage::parse_import( 'nope' ) );
	}

	public function test_reset_keeps_active_kits_version_and_page_ids() {
		$active = get_option( Registry::OPTION );
		update_option( 'wootoolkit_wishlist_add_text', 'Save it' );
		update_option( 'wootoolkit_quote_page_id', 7 );
		update_option( 'wootoolkit_pdf_invoices_counter_2026', '12' );
		update_option( 'wootoolkit_show_tips', 'no' );

		$this->assertGreaterThan( 0, ( new SettingsPage( Plugin::instance()->registry() ) )->reset_kit_settings() );

		$this->assertFalse( get_option( 'wootoolkit_wishlist_add_text' ) );
		$this->assertSame( $active, get_option( Registry::OPTION ) );
		$this->assertSame( WOOTOOLKIT_VERSION, get_option( 'wootoolkit_version' ) );
		$this->assertSame( 7, (int) get_option( 'wootoolkit_quote_page_id' ) );
		$this->assertSame( '12', get_option( 'wootoolkit_pdf_invoices_counter_2026' ) );
		$this->assertSame( 'no', get_option( 'wootoolkit_show_tips' ) );
	}

	public function test_uninstall_deletes_only_when_opted_in_and_only_wootoolkit_data() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', WOOTOOLKIT_BASE );
		}
		$wishlist = self::factory()->post->create(
			array(
				'post_type'   => 'wtk_wishlist',
				'post_status' => 'private',
			)
		);
		update_option( 'wootoolkit_wishlist_add_text', 'Save it' );
		update_option( '_woo_active_kits', array( 'x' ) );
		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'wootoolkit_delete_data', 'no' );

		include WOOTOOLKIT_PATH . 'uninstall.php';
		$this->assertSame( 'Save it', get_option( 'wootoolkit_wishlist_add_text' ) );
		$this->assertNotNull( get_post( $wishlist ) );

		update_option( 'wootoolkit_delete_data', 'yes' );
		include WOOTOOLKIT_PATH . 'uninstall.php';
		$this->assertFalse( get_option( 'wootoolkit_wishlist_add_text' ) );
		$this->assertFalse( get_option( Registry::OPTION ) );
		$this->assertFalse( get_option( '_woo_active_kits' ) );
		$this->assertFalse( get_option( 'wootoolkit_delete_data' ) );
		$this->assertNull( get_post( $wishlist ) );
		$this->assertSame( 'EUR', get_option( 'woocommerce_currency' ) );
	}

	public function test_edition_changes_the_kits_page() {
		$page = new KitsPage( Plugin::instance()->registry() );
		$free = $this->render( array( $page, 'render' ) );
		$this->assertStringContainsString( 'Get this kit', $free );
		$this->assertStringContainsString( 'data-wtk-modal="pro"', $free );
		$this->assertStringContainsString( '$129', $free );
		$this->assertStringContainsString( 'https://wootoolkit.com/pricing/', $free );
		$this->assertStringContainsString( 'https://wootoolkit.com/kits/#gift-cards', $free );
		$this->assertStringContainsString( '>Get Pro<', $free );
		$this->assertStringNotContainsString( 'Activate licence to install', $free );

		add_filter(
			'wootoolkit_admin_edition',
			static function () {
				return 'nolicence';
			}
		);
		$nolicence = $this->render( array( $page, 'render' ) );
		$this->assertStringContainsString( 'Activate licence to install', $nolicence );
		$this->assertStringNotContainsString( 'Get this kit', $nolicence );
		$this->assertStringNotContainsString( 'data-wtk-modal="pro"', $nolicence );
		$this->assertStringNotContainsString( '>Get Pro<', $nolicence );
	}

	public function test_pro_kits_filter_drives_the_pro_cards() {
		add_filter(
			'wootoolkit_admin_edition',
			static function () {
				return 'pro';
			}
		);
		add_filter(
			'wootoolkit_admin_pro_kits',
			static function () {
				return array(
					array(
						'kit_id'      => 'gift-cards',
						'name'        => 'Gift cards',
						'description' => 'Sell gift cards.',
						'collection'  => 'loyalty',
						'price'       => '$49',
						'state'       => 'available',
						'store_id'    => 42,
						'needs'       => array(),
					),
				);
			}
		);
		$html = $this->render( array( new KitsPage( Plugin::instance()->registry() ), 'render' ) );
		$this->assertStringContainsString( 'data-store-id="42"', $html );
		$this->assertStringContainsString( '>Install<', $html );
		$this->assertStringNotContainsString( 'Back in stock alerts', $html );
	}

	public function test_free_pages_link_only_to_wootoolkit_com_and_wordpress_org() {
		HelpPage::hooks();
		$registry = Plugin::instance()->registry();
		$html     = $this->render( array( new DashboardPage( $registry ), 'render' ) );
		$html    .= $this->render( array( new KitsPage( $registry ), 'render' ) );
		$html    .= $this->render( array( new HelpPage( $registry ), 'render' ) );
		$kit      = new KitSettingsPage( $registry );
		foreach ( array( 'wishlist', 'pdf-invoices', 'catalog-mode', 'accordions', 'no-such-kit' ) as $id ) {
			$html .= $this->render(
				static function () use ( $kit, $id ) {
					$kit->render( $id );
				}
			);
		}
		$settings = new SettingsPage( $registry );
		foreach ( array_keys( SettingsPage::sections() ) as $section ) {
			$_GET['section'] = $section;
			$html           .= $this->render( array( $settings, 'render' ) );
		}

		preg_match_all( '#https?://[^\s"\'<>)]+#', $html, $matches );
		$site  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$hosts = array();
		foreach ( $matches[0] as $url ) {
			$hosts[ (string) wp_parse_url( html_entity_decode( $url ), PHP_URL_HOST ) ] = true;
		}
		unset( $hosts[ $site ] );
		$this->assertSame( array(), array_diff( array_keys( $hosts ), array( 'wootoolkit.com', 'wordpress.org' ) ) );
		$this->assertStringContainsString( 'https://wordpress.org/support/plugin/wootoolkit/', $html );
		$this->assertStringContainsString( 'https://wootoolkit.com/custom-kits/', $html );
		$this->assertStringContainsString( 'https://wootoolkit.com/docs/wishlist/', $html );
		$this->assertStringNotContainsString( 'fonts.googleapis', $html );
	}

	public function test_dashboard_lists_kits_attention_and_activity() {
		update_option( 'woocommerce_store_address', '' );
		delete_option( 'wootoolkit_pdf_invoices_logo' );
		$order = wc_create_order();
		$order->set_status( 'wtk-quote' );
		$order->save();

		$items = ( new DashboardPage( Plugin::instance()->registry() ) )->attention();
		$this->assertContains( 'PDF invoices', wp_list_pluck( $items, 'kit' ) );
		$activity = DashboardPage::activity();
		$this->assertContains( 'Request a quote', wp_list_pluck( $activity, 'kit' ) );
		$this->assertStringContainsString( 'wtk-quote', $activity[0]['url'] );
	}

	public function test_requirement_missing_is_on_the_dashboard() {
		$registry = $this->kit_registry();
		$registry->set_active( array( 'quotes-pro' ) );
		$items = wp_list_filter( ( new DashboardPage( $registry ) )->attention(), array( 'level' => 'red' ) );
		$this->assertCount( 1, $items );
		$this->assertStringContainsString( 'Test kit quotes', reset( $items )['text'] );
	}
}
