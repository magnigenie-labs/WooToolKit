<?php
/**
 * The invoice / packing slip layout.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices;

use WooToolkit\Kits\PdfInvoices\Pdf\Writer;

defined( 'ABSPATH' ) || exit;

/**
 * Draws one document from its data array: header (logo or store name, store address, title, number
 * and dates), addresses, the item table (its header row repeats on every page), totals, notes, and
 * the footer text with page numbers on every page. Each part is a protected method, so a subclass
 * can restyle one part (style options) or replace it.
 */
class Template {

	/**
	 * Writer.
	 *
	 * @var Writer
	 */
	protected $pdf;

	/**
	 * Document data.
	 *
	 * @var array<string, mixed>
	 */
	protected $data = array();

	/**
	 * Style: colours (0-1 RGB), sizes (points).
	 *
	 * @var array<string, mixed>
	 */
	protected $style;

	/**
	 * Current position from the top of the page.
	 *
	 * @var float
	 */
	protected $y = 0.0;

	/**
	 * Footer lines, wrapped once per document.
	 *
	 * @var string[]|null
	 */
	protected $footer = null;

	/**
	 * First page of this document in the PDF.
	 *
	 * @var int
	 */
	protected $first_page = 1;

	/**
	 * Constructor.
	 *
	 * @param Writer               $pdf   Writer, with "regular" and "bold" fonts.
	 * @param array<string, mixed> $style Style overrides (see defaults()).
	 */
	public function __construct( Writer $pdf, array $style = array() ) {
		$this->pdf   = $pdf;
		$this->style = array_merge( static::defaults(), $style );
	}

	/**
	 * Default style.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'accent'     => array( 0.13, 0.20, 0.29 ),
			'text'       => array( 0.13, 0.13, 0.15 ),
			'muted'      => array( 0.42, 0.45, 0.50 ),
			'rule'       => array( 0.82, 0.84, 0.86 ),
			'shade'      => array( 0.95, 0.96, 0.97 ),
			'head_text'  => array( 0.13, 0.20, 0.29 ),
			'margin'     => 42.0,
			'size'       => 9.0,
			'small'      => 7.5,
			'title_size' => 22.0,
			'leading'    => 1.38,
			'padding'    => 6.0,
			'logo_w'     => 170.0,
			'logo_h'     => 60.0,
		);
	}

	/**
	 * Draws the document on new pages.
	 *
	 * @param array<string, mixed> $data Document data (Data::build()).
	 */
	public function draw( array $data ): void {
		$this->data   = $data;
		$this->footer = null;
		$this->pdf->add_page();
		$this->first_page = $this->pdf->page_number();
		$this->y          = $this->margin();

		$this->header();
		$this->addresses();
		$this->items();
		$this->totals();
		$this->notes();
		$this->page_footers();
	}

