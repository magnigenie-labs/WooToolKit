<?php
/**
 * UTF-8 to Windows-1252 (PDF WinAnsiEncoding) with transliteration.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices\Pdf;

defined( 'ABSPATH' ) || exit;

/**
 * Converts UTF-8 text to the single-byte WinAnsi encoding the core fonts use. Characters WinAnsi has
 * are mapped exactly; others are transliterated (Latin letters lose their accents, Cyrillic and
 * Greek are romanised, currency signs become codes such as "Rs."), and anything left becomes "?".
 */
final class WinAnsi {

	/**
	 * Unicode code points of WinAnsi bytes 0x80-0x9F.
	 */
	const HIGH = array(
		0x20AC => 0x80,
		0x201A => 0x82,
		0x0192 => 0x83,
		0x201E => 0x84,
		0x2026 => 0x85,
		0x2020 => 0x86,
		0x2021 => 0x87,
		0x02C6 => 0x88,
		0x2030 => 0x89,
		0x0160 => 0x8A,
		0x2039 => 0x8B,
		0x0152 => 0x8C,
		0x017D => 0x8E,
		0x2018 => 0x91,
		0x2019 => 0x92,
		0x201C => 0x93,
		0x201D => 0x94,
		0x2022 => 0x95,
		0x2013 => 0x96,
		0x2014 => 0x97,
		0x02DC => 0x98,
		0x2122 => 0x99,
		0x0161 => 0x9A,
		0x203A => 0x9B,
		0x0153 => 0x9C,
		0x017E => 0x9E,
		0x0178 => 0x9F,
	);

	/**
	 * Latin Extended-A (U+0100-U+017F) without accents, one character per code point. "?" marks the
	 * code points handled by SPECIAL or by WinAnsi itself.
	 */
	const LATIN_EXTENDED_A = 'AaAaAaCcCcCcCcDdDdEeEeEeEeEeGgGgGgGgHhHhIiIiIiIiIi??JjKkkLlLlLlLlLlNnNnNn?NnOoOoOo??RrRrRrSsSsSsSsTtTtTtUuUuUuUuUuUuWwYyYZzZzZzs';

	/**
	 * Other transliterations: code point => replacement.
	 */
	const SPECIAL = array(
		0x0132 => 'IJ',
		0x0133 => 'ij',
		0x0149 => "'n",
		0x0218 => 'S',
		0x0219 => 's',
		0x021A => 'T',
		0x021B => 't',
		0x01CD => 'A',
		0x01CE => 'a',
		0x01CF => 'I',
		0x01D0 => 'i',
		0x01D1 => 'O',
		0x01D2 => 'o',
		0x01D3 => 'U',
		0x01D4 => 'u',
		0x01A0 => 'O',
		0x01A1 => 'o',
		0x01AF => 'U',
		0x01B0 => 'u',
		0x0259 => 'e',
		0x018F => 'E',
		// Currency signs.
		0x20B9 => 'Rs.',
		0x20BD => 'RUB',
		0x20BA => 'TL',
		0x20B4 => 'UAH',
		0x20A9 => 'W',
		0x20AA => 'ILS',
		0x20AB => 'd',
		0x20B1 => 'PHP',
		0x20A6 => 'NGN',
		0x20B8 => 'KZT',
		0x20BC => 'AZN',
		0x20BE => 'GEL',
		0x20A8 => 'Rs',
		0x0E3F => 'THB',
		0x09F3 => 'Tk',
		0x20AD => 'LAK',
		0x20AE => 'MNT',
		0x20B2 => 'PYG',
		0x20B5 => 'GHS',
		0x060B => 'AFN',
		0xFDFC => 'Rial',
		// Punctuation and symbols.
		0x2010 => '-',
		0x2011 => '-',
		0x2012 => '-',
		0x2015 => '-',
		0x2212 => '-',
		0x2032 => "'",
		0x2033 => '"',
		0x2190 => '<-',
		0x2192 => '->',
		0x2264 => '<=',
		0x2265 => '>=',
		0x2260 => '!=',
		0x2248 => '~',
		0x2116 => 'No.',
		0x2713 => 'v',
		0x2714 => 'v',
	);

