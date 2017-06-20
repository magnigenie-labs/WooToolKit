<?PHP
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class WT_Products_Per_Page_Front_End.
 *
 * Handles all front end related business.
 *
 * @class		WT_Products_Per_Page_Front_End
 * @version		1.0.0
 */
class WT_Products_Per_Page_Front_End {

	private $dp_location;

	private $dp_options;

	private $default_ppp;

	private $shop_columns;

	private $ppp_method;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// Get products per page options
		$ppp_settings = get_option( 'wootoolkit_products_per_page', 'none' );

		if( is_array( $ppp_settings ) ) {

			// Assign Options
			$this->dp_location 	= $ppp_settings['wtpp_dropdown_location'];
			$this->dp_options 	= $ppp_settings['wtpp_dropdown_options'];
			$this->default_ppp 	= $ppp_settings['wtpp_default_ppp'];
			$this->shop_columns = $ppp_settings['wtpp_shop_columns'];
			$this->ppp_method 	= $ppp_settings['wtpp_method'];
		}

		if ( $this->dp_location == 'top' ) :
			add_action( 'woocommerce_before_shop_loop', array( $this, 'products_per_page_dropdown' ), 25 );
		elseif ( $this->dp_location == 'bottom' ) :
			add_action( 'woocommerce_after_shop_loop', array( $this, 'products_per_page_dropdown' ), 25 );
		elseif ( $this->dp_location == 'topbottom' ):
			add_action( 'woocommerce_before_shop_loop', array( $this, 'products_per_page_dropdown' ), 25 );
			add_action( 'woocommerce_after_shop_loop', array( $this, 'products_per_page_dropdown' ), 25 );
		endif;

		// Add filter for product columns
		add_filter( 'loop_shop_columns', array( $this, 'loop_shop_columns' ) );

		// Custom number of products per page
		add_filter( 'loop_shop_per_page', array( $this, 'loop_shop_per_page' ) );

		// Get the right amount of products from the DB
		add_action( 'woocommerce_product_query', array( $this, 'woocommerce_product_query' ), 2, 50 );

		// Set cookie so PPP will be saved
		add_action( 'init', array( $this, 'set_customer_session' ), 10 );

		// Check if ppp form is fired
		add_action( 'init', array( $this, 'products_per_page_action' ) );

	}


	/**
	 * Display drop down.
	 *
	 * Display the drop down front end to the user to choose
	 * the number of products per page.
	 *
	 * @since 1.0.0
	 */
	public function products_per_page_dropdown() {

		$options = get_option('wootoolkit_products_per_page');
		if( !empty($options['enabled']) && $options['enabled'] == 'on' ) :
			global $wp_query;

			$action = '';
			$cat 	= '';
			$cat 	= $wp_query->get_queried_object();
			$method = in_array( $this->ppp_method, array( 'post', 'get' ) ) ? $this->ppp_method : 'post';

			// Set the products per page options (e.g. 4, 8, 12)
			$products_per_page_options = explode( ' ', apply_filters( 'wppp_products_per_page', $this->dp_options ) );

			// Set action url if option behaviour is true
			// Paste QUERY string after for filter and orderby support
			$query_string = ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . add_query_arg( array( 'items' => false ), $_SERVER['QUERY_STRING'] ) : null;

			$action = get_permalink( wc_get_page_id( 'shop' ) ) . $query_string;

			// Only show on product categories
			if ( ! woocommerce_products_will_display() ) :
				return;
			endif;

			do_action( 'wppp_before_dropdown_form' );

		?>

			<form method="<?php echo esc_attr( $method ); ?>" action="<?php echo esc_url( $action ); ?>" style='float: right; margin-left: 5px;' class="form-wppp-select products-per-page"><?php

				do_action( 'wppp_before_dropdown' );

			?><select name="items" onchange="this.form.submit()" class="select wppp-select"><?php

				foreach( $products_per_page_options as $key => $value ) :

					?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $this->loop_shop_per_page() ); ?>><?php
						$ppp_text = apply_filters( 'wppp_ppp_text', __( '%s products per page', 'woocommerce-products-per-page' ), $value );
						esc_html( printf( $ppp_text, $value == -1 ? __( 'All', 'woocommerce-products-per-page' ) : $value ) ); // Set to 'All' when value is 0
					?></option><?php

				endforeach;

			?></select><?php

			// Keep query string vars intact
			foreach ( $_GET as $key => $val ) :

				if ( 'items' === $key || 'submit' === $key ) :
					continue;
				endif;
				if ( is_array( $val ) ) :
					foreach( $val as $inner_val ) :
						?><input type="hidden" name="<?php echo esc_attr( $key ); ?>[]" value="<?php echo esc_attr( $inner_val ); ?>" /><?php
					endforeach;
				else :
					?><input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val ); ?>" /><?php
				endif;
			endforeach;

			do_action( 'wppp_after_dropdown' );

		?>

		</form>

		<?php do_action( 'wppp_after_dropdown_form' );
		endif;
	}


	/**
	 * Shop columns.
	 *
	 * Set number of columns (products per row).
	 *
	 * @since 1.0.0
	 *
	 * @param int $columns Current number of shop columns.
	 * @return int Number of columns.
	 */
	public function loop_shop_columns( $columns ) {

		if ( ( $shop_columns = $this->shop_columns ) > 0 ) :
			$columns = $shop_columns;
		endif;

		return $columns;

	}


	/**
	 * Per page hook.
	 *
	 * Return the number of products per page to the hook
	 *
	 * @since 1.0.0
	 *
	 * @return int Products per page.
	 */
	public function loop_shop_per_page() {

		if ( isset( $_REQUEST['wt_items'] ) ) :
			return intval( $_REQUEST['wt_items'] );
		elseif ( isset( $_REQUEST['items'] ) ) :
			return intval( $_REQUEST['items'] );
		elseif ( WC()->session->__isset( 'products_per_page' ) ) :
			return intval( WC()->session->__get( 'products_per_page' ) );
		else :
			return intval( $this->default_ppp );
		endif;

	}

	/**
	 * Posts per page.
	 *
	 * Set the number of posts per page on a hard way, build in fix for many themes who override the offical loop_shop_per_page filter.
	 *
	 * @since 1.0.0
	 *
	 * @param	object 	$q		Existing query object.
	 * @param	object	$class	Class object.
	 * @return 	object 			Modified query object.
	 */
	public function woocommerce_product_query( $q, $class ) {

		if ( function_exists( 'woocommerce_products_will_display' ) && woocommerce_products_will_display() && $q->is_main_query() ) :
			$q->set( 'posts_per_page', $this->loop_shop_per_page() );
		endif;

	}


	/**
	 * Initialize session.
	 *
	 * @since 1.0.0
	 */
	public function set_customer_session() {

		if ( WC()->version > '2.1' && ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' ) ) :
			WC()->session->set_customer_session_cookie( true );
		endif;

	}


	/**
	 * PPP action.
	 *
	 * Set the number of products per page when the customer
	 * changes the amount in the drop down.
	 *
	 * @since 1.0.0
	 */
	public function products_per_page_action() {

		if ( isset( $_REQUEST['wt_items'] ) ) :
			WC()->session->set( 'products_per_page', intval( $_REQUEST['wt_items'] ) );
		elseif ( isset( $_REQUEST['items'] ) ) :
			WC()->session->set( 'products_per_page', intval( $_REQUEST['items'] ) );
		endif;
	}
}