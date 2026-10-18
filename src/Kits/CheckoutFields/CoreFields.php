<?php
/**
 * Edits to WooCommerce's built-in checkout fields.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\CheckoutFields;

use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;

defined( 'ABSPATH' ) || exit;

/**
 * Applies the saved label, placeholder, required, hidden and priority edits of the built-in fields.
 *
 * Only properties the store owner changed are applied, so country rules (a country without postcodes,
 * a country's own name for "State") keep working for everything else.
 *
 * - Classic checkout and My Account: woocommerce_default_address_fields, woocommerce_billing_fields,
 *   woocommerce_shipping_fields and woocommerce_checkout_fields.
 * - Both checkouts (the block checkout reads nothing else for address fields): the country locale
 *   (woocommerce_get_country_locale, _default and _base), which carries label, required, hidden and
 *   priority (the block checkout's "index") for every country, and which the Store API validates against.
 * - Company, Apartment and Phone: their WooCommerce visibility settings (Optional / Required / Hidden),
 *   which the block checkout uses for its own defaults, read through pre_option filters so nothing is
 *   written to the database and switching the kit off restores the store's own settings.
 */
final class CoreFields {

	/**
	 * Visibility options: field => option name.
	 */
	const VISIBILITY = array(
		'company'   => 'woocommerce_checkout_company_field',
		'address_2' => 'woocommerce_checkout_address_2_field',
		'phone'     => 'woocommerce_checkout_phone_field',
	);

	/**
	 * True while WooCommerce's own defaults are being read.
	 *
	 * @var bool
	 */
	private static $suspended = false;

	/**
	 * Per-request cache of the defaults.
	 *
	 * @var array<string, array<string, array<string, mixed>>>|null
	 */
	private static $defaults = null;

	/**
	 * Hooks the edits.
	 */
	public static function hooks(): void {
		add_filter( 'woocommerce_default_address_fields', array( self::class, 'default_address_fields' ), 20 );
		add_filter( 'woocommerce_billing_fields', array( self::class, 'billing_fields' ), 20 );
		add_filter( 'woocommerce_shipping_fields', array( self::class, 'shipping_fields' ), 20 );
		add_filter( 'woocommerce_checkout_fields', array( self::class, 'checkout_fields' ), 20 );
		add_filter( 'woocommerce_get_country_locale', array( self::class, 'country_locale' ), 20 );
		add_filter( 'woocommerce_get_country_locale_default', array( self::class, 'locale_default' ), 20 );
		add_filter( 'woocommerce_get_country_locale_base', array( self::class, 'locale_default' ), 20 );
		foreach ( self::VISIBILITY as $option ) {
			add_filter( 'pre_option_' . $option, array( self::class, 'visibility_option' ), 20, 2 );
		}
	}

	/**
	 * Clears the cached defaults.
	 */
	public static function flush(): void {
		self::$defaults = null;
	}

	/**
	 * WooCommerce's own settings of the built-in fields, as shown on the settings screen and compared
	 * against when saving: section => field => label, placeholder, required, hidden, priority.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public static function defaults(): array {
		if ( null !== self::$defaults ) {
			return self::$defaults;
		}
		$was             = self::$suspended;
		self::$suspended = true;

		$visibility = array();
		foreach ( self::VISIBILITY as $key => $option ) {
			$visibility[ $key ] = self::store_visibility( $key );
		}
		// Read every address field, including those the store hides, so each has a label to show.
		$optional = static function () {
			return 'optional';
		};
		foreach ( self::VISIBILITY as $option ) {
			add_filter( 'pre_option_' . $option, $optional, 99 );
		}
		$address = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_default_address_fields() : array();
		foreach ( self::VISIBILITY as $option ) {
			remove_filter( 'pre_option_' . $option, $optional, 99 );
		}

		$defaults = array(
			'contact' => array(
				'email' => array(
					'label'       => __( 'Email address', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- WooCommerce's own label.
					'placeholder' => '',
					'priority'    => 110,
				),
			),
			'address' => array(),
			'order'   => array(
				'order_comments' => array(
					'label'       => __( 'Order notes', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- WooCommerce's own label.
					'placeholder' => __( 'Notes about your order, e.g. special notes for delivery.', 'woocommerce' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- WooCommerce's own placeholder.
					'required'    => false,
					'hidden'      => false,
				),
			),
		);
		foreach ( array_keys( Fields::CORE['address'] ) as $key ) {
			$field   = isset( $address[ $key ] ) && is_array( $address[ $key ] ) ? $address[ $key ] : array();
			$visible = $visibility[ $key ] ?? '';

			$defaults['address'][ $key ] = array(
				'label'       => (string) ( $field['label'] ?? $key ),
				'placeholder' => (string) ( $field['placeholder'] ?? '' ),
				'required'    => '' === $visible ? ! empty( $field['required'] ) : 'required' === $visible,
				'hidden'      => 'hidden' === $visible,
				'priority'    => (int) ( $field['priority'] ?? 0 ),
			);
		}

		self::$suspended = $was;
		self::$defaults  = $defaults;
		return $defaults;
	}

	/**
	 * The store's own visibility setting of Company, Apartment or Phone.
	 *
	 * @param string $key Field key.
	 */
	private static function store_visibility( string $key ): string {
		if ( class_exists( CartCheckoutUtils::class ) ) {
			if ( 'company' === $key ) {
				return (string) CartCheckoutUtils::get_company_field_visibility();
			}
			if ( 'address_2' === $key ) {
				return (string) CartCheckoutUtils::get_address_2_field_visibility();
			}
			return (string) CartCheckoutUtils::get_phone_field_visibility();
		}
		return (string) get_option( self::VISIBILITY[ $key ], 'optional' );
	}

