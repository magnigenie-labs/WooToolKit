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
    if ( ! is_array( $options ) ) return;
    $checkbox_status = ( isset( $options['optin_checkbox_default'] ) && $options['optin_checkbox_default'] == 'checked' ) ? 1 : '';
    if( !empty($options['enabled']) && $options['enabled'] == 'on' ) :
      if( isset( $options['subscribe_customer'] ) && $options['subscribe_customer'] == 'automatically' )
        return; 

      echo '<div id="wt-mailchimp-subscribe-checkout">';
      woocommerce_form_field( 'wt_mailchimp_subscribe', array(
        'type'          => 'checkbox',
        'class'         => array('wt-mailchimp-subscribe-checkbox'),
        'label'         => isset( $options['optin_label'] ) ? esc_html( $options['optin_label'] ) : esc_html__( 'Subscribe to our newsletter', 'wootoolkit' ),
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
    $options = get_option( 'wootoolkit_mailchimp' );
    if ( ! is_array( $options ) ) {
      return;
    }
    $api_key = isset( $options['mailchimp_api_key'] ) ? trim( $options['mailchimp_api_key'] ) : '';
    $list_id = isset( $options['mailchimp_list_id'] ) ? trim( $options['mailchimp_list_id'] ) : '';

    if ( empty( $options['enabled'] ) || 'on' !== $options['enabled'] || '' === $api_key || '' === $list_id ) {
      return;
    }

    $automatic = isset( $options['subscribe_customer'] ) && 'automatically' === $options['subscribe_customer'];
    if ( ! $automatic && empty( $_POST['wt_mailchimp_subscribe'] ) ) {
      return;
    }

    $user_data = array(
      'user_email' => isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '',
      'first_name' => isset( $_POST['billing_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ) ) : '',
      'last_name'  => isset( $_POST['billing_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ) ) : '',
    );

    if ( ! is_email( $user_data['user_email'] ) ) {
      return;
    }

    $this->wt_mailchimp_subscribe_user( $user_data, $api_key, $list_id );
  }


  /**
  * Adds or updates the customer in the Mailchimp audience.
  *
  * Uses PUT (upsert) with status_if_new, so existing members keep their
  * status (an unsubscribed contact is never re-subscribed) and no customer
  * emails need to be stored in WordPress. Errors never break checkout.
  */
  public function wt_mailchimp_subscribe_user( $user_data, $api_key, $list_id ) {
    $options = get_option( 'wootoolkit_mailchimp' );
    $optin   = ( isset( $options['double_optin'] ) && 'on' === $options['double_optin'] ) ? 'pending' : 'subscribed';

    if ( ! is_array( $user_data ) || empty( $user_data['user_email'] ) ) {
      return;
    }

    try {
      $mailchimp       = new WTMailChimp( $api_key );
      $subscriber_hash = md5( strtolower( $user_data['user_email'] ) );
      $mailchimp->put( "lists/{$list_id}/members/{$subscriber_hash}", array(
        'email_address' => $user_data['user_email'],
        'status_if_new' => $optin,
        'merge_fields'  => array( 'FNAME' => $user_data['first_name'], 'LNAME' => $user_data['last_name'] ),
      ) );
      if ( ! $mailchimp->success() && function_exists( 'wc_get_logger' ) ) {
        wc_get_logger()->warning( 'Mailchimp subscribe failed: ' . $mailchimp->getLastError(), array( 'source' => 'wootoolkit-mailchimp' ) );
      }
    } catch ( \Exception $e ) {
      if ( function_exists( 'wc_get_logger' ) ) {
        wc_get_logger()->error( 'Mailchimp error: ' . $e->getMessage(), array( 'source' => 'wootoolkit-mailchimp' ) );
      }
    }
  }
}
