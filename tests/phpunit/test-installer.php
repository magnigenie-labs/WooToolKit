<?php
/**
 * Fresh installs and the 1.x -> 2.0 upgrade.
 *
 * @package WooToolkit
 */

use WooToolkit\Install\Installer;
use WooToolkit\Kit\Registry;
use WooToolkit\Kits\CatalogMode\Rules;

/**
 * Installer tests.
 */
class Test_Installer extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		foreach ( array( Installer::VERSION_OPTION, Registry::OPTION, Installer::LEGACY_ACTIVE_OPTION, Installer::UPGRADE_NOTICE_OPTION, 'wt_mailchimp_mails', Installer::LEGACY_CATALOG_OPTION, Rules::OPT_AUDIENCE, Rules::OPT_SCOPE, Rules::OPT_CATEGORIES, Rules::OPT_BLOCK_PURCHASE, Rules::OPT_HIDE_PRICE, Rules::OPT_BUTTON, Rules::OPT_BUTTON_TEXT, Rules::OPT_BUTTON_URL ) as $option ) {
			delete_option( $option );
		}
	}

	public function test_fresh_install_starts_with_no_kits_active() {
		Installer::activate();
		$this->assertSame( WOOTOOLKIT_VERSION, get_option( Installer::VERSION_OPTION ) );
		$this->assertSame( array(), get_option( Registry::OPTION ) );
		$this->assertFalse( get_option( Installer::UPGRADE_NOTICE_OPTION ) );
	}

	public function test_upgrade_from_1_0_2_keeps_the_kits_that_were_on() {
		// 1.0.2 stored no version, only the list of active kit files.
		update_option(
			Installer::LEGACY_ACTIVE_OPTION,
			array(
				'wootoolkit-catalog/wootoolkit-catalog.php',
				'wootoolkit-sequential-order-numbers/wootoolkit-sequential-order-numbers.php',
				'../../../wp-config.php',
				'wootoolkit-catalog/other-file.php',
			)
		);
		update_option( 'wt_mailchimp_mails', array( 'old@example.com' ) );

		Installer::maybe_upgrade();

		// The 1.x Catalog kit's own "Catalog mode?" switch was never turned on, so Catalog mode stays off.
		$this->assertSame( array( 'sequential-order-numbers' => 'yes' ), get_option( Registry::OPTION ) );
		$this->assertFalse( get_option( 'wt_mailchimp_mails' ), '1.0.3 cleanup should still run.' );
		$this->assertSame( 'yes', get_option( Installer::UPGRADE_NOTICE_OPTION ) );
		$this->assertSame( WOOTOOLKIT_VERSION, get_option( Installer::VERSION_OPTION ) );
		$this->assertNotFalse( get_option( Installer::LEGACY_ACTIVE_OPTION ), 'The 1.x option is kept for downgrades.' );
	}

	public function test_upgrade_from_1_0_3_migrates_kits_but_skips_the_1_0_3_cleanup() {
		update_option( Installer::VERSION_OPTION, '1.0.3' );
		update_option( Installer::LEGACY_ACTIVE_OPTION, array( 'wootoolkit-empty-cart/wootoolkit-empty-cart.php' ) );
		update_option( 'wt_mailchimp_mails', array( 'kept@example.com' ) );

		Installer::maybe_upgrade();

		$this->assertSame( array( 'empty-cart' => 'yes' ), get_option( Registry::OPTION ) );
		$this->assertSame( array( 'kept@example.com' ), get_option( 'wt_mailchimp_mails' ) );
	}

	public function test_migration_never_overwrites_an_existing_2x_kit_list() {
		update_option( Installer::VERSION_OPTION, '1.0.3' );
		update_option( Installer::LEGACY_ACTIVE_OPTION, array( 'wootoolkit-empty-cart/wootoolkit-empty-cart.php' ) );
		update_option( Registry::OPTION, array( 'empty-cart' => 'no' ) );

		Installer::maybe_upgrade();

		$this->assertSame( array( 'empty-cart' => 'no' ), get_option( Registry::OPTION ) );
	}

	public function test_an_active_1x_catalog_moves_to_catalog_mode_with_its_settings() {
		update_option( Installer::VERSION_OPTION, '1.0.3' );
		update_option( Installer::LEGACY_ACTIVE_OPTION, array( 'wootoolkit-catalog/wootoolkit-catalog.php' ) );
		update_option(
			Installer::LEGACY_CATALOG_OPTION,
			array(
				'catalog_mode'              => 'on',
				'catalog_groups'            => 'non_registered_users',
				'categories'                => array( '12', '15' ),
				'remove_add_to_cart_button' => 'on',
				'add_custom_button'         => 'on',
				'custom_button_text'        => 'Enquire',
				'custom_button_type'        => 'custom_button_type_custom',
				'custom_button_link'        => 'https://example.com/enquire',
				'remove_price'              => 'off',
			)
		);

		Installer::maybe_upgrade();

		$this->assertSame( array( 'catalog-mode' => 'yes' ), get_option( Registry::OPTION ) );
		$this->assertSame( 'guests', get_option( Rules::OPT_AUDIENCE ) );
		$this->assertSame( 'categories', get_option( Rules::OPT_SCOPE ) );
		$this->assertSame( array( 12, 15 ), get_option( Rules::OPT_CATEGORIES ) );
		$this->assertSame( 'yes', get_option( Rules::OPT_BLOCK_PURCHASE ) );
		$this->assertSame( 'no', get_option( Rules::OPT_HIDE_PRICE ) );
		$this->assertSame( 'custom', get_option( Rules::OPT_BUTTON ) );
		$this->assertSame( 'Enquire', get_option( Rules::OPT_BUTTON_TEXT ) );
		$this->assertSame( 'https://example.com/enquire', get_option( Rules::OPT_BUTTON_URL ) );
		$this->assertIsArray( get_option( Installer::LEGACY_CATALOG_OPTION ), 'The 1.x settings are kept for downgrades.' );
	}

	public function test_1x_catalog_for_all_categories_with_the_read_more_button() {
		update_option( Installer::VERSION_OPTION, '1.0.3' );
		update_option( Installer::LEGACY_ACTIVE_OPTION, array( 'wootoolkit-catalog/wootoolkit-catalog.php' ) );
		update_option(
			Installer::LEGACY_CATALOG_OPTION,
			array(
				'catalog_mode'       => 'off',
				'categories'         => array( 'all' ),
				'add_custom_button'  => 'on',
				'custom_button_type' => 'custom_button_type_read_more',
				'remove_price'       => 'on',
			)
		);

		Installer::maybe_upgrade();

		$this->assertSame( array(), get_option( Registry::OPTION ), 'The 1.x switch was off, so the kit stays off.' );
		$this->assertSame( 'all', get_option( Rules::OPT_SCOPE ) );
		$this->assertSame( 'product', get_option( Rules::OPT_BUTTON ) );
		$this->assertSame( 'yes', get_option( Rules::OPT_HIDE_PRICE ) );
		$this->assertSame( 'no', get_option( Rules::OPT_BLOCK_PURCHASE ) );
	}

	public function test_upgrade_is_a_no_op_once_current() {
		update_option( Installer::VERSION_OPTION, WOOTOOLKIT_VERSION );
		update_option( Installer::LEGACY_ACTIVE_OPTION, array( 'wootoolkit-catalog/wootoolkit-catalog.php' ) );

		Installer::maybe_upgrade();

		$this->assertFalse( get_option( Registry::OPTION ) );
	}

	public function test_nothing_happens_before_the_plugin_was_ever_installed() {
		Installer::maybe_upgrade();
		$this->assertFalse( get_option( Installer::VERSION_OPTION ) );
	}
}
