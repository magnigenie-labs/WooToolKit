<?php
/**
 * Kit Name: Products Per Page
 * Kit URI: http://wootoolkit.com
 * Kit Slug: products-per-page
 * Description: Adds a products per page dropdown to your WooCommerce website.
 * Version: 1.0.0
 * Author: Magnigenie
 * Author URI: http://magnigenie.com
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class WT_Products_Per_Page
 *
 * @class       WT_Products_Per_Page
 * @version     1.0.0
 */
class WT_Products_Per_Page {

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var string $version Plugin version number.
	 */
	public $version = '1.0.0';


	/**
	 * Instance of WT_Products_Per_Page.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var object $instance The instance of WT_Products_Per_Page.
	 */
	private static $instance;

	/**
	 * Construct.
	 *
	 * @since 1.0.0
	 */
	function __construct() {
		
		$this->init();
		add_filter( 'kit_action_links_products-per-page', array( $this, 'plugin_settings_link' ) );
    }

	/**
	 * Instance.
	 *
	 * An global instance of the class. Used to retrieve the instance
	 * to use on other files/plugins/themes.
	 *
	 * @since 1.0.0
	 * @return object Instance of the class.
	 */
	public static function instance() {

		if ( is_null( self::$instance ) ) :
			self::$instance = new self();
		endif;

		return self::$instance;

	}


	/**
	 * Init.
	 *
	 * Initialize plugin parts.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		if ( is_admin() ) :

			/**
			 * Settings
			 */
			require_once plugin_dir_path( __FILE__ ) . 'includes/admin/products-per-page-admin-settings.php';
			$this->admin_settings = new WT_Products_Per_Page_Admin_Settings();

		else :

			/**
			 * Front end
			 */
			require_once plugin_dir_path( __FILE__ ) . 'includes/products-per-page-front-end.php';
			$this->front_end = new WT_Products_Per_Page_Front_End();

		endif;

	}

	public function plugin_settings_link( $links ) {
        $wootoolkit_settings_page = array( '<a href="' . admin_url( 'admin.php?page=woo-toolkit-settings&tab=wootoolkit_products_per_page' ) . '">Settings</a>' );
        return array_merge( $links, $wootoolkit_settings_page );
    }
}


/**
 * The main function responsible for returning the WT_Products_Per_Page object.
 *
 * Use this function like you would a global variable, except without needing to declare the global.
 *
 * Example: WT_Products_Per_Page()->method_name();
 *
 * @since 1.0.0
 *
 * @return object WT_Products_Per_Page class object.
 */
if ( ! function_exists( 'WT_Products_Per_Page' ) ) :

 	function WT_Products_Per_Page() {
		return WT_Products_Per_Page::instance();
	}

endif;

WT_Products_Per_Page();