<?php
/**
 * Quote order statuses and the "Send quote" order action.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\RequestAQuote;

defined( 'ABSPATH' ) || exit;

/**
 * A quote is a WooCommerce order. "Quote requested" orders can be edited like pending orders; "Send
 * quote to customer" recalculates the totals, moves the order to "Quote sent" and emails the customer
 * WooCommerce's own pay-for-order link. Payment then moves it to Processing as usual.
 */
final class Statuses {

	const REQUESTED = 'wtk-quote';
	const SENT      = 'wtk-quote-sent';
	const ACTION    = 'wootoolkit_send_quote';

	/**
	 * Order meta marking orders that started as quotes.
	 */
	const META = '_wootoolkit_quote';

	/**
	 * Hooks the statuses.
	 */
	public static function hooks(): void {
		self::register();
		add_filter( 'wc_order_statuses', array( self::class, 'order_statuses' ) );
		add_filter( 'wc_order_is_editable', array( self::class, 'is_editable' ), 10, 2 );
		add_filter( 'woocommerce_valid_order_statuses_for_payment', array( self::class, 'payable' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( self::class, 'payable' ) );
		add_filter( 'woocommerce_analytics_excluded_statuses', array( self::class, 'excluded_from_analytics' ) );
		add_filter( 'woocommerce_order_actions', array( self::class, 'order_actions' ), 10, 2 );
		add_action( 'woocommerce_order_action_' . self::ACTION, array( self::class, 'send' ) );
	}

	/**
	 * Status labels.
	 *
	 * @return array<string, string> slug (without wc-) => label
	 */
	public static function labels(): array {
		return array(
			self::REQUESTED => _x( 'Quote requested', 'Order status', 'wootoolkit' ),
			self::SENT      => _x( 'Quote sent', 'Order status', 'wootoolkit' ),
		);
	}

	/**
	 * Registers the post statuses (used by the orders list when orders are stored as posts).
	 */
	private static function register(): void {
		foreach ( self::labels() as $slug => $label ) {
			register_post_status(
				'wc-' . $slug,
				array(
					'label'                     => $label,
					'public'                    => false,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: number of orders */
					'label_count'               => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>', 'wootoolkit' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralSingular,WordPress.WP.I18n.NonSingularStringLiteralPlural -- label is translated above.
				)
			);
		}
	}

	/**
	 * Adds the statuses after "Pending payment".
	 *
	 * @param array<string, string> $statuses wc-slug => label.
	 * @return array<string, string>
	 */
	public static function order_statuses( $statuses ): array {
		$out = array();
		foreach ( (array) $statuses as $slug => $label ) {
			$out[ $slug ] = $label;
			if ( 'wc-pending' === $slug ) {
				foreach ( self::labels() as $quote_slug => $quote_label ) {
					$out[ 'wc-' . $quote_slug ] = $quote_label;
				}
			}
		}
		foreach ( self::labels() as $quote_slug => $quote_label ) {
			$out += array( 'wc-' . $quote_slug => $quote_label );
		}
		return $out;
	}

	/**
	 * Quotes can be edited (items, prices, shipping) until they are paid.
	 *
	 * @param bool      $editable Whether WooCommerce allows editing.
	 * @param \WC_Order $order    Order.
	 */
	public static function is_editable( $editable, $order ): bool {
		return $editable || ( $order instanceof \WC_Order && $order->has_status( array_keys( self::labels() ) ) );
	}

	/**
	 * A sent quote can be paid, and payment completes it like a pending order.
	 *
	 * @param string[] $statuses Statuses.
	 * @return string[]
	 */
	public static function payable( $statuses ): array {
		$statuses   = (array) $statuses;
		$statuses[] = self::SENT;
		return array_values( array_unique( $statuses ) );
	}

	/**
	 * Unpaid quotes are not sales.
	 *
	 * @param string[] $statuses Statuses excluded from WooCommerce Analytics.
	 * @return string[]
	 */
	public static function excluded_from_analytics( $statuses ): array {
		return array_values( array_unique( array_merge( (array) $statuses, array_keys( self::labels() ) ) ) );
	}

	/**
	 * "Send quote to customer" in the order screen's Order actions.
	 *
	 * @param array<string, string> $actions Actions.
	 * @param \WC_Order|null        $order   Order.
	 * @return array<string, string>
	 */
	public static function order_actions( $actions, $order = null ): array {
		$actions = (array) $actions;
		if ( $order instanceof \WC_Order && $order->has_status( array_keys( self::labels() ) ) ) {
			$actions[ self::ACTION ] = $order->has_status( self::SENT ) ? __( 'Send quote to customer again', 'wootoolkit' ) : __( 'Send quote to customer', 'wootoolkit' );
		}
		return $actions;
	}

	/**
	 * Sends a quote: recalculates totals from the (edited) items, marks it sent and emails the customer.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function send( $order ): void {
		if ( ! $order instanceof \WC_Order || ! $order->has_status( array_keys( self::labels() ) ) ) {
			return;
		}
		$order->calculate_totals();
		$order->update_status( self::SENT, __( 'Quote sent to the customer.', 'wootoolkit' ) );

		/**
		 * A quote was sent to the customer.
		 *
		 * @param int       $order_id Order id.
		 * @param \WC_Order $order    Order.
		 */
		do_action( 'wootoolkit_quote_sent', $order->get_id(), $order );
	}
}
