<?php
/**
 * Extra field values in wp-admin, emails and My Account, for orders placed with the classic checkout.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\CheckoutFields;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce shows Additional Checkout Fields only for orders created through the Store API (the
 * block checkout). For every other order (classic checkout, orders created in wp-admin) the kit shows
 * the same values, read from the same meta keys, in the same places: the order screen's billing and
 * shipping columns (editable), the order emails, and the order received / My Account order pages.
 */
final class Display {

	/**
	 * Hooks the displays.
	 */
	public static function hooks(): void {
		add_filter( 'woocommerce_admin_billing_fields', array( self::class, 'admin_billing_fields' ), 20, 3 );
		add_filter( 'woocommerce_admin_shipping_fields', array( self::class, 'admin_shipping_fields' ), 20, 3 );
		add_action( 'woocommerce_email_customer_details', array( self::class, 'email_fields' ), 30, 3 );
		add_action( 'woocommerce_email_customer_address_section', array( self::class, 'email_address_fields' ), 30, 4 );
		add_action( 'woocommerce_order_details_after_order_table', array( self::class, 'order_details' ), 20 );
		add_filter( 'wootoolkit_pdf_invoices_extra_fields', array( self::class, 'pdf_fields' ), 10, 3 );
	}

	/**
	 * Whether WooCommerce shows the order's Additional Checkout Fields itself.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function handled_by_woocommerce( \WC_Order $order ): bool {
		return 'store-api' === $order->get_created_via();
	}

	/**
	 * Fields with a value on an order: list of array( field, group, raw value, formatted value ).
	 *
	 * @param \WC_Order $order     Order.
	 * @param string[]  $locations Locations.
	 * @param string    $group     Only this group ('' for all).
	 * @return array<int, array{0: array<string, mixed>, 1: string, 2: mixed, 3: string}>
	 */
	public static function values( \WC_Order $order, array $locations, string $group = '' ): array {
		$out = array();
		foreach ( $locations as $location ) {
			foreach ( Fields::custom_fields( $location ) as $field ) {
				if ( ! Fields::has_value( $field ) ) {
					continue;
				}
				foreach ( Fields::groups( $field ) as $field_group ) {
					if ( '' !== $group && $group !== $field_group ) {
						continue;
					}
					$value = Fields::stored_value( $order, $field, $field_group );
					if ( '' === $value || array() === $value ) {
						continue;
					}
					$out[] = array( $field, $field_group, $value, Fields::format_value( $field, $value ) );
				}
			}
		}
		return $out;
	}

	/**
	 * A field as the order screen's meta box expects it.
	 *
	 * @param array<string, mixed> $field   Field.
	 * @param string               $group   Group.
	 * @param mixed                $value   Raw value.
	 * @param string               $context 'view' or 'edit'.
	 * @return array<string, mixed>
	 */
	private static function meta_box_field( array $field, string $group, $value, string $context ): array {
		$type = in_array( $field['type'], array( 'select', 'checkbox' ), true ) ? $field['type'] : 'text';
		$out  = array(
			'id'              => Fields::meta_key( $field, $group ),
			'label'           => (string) $field['label'],
			'value'           => 'view' === $context ? Fields::format_value( $field, $value ) : ( is_array( $value ) ? implode( ', ', $value ) : (string) $value ),
			'type'            => $type,
			'update_callback' => array( self::class, 'admin_update' ),
			'show'            => true,
			'wrapper_class'   => 'form-field-wide',
		);
		if ( 'select' === $type ) {
			$out['options'] = array( '' => '—' ) + Fields::choices( $field );
		} elseif ( 'checkbox' === $type ) {
			$out['cbvalue']         = '1';
			$out['checked_value']   = '1';
			$out['unchecked_value'] = '0';
		}
		return $out;
	}

