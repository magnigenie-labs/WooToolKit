<?php
/**
 * Turns the quote list into a "Quote requested" order.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\RequestAQuote;

defined( 'ABSPATH' ) || exit;

/**
 * Validates the quote request form and creates the order.
 */
final class Submission {

	/**
	 * Form fields: name => [label, required].
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function fields(): array {
		return array(
			'first_name' => array( __( 'First name', 'wootoolkit' ), true ),
			'last_name'  => array( __( 'Last name', 'wootoolkit' ), false ),
			'email'      => array( __( 'Email address', 'wootoolkit' ), true ),
			'phone'      => array( __( 'Phone', 'wootoolkit' ), false ),
			'company'    => array( __( 'Company', 'wootoolkit' ), false ),
			'message'    => array( __( 'Message', 'wootoolkit' ), false ),
		);
	}

	/**
	 * Creates the quote order from the current quote list.
	 *
	 * @param array<string, string> $data Submitted fields (unslashed).
	 * @return \WC_Order|\WP_Error
	 */
	public static function create( array $data ) {
		$items = QuoteList::items();
		if ( ! $items ) {
			return new \WP_Error( 'wootoolkit_quote_empty', __( 'Your quote list is empty.', 'wootoolkit' ) );
		}

		$values = array();
		foreach ( self::fields() as $name => $field ) {
			$raw             = isset( $data[ $name ] ) ? (string) $data[ $name ] : '';
			$values[ $name ] = 'message' === $name ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
			if ( $field[1] && '' === $values[ $name ] ) {
				/* translators: %s: field label */
				return new \WP_Error( 'wootoolkit_quote_required', sprintf( __( '%s is a required field.', 'wootoolkit' ), $field[0] ) );
			}
		}
		$values['email'] = sanitize_email( $values['email'] );
		if ( ! is_email( $values['email'] ) ) {
			return new \WP_Error( 'wootoolkit_quote_email', __( 'Please enter a valid email address.', 'wootoolkit' ) );
		}

		$order = new \WC_Order();
		$order->set_customer_id( get_current_user_id() );
		$order->set_created_via( 'wootoolkit_quote' );
		$order->set_currency( get_woocommerce_currency() );
		$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
		$order->set_billing_first_name( $values['first_name'] );
		$order->set_billing_last_name( $values['last_name'] );
		$order->set_billing_email( $values['email'] );
		$order->set_billing_phone( $values['phone'] );
		$order->set_billing_company( $values['company'] );
		$order->set_customer_note( $values['message'] );
		$order->set_customer_ip_address( \WC_Geolocation::get_ip_address() );
		$order->set_customer_user_agent( wc_get_user_agent() );
		$order->update_meta_data( Statuses::META, 'yes' );

		$added = 0;
		foreach ( $items as $item ) {
			$product = QuoteList::product( $item );
			if ( $product ) {
				$order->add_product( $product, $item['quantity'], array( 'variation' => $item['variation'] ) );
				++$added;
			}
		}
		if ( ! $added ) {
			QuoteList::clear();
			return new \WP_Error( 'wootoolkit_quote_empty', __( 'The products in your quote list are no longer available.', 'wootoolkit' ) );
		}

		$order->calculate_totals();
		$order->set_status( Statuses::REQUESTED, __( 'Quote requested by the customer.', 'wootoolkit' ) );
		$order->save();
		QuoteList::clear();

		/**
		 * A customer requested a quote.
		 *
		 * @param int       $order_id Order id.
		 * @param \WC_Order $order    Order.
		 */
		do_action( 'wootoolkit_quote_requested', $order->get_id(), $order );

		return $order;
	}
}
