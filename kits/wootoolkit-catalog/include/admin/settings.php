<?PHP
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class WT_Catalog_Settings.
 *
 * WooToolKit Catalog Admin settings class.
 *
 * @class		WT_Catalog_Settings
 * @version		1.0.0
 * @author		Magnigeeks
 */
class WT_Catalog_Settings {

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

		$this->kit = 'wootoolkit_catalog';

		// Add settings to the settings array
        add_filter( 'wootoolkit_setting_tabs', array( $this, 'set_settings_tab' ) );
        add_filter( 'wootoolkit_setting_fields', array( $this, 'set_settings_fields' ) );
	}

	/**
     * Returns all the settings tabs
     *
     * @return array settings tabs
     */
    function set_settings_tab( $tabs ) {
        $tabs[] = array( 'id' => $this->kit, 'title' => __( 'Catalog', 'wootoolkit' ) );
        return $tabs;
    }

    /**
     * Returns all the settings fields
     *
     * @return array settings fields
     */
    function set_settings_fields( $fields ) {

    	$args=array();
		$product_categories = get_terms( 'product_cat', $args );
		$cat_ids=array();
		$cat_names=array();
		
		$cat_ids[] = "all";
		$cat_names[] = "All categories";
		if( !is_wp_error( $product_categories ) ){
			foreach($product_categories as  $v) {
				$cat_names[]= $v->name;
				$cat_ids[] = $v->term_id;
			} 
		}
		$product_categories=array_combine( $cat_ids, $cat_names );

		// Setting the page form fields
        $fields[$this->kit] = array(
        	array(
				'name' 			=> 'catalog_mode',
				'label'			=> __( 'Catalog mode?', 'wootoolkit' ),
				'desc'			=> __( 'Enable or disable catalog mode', 'wootoolkit' ),
				'type'			=> 'checkbox',
				'default'		=> ''

			),
			array(
				'name' 			=> 'catalog_groups',
				'label'			=> __( 'Apply these settings to the following groups:', 'wootoolkit' ),
				'desc'			=> __( 'Choose the group you want to apply catalog mode.', 'wootoolkit' ),
				'type'			=> 'select',
				'options'		=> array( 
									'all' => 'All (apply catalog settings for all users)', 
									'registered_users' => 'Only to registered users', 
									'non_registered_users' => 'Only to non registered users' ),
				'default'		=> 'all'
			),
			array(
				'name' 			=> 'categories',
				'label'			=> __( 'Apply these settings to the following categories:','wootoolkit' ),
				'desc'			=> __( 'Choose categories you want to apply catalog mode.','wootoolkit' ),
				'type'			=> 'multicheck',
				'options'		=> $product_categories,
				'default'		=> array('all')					
			),
			array(
				'name' 			=> 'remove_add_to_cart_button',
				'label'			=> __( 'Remove Add to cart button?', 'wootoolkit' ),
				'desc'			=> __( 'Check this option if you want to remove add to cart button in your catalog.', 'wootoolkit' ),
				'type'			=> 'checkbox',
				'default'		=> 'on'
			),
			array(
				'name' 			=> 'add_custom_button',
				'label'			=> __( 'Add custom button instead of add to cart', 'wootoolkit' ),
				'type'			=> 'checkbox',
				'default'		=> ''
			),
			array(
				'name' 			=> 'custom_button_text',
				'label'			=> __( 'Custom button text' , 'wootoolkit' ),
				'type'			=> 'text',				
				'default'		=> '',
				'placeholder'	=> 'Read more'
			),
			array(
				'name' 			=> 'custom_button_type',
				'label'			=> __( 'Choose from drop-down menu custom button type' , 'wootoolkit' ),
				'type'			=> 'select',				
				'options'		=> array( 
									'custom_button_type_read_more' => 'Read More (redirect to product details)', 
									'custom_button_type_custom' => 'Custom link in all products' ),
				'default'		=> 'custom_button_type_read_more'
			),
			array(
				'name' 			=> 'custom_button_link',
				'label'			=> __( 'Enter here the link for your custom button' , 'wootoolkit' ),
				'type'			=> 'text',				
				'default'		=> '',
				'placeholder'	=> 'http://example.com'
			),
			array(
				'name' 			=> 'remove_price',
				'label'			=> __( 'Remove Price?', 'wootoolkit' ),
				'desc'	=> __( 'Check this option if you want to remove price from product loop and from product details page.', 'wootoolkit' ),
				'type'			=> 'checkbox',
				'default'		=> ''

			),
        );
        return $fields;
    }
}