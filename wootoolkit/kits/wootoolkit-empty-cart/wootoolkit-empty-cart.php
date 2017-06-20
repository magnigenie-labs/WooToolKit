<?php
/**
 * Kit Name: Empty Cart
 * Kit URI: http://wootoolkit.com
 * Kit Slug: empty-cart
 * Description: Adds Empty cart button to WooCommerce Cart.
 * Version: 1.0.0
 * Author: Magnigenie
 * Author URI: http://magnigenie.com
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class WT_Empty_Cart
 *
 * @class       WT_Empty_Cart
 * @version     1.0.0
 */
class WT_Empty_Cart {

    /**
     * Plugin version.
     *
     * @since 1.0.0
     * @var string $version Plugin version number.
     */
    public $version = '1.0.0';


    /**
     * Instance of WT_Empty_Cart.
     *
     * @since 1.0.0
     * @access private
     * @var object $instance The instance of WT_Empty_Cart.
     */
    private static $instance;

    /**
     * Construct.
     *
     * @since 1.0.0
     */
    function __construct() {
        $this->init();
        add_filter( 'kit_action_links_empty-cart', array( $this, 'plugin_settings_link' ) );
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
            require_once plugin_dir_path( __FILE__ ) . 'includes/admin/empty-cart-admin.php';
            $this->admin_settings = new WT_Empty_Cart_Admin_Settings();

        else :

            /**
             * Front end
             */
            require_once plugin_dir_path( __FILE__ ) . 'includes/empty-cart-front-end.php';
            $this->front_end = new WT_Empty_Cart_Front_End();

        endif;

    }

    public function plugin_settings_link( $links ) {
        
        $wootoolkit_settings_page = array( '<a href="' . admin_url( 'admin.php?page=woo-toolkit-settings&tab=wootoolkit_empty_cart' ) . '">Settings</a>',
        );
        return array_merge( $links, $wootoolkit_settings_page );
    }
}


/**
 * The main function responsible for returning the WT_Empty_Cart object.
 *
 * @since 1.0.0
 *
 * @return object WT_Empty_Cart class object.
 */
if ( ! function_exists( 'Empty_Cart' ) ) :

    function WT_Empty_Cart() {
        return WT_Empty_Cart::instance();
    }

endif;

WT_Empty_Cart();