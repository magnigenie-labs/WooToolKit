<?PHP
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class WT_Products_Per_Page_Admin_Settings.
 *
 * WooCommerce Products Per Page Admin settings class.
 *
 * @class		WT_Products_Per_Page_Admin_Settings
 * @version		1.0.0
 */
class WT_Products_Per_Page_Admin_Settings {

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

		$this->kit = 'wootoolkit_products_per_page';

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
        $tabs[] = array( 'id' => $this->kit, 'title' => __( 'Products Per Page', 'wootoolkit' ) );
        return $tabs;
    }

    /**
     * Returns all the settings fields
     *
     * @return array settings fields
     */
    function set_settings_fields( $fields ) {

    	// Set default options to (3|6|9|All) rows
		$dropdown_options_default =
			( apply_filters( 'loop_shop_columns', 4 ) * 3 ) . ' ' .
			( apply_filters( 'loop_shop_columns', 4 ) * 6 ) . ' ' .
			( apply_filters( 'loop_shop_columns', 4 ) * 9 ) . ' ' . '0';

		// Setting the page form fields
        $fields[$this->kit] = 
        	array(
						array(
							'label' 			=> __( 'Enable', 'wootoolkit' ),
							'desc' 				=> __( 'Enable Products Per Page.', 'wootoolkit' ),
							'type' 				=> 'checkbox',
							'name' 				=> 'enabled',
							'default' 		=> 'off'											
						),        		
          	array(
          		'name'				=> 'wtpp_dropdown_location',
							'label'	   		=> __( 'Dropdown Location', 'wootoolkit' ),
							'desc'		  	=> __( 'Choose the location to display dropdown', 'wootoolkit' ),
							'default'  		=> 'topbottom',
							'type'		  	=> 'select',
							'options'  		=> array(
								'top' 			=> __( 'Top', 'wootoolkit' ),
								'bottom' 		=> __( 'Bottom', 'wootoolkit' ),
								'topbottom' => __( 'Top/Bottom', 'wootoolkit' ),
								'none' 			=> __( 'None', 'wootoolkit' ),
							)
						),
						array(
							'name'			=> 'wtpp_dropdown_options',
							'label'			=> __( 'Dropdown Options', 'wootoolkit' ),
							'desc'			=> __( 'Seperated by spaces <em>(-1 for all products)</em>', 'wootoolkit' ),
							'default'		=> $dropdown_options_default,
							'type'			=> 'text',
						),
            array(
            	'name'			=> 'wtpp_default_ppp',
							'label'			=> __( 'Default Products Per Page', 'wootoolkit' ),
							'desc'			=> __( '-1 to display all products', 'wootoolkit' ),
							'default'		=> apply_filters( 'loop_shop_per_page', get_option( 'posts_per_page' ) ),
							'type'			=> 'number',
						),
						array(
							'name'			=> 'wtpp_shop_columns',
							'label'			=> __( 'Shop Columns', 'wootoolkit' ),
							'default'		=> apply_filters( 'loop_shop_columns', 4 ),
							'type'			=> 'number',
						),
						array(
							'label'			=> __( 'HTTP method', 'wootoolkit' ),
							'desc'			=> __( 'GET sends the products per page via the url, POST does this on the background', 'wootoolkit' ),
							'name'			=> 'wtpp_method',
							'default'		=> 'post',
							'type'			=> 'select',
							'options'		=> array(
								'post'		=> __( 'POST', 'wootoolkit' ),
								'get'			=> __( 'GET', 'wootoolkit' ),
							)
						),
          );
        return $fields;
    }
}