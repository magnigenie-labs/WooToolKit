<?php
/**
 * PDF invoices & packing slips kit.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices;

use WooToolkit\Kit\AbstractKit;

defined( 'ABSPATH' ) || exit;

/**
 * A PDF invoice and packing slip per order, made by a built-in PDF writer (no libraries): download
 * from the order screen, the orders list and My Account, and attached to chosen emails.
 */
final class PdfInvoicesKit extends AbstractKit {

	/**
	 * Stable kit id.
	 */
	public function id(): string {
		return 'pdf-invoices';
	}

	/**
	 * Kit name.
	 */
	public function name(): string {
		return __( 'PDF invoices & packing slips', 'wootoolkit' );
	}

	/**
	 * Kit description.
	 */
	public function description(): string {
		return __( 'A PDF invoice with a sequential invoice number and a packing slip for every order. Open them from the order screen or the orders list, attach the invoice to order emails, and let customers download it from My Account.', 'wootoolkit' );
	}

	/**
	 * Orders are read through the CRUD API (HPOS); nothing changes in the cart or checkout.
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
		return Settings::fields();
	}

	/**
	 * Hooks the kit.
	 */
	public function boot(): void {
		Attachments::hooks();
		Downloads::hooks();
		Account::hooks();
		add_filter( 'wootoolkit_dashboard_attention', array( OrderAdmin::class, 'attention' ) );
		if ( is_admin() ) {
			OrderAdmin::hooks();
		}
	}
}
