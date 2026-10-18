<?php
/**
 * Wishlist housekeeping: guest clean-up, deleted users, personal data tools.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * Removes guest lists nobody has touched for the configured number of days (daily, with Action
 * Scheduler), removes a customer's lists with their account, and connects to Tools > Export /
 * Erase Personal Data.
 */
final class Maintenance {

	const HOOK  = 'wootoolkit_wishlist_cleanup';
	const GROUP = 'wootoolkit-wishlist';

	/**
	 * Guest lists deleted per run (the next run continues).
	 */
	const BATCH = 200;

	/**
	 * Adds the hooks.
	 */
	public static function hooks(): void {
		add_action( self::HOOK, array( self::class, 'run_cleanup' ) );
		add_action( 'wp_loaded', array( self::class, 'schedule' ) );
		add_action( 'deleted_user', array( self::class, 'delete_user_lists' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
	}

	/**
	 * Schedules the daily clean-up (checked in wp-admin and cron requests only).
	 */
	public static function schedule(): void {
		if ( ! ( is_admin() || wp_doing_cron() ) || ! function_exists( 'WC' ) ) {
			return;
		}
		$queue = WC()->queue();
		if ( ! $queue->get_next( self::HOOK, array(), self::GROUP ) ) {
			$queue->schedule_recurring( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::HOOK, array(), self::GROUP );
		}
	}

	/**
	 * The scheduled action.
	 */
	public static function run_cleanup(): void {
		self::cleanup();
	}

	/**
	 * Deletes guest lists not changed for the configured days. Returns how many were deleted.
	 */
	public static function cleanup(): int {
		$ids = get_posts(
			array(
				'post_type'      => Store::POST_TYPE,
				'post_status'    => Store::STATUS,
				'author__in'     => array( 0 ),
				'fields'         => 'ids',
				'posts_per_page' => self::BATCH,
				'orderby'        => 'modified',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'column' => 'post_modified_gmt',
						'before' => gmdate( 'Y-m-d H:i:s', time() - Settings::guest_days() * DAY_IN_SECONDS ),
					),
				),
			)
		);
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( Store::delete_list( (int) $id ) ) {
				++$deleted;
			}
		}
		return $deleted;
	}

	/**
	 * Deletes a removed customer's lists.
	 *
	 * @param int $user_id User id.
	 */
	public static function delete_user_lists( $user_id ): void {
		foreach ( Store::lists( Owner::user( (int) $user_id ) ) as $list_id ) {
			Store::delete_list( $list_id );
		}
	}

	/**
	 * Registers the personal data exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_exporter( $exporters ): array {
		$exporters                        = (array) $exporters;
		$exporters['wootoolkit-wishlist'] = array(
			'exporter_friendly_name' => __( 'Wishlists', 'wootoolkit' ),
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Registers the personal data eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_eraser( $erasers ): array {
		$erasers                        = (array) $erasers;
		$erasers['wootoolkit-wishlist'] = array(
			'eraser_friendly_name' => __( 'Wishlists', 'wootoolkit' ),
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Exports the wishlists of the account with this email.
	 *
	 * @param string $email Email.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public static function export( $email ): array {
		$data = array();
		$user = get_user_by( 'email', (string) $email );
		if ( $user ) {
			foreach ( Store::lists( Owner::user( (int) $user->ID ) ) as $list_id ) {
				$names = array();
				foreach ( Store::items( $list_id ) as $item ) {
					$product = wc_get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );
					$names[] = $product ? $product->get_name() : '#' . $item['product_id'];
				}
				$data[] = array(
					'group_id'    => 'wootoolkit-wishlist',
					'group_label' => __( 'Wishlists', 'wootoolkit' ),
					'item_id'     => 'wootoolkit-wishlist-' . $list_id,
					'data'        => array(
						array(
							'name'  => __( 'Name', 'wootoolkit' ),
							'value' => get_the_title( $list_id ),
						),
						array(
							'name'  => __( 'Products', 'wootoolkit' ),
							'value' => implode( ', ', $names ),
						),
					),
				);
			}
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erases the wishlists of the account with this email.
	 *
	 * @param string $email Email.
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public static function erase( $email ): array {
		$removed = false;
		$user    = get_user_by( 'email', (string) $email );
		if ( $user ) {
			foreach ( Store::lists( Owner::user( (int) $user->ID ) ) as $list_id ) {
				$removed = Store::delete_list( $list_id ) || $removed;
			}
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
