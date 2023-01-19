<?php
/**
 * Kit Name: Catalog Mode
 * Kit URI: http://wootoolkit.com
 * Kit Slug: wootoolkit-catalog
 * Description: Convert  your store into catalog mode by hiding add to cart button, price tag, ratings, reviews.
 * Version: 1.0.0
 * Author: Magnigenie
 * Author URI: http://magnigenie.com
 */

class WT_Catalog_Mode {

	private $file 	= ''; 	// Path of this file
    private $settings; 		// Settings object
	
	// Options
	private $catalog_mode;
	private $user_groups;
	private $woo_categories;
	private $remove_add_to_cart_button;
	private $add_custom_button;
	private $load_more_button_text;
	private $custom_button_type;
	private $custom_button_link;
	private $remove_price;

	private $catalog_on = false;
	
	function __construct() {
		
		require_once ( plugin_dir_path( __FILE__ ) . 'include/admin/settings.php' );
		
		$this->settings = new WT_Catalog_Settings();

		// Get Accordion Options
    	$catalog_settings = get_option( 'wootoolkit_catalog', 'none' );

    	if( is_array( $catalog_settings ) ) {
		
			// Assign options
			$this->catalog_mode 				= $catalog_settings['catalog_mode'];
			$this->user_groups 					= $catalog_settings['catalog_groups'];
			$this->woo_categories 				= $catalog_settings['categories'];
			$this->remove_add_to_cart_button 	= $catalog_settings['remove_add_to_cart_button'];
			$this->add_custom_button 			= $catalog_settings['add_custom_button'];
			$this->load_more_button_text 		= $catalog_settings['custom_button_text'];
			$this->custom_button_type 			= $catalog_settings['custom_button_type'];
			$this->custom_button_link 			= $catalog_settings['custom_button_link'];
			$this->remove_price 				= $catalog_settings['remove_price'];
		}
		
		if ( $this->catalog_mode == "on" ) {
			$this->configCatalog();
		}

		add_filter( 'kit_action_links_wootoolkit-catalog', array( $this, 'kit_settings_link' ) );
		
    }

	/**
	* Necessary options if catalog option is "on"
	*
	* @param void
	* @since 1.0.0
	*/
	public function wt_catalog_apply() {
		//check for user groups
		if( $this->catalog_on ) {

			if ( $this->remove_add_to_cart_button == "on" ) {
				$this->remove_add_to_cart_button();
			}

			if ( $this->remove_price == "on" ) {
				$this->remove_price();
			}
		}
	}
	
