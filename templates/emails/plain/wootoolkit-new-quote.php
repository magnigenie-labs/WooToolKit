<?php
/**
 * "New quote request" email (plain text).
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

/* translators: %s: customer name */
echo esc_html( sprintf( __( 'You have a new quote request from %s.', 'wootoolkit' ), $order->get_formatted_billing_full_name() ) ) . "\n\n";
echo esc_html__( 'Check the products and set your prices in the order, then choose "Send quote to customer" under Order actions. The customer gets the quote by email with a link to pay for it.', 'wootoolkit' ) . "\n\n";
echo esc_url_raw( $order->get_edit_order_url() ) . "\n\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
echo "\n----------------------------------------\n\n";
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
