<?php
/**
 * Plugin bootstrap.
 *
 * @package WooToolkit
 */

namespace WooToolkit;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use WooToolkit\Admin\Admin;
use WooToolkit\Install\Installer;
use WooToolkit\Kit\ClassicKit;
use WooToolkit\Kit\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin together.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance;

	/**
	 * Kit registry.
	 *
	 * @var Registry
	 */
	private $registry;

	/**
	 * The plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->registry = new Registry();
	}

	/**
	 * The kit registry.
	 */
	public function registry(): Registry {
		return $this->registry;
	}

	/**
	 * Registers the plugin's hooks.
	 */
	public function boot(): void {
		add_action( 'plugins_loaded', array( $this, 'register_kits' ), 5 );
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
		add_action( 'init', array( $this, 'boot_kits' ), 1 );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( is_admin() ) {
			( new Admin( $this->registry ) )->hooks();
		}
	}

	/**
	 * Runs pending upgrades, then registers the bundled kits and lets add-ons (Pro) register theirs.
	 */
	public function register_kits(): void {
		Installer::maybe_upgrade();

		$this->registry->register( new Kits\WholesalePrices\WholesalePricesKit() );
		$this->registry->register( new Kits\CatalogMode\CatalogModeKit() );
		$this->registry->register( new Kits\RequestAQuote\RequestAQuoteKit() );

		foreach ( self::classic_kits() as $file => $hpos ) {
			$this->registry->register( new ClassicKit( WOOTOOLKIT_PATH . 'kits/' . $file, $hpos ) );
		}

		/**
		 * Register additional kits.
		 *
		 * @param Registry $registry Kit registry.
		 */
		do_action( 'wootoolkit_register_kits', $this->registry );
	}

	/**
	 * Boots active kits, only when WooCommerce is running.
	 */
	public function boot_kits(): void {
		if ( class_exists( 'WooCommerce' ) ) {
			$this->registry->boot_active();
		}
	}

	/**
	 * Tells WooCommerce whether the active kits support HPOS and the Cart & Checkout blocks.
	 */
	public function declare_compatibility(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}
		FeaturesUtil::declare_compatibility( 'custom_order_tables', WOOTOOLKIT_FILE, $this->registry->supports( 'hpos' ) );
		FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WOOTOOLKIT_FILE, $this->registry->supports( 'blocks' ) );
	}

	/**
	 * Loads translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'wootoolkit', false, dirname( WOOTOOLKIT_BASE ) . '/languages' );
	}

	/**
	 * The 1.x kits: main file (relative to kits/) => whether it supports HPOS.
	 *
	 * @return array<string, bool>
	 */
	public static function classic_kits(): array {
		return array(
			'wootoolkit-accordions/wootoolkit-accordions.php' => true,
			'wootoolkit-continue-shopping/wootoolkit-continue-shopping.php' => true,
			'wootoolkit-custom-product-tabs/wootoolkit-custom-product-tabs.php' => true,
			'wootoolkit-empty-cart/wootoolkit-empty-cart.php' => true,
			'wootoolkit-mailchimp/wootoolkit-mailchimp.php' => true,
			'wootoolkit-product-sorting-options/wootoolkit-product-sorting-options.php' => true,
			'wootoolkit-products-per-page/wootoolkit-products-per-page.php' => true,
			'wootoolkit-sequential-order-numbers/wootoolkit-sequential-order-numbers.php' => false,
			'wootoolkit-woo-customizer/wootoolkit-woo-customizer.php' => true,
		);
	}
}