	/**
	 * Saved edits of a section, filled with defaults for properties not changed: field => settings.
	 *
	 * @param string $section Section.
	 * @return array<string, array<string, mixed>>
	 */
	public static function effective( string $section ): array {
		$defaults = self::defaults()[ $section ] ?? array();
		$out      = array();
		foreach ( $defaults as $key => $values ) {
			$out[ $key ] = array_merge( $values, Fields::core_edits( $section )[ $key ] ?? array() );
		}
		return $out;
	}

	/**
	 * Visibility setting for Company, Apartment and Phone when their required/hidden state was edited.
	 *
	 * @param mixed  $pre    Short-circuit value (false to read the option).
	 * @param string $option Option name.
	 * @return mixed
	 */
	public static function visibility_option( $pre, $option ) {
		if ( self::$suspended ) {
			return $pre;
		}
		$key = array_search( $option, self::VISIBILITY, true );
		if ( false === $key ) {
			return $pre;
		}
		$edits = Fields::core_edits( 'address' )[ $key ] ?? array();
		if ( ! isset( $edits['required'] ) && ! isset( $edits['hidden'] ) ) {
			return $pre;
		}
		$field = self::effective( 'address' )[ $key ];
		if ( ! empty( $field['hidden'] ) ) {
			return 'hidden';
		}
		return empty( $field['required'] ) ? 'optional' : 'required';
	}

	/**
	 * Applies edits to one field definition (classic woocommerce_form_field() arguments).
	 *
	 * @param array<string, mixed> $field Field.
	 * @param array<string, mixed> $edits Edits.
	 * @return array<string, mixed>
	 */
	private static function apply( array $field, array $edits ): array {
		foreach ( array( 'label', 'placeholder' ) as $prop ) {
			if ( isset( $edits[ $prop ] ) && '' !== $edits[ $prop ] ) {
				$field[ $prop ] = (string) $edits[ $prop ];
			}
		}
		if ( isset( $edits['required'] ) ) {
			$field['required'] = (bool) $edits['required'];
		}
		if ( ! empty( $edits['priority'] ) ) {
			$field['priority'] = (int) $edits['priority'];
		}
		return $field;
	}

	/**
	 * Applies the address edits to a list of address fields keyed with a prefix ('' or 'billing_').
	 *
	 * @param array<string, mixed> $fields Fields.
	 * @param string               $prefix Key prefix.
	 * @return array<string, mixed>
	 */
	private static function apply_address( array $fields, string $prefix ): array {
		foreach ( Fields::core_edits( 'address' ) as $key => $edits ) {
			$name = $prefix . $key;
			if ( ! empty( $edits['hidden'] ) && 'country' !== $key ) {
				unset( $fields[ $name ] );
				continue;
			}
			if ( isset( $fields[ $name ] ) && is_array( $fields[ $name ] ) ) {
				$fields[ $name ] = self::apply( $fields[ $name ], 'country' === $key ? array_intersect_key( $edits, array_flip( array( 'label', 'priority' ) ) ) : $edits );
			}
		}
		return $fields;
	}

	/**
	 * Default address fields (classic checkout, My Account, and the default country locale).
	 *
	 * @param array<string, mixed> $fields Fields.
	 * @return array<string, mixed>
	 */
	public static function default_address_fields( $fields ) {
		$fields = (array) $fields;
		return self::$suspended ? $fields : self::apply_address( $fields, '' );
	}

	/**
	 * Billing fields: the address edits again (WooCommerce rebuilds the billing phone after the
	 * defaults), and the email field.
	 *
	 * @param array<string, mixed> $fields Fields.
	 * @return array<string, mixed>
	 */
	public static function billing_fields( $fields ) {
		$fields = (array) $fields;
		if ( self::$suspended ) {
			return $fields;
		}
		$fields = self::apply_address( $fields, 'billing_' );
		$email  = Fields::core_edits( 'contact' )['email'] ?? array();
		if ( $email && isset( $fields['billing_email'] ) && is_array( $fields['billing_email'] ) ) {
			$fields['billing_email'] = self::apply( $fields['billing_email'], array_intersect_key( $email, array_flip( array( 'label', 'placeholder', 'priority' ) ) ) );
		}
		return $fields;
	}

