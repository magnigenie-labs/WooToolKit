<?php
/**
 * Invoice downloads for customers.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * "Invoice" in the My Account orders list, and a "Download invoice" button under the order details
 * (My Account > View order and the order received page). Shown for paid orders only.
 */
final class Account {

	/**
	 * Hooks My Account.
	 */
	public static function hooks(): void {
		add_filter( 'woocommerce_my_account_my_orders_actions', array( self::class, 'orders_actions' ), 20, 2 );
		add_action( 'woocommerce_order_details_after_order_table', array( self::class, 'order_details' ), 20 );
	}

	/**
	 * Adds the download to the orders list.
	 *
	 * @param mixed $actions Actions: key => array( url, name ).
	 * @param mixed $order   Order.
	 * @return mixed
	 */
	public static function orders_actions( $actions, $order = null ) {
		if ( ! is_array( $actions ) || ! $order instanceof \WC_Order ) {
			return $actions;
		}
		foreach ( Downloads::customer_types( $order ) as $type => $labels ) {
			$actions[ 'wootoolkit-pdf-' . $type ] = array(
				'url'  => Downloads::customer_url( $type, $order ),
				'name' => $labels['label'],
			);
		}
		return $actions;
	}

	/**
	 * Download buttons under the order details table.
	 *
	 * @param mixed $order Order.
	 */
	public static function order_details( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$types = Downloads::customer_types( $order );
		if ( empty( $types ) ) {
			return;
		}
		echo '<p class="wootoolkit-pdf-downloads">';
		foreach ( $types as $type => $labels ) {
			printf(
				'<a class="woocommerce-button button %1$s" href="%2$s" target="_blank" rel="noopener">%3$s</a> ',
				esc_attr( 'wootoolkit-pdf-' . $type . ( function_exists( 'wc_wp_theme_get_element_class_name' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ) ),
				esc_url( Downloads::customer_url( $type, $order ) ),
				esc_html( $labels['download'] )
			);
		}
		echo '</p>';
	}
}
