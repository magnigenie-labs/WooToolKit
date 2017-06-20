<?php
/**
 * Kit Name: Custom Product Tabs
 * Kit URI: http://wootoolkit.com
 * Kit Slug: custom-product-tabs
 * Description: Add a custom tab on WooCommerce products.
 * Version: 1.0.0
 * Author: Magnigenie
 * Author URI: http://magnigenie.com
 */

defined( 'ABSPATH' ) or exit;

class WT_Custom_Tabs {

	private $tab_data = false;

	/** @var WT_Custom_Tabs single instance of this plugin */
	protected static $instance;

	/**
	 * Gets things started by adding an action to initialize this plugin once
	 * WooCommerce is known to be active and initialized
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'kit_initialization' ) );
	}

	/**
	 * Intialization of the kit
	 *
	 * @since 1.0.0
	 */
	public function kit_initialization() {

		// backend stuff
		add_action( 'woocommerce_product_write_panel_tabs', array( $this, 'product_write_panel_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'product_write_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'product_save_data' ), 10, 2 );

		// frontend stuff
		add_filter( 'woocommerce_product_tabs', array( $this, 'add_custom_product_tabs' ) );

		// allow the use of shortcodes within the tab content
		add_filter( 'woocommerce_custom_product_tabs_content', 'do_shortcode' );
	}


	/* Frontend Option */

	/**
	 * Add the custom product tab
	 *
	 * @since 1.0.0
	 * @param array $tabs array representing the product tabs
	 * @return array representing the product tabs
	 */
	public function add_custom_product_tabs( $tabs ) {
		global $product;

		if ( $this->product_has_custom_tabs( $product ) ) {
			foreach ( $this->tab_data as $tab ) {
				$tab_title = __( $tab['title'], 'woocommerce-custom-product-tabs' );
				$tabs[ $tab['id'] ] = array(
					'title'    => apply_filters( 'woocommerce_custom_product_tabs_title', $tab_title, $product, $this ),
					'priority' => 25,
					'callback' => array( $this, 'custom_product_tabs_panel_content' ),
					'content'  => $tab['content'],  // custom field
				);
			}
		}

		return $tabs;
	}


	/**
	 * Render the custom product tab panel content for the given $tab
	 *
	 * $tab structure:
	 * Array(
	 *   'title'    => (string) Tab title,
	 *   'priority' => (string) Tab priority,
	 *   'callback' => (mixed) callback function,
	 *   'id'       => (int) tab post identifier,
	 *   'content'  => (sring) tab content,
	 * )
	 *
	 * @param string $key tab key
	 * @param array $tab tab data
	 *
	 * @param array $tab the tab
	 */
	public function custom_product_tabs_panel_content( $key, $tab ) {

		// allow shortcodes to function
		$content = apply_filters( 'the_content', $tab['content'] );
		$content = str_replace( ']]>', ']]&gt;', $content );

		echo apply_filters( 'woocommerce_custom_product_tabs_lite_heading', '<h2>' . $tab['title'] . '</h2>', $tab );
		echo apply_filters( 'woocommerce_custom_product_tabs_content', $content, $tab );
	}


	/* Admin Settings */

	/**
	 * Adds a new tab to the Product Data postbox in the admin product interface
	 */
	public function product_write_panel_tab() {
		
		echo "<li class=\"product_tabs_lite_tab\"><a href=\"#wootoolkit_custom_product_tabs\"><span>" . __( 'Custom Tab', 'woocommerce-custom-product-tabs' ) . "</span></a></li>";
	}


	/**
	 * Adds the panel to the Product Data postbox in the product interface
	 */
	public function product_write_panel() {
		
		global $post;

		// pull the custom tab data out of the database
		$tab_data = maybe_unserialize( get_post_meta( $post->ID, 'frs_woo_product_tabs', true ) );

		if ( empty( $tab_data ) ) {
			$tab_data[] = array( 'title' => '', 'content' => '' );
		}


		foreach ( $tab_data as $tab ) {
			
			// display the custom tab panel
			echo '<div id="wootoolkit_custom_product_tabs" class="panel wc-metaboxes-wrapper woocommerce_options_panel">';

			woocommerce_wp_text_input( array( 'id' => '_wt_custom_product_tabs_tab_title', 'label' => __( 'Tab Title', 'woocommerce-custom-product-tabs' ), 'description' => __( 'Required for tab to be visible', 'woocommerce-custom-product-tabs' ), 'value' => $tab['title'] ) );

			$this->woocommerce_wp_textarea_input( array( 'id' => '_wt_custom_product_tabs_tab_content', 'label' => __( 'Content', 'woocommerce-custom-product-tabs' ), 'placeholder' => __( 'HTML and text to display.', 'woocommerce-custom-product-tabs' ), 'value' => $tab['content'], 'style' => 'width:70%;height:21.5em;' ) );

			echo '</div>';
		}
	}


