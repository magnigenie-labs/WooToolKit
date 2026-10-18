<?php
/**
 * PDF invoices settings.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * Option names, defaults and the settings section.
 */
final class Settings {

	const OPT_EMAILS     = 'wootoolkit_pdf_invoices_emails';
	const OPT_PREFIX     = 'wootoolkit_pdf_invoices_prefix';
	const OPT_START      = 'wootoolkit_pdf_invoices_start';
	const OPT_LOGO       = 'wootoolkit_pdf_invoices_logo';
	const OPT_STORE_INFO = 'wootoolkit_pdf_invoices_store_info';
	const OPT_FOOTER     = 'wootoolkit_pdf_invoices_footer';
	const OPT_PAPER      = 'wootoolkit_pdf_invoices_paper';
	const OPT_MY_ACCOUNT = 'wootoolkit_pdf_invoices_my_account';

	/**
	 * Emails the invoice is attached to (WC_Email ids).
	 *
	 * @return string[]
	 */
	public static function emails(): array {
		$emails = get_option( self::OPT_EMAILS, array( 'customer_completed_order' ) );
		return array_values( array_filter( array_map( 'strval', is_array( $emails ) ? $emails : array() ) ) );
	}

	/**
	 * Invoice number prefix.
	 */
	public static function prefix(): string {
		return trim( (string) get_option( self::OPT_PREFIX, '' ) );
	}

	/**
	 * The lowest invoice number to use.
	 */
	public static function start(): int {
		return max( 1, (int) get_option( self::OPT_START, 1 ) );
	}

	/**
	 * Local path of the logo image, '' when none is set or it is not a local JPEG or PNG.
	 * The setting holds a media library attachment id or an image URL from this site's uploads.
	 */
	public static function logo_path(): string {
		$value = trim( (string) get_option( self::OPT_LOGO, '' ) );
		if ( '' === $value ) {
			return '';
		}
		$id = ctype_digit( $value ) ? (int) $value : attachment_url_to_postid( $value );
		if ( $id > 0 ) {
			$path = get_attached_file( $id );
		} else {
			// An uploads URL that is not an attachment (e.g. a resized copy).
			$uploads = wp_get_upload_dir();
			$base    = (string) $uploads['baseurl'];
			$path    = '' !== $base && 0 === strpos( set_url_scheme( $value, 'http' ), set_url_scheme( $base, 'http' ) )
				? (string) $uploads['basedir'] . substr( set_url_scheme( $value, 'http' ), strlen( set_url_scheme( $base, 'http' ) ) )
				: '';
		}
		if ( ! is_string( $path ) || '' === $path || false !== strpos( $path, '..' ) || ! preg_match( '/\.(jpe?g|png)$/i', $path ) || ! is_readable( $path ) ) {
			return '';
		}
		return $path;
	}

	/**
	 * Extra store lines (e.g. VAT or company registration number).
	 */
	public static function store_info(): string {
		return trim( (string) get_option( self::OPT_STORE_INFO, '' ) );
	}

	/**
	 * Footer text.
	 */
	public static function footer(): string {
		return trim( (string) get_option( self::OPT_FOOTER, '' ) );
	}

	/**
	 * Paper size: a4 or letter.
	 */
	public static function paper(): string {
		return 'letter' === get_option( self::OPT_PAPER, 'a4' ) ? 'letter' : 'a4';
	}

	/**
	 * Whether customers can download their invoice from My Account and the order received page.
	 */
	public static function my_account(): bool {
		return 'no' !== get_option( self::OPT_MY_ACCOUNT, 'yes' );
	}

