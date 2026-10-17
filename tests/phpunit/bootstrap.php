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

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $wootoolkit_plugin_dir . '/vendor/yoast/phpunit-polyfills' );

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
		// The activation hook does not run under PHPUnit; activate every kit the
		// way WooToolkit::install() does, before kits load on init.
		$GLOBALS['wootoolkit']->install();
	}
);

tests_add_filter(
	'setup_theme',
	static function () {
		// Create WooCommerce tables, roles and pages in the test database.
		WC_Install::install();
		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_roles();
	}
);

require $wootoolkit_tests_dir . '/includes/bootstrap.php';

require __DIR__ . '/helpers/class-wootoolkit-test-redirect.php';
require __DIR__ . '/helpers/class-wc-helper-product-minimal.php';
