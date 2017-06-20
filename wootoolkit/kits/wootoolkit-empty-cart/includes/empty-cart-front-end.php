<?php 

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class WT_Empty_Cart_Front_End.
 *
 * Handles all front end related business.
 *
 * @class       WT_Empty_Cart_Front_End
 * @version     1.0.0
 */
class WT_Empty_Cart_Front_End {

    private $empty_cart;

    private $empty_cart_text;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {

        // Get products per page options
        $ec_settings = get_option( 'wootoolkit_empty_cart', 'none' );
        if( is_array( $ec_settings ) ) {
            // Assign Options
            $this->empty_cart = $ec_settings['wt_empty_cart'];
            $this->empty_cart_text = $ec_settings['wt_empty_cart_button_text'];
        }


        //return if empty cart is not enabled
        if( empty($ec_settings['wt_empty_cart']) || $ec_settings['wt_empty_cart'] !== 'on' ) 
          return;

        // Add Actions
        add_action( 'init', 
            array( $this, 'wt_clear_cart_url' ) );
        add_action( 'woocommerce_cart_actions', 
            array( $this, 'wt_empty_cart_button' ) );
    }

    function wt_clear_cart_url() {

      if( isset($_GET['wt_cart']) && $_GET['wt_cart'] == 'clear-cart' ) {

        global $woocommerce;

        $woocommerce->cart->empty_cart();

      }

    }

    public function wt_empty_cart_button() {

        global $woocommerce;
        
        $cart_url = $woocommerce->cart->get_cart_url();

        ?>

        <a href="<?php echo $cart_url; ?>?wt_cart=clear-cart">
            <input type="button" class="button" name="" value="<?php echo $this->empty_cart_text; ?>" />
        </a>

        <?php
    }
}