	/**
	 * Order emails to choose from: id => title.
	 *
	 * @return array<string, string>
	 */
	public static function email_options(): array {
		$options = array();
		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			foreach ( WC()->mailer()->get_emails() as $email ) {
				if ( $email instanceof \WC_Email && ! empty( $email->id ) ) {
					$options[ $email->id ] = $email->get_title() . ( $email->is_customer_email() ? '' : ' (' . __( 'admin', 'wootoolkit' ) . ')' );
				}
			}
		}
		if ( empty( $options ) ) {
			$options['customer_completed_order'] = __( 'Completed order', 'wootoolkit' );
		}
		return $options;
	}

	/**
	 * Settings fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function fields(): array {
		return array(
			array(
				'id'    => 'wootoolkit_pdf_invoices',
				'type'  => 'title',
				'title' => __( 'PDF invoices & packing slips', 'wootoolkit' ),
				'desc'  => __( 'Open an invoice or packing slip from the order screen or the Actions column of the orders list. Invoices get the next invoice number the first time one is created for an order. The built-in fonts cover Western European languages; other characters are transliterated (for example Ł becomes L). The PDF invoices Pro kit adds full Unicode and right-to-left text.', 'wootoolkit' ),
			),
			array(
				'id'      => self::OPT_EMAILS,
				'type'    => 'multiselect',
				'title'   => __( 'Attach invoice to', 'wootoolkit' ),
				'class'   => 'wc-enhanced-select',
				'options' => self::email_options(),
				'default' => array( 'customer_completed_order' ),
			),
			array(
				'id'          => self::OPT_PREFIX,
				'type'        => 'text',
				'title'       => __( 'Invoice number prefix', 'wootoolkit' ),
				'desc'        => __( 'For example INV-. Numbers themselves are sequential and never reused.', 'wootoolkit' ),
				'placeholder' => 'INV-',
				'default'     => '',
			),
			array(
				'id'                => self::OPT_START,
				'type'              => 'number',
				'title'             => __( 'Start invoice numbers at', 'wootoolkit' ),
				'desc'              => __( 'Used when it is higher than the next number. Numbers never go back.', 'wootoolkit' ),
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
				'default'           => '1',
			),
			array(
				'id'          => self::OPT_LOGO,
				'type'        => 'text',
				'title'       => __( 'Logo', 'wootoolkit' ),
				'desc'        => __( 'A JPEG or PNG from the media library: its attachment id or URL. Leave empty to show the store name.', 'wootoolkit' ),
				'class'       => 'wootoolkit-pdf-logo',
				'placeholder' => __( 'Attachment id or image URL', 'wootoolkit' ),
				'default'     => '',
			),
			array(
				'id'      => self::OPT_STORE_INFO,
				'type'    => 'textarea',
				'title'   => __( 'Extra store details', 'wootoolkit' ),
				'desc'    => __( 'Shown under the store address (WooCommerce > Settings > General), e.g. VAT or company number.', 'wootoolkit' ),
				'css'     => 'min-height:60px',
				'default' => '',
			),
			array(
				'id'      => self::OPT_FOOTER,
				'type'    => 'textarea',
				'title'   => __( 'Footer text', 'wootoolkit' ),
				'desc'    => __( 'Shown at the bottom of every page, e.g. payment terms or a thank-you note.', 'wootoolkit' ),
				'css'     => 'min-height:60px',
				'default' => '',
			),
			array(
				'id'      => self::OPT_PAPER,
				'type'    => 'select',
				'title'   => __( 'Paper size', 'wootoolkit' ),
				'options' => array(
					'a4'     => __( 'A4', 'wootoolkit' ),
					'letter' => __( 'US Letter', 'wootoolkit' ),
				),
				'default' => 'a4',
			),
			array(
				'id'      => self::OPT_MY_ACCOUNT,
				'type'    => 'checkbox',
				'title'   => __( 'Customers', 'wootoolkit' ),
				'desc'    => __( 'Customers can download the invoice of a paid order from My Account and the order received page', 'wootoolkit' ),
				'default' => 'yes',
			),
			array(
				'id'   => 'wootoolkit_pdf_invoices',
				'type' => 'sectionend',
			),
		);
	}
}
