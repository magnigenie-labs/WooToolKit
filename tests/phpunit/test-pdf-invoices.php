<?php
/**
 * PDF invoices & packing slips kit.
 *
 * @package WooToolkit
 */

use WooToolkit\Kits\PdfInvoices\Account;
use WooToolkit\Kits\PdfInvoices\Attachments;
use WooToolkit\Kits\PdfInvoices\Documents;
use WooToolkit\Kits\PdfInvoices\Downloads;
use WooToolkit\Kits\PdfInvoices\Numbering;
use WooToolkit\Kits\PdfInvoices\OrderAdmin;
use WooToolkit\Kits\PdfInvoices\Pdf\CoreFont;
use WooToolkit\Kits\PdfInvoices\Pdf\Image;
use WooToolkit\Kits\PdfInvoices\Pdf\WinAnsi;
use WooToolkit\Kits\PdfInvoices\Pdf\Writer;
use WooToolkit\Kits\PdfInvoices\Renderer;
use WooToolkit\Kits\PdfInvoices\Settings;

/**
 * PDF writer, numbering, documents, attachments and download permissions.
 */
class Test_Pdf_Invoices extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		add_filter( 'wootoolkit_pdf_invoices_compress', '__return_false' );
		reset_phpmailer_instance();
		wp_set_current_user( 0 );
	}

	public function tear_down() {
		remove_filter( 'wootoolkit_pdf_invoices_compress', '__return_false' );
		Attachments::cleanup();
		$_GET = array();
		parent::tear_down();
	}

	/**
	 * A completed order with one or more lines.
	 *
	 * @param array<string, mixed> $args customer_id, status, items (count), name, price, billing_first_name.
	 */
	private function order( array $args = array() ): WC_Order {
		$args    = array_merge(
			array(
				'customer_id'        => 0,
				'status'             => 'completed',
				'items'              => 1,
				'name'               => 'Blue mug (large)',
				'price'              => '12.34',
				'billing_first_name' => 'Ada',
			),
			$args
		);
		$product = new WC_Product_Simple();
		$product->set_name( $args['name'] );
		$product->set_sku( 'MUG-' . wp_generate_password( 6, false ) );
		$product->set_regular_price( $args['price'] );
		$product->save();

		$order = wc_create_order( array( 'customer_id' => $args['customer_id'] ) );
		for ( $i = 1; $i <= $args['items']; $i++ ) {
			$order->add_product( $product, 2 );
		}
		$order->set_billing_first_name( $args['billing_first_name'] );
		$order->set_billing_last_name( 'Buyer' );
		$order->set_billing_address_1( '1 Test Street' );
		$order->set_billing_city( 'Testville' );
		$order->set_billing_postcode( '12345' );
		$order->set_billing_country( 'GB' );
		$order->set_billing_email( 'ada@example.com' );
		$order->set_payment_method_title( 'Direct bank transfer' );
		$order->set_customer_note( 'Leave at the door.' );
		$order->calculate_totals();
		$order->set_status( $args['status'] );
		$order->save();
		return $order;
	}

	/**
	 * Checks the header, the cross-reference table and the trailer; returns the page count.
	 *
	 * @param string $pdf PDF bytes.
	 */
	private function assert_valid_pdf( string $pdf ): int {
		$this->assertSame( "%PDF-1.4\n", substr( $pdf, 0, 9 ) );
		$this->assertSame( "%%EOF\n", substr( $pdf, -6 ) );
		$this->assertSame( 1, preg_match( '/startxref\n(\d+)\n%%EOF\n$/', $pdf, $m ) );
		$xref = (int) $m[1];
		$this->assertSame( 'xref', substr( $pdf, $xref, 4 ), 'startxref points at the xref table.' );
		$this->assertSame( 1, preg_match( '/^xref\n0 (\d+)\n/', substr( $pdf, $xref ), $h ) );
		$size    = (int) $h[1];
		$entries = substr( $pdf, $xref + strlen( $h[0] ), 20 * $size );
		for ( $i = 1; $i < $size; $i++ ) {
			$entry = substr( $entries, 20 * $i, 20 );
			$this->assertSame( 1, preg_match( '/^(\d{10}) 00000 n \n$/', $entry, $e ), "xref entry {$i} is 20 bytes." );
			$this->assertSame( $i . ' 0 obj', substr( $pdf, (int) $e[1], strlen( $i . ' 0 obj' ) ), "xref offset of object {$i}." );
		}
		$this->assertMatchesRegularExpression( '/trailer\n<< \/Size ' . $size . ' \/Root 1 0 R/', $pdf );
		$this->assertSame( 1, preg_match( '/\/Type \/Pages \/Kids \[[^\]]*\] \/Count (\d+)/', $pdf, $c ) );
		$this->assertSame( (int) $c[1], preg_match_all( '/\/Type \/Page \/Parent/', $pdf ) );
		return (int) $c[1];
	}

	/**
	 * Uncompressed page content streams.
	 *
	 * @param string $pdf PDF bytes.
	 * @return string[]
	 */
	private function pages( string $pdf ): array {
		preg_match_all( '/(\d+) 0 obj\n<< \/Type \/Page \/Parent 2 0 R .*? \/Contents (\d+) 0 R >>/', $pdf, $pages, PREG_SET_ORDER );
		$out = array();
		foreach ( $pages as $page ) {
			$this->assertSame( 1, preg_match( '/\n' . $page[2] . ' 0 obj\n<< \/Length (\d+) >>\nstream\n/', $pdf, $s, PREG_OFFSET_CAPTURE ) );
			$out[] = substr( $pdf, $s[0][1] + strlen( $s[0][0] ), (int) $s[1][0] );
		}
		return $out;
	}

	private function admin(): int {
		$id = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $id );
		return $id;
	}

	public function test_settings_defaults() {
		$this->assertSame( array( 'customer_completed_order' ), Settings::emails() );
		$this->assertSame( '', Settings::prefix() );
		$this->assertSame( 1, Settings::start() );
		$this->assertSame( 'a4', Settings::paper() );
		$this->assertTrue( Settings::my_account() );
		$this->assertSame( '', Settings::logo_path() );
		$ids = wp_list_pluck( Settings::fields(), 'id' );
		$this->assertContains( Settings::OPT_EMAILS, $ids );
		$this->assertContains( Settings::OPT_PREFIX, $ids );
	}

	public function test_the_document_types_can_be_extended() {
		$this->assertSame( array( 'invoice', 'packing-slip' ), array_keys( Documents::types() ) );
		$add = static function ( $types ) {
			$types['proforma'] = array(
				'label'  => 'Proforma',
				'title'  => 'Proforma invoice',
				'prices' => true,
			);
			return $types;
		};
		add_filter( 'wootoolkit_pdf_invoices_document_types', $add );
		$type = Documents::type( 'proforma' );
		remove_filter( 'wootoolkit_pdf_invoices_document_types', $add );
		$this->assertSame( 'order', $type['subject'], 'Missing keys get defaults.' );
		$this->assertFalse( $type['numbered'] );
		$this->assertNull( Documents::type( 'proforma' ) );
	}

	public function test_the_invoice_is_a_valid_pdf_with_the_order_text() {
		$order = $this->order();
		$pdf   = Documents::render(
			array(
				array(
					'type'  => 'invoice',
					'order' => $order,
				),
			)
		);
		$this->assertSame( 1, $this->assert_valid_pdf( $pdf ) );
		$this->assertStringContainsString( '/BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding', $pdf );
		$page = $this->pages( $pdf )[0];
		$this->assertStringContainsString( '(Invoice) Tj', $page );
		$this->assertStringContainsString( '(' . $order->get_order_number() . ') Tj', $page );
		$this->assertStringContainsString( '(Blue mug \(large\)) Tj', $page, 'Parentheses are escaped.' );
		$this->assertStringContainsString( '(' . $order->get_items()[ array_key_first( $order->get_items() ) ]->get_product()->get_sku() . ') Tj', $page );
		$this->assertStringContainsString( '(Ada Buyer) Tj', $page );
		$this->assertStringContainsString( '(Direct bank transfer) Tj', $page );
		$this->assertStringContainsString( '(Leave at the door.) Tj', $page );
		$this->assertStringContainsString( '12.34', $page );
	}

	public function test_after_a_refund_the_total_shows_only_the_new_amount() {
		$order = $this->order();
		wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => 5,
			)
		);
		$order  = wc_get_order( $order->get_id() );
		$totals = Documents::data( 'invoice', $order )['totals'];
		$last   = end( $totals );
		$this->assertTrue( $last['strong'] );
		$this->assertSame( WooToolkit\Kits\PdfInvoices\Data::money( (float) $order->get_total() - 5, array( 'currency' => $order->get_currency() ) ), $last['value'] );
	}

	public function test_a_long_order_continues_on_new_pages_under_a_repeated_table_header() {
		$order = $this->order( array( 'items' => 45 ) );
		$pdf   = Documents::render(
			array(
				array(
					'type'  => 'invoice',
					'order' => $order,
				),
			)
		);
		$count = $this->assert_valid_pdf( $pdf );
		$this->assertGreaterThan( 1, $count );
		foreach ( $this->pages( $pdf ) as $i => $page ) {
			if ( $i < $count - 1 ) {
				$this->assertStringContainsString( '(Product) Tj', $page, 'Header row on page ' . ( $i + 1 ) );
			}
			$this->assertStringContainsString( '(Page ' . ( $i + 1 ) . ' of ' . $count . ') Tj', $page );
		}
		$this->assertSame( 45, substr_count( implode( '', $this->pages( $pdf ) ), '(Blue mug \(large\)) Tj' ) );
	}

	public function test_invoice_numbers_are_sequential_with_the_prefix_and_assigned_once() {
		update_option( Settings::OPT_PREFIX, 'INV-' );
		$last   = Numbering::last( 'invoice' );
		$first  = $this->order();
		$second = $this->order();

		$a = Numbering::assign( $first, 'invoice' );
		$b = Numbering::assign( $second, 'invoice' );
		$this->assertSame( 'INV-' . ( $last + 1 ), $a['number'] );
		$this->assertSame( 'INV-' . ( $last + 2 ), $b['number'] );
		$this->assertSame( $a, Numbering::assign( $first, 'invoice' ), 'An invoice keeps its number.' );

		$reloaded = wc_get_order( $first->get_id() );
		$this->assertSame( 'INV-' . ( $last + 1 ), $reloaded->get_meta( Numbering::number_key( 'invoice' ) ) );
		$this->assertSame( (string) $a['date'], $reloaded->get_meta( Numbering::date_key( 'invoice' ) ) );
		$this->assertSame( $last + 2, Numbering::last( 'invoice' ) );
	}

	public function test_numbers_never_repeat_and_come_from_the_database_not_a_cache() {
		$last    = Numbering::last( 'invoice' );
		$numbers = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$numbers[] = Numbering::assign( $this->order(), 'invoice' )['number'];
		}
		$this->assertSame( array_map( 'strval', range( $last + 1, $last + 12 ) ), $numbers );

		// Another request moved the counter on; this request's cache still has the old value.
		global $wpdb;
		$wpdb->update( $wpdb->options, array( 'option_value' => '500' ), array( 'option_name' => Numbering::COUNTER . 'invoice' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- simulates another request.
		wp_cache_set( Numbering::COUNTER . 'invoice', (string) ( $last + 12 ), 'options' );
		$this->assertSame( '501', Numbering::assign( $this->order(), 'invoice' )['number'] );

		// The start number lifts the sequence but never lowers it.
		update_option( Settings::OPT_START, '1000' );
		$this->assertSame( '1000', Numbering::assign( $this->order(), 'invoice' )['number'] );
		update_option( Settings::OPT_START, '10' );
		$this->assertSame( '1001', Numbering::assign( $this->order(), 'invoice' )['number'] );
	}

	public function test_numbering_waits_for_the_lock_held_by_another_connection() {
		global $wpdb;
		list( $host, $port, $socket ) = $wpdb->parse_db_host( DB_HOST );
		mysqli_report( MYSQLI_REPORT_OFF ); // phpcs:ignore WordPress.DB.RestrictedFunctions -- a second connection to hold the lock.
		$other = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions -- a second connection to hold the lock.
		if ( ! $other || ! $other->real_connect( $host, DB_USER, DB_PASSWORD, DB_NAME, $port ? (int) $port : 3306, $socket ) ) {
			$this->markTestSkipped( 'Needs a second database connection.' );
		}
		$name = Numbering::lock_name( 'invoice' );
		$this->assertSame( '1', (string) $other->query( "SELECT GET_LOCK( '" . $other->real_escape_string( $name ) . "', 5 )" )->fetch_row()[0] );

		$order   = $this->order();
		$timeout = static function () {
			return 1;
		};
		add_filter( 'wootoolkit_pdf_invoices_lock_timeout', $timeout );
		try {
			Numbering::assign( $order, 'invoice' );
			$this->fail( 'Numbering must not run while another request holds the lock.' );
		} catch ( RuntimeException $e ) {
			$this->assertNull( Numbering::get( $order, 'invoice' ) );
		}
		$other->query( "SELECT RELEASE_LOCK( '" . $other->real_escape_string( $name ) . "' )" );
		$other->close();
		$this->assertNotEmpty( Numbering::assign( $order, 'invoice' )['number'] );
		remove_filter( 'wootoolkit_pdf_invoices_lock_timeout', $timeout );
	}

	public function test_the_packing_slip_has_no_prices_and_no_invoice_number() {
		$order = $this->order( array( 'price' => '98.76' ) );
		$order->set_shipping_first_name( 'Grace' );
		$order->set_shipping_last_name( 'Hopper' );
		$order->set_shipping_address_1( '2 Dock Road' );
		$order->set_shipping_city( 'Portsmouth' );
		$order->set_shipping_country( 'GB' );
		$order->save();

		$pdf  = Documents::render(
			array(
				array(
					'type'  => 'packing-slip',
					'order' => $order,
				),
			)
		);
		$page = implode( '', $this->pages( $pdf ) );
		$this->assertStringContainsString( '(Packing slip) Tj', $page );
		$this->assertStringContainsString( '(Grace Hopper) Tj', $page );
		$this->assertStringContainsString( '(2) Tj', $page, 'Quantity.' );
		$this->assertStringNotContainsString( '98.76', $page );
		$this->assertStringNotContainsString( '197.52', $page );
		$this->assertStringNotContainsString( '(Price) Tj', $page );
		$this->assertStringNotContainsString( '(Total) Tj', $page );
		$this->assertStringNotContainsString( 'Direct bank transfer', $page );
		$this->assertNull( Numbering::get( wc_get_order( $order->get_id() ), 'invoice' ) );
	}

	public function test_characters_outside_winansi_are_transliterated() {
		$this->assertSame( "Caf\xE9 \x80 \x93ok\x94", WinAnsi::encode( 'Café € “ok”' ), 'WinAnsi characters are kept.' );
		$this->assertSame( 'Zaz' . "\xF3" . 'lc Privet Ellada Rs.5 ?', WinAnsi::encode( 'Zażółć Привет Ελλάδα ₹5 東' ) );
		$this->assertContains( WinAnsi::encode( "e\u{0301}" ), array( "\xE9", 'e' ), 'Decomposed accents are composed or dropped.' );
		$this->assertFalse( WinAnsi::covers( 'Łódź' ) );
		$this->assertTrue( WinAnsi::covers( 'Müller & Søn' ) );

		$font = new CoreFont();
		$this->assertEqualsWithDelta( 0.0, $font->width( '', 10 ), 0.001 );
		$this->assertEqualsWithDelta( ( 667 + 556 ) / 100, $font->width( 'Ae', 10 ), 0.001, 'AFM widths.' );

		$order = $this->order( array( 'billing_first_name' => 'Łukasz' ) );
		$page  = implode(
			'',
			$this->pages(
				Documents::render(
					array(
						array(
							'type'  => 'invoice',
							'order' => $order,
						),
					)
				)
			)
		);
		$this->assertStringContainsString( '(Lukasz Buyer) Tj', $page );
	}

	public function test_the_writer_wraps_and_right_aligns_by_measured_width() {
		$writer = new Writer( 'a4', false );
		$writer->add_font( 'regular', new CoreFont() );
		$writer->set_font( 'regular', 10 );
		$lines = $writer->wrap( 'one two three four five six seven eight nine ten', 60 );
		$this->assertGreaterThan( 1, count( $lines ) );
		foreach ( $lines as $line ) {
			$this->assertLessThanOrEqual( 60, $writer->text_width( $line ) );
		}
		$this->assertSame( array( 'abcdefghij', 'klm' ), $writer->wrap( 'abcdefghijklm', $writer->text_width( 'abcdefghij' ) ), 'Long words break.' );

		$writer->add_page();
		$writer->text( 500, 100, 'Total', 'right' );
		$pdf = $writer->output();
		$this->assert_valid_pdf( $pdf );
		$x = 500 - $writer->text_width( 'Total' );
		$this->assertStringContainsString( Writer::num( $x ) . ' ' . Writer::num( 841.89 - 100 ) . ' Td (Total) Tj', $pdf );
	}

	public function test_png_logos_with_transparency_are_flattened_and_jpegs_embedded() {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is needed to make the test images.' );
		}
		$dir = get_temp_dir();
		$gd  = imagecreatetruecolor( 4, 2 );
		imagesavealpha( $gd, true );
		imagealphablending( $gd, false );
		imagefill( $gd, 0, 0, imagecolorallocatealpha( $gd, 0, 0, 0, 127 ) );
		imagesetpixel( $gd, 0, 0, imagecolorallocatealpha( $gd, 255, 0, 0, 0 ) );
		imagepng( $gd, $dir . 'wtk-alpha.png' );
		imagejpeg( imagecreatetruecolor( 3, 3 ), $dir . 'wtk-logo.jpg' );

		$png = Image::from_file( $dir . 'wtk-alpha.png' );
		$this->assertNotNull( $png );
		$this->assertSame( array( 4, 2 ), array( $png->width, $png->height ) );
		$this->assertStringContainsString( '/DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode', $png->dict );
		$rgb = gzuncompress( $png->data );
		$this->assertSame( 4 * 2 * 3, strlen( $rgb ) );
		$this->assertSame( "\xFF\x00\x00", substr( $rgb, 0, 3 ), 'Opaque red stays red.' );
		$this->assertSame( "\xFF\xFF\xFF", substr( $rgb, 3, 3 ), 'Transparent becomes white.' );

		$jpeg = Image::from_file( $dir . 'wtk-logo.jpg' );
		$this->assertStringContainsString( '/DCTDecode', $jpeg->dict );
		$this->assertNull( Image::from_string( 'GIF89a' ) );

		$writer = new Writer();
		$writer->add_font( 'regular', new CoreFont() );
		$writer->add_page();
		$this->assertTrue( $writer->image( $dir . 'wtk-alpha.png', 10, 10, 40, 20 ) );
		$this->assertFalse( $writer->image( $dir . 'missing.png', 10, 10, 40, 20 ) );
		$pdf = $writer->output();
		$this->assert_valid_pdf( $pdf );
		$this->assertStringContainsString( '/Subtype /Image /Width 4 /Height 2', $pdf );
		wp_delete_file( $dir . 'wtk-alpha.png' );
		wp_delete_file( $dir . 'wtk-logo.jpg' );
	}

	public function test_the_invoice_is_attached_to_the_chosen_emails_only() {
		$order = $this->order();
		$this->assertSame( array(), apply_filters( 'woocommerce_email_attachments', array(), 'customer_processing_order', $order, null ) );
		$this->assertNull( Numbering::get( $order, 'invoice' ), 'No invoice for an email that does not attach it.' );

		$files = apply_filters( 'woocommerce_email_attachments', array(), 'customer_completed_order', $order, null );
		$this->assertCount( 1, $files );
		$this->assertFileExists( $files[0] );
		$this->assertStringStartsWith( '%PDF-1.4', file_get_contents( $files[0] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file.
		$this->assertMatchesRegularExpression( '#/' . Attachments::DIR . '/[A-Za-z0-9]{24}/invoice-[^/]+\.pdf$#', $files[0] );
		$this->assertFileExists( dirname( $files[0], 2 ) . '/.htaccess' );
		$this->assertFileExists( dirname( $files[0], 2 ) . '/index.php' );

		update_option( Settings::OPT_EMAILS, array( 'customer_processing_order' ) );
		$this->assertCount( 1, apply_filters( 'woocommerce_email_attachments', array(), 'customer_processing_order', $order, null ) );
		$this->assertCount( 0, apply_filters( 'woocommerce_email_attachments', array(), 'customer_completed_order', $order, null ) );
	}

	public function test_the_completed_order_email_carries_the_invoice_and_the_file_is_deleted_after_sending() {
		$order  = $this->order();
		$emails = WC()->mailer()->get_emails();
		$emails['WC_Email_Customer_Completed_Order']->trigger( $order->get_id(), $order );

		$mailer      = tests_retrieve_phpmailer_instance();
		$attachments = $mailer->getAttachments();
		$this->assertCount( 1, $attachments );
		$this->assertMatchesRegularExpression( '/^invoice-\d+\.pdf$/', $attachments[0][2] );
		$this->assertFileDoesNotExist( $attachments[0][0], 'Deleted once wp_mail() has sent it.' );
		$this->assertSame( array(), Attachments::pending() );
		$this->assertNotNull( Numbering::get( wc_get_order( $order->get_id() ), 'invoice' ) );
	}

	public function test_no_attachments_while_the_kit_is_switched_off() {
		$order  = $this->order();
		$active = get_option( 'wootoolkit_active_kits' );
		update_option( 'wootoolkit_active_kits', array_merge( $active, array( 'pdf-invoices' => 'no' ) ) );
		$files = apply_filters( 'woocommerce_email_attachments', array(), 'customer_completed_order', $order, null );
		update_option( 'wootoolkit_active_kits', $active );
		$this->assertSame( array(), $files );
		$this->assertNull( Numbering::get( $order, 'invoice' ) );
	}

	public function test_staff_downloads_need_the_nonce_and_the_capability() {
		$order   = $this->order();
		$request = array(
			'type' => 'invoice',
			'ids'  => $order->get_id() . ',999999',
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$this->assertWPError( Downloads::check_admin( $request + array( '_wpnonce' => wp_create_nonce( Downloads::NONCE ) ) ), 'Customers are refused.' );

		$this->admin();
		$this->assertWPError( Downloads::check_admin( $request ), 'No nonce.' );
		$this->assertWPError( Downloads::check_admin( array( 'type' => 'nope' ) + $request + array( '_wpnonce' => wp_create_nonce( Downloads::NONCE ) ) ) );
		$documents = Downloads::check_admin( $request + array( '_wpnonce' => wp_create_nonce( Downloads::NONCE ) ) );
		$this->assertIsArray( $documents );
		$this->assertCount( 1, $documents, 'Unknown ids are skipped.' );
		$this->assertSame( $order->get_id(), $documents[0]['order']->get_id() );

		parse_str( (string) wp_parse_url( html_entity_decode( Downloads::admin_url( 'packing-slip', array( $order->get_id() ) ) ), PHP_URL_QUERY ), $query );
		$this->assertIsArray( Downloads::check_admin( $query ), 'The link the buttons use passes.' );
	}

	public function test_customers_only_get_their_own_invoices() {
		$owner = self::factory()->user->create( array( 'role' => 'customer' ) );
		$other = self::factory()->user->create( array( 'role' => 'customer' ) );
		$order = $this->order( array( 'customer_id' => $owner ) );
		$args  = array(
			Downloads::QUERY => 'invoice',
			'order'          => $order->get_id(),
			'key'            => $order->get_order_key(),
		);

		wp_set_current_user( $owner );
		$this->assertIsArray( Downloads::check_customer( $args + array( '_wpnonce' => wp_create_nonce( Downloads::NONCE_FRONT ) ) ) );
		$this->assertWPError( Downloads::check_customer( $args ), 'Logged-in requests need the nonce.' );
		$this->assertWPError( Downloads::check_customer( array( Downloads::QUERY => 'packing-slip' ) + $args + array( '_wpnonce' => wp_create_nonce( Downloads::NONCE_FRONT ) ) ), 'Packing slips are for staff.' );

		wp_set_current_user( $other );
		$this->assertWPError( Downloads::check_customer( $args + array( '_wpnonce' => wp_create_nonce( Downloads::NONCE_FRONT ) ) ), 'Another customer, even with the order key.' );

		wp_set_current_user( 0 );
		$this->assertWPError( Downloads::check_customer( $args ), 'A guest with the key of a customer account order.' );
		$this->assertWPError( Downloads::check_customer( array( 'key' => '' ) + $args ), 'A guest with only the order id.' );

		$guest_order = $this->order();
		$guest       = array(
			Downloads::QUERY => 'invoice',
			'order'          => $guest_order->get_id(),
		);
		$this->assertWPError( Downloads::check_customer( $guest + array( 'key' => 'wc_order_wrong' ) ) );
		$this->assertIsArray( Downloads::check_customer( $guest + array( 'key' => $guest_order->get_order_key() ) ), 'Guest order + its key.' );

		$unpaid      = $this->order( array( 'status' => 'pending' ) );
		$unpaid_args = array(
			'order' => $unpaid->get_id(),
			'key'   => $unpaid->get_order_key(),
		);
		$this->assertWPError( Downloads::check_customer( $unpaid_args + $guest ), 'No invoice for unpaid orders.' );

		update_option( Settings::OPT_MY_ACCOUNT, 'no' );
		$this->assertWPError( Downloads::check_customer( $guest + array( 'key' => $guest_order->get_order_key() ) ), 'Customer downloads switched off.' );
	}

	public function test_my_account_shows_the_invoice_for_paid_orders() {
		$paid   = $this->order();
		$unpaid = $this->order( array( 'status' => 'on-hold' ) );

		$actions = Account::orders_actions( array(), $paid );
		$this->assertArrayHasKey( 'wootoolkit-pdf-invoice', $actions );
		$this->assertStringContainsString( 'wootoolkit-pdf=invoice', $actions['wootoolkit-pdf-invoice']['url'] );
		$this->assertStringContainsString( 'key=' . $paid->get_order_key(), $actions['wootoolkit-pdf-invoice']['url'] );
		$this->assertSame( array(), Account::orders_actions( array(), $unpaid ) );

		ob_start();
		Account::order_details( $paid );
		$this->assertStringContainsString( 'Download invoice', ob_get_clean() );
	}

	public function test_order_screen_and_orders_list_buttons() {
		$order = $this->order();
		$this->assertSame( array(), OrderAdmin::list_actions( array(), $order ), 'Nothing for visitors.' );

		$this->admin();
		$actions = OrderAdmin::list_actions( array(), $order );
		$this->assertSame( array( 'wootoolkit_pdf_invoice', 'wootoolkit_pdf_packing-slip' ), array_keys( $actions ) );
		$this->assertStringContainsString( '_wpnonce=', $actions['wootoolkit_pdf_invoice']['url'] );

		Numbering::assign( $order, 'invoice' );
		ob_start();
		OrderAdmin::render_meta_box( $order );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'admin-post.php?action=wootoolkit_pdf_invoices', $html );
		$this->assertStringContainsString( Numbering::get( $order, 'invoice' )['number'], $html );
	}

	public function test_pro_kits_can_change_the_data_and_the_renderer() {
		$order = $this->order();
		$data  = static function ( $data ) {
			$data['meta'][] = array(
				'label' => 'PO number',
				'value' => 'PO-777',
			);
			return $data;
		};
		add_filter( 'wootoolkit_pdf_invoices_document_data', $data );
		$pdf = Documents::render(
			array(
				array(
					'type'  => 'invoice',
					'order' => $order,
				),
			)
		);
		remove_filter( 'wootoolkit_pdf_invoices_document_data', $data );
		$this->assertStringContainsString( '(PO-777) Tj', $this->pages( $pdf )[0] );

		$renderer = new class() implements Renderer {
			public $seen = array();
			public function render( array $documents ): string {
				$this->seen = $documents;
				return '%PDF-custom';
			}
		};
		$swap     = static function () use ( $renderer ) {
			return $renderer;
		};
		add_filter( 'wootoolkit_pdf_invoices_renderer', $swap );
		$out = Documents::render(
			array(
				array(
					'type'  => 'packing-slip',
					'order' => $order,
				),
			)
		);
		remove_filter( 'wootoolkit_pdf_invoices_renderer', $swap );
		$this->assertSame( '%PDF-custom', $out );
		$this->assertSame( 'packing-slip', $renderer->seen[0]['type'] );
		$this->assertFalse( $renderer->seen[0]['prices'] );
	}
}