	/**
	 * Cyrillic, U+0410-U+044F (А-я).
	 */
	const CYRILLIC = array(
		'A',
		'B',
		'V',
		'G',
		'D',
		'E',
		'Zh',
		'Z',
		'I',
		'Y',
		'K',
		'L',
		'M',
		'N',
		'O',
		'P',
		'R',
		'S',
		'T',
		'U',
		'F',
		'Kh',
		'Ts',
		'Ch',
		'Sh',
		'Shch',
		'',
		'Y',
		'',
		'E',
		'Yu',
		'Ya',
	);

	/**
	 * Other Cyrillic letters.
	 */
	const CYRILLIC_OTHER = array(
		0x0401 => 'Yo',
		0x0451 => 'yo',
		0x0404 => 'Ye',
		0x0454 => 'ye',
		0x0406 => 'I',
		0x0456 => 'i',
		0x0407 => 'Yi',
		0x0457 => 'yi',
		0x0490 => 'G',
		0x0491 => 'g',
		0x040E => 'U',
		0x045E => 'u',
	);

	/**
	 * Greek, U+0391-U+03A9 (Α-Ω; U+03A2 is unassigned).
	 */
	const GREEK = array( 'A', 'B', 'G', 'D', 'E', 'Z', 'I', 'Th', 'I', 'K', 'L', 'M', 'N', 'X', 'O', 'P', 'R', '', 'S', 'T', 'Y', 'F', 'Ch', 'Ps', 'O' );

	/**
	 * Accented Greek vowels and final sigma.
	 */
	const GREEK_OTHER = array(
		0x0386 => 'A',
		0x0388 => 'E',
		0x0389 => 'I',
		0x038A => 'I',
		0x038C => 'O',
		0x038E => 'Y',
		0x038F => 'O',
		0x03AC => 'a',
		0x03AD => 'e',
		0x03AE => 'i',
		0x03AF => 'i',
		0x03CC => 'o',
		0x03CD => 'y',
		0x03CE => 'o',
		0x03C2 => 's',
		0x03CA => 'i',
		0x03CB => 'y',
		0x0390 => 'i',
		0x03B0 => 'y',
	);

	/**
	 * Converts UTF-8 text to WinAnsi bytes.
	 *
	 * @param string $text UTF-8 text.
	 */
	public static function encode( string $text ): string {
		if ( '' === $text ) {
			return '';
		}
		if ( ! preg_match( '//u', $text ) ) {
			// Not valid UTF-8: keep the ASCII bytes only.
			return (string) preg_replace( '/[^\x20-\x7E]/', '?', $text );
		}
		if ( class_exists( '\Normalizer' ) ) {
			$normal = \Normalizer::normalize( $text, \Normalizer::FORM_C );
			$text   = is_string( $normal ) ? $normal : $text;
		}
		$out = '';
		foreach ( self::code_points( $text ) as $cp ) {
			$out .= self::char( $cp );
		}
		return $out;
	}

