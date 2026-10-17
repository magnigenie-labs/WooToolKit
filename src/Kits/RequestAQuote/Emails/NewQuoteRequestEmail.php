<?php
/**
 * "New quote request" email to the store.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\RequestAQuote\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Sent to the store when a customer requests a quote. Configurable under WooCommerce > Settings > Emails.
 */
class NewQuoteRequestEmail extends \WC_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'wootoolkit_new_quote';
		$this->title          = __( 'New quote request', 'wootoolkit' );
		$this->description    = __( 'Sent to the store when a customer requests a quote.', 'wootoolkit' );
		$this->template_base  = WOOTOOLKIT_PATH . 'templates/';
		$this->template_html  = 'emails/wootoolkit-new-quote.php';
		$this->template_plain = 'emails/plain/wootoolkit-new-quote.php';
		$this->placeholders   = array(
			'{order_date}'   => '',
			'{order_number}' => '',
		);

		parent::__construct();

		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * Default subject.
	 */
	public function get_default_subject() {
		return __( '[{site_title}]: New quote request #{order_number}', 'wootoolkit' );
	}

	/**
	 * Default heading.
	 */
	public function get_default_heading() {
		return __( 'New quote request: #{order_number}', 'wootoolkit' );
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
				'sent_to_admin'      => true,
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
				'sent_to_admin'      => true,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Settings, with a recipient field.
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		$this->form_fields = array_merge(
			array(
				'recipient' => array(
					'title'       => __( 'Recipient(s)', 'wootoolkit' ),
					'type'        => 'text',
					/* translators: %s: admin email */
					'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s.', 'wootoolkit' ), '<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>' ),
					'placeholder' => '',
					'default'     => '',
					'desc_tip'    => true,
				),
			),
			$this->form_fields
		);
	}
}
