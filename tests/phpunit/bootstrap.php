<?php
/**
 * PHPUnit bootstrap: WordPress test library + WooCommerce + WooToolkit.
 *
 * Works in wp-env (WP_TESTS_DIR=/wordpress-phpunit) and with a classic
 * install-wp-tests setup (WP_TESTS_DIR pointing at the test library).
 *
 * @package WooToolkit
 */

$wootoolkit_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wootoolkit_tests_dir ) {
	$wootoolkit_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $wootoolkit_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test library not found in {$wootoolkit_tests_dir}. Set WP_TESTS_DIR.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

$wootoolkit_plugin_dir = dirname( __DIR__, 2 );

// Optional per-run config (own table prefix), so parallel runs on one database don't collide.
if ( getenv( 'WP_TESTS_CONFIG' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', getenv( 'WP_TESTS_CONFIG' ) );
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $wootoolkit_plugin_dir . '/vendor/yoast/phpunit-polyfills' );

// Load WooToolkit classes from this checkout. When vendor/ is a symlink to another checkout's (the
// parallel VPS runner), Composer's PSR-4 map points at that checkout's src/ and would win.
spl_autoload_register(
	static function ( $class_name ) use ( $wootoolkit_plugin_dir ) {
		if ( 0 !== strpos( $class_name, 'WooToolkit\\' ) ) {
			return;
		}
		$file = $wootoolkit_plugin_dir . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( 'WooToolkit\\' ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	},
	true,
	true
);

require_once $wootoolkit_tests_dir . '/includes/functions.php';

$wootoolkit_wc_file = getenv( 'WC_PLUGIN_FILE' );
if ( ! $wootoolkit_wc_file ) {
	$wootoolkit_wc_file = dirname( $wootoolkit_plugin_dir ) . '/woocommerce/woocommerce.php';
}

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $wootoolkit_wc_file, $wootoolkit_plugin_dir ) {
		require $wootoolkit_wc_file;
		require $wootoolkit_plugin_dir . '/wootoolkit.php';
		// The activation hook does not run under PHPUnit. Mark 2.0 as installed and switch on every
		// kit before kits boot on init, so kit-level tests can exercise them.
		update_option( 'wootoolkit_version', WOOTOOLKIT_VERSION );
		$wootoolkit_active = array();
		foreach ( array_keys( WooToolkit\Plugin::classic_kits() ) as $wootoolkit_file ) {
			$wootoolkit_active[ ( new WooToolkit\Kit\ClassicKit( WOOTOOLKIT_PATH . 'kits/' . $wootoolkit_file ) )->id() ] = 'yes';
		}
		$wootoolkit_active['wholesale-prices'] = 'yes';
		$wootoolkit_active['catalog-mode']     = 'yes';
		$wootoolkit_active['request-a-quote']  = 'yes';
		$wootoolkit_active['wishlist']         = 'yes';
		update_option( 'wootoolkit_active_kits', $wootoolkit_active );
		// Catalog mode is on, but only for products ticked one by one, so other tests see a normal store.
		// Tests change the scope inside their own (rolled back) transaction.
		update_option( 'wootoolkit_catalog_scope', 'products' );
	}
);

tests_add_filter(
	'setup_theme',
	static function () {
		// Create WooCommerce tables, roles and pages in the test database.
		WC_Install::install();
		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_roles();
		// WTK_HPOS=1: store orders in the High-Performance Order Storage tables (no sync), so kits are
		// tested on both order storage types.
		$wootoolkit_hpos = getenv( 'WTK_HPOS' ) ? 'yes' : 'no';
		if ( 'yes' === $wootoolkit_hpos ) {
			wc_get_container()->get( Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class )->create_database_tables();
		}
		update_option( 'woocommerce_feature_custom_order_tables_enabled', $wootoolkit_hpos );
		update_option( 'woocommerce_custom_orders_table_enabled', $wootoolkit_hpos );
		update_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' );
	}
);

require $wootoolkit_tests_dir . '/includes/bootstrap.php';

require __DIR__ . '/helpers/class-wootoolkit-test-kit.php';
require __DIR__ . '/helpers/class-wc-helper-product-minimal.php';
