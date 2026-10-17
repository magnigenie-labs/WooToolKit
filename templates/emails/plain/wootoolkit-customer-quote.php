<?php
/**
 * "Your quote" email to the customer (plain text).
 *
 * @package WooToolkit
 *
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $additional_content
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

echo "=================================================================\n";
echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n";
echo "=================================================================\n\n";

if ( $order->get_billing_first_name() ) {
	/* translators: %s: customer first name */
	echo esc_html( sprintf( __( 'Hi %s,', 'wootoolkit' ), $order->get_billing_first_name() ) ) . "\n\n";
}
/* translators: %s: site title */
echo esc_html( sprintf( __( 'Thank you for your quote request. Here is our quote from %s.', 'wootoolkit' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) ) ) . "\n\n";
if ( $order->needs_payment() ) {
	echo esc_html__( 'Accept and pay for this quote:', 'wootoolkit' ) . ' ' . esc_url_raw( $order->get_checkout_payment_url() ) . "\n\n";
}

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
echo "\n----------------------------------------\n\n";
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
