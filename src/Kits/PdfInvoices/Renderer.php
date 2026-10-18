<?php
/**
 * Renderer contract.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * Turns document data arrays (Data::build()) into one PDF. Swap it with the
 * wootoolkit_pdf_invoices_renderer filter.
 */
interface Renderer {

	/**
	 * Renders documents; each starts on a new page.
	 *
	 * @param array<int, array<string, mixed>> $documents Document data arrays.
	 * @return string PDF bytes.
	 */
	public function render( array $documents ): string;
}