	/**
	 * Saves the data inputed into the product boxes, as post meta data
	 * identified by the name 'frs_woo_product_tabs'
	 *
	 * @param int $post_id the post (product) identifier
	 * @param stdClass $post the post (product)
	 */
	public function product_save_data( $post_id, $post ) {

		$tab_title = stripslashes( $_POST['_wt_custom_product_tabs_tab_title'] );
		$tab_content = stripslashes( $_POST['_wt_custom_product_tabs_tab_content'] );

		if ( empty( $tab_title ) && empty( $tab_content ) && get_post_meta( $post_id, 'frs_woo_product_tabs', true ) ) {
			
			// clean up if the custom tabs are removed
			delete_post_meta( $post_id, 'frs_woo_product_tabs' );

		} elseif ( ! empty( $tab_title ) || ! empty( $tab_content ) ) {
			
			$tab_data = array();

			$tab_id = '';

			if ( $tab_title ) {
				
				if ( strlen( $tab_title ) != strlen( utf8_encode( $tab_title ) ) ) {
					
					// can't have titles with utf8 characters as it breaks the tab-switching javascript
					$tab_id = "tab-custom";

				} else {
					
					// convert the tab title into an id string
					$tab_id = strtolower( $tab_title );
					$tab_id = preg_replace( "/[^\w\s]/", '', $tab_id );

					// remove non-alphas, numbers, underscores or whitespace
					$tab_id = preg_replace( "/_+/", ' ', $tab_id );

					// replace all underscores with single spaces
					$tab_id = preg_replace( "/\s+/", '-', $tab_id );
					
					// replace all multiple spaces with single dashes
					$tab_id = 'tab-' . $tab_id;
					// prepend with 'tab-' string
				}
			}

			// save the data to the database
			$tab_data[] = array( 'title' => $tab_title, 'id' => $tab_id, 'content' => $tab_content );
			update_post_meta( $post_id, 'frs_woo_product_tabs', $tab_data );
		}
	}


	private function woocommerce_wp_textarea_input( $field ) {
		global $thepostid, $post;

		if ( ! $thepostid ) $thepostid = $post->ID;
		if ( ! isset( $field['placeholder'] ) ) $field['placeholder'] = '';
		if ( ! isset( $field['class'] ) ) $field['class'] = 'short';
		if ( ! isset( $field['value'] ) ) $field['value'] = get_post_meta( $thepostid, $field['id'], true );

		echo '<p class="form-field ' . $field['id'] . '_field"><label style="display:block;" for="' . $field['id'] . '">' . $field['label'] . '</label><textarea class="' . $field['class'] . '" name="' . $field['id'] . '" id="' . $field['id'] . '" placeholder="' . $field['placeholder'] . '" rows="2" cols="20"' . (isset( $field['style'] ) ? ' style="' . $field['style'] . '"' : '') . '>' . esc_textarea( $field['value'] ) . '</textarea> ';

		if ( isset( $field['description'] ) && $field['description'] ) {
			echo '<span class="description">' . $field['description'] . '</span>';
		}

		echo '</p>';
	}


	/** Helper methods ******************************************************/


	/**
	 * Main Custom Product Tabs Lite Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.0.0
	 * @see wt_custom_product_tabs()
	 * @return WT_Custom_Tabs
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Lazy-load the product_tabs meta data, and return true if it exists,
	 * false otherwise
	 *
	 * @return true if there is custom tab data, false otherwise
	 */
	private function product_has_custom_tabs( $product ) {
		
		if ( false === $this->tab_data ) {
			$this->tab_data = maybe_unserialize( get_post_meta( $product->get_id(), 'frs_woo_product_tabs', true ) );
		}

		// tab must at least have a title to exist
		return ! empty( $this->tab_data ) && ! empty( $this->tab_data[0] ) && ! empty( $this->tab_data[0]['title'] );
	}

}


/**
 * Returns the One True Instance of Custom Product Tabs
 *
 * @since 1.0.0
 * @return WT_Custom_Tabs
 */
function wt_custom_product_tabs() {
	return WT_Custom_Tabs::instance();
}


/**
 * The WT_Custom_Tabs global object
 * @deprecated 1.0.0
 * @name $wootoolkit_custom_product_tabs
 * @global WT_Custom_Tabs $GLOBALS['wootoolkit_custom_product_tabs']
 */
$GLOBALS['wootoolkit_custom_product_tabs'] = wt_custom_product_tabs();