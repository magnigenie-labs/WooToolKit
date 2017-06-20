<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class Continue_Shopping_Front_End.
 *
 * Handles all front end related business.
 *
 * @class		Continue_Shopping_Front_End
 * @version		1.0.0
 */
class Continue_Shopping_Front_End {

	private $continue_destination;

	private $custom_link;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Get products per page options
		$wtk_cs_settings = get_option( 'wootoolkit_continue_shopping', 'none' );

		if( is_array( $wtk_cs_settings ) ) {

			// Assign Options
			$this->continue_destination = $wtk_cs_settings['cs_destination'];
			$this->custom_link 			= $wtk_cs_settings['cs_custom_link'];
		}

		// Add filter for Continue Shipping button
		add_action( 'woocommerce_cart_actions', 
            array( $this, 'woocommerce_continue_shipping_button' ) );
		
		// Add filter to get redirect link
		add_filter( 'woocommerce_continue_shopping_redirect', 
			array( $this, 'wc_custom_redirect_continue_shopping' ) );

		// Add filter to get redirect link
		add_filter( 'woocommerce_before_checkout_form', 
			array( $this, 'woocommerce_continue_shipping_button_checkout' ) );
	}

	/**
	* Continue Shipping aditional button
	*
	* @param void
	* @since 1.0.0
	*/
	public function woocommerce_continue_shipping_button() {

        global $woocommerce;
        
        $continue_url = $this->wc_custom_redirect_continue_shopping();
        $options = get_option('wootoolkit_continue_shopping');
        $enable_shopping = $options['cs_enabled'];
        if( !empty($enable_shopping) && $enable_shopping == 'on' ) :
        ?>
        	<a href="<?php echo $continue_url; ?>">
            	<input type="button" class="button" name="" value="<?php echo __( 'Continue shopping', 'wootoolkit' ) ?>" />
        	</a>
        <?php
        endif;
    }

    /**
	* Continue Shipping aditional button on checkout
	*
	* @param void
	* @since 1.0.0
	*/
	public function woocommerce_continue_shipping_button_checkout() {

        global $woocommerce;
        
        $continue_url = $this->wc_custom_redirect_continue_shopping();

        ?>

        <a href="<?php echo $continue_url; ?>">
        	<?php echo __( 'Continue shopping', 'wootoolkit' ) ?>
        </a>
        <br>
        <br>

        <?php
    }

	/**
	* Custom redirect as per settings
	*
	* @param void
	* @since 1.0.0
	*/
	public function wc_custom_redirect_continue_shopping() {
		
		$cat_referer = get_transient( 'recent_cat' );

		$continue_destination = $this->continue_destination;
		$custom_link = $this->custom_link;
		$siteurl = get_site_url();

		//Begin the switch to check which option has been selected in the admin area.
		switch( $continue_destination ) {

			case "home" :
				$returnlink = $siteurl;
				break;

			case "shop" :
				$shop_id = get_option( 'woocommerce_shop_page_id' );
				$returnlink = get_permalink( $shop_id );
				break;

			case "recent_prod" :

				if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
					$referer = $_SERVER[ 'HTTP_REFERER' ];
				}

				if ( isset( $referer ) ) {
					$returnlink = $referer;
				} else {
					$shop_id = get_option( 'woocommerce_shop_page_id' );
					$returnlink = get_permalink( $shop_id );
				}
				break;

			case "recent_cat" :
				if ( !empty( $cat_referer ) ) {
					$returnlink = $cat_referer;
				} else {
					$shop_id = get_option( 'woocommerce_shop_page_id' );
					$returnlink = get_permalink( $shop_id );
				}
				break;

			case "custom" :
				if ( isset( $custom_link ) ) {
					$returnlink = $custom_link;
				} else {
					$shop_id = get_option( 'woocommerce_shop_page_id' );
					$returnlink = get_permalink( $shop_id );
				}
				break;

			default :
				$returnlink = $siteurl;
				break;
		}

		//return the link we grabbed above.
		return $returnlink;
	}
}