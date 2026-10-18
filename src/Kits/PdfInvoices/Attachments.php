<?php
/**
 * PDF attachments on WooCommerce emails.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices;

use WooToolkit\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Attaches the invoice to the emails chosen in the settings. Each PDF is written to a random folder
 * under wp-content/uploads/wootoolkit-pdf-invoices (index.php and a deny-all .htaccess inside) and
 * deleted as soon as wp_mail() has sent or failed, or at the end of the request at the latest.
 */
final class Attachments {

	/**
	 * Folder under uploads.
	 */
	const DIR = 'wootoolkit-pdf-invoices';

	/**
	 * Files written in this request.
	 *
	 * @var string[]
	 */
	private static $files = array();

	/**
	 * Hooks attachments.
	 */
	public static function hooks(): void {
		add_filter( 'woocommerce_email_attachments', array( self::class, 'attach' ), 10, 4 );
		add_action( 'wp_mail_succeeded', array( self::class, 'after_mail' ) );
		add_action( 'wp_mail_failed', array( self::class, 'after_mail_failed' ) );
		add_action( 'shutdown', array( self::class, 'cleanup' ) );
	}

	/**
	 * Adds the documents for this email.
	 *
	 * @param mixed     $attachments Attachments (file paths).
	 * @param string    $email_id    WC_Email id.
	 * @param mixed     $subject     The email's object (an order for order emails).
	 * @param \WC_Email $email       Email.
	 * @return mixed
	 */
	public static function attach( $attachments, $email_id = '', $subject = null, $email = null ) {
		if ( ! is_array( $attachments ) || ! Plugin::instance()->registry()->is_active( 'pdf-invoices' ) ) {
			return $attachments;
		}
		$documents = array();
		if ( $subject instanceof \WC_Order && in_array( (string) $email_id, Settings::emails(), true ) ) {
			$documents[] = array(
				'type'  => 'invoice',
				'order' => $subject,
			);
		}

		/**
		 * Documents attached to an email, each array( 'type' => document type, 'order' => order or refund ).
		 *
		 * @param array<int, array{type: string, order: \WC_Abstract_Order}> $documents Documents.
		 * @param string                                                     $email_id  WC_Email id.
		 * @param mixed                                                      $subject   The email's object.
		 * @param \WC_Email|null                                             $email     Email.
		 */
		$documents = apply_filters( 'wootoolkit_pdf_invoices_email_attachments', $documents, (string) $email_id, $subject, $email );

		foreach ( is_array( $documents ) ? $documents : array() as $document ) {
			if ( ! is_array( $document ) || ! isset( $document['type'], $document['order'] ) || ! Documents::accepts( (string) $document['type'], $document['order'] ) ) {
				continue;
			}
			$document = array(
				'type'  => (string) $document['type'],
				'order' => $document['order'],
			);
			try {
				$path = self::write( Documents::render( array( $document ) ), Documents::filename( array( $document ) ) );
			} catch ( \Throwable $e ) {
				// Never stop the email; log why the PDF is missing.
				wc_get_logger()->error( 'PDF attachment failed: ' . $e->getMessage(), array( 'source' => 'wootoolkit-pdf-invoices' ) );
				$path = '';
			}
			if ( '' !== $path ) {
				$attachments[] = $path;
			}
		}
		return $attachments;
	}

	/**
	 * Writes a PDF to a new random folder and returns its path ('' on failure).
	 *
	 * @param string $pdf      PDF bytes.
	 * @param string $filename File name.
	 */
	public static function write( string $pdf, string $filename ): string {
		$base = self::base_dir();
		if ( '' === $base ) {
			return '';
		}
		self::purge_stale( $base );
		$dir = $base . '/' . wp_generate_password( 24, false, false );
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		$path = $dir . '/' . sanitize_file_name( $filename );
		if ( false === file_put_contents( $path, $pdf ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a temporary file in the kit's protected folder.
			return '';
		}
		self::$files[] = $path;
		return $path;
	}

	/**
	 * The protected temp folder, created with its index.php and .htaccess ('' when uploads are not writable).
	 */
	public static function base_dir(): string {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return '';
		}
		$base = untrailingslashit( (string) $uploads['basedir'] ) . '/' . self::DIR;
		if ( ! wp_mkdir_p( $base ) ) {
			return '';
		}
		$guards = array(
			'index.php' => "<?php\n// Silence is golden.\n",
			'.htaccess' => "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
		);
		foreach ( $guards as $file => $contents ) {
			if ( ! file_exists( $base . '/' . $file ) ) {
				file_put_contents( $base . '/' . $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- guard files for the protected folder.
			}
		}
		return $base;
	}

	/**
	 * Deletes this request's files that were attachments of a sent email.
	 *
	 * @param mixed $mail wp_mail() arguments (to, subject, message, headers, attachments).
	 */
	public static function after_mail( $mail ): void {
		$sent = is_array( $mail ) && isset( $mail['attachments'] ) ? (array) $mail['attachments'] : array();
		foreach ( $sent as $path ) {
			if ( in_array( $path, self::$files, true ) ) {
				self::delete( (string) $path );
			}
		}
	}

	/**
	 * Same after a failed send (the WP_Error carries the mail data).
	 *
	 * @param mixed $error WP_Error.
	 */
	public static function after_mail_failed( $error ): void {
		if ( $error instanceof \WP_Error ) {
			self::after_mail( $error->get_error_data() );
		}
	}

	/**
	 * Deletes every file this request wrote.
	 */
	public static function cleanup(): void {
		foreach ( self::$files as $path ) {
			self::delete( $path );
		}
	}

	/**
	 * Files written in this request and not deleted yet.
	 *
	 * @return string[]
	 */
	public static function pending(): array {
		return self::$files;
	}

	/**
	 * Deletes one temp file and its folder.
	 *
	 * @param string $path File path.
	 */
	private static function delete( string $path ): void {
		$base = self::base_dir();
		if ( '' === $base || 0 !== strpos( $path, $base . '/' ) ) {
			return;
		}
		wp_delete_file( $path );
		@rmdir( dirname( $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- the empty random folder.
		self::$files = array_values( array_diff( self::$files, array( $path ) ) );
	}

	/**
	 * Removes folders left behind by requests that died before cleaning up (older than an hour).
	 *
	 * @param string $base Temp folder.
	 */
	private static function purge_stale( string $base ): void {
		foreach ( (array) glob( $base . '/*', GLOB_ONLYDIR ) as $dir ) {
			if ( ! is_string( $dir ) || filemtime( $dir ) > time() - HOUR_IN_SECONDS ) {
				continue;
			}
			foreach ( (array) glob( $dir . '/*' ) as $file ) {
				if ( is_string( $file ) ) {
					wp_delete_file( $file );
				}
			}
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- a stale random folder.
		}
	}
}
