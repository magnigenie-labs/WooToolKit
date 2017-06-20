<?php
/**
 * Kit Name: WooCommerce Customizer
 * Kit URI: http://wootoolkit.com
 * Kit Slug: woocommerce-customizer
 * Description: Adds ability to customize different WooCommerce options.
 * Version: 1.0.0
 * Author: Magnigenie
 * Author URI: http://magnigenie.com
 */

defined( 'ABSPATH' ) or exit;

class WooToolkit_Customizer {


	/** @var \WooToolkit_Customizer single instance of this plugin */
	protected static $instance;

	/** @var \Customizer_Admin_Settings instance */
	public $admin_settings;

	/** var array the active filters */
	public $filters;


	/**
	 * Initializes the plugin
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$options = get_option('wootoolkit_woo_customizer');
		if( !empty($options['enabled']) && $options['enabled'] == 'on' ) :
			// load translation
			add_action( 'init', array( $this, 'load_customizations' ) );
		endif;

		// Add settings page link
		add_filter( 'kit_action_links_woocommerce-customizer', array( $this, 'plugin_settings_link' ) );

		// admin
		if ( is_admin() && !defined( 'DOING_AJAX' ) ) {

			require_once plugin_dir_path( __FILE__ ) . 'includes/class-admin-customizer-settings.php';
            $this->admin_settings = new WT_Customizer_Admin_Settings();

		}
	}

	/**
	 * Handle localization, WPML compatible
	 *
	 * @since 1.0.0
	 */
	public function load_customizations() {

		// load filter names and values
		$this->filters = get_option( 'wootoolkit_woo_customizer');

		// only add filters if some exist
		if ( !empty( $this->filters ) && is_array($this->filters) ) {

			foreach ( $this->filters as $filter_name => $filter_value ) {

				// Do not apply filter is value is blank
				if( $filter_value == '' ) continue;

				// WC 2.1 changed the add to cart text filter signatures so conditionally add the new filters
				if ( false !== strpos( $filter_name, 'add_to_cart_text' ) ) {

					if ( $filter_name == 'single_add_to_cart_text' ) {

						add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'customize_single_add_to_cart_text' ) );

					} else {

						add_filter( 'woocommerce_product_add_to_cart_text', array( $this, 'customize_add_to_cart_text' ), 10, 2 );
					}

				} else {

					add_filter( $filter_name, array( $this, 'customize' ) );
				}
			}
		}
	}


	/** Frontend methods ******************************************************/


	/**
	 * Add hook to selected filters
	 *
	 * @since 1.0.0
	 * @return string $filter_value value to use for selected hook
	 */
	public function customize() {

		$current_filter = current_filter();

		if ( !empty( $this->filters[ $current_filter ] ) ) {

			if ( 'customizer_true' === $this->filters[ $current_filter] || 'customizer_true' === $this->filters[ $current_filter] ) {

				// helper to return a pure boolean value
				return 'customizer_true' === $this->filters[ $current_filter ];

			} else {

				return $this->filters[ $current_filter ];
			}

		} else {

			return '';
		}

		// no need to return a value passed in, because if a filter is set, it's designed to only return that value
	}


	/**
	 * Apply the single add to cart button text customization
	 *
	 * @since 1.0.0
	 */
	public function customize_single_add_to_cart_text() {

		return $this->filters['single_add_to_cart_text'];
	}


	/**
	 * Apply the shop loop add to cart button text customization
	 *
	 * @since 1.0.0
	 * @param string $text add to cart text
	 * @param WC_Product $product product object
	 * @return string modified add to cart text
	 */
	public function customize_add_to_cart_text( $text, $product ) {

		// out of stock add to cart text
		if ( isset( $this->filters['out_of_stock_add_to_cart_text'] ) && ! $product->is_in_stock() ) {

			return $this->filters['out_of_stock_add_to_cart_text'];
		}

		if ( isset( $this->filters['add_to_cart_text'] ) && $product->is_type( 'simple' ) ) {

			// simple add to cart text
			return $this->filters['add_to_cart_text'];

		} elseif ( isset( $this->filters['variable_add_to_cart_text'] ) && $product->is_type( 'variable' ) )  {

			// variable add to cart text
			return $this->filters['variable_add_to_cart_text'];

		} elseif ( isset( $this->filters['grouped_add_to_cart_text'] ) && $product->is_type( 'grouped' ) ) {

			// grouped add to cart text
			return $this->filters['grouped_add_to_cart_text'];

		} elseif( isset( $this->filters['external_add_to_cart_text'] ) && $product->is_type( 'external' ) ) {

			// external add to cart text
			return $this->filters['external_add_to_cart_text'];
		}

		return $text;
	}


	/** Helper methods ******************************************************/


	/**
	 * Main Customizer Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.0.0
	 * @see wt_customizer()
	 * @return \WooToolkit_Customizer
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function plugin_settings_link( $links ) {
        $wootoolkit_settings_page = array( '<a href="' . admin_url( 'admin.php?page=woo-toolkit-settings&tab=wootoolkit_woo_customizer' ) . '">Settings</a>',
        );
        return array_merge( $links, $wootoolkit_settings_page );
    }
}


/**
 * Returns the One True Instance of Customizer
 *
 * @since 1.0.0
 * @return \WooToolkit_Customizer
 */
function wt_customizer() {
	return WooToolkit_Customizer::instance();
}


/**
 * The WooToolkit_Customizer global object
 * @deprecated 1.0.0
 * @name $wt_customizer
 * @global WooToolkit_Customizer $GLOBALS['wt_customizer']
 */
$GLOBALS['wt_customizer'] = wt_customizer();