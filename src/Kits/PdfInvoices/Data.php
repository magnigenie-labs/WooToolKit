<?php
/**
 * Document data from an order.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * Collects everything a document shows as plain text (prices already formatted), so the renderer
 * needs nothing from WooCommerce and a Pro kit can add or change rows in one filter.
 */
final class Data {

	/**
	 * The document data array.
	 *
	 * Keys: type, title, number, prices, store {name, lines[], logo}, meta [{label, value}],
	 * addresses [{title, lines[]}], columns [{key, label, width, align}], items [{name, details[],
	 * cells{key: text}}], totals [{label, value, strong}], notes [{title, text}], footer, rtl, labels.
	 *
	 * @param \WC_Abstract_Order   $subject Order (or refund, for refund document types).
	 * @param string               $type    Document type.
	 * @param array<string, mixed> $def     Document type definition.
	 * @return array<string, mixed>
	 */
	public static function build( \WC_Abstract_Order $subject, string $type, array $def ): array {
		$order  = $subject instanceof \WC_Order_Refund ? wc_get_order( $subject->get_parent_id() ) : $subject;
		$order  = $order instanceof \WC_Order ? $order : null;
		$prices = ! empty( $def['prices'] );
		$stored = ! empty( $def['numbered'] ) ? Numbering::get( $subject, $type ) : null;

		$data = array(
			'type'      => $type,
			'title'     => (string) $def['title'],
			'number'    => null !== $stored ? $stored['number'] : '',
			'prices'    => $prices,
			'store'     => self::store(),
			'meta'      => array(),
			'addresses' => array(),
			'columns'   => self::columns( $prices ),
			'items'     => array(),
			'totals'    => array(),
			'notes'     => array(),
			'footer'    => Settings::footer(),
			'rtl'       => is_rtl(),
			'labels'    => array(
				/* translators: 1: page number, 2: number of pages */
				'page' => __( 'Page %1$d of %2$d', 'wootoolkit' ),
			),
		);

		if ( null !== $stored ) {
			$data['meta'][] = self::row( (string) $def['number_label'], $stored['number'] );
			$data['meta'][] = self::row( (string) $def['date_label'], self::date( $stored['date'] ) );
		}
		if ( null !== $order ) {
			$data['meta'][] = self::row( __( 'Order number', 'wootoolkit' ), (string) $order->get_order_number() );
			$created        = $order->get_date_created();
			if ( $created ) {
				$data['meta'][] = self::row( __( 'Order date', 'wootoolkit' ), self::date( $created->getTimestamp() ) );
			}
			if ( $prices && '' !== $order->get_payment_method_title() ) {
				$data['meta'][] = self::row( __( 'Payment method', 'wootoolkit' ), self::plain( $order->get_payment_method_title() ) );
			}
			if ( ! $prices && '' !== $order->get_shipping_method() ) {
				$data['meta'][] = self::row( __( 'Shipping method', 'wootoolkit' ), self::plain( $order->get_shipping_method() ) );
			}

			$data['addresses'] = self::addresses( $order, $prices );
			$data['items']     = self::items( $order, $prices );
			$data['totals']    = $prices ? self::totals( $order ) : array();
			if ( '' !== trim( $order->get_customer_note() ) ) {
				$data['notes'][] = array(
					'title' => __( 'Customer note', 'wootoolkit' ),
					'text'  => self::plain( $order->get_customer_note() ),
				);
			}
		}

		/**
		 * The data a document is drawn from, before it is rendered. All values are plain text.
		 *
		 * @param array<string, mixed> $data    Document data (see Data::build()).
		 * @param \WC_Order|null       $order   The order (the parent order for refund documents).
		 * @param string               $type    Document type.
		 * @param \WC_Abstract_Order   $subject The order or refund the document is for.
		 */
		$data = apply_filters( 'wootoolkit_pdf_invoices_document_data', $data, $order, $type, $subject );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Store name, address lines (WooCommerce > Settings > General) and logo.
	 *
	 * @return array{name: string, lines: string[], logo: string}
	 */
	public static function store(): array {
		$lines = array();
		if ( function_exists( 'WC' ) && WC()->countries ) {
			$countries = WC()->countries;
			$formatted = $countries->get_formatted_address(
				array(
					'address_1' => $countries->get_base_address(),
					'address_2' => $countries->get_base_address_2(),
					'city'      => $countries->get_base_city(),
					'state'     => $countries->get_base_state(),
					'postcode'  => $countries->get_base_postcode(),
					'country'   => $countries->get_base_country(),
				)
			);
			$lines     = self::lines( $formatted );
		}
		foreach ( preg_split( '/\r\n|\r|\n/', Settings::store_info() ) ?: array() as $line ) { // phpcs:ignore Universal.Operators.DisallowShortTernary.Found -- preg_split returns false on error.
			if ( '' !== trim( $line ) ) {
				$lines[] = trim( $line );
			}
		}
		return array(
			'name'  => self::plain( (string) get_bloginfo( 'name' ) ),
			'lines' => $lines,
			'logo'  => Settings::logo_path(),
		);
	}

	/**
	 * Item table columns. Invoices: product, SKU, quantity, unit price, tax (when taxes are on),
	 * total. Packing slips: product, SKU, quantity.
	 *
	 * @param bool $prices Whether the document shows prices.
	 * @return array<int, array{key: string, label: string, width: float, align: string}>
	 */
	public static function columns( bool $prices ): array {
		$columns = array( self::column( 'product', __( 'Product', 'wootoolkit' ), $prices ? 0.40 : 0.62 ) );
		$columns[] = self::column( 'sku', __( 'SKU', 'wootoolkit' ), $prices ? 0.14 : 0.24 );
		$columns[] = self::column( 'qty', __( 'Qty', 'wootoolkit' ), $prices ? 0.07 : 0.14, 'right' );
		if ( $prices ) {
			$columns[] = self::column( 'price', __( 'Price', 'wootoolkit' ), 0.13, 'right' );
			if ( wc_tax_enabled() ) {
				$columns[] = self::column( 'tax', __( 'Tax', 'wootoolkit' ), 0.12, 'right' );
			}
			$columns[] = self::column( 'total', __( 'Total', 'wootoolkit' ), 0.14, 'right' );
		}
		return $columns;
	}

	/**
	 * Billing and shipping address blocks.
	 *
	 * @param \WC_Order $order  Order.
	 * @param bool      $prices Invoice (billing first) or packing slip (shipping first).
	 * @return array<int, array{title: string, lines: string[]}>
	 */
	private static function addresses( \WC_Order $order, bool $prices ): array {
		$billing = self::lines( $order->get_formatted_billing_address() );
		if ( '' !== $order->get_billing_email() ) {
			$billing[] = $order->get_billing_email();
		}
		if ( '' !== $order->get_billing_phone() ) {
			$billing[] = $order->get_billing_phone();
		}
		$shipping = $order->has_shipping_address() ? self::lines( $order->get_formatted_shipping_address() ) : array();
		if ( $shipping && '' !== $order->get_shipping_phone() ) {
			$shipping[] = $order->get_shipping_phone();
		}

		$blocks = array();
		if ( $billing ) {
			$blocks[] = array(
				'title' => __( 'Billing address', 'wootoolkit' ),
				'lines' => $billing,
			);
		}
		if ( $shipping ) {
			$ship = array(
				'title' => __( 'Shipping address', 'wootoolkit' ),
				'lines' => $shipping,
			);
			if ( $prices ) {
				$blocks[] = $ship;
			} else {
				array_unshift( $blocks, $ship );
			}
		}
		return $blocks;
	}

	/**
	 * Item rows.
	 *
	 * @param \WC_Order $order  Order.
	 * @param bool      $prices Whether to fill the price columns.
	 * @return array<int, array{name: string, details: string[], cells: array<string, string>}>
	 */
	private static function items( \WC_Order $order, bool $prices ): array {
		$currency = array( 'currency' => $order->get_currency() );
		$rows     = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			$details = array();
			$meta    = wc_display_item_meta(
				$item,
				array(
					'before'       => '',
					'after'        => '',
					'separator'    => "\n",
					'echo'         => false,
					'autop'        => false,
					'label_before' => '',
					'label_after'  => ': ',
				)
			);
			foreach ( preg_split( '/\n/', self::plain( (string) $meta ) ) ?: array() as $line ) { // phpcs:ignore Universal.Operators.DisallowShortTernary.Found -- preg_split returns false on error.
				if ( '' !== trim( $line ) ) {
					$details[] = trim( $line );
				}
			}

			$qty  = (float) $item->get_quantity();
			$row  = array(
				'name'    => self::plain( $item->get_name() ),
				'details' => $details,
				'cells'   => array(
					'sku' => $product instanceof \WC_Product ? (string) $product->get_sku() : '',
					'qty' => (string) wc_format_decimal( $qty, false, true ),
				),
			);
			if ( $prices ) {
				$row['cells']['price'] = self::money( (float) $order->get_item_subtotal( $item, false, true ), $currency );
				$row['cells']['tax']   = self::money( (float) $item->get_subtotal_tax(), $currency );
				$row['cells']['total'] = self::plain( $order->get_formatted_line_subtotal( $item ) );
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Totals rows from WooCommerce's own order totals (subtotal, discounts, shipping, fees, taxes,
	 * refunds, total), minus the payment method, which is shown at the top.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<int, array{label: string, value: string, strong: bool}>
	 */
	private static function totals( \WC_Order $order ): array {
		$rows = array();
		foreach ( $order->get_order_item_totals() as $key => $total ) {
			if ( 'payment_method' === $key ) {
				continue;
			}
			$rows[] = array(
				'label'  => rtrim( self::plain( (string) $total['label'] ), ':' ),
				'value'  => self::plain( (string) $total['value'] ),
				'strong' => 'order_total' === $key,
			);
		}
		return $rows;
	}

	/**
	 * A label/value row.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return array{label: string, value: string}
	 */
	public static function row( string $label, string $value ): array {
		return array(
			'label' => $label,
			'value' => $value,
		);
	}

	/**
	 * A column definition.
	 *
	 * @param string $key   Cell key.
	 * @param string $label Header.
	 * @param float  $width Relative width.
	 * @param string $align left or right.
	 * @return array{key: string, label: string, width: float, align: string}
	 */
	private static function column( string $key, string $label, float $width, string $align = 'left' ): array {
		return array(
			'key'   => $key,
			'label' => $label,
			'width' => $width,
			'align' => $align,
		);
	}

	/**
	 * A price as plain text.
	 *
	 * @param float                $amount Amount.
	 * @param array<string, mixed> $args   wc_price() arguments.
	 */
	public static function money( float $amount, array $args = array() ): string {
		return self::plain( wc_price( $amount, $args ) );
	}

	/**
	 * A date in the store's date format and timezone.
	 *
	 * @param int $timestamp Unix timestamp.
	 */
	public static function date( int $timestamp ): string {
		return (string) wp_date( wc_date_format(), $timestamp );
	}

	/**
	 * HTML to plain text: line breaks kept, tags removed, entities decoded.
	 *
	 * @param string $html HTML.
	 */
	public static function plain( string $html ): string {
		$text = (string) preg_replace( '#<br\s*/?>#i', "\n", $html );
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( (string) preg_replace( '/[ \t]+/u', ' ', $text ) );
	}

	/**
	 * Address HTML ("<br/>"-separated) as lines.
	 *
	 * @param string $html Formatted address.
	 * @return string[]
	 */
	private static function lines( string $html ): array {
		$lines = array();
		foreach ( preg_split( '#<br\s*/?>|\n#i', $html ) ?: array() as $line ) { // phpcs:ignore Universal.Operators.DisallowShortTernary.Found -- preg_split returns false on error.
			$line = self::plain( $line );
			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}
		return $lines;
	}
}
