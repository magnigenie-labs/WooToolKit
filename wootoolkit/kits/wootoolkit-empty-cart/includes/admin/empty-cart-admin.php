 <?php

 if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class WT_Empty_Cart_Admin_Settings.
 *
 * WooCommerce Empty Cart Admin settings class.
 *
 * @class       WT_Empty_Cart_Admin_Settings
 * @version     1.0.0
 */
class WT_Empty_Cart_Admin_Settings {

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

        $this->kit = 'wootoolkit_empty_cart';

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
        $tabs[] = array( 'id' => $this->kit, 'title' => __( 'Empty Cart', 'wootoolkit' ) );
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
                    'label'         => __( 'Enable', 'wootoolkit' ),
                    'desc'          => __( 'Enable empty cart.', 'wootoolkit' ),
                    'name'          => 'wt_empty_cart',
                    'type'          => 'checkbox',
                    'default'       => '1',
                ),
                array(
                    'label'         => __( 'Button Text', 'wootoolkit' ),
                    'name'          => 'wt_empty_cart_button_text',
                    'type'          => 'text',
                    'desc'          => __( 'Any text you want can be shown to your button with this option!', 'wootoolkit' ),
                    'default'       => __( 'Empty Cart', 'wootoolkit' )
                ),
            );
        return $fields;
    }
}