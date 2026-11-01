<?php
/**
 * Extra fields in the Checkout block.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\CheckoutFields;

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields as WooCheckoutFields;
use Automattic\WooCommerce\Blocks\Package;
use WooToolkit\Kit\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the extra fields with WooCommerce's Additional Checkout Fields API, which the Checkout
 * block renders and the Store API validates and saves (order meta `_wc_other/…`, `_wc_billing/…`,
 * `_wc_shipping/…`), and which WooCommerce itself shows on the order screen, in emails and on the
 * order confirmation and My Account pages for orders placed through the block checkout.
 *
 * The block checkout supports the text, select, checkbox and date field types (WooCommerce 11.2), so a
 * text area is a one-line text input there, and email, phone and number fields are text inputs that
 * the kit validates on the server (number fields also get a browser pattern check).
 */
final class BlockCheckout {

	/**
	 * Ids registered by the kit in this request.
	 *
	 * @var string[]
	 */
	private static $registered = array();

	/**
	 * Hooks the block checkout. Fields are registered late on init, after every kit (add-ons included)
	 * has booted and added its filters.
	 */
	public static function hooks(): void {
		add_action( 'init', array( self::class, 'register' ), 20 );
	}

	/**
	 * Registers the enabled extra fields.
	 */
	public static function register(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}
		foreach ( Fields::all_custom_fields() as $field ) {
			if ( in_array( (string) $field['id'], self::$registered, true ) ) {
				continue;
			}
			if ( Support::is_storefront() && ! Fields::applies( $field, 'block' ) ) {
				continue;
			}
			$args = self::args( $field );
			if ( ! $args ) {
				continue;
			}
			try {
				woocommerce_register_additional_checkout_field( $args );
				self::$registered[] = (string) $field['id'];
			} catch ( \Exception $e ) {
				// An invalid field (WooCommerce reports why); the rest of checkout keeps working.
				unset( $e );
			}
		}
	}

	/**
	 * Removes the kit's fields from the block checkout registry (used before registering again in the
	 * same request, e.g. in tests).
	 */
	public static function unregister(): void {
		if ( class_exists( Package::class ) ) {
			$controller = Package::container()->get( WooCheckoutFields::class );
			foreach ( self::$registered as $id ) {
				$controller->deregister_checkout_field( $id );
			}
		}
		self::$registered = array();
	}

	/**
	 * Additional Checkout Fields arguments of a field, or an empty array if it cannot be shown in the
	 * block checkout (a classic-only type, or a dropdown without choices).
	 *
	 * @param array<string, mixed> $field Field.
	 * @return array<string, mixed>
	 */
	public static function args( array $field ): array {
		$type = Fields::field_type( $field );
		if ( empty( $type['block'] ) || ! Fields::has_value( $field ) ) {
			return array();
		}
		$block_type = (string) $type['block'];
		$as_text    = ! self::supports_type( $block_type );
		if ( $as_text ) {
			// An older WooCommerce: show it as a text field; the value is still checked when the order is placed.
			$type['block'] = 'text';
		}
		$args = array(
			'id'                         => (string) $field['id'],
			'label'                      => (string) $field['label'],
			'location'                   => (string) $field['location'],
			'type'                       => (string) $type['block'],
			'required'                   => ! empty( $field['required'] ),
			'show_in_order_confirmation' => true,
			'validate_callback'          => static function ( $value ) use ( $field ) {
				$error = Fields::validate_value( $field, $value );
				return $error ? $error : true;
			},
		);

		if ( 'select' === $type['block'] ) {
			$options = array();
			foreach ( Fields::choices( $field ) as $value => $label ) {
				$options[] = array(
					'value' => (string) $value,
					'label' => $label,
				);
			}
			if ( ! $options ) {
				return array();
			}
			$args['options'] = $options;
			if ( '' !== (string) $field['placeholder'] ) {
				$args['placeholder'] = (string) $field['placeholder'];
			}
		}

		if ( 'text' === $type['block'] ) {
			$args['sanitize_callback'] = static function ( $value ) use ( $field ) {
				return Fields::sanitize_value( $field, $value );
			};
			$attributes                = array();
			if ( 'email' === $type['validate'] ) {
				$attributes = array(
					'autocomplete'   => 'email',
					'autocapitalize' => 'none',
				);
			} elseif ( 'phone' === $type['validate'] ) {
				$attributes = array( 'autocomplete' => 'tel' );
			} elseif ( 'number' === $type['validate'] ) {
				$attributes = array(
					'pattern' => '-?[0-9]+([.,][0-9]+)?',
					'title'   => __( 'Numbers only', 'wootoolkit' ),
				);
			}
			if ( $as_text && 'date' === $block_type ) {
				$attributes = array(
					'pattern' => '[0-9]{4}-[0-9]{2}-[0-9]{2}',
					'title'   => __( 'Date as YYYY-MM-DD', 'wootoolkit' ),
				);
			}
			if ( $attributes ) {
				$args['attributes'] = $attributes;
			}
		}

		/**
		 * The woocommerce_register_additional_checkout_field() arguments of an extra field.
		 *
		 * @param array<string, mixed> $args  Arguments.
		 * @param array<string, mixed> $field Field.
		 */
		return (array) apply_filters( 'wootoolkit_checkout_fields_block_field_args', $args, $field );
	}

	/**
	 * Whether this WooCommerce's block checkout accepts a field type. Text, select and checkbox are always
	 * there; newer types (date, from WooCommerce 11.2) have their own class.
	 *
	 * @param string $type Block field type.
	 */
	public static function supports_type( string $type ): bool {
		if ( in_array( $type, array( 'text', 'select', 'checkbox' ), true ) ) {
			return true;
		}
		return class_exists( 'Automattic\\WooCommerce\\Blocks\\Domain\\Services\\CheckoutFieldTypes\\' . ucfirst( $type ) . 'FieldType' );
	}
}
