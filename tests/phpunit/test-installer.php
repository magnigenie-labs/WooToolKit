<?php
/**
 * Fresh installs and the 1.x -> 2.0 upgrade.
 *
 * @package WooToolkit
 */

use WooToolkit\Install\Installer;
use WooToolkit\Kit\Registry;

/**
 * Installer tests.
 */
class Test_Installer extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		foreach ( array( Installer::VERSION_OPTION, Registry::OPTION, Installer::LEGACY_ACTIVE_OPTION, Installer::UPGRADE_NOTICE_OPTION, 'wt_mailchimp_mails' ) as $option ) {
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

		$this->assertSame(
			array(
				'catalog'                  => 'yes',
				'sequential-order-numbers' => 'yes',
			),
			get_option( Registry::OPTION )
		);
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
		update_option( Installer::LEGACY_ACTIVE_OPTION, array( 'wootoolkit-catalog/wootoolkit-catalog.php' ) );
		update_option( Registry::OPTION, array( 'catalog' => 'no' ) );

		Installer::maybe_upgrade();

		$this->assertSame( array( 'catalog' => 'no' ), get_option( Registry::OPTION ) );
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
