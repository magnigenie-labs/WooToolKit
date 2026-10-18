<?php
/**
 * A small, dependency-free PDF 1.4 writer.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices\Pdf;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a PDF page by page: text (measured, wrapped and aligned with the font's widths), lines,
 * filled rectangles and JPEG/PNG images. Coordinates are in points from the top-left corner of the
 * page; text is placed by its baseline. Fonts are registered under aliases ("regular", "bold") so a
 * layout can switch the font family without changing.
 *
 * Usage: add_font(), add_page(), draw, output(). output() returns the complete file.
 */
class Writer {

	/**
	 * Page sizes in points.
	 */
	const SIZES = array(
		'a4'     => array( 595.28, 841.89 ),
		'letter' => array( 612.0, 792.0 ),
	);

	/**
	 * Page width.
	 *
	 * @var float
	 */
	protected $width;

	/**
	 * Page height.
	 *
	 * @var float
	 */
	protected $height;

	/**
	 * Whether streams are compressed with zlib.
	 *
	 * @var bool
	 */
	protected $compress;

	/**
	 * Object bodies by object number (1-based). Null while reserved.
	 *
	 * @var array<int, string|null>
	 */
	protected $objects = array();

	/**
	 * Content stream of each page (0-based).
	 *
	 * @var string[]
	 */
	protected $pages = array();

	/**
	 * Index of the page being drawn on.
	 *
	 * @var int
	 */
	protected $page = -1;

	/**
	 * Fonts by alias.
	 *
	 * @var array<string, Font>
	 */
	protected $fonts = array();

	/**
	 * Resource names of the fonts by alias (F1, F2...).
	 *
	 * @var array<string, string>
	 */
	protected $font_names = array();

	/**
	 * Images: key => array( name, object number, width, height ).
	 *
	 * @var array<string, array{0: string, 1: int, 2: int, 3: int}>
	 */
	protected $images = array();

	/**
	 * Current font alias.
	 *
	 * @var string
	 */
	protected $font = '';

	/**
	 * Current font size.
	 *
	 * @var float
	 */
	protected $size = 10.0;

	/**
	 * Text colour, as a PDF colour operand ("r g b").
	 *
	 * @var string
	 */
	protected $text_color = '0 0 0';

	/**
	 * Fill colour.
	 *
	 * @var string
	 */
	protected $fill_color = '0 0 0';

	/**
	 * Stroke colour.
	 *
	 * @var string
	 */
	protected $stroke_color = '0 0 0';

	/**
	 * Document information (Title, Author, Subject).
	 *
	 * @var array<string, string>
	 */
	protected $info = array();

	/**
	 * Constructor.
	 *
	 * @param string $size     Page size: "a4" or "letter".
	 * @param bool   $compress Compress streams (only when zlib is available).
	 */
	public function __construct( string $size = 'a4', bool $compress = true ) {
		list( $this->width, $this->height ) = isset( self::SIZES[ $size ] ) ? self::SIZES[ $size ] : self::SIZES['a4'];
		$this->compress                     = $compress && function_exists( 'gzcompress' );
		$this->objects                      = array(
			1 => null, // Catalog.
			2 => null, // Page tree.
		);
	}

	/**
	 * Page width in points.
	 */
	public function width(): float {
		return $this->width;
	}

	/**
	 * Page height in points.
	 */
	public function height(): float {
		return $this->height;
	}

	/**
	 * Registers a font under an alias. The first font registered becomes the current one.
	 *
	 * @param string $alias Alias, e.g. "regular".
	 * @param Font   $font  Font.
	 */
	public function add_font( string $alias, Font $font ): void {
		$this->fonts[ $alias ] = $font;
		if ( ! isset( $this->font_names[ $alias ] ) ) {
			$this->font_names[ $alias ] = 'F' . ( count( $this->font_names ) + 1 );
		}
		if ( '' === $this->font ) {
			$this->font = $alias;
		}
	}

	/**
	 * A registered font.
	 *
	 * @param string $alias Alias.
	 */
	public function font( string $alias ): ?Font {
		return isset( $this->fonts[ $alias ] ) ? $this->fonts[ $alias ] : null;
	}

