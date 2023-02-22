<?php
/**
 * Kit Name: Product Sorting Options
 * Kit URI: http://wootoolkit.com
 * Kit Slug: product-sorting-options
 * Description: Add new sorting options to WooCommerce shop page and also renmae existing options.
 * Version: 1.0.0
 * Author: Magnigenie
 * Author URI: http://magnigenie.com
 */

defined( 'ABSPATH' ) or exit;

// Make sure we're loaded after WC and fire it up!
function init_wt_extra_sorting_options() {
	wt_extra_sorting_options();
}
add_action( 'init', 'init_wt_extra_sorting_options' );


/**
 * Plugin Description
 *
 * Rename default sorting option - helpful if custom sorting is used.
 * Adds sorting by name, on sale, featured, availability, and random to shop pages.
 *
 */


class WT_Extra_Sorting_Options {

	/** @var WT_Extra_Sorting_Options single instance of this plugin */
	protected static $instance;

	public function __construct() {

		$this->init();

		$options = get_option('wootoolkit_sorting_options');
		// settings page link
		add_filter( 'kit_action_links_product-sorting-options', array( $this, 'plugin_settings_link' ) );		

		if( !empty($options['enabled']) && $options['enabled'] == 'on' ) :
			// modify product sorting settings
			add_filter( 'woocommerce_catalog_orderby', array( $this, 'modify_sorting_settings' ) );

			// add new sorting options to orderby dropdown
			add_filter( 'woocommerce_default_catalog_orderby_options', array( $this, 'modify_sorting_settings' ) );

			// add new product sorting arguments
			add_filter( 'woocommerce_get_catalog_ordering_args', array( $this, 'add_new_shop_ordering_args' ) );
		endif;

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
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-admin-settings.php';
			$this->admin_settings = new WT_Sorting_Options();

        endif;

    }


	/** Helper methods */

	/**
	 * Main Extra Sorting Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.0.0
	 * @see wt_extra_sorting_options()
	 * @return WT_Extra_Sorting_Options
	 */
	public static function instance() {
    	
    	if ( is_null( self::$instance ) ) {
       		self::$instance = new self();
   		}
    	return self::$instance;
	}

	/**
	 * Change "Default Sorting" to custom name and add new sorting options; added to admin + frontend dropdown
	 *
	 * @since 1.0.0
	 */
	public function modify_sorting_settings( $sortby ) {

		$ec_settings = get_option( 'wootoolkit_sorting_options', 'none' );

		if( is_array( $ec_settings ) ):

			$new_default_name = $ec_settings['wc_rename_default_sorting'];
			$new_sorting_options = $ec_settings['wc_extra_product_sorting_options'];

			if ( $new_default_name ) {
				$sortby = str_replace( "Default sorting", $new_default_name, $sortby );
			}

			foreach( $new_sorting_options as $option ) {

				switch ( $option ) {

					case 'alphabetical':
						$sortby['alphabetical']   = __( 'Sort by name: A to Z', 'woocommerce-extra-product-sorting-options' );
						break;

					case 'reverse_alpha':
						$sortby['reverse_alpha']  = __( 'Sort by name: Z to A', 'woocommerce-extra-product-sorting-options' );
						break;

					case 'by_stock':
						$sortby['by_stock']       = __( 'Sort by availability', 'woocommerce-extra-product-sorting-options' );
						break;

					case 'on_sale_first':
						$sortby['on_sale_first']  = __( 'Show sale items first', 'woocommerce-extra-product-sorting-options' );
						break;

					case 'featured_first':
						$sortby['featured_first'] = __( 'Show featured items first', 'woocommerce-extra-product-sorting-options' );
						break;
				}
			}

		endif;

		return $sortby;
	}


	/**
	 * Add sorting option to WC sorting arguments
	 *
	 * @since 1.0.0
	*/
	public function add_new_shop_ordering_args( $sort_args ) {

		// If we have the orderby via URL, let's pass it in
		// This means we're on a shop / archive, so if we don't have it, use the default
		if ( isset( $_GET['orderby'] ) ) {
			$orderby_value = wc_clean( $_GET['orderby'] );
		} else {
			$orderby_value = apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby' ) );
		}

		// Since a shortcode can be used on a non-WC page, we won't have $_GET['orderby']
		// Grab it from the passed in sorting args instead for non-WC pages
		// Don't use this on WC pages since it breaks the default
		if ( ! is_woocommerce() && isset( $sort_args['orderby'] ) ) {
			$orderby_value = $sort_args['orderby'];
		}

		$fallback       = apply_filters( 'wt_extra_sorting_options_fallback', 'title', $orderby_value );
		$fallback_order = apply_filters( 'wt_extra_sorting_options_fallback_order', 'ASC', $orderby_value );

		switch( $orderby_value ) {

			case 'alphabetical':
				$sort_args['orderby'] = 'title';
				$sort_args['order']   = 'asc';
				break;

			case 'reverse_alpha':
				$sort_args['orderby']  = 'title';
				$sort_args['order']    = 'desc';
				$sort_args['meta_key'] = '';
				break;

			case 'by_stock':
				$sort_args['orderby']  = array( 'meta_value_num' => 'DESC', $fallback => $fallback_order );
				$sort_args['meta_key'] = '_stock';
				break;


			case 'on_sale_first':
				$sort_args['orderby']  = array( 'meta_value_num' => 'DESC', $fallback => $fallback_order );
				$sort_args['meta_key'] = '_sale_price';
				break;

			case 'featured_first':
				$sort_args['orderby']  = array( 'meta_value' => 'DESC', $fallback => $fallback_order );
				$sort_args['meta_key'] = '_featured';
				break;

		}

		return $sort_args;
	}

	public function plugin_settings_link( $links ) {
        $wootoolkit_settings_page = array( '<a href="' . admin_url( 'admin.php?page=woo-toolkit-settings&tab=wootoolkit_sorting_options' ) . '">Settings</a>',
        );
        return array_merge( $links, $wootoolkit_settings_page );
    }

} // end \WT_Extra_Sorting_Options class


/**
 * Returns the One True Instance of WC Extra Sorting
 *
 * @since 1.0.0
 * @return WT_Extra_Sorting_Options
 */
function wt_extra_sorting_options() {
    return WT_Extra_Sorting_Options::instance();
}