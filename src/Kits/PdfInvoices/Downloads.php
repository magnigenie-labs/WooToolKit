<?php
/**
 * Download links and their request handlers.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * Two ways in:
 * - Store staff: wp-admin/admin-post.php?action=wootoolkit_pdf_invoices (nonce + edit_shop_orders),
 *   one or more orders merged into one PDF.
 * - Customers: ?wootoolkit-pdf=invoice&order=ID&key=ORDER_KEY on the site. Logged-in customers
 *   only get their own orders (plus a nonce); guest orders need their secret order key. An order
 *   id alone never opens anything.
 */
final class Downloads {

	const ACTION       = 'wootoolkit_pdf_invoices';
	const NONCE        = 'wootoolkit_pdf_invoices';
	const NONCE_FRONT  = 'wootoolkit_pdf_invoices_customer';
	const QUERY        = 'wootoolkit-pdf';
	const MAX_ORDERS   = 100;
	const ERROR_STATUS = 403;

	/**
	 * Hooks the handlers.
	 */
	public static function hooks(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_admin' ) );
		add_action( 'wp_loaded', array( self::class, 'handle_customer' ), 20 );
	}

	/**
	 * Admin link for one or more orders (or refunds).
	 *
	 * @param string $type Document type.
	 * @param int[]  $ids  Order or refund ids.
	 */
	public static function admin_url( string $type, array $ids ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION,
					'type'   => rawurlencode( $type ),
					'ids'    => implode( ',', array_map( 'absint', $ids ) ),
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE
		);
	}

	/**
	 * Customer link for an order's document.
	 *
	 * @param string             $type    Document type.
	 * @param \WC_Abstract_Order $subject Order or refund.
	 */
	public static function customer_url( string $type, \WC_Abstract_Order $subject ): string {
		$order = $subject instanceof \WC_Order_Refund ? wc_get_order( $subject->get_parent_id() ) : $subject;
		$args  = array(
			self::QUERY => rawurlencode( $type ),
			'order'     => $subject->get_id(),
			'key'       => $order instanceof \WC_Order ? rawurlencode( $order->get_order_key() ) : '',
		);
		if ( is_user_logged_in() ) {
			$args['_wpnonce'] = wp_create_nonce( self::NONCE_FRONT );
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * admin-post handler.
	 */
	public static function handle_admin(): void {
		$result = self::check_admin( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- check_admin() verifies the nonce.
		self::respond( $result );
	}

	/**
	 * Front-end handler (only runs for requests with the wootoolkit-pdf query argument).
	 */
	public static function handle_customer(): void {
		if ( ! isset( $_GET[ self::QUERY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- check_customer() verifies ownership and the nonce.
			return;
		}
		$result = self::check_customer( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- check_customer() verifies ownership and the nonce.
		self::respond( $result );
	}

	/**
	 * Validates a staff request.
	 *
	 * @param array<string, mixed> $request Request arguments (type, ids, _wpnonce).
	 * @return array<int, array{type: string, order: \WC_Abstract_Order}>|\WP_Error Documents.
	 */
	public static function check_admin( array $request ) {
		$nonce = isset( $request['_wpnonce'] ) ? sanitize_text_field( (string) $request['_wpnonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return new \WP_Error( 'wootoolkit_pdf_nonce', __( 'This link has expired. Go back and try again.', 'wootoolkit' ) );
		}
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return new \WP_Error( 'wootoolkit_pdf_denied', __( 'You are not allowed to view this document.', 'wootoolkit' ) );
		}
		$type = isset( $request['type'] ) ? sanitize_key( (string) $request['type'] ) : '';
		if ( null === Documents::type( $type ) ) {
			return new \WP_Error( 'wootoolkit_pdf_type', __( 'Unknown document type.', 'wootoolkit' ) );
		}
		$ids = isset( $request['ids'] ) ? array_slice( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $request['ids'] ) ) ) ), 0, self::MAX_ORDERS ) : array();

		$documents = array();
		foreach ( $ids as $id ) {
			$subject = wc_get_order( $id );
			if ( Documents::accepts( $type, $subject ) ) {
				$documents[] = array(
					'type'  => $type,
					'order' => $subject,
				);
			}
		}
		if ( empty( $documents ) ) {
			return new \WP_Error( 'wootoolkit_pdf_none', __( 'No orders found for this document.', 'wootoolkit' ) );
		}
		return $documents;
	}

	/**
	 * Validates a customer request.
	 *
	 * @param array<string, mixed> $request Request arguments (wootoolkit-pdf, order, key, _wpnonce).
	 * @return array<int, array{type: string, order: \WC_Abstract_Order}>|\WP_Error Documents.
	 */
	public static function check_customer( array $request ) {
		$denied  = new \WP_Error( 'wootoolkit_pdf_denied', __( 'You are not allowed to view this document.', 'wootoolkit' ) );
		$type    = isset( $request[ self::QUERY ] ) ? sanitize_key( (string) $request[ self::QUERY ] ) : '';
		$id      = isset( $request['order'] ) ? absint( $request['order'] ) : 0;
		$key     = isset( $request['key'] ) ? sanitize_text_field( (string) $request['key'] ) : '';
		$def     = Documents::type( $type );
		$subject = $id ? wc_get_order( $id ) : false;
		if ( null === $def || empty( $def['customer'] ) || ! Settings::my_account() || ! Documents::accepts( $type, $subject ) ) {
			return $denied;
		}
		$order = $subject instanceof \WC_Order_Refund ? wc_get_order( $subject->get_parent_id() ) : $subject;
		if ( ! $order instanceof \WC_Order || ! self::customer_owns( $order, $key, $request ) ) {
			return $denied;
		}
		if ( ! self::customer_may_download( $type, $subject ) ) {
			return $denied;
		}
		return array(
			array(
				'type'  => $type,
				'order' => $subject,
			),
		);
	}

	/**
	 * Whether the current visitor owns the order: the logged-in customer of the order (with a valid
	 * nonce), or, for guest orders only, anyone holding the order key. Staff who can edit orders pass too.
	 *
	 * @param \WC_Order            $order   Order.
	 * @param string               $key     Order key from the request.
	 * @param array<string, mixed> $request Request (for the nonce).
	 */
	public static function customer_owns( \WC_Order $order, string $key, array $request = array() ): bool {
		$user = get_current_user_id();
		if ( $user > 0 ) {
			$nonce = isset( $request['_wpnonce'] ) ? sanitize_text_field( (string) $request['_wpnonce'] ) : '';
			if ( ! wp_verify_nonce( $nonce, self::NONCE_FRONT ) ) {
				return false;
			}
			if ( current_user_can( 'edit_shop_orders' ) ) {
				return true;
			}
			if ( $order->get_customer_id() > 0 ) {
				return $order->get_customer_id() === $user;
			}
		}
		// Guest orders: the order key is the secret (as on WooCommerce's order received page).
		return 0 === $order->get_customer_id() && '' !== $key && hash_equals( $order->get_order_key(), $key );
	}

	/**
	 * Whether a customer may download a document of this order. Invoices: once the order is paid,
	 * or once an invoice exists.
	 *
	 * @param string             $type    Document type.
	 * @param \WC_Abstract_Order $subject Order or refund.
	 */
	public static function customer_may_download( string $type, \WC_Abstract_Order $subject ): bool {
		$allowed = false;
		if ( 'invoice' === $type && $subject instanceof \WC_Order ) {
			$allowed = $subject->is_paid() || null !== Numbering::get( $subject, 'invoice' );
		}

		/**
		 * Whether a customer may download a document (ownership is already checked).
		 *
		 * @param bool               $allowed Allowed.
		 * @param string             $type    Document type.
		 * @param \WC_Abstract_Order $subject Order or refund.
		 */
		return (bool) apply_filters( 'wootoolkit_pdf_invoices_customer_can_download', $allowed, $type, $subject );
	}

	/**
	 * Document types the customer can download for an order.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<string, array{label: string, download: string}> Type => labels.
	 */
	public static function customer_types( \WC_Order $order ): array {
		$out = array();
		if ( ! Settings::my_account() ) {
			return $out;
		}
		foreach ( Documents::types() as $type => $def ) {
			if ( ! empty( $def['customer'] ) && 'order' === $def['subject'] && self::customer_may_download( $type, $order ) ) {
				$out[ $type ] = array(
					'label'    => (string) $def['label'],
					'download' => isset( $def['download'] ) ? (string) $def['download'] : (string) $def['label'],
				);
			}
		}
		return $out;
	}

	/**
	 * Sends the PDF, or an error page.
	 *
	 * @param array<int, array{type: string, order: \WC_Abstract_Order}>|\WP_Error $result Documents.
	 */
	private static function respond( $result ): void {
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), esc_html__( 'Document', 'wootoolkit' ), array( 'response' => (int) self::ERROR_STATUS ) );
		}
		try {
			$pdf = Documents::render( $result );
		} catch ( \Throwable $e ) {
			wc_get_logger()->error( 'PDF download failed: ' . $e->getMessage(), array( 'source' => 'wootoolkit-pdf-invoices' ) );
			wp_die( esc_html__( 'The document could not be created. Please try again.', 'wootoolkit' ), esc_html__( 'Document', 'wootoolkit' ), array( 'response' => 500 ) );
		}
		$filename = Documents::filename( $result );
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $pdf ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PDF.
		exit;
	}
}