	/**
	* Remove add to cart button
	*
	* @param void
	* @since 1.0.0
	*/
	public function remove_add_to_cart_button() {
		
		if ( $this->add_custom_button=="on" ) {
			  add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'custom_button' ), 10 );
			  add_action( 'woocommerce_after_shop_loop_item', array( $this, 'set_up_template_add_to_cart' ), 1 );	
			  add_filter( 'woocommerce_get_price_html', array( $this, 'custom_button' ) );
			  add_filter( 'woocommerce_cart_item_price', array( $this, 'custom_button' ) );

		} else {
			remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
			add_action( 'woocommerce_after_shop_loop_item', array( $this, 'set_up_template_add_to_cart' ), 1 );
		}

		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 10 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'set_up_template_add_to_cart' ), 1 );

		//Remove add to cart from shop page for astra theme.

		add_filter('astra_woo_shop_product_structure' , 'call_back_add_to_cart_shop');
		//Remove add to cart from single product page.
		add_filter( 'astra_woo_single_product_structure' , 'call_back_add_to_cart' );
		function call_back_add_to_cart_shop(){
		    $structure = astra_get_option( 'shop-product-structure' );
		    $add_to_cart_key = array_search('add_cart', $structure);
		    unset($structure[$add_to_cart_key]);
		    return $structure;
		}

		function call_back_add_to_cart(){
		    $structure = astra_get_option( 'single-product-structure' );
		    $add_to_cart_key = array_search('add_cart', $structure);
		    unset($structure[$add_to_cart_key]);
		    return $structure;
		}
		
	}
	
	/**
	* Custom button in place of Add to Cart
	*
	* @param void
	* @since 1.0.0
	*/
	public function custom_button() {


		$this->load_more_button_text= $this->load_more_button_text == ""?__( 'More', 'wootoolkit' ):$this->load_more_button_text;
		
		if ($this->custom_button_type == "custom_button_type_read_more" ) {
			global $product;
			echo ' <a id="wootoolkit_custom_button" href="' . esc_url( $product->get_permalink( $product->id ) ) . '" class="single_add_to_cart_button button alt">'.$this->load_more_button_text.'</a>
				  </a>';
		} else {
			echo ' <a id="wootoolkit_custom_button" href="' . $this->custom_button_link . '" class="single_add_to_cart_button button alt">'.$this->load_more_button_text.'</a>
				  </a>';
		}
	
	}
	
	/**
	* Template set up for add to cart
	*
	* @param void
	* @since 1.0.0
	*/
	public function set_up_template_add_to_cart() {
		
		if( $this->shouldExcludeCategory() ) {

			remove_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'custom_button' ), 10 );
			add_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
			add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
			
		}else{
			//in catalog
			if ( $this->add_custom_button != "on" ) {
				remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
			} else {
				add_filter('woocommerce_loop_add_to_cart_link', array( $this, 'custom_button' ), 10);
				// add_filter( 'astra_global_btn_woo_comp' , array( $this, 'custom_button' ), 10 );

			}
		}
	}
	
	/**
	* Remove price from procuts as per settings
	*
	* @param void
	* @since 1.0.0
	*/
	public function remove_price() {
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'set_up_template_price' ), 5 );
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'set_up_template_price' ), 1 );
		function patricks_custom_variation_price( $price, $product ) {

			$target_product_types = array( 
				'variable' 
			);

			if ( in_array ( $product->product_type, $target_product_types ) ) {
				// if variable product return and empty string
				return '';
			}

			// return normal price
			return '';
		}
		add_filter('woocommerce_get_price_html', 'patricks_custom_variation_price', 10, 2);
	}
	
	/**
	* Set up template for price
	*
	* @param void
	* @since 1.0.0
	*/
	public function set_up_template_price() {

		if( $this->shouldExcludeCategory() ) {
			add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
			add_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
		} else {
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
			remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );	
		}
	}
	
	/**
	* Excluding categories as per settings
	*
	* @param void
	* @since 1.0.0
	*/
	public function shouldExcludeCategory() {
		
		global $product;

		//if all categories selected get out of here
		if( is_array( $this->woo_categories ) && in_array( "all", $this->woo_categories ) ) {
			return false;
		}

		//get terms for each product
		$terms = get_the_terms( $product->id, 'product_cat' );
		if($terms){
			foreach($terms as $term){
				$cat_id = $term->term_id;
				if( is_array( $this->woo_categories ) && in_array( $cat_id, $this->woo_categories ) ) {
					return false;
				}
			}
		}
		
		return true;
    }

    /**
	* Catalog Configurations
	*
	* @param void
	* @since 1.0.0
	*/
	public function configCatalog() {

		if( $this->user_groups == "registered_users" ) {
			if ( is_user_logged_in() ) {
				$this->catalog_on = true;
			}
		}

		if( $this->user_groups == "non_registered_users" ) {
			if ( !is_user_logged_in() ) {
				$this->catalog_on = true;
			}
		}
		
		if( $this->user_groups == "all" ) {
				$this->catalog_on = true;
		}
	
		$this->wt_catalog_apply();
	
	}

	/**
	* Link to settings page for this kit
	*
	* @param void
	* @since 1.0.0
	*/
	public function kit_settings_link( $links ) {
        $wootoolkit_settings_page = array( '<a href="' . admin_url( 'admin.php?page=woo-toolkit-settings&tab=wootoolkit_catalog' ) . '">Settings</a>',
        );
        return array_merge( $links, $wootoolkit_settings_page );
    }
}

$woocatalog = new WT_Catalog_Mode();