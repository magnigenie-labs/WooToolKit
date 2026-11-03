<?php
/**
 * Invoice and packing slip buttons in wp-admin.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * A "PDF documents" box on the order screen, buttons in the Actions column of the orders list (both
 * order storage types), and the media picker for the logo setting.
 */
final class OrderAdmin {

	/**
	 * Hooks wp-admin.
	 */
	public static function hooks(): void {
		add_action( 'add_meta_boxes', array( self::class, 'meta_box' ), 30 );
		add_filter( 'woocommerce_admin_order_actions', array( self::class, 'list_actions' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ), 20 );
	}

	/**
	 * Document buttons for an order.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<string, array{type: string, ids: int[], label: string}> Key => action.
	 */
	public static function actions( \WC_Order $order ): array {
		$actions = array();
		foreach ( Documents::types() as $type => $def ) {
			if ( 'order' === $def['subject'] && in_array( $type, array( 'invoice', 'packing-slip' ), true ) ) {
				$actions[ $type ] = array(
					'type'  => $type,
					'ids'   => array( $order->get_id() ),
					'label' => (string) $def['label'],
				);
			}
		}

		/**
		 * Document buttons on the order screen and in the orders list.
		 *
		 * @param array<string, array{type: string, ids: int[], label: string}> $actions Key => array( type, ids (order or refund ids), label ).
		 * @param \WC_Order                                                     $order   Order.
		 */
		$actions = apply_filters( 'wootoolkit_pdf_invoices_admin_actions', $actions, $order );

		$out = array();
		foreach ( is_array( $actions ) ? $actions : array() as $key => $action ) {
			if ( is_array( $action ) && isset( $action['type'], $action['ids'], $action['label'] ) && null !== Documents::type( (string) $action['type'] ) ) {
				$out[ sanitize_key( (string) $key ) ] = array(
					'type'  => (string) $action['type'],
					'ids'   => array_map( 'absint', (array) $action['ids'] ),
					'label' => (string) $action['label'],
				);
			}
		}
		return $out;
	}

	/**
	 * Registers the box on the order screen (posts and HPOS).
	 */
	public static function meta_box(): void {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		foreach ( array_unique( array_filter( $screens ) ) as $screen ) {
			add_meta_box( 'wootoolkit-pdf-invoices', __( 'PDF documents', 'wootoolkit' ), array( self::class, 'render_meta_box' ), $screen, 'side', 'default' );
		}
	}

	/**
	 * The box: buttons, and the invoice number once there is one.
	 *
	 * @param mixed $post_or_order WP_Post (posts storage) or WC_Order (HPOS).
	 */
	public static function render_meta_box( $post_or_order ): void {
		$order = $post_or_order instanceof \WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;
		if ( ! $order instanceof \WC_Order || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$invoice = Numbering::get( $order, 'invoice' );
		if ( null !== $invoice ) {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: 1: invoice number, 2: invoice date */
					__( 'Invoice %1$s, %2$s', 'wootoolkit' ),
					$invoice['number'],
					Data::date( $invoice['date'] )
				)
			) . '</p>';
		}
		echo '<p class="wootoolkit-pdf-buttons">';
		foreach ( self::actions( $order ) as $action ) {
			printf(
				'<a class="button" target="_blank" rel="noopener" href="%1$s">%2$s</a> ',
				esc_url( Downloads::admin_url( $action['type'], $action['ids'] ) ),
				esc_html( $action['label'] )
			);
		}
		echo '</p>';
		if ( null === $invoice ) {
			echo '<p class="description">' . esc_html__( 'The invoice gets the next invoice number when it is first opened or sent.', 'wootoolkit' ) . '</p>';
		}
	}

	/**
	 * Buttons in the orders list's Actions column.
	 *
	 * @param mixed $actions Actions.
	 * @param mixed $order   Order.
	 * @return mixed
	 */
	public static function list_actions( $actions, $order = null ) {
		if ( ! is_array( $actions ) || ! $order instanceof \WC_Order || ! current_user_can( 'edit_shop_orders' ) ) {
			return $actions;
		}
		foreach ( self::actions( $order ) as $key => $action ) {
			$actions[ 'wootoolkit_pdf_' . $key ] = array(
				'url'    => Downloads::admin_url( $action['type'], $action['ids'] ),
				'name'   => $action['label'],
				'action' => 'wootoolkit-pdf wootoolkit-pdf-' . sanitize_html_class( $action['type'] ),
			);
		}
		return $actions;
	}

	/**
	 * Dashboard "Needs your attention": invoices without a logo or store address.
	 *
	 * @param array<int, array<string, string>> $items Items.
	 * @return array<int, array<string, string>>
	 */
	public static function attention( $items ): array {
		$items   = is_array( $items ) ? $items : array();
		$logo    = '' !== trim( (string) get_option( Settings::OPT_LOGO, '' ) );
		$address = '' !== trim( (string) get_option( 'woocommerce_store_address', '' ) );
		if ( $logo && $address ) {
			return $items;
		}
		if ( ! $logo && ! $address ) {
			$text   = __( 'has no logo or store address yet.', 'wootoolkit' );
			$action = __( 'Add them', 'wootoolkit' );
			$url    = \WooToolkit\Admin\Admin::settings_url( 'pdf-invoices' );
		} elseif ( ! $logo ) {
			$text   = __( 'has no logo yet.', 'wootoolkit' );
			$action = __( 'Add a logo', 'wootoolkit' );
			$url    = \WooToolkit\Admin\Admin::settings_url( 'pdf-invoices' );
		} else {
			$text   = __( 'has no store address yet.', 'wootoolkit' );
			$action = __( 'Add your address', 'wootoolkit' );
			$url    = admin_url( 'admin.php?page=wc-settings&tab=general' );
		}
		$items[] = array(
			'kit'          => __( 'PDF invoices', 'wootoolkit' ),
			'text'         => $text,
			'action_label' => $action,
			'url'          => $url,
			'level'        => 'amber',
		);
		return $items;
	}

	/**
	 * Icons for the list buttons; the media picker on the kit's settings section.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function assets( $hook = '' ): void {
		$css = '.wc-action-button-wootoolkit-pdf::after{font-family:dashicons!important;content:"\f497"!important}'
			. '.wc-action-button-wootoolkit-pdf.wootoolkit-pdf-packing-slip::after{content:"\f481"!important}'
			. '.wootoolkit-pdf-buttons .button{margin:0 4px 4px 0}';
		wp_register_style( 'wootoolkit-pdf-invoices-admin', false, array(), WOOTOOLKIT_VERSION );
		wp_enqueue_style( 'wootoolkit-pdf-invoices-admin' );
		wp_add_inline_style( 'wootoolkit-pdf-invoices-admin', $css );

		unset( $hook );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- only decides whether to load a script.
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$kit  = isset( $_GET['kit'] ) && is_string( $_GET['kit'] ) ? sanitize_key( wp_unslash( $_GET['kit'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( 'wootoolkit-kits' === $page && 'pdf-invoices' === $kit && current_user_can( \WooToolkit\Admin\Admin::capability() ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'wootoolkit-pdf-invoices-settings', plugins_url( 'assets/js/pdf-invoices-settings.js', WOOTOOLKIT_FILE ), array( 'jquery' ), WOOTOOLKIT_VERSION, true );
			wp_localize_script(
				'wootoolkit-pdf-invoices-settings',
				'wootoolkitPdfInvoices',
				array(
					'choose' => __( 'Choose logo', 'wootoolkit' ),
					'use'    => __( 'Use this image', 'wootoolkit' ),
				)
			);
		}
	}
}