	/**
	 * Sets Title, Author or Subject of the document information.
	 *
	 * @param string $key   Title, Author or Subject.
	 * @param string $value UTF-8 value.
	 */
	public function set_info( string $key, string $value ): void {
		$this->info[ $key ] = $value;
	}

	/**
	 * Starts a new page and draws on it.
	 */
	public function add_page(): void {
		$this->pages[] = '';
		$this->page    = count( $this->pages ) - 1;
	}

	/**
	 * Number of pages.
	 */
	public function page_count(): int {
		return count( $this->pages );
	}

	/**
	 * Draws on an earlier page (1-based), e.g. to add "Page 1 of 3" once the page count is known.
	 *
	 * @param int $number Page number.
	 */
	public function go_to_page( int $number ): void {
		if ( $number >= 1 && $number <= count( $this->pages ) ) {
			$this->page = $number - 1;
		}
	}

	/**
	 * Current page number (1-based, 0 before the first page).
	 */
	public function page_number(): int {
		return $this->page + 1;
	}

	/**
	 * Sets the font and size.
	 *
	 * @param string $alias Registered alias.
	 * @param float  $size  Size in points.
	 */
	public function set_font( string $alias, float $size ): void {
		if ( isset( $this->fonts[ $alias ] ) ) {
			$this->font = $alias;
		}
		$this->size = $size;
	}

	/**
	 * Current font size.
	 */
	public function font_size(): float {
		return $this->size;
	}

	/**
	 * Sets the text colour.
	 *
	 * @param float[] $rgb Red, green, blue, each 0-1.
	 */
	public function set_text_color( array $rgb ): void {
		$this->text_color = self::color( $rgb );
	}

	/**
	 * Sets the fill colour for rectangles.
	 *
	 * @param float[] $rgb Red, green, blue, each 0-1.
	 */
	public function set_fill_color( array $rgb ): void {
		$this->fill_color = self::color( $rgb );
	}

	/**
	 * Sets the stroke colour for lines.
	 *
	 * @param float[] $rgb Red, green, blue, each 0-1.
	 */
	public function set_stroke_color( array $rgb ): void {
		$this->stroke_color = self::color( $rgb );
	}

	/**
	 * Width of text in the current (or given) font and size.
	 *
	 * @param string      $text  UTF-8 text.
	 * @param string|null $alias Font alias, current when null.
	 * @param float|null  $size  Size, current when null.
	 */
	public function text_width( string $text, ?string $alias = null, ?float $size = null ): float {
		$font = $this->fonts[ null === $alias ? $this->font : $alias ] ?? null;
		return $font ? $font->width( $text, null === $size ? $this->size : $size ) : 0.0;
	}

	/**
	 * Draws one line of text.
	 *
	 * @param float  $x     Left edge for "left", right edge for "right", middle for "center".
	 * @param float  $y     Baseline, from the top of the page.
	 * @param string $text  UTF-8 text.
	 * @param string $align left, right or center.
	 */
	public function text( float $x, float $y, string $text, string $align = 'left' ): void {
		$font = $this->fonts[ $this->font ] ?? null;
		if ( null === $font || '' === $text || $this->page < 0 ) {
			return;
		}
		if ( 'right' === $align ) {
			$x -= $font->width( $text, $this->size );
		} elseif ( 'center' === $align ) {
			$x -= $font->width( $text, $this->size ) / 2;
		}
		$this->out(
			sprintf(
				'BT /%s %s Tf %s rg %s %s Td %s Tj ET',
				$this->font_names[ $this->font ],
				self::num( $this->size ),
				$this->text_color,
				self::num( $x ),
				self::num( $this->height - $y ),
				$font->encode( $text )
			)
		);
	}

