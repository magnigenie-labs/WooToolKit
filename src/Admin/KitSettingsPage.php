<?php
/**
 * WooToolkit > Kits > (one kit).
 *
 * @package WooToolkit
 */

namespace WooToolkit\Admin;

use WooToolkit\Kit\ClassicKit;
use WooToolkit\Kit\HasRequirements;
use WooToolkit\Kit\Kit;
use WooToolkit\Kit\ProKit;
use WooToolkit\Kit\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * A kit's own page (`admin.php?page=wootoolkit-kits&kit=<id>`): header card with the switch, tabs
 * for kits that extend it (`wootoolkit_kit_tabs`) and its WooCommerce settings fields, saved
 * together with one nonce.
 */
final class KitSettingsPage {

	/**
	 * admin-post action that saves a kit's settings.
	 */
	const SAVE = 'wootoolkit_save_kit';

	/**
	 * Option set once kit settings have been saved (ends the dashboard's "Get started" box).
	 */
	const SAVED_OPTION = 'wootoolkit_kit_settings_saved';

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
	 * Registers the hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_post_' . self::SAVE, array( $this, 'handle_save' ) );
	}

	/**
	 * Nonce action of a kit's settings form.
	 *
	 * @param string $kit_id Kit id.
	 */
	public static function nonce_action( string $kit_id ): string {
		return 'wootoolkit_kit_settings_' . $kit_id;
	}

	/**
	 * Loads WooCommerce's settings API (fields output and saving).
	 */
	public static function load_wc_settings(): void {
		if ( ! class_exists( 'WC_Admin_Settings', false ) && defined( 'WC_ABSPATH' ) ) {
			include_once WC_ABSPATH . 'includes/admin/class-wc-admin-settings.php';
		}
	}

	/**
	 * Kits drawn as tabs on a kit's page: by default every active Pro kit that needs only this kit
	 * (e.g. Wishlist Pro on Wishlist).
	 *
	 * @param string $kit_id Kit id.
	 * @return string[] Kit ids, main kit excluded.
	 */
	public function tab_kits( string $kit_id ): array {
		$default = array();
		foreach ( $this->registry->active() as $id => $kit ) {
			if ( $id !== $kit_id && $kit instanceof ProKit && $kit instanceof HasRequirements && array_values( array_map( 'strval', $kit->requires() ) ) === array( $kit_id ) ) {
				$default[] = (string) $id;
			}
		}
		/**
		 * Extra tabs on a kit's settings page. Each is a kit id whose settings are drawn and saved
		 * together with the main kit.
		 *
		 * @param string[] $tabs   Kit ids.
		 * @param string   $kit_id Main kit id.
		 */
		$tabs = apply_filters( 'wootoolkit_kit_tabs', $default, $kit_id );
		$out  = array();
		foreach ( is_array( $tabs ) ? $tabs : array() as $id ) {
			$id = sanitize_key( (string) $id );
			if ( $id !== $kit_id && $this->registry->is_active( $id ) && ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * The kit whose page shows this kit as a tab ('' when it has its own page): a Pro kit that needs
	 * exactly one other kit, e.g. Wishlist Pro opens on Wishlist's page.
	 *
	 * @param string $kit_id Kit id.
	 */
	public function parent_of( string $kit_id ): string {
		$kit = $this->registry->get( $kit_id );
		if ( ! $kit instanceof ProKit || ! $kit instanceof HasRequirements ) {
			return '';
		}
		$requires = array_values( array_map( 'strval', $kit->requires() ) );
		if ( 1 !== count( $requires ) || null === $this->registry->get( $requires[0] ) ) {
			return '';
		}
		return in_array( $kit_id, $this->tab_kits( $requires[0] ), true ) ? $requires[0] : '';
	}

	/**
	 * Saves the posted settings of a kit and its tab kits. Checks the capability and the nonce.
	 *
	 * @param string $kit_id Kit id.
	 * @return bool Whether the settings were saved.
	 */
	public function save( string $kit_id ): bool {
		$kit   = $this->registry->get( $kit_id );
		$nonce = isset( $_POST['_wpnonce'] ) && is_string( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( null === $kit || ! current_user_can( Admin::capability() ) || ! wp_verify_nonce( $nonce, self::nonce_action( $kit_id ) ) ) {
			return false;
		}
		if ( ! $this->registry->is_active( $kit_id ) ) {
			return false;
		}
		self::load_wc_settings();
		$ids = array_merge( array( $kit_id ), $this->tab_kits( $kit_id ) );
		foreach ( $ids as $id ) {
			$tab = $this->registry->get( $id );
			if ( $tab && $tab->settings() ) {
				\WC_Admin_Settings::save_fields( $tab->settings() );
			}
		}
		foreach ( $ids as $id ) {
			/**
			 * A kit's settings were saved (kits flush their caches here).
			 */
			do_action( 'woocommerce_update_options_wootoolkit_' . $id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- hook name kept from the WooCommerce settings tab.
		}
		update_option( self::SAVED_OPTION, 'yes', false );
		return true;
	}

	/**
	 * admin-post handler: saves, then goes back to the kit page.
	 */
	public function handle_save(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is checked in save().
		$kit_id = isset( $_POST['kit'] ) && is_string( $_POST['kit'] ) ? sanitize_key( wp_unslash( $_POST['kit'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is checked in save().
		$tab = isset( $_POST['tab'] ) && is_string( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
		if ( ! $this->save( $kit_id ) ) {
			wp_die(
				esc_html__( 'Your settings could not be saved: the link has expired or you are not allowed to change this kit. Go back, reload the page and try again.', 'wootoolkit' ),
				esc_html__( 'Settings not saved', 'wootoolkit' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}
		$args = array( 'saved' => '1' );
		if ( '' !== $tab ) {
			$args['tab'] = $tab;
		}
		wp_safe_redirect( add_query_arg( $args, Admin::settings_url( $kit_id ) ) );
		exit;
	}

	/**
	 * Prints a kit's page.
	 *
	 * @param string $kit_id Kit id.
	 */
	public function render( string $kit_id ): void {
		if ( ! current_user_can( Admin::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'wootoolkit' ), 403 );
		}
		// A kit drawn as a tab opens on its parent's page, on its own tab.
		$tab    = '';
		$parent = $this->parent_of( $kit_id );
		if ( '' !== $parent ) {
			$tab    = $kit_id;
			$kit_id = $parent;
		}
		$kit = $this->registry->get( $kit_id );
		Screen::open( 'wootoolkit-kits' );
		if ( null === $kit ) {
			$this->print_unknown( $kit_id );
			Screen::close();
			return;
		}
		$on = $this->registry->is_active( $kit_id );
		?>
		<nav class="wtk-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'wootoolkit' ); ?>">
			<a href="<?php echo esc_url( Admin::settings_url() ); ?>"><?php esc_html_e( 'Kits', 'wootoolkit' ); ?></a>
			<span aria-hidden="true">›</span>
			<span aria-current="page"><?php echo esc_html( $kit->name() ); ?></span>
		</nav>
		<div class="wtk-notices" data-wtk-notices>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			if ( isset( $_GET['saved'] ) && $on ) {
				Screen::notice( __( 'Settings saved. Your store uses them straight away.', 'wootoolkit' ) );
			}
			?>
		</div>
		<?php
		$this->print_header( $kit, $on );

		if ( ! $on ) {
			$this->print_off( $kit );
		} elseif ( $kit instanceof ClassicKit ) {
			$this->print_classic( $kit );
		} else {
			$missing = $this->registry->missing_requirements( $kit );
			if ( $missing ) {
				$this->print_missing( $missing );
			}
			$this->print_form( $kit, $tab );
		}
		Screen::close();
	}

	/**
	 * "This kit isn't installed".
	 *
	 * @param string $kit_id Requested kit id.
	 */
	private function print_unknown( string $kit_id ): void {
		$pro = ProKits::all();
		?>
		<div class="wtk-card wtk-empty-state" role="status">
			<h1 class="wtk-h2"><?php esc_html_e( 'This kit is not installed', 'wootoolkit' ); ?></h1>
			<p>
				<?php
				if ( isset( $pro[ $kit_id ] ) ) {
					/* translators: %s: kit name */
					echo esc_html( sprintf( __( '%s is a Pro kit. Its settings appear here once it is installed and switched on.', 'wootoolkit' ), $pro[ $kit_id ]['name'] ) );
				} else {
					esc_html_e( 'There is no kit with that name on this site. It may have been removed, or the link is wrong.', 'wootoolkit' );
				}
				?>
			</p>
			<a class="wtk-button" href="<?php echo esc_url( Admin::settings_url() ); ?>"><?php esc_html_e( 'Back to Kits', 'wootoolkit' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Header card: name, badge, description, guide link and the switch.
	 *
	 * @param Kit  $kit Kit.
	 * @param bool $on  Whether it is on.
	 */
	private function print_header( Kit $kit, bool $on ): void {
		$id     = $kit->id();
		$type   = $kit->is_classic() ? 'classic' : ( $kit instanceof ProKit ? 'pro' : 'free' );
		$badges = array(
			'free'    => __( 'Free', 'wootoolkit' ),
			'pro'     => __( 'Pro', 'wootoolkit' ),
			'classic' => __( 'Classic', 'wootoolkit' ),
		);
		?>
		<div class="wtk-card wtk-kit-head" data-wtk-kit="<?php echo esc_attr( $id ); ?>" data-name="<?php echo esc_attr( $kit->name() ); ?>" data-on="<?php echo $on ? '1' : '0'; ?>" data-owned="1">
			<div class="wtk-kit-head__icon"><?php echo Screen::icon( 'kit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></div>
			<div class="wtk-kit-head__text">
				<div class="wtk-kit-head__title">
					<h1 class="wtk-h1 wtk-h1--kit" id="wtk-kit-title"><?php echo esc_html( $kit->name() ); ?></h1>
					<span class="wtk-badge wtk-badge--<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $badges[ $type ] ); ?></span>
					<?php if ( 'free' === $type ) : ?>
						<span class="wtk-mono wtk-muted">
							<?php
							/* translators: %s: WooToolkit version */
							echo esc_html( sprintf( __( 'part of WooToolkit %s', 'wootoolkit' ), WOOTOOLKIT_VERSION ) );
							?>
						</span>
					<?php endif; ?>
				</div>
				<p class="wtk-kit-head__desc"><?php echo esc_html( $kit->description() ); ?></p>
			</div>
			<?php
			if ( ! $kit->is_classic() ) {
				/* translators: %s: kit name */
				echo Screen::external_link( ProKits::guide_url( $id ), sprintf( __( '%s guide', 'wootoolkit' ), $kit->name() ), 'wtk-kit-head__link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link().
			}
			?>
			<div class="wtk-kit-head__switch">
				<span class="wtk-kit-head__state" data-wtk-status aria-hidden="true"><?php echo esc_html( $on ? __( 'On', 'wootoolkit' ) : __( 'Off', 'wootoolkit' ) ); ?></span>
				<button type="button" class="wtk-switch" role="switch" aria-checked="<?php echo $on ? 'true' : 'false'; ?>" aria-labelledby="wtk-kit-title" data-wtk-toggle="<?php echo esc_attr( $id ); ?>" data-wtk-reload="1"><span class="wtk-switch__knob"></span></button>
			</div>
		</div>
		<?php
	}

	/**
	 * The "kit is off" state.
	 *
	 * @param Kit $kit Kit.
	 */
	private function print_off( Kit $kit ): void {
		?>
		<div class="wtk-card wtk-off-state" role="status">
			<strong class="wtk-off-state__title">
				<?php
				/* translators: %s: kit name */
				echo esc_html( sprintf( __( '%s is off.', 'wootoolkit' ), $kit->name() ) );
				?>
			</strong>
			<span><?php esc_html_e( 'Shoppers do not see it and its code does not load. Its settings are kept, so switching it back on brings them back.', 'wootoolkit' ); ?></span>
			<button type="button" class="wtk-button" data-wtk-enable="<?php echo esc_attr( $kit->id() ); ?>" data-wtk-reload="1">
				<?php
				/* translators: %s: kit name */
				echo esc_html( sprintf( __( 'Switch on %s', 'wootoolkit' ), $kit->name() ) );
				?>
			</button>
		</div>
		<?php
	}

	/**
	 * A Classic kit keeps its 1.x settings screen.
	 *
	 * @param ClassicKit $kit Kit.
	 */
	private function print_classic( ClassicKit $kit ): void {
		$url = KitsPage::classic_settings_url( $kit, true );
		?>
		<div class="wtk-card wtk-off-state">
			<strong class="wtk-off-state__title"><?php esc_html_e( 'Classic kits keep their own settings screen.', 'wootoolkit' ); ?></strong>
			<span><?php esc_html_e( 'This kit is from WooToolkit 1.x. It keeps working, with bug fixes only, and only with the classic cart and checkout.', 'wootoolkit' ); ?></span>
			<?php if ( '' !== $url ) : ?>
				<a class="wtk-button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open its settings', 'wootoolkit' ); ?></a>
			<?php else : ?>
				<span class="wtk-muted"><?php esc_html_e( 'It has no settings of its own.', 'wootoolkit' ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * "Not running: it needs X" with a button that switches X on.
	 *
	 * @param string[] $missing Kit ids.
	 */
	private function print_missing( array $missing ): void {
		$names = array();
		foreach ( $missing as $id ) {
			$other   = $this->registry->get( $id );
			$names[] = $other ? $other->name() : $id;
		}
		$first = (string) reset( $missing );
		$can   = null !== $this->registry->get( $first );
		?>
		<div class="wtk-notice wtk-notice--error" role="alert">
			<?php echo Screen::icon( 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<strong class="wtk-notice__text">
				<?php
				/* translators: %s: names of the kits it needs */
				echo esc_html( sprintf( __( 'Not running: it needs %s.', 'wootoolkit' ), implode( ', ', $names ) ) );
				?>
			</strong>
			<?php if ( $can ) : ?>
				<button type="button" class="wtk-button wtk-button--small wtk-button--red" data-wtk-enable="<?php echo esc_attr( $first ); ?>" data-wtk-reload="1"><?php esc_html_e( 'Switch it on', 'wootoolkit' ); ?></button>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * The settings form: tabs, sections and one Save button.
	 *
	 * @param Kit    $kit  Kit.
	 * @param string $open Tab to open ('' for the one in the URL).
	 */
	private function print_form( Kit $kit, string $open = '' ): void {
		self::load_wc_settings();
		$id   = $kit->id();
		$tabs = array( $id => $kit );
		foreach ( $this->tab_kits( $id ) as $tab_id ) {
			$tab = $this->registry->get( $tab_id );
			if ( $tab ) {
				$tabs[ $tab_id ] = $tab;
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only picks the open tab.
		$current = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $id;
		$current = '' !== $open ? $open : $current;
		$current = isset( $tabs[ $current ] ) ? $current : $id;
		$fields  = false;
		foreach ( $tabs as $tab ) {
			$fields = $fields || (bool) $tab->settings();
		}
		if ( ! $fields ) {
			?>
			<div class="wtk-card wtk-off-state" role="status">
				<strong class="wtk-off-state__title"><?php esc_html_e( 'This kit has no settings.', 'wootoolkit' ); ?></strong>
				<span><?php esc_html_e( 'It works as soon as it is on.', 'wootoolkit' ); ?></span>
			</div>
			<?php
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wtk-kit-form" data-wtk-form>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE ); ?>">
			<input type="hidden" name="kit" value="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $current ); ?>" data-wtk-tab-field>
			<?php wp_nonce_field( self::nonce_action( $id ) ); ?>

			<?php if ( count( $tabs ) > 1 ) : ?>
				<div class="wtk-tabs" role="tablist" aria-label="<?php echo esc_attr( $kit->name() ); ?>" data-wtk-tabs>
					<?php foreach ( $tabs as $tab_id => $tab ) : ?>
						<a class="wtk-tabs__tab" role="tab" id="wtk-tab-<?php echo esc_attr( $tab_id ); ?>" href="<?php echo esc_url( add_query_arg( 'tab', $tab_id, Admin::settings_url( $id ) ) ); ?>" aria-controls="wtk-panel-<?php echo esc_attr( $tab_id ); ?>" aria-selected="<?php echo $tab_id === $current ? 'true' : 'false'; ?>" tabindex="<?php echo $tab_id === $current ? '0' : '-1'; ?>" data-wtk-tab="<?php echo esc_attr( $tab_id ); ?>">
							<?php echo esc_html( $tab->name() ); ?>
							<?php if ( $tab instanceof ProKit && $tab_id !== $id ) : ?>
								<span class="wtk-badge wtk-badge--pro wtk-badge--tiny"><?php esc_html_e( 'Pro', 'wootoolkit' ); ?></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php foreach ( $tabs as $tab_id => $tab ) : ?>
				<div class="wtk-panel" id="wtk-panel-<?php echo esc_attr( $tab_id ); ?>"<?php echo count( $tabs ) > 1 ? ' role="tabpanel" aria-labelledby="wtk-tab-' . esc_attr( $tab_id ) . '"' : ''; ?><?php echo $tab_id === $current ? '' : ' hidden'; ?>>
					<?php if ( $tab_id !== $id ) : ?>
						<p class="wtk-muted wtk-tip">
							<?php
							/* translators: 1: tab kit name, 2: main kit name */
							echo esc_html( sprintf( __( 'From the %1$s kit. Shown here because it extends %2$s.', 'wootoolkit' ), $tab->name(), $kit->name() ) );
							?>
						</p>
					<?php endif; ?>
					<?php $this->print_sections( $tab->settings() ); ?>
				</div>
			<?php endforeach; ?>

			<div class="wtk-card wtk-savebar">
				<button type="submit" class="wtk-button wtk-button--large" name="save" value="1"><?php esc_html_e( 'Save changes', 'wootoolkit' ); ?></button>
				<span class="wtk-muted" data-wtk-dirty-note data-clean="<?php echo esc_attr( count( $tabs ) > 1 ? __( 'All tabs save together.', 'wootoolkit' ) : '' ); ?>" data-dirty="<?php esc_attr_e( 'You have unsaved changes.', 'wootoolkit' ); ?>" aria-live="polite"><?php echo esc_html( count( $tabs ) > 1 ? __( 'All tabs save together.', 'wootoolkit' ) : '' ); ?></span>
			</div>
		</form>
		<?php
	}

	/**
	 * Draws settings fields, one card per WooCommerce "title" section.
	 *
	 * @param array<int, array<string, mixed>> $fields WooCommerce settings fields.
	 */
	private function print_sections( array $fields ): void {
		$chunks = array();
		$chunk  = array();
		foreach ( $fields as $field ) {
			$type = isset( $field['type'] ) ? (string) $field['type'] : '';
			if ( 'title' === $type && $chunk ) {
				$chunks[] = $chunk;
				$chunk    = array();
			}
			$chunk[] = $field;
			if ( 'sectionend' === $type ) {
				$chunks[] = $chunk;
				$chunk    = array();
			}
		}
		if ( $chunk ) {
			$chunks[] = $chunk;
		}
		foreach ( $chunks as $chunk ) {
			echo '<section class="wtk-card wtk-fields">';
			\WC_Admin_Settings::output_fields( $chunk );
			echo '</section>';
		}
	}
}
