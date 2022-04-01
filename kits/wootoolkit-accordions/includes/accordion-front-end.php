<?php

class Accordion_Front_End {

  /**
   * Bootstraps the class and hooks required actions & filters.
   *
   */

  private $atm_enabled;

  private $atm_bg;

  private $atm_active_bg;

  private $atm_title_color;

  private $atm_title_active;

  private $atm_arrow_color;

  private $atm_arrow_active;


  public function __construct() {

    // Get Accordion Options
    $atm_settings = get_option( 'wootoolkit_accordion', 'none' );

    if( is_array($atm_settings)) {

      // Assign Options
      $this->atm_enabled      = $atm_settings['wooatm_enabled'];
      $this->atm_bg           = $atm_settings['wooatm_bg'];
      $this->atm_active_bg    = $atm_settings['wooatm_active_bg'];
      $this->atm_title_color  = $atm_settings['wooatm_title_color'];
      $this->atm_title_active = $atm_settings['wooatm_title_active'];
      $this->atm_arrow_color  = $atm_settings['wooatm_arrow_color'];
      $this->atm_arrow_active = $atm_settings['wooatm_arrow_active'];
    }

    //return if wooatm is not enabled
    if( $this->atm_enabled != 'on' ) return;

    //Add css and js files for the tabs
    add_action( 'wp_enqueue_scripts',  array( $this, 'wooatm_enqueue_scripts' ) );

    //Replace default tabs with woocommerce tabs
    add_action( 'init', array( $this, 'add_remove_tabs' ), 10 );
  }

  /**
  *
  * Add necessary js and css files for the popup
  *
  */
  public function wooatm_enqueue_scripts() {

    if( !is_product() ) return;

    $css = "body .accordion-header h1{ color:{$this->atm_title_color};}";
    $css.= "body .accordion-item-active .accordion-header h1{ color:{$this->atm_title_active};}";
    $css.= "body .accordion-header{ background:{$this->atm_bg}; }";
    $css.= "body .accordion-item-active .accordion-header{ background:{$this->atm_active_bg}; }";
    $css.= ".accordion-header-icon{ color:{$this->atm_arrow_color}; }";
    $css.= ".accordion-header-icon.accordion-header-icon-active{ color:{$this->atm_arrow_active};}";

    //Add responsive tabs css 
    wp_enqueue_style( 'accordions-css', 
      plugins_url( 'assets/css/wt-accordion.min.css', dirname(__FILE__) ) );
    wp_enqueue_script( 'accordions-js',
      plugins_url( 'assets/js/wt-accordion.min.js', dirname(__FILE__) ), array( 'jquery' ), '1.0.0', true);
    wp_enqueue_script( 'accordions-custom', 
      plugins_url( 'assets/js/custom.js', dirname(__FILE__) ), array( 'jquery', 'accordions-js' ), '1.0.0', true );
    wp_add_inline_style( 'accordions-css', $css );
  }


  /**
   * Replace default woocommerce tabs with woo accordions.
   *
   * @param void
   *
   */
  public function add_remove_tabs() {

    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
    remove_action( 'woocommerce_after_single_product_summary', 'action_woocommerce_after_single_product_summary', 10, 2 );
    add_action( 'woocommerce_after_single_product_summary', array( $this, 'wooatm_output_tabs' ), 10 );
  }

  /**
   * Output woo accordions.
   *
   * @param void
   *
   */
  public function wooatm_output_tabs(){
    
    $tabs = apply_filters( 'woocommerce_product_tabs', array() );

    if ( ! empty( $tabs ) ) : ?>
      <div id="accordion-container" class="woocommerce-tabs wc-tabs-wrapper">
        <?php foreach ( $tabs as $key => $tab ) : ?>
          <h1 class="<?php echo esc_attr( $key ); ?>_tab">
            <?php echo apply_filters( 'woocommerce_product_' . $key . '_tab_title', esc_html( $tab['title'] ), $key ); ?>
          </h1>
          <div id="tab-<?php echo esc_attr( $key ); ?>">
            <?php call_user_func( $tab['callback'], $key, $tab ); ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif;
  }
}