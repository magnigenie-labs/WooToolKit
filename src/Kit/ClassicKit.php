<?php
/**
 * Adapter for the 1.x kits.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps a 1.x kit file. The kit is loaded exactly as 1.x loaded it: by including its main file on init.
 */
final class ClassicKit implements Kit {

	/**
	 * Absolute path of the kit's main file.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Whether the kit works with High-Performance Order Storage.
	 *
	 * @var bool
	 */
	private $hpos;

	/**
	 * File headers, read lazily (only admin screens need them).
	 *
	 * @var array<string, string>|null
	 */
	private $headers;

	/**
	 * Constructor.
	 *
	 * @param string $file Absolute path of the kit's main file, e.g. .../kits/wootoolkit-catalog/wootoolkit-catalog.php.
	 * @param bool   $hpos Whether the kit works with HPOS.
	 */
	public function __construct( string $file, bool $hpos = true ) {
		$this->file = $file;
		$this->hpos = $hpos;
	}

	/**
	 * The kit folder name without the "wootoolkit-" prefix, e.g. "catalog".
	 */
	public function id(): string {
		return (string) preg_replace( '/^wootoolkit-/', '', basename( dirname( $this->file ) ) );
	}

	/**
	 * Kit name from the "Kit Name" header.
	 */
	public function name(): string {
		$name = $this->header( 'name' );
		return '' !== $name ? $name : $this->id();
	}

	/**
	 * Kit description from the "Description" header.
	 */
	public function description(): string {
		return $this->header( 'description' );
	}

	/**
	 * The 1.x "Kit Slug" header, used by the kit_action_links_{slug} filter.
	 */
	public function legacy_slug(): string {
		return $this->header( 'slug' );
	}

	/**
	 * Path of the kit's main file.
	 */
	public function file(): string {
		return $this->file;
	}

	/**
	 * Classic kits are always classic.
	 */
	public function is_classic(): bool {
		return true;
	}

	/**
	 * 1.x kits hook the classic cart and checkout templates, so none of them work in the blocks.
	 *
	 * @return array<string, bool>
	 */
	public function compat(): array {
		return array(
			'hpos'   => $this->hpos,
			'blocks' => false,
		);
	}

	/**
	 * Classic kits keep their own settings screen (WooCommerce > WooToolkit Classic).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings(): array {
		return array();
	}

	/**
	 * Loads the kit the way 1.x did.
	 */
	public function boot(): void {
		if ( is_readable( $this->file ) ) {
			include_once $this->file;
		}
	}

	/**
	 * Reads one of the kit's file headers.
	 *
	 * @param string $key name, description or slug.
	 */
	private function header( string $key ): string {
		if ( null === $this->headers ) {
			$this->headers = is_readable( $this->file ) ? get_file_data(
				$this->file,
				array(
					'name'        => 'Kit Name',
					'description' => 'Description',
					'slug'        => 'Kit Slug',
				),
				'plugin'
			) : array();
		}
		return isset( $this->headers[ $key ] ) ? trim( (string) $this->headers[ $key ] ) : '';
	}
}
