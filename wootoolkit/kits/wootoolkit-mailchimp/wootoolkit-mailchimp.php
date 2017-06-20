<?php
/**
 * Kit Name: MailChimp
 * Kit URI: http://wootoolkit.com
 * Kit Slug: mailchimp
 * Description: Adds the ability to subscribe to Mailchimp list on checkout page.
 * Version: 1.0.0
 * Author: Magnigenie
 * Author URI: http://magnigenie.com
 */

// No direct file access
! defined( 'ABSPATH' ) AND exit;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once plugin_dir_path( __FILE__ ) . 'includes/class-mailchimp.php'; 


class WT_Mailchimp {

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var string $version Plugin version number.
	 */
	public $version = '1.0.0';

	/**
	 * Instance of WT_Mailchimp.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var object $instance The instance of WT_Mailchimp.
	 */
	private static $instance;

	/**
	 * Construct.
	 *
	 * @since 1.0.0
	 */
	function __construct() {
		
		$this->init();
		add_filter( 'kit_action_links_mailchimp', array( $this, 'plugin_settings_link' ) );
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
			require_once plugin_dir_path( __FILE__ ) . 'includes/admin/mailchimp-admin-settings.php';
			$this->admin_settings = new WT_Mailchimp_Admin_Settings();

		else :

			/**
			 * Front end
			 */
			require_once plugin_dir_path( __FILE__ ) . 'includes/mailchimp-front-end.php';
			$this->front_end = new WT_Mailchimp_Front_End();

		endif;

	}

	public function plugin_settings_link( $links ) {
	    $wootoolkit_settings_page = array( '<a href="' . admin_url( 'admin.php?page=woo-toolkit-settings&tab=wootoolkit_mailchimp' ) . '">Settings</a>',
	    );
	    return array_merge( $links, $wootoolkit_settings_page );
	}
}


/**
 * The main function responsible for returning the WT_Mailchimp object.
 *
 * @since 1.0.0
 *
 * @return object WT_Mailchimp class object.
 */
if ( ! function_exists( 'WT_Mailchimp' ) ) :

 	function WT_Mailchimp() {
		return WT_Mailchimp::instance();
	}

endif;


WT_Mailchimp();