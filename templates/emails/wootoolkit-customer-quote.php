<?php
/**
 * "Your quote" email to the customer (HTML). Override in yourtheme/woocommerce/emails/wootoolkit-customer-quote.php.
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

<?php if ( $order->get_billing_first_name() ) : ?>
	<?php /* translators: %s: customer first name */ ?>
	<p><?php printf( esc_html__( 'Hi %s,', 'wootoolkit' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<?php endif; ?>
<p>
<?php
/* translators: %s: site title */
printf( esc_html__( 'Thank you for your quote request. Here is our quote from %s.', 'wootoolkit' ), esc_html( wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) ) );
?>
</p>
<?php if ( $order->needs_payment() ) : ?>
	<p><a class="link" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Accept and pay for this quote', 'wootoolkit' ); ?></a></p>
<?php endif; ?>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
