<?php

defined( 'ABSPATH' ) or exit;

class WT_Customizer_Admin_Settings {

	/**
	 * Kit url slug and ID
	 *
	 * @since 1.0.0
	 * @var string $kit kit slug
	 */
	private $kit;


	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$this->kit = 'wootoolkit_woo_customizer';

		// Add settings to the settings array
        add_filter( 'wootoolkit_setting_tabs', array( $this, 'set_settings_tab' ) );
        add_filter( 'wootoolkit_setting_fields', array( $this, 'set_settings_fields' ) );
	}

	/**
     * Returns all the settings tabs
     *
     * @return array settings tabs
     */
    function set_settings_tab($tabs) {
        $tabs[] = array( 'id' => $this->kit, 'title' => __( 'Shop Customizer', 'wootoolkit' ) );
        return $tabs;
    }

    /**
     * Returns all the settings fields
     *
     * @return array settings fields
     */
    function set_settings_fields( $fields ) {

		// Setting the page form fields
        $fields[$this->kit] = 
        	array(
        		array(
          		'label'     => __( 'Enable', 'wootoolkit' ),
          		'desc'      => __( 'Enable Shop Customizer.', 'wootoolkit' ),
          		'type'      => 'checkbox',
          		'name'      => 'enabled',
          		'default'   => 'off'                      
        		),
						array(
							'name'      => 'add_to_cart_text',
							'label'    	=> __( 'Simple Product', 'wootoolkit' ),
							'desc' 			=> __( 'Changes the add to cart button text for simple products on all loop pages', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'variable_add_to_cart_text',
							'label'    	=> __( 'Variable Product', 'wootoolkit' ),
							'desc' 			=> __( 'Changes the add to cart button text for variable products on all loop pages', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'grouped_add_to_cart_text',
							'label'    	=> __( 'Grouped Product', 'wootoolkit' ),
							'desc' => __( 'Changes the add to cart button text for grouped products on all loop pages', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'out_of_stock_add_to_cart_text',
							'label'    	=> __( 'Out of Stock Product', 'wootoolkit' ),
							'desc' => __( 'Changes the add to cart button text for out of stock products on all loop pages', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'loop_shop_per_page',
							'label'    	=> __( 'Products displayed per page', 'wootoolkit' ),
							'desc' => __( 'Changes the number of products displayed per page', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'loop_shop_columns',
							'label'    	=> __( 'Product columns displayed per page', 'wootoolkit' ),
							'desc' => __( 'Changes the number of columns displayed per page', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'woocommerce_product_thumbnails_columns',
							'label'    	=> __( 'Product thumbnail columns displayed', 'wootoolkit' ),
							'desc' => __( 'Changes the number of product thumbnail columns displayed', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'woocommerce_product_description_tab_title',
							'label'    	=> __( 'Product Description', 'wootoolkit' ),
							'desc' => __( 'Changes the Production Description tab title', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'woocommerce_product_additional_information_tab_title',
							'label'    	=> __( 'Additional Information', 'wootoolkit' ),
							'desc' => __( 'Changes the Additional Information tab title', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'woocommerce_product_description_heading',
							'label'    	=> __( 'Product Description', 'wootoolkit' ),
							'desc' => __( 'Changes the Product Description tab heading', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'woocommerce_product_additional_information_heading',
							'label'    	=> __( 'Additional Information', 'wootoolkit' ),
							'desc' 			=> __( 'Changes the Additional Information tab heading', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'single_add_to_cart_text',
							'label'    	=> __( 'All Product Types', 'wootoolkit' ),
							'desc' 			=> __( 'Changes the Add to Cart button text on the single product page for all product type', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'woocommerce_checkout_must_be_logged_in_message',
							'label'    	=> __( 'Must be logged in text', 'wootoolkit' ),
							'desc' => __( 'Changes the message displayed when a customer must be logged in to checkout', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'woocommerce_checkout_coupon_message',
							'label'    	=> __( 'Coupon text', 'wootoolkit' ),
							'desc' => __( 'Changes the message displayed if the coupon form is enabled on checkout', 'wootoolkit' ),
							'type'     	=> 'text',
							'desc'     	=> sprintf( '<code>%s ' . esc_attr( '<a href="#" class="showcoupon">%s</a>' ) . '</code>', 'Have a coupon?', 'Click here to enter your code' ),
						),
						array(
							'name'      => 'woocommerce_checkout_login_message',
							'label'    	=> __( 'Login text', 'wootoolkit' ),
							'desc' => __( 'Changes the message displayed if customers can login at checkout', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'woocommerce_create_account_default_checked',
							'label'    	=> __( 'Create Account checkbox default' ),
							'desc' => __( 'Control the default state for the Create Account checkbox', 'wootoolkit' ),
							'type'     	=> 'select',
							'options'  	=> array(
								'customizer_true'  => __( 'Checked', 'wootoolkit' ),
								'customizer_false' => __( 'Unchecked', 'wootoolkit' ),
							),
							'default'  	=> 'customizer_false',
						),
						array(
							'name'      => 'woocommerce_order_button_text',
							'label'    	=> __( 'Submit Order button', 'wootoolkit' ),
							'desc' => __( 'Changes the Place Order button text on checkout', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'woocommerce_countries_tax_or_vat',
							'label'    	=> __( 'Tax Label', 'wootoolkit' ),
							'desc' => __( 'Changes the Taxes label. Defaults to Tax for USA, VAT for European countries', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'woocommerce_countries_inc_tax_or_vat',
							'label'    	=> __( 'Including Tax Label', 'wootoolkit' ),
							'desc' 			=> __( 'Changes the Including Taxes label. Defaults to Inc. tax for USA, Inc. VAT for European countries', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'woocommerce_countries_ex_tax_or_vat',
							'label'    	=> __( 'Excluding Tax Label', 'wootoolkit' ),
							'desc' 			=> __( 'Changes the Excluding Taxes label. Defaults to Exc. tax for USA, Exc. VAT for European countries', 'wootoolkit' ),
							'type'     	=> 'text'
						),
						array(
							'name'      => 'woocommerce_placeholder_img_src',
							'label'    	=> __( 'Placeholder Image source', 'wootoolkit' ),
							'desc' => __( 'Change the default placeholder image by setting this to a valid image URL', 'wootoolkit' ),
							'type'     	=> 'text'
						),
          );
        return $fields;
    }
}