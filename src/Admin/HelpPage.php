<?php
/**
 * WooToolkit > Help.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Admin;

use Automattic\WooCommerce\Utilities\OrderUtil;
use WooToolkit\Kit\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Getting started, guides, common questions, what's new, the support box (`wootoolkit_help_support`)
 * and system details (`wootoolkit_system_rows`) with a Copy button.
 */
final class HelpPage {

	/**
	 * Kit registry.
	 *
	 * @var Registry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param Registry $registry Kit registry.
	 */
	public function __construct( Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Registers the free support box. WooToolkit Pro replaces it with:
	 * remove_action( 'wootoolkit_help_support', array( \WooToolkit\Admin\HelpPage::class, 'default_support' ) ).
	 */
	public static function hooks(): void {
		if ( false === has_action( 'wootoolkit_help_support', array( self::class, 'default_support' ) ) ) {
			add_action( 'wootoolkit_help_support', array( self::class, 'default_support' ) );
		}
	}

	/**
	 * The free plugin's support box: the WordPress.org forum and custom kit requests.
	 *
	 * @param string $edition Edition.
	 */
	public static function default_support( $edition = 'free' ): void {
		unset( $edition );
		?>
		<p><?php esc_html_e( 'Questions about the free kits are answered on the WordPress.org support forum.', 'wootoolkit' ); ?></p>
		<?php echo Screen::external_link( 'https://wordpress.org/support/plugin/wootoolkit/', __( 'Open the support forum', 'wootoolkit' ), 'wtk-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link(). ?>
		<p><?php esc_html_e( 'Need a feature built for your store? Custom kits start at $500; we reply with questions or a quote.', 'wootoolkit' ); ?></p>
		<?php
		echo Screen::external_link( 'https://wootoolkit.com/custom-kits/', __( 'Request a custom kit', 'wootoolkit' ), 'wtk-button wtk-button--ghost' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link().
	}

	/**
	 * System details: label => value.
	 *
	 * @return array<string, string>
	 */
	public function system_rows(): array {
		$active   = $this->registry->active();
		$checkout = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'checkout' ) : 0;
		$block    = $checkout > 0 && has_block( 'woocommerce/checkout', (int) $checkout );
		$hpos     = class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled();
		$rows     = array(
			__( 'WooToolkit', 'wootoolkit' )    => WOOTOOLKIT_VERSION,
			__( 'WooCommerce', 'wootoolkit' )   => defined( 'WC_VERSION' ) ? (string) WC_VERSION : '-',
			__( 'WordPress', 'wootoolkit' )     => (string) get_bloginfo( 'version' ),
			__( 'PHP', 'wootoolkit' )           => PHP_VERSION,
			/* translators: 1: kits on, 2: registered kits */
			__( 'Kits on', 'wootoolkit' )       => sprintf( __( '%1$s of %2$s', 'wootoolkit' ), number_format_i18n( count( $active ) ), number_format_i18n( count( $this->registry->all() ) ) ),
			__( 'Order storage', 'wootoolkit' ) => $hpos ? __( 'HPOS', 'wootoolkit' ) : __( 'Posts', 'wootoolkit' ),
			__( 'Checkout', 'wootoolkit' )      => $block ? __( 'Block', 'wootoolkit' ) : __( 'Classic', 'wootoolkit' ),
		);

		$rows[ __( 'Kits that are on', 'wootoolkit' ) ] = $active ? implode( ', ', array_keys( $active ) ) : '-';
		/**
		 * Rows of Help > System details. WooToolkit Pro adds its version and licence state.
		 *
		 * @param array<string, string> $rows Label => value.
		 */
		$rows = apply_filters( 'wootoolkit_system_rows', $rows );
		$out  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $label => $value ) {
			if ( is_scalar( $value ) ) {
				$out[ (string) $label ] = (string) $value;
			}
		}
		return $out;
	}

	/**
	 * Common questions: pairs of question and answer.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	private static function faqs(): array {
		return array(
			array(
				__( 'A kit is on but nothing changed on my store.', 'wootoolkit' ),
				__( 'Open the kit\'s settings: most kits do nothing until you choose who or what they apply to. If the kit says it needs another kit, switch that one on too. Clear any page cache afterwards.', 'wootoolkit' ),
			),
			array(
				__( 'Do kits work with the block checkout?', 'wootoolkit' ),
				__( 'Yes. Every 2.0 kit works with the Cart and Checkout blocks and the classic shortcodes, and with HPOS order storage. Classic kits from 1.x work with the classic checkout only.', 'wootoolkit' ),
			),
			array(
				__( 'What are Classic kits?', 'wootoolkit' ),
				__( 'The kits from WooToolkit 1.x. Sites that used them keep them, with bug fixes only. Their settings are on their own screen, linked from each Classic kit\'s card and from Settings › Classic kits.', 'wootoolkit' ),
			),
			array(
				__( 'Can I move my settings to another site?', 'wootoolkit' ),
				__( 'Yes: Settings › Import and export downloads a settings file you can import on the other site. You see what changes before anything is saved.', 'wootoolkit' ),
			),
		);
	}

	/**
	 * Prints the page.
	 */
	public function render(): void {
		if ( ! current_user_can( Admin::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'wootoolkit' ), 403 );
		}
		$edition = Screen::edition();
		$guides  = array();
		foreach ( $this->registry->all() as $id => $kit ) {
			if ( ! $kit->is_classic() ) {
				$guides[ (string) $id ] = array(
					'name' => $kit->name(),
					'on'   => $this->registry->is_active( (string) $id ),
				);
			}
		}
		uasort(
			$guides,
			static function ( $a, $b ) {
				return $a['on'] === $b['on'] ? strnatcasecmp( $a['name'], $b['name'] ) : ( $a['on'] ? -1 : 1 );
			}
		);
		$checkout = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/' );
		$rows     = $this->system_rows();

		Screen::open( 'wootoolkit-help' );
		?>
		<div class="wtk-intro">
			<h1 class="wtk-h1"><?php esc_html_e( 'How can we help?', 'wootoolkit' ); ?></h1>
			<div class="wtk-search" role="search">
				<label for="wtk-guide-search" class="screen-reader-text"><?php esc_html_e( 'Search the guides', 'wootoolkit' ); ?></label>
				<input id="wtk-guide-search" class="wtk-input" type="search" data-wtk-guide-search aria-controls="wtk-guide-list" placeholder="<?php esc_attr_e( 'Search the guides, e.g. wishlist', 'wootoolkit' ); ?>">
				<?php echo Screen::external_link( 'https://wootoolkit.com/docs/', __( 'All guides on wootoolkit.com', 'wootoolkit' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link(). ?>
			</div>
		</div>

		<div class="wtk-columns">
			<div class="wtk-columns__main">
				<section class="wtk-card wtk-section" aria-labelledby="wtk-start">
					<h2 class="wtk-h2" id="wtk-start"><?php esc_html_e( 'Getting started', 'wootoolkit' ); ?></h2>
					<div class="wtk-grid wtk-grid--three">
						<a class="wtk-step" href="<?php echo esc_url( Admin::settings_url() ); ?>"><span class="wtk-step__num">01</span><strong><?php esc_html_e( 'Switch on a kit', 'wootoolkit' ); ?></strong><span class="wtk-muted"><?php esc_html_e( 'On the Kits page', 'wootoolkit' ); ?></span></a>
						<a class="wtk-step" href="<?php echo esc_url( Admin::settings_url() ); ?>"><span class="wtk-step__num">02</span><strong><?php esc_html_e( 'Set it up', 'wootoolkit' ); ?></strong><span class="wtk-muted"><?php esc_html_e( 'Its Settings link', 'wootoolkit' ); ?></span></a>
						<a class="wtk-step" href="<?php echo esc_url( $checkout ); ?>" target="_blank" rel="noopener"><span class="wtk-step__num">03</span><strong><?php esc_html_e( 'Test your checkout', 'wootoolkit' ); ?></strong><span class="wtk-muted"><?php esc_html_e( 'In a private window', 'wootoolkit' ); ?><span aria-hidden="true"> &#8599;</span></span><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'wootoolkit' ); ?></span></a>
					</div>
				</section>

				<section class="wtk-card wtk-section" id="wtk-guides" aria-labelledby="wtk-guides-title">
					<div class="wtk-section__head">
						<h2 class="wtk-h2" id="wtk-guides-title"><?php esc_html_e( 'Guides for your kits', 'wootoolkit' ); ?></h2>
						<span class="wtk-muted wtk-small"><?php esc_html_e( 'Kits that are on come first', 'wootoolkit' ); ?></span>
					</div>
					<ul class="wtk-guides" id="wtk-guide-list">
						<?php foreach ( $guides as $id => $guide ) : ?>
							<li data-wtk-guide="<?php echo esc_attr( strtolower( $guide['name'] . ' ' . $id ) ); ?>"><?php echo Screen::external_link( ProKits::guide_url( $id ), $guide['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link(). ?></li>
						<?php endforeach; ?>
					</ul>
					<p class="wtk-muted" data-wtk-guide-empty hidden aria-live="polite"><?php esc_html_e( 'No guide matches that search.', 'wootoolkit' ); ?></p>
				</section>

				<section class="wtk-card wtk-section" id="wtk-faq" aria-labelledby="wtk-faq-title">
					<h2 class="wtk-h2" id="wtk-faq-title"><?php esc_html_e( 'Common questions', 'wootoolkit' ); ?></h2>
					<?php $first = true; ?>
					<?php foreach ( self::faqs() as $faq ) : ?>
						<details class="wtk-faq"<?php echo $first ? ' open' : ''; ?>>
							<summary class="wtk-faq__q"><?php echo esc_html( $faq[0] ); ?></summary>
							<p class="wtk-faq__a"><?php echo esc_html( $faq[1] ); ?></p>
						</details>
						<?php $first = false; ?>
					<?php endforeach; ?>
				</section>

				<section class="wtk-card wtk-section" id="wtk-new" aria-labelledby="wtk-new-title">
					<h2 class="wtk-h2" id="wtk-new-title"><?php esc_html_e( 'What\'s new in 2.0', 'wootoolkit' ); ?></h2>
					<ul class="wtk-plain-list wtk-plain-list--bullets">
						<li><?php esc_html_e( 'Seven kits built for the block checkout and HPOS: Wholesale prices, Catalog mode, Request a quote, Checkout fields, Wishlist, Variation swatches and PDF invoices.', 'wootoolkit' ); ?></li>
						<li><?php esc_html_e( 'WooToolkit has its own menu, with a dashboard and a page for every kit.', 'wootoolkit' ); ?></li>
						<li><?php esc_html_e( 'Your 1.x kits keep working as Classic kits.', 'wootoolkit' ); ?></li>
					</ul>
					<?php echo Screen::external_link( 'https://wordpress.org/plugins/wootoolkit/#developers', __( 'Full changelog', 'wootoolkit' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link(). ?>
				</section>

				<?php if ( 'free' === $edition ) : ?>
					<section class="wtk-card wtk-section" id="wtk-help-pro" aria-labelledby="wtk-help-pro-title">
						<h2 class="wtk-h2" id="wtk-help-pro-title"><?php esc_html_e( 'Already bought WooToolkit Pro?', 'wootoolkit' ); ?></h2>
						<p><?php esc_html_e( 'Download WooToolkit Pro from your account on wootoolkit.com and install it under Plugins › Add New › Upload Plugin. Then enter your licence key under WooToolkit › Licence, and install your kits from the Kits page.', 'wootoolkit' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=upload' ) ); ?>"><?php esc_html_e( 'Upload a plugin', 'wootoolkit' ); ?></a>
					</section>
				<?php endif; ?>
			</div>

			<aside class="wtk-columns__side">
				<section class="wtk-card wtk-section" id="wtk-support" aria-labelledby="wtk-support-title">
					<h2 class="wtk-h2" id="wtk-support-title"><?php esc_html_e( 'Contact support', 'wootoolkit' ); ?></h2>
					<?php
					/**
					 * The Help page's support box. The free plugin prints the WordPress.org forum and
					 * custom kit links; WooToolkit Pro replaces them with its support form.
					 *
					 * @param string $edition Edition.
					 */
					do_action( 'wootoolkit_help_support', $edition );
					?>
				</section>

				<section class="wtk-card wtk-section" aria-labelledby="wtk-system-title">
					<h2 class="wtk-h2" id="wtk-system-title"><?php esc_html_e( 'System details', 'wootoolkit' ); ?></h2>
					<dl class="wtk-system">
						<?php foreach ( $rows as $label => $value ) : ?>
							<div class="wtk-system__row"><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div>
						<?php endforeach; ?>
					</dl>
					<?php
					$text = '';
					foreach ( $rows as $label => $value ) {
						$text .= $label . ': ' . $value . "\n";
					}
					?>
					<label for="wtk-system-text" class="screen-reader-text"><?php esc_html_e( 'System details as text', 'wootoolkit' ); ?></label>
					<textarea id="wtk-system-text" class="wtk-system__text" readonly rows="8" data-wtk-copy-source hidden><?php echo esc_textarea( $text ); ?></textarea>
					<button type="button" class="wtk-button wtk-button--ghost" data-wtk-copy="#wtk-system-text"><?php esc_html_e( 'Copy for support', 'wootoolkit' ); ?></button>
					<span class="wtk-muted wtk-small" data-wtk-copy-status aria-live="polite"></span>
				</section>
			</aside>
		</div>
		<?php
		Screen::close();
	}
}
