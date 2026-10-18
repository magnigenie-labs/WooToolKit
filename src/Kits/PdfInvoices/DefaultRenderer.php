<?php
/**
 * The built-in renderer.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices;

use WooToolkit\Kits\PdfInvoices\Pdf\CoreFont;
use WooToolkit\Kits\PdfInvoices\Pdf\Writer;

defined( 'ABSPATH' ) || exit;

/**
 * Draws documents with the Template on the core Helvetica fonts. Extend it and override writer()
 * or template() to change the fonts or the layout.
 */
class DefaultRenderer implements Renderer {

	/**
	 * Renders documents; each starts on a new page.
	 *
	 * @param array<int, array<string, mixed>> $documents Document data arrays.
	 * @return string PDF bytes.
	 */
	public function render( array $documents ): string {
		$writer = $this->writer( Settings::paper() );
		if ( isset( $documents[0]['title'], $documents[0]['number'] ) ) {
			$writer->set_info( 'Title', trim( $documents[0]['title'] . ' ' . $documents[0]['number'] ) );
		}
		if ( isset( $documents[0]['store']['name'] ) ) {
			$writer->set_info( 'Author', (string) $documents[0]['store']['name'] );
		}
		foreach ( $documents as $data ) {
			$this->template( $writer, $data )->draw( $data );
		}
		return $writer->output();
	}

	/**
	 * A writer with the "regular" and "bold" fonts registered.
	 *
	 * @param string $paper a4 or letter.
	 */
	protected function writer( string $paper ): Writer {
		$writer = new Writer( $paper, Documents::compress() );
		$writer->add_font( 'regular', new CoreFont( false ) );
		$writer->add_font( 'bold', new CoreFont( true ) );
		return $writer;
	}

	/**
	 * The template that draws one document.
	 *
	 * @param Writer               $writer Writer.
	 * @param array<string, mixed> $data   Document data.
	 */
	protected function template( Writer $writer, array $data ): Template {
		unset( $data );
		return new Template( $writer );
	}
}
