<?php
/**
 * Kit Name: Sequential Order Numbers
 * Kit URI: http://wootoolkit.com
 * Kit Slug: sequential-order-numbers
 * Description: Adds option to have sequential order numbers for WooCommerce orders
 * Version: 1.0.0
 * Author: Magnigenie
 * Author URI: http://magnigenie.com
 */

defined( 'ABSPATH' ) or exit;

class WT_Seq_Order_Number {

	/** @var \WT_Seq_Order_Number single instance of this plugin */
	protected static $instance;

	/**
	 * Construct the plugin
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		add_action( 'init', array( $this, 'initialize' ) );
	}


	/**
	 * Initialize the plugin, bailing if any required conditions are not met,
	 * including minimum WooCommerce version
	 *
	 * @since 1.0.0
	 */
	public function initialize() {

		// Set the custom order number on the new order.  we hook into wp_insert_post for orders which are created
		// from the frontend, and we hook into woocommerce_process_shop_order_meta for admin-created orders
		add_action( 'wp_insert_post', array( $this, 'set_sequential_order_number' ), 10, 2 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'set_sequential_order_number' ), 10, 2 );

		// return our custom order number for display
		add_filter( 'woocommerce_order_number', array( $this, 'get_order_number' ), 10, 2 );

		// order tracking page search by order number
		add_filter( 'woocommerce_shortcode_order_tracking_order_id', array( $this, 'find_order_by_order_number' ) );

		// WC Subscriptions support
		if ( self::is_wc_subscriptions_version_gte_2_0() ) {
			add_filter( 'wcs_renewal_order_meta_query', 
				array( $this, 'subscriptions_remove_renewal_order_meta' ) );
			add_filter( 'wcs_renewal_order_created', 
				array( $this, 'subscriptions_set_sequential_order_number' ), 10, 2 );
		} else {
			add_filter( 'woocommerce_subscriptions_renewal_order_meta_query', 
				array( $this, 'subscriptions_remove_renewal_order_meta' ) );
			add_action( 'woocommerce_subscriptions_renewal_order_created', 
				array( $this, 'subscriptions_set_sequential_order_number' ), 10, 2 );
		}

