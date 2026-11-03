<?php
/**
 * The Pro kit catalogue shown on the Kits page.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Every Pro kit, from `wootoolkit_admin_pro_kits`. The free default is the bundled data/pro-kits.json
 * (generated from the kit builds), all "locked". Nothing is fetched from the internet.
 */
final class ProKits {

	/**
	 * Card states.
	 */
	const STATES = array( 'active', 'inactive', 'available', 'locked' );

	/**
	 * Collections.
	 */
	const COLLECTIONS = array( 'b2b', 'loyalty', 'catalog' );

	/**
	 * The bundled list, read once per request.
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private static $bundled;

	/**
	 * Documentation URL of a kit.
	 *
	 * @param string $kit_id Kit id.
	 */
	public static function guide_url( string $kit_id ): string {
		return 'https://wootoolkit.com/docs/' . rawurlencode( $kit_id ) . '/';
	}

	/**
	 * Product page of a Pro kit on wootoolkit.com.
	 *
	 * @param string $kit_id Kit id.
	 */
	public static function buy_url( string $kit_id ): string {
		return 'https://wootoolkit.com/kits/#' . rawurlencode( $kit_id );
	}

	/**
	 * The bundled list (data/pro-kits.json).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function bundled(): array {
		if ( null === self::$bundled ) {
			$file          = WOOTOOLKIT_PATH . 'data/pro-kits.json';
			$json          = is_readable( $file ) ? file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file in the plugin.
			$data          = $json ? json_decode( $json, true ) : array();
			self::$bundled = array();
			foreach ( is_array( $data ) ? $data : array() as $kit ) {
				if ( is_array( $kit ) ) {
					$kit['state']    = 'locked';
					self::$bundled[] = $kit;
				}
			}
		}
		return self::$bundled;
	}

	/**
	 * Every Pro kit, normalised, keyed by kit id.
	 *
	 * @return array<string, array{kit_id: string, name: string, description: string, collection: string, price: string, state: string, store_id: int, needs: string[], guide: string}>
	 */
	public static function all(): array {
		/**
		 * Pro kits for the Kits page. WooToolkit Pro replaces the bundled list with the kits of the
		 * licence and their install state.
		 *
		 * @param array<int, array<string, mixed>> $kits Each: kit_id, name, description, collection (b2b|loyalty|catalog),
		 *                                               price, state (active|inactive|available|locked), store_id, needs, guide.
		 */
		$kits = apply_filters( 'wootoolkit_admin_pro_kits', self::bundled() );
		$out  = array();
		foreach ( is_array( $kits ) ? $kits : array() as $kit ) {
			if ( ! is_array( $kit ) || empty( $kit['kit_id'] ) || ! is_string( $kit['kit_id'] ) ) {
				continue;
			}
			$id    = sanitize_key( $kit['kit_id'] );
			$needs = isset( $kit['needs'] ) && is_array( $kit['needs'] ) ? array_values( array_filter( array_map( 'sanitize_key', array_map( 'strval', $kit['needs'] ) ) ) ) : array();
			$guide = isset( $kit['guide'] ) && is_string( $kit['guide'] ) ? esc_url_raw( $kit['guide'] ) : '';

			$out[ $id ] = array(
				'kit_id'      => $id,
				'name'        => isset( $kit['name'] ) && is_string( $kit['name'] ) ? $kit['name'] : $id,
				'description' => isset( $kit['description'] ) && is_string( $kit['description'] ) ? $kit['description'] : '',
				'collection'  => isset( $kit['collection'] ) && in_array( $kit['collection'], self::COLLECTIONS, true ) ? (string) $kit['collection'] : 'catalog',
				'price'       => isset( $kit['price'] ) && is_scalar( $kit['price'] ) ? (string) $kit['price'] : '',
				'state'       => isset( $kit['state'] ) && in_array( $kit['state'], self::STATES, true ) ? (string) $kit['state'] : 'locked',
				'store_id'    => isset( $kit['store_id'] ) ? absint( $kit['store_id'] ) : 0,
				'needs'       => $needs,
				'guide'       => '' !== $guide ? $guide : self::guide_url( $id ),
			);
		}
		return $out;
	}
}
