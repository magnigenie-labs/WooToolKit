<?php
/**
 * Checkout fields kit.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\CheckoutFields;

use WooToolkit\Kit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Edit WooCommerce's checkout fields (label, placeholder, required, hidden, order) and add extra
 * fields, in the Checkout block and the classic checkout. See Fields for the data model and the hooks
 * add-ons can use.
 */
final class CheckoutFieldsKit implements Kit {

	const ID = 'checkout-fields';

	/**
	 * Stable kit id.
	 */
	public function id(): string {
		return self::ID;
	}

	/**
	 * Kit name.
	 */
	public function name(): string {
		return __( 'Checkout fields', 'wootoolkit' );
	}

	/**
	 * Kit description.
	 */
	public function description(): string {
		return __( 'Rename, reorder, hide or require the checkout fields, and add your own (text, dropdown, checkbox, email, phone, number) in the Checkout block and the classic checkout. Answers are saved on the order and shown in wp-admin, emails and My Account.', 'wootoolkit' );
	}

	/**
	 * A 2.0 kit.
	 */
	public function is_classic(): bool {
		return false;
	}

	/**
	 * Values are order meta saved through the CRUD API (HPOS), and the fields use the block checkout's
	 * own Additional Checkout Fields API.
	 *
	 * @return array<string, bool>
	 */
	public function compat(): array {
		return array(
			'hpos'   => true,
			'blocks' => true,
		);
	}

	/**
	 * Settings section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings(): array {
		$labels = Fields::section_labels();
		return array(
			array(
				'id'    => 'wootoolkit_checkout_fields',
				'type'  => 'title',
				'title' => __( 'Checkout fields', 'wootoolkit' ),
				'desc'  => __( 'Edit the built-in fields and add your own. Leave a label or placeholder empty to keep WooCommerce\'s. Extra fields work in the Checkout block and the classic checkout, are saved on the order (the same way for both), and are shown on the order screen, in order emails and on the customer\'s order pages.', 'wootoolkit' ),
			),
			array(
				'id'      => Fields::option( 'contact' ),
				'type'    => SettingsField::TYPE,
				'section' => 'contact',
				'title'   => $labels['contact'],
				'desc'    => __( 'Extra fields here appear in the Checkout block\'s Contact information section and in the classic checkout\'s billing form. The email field can only be changed in the classic checkout: the Checkout block always shows it, with its own label.', 'wootoolkit' ),
			),
			array(
				'id'      => Fields::option( 'address' ),
				'type'    => SettingsField::TYPE,
				'section' => 'address',
				'title'   => $labels['address'],
				'desc'    => __( 'Edits apply to both the billing and the shipping address, in every country (a country\'s own rules, such as no postcode, still apply to what you leave unchanged). Extra fields here are asked in both addresses. The Checkout block uses label, required, hidden and order, but not placeholders (it shows labels inside the inputs), and the country field cannot be hidden or made optional. Company, apartment and phone: required and hidden replace the visibility you set in the Checkout block\'s settings while this kit is on.', 'wootoolkit' ),
			),
			array(
				'id'      => Fields::option( 'order' ),
				'type'    => SettingsField::TYPE,
				'section' => 'order',
				'title'   => $labels['order'],
				'desc'    => __( 'Extra fields here appear in the Checkout block\'s "Additional order information" section and after the order notes in the classic checkout. The order notes field can only be changed in the classic checkout; in the Checkout block, switch it on or off in the block\'s settings.', 'wootoolkit' ),
			),
			array(
				'id'   => 'wootoolkit_checkout_fields',
				'type' => 'sectionend',
			),
			array(
				'id'    => 'wootoolkit_checkout_fields_limits',
				'type'  => 'title',
				'title' => __( 'What the Checkout block cannot do', 'wootoolkit' ),
				'desc'  => __( 'WooCommerce\'s Checkout block (WooCommerce 11.2) has text, dropdown, checkbox and date fields only: a text area is a one-line input there, and email, phone and number fields are text inputs that are checked when the order is placed. In the Contact and Order sections, extra fields always come after the built-in fields; address fields, built-in and extra, follow the Order numbers. Placeholders, the email field and the order notes field are classic checkout only.', 'wootoolkit' ),
			),
			array(
				'id'   => 'wootoolkit_checkout_fields_limits',
				'type' => 'sectionend',
			),
		);
	}

	/**
	 * Hooks the kit.
	 */
	public function boot(): void {
		add_action( 'woocommerce_update_options_wootoolkit_' . self::ID, array( Fields::class, 'flush' ) );
		SettingsField::hooks();
		CoreFields::hooks();
		BlockCheckout::hooks();
		ClassicCheckout::hooks();
		Display::hooks();
	}
}
