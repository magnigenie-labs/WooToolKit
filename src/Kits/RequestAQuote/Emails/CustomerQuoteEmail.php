<?php
/**
 * "Your quote" email to the customer.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\RequestAQuote\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Sent to the customer with the quote and a link to pay for it, when you choose "Send quote to customer" on the order. Configurable under WooCommerce > Settings > Emails.
 */
class CustomerQuoteEmail extends \WC_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wootoolkit_customer_quote';
		$this->title          = __( 'Quote sent', 'wootoolkit' );
		$this->description    = __( 'Sent to the customer with the quote and a link to pay for it, when you choose "Send quote to customer" on the order.', 'wootoolkit' );
		$this->template_base  = WOOTOOLKIT_PATH . 'templates/';
		$this->template_html  = 'emails/wootoolkit-customer-quote.php';
		$this->template_plain = 'emails/plain/wootoolkit-customer-quote.php';
		$this->placeholders   = array(
			'{order_date}'   => '',
			'{order_number}' => '',
		);

		$this->customer_email = true;

		parent::__construct();
	}

	/**
	 * Default subject.
	 */
	public function get_default_subject() {
		return __( 'Your quote #{order_number} from {site_title}', 'wootoolkit' );
	}

	/**
	 * Default heading.
	 */
	public function get_default_heading() {
		return __( 'Your quote is ready', 'wootoolkit' );
	}

	/**
	 * Sends the email.
	 *
	 * @param int            $order_id Order id.
	 * @param \WC_Order|null $order    Order.
	 */
	public function trigger( $order_id, $order = null ) {
		$this->setup_locale();
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( $order instanceof \WC_Order ) {
			$this->object                         = $order;
			$this->recipient                      = $order->get_billing_email();
			$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
			$this->placeholders['{order_number}'] = $order->get_order_number();
			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
		}
		$this->restore_locale();
	}

	/**
	 * HTML content.
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Plain text content.
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}
}
