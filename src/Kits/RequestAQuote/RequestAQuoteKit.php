<?php
/**
 * Request a quote kit.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\RequestAQuote;

use WooToolkit\Kit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Quote list, quote request form, and quotes as WooCommerce orders the customer pays through
 * WooCommerce's own pay-for-order page.
 */
final class RequestAQuoteKit implements Kit {

	/**
	 * Stable kit id.
	 */
	public function id(): string {
		return 'request-a-quote';
	}

	/**
	 * Kit name.
	 */
	public function name(): string {
		return __( 'Request a quote', 'wootoolkit' );
	}

	/**
	 * Kit description.
	 */
	public function description(): string {
		return __( 'Shoppers add products to a quote and send the request. Each quote is a WooCommerce order: set your prices, send it, and the customer pays through the normal pay-for-order page.', 'wootoolkit' );
	}

	/**
	 * A 2.0 kit.
	 */
	public function is_classic(): bool {
		return false;
	}

	/**
	 * Quotes are orders created through the CRUD API (HPOS), and the quote page does not touch the blocks' cart.
	 *
	 * @return array<string, bool>
	 */
	public function compat(): array {
		return array(
			'hpos'   => true,
			'blocks' => true,
		);
	}

	/**
	 * Settings section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings(): array {
		return array(
			array(
				'id'    => 'wootoolkit_quote',
				'type'  => 'title',
				'title' => __( 'Request a quote', 'wootoolkit' ),
				'desc'  => __( 'Quote requests appear under WooCommerce > Orders with the status "Quote requested". Edit the products and prices, then choose "Send quote to customer" under Order actions. The customer gets an email with a link to pay. In Catalog mode, choose "Add to quote" as the button.', 'wootoolkit' ),
			),
			array(
				'id'       => Frontend::OPT_PAGE,
				'type'     => 'single_select_page',
				'title'    => __( 'Quote page', 'wootoolkit' ),
				'desc'     => __( 'The page with the Quote block or the [wootoolkit_quote] shortcode.', 'wootoolkit' ),
				'desc_tip' => false,
				'class'    => 'wc-enhanced-select',
				'default'  => '',
			),
			array(
				'id'          => Frontend::OPT_BUTTON_TEXT,
				'type'        => 'text',
				'title'       => __( 'Button text', 'wootoolkit' ),
				'placeholder' => __( 'Add to quote', 'wootoolkit' ),
				'default'     => '',
			),
			array(
				'id'      => Frontend::OPT_ON_PURCHASABLE,
				'type'    => 'checkbox',
				'title'   => __( 'Products for sale', 'wootoolkit' ),
				'desc'    => __( 'Also show the button next to Add to cart on product pages', 'wootoolkit' ),
				'default' => 'yes',
			),
			array(
				'id'   => 'wootoolkit_quote',
				'type' => 'sectionend',
			),
		);
	}

	/**
	 * Hooks the kit.
	 */
	public function boot(): void {
		Statuses::hooks();
		Frontend::hooks();
		add_filter( 'woocommerce_email_classes', array( self::class, 'emails' ) );
		add_filter( 'woocommerce_email_actions', array( self::class, 'email_actions' ) );
		add_action( 'wootoolkit_quote_requested_notification', array( self::class, 'send_new_quote_email' ), 10, 2 );
		add_action( 'wootoolkit_quote_sent_notification', array( self::class, 'send_customer_quote_email' ), 10, 2 );
	}

	/**
	 * Sends "New quote request". Hooked here rather than in the email's constructor, so the hook does
	 * not depend on when WooCommerce first builds its mailer.
	 *
	 * @param int            $order_id Order id.
	 * @param \WC_Order|null $order    Order.
	 */
	public static function send_new_quote_email( $order_id, $order = null ): void {
		self::send_email( 'WooToolkit_New_Quote', $order_id, $order );
	}

	/**
	 * Sends "Your quote" to the customer.
	 *
	 * @param int            $order_id Order id.
	 * @param \WC_Order|null $order    Order.
	 */
	public static function send_customer_quote_email( $order_id, $order = null ): void {
		self::send_email( 'WooToolkit_Customer_Quote', $order_id, $order );
	}

	/**
	 * Triggers one of the kit's emails.
	 *
	 * @param string         $key      Email class key.
	 * @param int            $order_id Order id.
	 * @param \WC_Order|null $order    Order.
	 */
	private static function send_email( string $key, $order_id, $order ): void {
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails[ $key ] ) && is_callable( array( $emails[ $key ], 'trigger' ) ) ) {
			$emails[ $key ]->trigger( $order_id, $order );
		}
	}

	/**
	 * Registers the emails (WooCommerce > Settings > Emails).
	 *
	 * @param array<string, \WC_Email> $emails Emails.
	 * @return array<string, \WC_Email>
	 */
	public static function emails( $emails ): array {
		$emails                              = (array) $emails;
		$emails['WooToolkit_New_Quote']      = new Emails\NewQuoteRequestEmail();
		$emails['WooToolkit_Customer_Quote'] = new Emails\CustomerQuoteEmail();
		return $emails;
	}

	/**
	 * Lets WooCommerce load the mailer, and fire the emails' "_notification" hooks, for quote events.
	 *
	 * @param string[] $actions Actions.
	 * @return string[]
	 */
	public static function email_actions( $actions ): array {
		$actions   = (array) $actions;
		$actions[] = 'wootoolkit_quote_requested';
		$actions[] = 'wootoolkit_quote_sent';
		return $actions;
	}
}
