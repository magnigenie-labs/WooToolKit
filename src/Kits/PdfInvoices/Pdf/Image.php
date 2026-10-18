<?php
/**
 * JPEG and PNG images for the PDF writer.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices\Pdf;

defined( 'ABSPATH' ) || exit;

/**
 * Reads a JPEG or PNG into what a PDF image XObject needs. JPEGs are embedded as they are
 * (DCTDecode). Opaque 8-bit PNGs are embedded as their compressed data (FlateDecode with the PNG
 * predictors); PNGs with transparency, 16-bit samples or bit-packed greys are decoded and flattened
 * onto white, since PDF 1.4 soft masks are not needed for a logo on a white page.
 */
final class Image {

	/**
	 * Pixel width.
	 *
	 * @var int
	 */
	public $width = 0;

	/**
	 * Pixel height.
	 *
	 * @var int
	 */
	public $height = 0;

	/**
	 * XObject dictionary entries (without << >>), e.g. "/ColorSpace /DeviceRGB /BitsPerComponent 8".
	 *
	 * @var string
	 */
	public $dict = '';

	/**
	 * Stream data, already encoded with the filter named in $dict.
	 *
	 * @var string
	 */
	public $data = '';

	/**
	 * Loads an image file, or returns null when it is not a JPEG or PNG the writer can use.
	 *
	 * @param string $path Local file path.
	 */
	public static function from_file( string $path ): ?self {
		if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}
		$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local image file.
		return is_string( $bytes ) ? self::from_string( $bytes ) : null;
	}

	/**
	 * Loads image bytes, or returns null when they are not a JPEG or PNG the writer can use.
	 *
	 * @param string $bytes File contents.
	 */
	public static function from_string( string $bytes ): ?self {
		if ( 0 === strncmp( $bytes, "\xFF\xD8", 2 ) ) {
			return self::jpeg( $bytes );
		}
		if ( 0 === strncmp( $bytes, "\x89PNG\r\n\x1A\n", 8 ) ) {
			return self::png( $bytes );
		}
		return null;
	}

	/**
	 * A JPEG, embedded unchanged.
	 *
	 * @param string $bytes File contents.
	 */
	private static function jpeg( string $bytes ): ?self {
		$info = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a broken file returns false.
		if ( ! is_array( $info ) || empty( $info[0] ) || empty( $info[1] ) ) {
			return null;
		}
		$channels = isset( $info['channels'] ) ? (int) $info['channels'] : 3;
		$space    = 1 === $channels ? '/DeviceGray' : ( 4 === $channels ? '/DeviceCMYK' : '/DeviceRGB' );
		$image    = new self();
		// Adobe CMYK JPEGs are stored inverted.
		$image->width  = (int) $info[0];
		$image->height = (int) $info[1];
		$image->dict   = '/ColorSpace ' . $space . ' /BitsPerComponent 8 /Filter /DCTDecode' . ( 4 === $channels ? ' /Decode [1 0 1 0 1 0 1 0]' : '' );
		$image->data   = $bytes;
		return $image;
	}

	/**
	 * A PNG.
	 *
	 * @param string $bytes File contents.
	 */
	private static function png( string $bytes ): ?self {
		$png = self::png_chunks( $bytes );
		if ( null === $png ) {
			return null;
		}
		list( $width, $height, $depth, $type, $interlace ) = $png['header'];
		if ( $width < 1 || $height < 1 || ! in_array( $type, array( 0, 2, 3, 4, 6 ), true ) ) {
			return null;
		}
		if ( $interlace ) {
			return self::png_via_gd( $bytes );
		}
		$channels = array(
			0 => 1,
			2 => 3,
			3 => 1,
			4 => 2,
			6 => 4,
		)[ $type ];

		// Opaque 8-bit (or palette) PNG: the compressed data goes in as it is.
		$opaque = ( 0 === $type || 2 === $type || 3 === $type ) && '' === $png['trns'];
		if ( $opaque && ( 8 === $depth || 3 === $type ) ) {
			$image         = new self();
			$image->width  = $width;
			$image->height = $height;
			if ( 3 === $type ) {
				if ( '' === $png['palette'] ) {
					return null;
				}
				$space = '[/Indexed /DeviceRGB ' . ( intdiv( strlen( $png['palette'] ), 3 ) - 1 ) . ' ' . Writer::hex( $png['palette'] ) . ']';
			} else {
				$space = 0 === $type ? '/DeviceGray' : '/DeviceRGB';
			}
			$image->dict = '/ColorSpace ' . $space . ' /BitsPerComponent ' . $depth . ' /Filter /FlateDecode /DecodeParms << /Predictor 15 /Colors ' . $channels . ' /BitsPerComponent ' . $depth . ' /Columns ' . $width . ' >>';
			$image->data = $png['data'];
			return $image;
		}

		if ( ! function_exists( 'gzuncompress' ) || ! function_exists( 'gzcompress' ) ) {
			return null;
		}
		$raw = @gzuncompress( $png['data'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- corrupt data returns false.
		if ( ! is_string( $raw ) ) {
			return null;
		}
		$rgb = self::flatten( $raw, $width, $height, $depth, $type, $channels, $png['palette'], $png['trns'] );
		if ( null === $rgb ) {
			return null;
		}
		$image         = new self();
		$image->width  = $width;
		$image->height = $height;
		$image->dict   = '/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode';
		$image->data   = (string) gzcompress( $rgb );
		return $image;
	}

	/**
	 * The PNG's header, palette, transparency and concatenated image data.
	 *
	 * @param string $bytes File contents.
	 * @return array{header: int[], palette: string, trns: string, data: string}|null
	 */
	private static function png_chunks( string $bytes ): ?array {
		$pos    = 8;
		$len    = strlen( $bytes );
		$header = null;
		$out    = array(
			'palette' => '',
			'trns'    => '',
			'data'    => '',
		);
		while ( $pos + 8 <= $len ) {
			$size  = unpack( 'N', substr( $bytes, $pos, 4 ) )[1];
			$type  = substr( $bytes, $pos + 4, 4 );
			$chunk = substr( $bytes, $pos + 8, $size );
			if ( strlen( $chunk ) !== $size ) {
				return null;
			}
			if ( 'IHDR' === $type && 13 === $size ) {
				$h      = unpack( 'Nw/Nh/Cdepth/Ctype/Ccomp/Cfilter/Cinterlace', $chunk );
				$header = array( (int) $h['w'], (int) $h['h'], (int) $h['depth'], (int) $h['type'], (int) $h['interlace'] );
			} elseif ( 'PLTE' === $type ) {
				$out['palette'] = $chunk;
			} elseif ( 'tRNS' === $type ) {
				$out['trns'] = $chunk;
			} elseif ( 'IDAT' === $type ) {
				$out['data'] .= $chunk;
			} elseif ( 'IEND' === $type ) {
				break;
			}
			$pos += 12 + $size;
		}
		if ( null === $header || '' === $out['data'] ) {
			return null;
		}
		$out['header'] = $header;
		return $out;
	}

	/**
	 * Interlaced PNGs: re-saved without interlacing by GD, when GD is there.
	 *
	 * @param string $bytes File contents.
	 */
	private static function png_via_gd( string $bytes ): ?self {
		if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagepng' ) ) {
			return null;
		}
		$gd = @imagecreatefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- unsupported data returns false.
		if ( false === $gd ) {
			return null;
		}
		imageinterlace( $gd, 0 );
		imagesavealpha( $gd, true );
		ob_start();
		imagepng( $gd );
		$plain = (string) ob_get_clean();
		$png   = self::png_chunks( $plain );
		return null !== $png && empty( $png['header'][4] ) ? self::png( $plain ) : null;
	}

	/**
	 * Unfilters PNG scanlines and returns 8-bit RGB with any transparency composited onto white.
	 *
	 * @param string $raw      Decompressed IDAT data.
	 * @param int    $width    Width.
	 * @param int    $height   Height.
	 * @param int    $depth    Bits per sample (1, 2, 4, 8 or 16).
	 * @param int    $type     PNG colour type.
	 * @param int    $channels Samples per pixel.
	 * @param string $palette  PLTE data.
	 * @param string $trns     tRNS data.
	 */
	private static function flatten( string $raw, int $width, int $height, int $depth, int $type, int $channels, string $palette, string $trns ): ?string {
		$bits   = $depth * $channels;
		$stride = (int) ceil( $width * $bits / 8 );
		$bpp    = max( 1, intdiv( $bits, 8 ) );
		if ( strlen( $raw ) < ( $stride + 1 ) * $height ) {
			return null;
		}
		$max   = ( 1 << min( $depth, 8 ) ) - 1;
		$out   = '';
		$prior = str_repeat( "\0", $stride );
		for ( $y = 0; $y < $height; $y++ ) {
			$filter = ord( $raw[ $y * ( $stride + 1 ) ] );
			$line   = substr( $raw, $y * ( $stride + 1 ) + 1, $stride );
			$line   = self::unfilter( $filter, $line, $prior, $bpp );
			$prior  = $line;
			for ( $x = 0; $x < $width; $x++ ) {
				$samples = self::samples( $line, $x, $depth, $channels );
				$alpha   = 255;
				if ( 3 === $type ) {
					$index = $samples[0];
					$r     = isset( $palette[ $index * 3 + 2 ] ) ? ord( $palette[ $index * 3 ] ) : 0;
					$g     = isset( $palette[ $index * 3 + 2 ] ) ? ord( $palette[ $index * 3 + 1 ] ) : 0;
					$b     = isset( $palette[ $index * 3 + 2 ] ) ? ord( $palette[ $index * 3 + 2 ] ) : 0;
					$alpha = isset( $trns[ $index ] ) ? ord( $trns[ $index ] ) : 255;
				} elseif ( 0 === $type || 4 === $type ) {
					$r     = (int) round( $samples[0] * 255 / $max );
					$g     = $r;
					$b     = $r;
					$alpha = 4 === $type ? $samples[1] : ( self::is_key( $trns, array( $samples[0] ), $depth ) ? 0 : 255 );
				} else {
					list( $r, $g, $b ) = $samples;
					$alpha             = 6 === $type ? $samples[3] : ( self::is_key( $trns, array_slice( $samples, 0, 3 ), $depth ) ? 0 : 255 );
				}
				if ( $alpha < 255 ) {
					$r = (int) round( ( $r * $alpha + 255 * ( 255 - $alpha ) ) / 255 );
					$g = (int) round( ( $g * $alpha + 255 * ( 255 - $alpha ) ) / 255 );
					$b = (int) round( ( $b * $alpha + 255 * ( 255 - $alpha ) ) / 255 );
				}
				$out .= chr( $r ) . chr( $g ) . chr( $b );
			}
		}
		return $out;
	}

	/**
	 * Reverses one scanline's PNG filter.
	 *
	 * @param int    $filter Filter type 0-4.
	 * @param string $line   Filtered bytes.
	 * @param string $prior  Previous unfiltered line.
	 * @param int    $bpp    Bytes per complete pixel (at least 1).
	 */
	private static function unfilter( int $filter, string $line, string $prior, int $bpp ): string {
		$len = strlen( $line );
		if ( 0 === $filter ) {
			return $line;
		}
		for ( $i = 0; $i < $len; $i++ ) {
			$a = $i >= $bpp ? ord( $line[ $i - $bpp ] ) : 0;
			$b = ord( $prior[ $i ] );
			$c = $i >= $bpp ? ord( $prior[ $i - $bpp ] ) : 0;
			switch ( $filter ) {
				case 1:
					$add = $a;
					break;
				case 2:
					$add = $b;
					break;
				case 3:
					$add = ( $a + $b ) >> 1;
					break;
				case 4:
					$p   = $a + $b - $c;
					$pa  = abs( $p - $a );
					$pb  = abs( $p - $b );
					$pc  = abs( $p - $c );
					$add = ( $pa <= $pb && $pa <= $pc ) ? $a : ( $pb <= $pc ? $b : $c );
					break;
				default:
					$add = 0;
			}
			$line[ $i ] = chr( ( ord( $line[ $i ] ) + $add ) & 0xFF );
		}
		return $line;
	}

	/**
	 * The samples of pixel $x, scaled to 8 bits for 16-bit images.
	 *
	 * @param string $line     Unfiltered scanline.
	 * @param int    $x        Pixel index.
	 * @param int    $depth    Bits per sample.
	 * @param int    $channels Samples per pixel.
	 * @return int[]
	 */
	private static function samples( string $line, int $x, int $depth, int $channels ): array {
		$out = array();
		if ( $depth >= 8 ) {
			$size = intdiv( $depth, 8 );
			for ( $c = 0; $c < $channels; $c++ ) {
				$out[] = ord( $line[ ( $x * $channels + $c ) * $size ] ); // High byte of 16-bit samples.
			}
			return $out;
		}
		$bit   = $x * $depth;
		$byte  = ord( $line[ $bit >> 3 ] );
		$shift = 8 - $depth - ( $bit & 7 );
		$out[] = ( $byte >> $shift ) & ( ( 1 << $depth ) - 1 );
		return $out;
	}

	/**
	 * Whether grey or RGB samples match the tRNS colour key.
	 *
	 * @param string $trns    tRNS data (2 bytes per sample).
	 * @param int[]  $samples Samples (8-bit scaled for 16-bit images).
	 * @param int    $depth   Bits per sample.
	 */
	private static function is_key( string $trns, array $samples, int $depth ): bool {
		if ( strlen( $trns ) < 2 * count( $samples ) ) {
			return false;
		}
		foreach ( $samples as $i => $sample ) {
			$key = unpack( 'n', substr( $trns, $i * 2, 2 ) )[1];
			if ( 16 === $depth ) {
				$key >>= 8;
			}
			if ( (int) $key !== $sample ) {
				return false;
			}
		}
		return true;
	}
}
