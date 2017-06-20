<?PHP
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class WT_Mailchimp_Admin_Settings.
 *
 * WooToolKit Mailchimp Admin settings class.
 *
 * @class		WT_Mailchimp_Admin_Settings
 * @version		1.0.0
 * @author		Magnigeeks
 */
class WT_Mailchimp_Admin_Settings {

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

		$this->kit = 'wootoolkit_mailchimp';

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
        $tabs[] = array( 'id' => $this->kit, 'title' => __( 'Mailchimp', 'wootoolkit' ) );
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
								'label' 			=> __( 'Enable', 'wt-mailchimp' ),
								'desc' 				=> __( 'Enable Mailchimp.', 'wt-mailchimp' ),
								'type' 				=> 'checkbox',
								'name' 				=> 'enabled',
								'default' 		=> 'off'											
							),
							array(
								'label' 	  	=> __( 'Mailchimp API Key', 'wt-mailchimp' ),
								'name' 		  	=> 'mailchimp_api_key',
								'type' 		  	=> 'text',
								'desc' 		  	=> __( 'Enter your mailchimp api key. To find your API Key <a href="http://kb.mailchimp.com/accounts/management/about-api-keys" target="_blank">click here</a>', 'wt-mailchimp' ),
							),
							array(
								'label' 	  	=> __( 'Mailchimp list id', 'wt-mailchimp' ),
								'name' 		  	=> 'mailchimp_list_id',
								'type' 		  	=> 'text',
								'desc' 		  	=> __( 'Enter the mailchimp list id you want to use for subscription. To find your List id <a href="http://kb.mailchimp.com/lists/managing-subscribers/find-your-list-id" target="_blank">click here</a>', 'wt-mailchimp' ),
							),
							array(
								'label' 	  	=> __( 'Double Opt-In', 'wt-mailchimp' ),
								'name' 		  	=> 'double_optin',
								'type' 		  	=> 'checkbox',
								'default' 		=> 'off',
								'desc' 				=> __( 'If enabled, customers will receive an email prompting them to confirm their subscription to the list above.', 'wt-mailchimp' ),
							),
							array(
								'label' 	  	=> __( 'Subscribe Customers', 'wt-mailchimp' ),
								'name' 		  	=> 'subscribe_customer',
								'type' 		  	=> 'select',
								'options' 		=> array(
									'automatically' 		 => __( 'Automatically', 'wt-mailchimp' ),
									'ask-for-permission' => __( 'Ask for permission', 'wt-mailchimp' ),
          			),
								'default' 	=> 'automatically',
							),
							array(
								'label' 	  	=> __( 'Opt-In Field Label', 'wt-mailchimp' ),
								'name' 		  	=> 'optin_label',
								'type' 		  	=> 'text',
								'desc'				=> 'Optional: customize the label displayed next to the opt-in checkbox.',
								'default'			=> 'Subscribe to our newsletter',
								'css' 		  	=> 'width: 100px;',
								'desc_tip'	 		=>  true
							),
							array(
								'label' 	  	=> __( 'Opt-In Checkbox Default', 'wt-mailchimp' ),
								'name' 		  	=> 'optin_checkbox_default',
								'type' 		  	=> 'select',
								'desc'				=> 'The default state of the opt-in checkbox',
								'options' 	=> array(
									'checked' => __( 'Checked', 'wt-mailchimp' ),
									'unchecked' => __( 'Unchecked', 'wt-mailchimp' ),
          			),
								'default' 	=> 'checked'
							),
            );
        return $fields;
    }
}