	/**
	 * Shipping fields.
	 *
	 * @param array<string, mixed> $fields Fields.
	 * @return array<string, mixed>
	 */
	public static function shipping_fields( $fields ) {
		$fields = (array) $fields;
		return self::$suspended ? $fields : self::apply_address( $fields, 'shipping_' );
	}

	/**
	 * Classic checkout: the order notes field.
	 *
	 * @param array<string, array<string, mixed>> $fields Fieldsets.
	 * @return array<string, array<string, mixed>>
	 */
	public static function checkout_fields( $fields ) {
		$fields = (array) $fields;
		$edits  = Fields::core_edits( 'order' )['order_comments'] ?? array();
		if ( self::$suspended || ! $edits || ! isset( $fields['order']['order_comments'] ) ) {
			return $fields;
		}
		if ( ! empty( $edits['hidden'] ) ) {
			unset( $fields['order']['order_comments'] );
			return $fields;
		}
		$fields['order']['order_comments'] = self::apply( (array) $fields['order']['order_comments'], $edits );
		return $fields;
	}

	/**
	 * Locale properties for the edited address fields, and the priority of extra address fields
	 * (the block checkout sorts address fields by it).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function locale_overrides(): array {
		$out = array();
		foreach ( Fields::core_edits( 'address' ) as $key => $edits ) {
			$props = array();
			foreach ( array( 'label', 'placeholder' ) as $prop ) {
				if ( isset( $edits[ $prop ] ) && '' !== $edits[ $prop ] ) {
					$props[ $prop ] = (string) $edits[ $prop ];
				}
			}
			if ( ! empty( $edits['priority'] ) ) {
				$props['priority'] = (int) $edits['priority'];
			}
			if ( 'country' !== $key ) {
				if ( isset( $edits['required'] ) ) {
					$props['required'] = (bool) $edits['required'];
				}
				if ( isset( $edits['hidden'] ) ) {
					$props['hidden'] = (bool) $edits['hidden'];
					if ( $edits['hidden'] ) {
						$props['required'] = false;
					}
				}
			}
			if ( $props ) {
				$out[ $key ] = $props;
			}
		}
		return $out;
	}

	/**
	 * Priorities of the extra address fields, for the block checkout's sort order.
	 *
	 * @return array<string, array<string, int>>
	 */
	private static function custom_priorities(): array {
		$out = array();
		foreach ( Fields::custom_fields( 'address' ) as $field ) {
			$out[ (string) $field['id'] ] = array( 'priority' => (int) $field['priority'] );
		}
		return $out;
	}

	/**
	 * Every country's address field settings (both checkouts, and Store API validation).
	 *
	 * @param array<string, array<string, mixed>> $locale Locale settings by country.
	 * @return array<string, array<string, mixed>>
	 */
	public static function country_locale( $locale ) {
		$locale = (array) $locale;
		if ( self::$suspended ) {
			return $locale;
		}
		$overrides = self::locale_overrides() + self::custom_priorities();
		if ( ! $overrides ) {
			return $locale;
		}
		$base = WC()->countries->get_base_country();
		foreach ( array_keys( WC()->countries->get_countries() ) as $country ) {
			// A base country without its own settings uses the default ones, which carry the edits too.
			if ( $country === $base && ! isset( $locale[ $country ] ) ) {
				continue;
			}
			$entry = isset( $locale[ $country ] ) && is_array( $locale[ $country ] ) ? $locale[ $country ] : array();
			foreach ( $overrides as $key => $props ) {
				$entry[ $key ] = array_merge( isset( $entry[ $key ] ) && is_array( $entry[ $key ] ) ? $entry[ $key ] : array(), $props );
			}
			$locale[ $country ] = $entry;
		}
		return $locale;
	}

	/**
	 * The default (and base country) locale: already built from the edited default address fields;
	 * fields the owner hid are added back as hidden, so the block checkout hides them too.
	 *
	 * @param array<string, mixed> $fields Default locale.
	 * @return array<string, mixed>
	 */
	public static function locale_default( $fields ) {
		$fields = (array) $fields;
		if ( self::$suspended ) {
			return $fields;
		}
		foreach ( self::locale_overrides() + self::custom_priorities() as $key => $props ) {
			if ( isset( $fields[ $key ] ) || ! empty( $props['hidden'] ) || isset( $props['priority'] ) ) {
				$fields[ $key ] = array_merge( isset( $fields[ $key ] ) && is_array( $fields[ $key ] ) ? $fields[ $key ] : array(), $props );
			}
		}
		return $fields;
	}
}