	/**
	 * Splits text into lines no wider than $width, at spaces where possible. Newlines are kept.
	 *
	 * @param string      $text  UTF-8 text.
	 * @param float       $width Maximum width in points.
	 * @param string|null $alias Font alias, current when null.
	 * @param float|null  $size  Size, current when null.
	 * @return string[]
	 */
	public function wrap( string $text, float $width, ?string $alias = null, ?float $size = null ): array {
		$lines = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) ?: array() as $paragraph ) { // phpcs:ignore Universal.Operators.DisallowShortTernary.Found -- preg_split returns false on error.
			$paragraph = trim( (string) preg_replace( '/[ \t]+/', ' ', $paragraph ) );
			if ( '' === $paragraph ) {
				$lines[] = '';
				continue;
			}
			$line = '';
			foreach ( explode( ' ', $paragraph ) as $word ) {
				$try = '' === $line ? $word : $line . ' ' . $word;
				if ( $this->text_width( $try, $alias, $size ) <= $width ) {
					$line = $try;
					continue;
				}
				if ( '' !== $line ) {
					$lines[] = $line;
				}
				// A word wider than the line is broken between characters.
				$line = '';
				foreach ( preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY ) ?: array() as $char ) { // phpcs:ignore Universal.Operators.DisallowShortTernary.Found -- preg_split returns false on error.
					if ( '' !== $line && $this->text_width( $line . $char, $alias, $size ) > $width ) {
						$lines[] = $line;
						$line    = '';
					}
					$line .= $char;
				}
			}
			$lines[] = $line;
		}
		return $lines;
	}

	/**
	 * Draws a line.
	 *
	 * @param float $x1        Start x.
	 * @param float $y1        Start y.
	 * @param float $x2        End x.
	 * @param float $y2        End y.
	 * @param float $thickness Line width in points.
	 */
	public function line( float $x1, float $y1, float $x2, float $y2, float $thickness = 0.5 ): void {
		$this->out(
			sprintf(
				'%s w %s RG %s %s m %s %s l S',
				self::num( $thickness ),
				$this->stroke_color,
				self::num( $x1 ),
				self::num( $this->height - $y1 ),
				self::num( $x2 ),
				self::num( $this->height - $y2 )
			)
		);
	}

	/**
	 * Draws a rectangle.
	 *
	 * @param float  $x     Left.
	 * @param float  $y     Top.
	 * @param float  $w     Width.
	 * @param float  $h     Height.
	 * @param string $style F (fill), S (stroke) or B (both).
	 */
	public function rect( float $x, float $y, float $w, float $h, string $style = 'F' ): void {
		$op = in_array( $style, array( 'F', 'S', 'B' ), true ) ? $style : 'F';
		$this->out(
			sprintf(
				'%s rg %s RG %s %s %s %s re %s',
				$this->fill_color,
				$this->stroke_color,
				self::num( $x ),
				self::num( $this->height - $y - $h ),
				self::num( $w ),
				self::num( $h ),
				$op
			)
		);
	}

	/**
	 * Pixel size of an image file, or null when it cannot be used.
	 *
	 * @param string $path Local JPEG or PNG file.
	 * @return int[]|null array( width, height )
	 */
	public function image_size( string $path ): ?array {
		$key = $this->load_image( $path );
		return null === $key ? null : array( $this->images[ $key ][2], $this->images[ $key ][3] );
	}

	/**
	 * Draws an image. Returns false when the file is not a usable JPEG or PNG.
	 *
	 * @param string $path Local JPEG or PNG file.
	 * @param float  $x    Left.
	 * @param float  $y    Top.
	 * @param float  $w    Width in points.
	 * @param float  $h    Height in points.
	 */
	public function image( string $path, float $x, float $y, float $w, float $h ): bool {
		$key = $this->load_image( $path );
		if ( null === $key || $this->page < 0 ) {
			return false;
		}
		$this->out( sprintf( 'q %s 0 0 %s %s %s cm /%s Do Q', self::num( $w ), self::num( $h ), self::num( $x ), self::num( $this->height - $y - $h ), $this->images[ $key ][0] ) );
		return true;
	}

	/**
	 * Reserves an object number, for objects that refer to each other.
	 */
	public function reserve_object(): int {
		$this->objects[] = null;
		return (int) max( array_keys( $this->objects ) );
	}

	/**
	 * Adds an object (its body, without "n 0 obj" and "endobj") and returns its number.
	 *
	 * @param string   $body   Object body.
	 * @param int|null $number A reserved object number to fill.
	 */
	public function add_object( string $body, ?int $number = null ): int {
		$number                   = null === $number ? $this->reserve_object() : $number;
		$this->objects[ $number ] = $body;
		return $number;
	}

	/**
	 * Adds a stream object, compressed when compression is on.
	 *
	 * @param string   $dict   Dictionary entries (without << >> and /Length).
	 * @param string   $data   Stream data.
	 * @param int|null $number A reserved object number to fill.
	 */
	public function add_stream( string $dict, string $data, ?int $number = null ): int {
		if ( $this->compress && false === strpos( $dict, '/Filter' ) ) {
			$data = (string) gzcompress( $data );
			$dict = trim( $dict . ' /Filter /FlateDecode' );
		}
		return $this->add_object( '<< ' . trim( $dict . ' /Length ' . strlen( $data ) ) . " >>\nstream\n" . $data . "\nendstream", $number );
	}

	/**
	 * Whether streams are compressed.
	 */
	public function compresses(): bool {
		return $this->compress;
	}

	/**
	 * The complete PDF file.
	 */
	public function output(): string {
		if ( empty( $this->pages ) ) {
			$this->add_page();
		}
		$fonts = array();
		foreach ( $this->fonts as $alias => $font ) {
			$fonts[] = '/' . $this->font_names[ $alias ] . ' ' . $font->write( $this ) . ' 0 R';
		}
		$xobjects = array();
		foreach ( $this->images as $image ) {
			$xobjects[] = '/' . $image[0] . ' ' . $image[1] . ' 0 R';
		}
		$resources = $this->add_object(
			'<< /ProcSet [/PDF /Text /ImageB /ImageC /ImageI] /Font << ' . implode( ' ', $fonts ) . ' >>' . ( $xobjects ? ' /XObject << ' . implode( ' ', $xobjects ) . ' >>' : '' ) . ' >>'
		);

		$kids = array();
		foreach ( $this->pages as $content ) {
			$stream = $this->add_stream( '', $content );
			$kids[] = $this->add_object(
				sprintf(
					'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources %d 0 R /Contents %d 0 R >>',
					self::num( $this->width ),
					self::num( $this->height ),
					$resources,
					$stream
				)
			) . ' 0 R';
		}
		$this->add_object( '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . count( $kids ) . ' >>', 2 );
		$this->add_object( '<< /Type /Catalog /Pages 2 0 R >>', 1 );

		$info = array( '/Producer ' . self::text_string( 'WooToolkit' ), '/CreationDate ' . self::text_string( 'D:' . gmdate( 'YmdHis' ) . 'Z' ) );
		foreach ( $this->info as $key => $value ) {
			$info[] = '/' . $key . ' ' . self::text_string( $value );
		}
		$info_id = $this->add_object( '<< ' . implode( ' ', $info ) . ' >>' );

		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		ksort( $this->objects );
		foreach ( $this->objects as $number => $body ) {
			$offsets[ $number ] = strlen( $pdf );
			$pdf               .= $number . " 0 obj\n" . ( null === $body ? 'null' : $body ) . "\nendobj\n";
		}
		$size = (int) max( array_keys( $this->objects ) ) + 1;
		$xref = strlen( $pdf );
		$pdf .= "xref\n0 " . $size . "\n0000000000 65535 f \n";
		for ( $i = 1; $i < $size; $i++ ) {
			$pdf .= isset( $offsets[ $i ] ) ? sprintf( "%010d 00000 n \n", $offsets[ $i ] ) : "0000000000 65535 f \n";
		}
		$id   = md5( $pdf );
		$pdf .= "trailer\n<< /Size " . $size . ' /Root 1 0 R /Info ' . $info_id . ' 0 R /ID [<' . $id . '> <' . $id . "> ] >>\nstartxref\n" . $xref . "\n%%EOF\n";
		return $pdf;
	}

	/**
	 * A PDF literal string: (text) with \, ( and ) escaped.
	 *
	 * @param string $bytes Encoded bytes.
	 */
	public static function literal( string $bytes ): string {
		return '(' . strtr(
			$bytes,
			array(
				'\\' => '\\\\',
				'('  => '\\(',
				')'  => '\\)',
				"\r" => '\\r',
				"\n" => '\\n',
			)
		) . ')';
	}

	/**
	 * A PDF hex string.
	 *
	 * @param string $bytes Bytes.
	 */
	public static function hex( string $bytes ): string {
		return '<' . strtoupper( bin2hex( $bytes ) ) . '>';
	}

	/**
	 * A text string for the document information: plain when ASCII, UTF-16BE with a BOM otherwise.
	 *
	 * @param string $text UTF-8 text.
	 */
	public static function text_string( string $text ): string {
		if ( ! preg_match( '/[^\x20-\x7E]/', $text ) ) {
			return self::literal( $text );
		}
		$utf16 = "\xFE\xFF";
		foreach ( WinAnsi::code_points( $text ) as $cp ) {
			if ( $cp > 0xFFFF ) {
				$cp     -= 0x10000;
				$utf16 .= pack( 'nn', 0xD800 | ( $cp >> 10 ), 0xDC00 | ( $cp & 0x3FF ) );
			} else {
				$utf16 .= pack( 'n', $cp );
			}
		}
		return self::hex( $utf16 );
	}

	/**
	 * A number for a content stream: two decimals at most, no locale.
	 *
	 * @param float $value Number.
	 */
	public static function num( float $value ): string {
		$out = rtrim( rtrim( sprintf( '%.2F', $value ), '0' ), '.' );
		return '-0' === $out ? '0' : $out;
	}

	/**
	 * Parses a "#rrggbb" colour into 0-1 floats, or returns the fallback.
	 *
	 * @param string  $hex      Colour.
	 * @param float[] $fallback Fallback.
	 * @return float[]
	 */
	public static function rgb( string $hex, array $fallback = array( 0.0, 0.0, 0.0 ) ): array {
		if ( ! preg_match( '/^#?([0-9a-f]{6}|[0-9a-f]{3})$/i', trim( $hex ), $m ) ) {
			return $fallback;
		}
		$hex = 3 === strlen( $m[1] ) ? $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2] : $m[1];
		return array( hexdec( substr( $hex, 0, 2 ) ) / 255, hexdec( substr( $hex, 2, 2 ) ) / 255, hexdec( substr( $hex, 4, 2 ) ) / 255 );
	}

	/**
	 * Appends to the current page's content stream.
	 *
	 * @param string $op Operators.
	 */
	protected function out( string $op ): void {
		if ( $this->page >= 0 ) {
			$this->pages[ $this->page ] .= $op . "\n";
		}
	}

	/**
	 * Loads an image once and returns its key.
	 *
	 * @param string $path Local file.
	 */
	protected function load_image( string $path ): ?string {
		$key = md5( $path );
		if ( ! isset( $this->images[ $key ] ) ) {
			$image = Image::from_file( $path );
			if ( null === $image ) {
				return null;
			}
			$number               = $this->add_stream( '/Type /XObject /Subtype /Image /Width ' . $image->width . ' /Height ' . $image->height . ' ' . $image->dict, $image->data );
			$this->images[ $key ] = array( 'I' . ( count( $this->images ) + 1 ), $number, $image->width, $image->height );
		}
		return $key;
	}

	/**
	 * A colour operand.
	 *
	 * @param float[] $rgb Red, green, blue, each 0-1.
	 */
	protected static function color( array $rgb ): string {
		$rgb = array_values( $rgb ) + array( 0.0, 0.0, 0.0 );
		return sprintf( '%.3F %.3F %.3F', max( 0, min( 1, (float) $rgb[0] ) ), max( 0, min( 1, (float) $rgb[1] ) ), max( 0, min( 1, (float) $rgb[2] ) ) );
	}
}
