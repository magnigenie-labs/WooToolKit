<?php
/**
 * Runs when WooToolkit is deleted from the Plugins screen.
 *
 * Deletes WooToolkit's own data only on sites where WooToolkit > Settings > Privacy and data >
 * "Delete all WooToolkit data" is on: its options (wootoolkit_*, the 1.x _woo_active_kits), its
 * transients, its wishlists and the PDF invoices temporary folder. Orders (quotes included),
 * pages and other plugins' data are never touched. On multisite each site decides for itself.
 *
 * @package WooToolkit
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$wootoolkit_uninstall_site = static function () {
	global $wpdb;

	if ( 'yes' !== get_option( 'wootoolkit_delete_data', 'no' ) ) {
		return;
	}

	// Wishlists (private posts of type wtk_wishlist, with their meta).
	$wootoolkit_rounds = 0;
	do {
		$wootoolkit_ids = get_posts(
			array(
				'post_type'        => 'wtk_wishlist',
				'post_status'      => array( 'private', 'publish', 'draft', 'pending', 'future', 'trash' ),
				'numberposts'      => 100,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $wootoolkit_ids as $wootoolkit_id ) {
			wp_delete_post( (int) $wootoolkit_id, true );
		}
	} while ( $wootoolkit_ids && ++$wootoolkit_rounds < 1000 );

	// The PDF invoices kit's temporary folder in uploads.
	$wootoolkit_uploads = wp_upload_dir( null, false );
	if ( empty( $wootoolkit_uploads['error'] ) && ! empty( $wootoolkit_uploads['basedir'] ) ) {
		$wootoolkit_dir = untrailingslashit( (string) $wootoolkit_uploads['basedir'] ) . '/wootoolkit-pdf-invoices';
		if ( is_dir( $wootoolkit_dir ) ) {
			foreach ( (array) scandir( $wootoolkit_dir ) as $wootoolkit_file ) {
				if ( is_string( $wootoolkit_file ) && is_file( $wootoolkit_dir . '/' . $wootoolkit_file ) ) {
					wp_delete_file( $wootoolkit_dir . '/' . $wootoolkit_file );
				}
			}
			@rmdir( $wootoolkit_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- an empty folder WooToolkit created.
		}
	}

	// Scheduled clean-up of guest wishlists.
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'wootoolkit_wishlist_cleanup' );
	}

	// Options and transients. Names are listed first, then deleted through the options API.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall: list WooToolkit's own option names.
	$wootoolkit_names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'wootoolkit_' ) . '%',
			$wpdb->esc_like( '_transient_wootoolkit_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_wootoolkit_' ) . '%'
		)
	);
	foreach ( (array) $wootoolkit_names as $wootoolkit_name ) {
		delete_option( (string) $wootoolkit_name );
	}
	delete_option( '_woo_active_kits' );
	delete_option( 'wootoolkit_catalog' );
};

if ( is_multisite() ) {
	$wootoolkit_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $wootoolkit_sites as $wootoolkit_site ) {
		switch_to_blog( (int) $wootoolkit_site );
		$wootoolkit_uninstall_site();
		restore_current_blog();
	}
} else {
	$wootoolkit_uninstall_site();
}
