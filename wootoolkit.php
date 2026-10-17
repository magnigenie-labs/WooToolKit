<?php
/**
 * Plugin Name: WooToolkit
 * Plugin URI: https://wootoolkit.com/
 * Description: B2B tools for WooCommerce — wholesale prices, request a quote and catalog mode — plus the Classic kits.
 * Author: MagniGenie
 * Author URI: https://www.magnigenie.com/
 * Version: 2.0.0-dev
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * WC tested up to: 11.2
 * Text Domain: wootoolkit
 * Domain Path: /languages/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WooToolkit
 */

defined( 'ABSPATH' ) || exit;

define( 'WOOTOOLKIT_VERSION', '2.0.0-dev' );
define( 'WOOTOOLKIT_FILE', __FILE__ );
define( 'WOOTOOLKIT_BASE', plugin_basename( __FILE__ ) );
define( 'WOOTOOLKIT_PATH', plugin_dir_path( __FILE__ ) );

// PSR-4 autoloader for src/ (no Composer at runtime).
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'WooToolkit\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$file = WOOTOOLKIT_PATH . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

// Settings screen used by the Classic kits (WooCommerce > WooToolkit Classic).
require WOOTOOLKIT_PATH . 'includes/wootoolkit.settings.class.php';

register_activation_hook( __FILE__, array( 'WooToolkit\\Install\\Installer', 'activate' ) );

WooToolkit\Plugin::instance()->boot();
