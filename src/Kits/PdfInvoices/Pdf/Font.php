<?php
/**
 * Font contract for the PDF writer.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices\Pdf;

defined( 'ABSPATH' ) || exit;

/**
 * A font the writer can draw with. CoreFont is the built-in Helvetica; other fonts (for example an
 * embedded TrueType font with full Unicode) implement the same three methods.
 */
interface Font {

	/**
	 * The text as a complete PDF string operand for Tj, e.g. "(Hello)" or "<00480065>".
	 * Called while drawing, so a font can record which characters it must embed.
	 *
	 * @param string $text UTF-8 text.
	 */
	public function encode( string $text ): string;

	/**
	 * Width of the text in points.
	 *
	 * @param string $text UTF-8 text.
	 * @param float  $size Font size in points.
	 */
	public function width( string $text, float $size ): float;

	/**
	 * Writes the font's objects when the document is output.
	 *
	 * @param Writer $writer PDF writer.
	 * @return int Object number of the font dictionary.
	 */
	public function write( Writer $writer ): int;
}