		if ( is_admin() ) {
			add_filter( 'request', 
				array( $this, 'woocommerce_custom_shop_order_orderby' ), 20 );
			add_filter( 'woocommerce_shop_order_search_fields', 
				array( $this, 'custom_search_fields' ) );

			// sort by underlying _order_number on the Pre-Orders table
			add_filter( 'wc_pre_orders_edit_pre_orders_request', 
				array( $this, 'custom_orderby' ) );
			add_filter( 'wc_pre_orders_search_fields', 
				array( $this, 'custom_search_fields' ) );
		}
	}

	/**
	 * Search for an order with order_number $order_number
	 *
	 * @param string $order_number order number to search for
	 * @return int post_id for the order identified by $order_number, or 0
	 */
	public function find_order_by_order_number( $order_number ) {

		// search for the order by custom order number
		$query_args = array(
			'numberposts' => 1,
			'meta_key'    => '_order_number',
			'meta_value'  => $order_number,
			'post_type'   => 'shop_order',
			'post_status' => 'any',
			'fields'      => 'ids',
		);

		$posts            = get_posts( $query_args );
		list( $order_id ) = ! empty( $posts ) ? $posts : null;

		// order was found
		if ( $order_id !== null ) {
			return $order_id;
		}

		// if we didn't find the order, then it may be that this plugin was disabled and an order was placed in the interim
		$order = wc_get_order( $order_number );

		if ( ! $order ) {
			return 0;
		}

		if ( $order->order_number ) {
			// _order_number was set, so this is not an old order, it's a new one that just happened to have post_id that matched the searched-for order_number
			return 0;
		}

		return $order->id;
	}


	/**
	 * Set the _order_number field for the newly created order
	 *
	 * @param int $post_id post identifier
	 * @param WP_Post $post post object
	 */
	public function set_sequential_order_number( $post_id, $post ) {
		global $wpdb;

		if ( 'shop_order' === $post->post_type && 'auto-draft' !== $post->post_status ) {

			$order_number = get_post_meta( $post_id, '_order_number', true );

			if ( '' === $order_number ) {

				// attempt the query up to 3 times for a much higher success rate if it fails (due to Deadlock)
				$success = false;
				for ( $i = 0; $i < 3 && ! $success; $i++ ) {

					// this seems to me like the safest way to avoid order number clashes
					$query = $wpdb->prepare( "
						INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
						SELECT %d, '_order_number', IF( MAX( CAST( meta_value as UNSIGNED ) ) IS NULL, 1, MAX( CAST( meta_value as UNSIGNED ) ) + 1 )
							FROM {$wpdb->postmeta}
							WHERE meta_key='_order_number'",
						$post_id );

					$success = $wpdb->query( $query );
				}
			}
		}
	}


	/**
	 * Filter to return our _order_number field rather than the post ID,
	 * for display.
	 *
	 * @param string $order_number the order id with a leading hash
	 * @param WC_Order $order the order object
	 * @return string custom order number
	 */
	public function get_order_number( $order_number, $order ) {

		if ( $order->get_id() ) {
			return $order->get_id();
		}

		return $order_number;
	}


	/** Admin filters ******************************************************/


	/**
	 * Admin order table orderby ID operates on our meta _order_number
	 *
	 * @param array $vars associative array of orderby parameteres
	 * @return array associative array of orderby parameteres
	 */
	public function woocommerce_custom_shop_order_orderby( $vars ) {
		global $typenow, $wp_query;

		if ( 'shop_order' === $typenow ) {
			return $vars;
		}

		return $this->custom_orderby( $vars );
	}


	/**
	 * Mofifies the given $args argument to sort on our meta integral _order_number
	 *
	 * @since 1.0.0
	 * @param array $args associative array of orderby parameteres
	 * @return array associative array of orderby parameteres
	 */
	public function custom_orderby( $args ) {

		// Sorting
		if ( isset( $args['orderby'] ) && 'ID' == $args['orderby'] ) {

			$args = array_merge( $args, array(
				'meta_key' => '_order_number',  // sort on numerical portion for better results
				'orderby'  => 'meta_value_num',
			) );
		}

		return $args;
	}


	/**
	 * Add our custom _order_number to the set of search fields so that
	 * the admin search functionality is maintained
	 *
	 * @param array $search_fields array of post meta fields to search by
	 * @return array of post meta fields to search by
	 */
	public function custom_search_fields( $search_fields ) {

		array_push( $search_fields, '_order_number' );

		return $search_fields;
	}


	/** 3rd Party Plugin Support ******************************************************/


	/**
	 * Helper method to get the version of WooCommerce Subscriptions
	 *
	 * @since 1.0.0
	 * @return string|null WC_Subscriptions version number or null if not found
	 */
	protected static function get_wc_subscriptions_version() {
		return class_exists( 'WC_Subscriptions' ) && ! empty( WC_Subscriptions::$version ) ? WC_Subscriptions::$version : null;
	}


	/**
	 * Returns true if the installed version of WooCommerce Subscriptions is 2.0.0 or greater
	 *
	 * @since 1.0.0
	 * @return boolean
	 */
	protected static function is_wc_subscriptions_version_gte_2_0() {
		return self::get_wc_subscriptions_version() && version_compare( self::get_wc_subscriptions_version(), '2.0-beta-1', '>=' );
	}

	/**
	 * Sets an order number on a subscriptions-created order
	 *
	 * @since 1.0.0
	 * @param WC_Order $renewal_order the new renewal order object
	 * @param WC_Order $original_order the original order object
	 */
	public function subscriptions_set_sequential_order_number( $renewal_order, $original_order ) {

		$order_post = get_post( $renewal_order->id );
		$this->set_sequential_order_number( $order_post->ID, $order_post );

		// this callback needs to return the renewal order
		if ( self::is_wc_subscriptions_version_gte_2_0() ) {
			return $renewal_order;
		}
	}

	/**
	 * Don't copy over order number meta when creating a parent or child renewal order
	 *
	 * Prevents unnecessary order meta from polluting parent renewal orders,
	 * and set order number for subscription orders
	 *
	 * @since 1.0.0
	 * @param array $order_meta_query query for pulling the metadata
	 * @return string
	 */
	public function subscriptions_remove_renewal_order_meta( $order_meta_query ) {
		return $order_meta_query . " AND meta_key NOT IN ( '_order_number' )";
	}


	/** Helper Methods ******************************************************/


	/**
	 * Main Sequential Order Numbers Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.0.0
	 * @see wt_sequential_order_numbers()
	 * @return \WT_Seq_Order_Number
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

}

/**
 * Returns the One True Instance of Sequential Order Numbers
 *
 * @since 1.0.0
 * @return \WT_Seq_Order_Number
 */
function wt_sequential_order_numbers() {
	return WT_Seq_Order_Number::instance();
}


/**
 * The WT_Seq_Order_Number global object
 * @deprecated 1.0.0
 * @name $wt_seq_order_number
 * @global WT_Seq_Order_Number $GLOBALS['wt_seq_order_number']
 */
$GLOBALS['wt_seq_order_number'] = wt_sequential_order_numbers();