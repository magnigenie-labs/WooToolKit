<?php
/**
 * The PDF core fonts Helvetica and Helvetica-Bold, WinAnsi encoded.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices\Pdf;

defined( 'ABSPATH' ) || exit;

/**
 * A standard Type 1 font every PDF viewer has built in, so nothing is embedded. Text is converted to
 * Windows-1252 (WinAnsiEncoding); characters outside it are transliterated (see WinAnsi). Widths are
 * the Adobe AFM widths (1/1000 em) for WinAnsi codes 32-255.
 */
final class CoreFont implements Font {

	/**
	 * Helvetica widths, codes 32-255.
	 */
	const HELVETICA = array(
		278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, // 32-47
		556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, // 48-63
		1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, // 64-79
		667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, // 80-95
		333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, // 96-111
		556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584, 350, // 112-127
		556, 350, 222, 556, 333, 1000, 556, 556, 333, 1000, 667, 333, 1000, 350, 611, 350, // 128-143
		350, 222, 222, 333, 333, 350, 556, 1000, 333, 1000, 500, 333, 944, 350, 500, 667, // 144-159
		278, 333, 556, 556, 556, 556, 260, 556, 333, 737, 370, 556, 584, 333, 737, 333, // 160-175
		400, 584, 333, 333, 333, 556, 537, 278, 333, 333, 365, 556, 834, 834, 834, 611, // 176-191
		667, 667, 667, 667, 667, 667, 1000, 722, 667, 667, 667, 667, 278, 278, 278, 278, // 192-207
		722, 722, 778, 778, 778, 778, 778, 584, 778, 722, 722, 722, 722, 667, 667, 611, // 208-223
		556, 556, 556, 556, 556, 556, 889, 500, 556, 556, 556, 556, 278, 278, 278, 278, // 224-239
		556, 556, 556, 556, 556, 556, 556, 584, 611, 556, 556, 556, 556, 500, 556, 500, // 240-255
	);

	/**
	 * Helvetica-Bold widths, codes 32-255.
	 */
	const HELVETICA_BOLD = array(
		278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, // 32-47
		556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, // 48-63
		975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, // 64-79
		667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, // 80-95
		333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, // 96-111
		611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584, 350, // 112-127
		556, 350, 278, 556, 500, 1000, 556, 556, 333, 1000, 667, 333, 1000, 350, 611, 350, // 128-143
		350, 278, 278, 500, 500, 350, 556, 1000, 333, 1000, 556, 333, 944, 350, 500, 667, // 144-159
		278, 333, 556, 556, 556, 556, 280, 556, 333, 737, 370, 556, 584, 333, 737, 333, // 160-175
		400, 584, 333, 333, 333, 611, 556, 278, 333, 333, 365, 556, 834, 834, 834, 611, // 176-191
		722, 722, 722, 722, 722, 722, 1000, 722, 667, 667, 667, 667, 278, 278, 278, 278, // 192-207
		722, 722, 778, 778, 778, 778, 778, 584, 778, 722, 722, 722, 722, 667, 667, 611, // 208-223
		556, 556, 556, 556, 556, 556, 889, 556, 556, 556, 556, 556, 278, 278, 278, 278, // 224-239
		611, 611, 611, 611, 611, 611, 611, 584, 611, 611, 611, 611, 611, 556, 611, 556, // 240-255
	);

	/**
	 * PostScript name: Helvetica or Helvetica-Bold.
	 *
	 * @var string
	 */
	private $base_font;

	/**
	 * Widths, codes 32-255.
	 *
	 * @var int[]
	 */
	private $widths;

	/**
	 * Constructor.
	 *
	 * @param bool $bold Helvetica-Bold instead of Helvetica.
	 */
	public function __construct( bool $bold = false ) {
		$this->base_font = $bold ? 'Helvetica-Bold' : 'Helvetica';
		$this->widths    = $bold ? self::HELVETICA_BOLD : self::HELVETICA;
	}

	/**
	 * The PostScript name.
	 */
	public function base_font(): string {
		return $this->base_font;
	}

	/**
	 * A PDF literal string of the text in WinAnsi.
	 *
	 * @param string $text UTF-8 text.
	 */
	public function encode( string $text ): string {
		return Writer::literal( WinAnsi::encode( $text ) );
	}

	/**
	 * Width of the text in points.
	 *
	 * @param string $text UTF-8 text.
	 * @param float  $size Font size in points.
	 */
	public function width( string $text, float $size ): float {
		$bytes = WinAnsi::encode( $text );
		$units = 0;
		$len   = strlen( $bytes );
		for ( $i = 0; $i < $len; $i++ ) {
			$code   = ord( $bytes[ $i ] );
			$units += $code >= 32 ? $this->widths[ $code - 32 ] : 0;
		}
		return $units * $size / 1000;
	}

	/**
	 * Writes the font dictionary.
	 *
	 * @param Writer $writer PDF writer.
	 * @return int Object number of the font dictionary.
	 */
	public function write( Writer $writer ): int {
		return $writer->add_object( '<< /Type /Font /Subtype /Type1 /BaseFont /' . $this->base_font . ' /Encoding /WinAnsiEncoding >>' );
	}
}
