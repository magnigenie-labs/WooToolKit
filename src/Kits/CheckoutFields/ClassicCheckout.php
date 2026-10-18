<?php
/**
 * Extra fields in the classic checkout.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\CheckoutFields;

use WooToolkit\Kit\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the extra fields to the classic checkout form (contact fields in the billing form, address
 * fields in both address forms, order fields after the order notes), validates them, and saves them
 * in the same order and customer meta as the block checkout.
 */
final class ClassicCheckout {

	/**
	 * Classic input name => array( field, group ), for the fields offered in this request.
	 *
	 * @var array<string, array{0: array<string, mixed>, 1: string}>|null
	 */
	private static $keys = null;

	/**
	 * Hooks the classic checkout.
	 */
	public static function hooks(): void {
		add_filter( 'woocommerce_checkout_fields', array( self::class, 'add_fields' ), 30 );
		add_action( 'woocommerce_after_checkout_validation', array( self::class, 'validate' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'save' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_customer', array( self::class, 'save_customer' ), 10, 2 );
		add_filter( 'woocommerce_checkout_get_value', array( self::class, 'default_value' ), 10, 2 );
	}

	/**
	 * Clears the per-request cache.
	 */
	public static function flush(): void {
		self::$keys = null;
	}

	/**
	 * The fields offered in the classic checkout in this request: input name => array( field, group ).
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: string}>
	 */
	public static function keys(): array {
		if ( null === self::$keys ) {
			self::$keys = array();
			foreach ( Fields::all_custom_fields() as $field ) {
				if ( Support::is_storefront() && ! Fields::applies( $field, 'classic' ) ) {
					continue;
				}
				foreach ( Fields::groups( $field ) as $group ) {
					self::$keys[ Fields::classic_key( $field, $group ) ] = array( $field, $group );
				}
			}
		}
		return self::$keys;
	}

	/**
	 * Fieldset of a field in a group.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param string               $group Group.
	 */
	private static function fieldset( array $field, string $group ): string {
		if ( 'address' === $field['location'] ) {
			return $group;
		}
		return 'contact' === $field['location'] ? 'billing' : 'order';
	}

	/**
	 * woocommerce_form_field() arguments of a field.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param string               $group Group.
	 * @return array<string, mixed>
	 */
	public static function form_args( array $field, string $group ): array {
		$type = Fields::field_type( $field );
		$args = array(
			'type'        => (string) $type['classic'],
			'label'       => (string) $field['label'],
			'placeholder' => (string) $field['placeholder'],
			'required'    => ! empty( $field['required'] ) && Fields::has_value( $field ),
			'priority'    => (int) $field['priority'],
			'class'       => array( 'form-row-wide', 'wootoolkit-checkout-field', 'wootoolkit-checkout-field--' . sanitize_html_class( (string) $field['type'] ) ),
		);
		if ( ! empty( $type['options'] ) ) {
			$choices = Fields::choices( $field );
			if ( 'select' === $type['classic'] ) {
				$choices = array( '' => '' !== (string) $field['placeholder'] ? (string) $field['placeholder'] : __( 'Choose an option', 'wootoolkit' ) ) + $choices;
			}
			$args['options'] = $choices;
		}
		if ( 'number' === $type['classic'] ) {
			$args['custom_attributes'] = array( 'step' => 'any' );
		}
		if ( 'email' === $type['validate'] ) {
			$args['autocomplete'] = 'email';
		} elseif ( 'phone' === $type['validate'] ) {
			$args['autocomplete'] = 'tel';
		}

		/**
		 * The woocommerce_form_field() arguments of an extra field in the classic checkout.
		 *
		 * @param array<string, mixed> $args  Arguments.
		 * @param array<string, mixed> $field Field.
		 * @param string               $group 'billing', 'shipping' or 'other'.
		 */
		return (array) apply_filters( 'wootoolkit_checkout_fields_classic_field_args', $args, $field, $group );
	}

	/**
	 * Adds the fields to the checkout fieldsets.
	 *
	 * @param array<string, array<string, mixed>> $fieldsets Fieldsets.
	 * @return array<string, array<string, mixed>>
	 */
	public static function add_fields( $fieldsets ) {
		$fieldsets = (array) $fieldsets;
		foreach ( self::keys() as $key => list( $field, $group ) ) {
			$fieldsets[ self::fieldset( $field, $group ) ][ $key ] = self::form_args( $field, $group );
		}
		return $fieldsets;
	}

	/**
	 * Whether the shipping copy of an address field was really filled in (not copied from billing).
	 *
	 * @param array<string, mixed> $data Posted checkout data.
	 */
	private static function has_own_shipping( array $data ): bool {
		return ! empty( $data['ship_to_different_address'] );
	}

	/**
	 * Validates the values WooCommerce does not (format and choice checks; WooCommerce checks required).
	 *
	 * @param array<string, mixed> $data   Posted checkout data.
	 * @param \WP_Error            $errors Errors.
	 */
	public static function validate( $data, $errors ): void {
		if ( ! $errors instanceof \WP_Error ) {
			return;
		}
		$data = (array) $data;
		foreach ( self::keys() as $key => list( $field, $group ) ) {
			if ( ! array_key_exists( $key, $data ) || ! Fields::has_value( $field ) || ( 'shipping' === $group && ! self::has_own_shipping( $data ) ) ) {
				continue;
			}
			$value = Fields::sanitize_value( $field, $data[ $key ] );
			if ( Fields::is_empty( $field, $value ) ) {
				continue;
			}
			$error = Fields::validate_value( $field, $value );
			if ( $error ) {
				$errors->add( $key . '_validation', $error->get_error_message(), array( 'id' => $key ) );
			}
		}
	}

	/**
	 * Values to save on the order: meta key => value.
	 *
	 * @param array<string, mixed> $data Posted checkout data.
	 * @return array<string, mixed>
	 */
	public static function values( array $data ): array {
		$values = array();
		foreach ( self::keys() as $key => list( $field, $group ) ) {
			if ( ! array_key_exists( $key, $data ) || ! Fields::has_value( $field ) ) {
				continue;
			}
			$value = Fields::sanitize_value( $field, $data[ $key ] );
			if ( ( Fields::is_empty( $field, $value ) && 'checkbox' !== $field['type'] ) || Fields::validate_value( $field, $value ) ) {
				continue;
			}
			$values[ Fields::meta_key( $field, $group ) ] = $value;
		}
		return $values;
	}

	/**
	 * Saves the values on the new order, in the block checkout's meta keys.
	 *
	 * @param \WC_Order            $order Order.
	 * @param array<string, mixed> $data  Posted checkout data.
	 */
	public static function save( $order, $data = array() ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$values = self::values( (array) $data );
		foreach ( $values as $meta_key => $value ) {
			$order->update_meta_data( $meta_key, $value );
		}
		// WooCommerce also saves billing_/shipping_ prefixed inputs as "_billing_…" meta; one copy is enough.
		foreach ( array_keys( self::keys() ) as $key ) {
			if ( 0 === strpos( $key, 'billing_' ) || 0 === strpos( $key, 'shipping_' ) ) {
				$order->delete_meta_data( '_' . $key );
			}
		}

		/**
		 * After the classic checkout saved the extra fields on the order (not saved yet).
		 *
		 * @param \WC_Order            $order  Order.
		 * @param array<string, mixed> $values Meta key => value.
		 */
		do_action( 'wootoolkit_checkout_fields_classic_saved', $order, $values );
	}

	/**
	 * Remembers contact and address values on the customer, as the block checkout does.
	 *
	 * @param \WC_Customer         $customer Customer.
	 * @param array<string, mixed> $data     Posted checkout data.
	 */
	public static function save_customer( $customer, $data = array() ): void {
		if ( ! $customer instanceof \WC_Customer ) {
			return;
		}
		$data = (array) $data;
		foreach ( self::keys() as $key => list( $field, $group ) ) {
			if ( 'order' === $field['location'] ) {
				continue;
			}
			$customer->delete_meta_data( $key );
			if ( array_key_exists( $key, $data ) && Fields::has_value( $field ) ) {
				$value = Fields::sanitize_value( $field, $data[ $key ] );
				if ( ! Fields::validate_value( $field, $value ) ) {
					$customer->update_meta_data( Fields::meta_key( $field, $group ), $value );
				}
			}
		}
	}

	/**
	 * Fills contact and address fields from the customer's saved values (either checkout).
	 *
	 * @param mixed  $value Value (null for the default lookup).
	 * @param string $input Input name.
	 * @return mixed
	 */
	public static function default_value( $value, $input ) {
		if ( null !== $value || ! is_string( $input ) || 0 === strpos( $input, '_' ) ) {
			return $value;
		}
		$keys = self::keys();
		if ( ! isset( $keys[ $input ] ) || 'order' === $keys[ $input ][0]['location'] ) {
			return $value;
		}
		list( $field, $group ) = $keys[ $input ];

		$customer = is_user_logged_in() ? new \WC_Customer( get_current_user_id() ) : ( function_exists( 'WC' ) ? WC()->customer : null );
		if ( ! $customer instanceof \WC_Customer ) {
			return $value;
		}
		$stored = Fields::stored_value( $customer, $field, $group );
		return '' === $stored || is_array( $stored ) ? $value : (string) $stored;
	}
}
