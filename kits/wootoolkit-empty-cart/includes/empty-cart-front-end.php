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

        // Add Actions (wp_loaded: the cart session exists by then)
        add_action( 'wp_loaded',
            array( $this, 'wt_clear_cart_url' ) );
        add_action( 'woocommerce_cart_actions', 
            array( $this, 'wt_empty_cart_button' ) );
    }

    function wt_clear_cart_url() {

      if ( ! isset( $_GET['wt_cart'] ) || 'clear-cart' !== $_GET['wt_cart'] ) {
        return;
      }

      // Only a request carrying our nonce (i.e. the button) may empty the cart
      if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'wt_empty_cart' ) ) {
        return;
      }

      if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return;
      }

      WC()->cart->empty_cart();

      wp_safe_redirect( wc_get_cart_url() );
      exit;
    }

    public function wt_empty_cart_button() {

        $url  = wp_nonce_url( add_query_arg( 'wt_cart', 'clear-cart', wc_get_cart_url() ), 'wt_empty_cart' );
        $text = $this->empty_cart_text ? $this->empty_cart_text : __( 'Empty Cart', 'wootoolkit' );

        ?>

        <a href="<?php echo esc_url( $url ); ?>" class="button wt-empty-cart"><?php echo esc_html( $text ); ?></a>

        <?php
    }
}
