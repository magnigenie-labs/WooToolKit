<?php
/**
 * "New quote request" email (HTML). Override in yourtheme/woocommerce/emails/wootoolkit-new-quote.php.
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

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
<?php
/* translators: %s: customer name */
printf( esc_html__( 'You have a new quote request from %s.', 'wootoolkit' ), esc_html( $order->get_formatted_billing_full_name() ) );
?>
</p>
<p>
<?php esc_html_e( 'Check the products and set your prices in the order, then choose "Send quote to customer" under Order actions. The customer gets the quote by email with a link to pay for it.', 'wootoolkit' ); ?>
</p>
<p><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><?php esc_html_e( 'Open the quote', 'wootoolkit' ); ?></a></p>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