	/**
	 * Adds fields to an order screen column.
	 *
	 * @param array<string, mixed> $fields    Meta box fields.
	 * @param mixed                $order     Order.
	 * @param string               $context   'view' or 'edit'.
	 * @param string[]             $locations Locations shown in this column.
	 * @param string               $group     Address group shown in this column.
	 * @return array<string, mixed>
	 */
	private static function admin_fields( $fields, $order, $context, array $locations, string $group ) {
		$fields = (array) $fields;
		if ( ! $order instanceof \WC_Order || self::handled_by_woocommerce( $order ) ) {
			return $fields;
		}
		$context = 'view' === $context ? 'view' : 'edit';
		foreach ( $locations as $location ) {
			foreach ( Fields::custom_fields( $location ) as $field ) {
				if ( ! Fields::has_value( $field ) ) {
					continue;
				}
				$field_group = 'address' === $location ? $group : 'other';
				$value       = Fields::stored_value( $order, $field, $field_group );
				if ( 'view' === $context && ( '' === $value || array() === $value ) ) {
					continue;
				}
				$box                    = self::meta_box_field( $field, $field_group, $value, $context );
				$fields[ $box['id'] ] = $box;
			}
		}
		return $fields;
	}

	/**
	 * Order screen, billing column: contact fields and billing address fields.
	 *
	 * @param array<string, mixed> $fields  Fields.
	 * @param mixed                $order   Order.
	 * @param string               $context Context.
	 * @return array<string, mixed>
	 */
	public static function admin_billing_fields( $fields, $order = null, $context = 'edit' ) {
		return self::admin_fields( $fields, $order, (string) $context, array( 'address', 'contact' ), 'billing' );
	}

	/**
	 * Order screen, shipping column: shipping address fields and order fields.
	 *
	 * @param array<string, mixed> $fields  Fields.
	 * @param mixed                $order   Order.
	 * @param string               $context Context.
	 * @return array<string, mixed>
	 */
	public static function admin_shipping_fields( $fields, $order = null, $context = 'edit' ) {
		return self::admin_fields( $fields, $order, (string) $context, array( 'address', 'order' ), 'shipping' );
	}

