<?php

/**
 * The admin-options of the kit.
 *
 * @since      1.0.0
 *
 * @package    WOOTK_CS
 * @subpackage WOOTK_CS/includes/admin
 */

class Woo_Toolkit_Continue_Shopping {

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

		$this->kit = 'wootoolkit_continue_shopping';

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
  	$tabs[] = array( 'id' => $this->kit, 'title' => __( 'Continue Shopping', 'wootoolkit' ) );
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
					'label' 					=> __( 'Enable', 'wootoolkit' ),
					'desc' 						=> __( 'Enable Continue Shopping.', 'wootoolkit' ),
					'type' 						=> 'checkbox',
					'name' 						=> 'cs_enabled',
					'default' 				=> 'off'											
				),
      	array(
					'label'           => __( 'Continue Shopping Destination', 'wootoolkit' ),
					'name'            => 'cs_destination',
					'default'         => 'home',
					'type'            => 'radio',
					'options'         => array(
						'home'        	=> __( 'Back to the Home Page', 'wootoolkit' ),
						'shop'        	=> __( 'Back to the Shop', 'wootoolkit' ),
						'recent_prod' 	=> __( 'Jump back to the most recently viewed Product', 'wootoolkit' ),
						'recent_cat'  	=> __( 'Jump back to the most recently viewed Category', 'wootoolkit' ),
						'custom'      	=> __( 'Choose your own link (Best used to redirect to a landing page)', 'wootoolkit' ),
					),
					'autoload'        => false,
					'show_if_checked' => 'option',
				),
        array(
					'label'       		=> __( 'Custom Link', 'wootoolkit' ),
					'name'          	=> 'cs_custom_link',
					'desc'        		=> 'Please enter the link you want to redirect to',
					'type'        		=> 'text',
				),
      );
      return $fields;
    }
}