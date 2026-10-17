<?php 
/**
* Plugin Name: WooToolKit
* Plugin URI: https://wootoolkit.com/
* Description: One stop solution for all your WooCommerce needs.
* Author: MagniGenie
* Version: 1.0.3
* Requires at least: 6.0
* Requires PHP: 7.4
* WC requires at least: 7.0
* WC tested up to: 11.2
* Author URI: https://www.magnigenie.com/
* Text Domain: wootoolkit
* Domain path: /languages/
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'WOOTOOLKIT_BASE', plugin_basename(__FILE__) );
define( 'WOOTOOLKIT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOOTOOLKIT_FILE', __FILE__ );
define( 'WOOTOOLKIT_VERSION', '1.0.3' );

// All common functions related to plugin will stay here
require WOOTOOLKIT_PATH . '/includes/wootoolkit.class.php';

// Settings API of WooToolkit
require WOOTOOLKIT_PATH . '/includes/wootoolkit.settings.class.php';

// Localiztion with language files
function woo_tk_i18n() {
	load_plugin_textdomain( 'wootoolkit', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'woo_tk_i18n' );

/**
 * WooCommerce feature compatibility.
 * Only the Sequential Order Numbers kit stores data on order posts, so the plugin
 * is HPOS-compatible unless that kit is active. The front-end kits hook the classic
 * cart/checkout templates and do not render inside the Cart & Checkout blocks.
 *
 * @since 1.0.3
 */
add_action( 'before_woocommerce_init', function() {
	if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		return;
	}
	$active_kits = (array) get_option( '_woo_active_kits', array() );
	$hpos_ok     = ! in_array( 'wootoolkit-sequential-order-numbers/wootoolkit-sequential-order-numbers.php', $active_kits, true );
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WOOTOOLKIT_FILE, $hpos_ok );
	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WOOTOOLKIT_FILE, false );
} );

/**
 * One-time upgrade tasks.
 *
 * @since 1.0.3
 */
function woo_tk_maybe_upgrade() {
	$installed = get_option( 'wootoolkit_version', '1.0.2' );
	if ( version_compare( $installed, WOOTOOLKIT_VERSION, '>=' ) ) {
		return;
	}
	if ( version_compare( $installed, '1.0.3', '<' ) ) {
		// 1.0.2 and earlier kept customer emails in this option; Mailchimp now handles de-duplication.
		delete_option( 'wt_mailchimp_mails' );
	}
	update_option( 'wootoolkit_version', WOOTOOLKIT_VERSION );
}
add_action( 'admin_init', 'woo_tk_maybe_upgrade' );