	/**
	 * Whether every character of the text exists in WinAnsi (nothing is transliterated).
	 *
	 * @param string $text UTF-8 text.
	 */
	public static function covers( string $text ): bool {
		foreach ( self::code_points( $text ) as $cp ) {
			if ( ! ( ( $cp >= 0x20 && $cp <= 0x7E ) || ( $cp >= 0xA0 && $cp <= 0xFF ) || isset( self::HIGH[ $cp ] ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * The code points of valid UTF-8 text.
	 *
	 * @param string $text UTF-8 text.
	 * @return int[]
	 */
	public static function code_points( string $text ): array {
		$points = array();
		$len    = strlen( $text );
		for ( $i = 0; $i < $len; ) {
			$byte = ord( $text[ $i ] );
			if ( $byte < 0x80 ) {
				$points[] = $byte;
				++$i;
			} elseif ( $byte < 0xE0 && $i + 1 < $len ) {
				$points[] = ( ( $byte & 0x1F ) << 6 ) | ( ord( $text[ $i + 1 ] ) & 0x3F );
				$i       += 2;
			} elseif ( $byte < 0xF0 && $i + 2 < $len ) {
				$points[] = ( ( $byte & 0x0F ) << 12 ) | ( ( ord( $text[ $i + 1 ] ) & 0x3F ) << 6 ) | ( ord( $text[ $i + 2 ] ) & 0x3F );
				$i       += 3;
			} elseif ( $i + 3 < $len ) {
				$points[] = ( ( $byte & 0x07 ) << 18 ) | ( ( ord( $text[ $i + 1 ] ) & 0x3F ) << 12 ) | ( ( ord( $text[ $i + 2 ] ) & 0x3F ) << 6 ) | ( ord( $text[ $i + 3 ] ) & 0x3F );
				$i       += 4;
			} else {
				break;
			}
		}
		return $points;
	}

	/**
	 * One code point as WinAnsi bytes.
	 *
	 * @param int $cp Code point.
	 */
	private static function char( int $cp ): string {
		if ( 0x09 === $cp || 0x0A === $cp || 0x0D === $cp ) {
			return ' ';
		}
		if ( $cp < 0x20 || 0x7F === $cp || ( $cp >= 0x80 && $cp < 0xA0 ) ) {
			return '';
		}
		if ( $cp <= 0xFF ) {
			return chr( $cp );
		}
		if ( isset( self::HIGH[ $cp ] ) ) {
			return chr( self::HIGH[ $cp ] );
		}
		return self::transliterate( $cp );
	}

	/**
	 * A WinAnsi replacement for a character WinAnsi does not have.
	 *
	 * @param int $cp Code point.
	 */
	private static function transliterate( int $cp ): string {
		if ( isset( self::SPECIAL[ $cp ] ) ) {
			return self::SPECIAL[ $cp ];
		}
		if ( $cp >= 0x0100 && $cp <= 0x017F && '?' !== self::LATIN_EXTENDED_A[ $cp - 0x0100 ] ) {
			return self::LATIN_EXTENDED_A[ $cp - 0x0100 ];
		}
		if ( $cp >= 0x0410 && $cp <= 0x042F ) {
			return self::CYRILLIC[ $cp - 0x0410 ];
		}
		if ( $cp >= 0x0430 && $cp <= 0x044F ) {
			return strtolower( self::CYRILLIC[ $cp - 0x0430 ] );
		}
		if ( isset( self::CYRILLIC_OTHER[ $cp ] ) ) {
			return self::CYRILLIC_OTHER[ $cp ];
		}
		if ( $cp >= 0x0391 && $cp <= 0x03A9 ) {
			return self::GREEK[ $cp - 0x0391 ];
		}
		if ( $cp >= 0x03B1 && $cp <= 0x03C9 ) {
			return 0x03C2 === $cp ? 's' : strtolower( self::GREEK[ $cp - 0x03B1 ] );
		}
		if ( isset( self::GREEK_OTHER[ $cp ] ) ) {
			return self::GREEK_OTHER[ $cp ];
		}
		if ( ( $cp >= 0x0300 && $cp <= 0x036F ) || ( $cp >= 0x200B && $cp <= 0x200F ) || 0xFEFF === $cp ) {
			return ''; // Combining marks, zero-width characters and direction marks.
		}
		if ( ( $cp >= 0x2000 && $cp <= 0x200A ) || 0x202F === $cp || 0x205F === $cp || 0x3000 === $cp ) {
			return ' ';
		}
		if ( function_exists( 'remove_accents' ) ) {
			$plain = remove_accents( self::utf8( $cp ) );
			if ( '' !== $plain && ! preg_match( '/[^\x20-\x7E]/', $plain ) ) {
				return $plain;
			}
		}
		return '?';
	}

	/**
	 * A code point as UTF-8.
	 *
	 * @param int $cp Code point.
	 */
	public static function utf8( int $cp ): string {
		if ( $cp < 0x80 ) {
			return chr( $cp );
		}
		if ( $cp < 0x800 ) {
			return chr( 0xC0 | ( $cp >> 6 ) ) . chr( 0x80 | ( $cp & 0x3F ) );
		}
		if ( $cp < 0x10000 ) {
			return chr( 0xE0 | ( $cp >> 12 ) ) . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $cp & 0x3F ) );
		}
		return chr( 0xF0 | ( $cp >> 18 ) ) . chr( 0x80 | ( ( $cp >> 12 ) & 0x3F ) ) . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $cp & 0x3F ) );
	}
}
