<?php

class WT_Mailchimp_Front_End {

  /**
   * Bootstraps the class and hooks required actions & filters.
   *
   */
  private $mailchimp;

  public function __construct() {

    // Get Accordion Options
    $this->mailchimp = get_option( 'wootoolkit_mailchimp', 'none' );

    // return if mailchimp is not enabled
    if( isset($this->mailchimp['enabled']) && $this->mailchimp['enabled'] != 'on' ) return;

    // Add checkbox to the checkout page
    add_action('woocommerce_checkout_after_customer_details', array( $this, 'wt_mailchimp_add_subscribe_checkbox'));

    // use hook for adding customer to subscribe
    add_action( 'woocommerce_checkout_order_processed', array($this, 'wt_mailchimp_get_customers'));
  }

  /**
  *
  * @return checkbox on checkout page
  */
  public function wt_mailchimp_add_subscribe_checkbox() {
    $options = get_option('wootoolkit_mailchimp');
    $checkbox_status = $options['optin_checkbox_default'] == 'checked' ? 1 : '';
    if( !empty($options['enabled']) && $options['enabled'] == 'on' ) :
      if( $options['subscribe_customer'] == 'automatically' )
        return; 

      echo '<div id="wt-mailchimp-subscribe-checkout">';
      woocommerce_form_field( 'wt_mailchimp_subscribe', array(
        'type'          => 'checkbox',
        'class'         => array('wt-mailchimp-subscribe-checkbox'),
        'label'         => $options['optin_label'],
        'required'  => false,
      ),$checkbox_status);
      echo '</div>';
    endif;
  }

 /**
  * Get user subscribed data
  *
  * @return array user data
  */ 

  public function wt_mailchimp_get_customers() {
    $options = get_option('wootoolkit_mailchimp');
    $api_key = $options['mailchimp_api_key'];
    $list_id = $options['mailchimp_list_id'];


    if( !empty($options['enabled']) && $options['enabled'] == 'on' && !empty($api_key) && !empty($list_id) ) {
      if( $options['subscribe_customer'] !== 'automatically' && 
        !isset($_POST['wt_mailchimp_subscribe'])  )
        return;

      $user_data = array();
      $user_data['user_email'] = isset( $_POST['billing_email'] ) ? $_POST['billing_email'] : '';

      $user_data['first_name'] = isset($_POST['billing_first_name'] ) ? $_POST['billing_first_name'] : '';
      $user_data['last_name'] = isset($_POST['billing_last_name'] ) ? $_POST['billing_last_name'] : '';
      $this->wt_mailchimp_subscribe_user($user_data, $api_key, $list_id);
    }
  }


  /**
  * Returns mailchimp response
  *
  * @return array mailchimp response
  */

  public function wt_mailchimp_subscribe_user( $user_data, $api_key, $list_id ) {
    $options = get_option('wootoolkit_mailchimp');
    $optin = $options['double_optin'] == 'on' ? 'pending' : 'subscribed';

    if( is_array($user_data) ) {
      $email = $user_data['user_email'];
      $fname = $user_data['first_name'];
      $lname = $user_data['last_name'];
      $subscribed_emails = get_option( 'wt_mailchimp_mails', array() );
      $subscriber_hash = md5(strtolower($email));

      if( !empty( $api_key ) && !empty( $list_id ) ) {
        $mailchimp = new WTMailChimp( $api_key );
        if( in_array($email, $subscribed_emails) ) {
          $subscriber_hash = md5(strtolower($email));
          $result = $mailchimp->put("lists/{$list_id}/members/{$subscriber_hash}", [
            'email_address' => $email,
            'status'        => $optin,
            'merge_fields'  => Array( 'FNAME' => $fname, 'LNAME' => $lname ),
            ]);
          }
          else {
            $result = $mailchimp->post("lists/{$list_id}/members", [
              'email_address' => $email,
              'status'        => $optin,
              'merge_fields'  => Array( 'FNAME' => $fname, 'LNAME' => $lname),
              ]);
            $emails[] = $email;
            update_option( 'wt_mailchimp_mails', $emails );
          }
      }
    }
  }
}