	/**
	 * Logo or store name, store address, document title and the number/date rows.
	 */
	protected function header(): void {
		$top    = $this->y;
		$store  = isset( $this->data['store'] ) && is_array( $this->data['store'] ) ? $this->data['store'] : array();
		$name   = isset( $store['name'] ) ? (string) $store['name'] : '';
		$lines  = isset( $store['lines'] ) ? (array) $store['lines'] : array();
		$logo   = isset( $store['logo'] ) ? (string) $store['logo'] : '';
		$left_y = $top;
		$size   = $this->pdf->image_size( $logo );

		if ( null !== $size && $size[0] > 0 && $size[1] > 0 ) {
			$scale = min( (float) $this->style['logo_w'] / $size[0], (float) $this->style['logo_h'] / $size[1] );
			$w     = $size[0] * $scale;
			$h     = $size[1] * $scale;
			$this->pdf->image( $logo, $this->left(), $top, $w, $h );
			$left_y = $top + $h + 10;
			if ( '' !== $name ) {
				array_unshift( $lines, $name );
			}
		} elseif ( '' !== $name ) {
			$this->pdf->set_font( 'bold', 15 );
			$this->pdf->set_text_color( $this->style['accent'] );
			foreach ( $this->pdf->wrap( $name, $this->width() * 0.5 ) as $line ) {
				$this->pdf->text( $this->left(), $left_y + 15, $line );
				$left_y += 19;
			}
			$left_y += 4;
		}

		$this->pdf->set_font( 'regular', (float) $this->style['small'] + 0.5 );
		$this->pdf->set_text_color( $this->style['muted'] );
		foreach ( $lines as $line ) {
			foreach ( $this->pdf->wrap( (string) $line, $this->width() * 0.5 ) as $wrapped ) {
				$this->pdf->text( $this->left(), $left_y + 8, $wrapped );
				$left_y += 11;
			}
		}

		// Title and meta rows on the right.
		$title_size = (float) $this->style['title_size'];
		$this->pdf->set_font( 'bold', $title_size );
		$this->pdf->set_text_color( $this->style['accent'] );
		$this->pdf->text( $this->right(), $top + $title_size * 0.8, (string) ( $this->data['title'] ?? '' ), 'right' );
		$right_y = $top + $title_size + 10;
		$right_y = $this->meta_rows( $right_y );

		$this->y = max( $left_y, $right_y ) + 12;
		$this->pdf->set_stroke_color( $this->style['accent'] );
		$this->pdf->line( $this->left(), $this->y, $this->right(), $this->y, 1.2 );
		$this->y += 20;
	}

	/**
	 * Label/value rows (number, dates, payment method) right-aligned under the title.
	 *
	 * @param float $y Top.
	 * @return float Bottom.
	 */
	protected function meta_rows( float $y ): float {
		$value_w = 150.0;
		$label_x = $this->right() - $value_w - 100;
		$size    = (float) $this->style['size'];
		$lead    = $size * (float) $this->style['leading'];
		foreach ( (array) ( $this->data['meta'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) || '' === (string) ( $row['value'] ?? '' ) ) {
				continue;
			}
			$this->pdf->set_font( 'regular', $size );
			$this->pdf->set_text_color( $this->style['muted'] );
			$this->pdf->text( $label_x, $y + $size, (string) ( $row['label'] ?? '' ) );
			$this->pdf->set_font( 'bold', $size );
			$this->pdf->set_text_color( $this->style['text'] );
			foreach ( $this->pdf->wrap( (string) $row['value'], $value_w ) as $line ) {
				$this->pdf->text( $this->right(), $y + $size, $line, 'right' );
				$y += $lead;
			}
		}
		return $y;
	}

	/**
	 * Billing and shipping addresses side by side.
	 */
	protected function addresses(): void {
		$blocks = array_values( array_filter( (array) ( $this->data['addresses'] ?? array() ), 'is_array' ) );
		if ( empty( $blocks ) ) {
			return;
		}
		$columns = max( 2, min( 3, count( $blocks ) ) );
		$col_w   = $this->width() / $columns;
		$size    = (float) $this->style['size'];
		$lead    = $size * (float) $this->style['leading'];
		$bottom  = $this->y;
		foreach ( array_slice( $blocks, 0, 3 ) as $i => $block ) {
			$x = $this->left() + $i * $col_w;
			$y = $this->y;
			$this->pdf->set_font( 'bold', $size );
			$this->pdf->set_text_color( $this->style['accent'] );
			$this->pdf->text( $x, $y + $size, (string) ( $block['title'] ?? '' ) );
			$y += $lead + 3;
			$this->pdf->set_font( 'regular', $size );
			$this->pdf->set_text_color( $this->style['text'] );
			foreach ( (array) ( $block['lines'] ?? array() ) as $line ) {
				foreach ( $this->pdf->wrap( (string) $line, $col_w - 12 ) as $wrapped ) {
					$this->pdf->text( $x, $y + $size, $wrapped );
					$y += $lead;
				}
			}
			$bottom = max( $bottom, $y );
		}
		$this->y = $bottom + 18;
	}

