<?php
/**
 * Kit Name: Accordions
 * Kit URI: http://wootoolkit.com
 * Kit Slug: accordions
 * Description: Convert your woocommerce tabs to accordion.
 * Version: 1.0.0
 * Author: MagniGenie
 * Author URI: http://www.magnigenie.com
 */

// No direct file access
! defined( 'ABSPATH' ) AND exit;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WT_Accordions {

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var string $version Plugin version number.
	 */
	public $version = '1.0.0';


	/**
	 * Instance of Woo_Accordions.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var object $instance The instance of Woo_Accordions.
	 */
	private static $instance;

	/**
	 * Construct.
	 *
	 * @since 1.0.0
	 */
	function __construct() {
		
		$this->init();
		add_filter( 'kit_action_links_accordions', array( $this, 'plugin_settings_link' ) );
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
			require_once plugin_dir_path( __FILE__ ) . 'includes/admin/accordion-admin-settings.php';
			$this->admin_settings = new Accordion_Admin_Settings();

		else :

			/**
			 * Front end
			 */
			require_once plugin_dir_path( __FILE__ ) . 'includes/accordion-front-end.php';
			$this->front_end = new Accordion_Front_End();

		endif;

	}

	public function plugin_settings_link( $links ) {
	    $wootoolkit_settings_page = array( '<a href="' . admin_url( 'admin.php?page=woo-toolkit-settings&tab=wootoolkit_accordion' ) . '">Settings</a>' );
	    return array_merge( $links, $wootoolkit_settings_page );
	}
}


/**
 * The main function responsible for returning the WT_Accordions object.
 *
 * @since 1.0.0
 *
 * @return object WT_Accordions class object.
 */
if ( ! function_exists( 'WT_Accordions' ) ) :

 	function WT_Accordions() {
		return WT_Accordions::instance();
	}

endif;

WT_Accordions();