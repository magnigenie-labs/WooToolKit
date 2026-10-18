<?php
/**
 * Document types and rendering.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * The document types (invoice, packing slip, and any a Pro kit adds) and the pipeline that turns
 * orders into one PDF: number -> data array -> renderer.
 */
final class Documents {

	/**
	 * Registered document types.
	 *
	 * Each type: label (admin button), title (printed heading), prices (bool), numbered (bool: gets a
	 * sequential number), number_label and date_label (printed), subject ("order" or "refund"),
	 * customer (bool: customers may download it), download (customer button text), filename (slug
	 * used in file names).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function types(): array {
		$types = array(
			'invoice'      => array(
				'label'        => __( 'Invoice', 'wootoolkit' ),
				'title'        => __( 'Invoice', 'wootoolkit' ),
				'prices'       => true,
				'numbered'     => true,
				'number_label' => __( 'Invoice number', 'wootoolkit' ),
				'date_label'   => __( 'Invoice date', 'wootoolkit' ),
				'subject'      => 'order',
				'customer'     => true,
				'download'     => __( 'Download invoice', 'wootoolkit' ),
				'filename'     => 'invoice',
			),
			'packing-slip' => array(
				'label'        => __( 'Packing slip', 'wootoolkit' ),
				'title'        => __( 'Packing slip', 'wootoolkit' ),
				'prices'       => false,
				'numbered'     => false,
				'number_label' => '',
				'date_label'   => '',
				'subject'      => 'order',
				'customer'     => false,
				'filename'     => 'packing-slip',
			),
		);

		/**
		 * Document types. Add a type here and it can be rendered, downloaded and attached.
		 *
		 * @param array<string, array<string, mixed>> $types Type id => definition (see Documents::types()).
		 */
		$types = apply_filters( 'wootoolkit_pdf_invoices_document_types', $types );

		$out = array();
		foreach ( is_array( $types ) ? $types : array() as $id => $type ) {
			if ( is_array( $type ) && '' !== sanitize_key( (string) $id ) ) {
				$out[ sanitize_key( (string) $id ) ] = array_merge(
					array(
						'label'        => (string) $id,
						'title'        => (string) $id,
						'prices'       => true,
						'numbered'     => false,
						'number_label' => '',
						'date_label'   => '',
						'subject'      => 'order',
						'customer'     => false,
						'filename'     => sanitize_key( (string) $id ),
					),
					$type
				);
			}
		}
		return $out;
	}

	/**
	 * One document type, or null.
	 *
	 * @param string $type Type id.
	 * @return array<string, mixed>|null
	 */
	public static function type( string $type ): ?array {
		$types = self::types();
		return isset( $types[ $type ] ) ? $types[ $type ] : null;
	}

	/**
	 * Whether a subject (order or refund) fits a document type.
	 *
	 * @param string $type    Type id.
	 * @param mixed  $subject Order or refund.
	 */
	public static function accepts( string $type, $subject ): bool {
		$def = self::type( $type );
		if ( null === $def ) {
			return false;
		}
		if ( 'refund' === $def['subject'] ) {
			return $subject instanceof \WC_Order_Refund;
		}
		return $subject instanceof \WC_Order;
	}

	/**
	 * The data array of one document, numbering it first when its type is numbered.
	 *
	 * @param string             $type    Type id.
	 * @param \WC_Abstract_Order $subject Order or refund.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException For an unknown type or a subject the type does not take.
	 */
	public static function data( string $type, \WC_Abstract_Order $subject ): array {
		if ( ! self::accepts( $type, $subject ) ) {
			throw new \InvalidArgumentException( 'Unknown document type or wrong order type.' );
		}
		$def = (array) self::type( $type );
		if ( ! empty( $def['numbered'] ) ) {
			Numbering::assign( $subject, $type );
		}
		return Data::build( $subject, $type, $def );
	}

	/**
	 * Renders documents into one PDF: each document starts on a new page.
	 *
	 * @param array<int, array{type: string, order: \WC_Abstract_Order}> $documents Documents.
	 * @return string PDF bytes.
	 */
	public static function render( array $documents ): string {
		$data = array();
		foreach ( $documents as $document ) {
			$data[] = self::data( $document['type'], $document['order'] );
		}
		return self::renderer( $data )->render( $data );
	}

	/**
	 * The renderer.
	 *
	 * @param array<int, array<string, mixed>> $data Document data arrays about to be rendered.
	 */
	public static function renderer( array $data = array() ): Renderer {
		$default = new DefaultRenderer();

		/**
		 * The renderer that turns document data into PDF bytes. Return another Renderer (for
		 * example one with embedded Unicode fonts) to replace the built-in one.
		 *
		 * @param Renderer                         $renderer Renderer.
		 * @param array<int, array<string, mixed>> $data     Document data arrays.
		 */
		$renderer = apply_filters( 'wootoolkit_pdf_invoices_renderer', $default, $data );
		return $renderer instanceof Renderer ? $renderer : $default;
	}

	/**
	 * File name for documents, e.g. invoice-INV-12.pdf or packing-slips.pdf.
	 *
	 * @param array<int, array{type: string, order: \WC_Abstract_Order}> $documents Documents.
	 */
	public static function filename( array $documents ): string {
		if ( empty( $documents ) ) {
			return 'document.pdf';
		}
		$first = $documents[0];
		$def   = (array) self::type( $first['type'] );
		$slug  = isset( $def['filename'] ) ? (string) $def['filename'] : sanitize_key( $first['type'] );
		if ( count( $documents ) > 1 ) {
			$name = $slug . '-' . count( $documents );
		} else {
			$stored = ! empty( $def['numbered'] ) ? Numbering::get( $first['order'], $first['type'] ) : null;
			$name   = $slug . '-' . ( null !== $stored ? $stored['number'] : self::order_number( $first['order'] ) );
		}
		$name = sanitize_file_name( $name . '.pdf' );
		return '' !== $name && '.pdf' !== $name ? $name : 'document.pdf';
	}

	/**
	 * Order number of an order, or of a refund's parent order.
	 *
	 * @param \WC_Abstract_Order $subject Order or refund.
	 */
	public static function order_number( \WC_Abstract_Order $subject ): string {
		if ( $subject instanceof \WC_Order_Refund ) {
			$parent = wc_get_order( $subject->get_parent_id() );
			return $parent instanceof \WC_Order ? (string) $parent->get_order_number() : (string) $subject->get_parent_id();
		}
		return $subject instanceof \WC_Order ? (string) $subject->get_order_number() : (string) $subject->get_id();
	}

	/**
	 * Whether the store has turned compression off (tests read the content streams).
	 */
	public static function compress(): bool {
		/**
		 * Whether PDF streams are compressed (when zlib is available).
		 *
		 * @param bool $compress Compress. Default true.
		 */
		return (bool) apply_filters( 'wootoolkit_pdf_invoices_compress', true );
	}
}
