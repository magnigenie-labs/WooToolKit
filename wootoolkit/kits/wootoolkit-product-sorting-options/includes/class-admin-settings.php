 <?php

 if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class WT_Sorting_Options.
 *
 * WooToolkit Sorting Admin settings class.
 *
 * @class       WT_Sorting_Options
 * @version     1.0.0
 */
class WT_Sorting_Options {

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

        $this->kit = 'wootoolkit_sorting_options';

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
        
        $tabs[] = array( 'id' => $this->kit, 'title' => __( 'Sorting Options', 'wootoolkit' ) );
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
          'label'       => __( 'Enable', 'wootoolkit' ),
          'desc'        => __( 'Enable Sorting.', 'wootoolkit' ),
          'type'        => 'checkbox',
          'name'        => 'enabled',
          'default'     => 'off'                      
        ),
        array(
          'label'     => __( 'New Default Sorting Label', 'wootoolkit' ),
          'name'      => 'wc_rename_default_sorting',
          'type'      => 'text',
          'default'   => '',
          'desc'      => __( 'If desired, enter a new name for the default sorting option, e.g., &quot;Our Sorting&quot;', 'wootoolkit' ),
        ),
        array(
          'label'     => __( 'Add Product Sorting:', 'wootoolkit' ),
          'desc'      => __( 'Select sorting options to add to your shop. "Available Stock" sorts products with the most stock first.', 'wootoolkit' ),
          'name'      => 'wc_extra_product_sorting_options',
          'type'      => 'multicheck',
          'options'   => array(
            'alphabetical'      => __( 'Name: A to Z', 'wootoolkit' ),
            'reverse_alpha'     => __( 'Name: Z to A', 'wootoolkit' ),
            'by_stock'          => __( 'Available Stock', 'wootoolkit' ),
            'featured_first'    => __( 'Featured First', 'wootoolkit' ),
            'on_sale_first'     => __( 'On-sale First', 'wootoolkit' ),
          ),
        ),
      );
      return $fields;
    }
}