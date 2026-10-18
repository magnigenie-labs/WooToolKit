<?php
/**
 * Checkout fields: the saved configuration, field types, ids, and reading/writing values.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\CheckoutFields;

defined( 'ABSPATH' ) || exit;

/**
 * The configuration model shared by the block checkout, the classic checkout, the displays and the
 * settings screen.
 *
 * Three sections, each saved in its own option as array( 'core' => edits, 'custom' => fields ):
 * - contact: the email field (classic only) and extra fields in the block checkout's Contact section
 *   (classic: the billing form);
 * - address: the address fields, shared by billing and shipping (the block checkout has one set of
 *   address settings for both), and extra address fields (asked in both addresses);
 * - order:   the order notes field and extra fields in the block checkout's "Additional order
 *   information" section (classic: after the order notes).
 *
 * Every extra field has one id, `wootoolkit/<key>`, in both checkouts, and one value per group in the
 * order meta WooCommerce uses for Additional Checkout Fields: `_wc_other/wootoolkit/<key>` (contact and
 * order) or `_wc_billing/…` and `_wc_shipping/…` (address). The block checkout stores it itself; the
 * classic checkout stores it in the same keys, so wp-admin, emails and My Account read one value.
 *
 * Hooks for add-ons (WooToolkit Pro's Checkout fields Pro kit uses them):
 *
 * - filter `wootoolkit_checkout_fields_types` ( array $types ): field types, type => array(
 *   'label' => string, 'block' => 'text'|'select'|'checkbox'|'date'|null (null = classic checkout only),
 *   'classic' => woocommerce_form_field() type, 'options' => bool (needs a list of choices),
 *   'value' => bool (false for display-only types such as a heading), 'validate' => ''|'email'|'phone'|'number' ).
 * - filter `wootoolkit_checkout_fields_field_defaults` ( array $defaults ): default settings of a new
 *   extra field (add your own keys so they reach the settings row and the sanitizer).
 * - filter `wootoolkit_checkout_fields_sanitize_field` ( array $clean, array $raw, string $section ):
 *   one extra field as saved from the settings screen; keep your own keys clean here.
 * - action `wootoolkit_checkout_fields_row_settings` ( array $field, string $name_prefix, string $section ):
 *   prints extra settings controls under an extra field's row (inputs named `$name_prefix[your_key]`).
 * - filter `wootoolkit_checkout_fields_fields` ( array $fields, string $location ): the enabled extra
 *   fields of a location ('contact', 'address', 'order'), sorted, as used at checkout and in displays.
 * - filter `wootoolkit_checkout_fields_field_applies` ( bool $applies, array $field, string $context ):
 *   whether a field is offered in this checkout request; $context is 'block' (registration with the
 *   block checkout, at init) or 'classic' (building the classic form). Only asked on the storefront;
 *   values already saved on orders are always shown.
 * - filter `wootoolkit_checkout_fields_block_field_args` ( array $args, array $field ): the
 *   woocommerce_register_additional_checkout_field() arguments, e.g. to add `hidden` / `required`
 *   JSON-schema rules.
 * - filter `wootoolkit_checkout_fields_classic_field_args` ( array $args, array $field, string $group ):
 *   the woocommerce_form_field() arguments of a classic checkout field.
 * - filter `wootoolkit_checkout_fields_sanitize_value` ( mixed $value, mixed $raw, array $field ):
 *   a submitted value, before it is validated and saved.
 * - filter `wootoolkit_checkout_fields_validate_value` ( \WP_Error|null $error, mixed $value, array $field ):
 *   checks a non-empty submitted value (both checkouts); return a WP_Error to refuse it.
 * - filter `wootoolkit_checkout_fields_format_value` ( string $display, mixed $value, array $field ):
 *   a saved value as shown in wp-admin, emails and My Account.
 * - action `wootoolkit_checkout_fields_classic_saved` ( \WC_Order $order, array $values ): after the
 *   classic checkout saved the extra fields; $values is meta key => value.
 */
final class Fields {

	const SECTIONS  = array( 'contact', 'address', 'order' );
	const NAMESPACE = 'wootoolkit';

	/**
	 * Option name prefix: wootoolkit_checkout_fields_<section>.
	 */
	const OPTION_PREFIX = 'wootoolkit_checkout_fields_';

	/**
	 * Group meta key prefixes, as used by WooCommerce's Additional Checkout Fields.
	 */
	const GROUP_PREFIXES = array(
		'billing'  => '_wc_billing/',
		'shipping' => '_wc_shipping/',
		'other'    => '_wc_other/',
	);

