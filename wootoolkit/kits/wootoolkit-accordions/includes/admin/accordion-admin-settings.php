<?PHP
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class Accordion_Admin_Settings.
 *
 * WooCommerce Accordion Admin settings class.
 *
 * @class		Accordion_Admin_Settings
 * @version		1.0.0
 * @author		Magnigeeks
 */
class Accordion_Admin_Settings {

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

		$this->kit = 'wootoolkit_accordion';

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
        $tabs[] = array( 'id' => $this->kit, 'title' => __( 'Accordion', 'wootoolkit' ) );
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
					'label' 		=> __( 'Enable', 'wooatm' ),
					'desc' 			=> __( 'Enable Accordions.', 'wooatm' ),
					'type' 			=> 'checkbox',
					'name' 			=> 'wooatm_enabled',
					'default' 		=> 'off'											
				),
				array(
					'label' 	  	=> __( 'Accordion background color', 'wooatm' ),
					'name' 		  	=> 'wooatm_bg',
					'type' 		  	=> 'color',
					'default'	  	=> '#ffffff',
				),
				array(
					'label' 	  	=> __( 'Accordion active background', 'wooatm' ),
					'name' 		  	=> 'wooatm_active_bg',
					'type' 		  	=> 'color',
					'default'	  	=> '#adadad',
				),
				array(
					'label' 	  	=> __( 'Accordion title color', 'wooatm' ),
					'name' 		  	=> 'wooatm_title_color',
					'type' 		  	=> 'color',
					'default'	  	=> '#000000',
				),
				array(
					'label' 	  	=> __( 'Accordion active title color', 'wooatm' ),
					'name' 		  	=> 'wooatm_title_active',
					'type' 		  	=> 'color',
					'default'	  	=> '#ffffff',
				),
				array(
					'label' 	  	=> __( 'Accordion arrow color', 'wooatm' ),
					'name' 		  	=> 'wooatm_arrow_color',
					'type' 		  	=> 'color',
					'default'	  	=> '#000000',
					'css' 		  	=> 'width: 125px;',
					'desc_tip'	 		=>  true
				),
				array(
					'label' 	  	=> __( 'Accordion active arrow color', 'wooatm' ),
					'name' 		  	=> 'wooatm_arrow_active',
					'type' 		  	=> 'color',
					'default'	  	=> '#ffffff',
					'css' 		  	=> 'width: 125px;',
					'desc_tip'	  	=>  true
				),
            );
        return $fields;
    }
}