	/**
	 * The item table. Rows that do not fit go to a new page, under a repeated header row.
	 */
	protected function items(): void {
		$columns = $this->columns();
		if ( empty( $columns ) ) {
			return;
		}
		$this->table_header( $columns );
		$size  = (float) $this->style['size'];
		$small = (float) $this->style['small'];
		$lead  = $size * (float) $this->style['leading'];
		$slead = $small * (float) $this->style['leading'];
		$pad   = (float) $this->style['padding'];

		foreach ( (array) ( $this->data['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$cells = array();
			$tall  = 0.0;
			foreach ( $columns as $i => $col ) {
				if ( 0 === $i ) {
					$name    = $this->pdf->wrap( (string) ( $item['name'] ?? '' ), $col['w'] - 2 * $pad, 'regular', $size );
					$details = array();
					foreach ( (array) ( $item['details'] ?? array() ) as $detail ) {
						$details = array_merge( $details, $this->pdf->wrap( (string) $detail, $col['w'] - 2 * $pad, 'regular', $small ) );
					}
					$cells[ $i ] = array( $name, $details );
					$tall        = max( $tall, count( $name ) * $lead + count( $details ) * $slead );
				} else {
					$value       = (string) ( $item['cells'][ $col['key'] ] ?? '' );
					$cells[ $i ] = array( $this->pdf->wrap( $value, $col['w'] - 2 * $pad, 'regular', $size ), array() );
					$tall        = max( $tall, count( $cells[ $i ][0] ) * $lead );
				}
			}
			$row_h = $tall + 2 * $pad;
			if ( $this->y + $row_h > $this->bottom() && $this->y > $this->margin() + 60 ) {
				$this->new_page();
				$this->table_header( $columns );
			}
			foreach ( $columns as $i => $col ) {
				$y = $this->y + $pad;
				$this->pdf->set_font( 'regular', $size );
				$this->pdf->set_text_color( $this->style['text'] );
				foreach ( $cells[ $i ][0] as $line ) {
					$this->cell_text( $col, $y + $size, $line, $pad );
					$y += $lead;
				}
				if ( $cells[ $i ][1] ) {
					$this->pdf->set_font( 'regular', $small );
					$this->pdf->set_text_color( $this->style['muted'] );
					foreach ( $cells[ $i ][1] as $line ) {
						$this->cell_text( $col, $y + $small, $line, $pad );
						$y += $slead;
					}
				}
			}
			$this->y += $row_h;
			$this->pdf->set_stroke_color( $this->style['rule'] );
			$this->pdf->line( $this->left(), $this->y, $this->right(), $this->y, 0.5 );
		}
		$this->y += 14;
	}

	/**
	 * Column definitions with absolute x positions and widths.
	 *
	 * @return array<int, array{key: string, label: string, align: string, x: float, w: float}>
	 */
	protected function columns(): array {
		$defs  = array_values( array_filter( (array) ( $this->data['columns'] ?? array() ), 'is_array' ) );
		$total = 0.0;
		foreach ( $defs as $def ) {
			$total += max( 0.01, (float) ( $def['width'] ?? 1 ) );
		}
		$out = array();
		$x   = $this->left();
		foreach ( $defs as $def ) {
			$w     = $this->width() * max( 0.01, (float) ( $def['width'] ?? 1 ) ) / ( $total > 0 ? $total : 1 );
			$out[] = array(
				'key'   => (string) ( $def['key'] ?? '' ),
				'label' => (string) ( $def['label'] ?? '' ),
				'align' => 'right' === ( $def['align'] ?? '' ) ? 'right' : 'left',
				'x'     => $x,
				'w'     => $w,
			);
			$x    += $w;
		}
		return $out;
	}

	/**
	 * The table's header row.
	 *
	 * @param array<int, array{key: string, label: string, align: string, x: float, w: float}> $columns Columns.
	 */
	protected function table_header( array $columns ): void {
		$size = (float) $this->style['size'];
		$pad  = (float) $this->style['padding'];
		$h    = $size + 2 * $pad + 2;
		$this->pdf->set_fill_color( $this->style['shade'] );
		$this->pdf->rect( $this->left(), $this->y, $this->width(), $h );
		$this->pdf->set_font( 'bold', $size );
		$this->pdf->set_text_color( $this->style['head_text'] );
		foreach ( $columns as $col ) {
			$this->cell_text( $col, $this->y + $pad + $size, $col['label'], $pad );
		}
		$this->y += $h;
	}

	/**
	 * Text inside a table cell, aligned.
	 *
	 * @param array{key: string, label: string, align: string, x: float, w: float} $col  Column.
	 * @param float                                                                $y    Baseline.
	 * @param string                                                               $text Text.
	 * @param float                                                                $pad  Cell padding.
	 */
	protected function cell_text( array $col, float $y, string $text, float $pad ): void {
		if ( 'right' === $col['align'] ) {
			$this->pdf->text( $col['x'] + $col['w'] - $pad, $y, $text, 'right' );
		} else {
			$this->pdf->text( $col['x'] + $pad, $y, $text );
		}
	}

	/**
	 * Totals, right-aligned under the table.
	 */
	protected function totals(): void {
		$rows = array_values( array_filter( (array) ( $this->data['totals'] ?? array() ), 'is_array' ) );
		if ( empty( $rows ) ) {
			return;
		}
		$size    = (float) $this->style['size'];
		$lead    = $size * (float) $this->style['leading'] + 4;
		$block_w = min( 280.0, $this->width() * 0.55 );
		$label_x = $this->right() - $block_w;
		if ( $this->y + count( $rows ) * $lead > $this->bottom() ) {
			$this->new_page();
		}
		foreach ( $rows as $row ) {
			$strong = ! empty( $row['strong'] );
			if ( $strong ) {
				$this->pdf->set_stroke_color( $this->style['accent'] );
				$this->pdf->line( $label_x, $this->y, $this->right(), $this->y, 0.8 );
				$this->y += 4;
			}
			$this->pdf->set_font( $strong ? 'bold' : 'regular', $strong ? $size + 1 : $size );
			$this->pdf->set_text_color( $strong ? $this->style['accent'] : $this->style['muted'] );
			$this->pdf->text( $label_x, $this->y + $size, (string) ( $row['label'] ?? '' ) );
			$this->pdf->set_text_color( $strong ? $this->style['accent'] : $this->style['text'] );
			$values = $this->pdf->wrap( (string) ( $row['value'] ?? '' ), $block_w * 0.6 );
			foreach ( $values as $i => $line ) {
				if ( $i > 0 ) {
					$this->pdf->set_font( 'regular', (float) $this->style['small'] );
				}
				$this->pdf->text( $this->right(), $this->y + $size, $line, 'right' );
				$this->y += $i < count( $values ) - 1 ? $size * 1.2 : $lead;
			}
		}
		$this->y += 14;
	}

	/**
	 * Notes (customer note and any others), full width.
	 */
	protected function notes(): void {
		$size = (float) $this->style['size'];
		$lead = $size * (float) $this->style['leading'];
		foreach ( (array) ( $this->data['notes'] ?? array() ) as $note ) {
			if ( ! is_array( $note ) || '' === trim( (string) ( $note['text'] ?? '' ) ) ) {
				continue;
			}
			if ( $this->y + 3 * $lead > $this->bottom() ) {
				$this->new_page();
			}
			$this->pdf->set_font( 'bold', $size );
			$this->pdf->set_text_color( $this->style['accent'] );
			$this->pdf->text( $this->left(), $this->y + $size, (string) ( $note['title'] ?? '' ) );
			$this->y += $lead + 2;
			$this->pdf->set_font( 'regular', $size );
			$this->pdf->set_text_color( $this->style['text'] );
			foreach ( $this->pdf->wrap( (string) $note['text'], $this->width() ) as $line ) {
				if ( $this->y + $lead > $this->bottom() ) {
					$this->new_page();
					$this->pdf->set_font( 'regular', $size );
					$this->pdf->set_text_color( $this->style['text'] );
				}
				$this->pdf->text( $this->left(), $this->y + $size, $line );
				$this->y += $lead;
			}
			$this->y += 10;
		}
	}

	/**
	 * Footer text and "Page x of y" on every page of this document.
	 */
	protected function page_footers(): void {
		$last  = $this->pdf->page_number();
		$count = $last - $this->first_page + 1;
		$small = (float) $this->style['small'];
		$lines = $this->footer_lines();
		for ( $page = $this->first_page; $page <= $last; $page++ ) {
			$this->pdf->go_to_page( $page );
			$y = $this->pdf->height() - $this->margin() - count( $lines ) * ( $small + 3 );
			if ( $lines ) {
				$this->pdf->set_stroke_color( $this->style['rule'] );
				$this->pdf->line( $this->left(), $y - 8, $this->right(), $y - 8, 0.5 );
			}
			$this->pdf->set_font( 'regular', $small );
			$this->pdf->set_text_color( $this->style['muted'] );
			foreach ( $lines as $line ) {
				$this->pdf->text( $this->left() + $this->width() / 2, $y + $small, $line, 'center' );
				$y += $small + 3;
			}
			if ( $count > 1 ) {
				$label = (string) ( $this->data['labels']['page'] ?? 'Page %1$d of %2$d' );
				$this->pdf->text( $this->right(), $this->pdf->height() - $this->margin() / 2, sprintf( $label, $page - $this->first_page + 1, $count ), 'right' );
			}
		}
		$this->pdf->go_to_page( $last );
	}

	/**
	 * Footer text, wrapped.
	 *
	 * @return string[]
	 */
	protected function footer_lines(): array {
		if ( null === $this->footer ) {
			$footer       = trim( (string) ( $this->data['footer'] ?? '' ) );
			$this->footer = '' === $footer ? array() : $this->pdf->wrap( $footer, $this->width(), 'regular', (float) $this->style['small'] );
		}
		return $this->footer;
	}

	/**
	 * Continues on a new page, with a one-line reminder of the document at the top.
	 */
	protected function new_page(): void {
		$this->pdf->add_page();
		$this->y = $this->margin();
		$small   = (float) $this->style['small'];
		$this->pdf->set_font( 'regular', $small + 0.5 );
		$this->pdf->set_text_color( $this->style['muted'] );
		$this->pdf->text( $this->left(), $this->y + $small, trim( (string) ( $this->data['title'] ?? '' ) . ' ' . (string) ( $this->data['number'] ?? '' ) ) );
		$this->pdf->text( $this->right(), $this->y + $small, (string) ( $this->data['store']['name'] ?? '' ), 'right' );
		$this->y += $small + 14;
	}

	/**
	 * Page margin.
	 */
	protected function margin(): float {
		return (float) $this->style['margin'];
	}

	/**
	 * Left edge of the content.
	 */
	protected function left(): float {
		return $this->margin();
	}

	/**
	 * Right edge of the content.
	 */
	protected function right(): float {
		return $this->pdf->width() - $this->margin();
	}

	/**
	 * Content width.
	 */
	protected function width(): float {
		return $this->right() - $this->left();
	}

	/**
	 * Lowest y content may reach before the footer.
	 */
	protected function bottom(): float {
		$lines = count( $this->footer_lines() );
		return $this->pdf->height() - $this->margin() - ( $lines ? $lines * ( (float) $this->style['small'] + 3 ) + 16 : 0 ) - 12;
	}
}
