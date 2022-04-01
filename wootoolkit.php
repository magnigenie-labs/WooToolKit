<?php 
/*
Plugin Name: WooToolKit
Plugin URI: http://wootookit.com
Description: One stop solution for all your WooCommerce needs.
Author: MagniGenie
Version: 1.0.0
Author URI: http://www.magnigenie.com
Text Domain: wootoolkit
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'WOOTOOLKIT_BASE', plugin_basename(__FILE__) );
define( 'WOOTOOLKIT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOOTOOLKIT_FILE', __FILE__ );

// All common functions related to plugin will stay here
require WOOTOOLKIT_PATH . '/includes/wootoolkit.class.php';

// Settings API of WooToolkit
require WOOTOOLKIT_PATH . '/includes/wootoolkit.settings.class.php';

// Localiztion with language files
function woo_tk_i18n() {
	load_plugin_textdomain( 'wootoolkit', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'woo_tk_i18n' );