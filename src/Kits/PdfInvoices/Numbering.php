<?php
/**
 * Sequential document numbers.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\PdfInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * Gives an order (or refund) its document number the first time the document is created, and keeps
 * it: the number and date are stored on the order as meta (CRUD, so HPOS works). Each sequence is a
 * counter in wp_options, read and incremented under a MySQL named lock (GET_LOCK) and read straight
 * from the database, so two requests at once never get the same number, even with an object cache.
 */
final class Numbering {

	/**
	 * Seconds to wait for the lock.
	 */
	const LOCK_TIMEOUT = 10;

	/**
	 * Counter option name prefix; the sequence key follows.
	 */
	const COUNTER = 'wootoolkit_pdf_invoices_counter_';

	/**
	 * Meta key holding a document's number.
	 *
	 * @param string $type Document type.
	 */
	public static function number_key( string $type ): string {
		return '_wootoolkit_pdf_' . str_replace( '-', '_', sanitize_key( $type ) ) . '_number';
	}

	/**
	 * Meta key holding a document's date (Unix timestamp).
	 *
	 * @param string $type Document type.
	 */
	public static function date_key( string $type ): string {
		return '_wootoolkit_pdf_' . str_replace( '-', '_', sanitize_key( $type ) ) . '_date';
	}

	/**
	 * The stored number and date of a document, or null when it has none yet.
	 *
	 * @param \WC_Abstract_Order $subject Order or refund.
	 * @param string             $type    Document type.
	 * @return array{number: string, date: int}|null
	 */
	public static function get( \WC_Abstract_Order $subject, string $type ): ?array {
		$number = (string) $subject->get_meta( self::number_key( $type ), true );
		if ( '' === $number ) {
			return null;
		}
		$date = (int) $subject->get_meta( self::date_key( $type ), true );
		return array(
			'number' => $number,
			'date'   => $date > 0 ? $date : time(),
		);
	}

	/**
	 * The document's number and date, assigning the next number of its sequence when it has none.
	 *
	 * @param \WC_Abstract_Order $subject Order or refund.
	 * @param string             $type    Document type.
	 * @return array{number: string, date: int}
	 * @throws \RuntimeException When the lock cannot be taken.
	 */
	public static function assign( \WC_Abstract_Order $subject, string $type ): array {
		$existing = self::get( $subject, $type );
		if ( null !== $existing ) {
			return $existing;
		}
		$date = time();

		/**
		 * The counter a document type draws its numbers from. Return a different key for a
		 * separate sequence (e.g. one per year).
		 *
		 * @param string             $sequence Sequence key; the document type by default.
		 * @param string             $type     Document type.
		 * @param \WC_Abstract_Order $subject  Order or refund.
		 * @param int                $date     Document date (Unix timestamp).
		 */
		$sequence = sanitize_key( (string) apply_filters( 'wootoolkit_pdf_invoices_sequence', $type, $type, $subject, $date ) );

		return self::locked(
			$sequence,
			static function () use ( $subject, $type, $sequence, $date ) {
				// Another request may have numbered this document while we waited for the lock.
				$fresh = wc_get_order( $subject->get_id() );
				if ( $fresh instanceof \WC_Abstract_Order && $fresh !== $subject ) {
					$fresh->read_meta_data( true ); // From the database, not the cache.
					$existing = self::get( $fresh, $type );
					if ( null !== $existing ) {
						$subject->update_meta_data( self::number_key( $type ), $existing['number'] );
						$subject->update_meta_data( self::date_key( $type ), (string) $existing['date'] );
						return $existing;
					}
				}

				$next = self::increment( $sequence, $type );

				/**
				 * The document number as printed, from the sequential number.
				 *
				 * @param string             $formatted The number; the prefix setting plus the number for invoices.
				 * @param int                $next      Sequential number.
				 * @param string             $type      Document type.
				 * @param \WC_Abstract_Order $subject   Order or refund.
				 * @param int                $date      Document date (Unix timestamp).
				 */
				$formatted = (string) apply_filters( 'wootoolkit_pdf_invoices_number', ( 'invoice' === $type ? Settings::prefix() : '' ) . $next, $next, $type, $subject, $date );

				$subject->update_meta_data( self::number_key( $type ), $formatted );
				$subject->update_meta_data( self::date_key( $type ), (string) $date );
				$subject->save_meta_data();

				return array(
					'number' => $formatted,
					'date'   => $date,
				);
			}
		);
	}

	/**
	 * The next number of a sequence (call inside the sequence's lock).
	 *
	 * @param string $sequence Sequence key.
	 * @param string $type     Document type, for the start-number filter.
	 */
	private static function increment( string $sequence, string $type ): int {
		global $wpdb;
		$option = self::COUNTER . $sequence;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the counter must be read from the database inside the lock, never from a cache.
		$last = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );

		/**
		 * The lowest number a sequence may use. Numbers never go back below the last one used.
		 *
		 * @param int    $start    Lowest number; the "Start invoice numbers at" setting for invoices, else 1.
		 * @param string $sequence Sequence key.
		 * @param string $type     Document type.
		 */
		$start = max( 1, (int) apply_filters( 'wootoolkit_pdf_invoices_start_number', 'invoice' === $type ? Settings::start() : 1, $sequence, $type ) );
		$next  = max( (int) $last + 1, $start );

		if ( null === $last ) {
			add_option( $option, (string) $next, '', false );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- written straight through, like the read; the cache is cleared below.
			$wpdb->update( $wpdb->options, array( 'option_value' => (string) $next ), array( 'option_name' => $option ) );
		}
		wp_cache_delete( $option, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return $next;
	}

	/**
	 * The last number a sequence used (0 when none), read from the database.
	 *
	 * @param string $sequence Sequence key.
	 */
	public static function last( string $sequence ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the live counter.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::COUNTER . sanitize_key( $sequence ) ) );
	}

	/**
	 * The MySQL lock name of a sequence (per site, at most 64 characters).
	 *
	 * @param string $sequence Sequence key.
	 */
	public static function lock_name( string $sequence ): string {
		global $wpdb;
		return 'wtk_pdf_' . md5( $wpdb->prefix . '|' . sanitize_key( $sequence ) );
	}

	/**
	 * Runs a callback while holding the sequence's MySQL named lock.
	 *
	 * @param string   $sequence Sequence key.
	 * @param callable $callback Callback.
	 * @return mixed The callback's return value.
	 * @throws \RuntimeException When the lock cannot be taken in time.
	 */
	private static function locked( string $sequence, callable $callback ) {
		global $wpdb;
		$name = self::lock_name( $sequence );

		/**
		 * Seconds to wait for another request to finish numbering before giving up.
		 *
		 * @param int $timeout Seconds. Default 10.
		 */
		$timeout = max( 0, (int) apply_filters( 'wootoolkit_pdf_invoices_lock_timeout', self::LOCK_TIMEOUT ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a MySQL named lock, not data.
		$got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', $name, $timeout ) );
		if ( '1' !== (string) $got ) {
			throw new \RuntimeException( 'Could not lock the document number sequence.' );
		}
		try {
			return $callback();
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a MySQL named lock, not data.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $name ) );
		}
	}
}
