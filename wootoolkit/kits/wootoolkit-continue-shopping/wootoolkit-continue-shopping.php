<?php
/**
 * Kit Name: Continue Shopping
 * Kit URI: http://wootoolkit.com
 * Kit Slug: continue-shopping
 * Description: Adds ability to display continue shopping link to WooCommerce cart.
 * Version: 1.0.0
 * Author: Magnigenie
 * Author URI: http://magnigenie.com
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class Continue_Shopping
 *
 * @class       Continue_Shopping
 * @version     1.0.0
 */
class Continue_Shopping {

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var string $version Plugin version number.
	 */
	public $version = '1.0.0';


	/**
	 * Instance of Continue_Shopping.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var object $instance The instance of Continue_Shopping.
	 */
	private static $instance;

	/**
	 * Construct.
	 *
	 * @since 1.0.0
	 */
	function __construct() {
		
		$this->init();
		add_filter( 'kit_action_links_continue-shopping', array( $this, 'plugin_settings_link' ) );
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
			require_once plugin_dir_path( __FILE__ ) . 'includes/admin/class-admin-options.php';
			$this->admin_settings = new Woo_Toolkit_Continue_Shopping();

		else :

			/**
			 * Front end
			 */
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-continue-shopping.php';
			$this->front_end = new Continue_Shopping_Front_End();

		endif;

	}

	public function plugin_settings_link( $links ) {
	    $wootoolkit_settings_page = array( '<a href="' . admin_url( 'admin.php?page=woo-toolkit-settings&tab=wootoolkit_continue_shopping' ) . '">Settings</a>',
	    );
	    return array_merge( $links, $wootoolkit_settings_page );
	}
}


/**
 * The main function responsible for returning the Continue_Shopping object.
 *
 * Use this function like you would a global variable, except without needing to declare the global.
 *
 * Example: <?php Continue_Shopping()->method_name(); ?>
 *
 * @since 1.0.0
 *
 * @return object Continue_Shopping class object.
 */
if ( ! function_exists( 'Continue_Shopping' ) ) :

 	function Continue_Shopping() {
		return Continue_Shopping::instance();
	}

endif;

Continue_Shopping();