	/**
	 * Built-in fields each section can edit, and which properties apply to each.
	 */
	const CORE = array(
		'contact' => array(
			'email' => array( 'label', 'placeholder', 'priority' ),
		),
		'address' => array(
			'first_name' => array( 'label', 'placeholder', 'required', 'hidden', 'priority' ),
			'last_name'  => array( 'label', 'placeholder', 'required', 'hidden', 'priority' ),
			'company'    => array( 'label', 'placeholder', 'required', 'hidden', 'priority' ),
			'country'    => array( 'label', 'priority' ),
			'address_1'  => array( 'label', 'placeholder', 'required', 'hidden', 'priority' ),
			'address_2'  => array( 'label', 'placeholder', 'required', 'hidden', 'priority' ),
			'city'       => array( 'label', 'placeholder', 'required', 'hidden', 'priority' ),
			'state'      => array( 'label', 'placeholder', 'required', 'hidden', 'priority' ),
			'postcode'   => array( 'label', 'placeholder', 'required', 'hidden', 'priority' ),
			'phone'      => array( 'label', 'placeholder', 'required', 'hidden', 'priority' ),
		),
		'order'   => array(
			'order_comments' => array( 'label', 'placeholder', 'required', 'hidden' ),
		),
	);

	/**
	 * Per-request cache of the section options.
	 *
	 * @var array<string, array{core: array<string, array<string, mixed>>, custom: array<int, array<string, mixed>>}>
	 */
	private static $config = array();

	/**
	 * Clears the per-request caches (after saving settings, and in tests).
	 */
	public static function flush(): void {
		self::$config = array();
		CoreFields::flush();
		ClassicCheckout::flush();
		SettingsField::flush();
		if ( function_exists( 'WC' ) && WC()->countries ) {
			WC()->countries->locale = array();
		}
	}

	/**
	 * Option name of a section.
	 *
	 * @param string $section Section.
	 */
	public static function option( string $section ): string {
		return self::OPTION_PREFIX . $section;
	}

	/**
	 * Section names for the settings screen.
	 *
	 * @return array<string, string>
	 */
	public static function section_labels(): array {
		return array(
			'contact' => __( 'Contact', 'wootoolkit' ),
			'address' => __( 'Address (billing and shipping)', 'wootoolkit' ),
			'order'   => __( 'Order', 'wootoolkit' ),
		);
	}

	/**
	 * Field types.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function types(): array {
		$types = array(
			'text'     => array(
				'label' => __( 'Text', 'wootoolkit' ),
				'block' => 'text',
			),
			'textarea' => array(
				'label' => __( 'Text area (one line in the block checkout)', 'wootoolkit' ),
				'block' => 'text',
			),
			'select'   => array(
				'label'   => __( 'Dropdown', 'wootoolkit' ),
				'block'   => 'select',
				'options' => true,
			),
			'checkbox' => array(
				'label' => __( 'Checkbox', 'wootoolkit' ),
				'block' => 'checkbox',
			),
			'email'    => array(
				'label'    => __( 'Email', 'wootoolkit' ),
				'block'    => 'text',
				'validate' => 'email',
			),
			'tel'      => array(
				'label'    => __( 'Phone', 'wootoolkit' ),
				'block'    => 'text',
				'validate' => 'phone',
			),
			'number'   => array(
				'label'    => __( 'Number', 'wootoolkit' ),
				'block'    => 'text',
				'validate' => 'number',
			),
		);

		/**
		 * Field types for extra checkout fields. See the Fields class docblock for the keys.
		 *
		 * @param array<string, array<string, mixed>> $types Types.
		 */
		$types = (array) apply_filters( 'wootoolkit_checkout_fields_types', $types );