	/**
	 * Saves a field edited on the order screen. WooCommerce has checked the nonce and capability; the
	 * capability is checked again here.
	 *
	 * @param string    $meta_key Meta key (the field id on the screen).
	 * @param mixed     $value    Value, cleaned by WooCommerce.
	 * @param \WC_Order $order    Order.
	 */
	public static function admin_update( $meta_key, $value, $order ): void {
		if ( ! $order instanceof \WC_Order || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		foreach ( Fields::all_custom_fields() as $field ) {
			foreach ( Fields::groups( $field ) as $group ) {
				if ( Fields::meta_key( $field, $group ) !== $meta_key ) {
					continue;
				}
				if ( 'textarea' === $field['type'] && isset( $_POST[ $meta_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before the order is saved.
					$value = wp_unslash( $_POST[ $meta_key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized just below.
				}
				$value = Fields::sanitize_value( $field, $value );
				if ( Fields::is_empty( $field, $value ) && 'checkbox' !== $field['type'] ) {
					$order->delete_meta_data( $meta_key );
				} elseif ( ! Fields::validate_value( $field, $value ) ) {
					$order->update_meta_data( $meta_key, $value );
				}
				return;
			}
		}
	}

	/**
	 * Emails: contact and order fields.
	 *
	 * @param \WC_Order $order         Order.
	 * @param bool      $sent_to_admin Sent to admin.
	 * @param bool      $plain_text    Plain text.
	 */
	public static function email_fields( $order, $sent_to_admin = false, $plain_text = false ): void {
		unset( $sent_to_admin );
		if ( ! $order instanceof \WC_Order || self::handled_by_woocommerce( $order ) ) {
			return;
		}
		$values = self::values( $order, array( 'contact', 'order' ) );
		if ( ! $values ) {
			return;
		}
		if ( $plain_text ) {
			echo "\n" . esc_html( wc_strtoupper( __( 'Additional information', 'wootoolkit' ) ) ) . "\n\n";
			foreach ( $values as $row ) {
				echo esc_html( $row[0]['label'] . ': ' . $row[3] ) . "\n";
			}
			return;
		}
		echo '<h2>' . esc_html__( 'Additional information', 'wootoolkit' ) . '</h2><ul class="additional-fields wootoolkit-checkout-fields" style="margin-bottom: 40px;">';
		foreach ( $values as $row ) {
			echo '<li><strong>' . esc_html( $row[0]['label'] ) . '</strong>: ' . nl2br( esc_html( $row[3] ) ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Emails: address fields under the billing or shipping address.
	 *
	 * @param string    $address_type  'billing' or 'shipping'.
	 * @param \WC_Order $order         Order.
	 * @param bool      $sent_to_admin Sent to admin.
	 * @param bool      $plain_text    Plain text.
	 */
	public static function email_address_fields( $address_type, $order, $sent_to_admin = false, $plain_text = false ): void {
		unset( $sent_to_admin );
		if ( ! $order instanceof \WC_Order || self::handled_by_woocommerce( $order ) || ! in_array( $address_type, array( 'billing', 'shipping' ), true ) ) {
			return;
		}
		foreach ( self::values( $order, array( 'address' ), $address_type ) as $row ) {
			if ( $plain_text ) {
				echo esc_html( $row[0]['label'] . ': ' . $row[3] ) . "\n";
			} else {
				echo '<br><strong>' . esc_html( $row[0]['label'] ) . '</strong>: ' . esc_html( $row[3] );
			}
		}
	}

	/**
	 * Order received page and My Account order view (classic templates and the Order Confirmation blocks).
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function order_details( $order ): void {
		if ( ! $order instanceof \WC_Order || self::handled_by_woocommerce( $order ) ) {
			return;
		}
		$values = self::values( $order, array( 'contact', 'address', 'order' ) );
		if ( ! $values ) {
			return;
		}
		$suffix = array(
			'billing'  => __( 'billing', 'wootoolkit' ),
			'shipping' => __( 'shipping', 'wootoolkit' ),
		);
		echo '<section class="woocommerce-additional-fields wootoolkit-checkout-fields"><h2 class="woocommerce-column__title">' . esc_html__( 'Additional information', 'wootoolkit' ) . '</h2><dl class="wc-block-components-additional-fields-list">';
		foreach ( $values as $row ) {
			$label = (string) $row[0]['label'];
			if ( 'other' !== $row[1] ) {
				/* translators: 1: field label, 2: "billing" or "shipping" */
				$label = sprintf( __( '%1$s (%2$s)', 'wootoolkit' ), $label, $suffix[ $row[1] ] );
			}
			echo '<dt>' . esc_html( $label ) . '</dt><dd>' . nl2br( esc_html( $row[3] ) ) . '</dd>';
		}
		echo '</dl></section>';
	}

	/**
	 * PDF invoices (Pro): custom field values to print on the order's documents. Packing slips get
	 * them too, since delivery instructions often live in a custom field.
	 *
	 * @param mixed     $fields Rows so far.
	 * @param \WC_Order $order  Order.
	 * @param string    $type   Document type.
	 * @return array<int, array{label: string, value: string}>
	 */
	public static function pdf_fields( $fields, $order = null, $type = '' ): array {
		unset( $type );
		$fields = is_array( $fields ) ? $fields : array();
		if ( ! $order instanceof \WC_Order ) {
			return $fields;
		}
		$groups = array(
			'billing'  => __( 'billing', 'wootoolkit' ),
			'shipping' => __( 'shipping', 'wootoolkit' ),
		);
		foreach ( self::values( $order, array( 'contact', 'address', 'order' ) ) as $row ) {
			$label = (string) $row[0]['label'];
			if ( isset( $groups[ $row[1] ] ) ) {
				/* translators: 1: field label, 2: "billing" or "shipping" */
				$label = sprintf( __( '%1$s (%2$s)', 'wootoolkit' ), $label, $groups[ $row[1] ] );
			}
			$fields[] = array(
				'label' => $label,
				'value' => (string) $row[3],
			);
		}
		return $fields;
	}
}
