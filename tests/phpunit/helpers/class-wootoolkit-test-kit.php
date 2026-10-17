<?php
/**
 * Test helper.
 *
 * @package WooToolkit
 */

/**
 * A configurable kit for registry and settings tests.
 */
class WooToolkit_Test_Kit implements WooToolkit\Kit\Kit {

	/**
	 * How many times boot() ran.
	 *
	 * @var int
	 */
	public $boots = 0;

	/**
	 * Kit id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Compatibility flags.
	 *
	 * @var array<string, bool>
	 */
	private $compat;

	/**
	 * Settings fields.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param string                           $id       Kit id.
	 * @param array<string, bool>              $compat   Compatibility flags.
	 * @param array<int, array<string, mixed>> $settings Settings fields.
	 */
	public function __construct( string $id, array $compat = array(), array $settings = array() ) {
		$this->id       = $id;
		$this->compat   = array_merge(
			array(
				'hpos'   => true,
				'blocks' => true,
			),
			$compat
		);
		$this->settings = $settings;
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return 'Test kit ' . $this->id;
	}

	public function description(): string {
		return 'A kit used in tests.';
	}

	public function is_classic(): bool {
		return false;
	}

	public function compat(): array {
		return $this->compat;
	}

	public function settings(): array {
		return $this->settings;
	}

	public function boot(): void {
		++$this->boots;
	}
}