		$out = array();
		foreach ( $types as $slug => $type ) {
			if ( ! is_array( $type ) || '' === sanitize_key( (string) $slug ) ) {
				continue;
			}
			$out[ sanitize_key( (string) $slug ) ] = array_merge(
				array(
					'label'    => (string) $slug,
					'block'    => null,
					'classic'  => (string) $slug,
					'options'  => false,
					'value'    => true,
					'validate' => '',
				),
				$type
			);
		}
		return $out;
	}

	/**
	 * One type's definition (text for unknown types).
	 *
	 * @param string $type Type slug.
	 * @return array<string, mixed>
	 */
	public static function type( string $type ): array {
		$types = self::types();
		return $types[ $type ] ?? $types['text'];
	}

	/**
	 * Default settings of a new extra field.
	 *
	 * @return array<string, mixed>
	 */
	public static function field_defaults(): array {
		$defaults = array(
			'key'         => '',
			'label'       => '',
			'placeholder' => '',
			'type'        => 'text',
			'options'     => array(),
			'required'    => false,
			'hidden'      => false,
			'priority'    => 0,
		);

		/**
		 * Default settings of a new extra checkout field. Add-ons add their own keys here.
		 *
		 * @param array<string, mixed> $defaults Defaults.
		 */
		return array_merge( $defaults, (array) apply_filters( 'wootoolkit_checkout_fields_field_defaults', $defaults ) );
	}

	/**
	 * A section's saved configuration.
	 *
	 * @param string $section Section.
	 * @return array{core: array<string, array<string, mixed>>, custom: array<int, array<string, mixed>>}
	 */
	public static function config( string $section ): array {
		if ( ! isset( self::$config[ $section ] ) ) {
			$saved = in_array( $section, self::SECTIONS, true ) ? get_option( self::option( $section ), array() ) : array();
			$saved = is_array( $saved ) ? $saved : array();

			self::$config[ $section ] = array(
				'core'   => isset( $saved['core'] ) && is_array( $saved['core'] ) ? array_intersect_key( $saved['core'], self::CORE[ $section ] ?? array() ) : array(),
				'custom' => isset( $saved['custom'] ) && is_array( $saved['custom'] ) ? array_values( array_filter( $saved['custom'], 'is_array' ) ) : array(),
			);
		}
		return self::$config[ $section ];
	}

	/**
	 * Edits saved for the built-in fields of a section: field key => changed properties.
	 *
	 * @param string $section Section.
	 * @return array<string, array<string, mixed>>
	 */
	public static function core_edits( string $section ): array {
		return self::config( $section )['core'];
	}

	/**
	 * The location (block checkout section) of a settings section.
	 *
	 * @param string $section Section.
	 */
	public static function location( string $section ): string {
		return in_array( $section, self::SECTIONS, true ) ? $section : 'order';
	}

	/**
	 * The enabled extra fields of a location, sorted by priority, with derived keys:
	 * 'id' (wootoolkit/<key>), 'location' and 'section'.
	 *
	 * @param string $location 'contact', 'address' or 'order'.
	 * @return array<int, array<string, mixed>>
	 */
	public static function custom_fields( string $location ): array {
		$fields = array();
		foreach ( self::config( $location )['custom'] as $index => $field ) {
			$field = array_merge( self::field_defaults(), $field );
			if ( '' === (string) $field['key'] || '' === (string) $field['label'] || ! empty( $field['hidden'] ) ) {
				continue;
			}
			$field['id']       = self::NAMESPACE . '/' . $field['key'];
			$field['location'] = $location;
			$field['section']  = $location;
			$field['priority'] = (int) $field['priority'] ? (int) $field['priority'] : self::default_priority( $location, (int) $index );
			$fields[]          = $field;
		}
		usort(
			$fields,
			static function ( $a, $b ) {
				return $a['priority'] <=> $b['priority'];
			}
		);

		/**
		 * The enabled extra checkout fields of a location, in display order.
		 *
		 * @param array<int, array<string, mixed>> $fields   Fields.
		 * @param string                           $location 'contact', 'address' or 'order'.
		 */
		return array_values( array_filter( (array) apply_filters( 'wootoolkit_checkout_fields_fields', $fields, $location ), 'is_array' ) );
	}

	/**
	 * Every enabled extra field, all locations.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all_custom_fields(): array {
		$all = array();
		foreach ( self::SECTIONS as $location ) {
			$all = array_merge( $all, self::custom_fields( $location ) );
		}
		return $all;
	}

	/**
	 * Default priority of the n-th extra field of a location (after the built-in fields).
	 *
	 * @param string $location Location.
	 * @param int    $index    Position in the table.
	 */
	public static function default_priority( string $location, int $index ): int {
		$base = array(
			'contact' => 120,
			'address' => 110,
			'order'   => 20,
		);
		return ( $base[ $location ] ?? 200 ) + $index;
	}

	/**
	 * Groups a field's value is stored in.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return string[]
	 */
	public static function groups( array $field ): array {
		return 'address' === ( $field['location'] ?? '' ) ? array( 'billing', 'shipping' ) : array( 'other' );
	}

	/**
	 * Order/customer meta key of a field's value in a group.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param string               $group 'billing', 'shipping' or 'other'.
	 */
	public static function meta_key( array $field, string $group ): string {
		$prefix = self::GROUP_PREFIXES[ $group ] ?? self::GROUP_PREFIXES['other'];
		return $prefix . (string) $field['id'];
	}

	/**
	 * Classic checkout input name of a field in a group: billing_wootoolkit_<key> / shipping_wootoolkit_<key>
	 * for address fields (so WooCommerce copies billing to shipping when there is no separate shipping
	 * address), wootoolkit_<key> otherwise.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param string               $group Group.
	 */
	public static function classic_key( array $field, string $group ): string {
		$key = self::NAMESPACE . '_' . str_replace( '-', '_', (string) $field['key'] );
		return 'other' === $group ? $key : $group . '_' . $key;
	}

	/**
	 * The field type definition of a field.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return array<string, mixed>
	 */
	public static function field_type( array $field ): array {
		return self::type( (string) ( $field['type'] ?? 'text' ) );
	}

	/**
	 * Whether a field holds a value (headings and other display-only types do not).
	 *
	 * @param array<string, mixed> $field Field.
	 */
	public static function has_value( array $field ): bool {
		return ! empty( self::field_type( $field )['value'] );
	}

	/**
	 * Whether a field is offered in this checkout request.
	 *
	 * @param array<string, mixed> $field   Field.
	 * @param string               $context 'block' or 'classic'.
	 */
	public static function applies( array $field, string $context ): bool {
		/**
		 * Whether an extra checkout field is offered in this request.
		 *
		 * @param bool                 $applies Whether it applies.
		 * @param array<string, mixed> $field   Field.
		 * @param string               $context 'block' or 'classic'.
		 */
		return (bool) apply_filters( 'wootoolkit_checkout_fields_field_applies', true, $field, $context );
	}

	/**
	 * Choices of a select-like field: value => label.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return array<string, string>
	 */
	public static function choices( array $field ): array {
		$out = array();
		foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
			if ( is_array( $option ) && isset( $option['value'], $option['label'] ) && '' !== (string) $option['value'] ) {
				$out[ (string) $option['value'] ] = (string) $option['label'];
			}
		}
		return $out;
	}

	/**
	 * Parses the settings screen's options text: one per line, "value | Label" or just "Label".
	 *
	 * @param string $text Text.
	 * @return array<int, array{value: string, label: string}>
	 */
	public static function parse_options( string $text ): array {
		$out   = array();
		$seen  = array();
		$lines = preg_split( '/\r\n|\r|\n/', $text );
		foreach ( is_array( $lines ) ? $lines : array() as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$label = sanitize_text_field( $parts[1] ?? $parts[0] );
			$value = sanitize_text_field( $parts[0] );
			if ( '' === $value || '' === $label || isset( $seen[ $value ] ) ) {
				continue;
			}
			$seen[ $value ] = true;
			$out[]          = array(
				'value' => $value,
				'label' => $label,
			);
		}
		return $out;
	}

	/**
	 * The options text shown on the settings screen.
	 *
	 * @param array<int, array<string, string>> $options Options.
	 */
	public static function options_text( array $options ): string {
		$lines = array();
		foreach ( $options as $option ) {
			if ( ! is_array( $option ) || ! isset( $option['value'], $option['label'] ) ) {
				continue;
			}
			$lines[] = $option['value'] === $option['label'] ? $option['label'] : $option['value'] . ' | ' . $option['label'];
		}
		return implode( "\n", $lines );
	}

	/**
	 * Cleans a submitted value for a field.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param mixed                $raw   Submitted value (unslashed).
	 * @return mixed
	 */
	public static function sanitize_value( array $field, $raw ) {
		$type = (string) ( $field['type'] ?? 'text' );
		if ( 'checkbox' === $type ) {
			$value = ( true === $raw || '1' === $raw || 1 === $raw || 'true' === $raw || 'yes' === $raw || 'on' === $raw ) ? '1' : '0';
		} elseif ( is_array( $raw ) || is_object( $raw ) ) {
			$value = '';
		} elseif ( 'textarea' === $type ) {
			$value = sanitize_textarea_field( (string) $raw );
		} elseif ( 'email' === $type ) {
			$value = trim( sanitize_text_field( (string) $raw ) );
		} else {
			$value = sanitize_text_field( (string) $raw );
		}

		/**
		 * A submitted extra field value, after the free kit cleaned it.
		 *
		 * @param mixed                $value Clean value.
		 * @param mixed                $raw   Submitted value.
		 * @param array<string, mixed> $field Field.
		 */
		return apply_filters( 'wootoolkit_checkout_fields_sanitize_value', $value, $raw, $field );
	}

	/**
	 * Whether a clean value counts as empty (an unticked checkbox is empty).
	 *
	 * @param array<string, mixed> $field Field.
	 * @param mixed                $value Value.
	 */
	public static function is_empty( array $field, $value ): bool {
		if ( 'checkbox' === ( $field['type'] ?? '' ) ) {
			return '1' !== $value && true !== $value;
		}
		if ( is_array( $value ) ) {
			return ! array_filter(
				$value,
				static function ( $item ) {
					return '' !== trim( (string) $item );
				}
			);
		}
		return '' === trim( (string) $value );
	}

	/**
	 * Checks a non-empty clean value.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param mixed                $value Clean value.
	 */
	public static function validate_value( array $field, $value ): ?\WP_Error {
		$error = null;
		$label = (string) $field['label'];
		$type  = self::field_type( $field );

		if ( ! self::is_empty( $field, $value ) && is_scalar( $value ) ) {
			$value = (string) $value;
			if ( 'email' === $type['validate'] && ! is_email( $value ) ) {
				/* translators: %s: field label */
				$error = new \WP_Error( 'wootoolkit_checkout_fields_invalid', sprintf( __( '%s is not a valid email address.', 'wootoolkit' ), $label ) );
			} elseif ( 'phone' === $type['validate'] && ! \WC_Validation::is_phone( $value ) ) {
				/* translators: %s: field label */
				$error = new \WP_Error( 'wootoolkit_checkout_fields_invalid', sprintf( __( '%s is not a valid phone number.', 'wootoolkit' ), $label ) );
			} elseif ( 'number' === $type['validate'] && ! is_numeric( str_replace( ',', '.', $value ) ) ) {
				/* translators: %s: field label */
				$error = new \WP_Error( 'wootoolkit_checkout_fields_invalid', sprintf( __( '%s must be a number.', 'wootoolkit' ), $label ) );
			} elseif ( ! empty( $type['options'] ) && 'select' === ( $field['type'] ?? '' ) && ! isset( self::choices( $field )[ $value ] ) ) {
				/* translators: %s: field label */
				$error = new \WP_Error( 'wootoolkit_checkout_fields_invalid', sprintf( __( 'Please choose a valid %s.', 'wootoolkit' ), $label ) );
			}
		}

		/**
		 * Checks a submitted extra field value. Return a WP_Error to refuse it.
		 *
		 * @param \WP_Error|null       $error Error, or null when valid.
		 * @param mixed                $value Clean value.
		 * @param array<string, mixed> $field Field.
		 */
		$error = apply_filters( 'wootoolkit_checkout_fields_validate_value', $error, $value, $field );
		return $error instanceof \WP_Error && $error->has_errors() ? $error : null;
	}

	/**
	 * A saved value as shown to people.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param mixed                $value Saved value.
	 */
	public static function format_value( array $field, $value ): string {
		$type = (string) ( $field['type'] ?? 'text' );
		if ( 'checkbox' === $type ) {
			$display = self::is_empty( $field, $value ) ? __( 'No', 'wootoolkit' ) : __( 'Yes', 'wootoolkit' );
		} elseif ( is_array( $value ) ) {
			$display = implode( ', ', array_map( 'strval', $value ) );
		} else {
			$value   = (string) $value;
			$choices = self::choices( $field );
			$display = isset( $choices[ $value ] ) ? $choices[ $value ] : $value;
		}

		/**
		 * An extra field value as shown in wp-admin, emails and My Account.
		 *
		 * @param string               $display Text.
		 * @param mixed                $value   Saved value.
		 * @param array<string, mixed> $field   Field.
		 */
		return (string) apply_filters( 'wootoolkit_checkout_fields_format_value', $display, $value, $field );
	}

	/**
	 * A field's saved value on an order (or customer), '' when none.
	 *
	 * @param \WC_Data             $wc_object Order or customer.
	 * @param array<string, mixed> $field     Field.
	 * @param string               $group     Group.
	 * @return mixed
	 */
	public static function stored_value( \WC_Data $wc_object, array $field, string $group ) {
		$value = $wc_object->get_meta( self::meta_key( $field, $group ), true );
		return null === $value ? '' : $value;
	}
}
