<?php
/**
 * Kit registry.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Holds every registered kit and knows which ones are active.
 */
final class Registry {

	/**
	 * Option holding array( kit id => 'yes'|'no' ). The 'yes'/'no' values match WooCommerce checkbox settings.
	 */
	const OPTION = 'wootoolkit_active_kits';

	/**
	 * Registered kits by id.
	 *
	 * @var array<string, Kit>
	 */
	private $kits = array();

	/**
	 * Ids of kits already booted in this request.
	 *
	 * @var array<string, bool>
	 */
	private $booted = array();

	/**
	 * Registers a kit. A later registration with the same id replaces the earlier one,
	 * which lets Pro override a free kit.
	 *
	 * @param Kit $kit Kit to register.
	 */
	public function register( Kit $kit ): void {
		$this->kits[ $kit->id() ] = $kit;
	}

	/**
	 * A registered kit, or null.
	 *
	 * @param string $id Kit id.
	 */
	public function get( string $id ): ?Kit {
		return isset( $this->kits[ $id ] ) ? $this->kits[ $id ] : null;
	}

	/**
	 * All registered kits: new kits first, then Classic kits, each group sorted by name.
	 *
	 * @return array<string, Kit>
	 */
	public function all(): array {
		$kits = $this->kits;
		uasort(
			$kits,
			static function ( Kit $a, Kit $b ) {
				if ( $a->is_classic() !== $b->is_classic() ) {
					return $a->is_classic() ? 1 : -1;
				}
				return strnatcasecmp( $a->name(), $b->name() );
			}
		);
		return $kits;
	}

	/**
	 * Ids of active, registered kits.
	 *
	 * @return string[]
	 */
	public function active_ids(): array {
		$saved = get_option( self::OPTION, array() );
		$ids   = array();
		foreach ( is_array( $saved ) ? $saved : array() as $id => $state ) {
			if ( 'yes' === $state && isset( $this->kits[ $id ] ) ) {
				$ids[] = (string) $id;
			}
		}
		return $ids;
	}

	/**
	 * Whether a kit is active.
	 *
	 * @param string $id Kit id.
	 */
	public function is_active( string $id ): bool {
		return in_array( $id, $this->active_ids(), true );
	}

	/**
	 * Active kits.
	 *
	 * @return array<string, Kit>
	 */
	public function active(): array {
		return array_intersect_key( $this->all(), array_flip( $this->active_ids() ) );
	}

	/**
	 * Replaces the active set. Registered kits not listed are switched off; unknown ids are ignored.
	 *
	 * @param string[] $ids Kit ids to activate.
	 */
	public function set_active( array $ids ): void {
		$state = array();
		foreach ( array_keys( $this->kits ) as $id ) {
			$state[ $id ] = in_array( $id, $ids, true ) ? 'yes' : 'no';
		}
		update_option( self::OPTION, $state );
	}

	/**
	 * Kits a kit needs that are not switched on (empty when it has none, or all are on).
	 *
	 * @param Kit $kit Kit.
	 * @return string[] Kit ids.
	 */
	public function missing_requirements( Kit $kit ): array {
		if ( ! $kit instanceof HasRequirements ) {
			return array();
		}
		$missing = array();
		foreach ( $kit->requires() as $id ) {
			if ( ! $this->is_active( (string) $id ) ) {
				$missing[] = (string) $id;
			}
		}
		return $missing;
	}

	/**
	 * Boots each active kit once (skipping kits whose required kits are off).
	 */
	public function boot_active(): void {
		foreach ( $this->active() as $id => $kit ) {
			if ( $this->missing_requirements( $kit ) ) {
				continue;
			}
			if ( empty( $this->booted[ $id ] ) ) {
				$this->booted[ $id ] = true;
				$kit->boot();
			}
		}
	}

	/**
	 * Whether every active kit supports a WooCommerce feature ('hpos' or 'blocks').
	 *
	 * @param string $feature Feature key.
	 */
	public function supports( string $feature ): bool {
		foreach ( $this->active() as $kit ) {
			$compat = $kit->compat();
			if ( empty( $compat[ $feature ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether any Classic kit is active (they share the WooToolkit Classic settings screen).
	 */
	public function has_active_classic(): bool {
		foreach ( $this->active() as $kit ) {
			if ( $kit->is_classic() ) {
				return true;
			}
		}
		return false;
	}
}
