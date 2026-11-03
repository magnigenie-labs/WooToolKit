<?php
/**
 * WooToolkit > Kits.
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
 * Kit cards with instant on/off switches (admin-ajax `wootoolkit_toggle_kit`), search and filters,
 * Classic kits, and the Pro kits from `wootoolkit_admin_pro_kits` with their install or Get Pro buttons.
 */
final class KitsPage {

	/**
	 * AJAX action that switches a kit on or off.
	 */
	const TOGGLE = 'wootoolkit_toggle_kit';

	/**
	 * Collections of the bundled kits.
	 */
	const FREE_COLLECTIONS = array(
		'wholesale-prices'   => 'b2b',
		'catalog-mode'       => 'b2b',
		'request-a-quote'    => 'b2b',
		'pdf-invoices'       => 'b2b',
		'wishlist'           => 'catalog',
		'variation-swatches' => 'catalog',
		'checkout-fields'    => 'catalog',
	);

	/**
	 * Classic kits with a tab on the Classic settings screen (kit id => tab), as their own links use.
	 */
	const CLASSIC_TABS = array(
		'accordions'              => 'wootoolkit_accordion',
		'continue-shopping'       => 'wootoolkit_continue_shopping',
		'empty-cart'              => 'wootoolkit_empty_cart',
		'mailchimp'               => 'wootoolkit_mailchimp',
		'product-sorting-options' => 'wootoolkit_sorting_options',
		'products-per-page'       => 'wootoolkit_products_per_page',
		'woo-customizer'          => 'wootoolkit_woo_customizer',
	);

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
		add_action( 'wp_ajax_' . self::TOGGLE, array( $this, 'ajax_toggle' ) );
	}

	/**
	 * Collection labels.
	 *
	 * @return array<string, string>
	 */
	public static function collections(): array {
		return array(
			'b2b'     => __( 'B2B', 'wootoolkit' ),
			'loyalty' => __( 'Loyalty & gift cards', 'wootoolkit' ),
			'catalog' => __( 'Catalog & conversion', 'wootoolkit' ),
			'classic' => __( 'Classic', 'wootoolkit' ),
		);
	}

	/**
	 * URL of the Classic settings screen, optionally on a tab.
	 *
	 * @param string $tab Tab id.
	 */
	public static function classic_url( string $tab = '' ): string {
		$args = array( 'page' => 'woo-toolkit-settings' );
		if ( '' !== $tab ) {
			$args['tab'] = $tab;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Settings URL of a Classic kit ('' when it has none, or the user cannot open the Classic screen).
	 *
	 * @param ClassicKit $kit    Kit.
	 * @param bool       $active Whether it is switched on.
	 */
	public static function classic_settings_url( ClassicKit $kit, bool $active ): string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		if ( $active && '' !== $kit->legacy_slug() ) {
			// 1.x kits add their "Settings" link through this filter; it only exists while they are loaded.
			$links = (array) apply_filters( 'kit_action_links_' . $kit->legacy_slug(), array(), $kit->legacy_slug() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- 1.x hook name, kept for the Classic kits.
			foreach ( $links as $link ) {
				if ( is_string( $link ) && preg_match( '/href=["\']([^"\']+)["\']/', $link, $m ) ) {
					return html_entity_decode( $m[1] );
				}
			}
		}
		return isset( self::CLASSIC_TABS[ $kit->id() ] ) ? self::classic_url( self::CLASSIC_TABS[ $kit->id() ] ) : '';
	}

	/**
	 * Every card on the page: bundled and registered kits, then Pro kits, then Classic kits.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function cards(): array {
		$edition = Screen::edition();
		$pro     = ProKits::all();
		$names   = array();
		foreach ( $this->registry->all() as $id => $kit ) {
			$names[ $id ] = $kit->name();
		}
		foreach ( $pro as $id => $entry ) {
			$names[ $id ] = isset( $names[ $id ] ) ? $names[ $id ] : $entry['name'];
		}

		$free    = array();
		$pros    = array();
		$classic = array();
		foreach ( $this->registry->all() as $id => $kit ) {
			$card = $this->kit_card( $kit, isset( $pro[ $id ] ) ? $pro[ $id ] : null, $names );
			if ( 'classic' === $card['type'] ) {
				$classic[] = $card;
			} elseif ( 'pro' === $card['type'] ) {
				$pros[ $id ] = $card;
			} else {
				$free[] = $card;
			}
		}
		foreach ( $pro as $id => $entry ) {
			if ( isset( $pros[ $id ] ) || null !== $this->registry->get( $id ) ) {
				continue;
			}
			$pros[ $id ] = $this->pro_card( $entry, $names, $edition );
		}
		uasort(
			$pros,
			static function ( $a, $b ) {
				return strnatcasecmp( (string) $a['name'], (string) $b['name'] );
			}
		);
		return array_merge( $free, array_values( $pros ), $classic );
	}

	/**
	 * Card of a registered kit.
	 *
	 * @param Kit                       $kit   Kit.
	 * @param array<string, mixed>|null $entry Its Pro catalogue entry, if any.
	 * @param array<string, string>     $names Kit names by id.
	 * @return array<string, mixed>
	 */
	private function kit_card( Kit $kit, ?array $entry, array $names ): array {
		$id       = $kit->id();
		$on       = $this->registry->is_active( $id );
		$type     = $kit->is_classic() ? 'classic' : ( $kit instanceof ProKit || null !== $entry ? 'pro' : 'free' );
		$requires = $kit instanceof HasRequirements ? array_values( array_map( 'strval', $kit->requires() ) ) : array();
		$missing  = $on ? $this->registry->missing_requirements( $kit ) : array();

		if ( 'classic' === $type ) {
			$collection = 'classic';
			$settings   = $kit instanceof ClassicKit ? self::classic_settings_url( $kit, $on ) : '';
			$guide      = '';
		} else {
			$collection = null !== $entry ? (string) $entry['collection'] : ( self::FREE_COLLECTIONS[ $id ] ?? '' );
			$settings   = Admin::settings_url( $id );
			$guide      = null !== $entry ? (string) $entry['guide'] : ProKits::guide_url( $id );
		}

		return array(
			'id'          => $id,
			'name'        => $kit->name(),
			'description' => $kit->description(),
			'type'        => $type,
			'collection'  => $collection,
			'owned'       => true,
			'on'          => $on,
			'state'       => $on ? ( $missing ? 'waiting' : 'on' ) : 'off',
			'requires'    => $requires,
			'missing'     => $missing,
			'names'       => array_intersect_key( $names, array_flip( $requires ) ),
			'settings'    => $settings,
			'guide'       => $guide,
			'price'       => null !== $entry ? (string) $entry['price'] : '',
			'store_id'    => null !== $entry ? (int) $entry['store_id'] : 0,
		);
	}

	/**
	 * Card of a Pro kit that is not running (inactive, available or locked).
	 *
	 * @param array<string, mixed>  $entry   Catalogue entry.
	 * @param array<string, string> $names   Kit names by id.
	 * @param string                $edition Edition.
	 * @return array<string, mixed>
	 */
	private function pro_card( array $entry, array $names, string $edition ): array {
		$state = 'active' === $entry['state'] ? 'inactive' : (string) $entry['state']; // "active" but not registered: its plugin is not running.
		if ( 'free' === $edition ) {
			$state = 'locked';
		}
		$requires = (array) $entry['needs'];
		return array(
			'id'          => (string) $entry['kit_id'],
			'name'        => (string) $entry['name'],
			'description' => (string) $entry['description'],
			'type'        => 'pro',
			'collection'  => (string) $entry['collection'],
			'owned'       => 'inactive' === $state,
			'on'          => false,
			'state'       => $state,
			'requires'    => $requires,
			'missing'     => array(),
			'names'       => array_intersect_key( $names, array_flip( $requires ) ),
			'settings'    => '',
			'guide'       => (string) $entry['guide'],
			'price'       => (string) $entry['price'],
			'store_id'    => (int) $entry['store_id'],
		);
	}

	/**
	 * Switches a kit on or off.
	 *
	 * @param string $id Kit id.
	 * @param bool   $on New state.
	 * @return array<string, mixed>|\WP_Error Response data.
	 */
	public function toggle( string $id, bool $on ) {
		$kit = $this->registry->get( $id );
		if ( null === $kit ) {
			return new \WP_Error( 'wootoolkit_unknown_kit', __( 'That kit is not installed.', 'wootoolkit' ) );
		}
		$saved        = get_option( Registry::OPTION, array() );
		$saved        = is_array( $saved ) ? $saved : array();
		$saved[ $id ] = $on ? 'yes' : 'no';
		update_option( Registry::OPTION, $saved );

		$missing = array();
		foreach ( $on ? $this->registry->missing_requirements( $kit ) : array() as $required ) {
			$other     = $this->registry->get( $required );
			$missing[] = array(
				'id'   => $required,
				'name' => $other ? $other->name() : $required,
			);
		}
		$waiting = array();
		foreach ( $this->registry->active() as $active_id => $active ) {
			if ( $this->registry->missing_requirements( $active ) ) {
				$waiting[] = (string) $active_id;
			}
		}
		$settings = '';
		if ( $on ) {
			$settings = $kit instanceof ClassicKit ? self::classic_settings_url( $kit, false ) : Admin::settings_url( $id );
		}

		return array(
			'kit_id'       => $id,
			'on'           => $on,
			'state'        => $on ? ( $missing ? 'waiting' : 'on' ) : 'off',
			'missing'      => $missing,
			'waiting'      => $waiting,
			'settings_url' => $settings,
			/* translators: %s: kit name */
			'message'      => sprintf( $on ? __( '%s is on.', 'wootoolkit' ) : __( '%s is off.', 'wootoolkit' ), $kit->name() ),
		);
	}

	/**
	 * admin-ajax handler for the kit switches.
	 */
	public function ajax_toggle(): void {
		if ( ! check_ajax_referer( self::TOGGLE, '_ajax_nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'This page has expired. Reload it and try again.', 'wootoolkit' ) ), 403 );
		}
		if ( ! current_user_can( Admin::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to change kits.', 'wootoolkit' ) ), 403 );
		}
		$id     = isset( $_POST['kit'] ) && is_string( $_POST['kit'] ) ? sanitize_key( wp_unslash( $_POST['kit'] ) ) : '';
		$on     = isset( $_POST['on'] ) && '1' === $_POST['on'];
		$result = $this->toggle( $id, $on );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Filter buttons for the edition: id => label.
	 *
	 * @param string $edition Edition.
	 * @return array<string, string>
	 */
	public static function views( string $edition ): array {
		if ( 'free' === $edition ) {
			return array(
				'all'  => __( 'All', 'wootoolkit' ),
				'on'   => __( 'On', 'wootoolkit' ),
				'free' => __( 'Free kits', 'wootoolkit' ),
				'pro'  => __( 'Pro kits', 'wootoolkit' ),
			);
		}
		return array(
			'all'       => __( 'All', 'wootoolkit' ),
			'on'        => __( 'On', 'wootoolkit' ),
			'off'       => __( 'Off', 'wootoolkit' ),
			'available' => __( 'Not installed', 'wootoolkit' ),
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
		$cards   = $this->cards();
		$views   = self::views( $edition );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only preselects a filter.
		$view = isset( $_GET['view'] ) && is_string( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'all';
		if ( 'pro' === $view && ! isset( $views['pro'] ) ) {
			$view = 'available';
		}
		$view = isset( $views[ $view ] ) ? $view : 'all';
		$on   = count( wp_list_filter( $cards, array( 'on' => true ) ) );
		$pro  = count( wp_list_filter( $cards, array( 'type' => 'pro' ) ) );

		Screen::open( 'wootoolkit-kits' );
		Screen::licence_banner();
		?>
		<div class="wtk-notices" data-wtk-notices></div>
		<div class="wtk-intro">
			<h1 class="wtk-h1"><?php esc_html_e( 'Kits', 'wootoolkit' ); ?></h1>
			<p class="wtk-lead">
				<?php
				if ( 'free' === $edition ) {
					esc_html_e( 'Switch on the free kits you need. Pro kits add B2B, loyalty and conversion features.', 'wootoolkit' );
				} else {
					esc_html_e( 'Switch on only what your store needs. A kit that is off loads no code. Changes save straight away.', 'wootoolkit' );
				}
				?>
			</p>
		</div>

		<div class="wtk-card wtk-filters" data-wtk-filters data-view="<?php echo esc_attr( $view ); ?>">
			<div class="wtk-filters__row">
				<label for="wtk-kit-search" class="screen-reader-text"><?php esc_html_e( 'Search kits', 'wootoolkit' ); ?></label>
				<input id="wtk-kit-search" class="wtk-input wtk-filters__search" type="search" data-wtk-search placeholder="<?php esc_attr_e( 'Search kits by name or what they do', 'wootoolkit' ); ?>">
				<div class="wtk-chips" role="group" aria-label="<?php esc_attr_e( 'Show', 'wootoolkit' ); ?>">
					<?php foreach ( $views as $id => $label ) : ?>
						<button type="button" class="wtk-chip" data-wtk-view="<?php echo esc_attr( $id ); ?>" aria-pressed="<?php echo $id === $view ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="wtk-chips" role="group" aria-label="<?php esc_attr_e( 'Collection', 'wootoolkit' ); ?>">
				<button type="button" class="wtk-chip" data-wtk-col="all" aria-pressed="true"><?php esc_html_e( 'All collections', 'wootoolkit' ); ?></button>
				<?php foreach ( self::collections() as $id => $label ) : ?>
					<button type="button" class="wtk-chip" data-wtk-col="<?php echo esc_attr( $id ); ?>" aria-pressed="false"><?php echo esc_html( $label ); ?></button>
				<?php endforeach; ?>
			</div>
		</div>

		<p class="wtk-muted wtk-count" data-wtk-count aria-live="polite">
			<?php
			/* translators: 1: number of kits shown, 2: number of kits on */
			echo esc_html( sprintf( __( '%1$s kits shown · %2$s on', 'wootoolkit' ), number_format_i18n( count( $cards ) ), number_format_i18n( $on ) ) );
			?>
		</p>

		<div class="wtk-grid wtk-grid--kits" data-wtk-grid>
			<?php
			foreach ( $cards as $card ) {
				$this->print_card( $card, $edition );
			}
			?>
		</div>
		<p class="wtk-empty" data-wtk-empty hidden><?php esc_html_e( 'No kits match. Try another search or filter.', 'wootoolkit' ); ?></p>

		<template id="wtk-switch-template">
			<button type="button" class="wtk-switch" role="switch" aria-checked="true" data-wtk-toggle=""><span class="wtk-switch__knob"></span></button>
		</template>
		<?php
		if ( 'free' === $edition ) {
			$this->print_pro_panel( $pro );
		}
		Screen::close();
	}

	/**
	 * Prints one kit card.
	 *
	 * @param array<string, mixed> $card    Card.
	 * @param string               $edition Edition.
	 */
	private function print_card( array $card, string $edition ): void {
		$id      = (string) $card['id'];
		$type    = (string) $card['type'];
		$labels  = self::collections();
		$badges  = array(
			'free'    => __( 'Free', 'wootoolkit' ),
			'pro'     => __( 'Pro', 'wootoolkit' ),
			'classic' => __( 'Classic', 'wootoolkit' ),
		);
		$name_id = 'wtk-kit-name-' . $id;
		$col     = (string) $card['collection'];
		$search  = strtolower( $card['name'] . ' ' . $card['description'] . ' ' . ( $labels[ $col ] ?? '' ) );
		$switch  = $card['owned'] && 'inactive' !== $card['state'];
		$missing = (array) $card['missing'];
		$names   = (array) $card['names'];
		?>
		<article class="wtk-card wtk-kit" id="wtk-kit-<?php echo esc_attr( $id ); ?>" data-wtk-kit="<?php echo esc_attr( $id ); ?>" data-type="<?php echo esc_attr( $type ); ?>" data-collection="<?php echo esc_attr( $col ); ?>" data-owned="<?php echo $card['owned'] ? '1' : '0'; ?>" data-on="<?php echo $card['on'] ? '1' : '0'; ?>" data-name="<?php echo esc_attr( (string) $card['name'] ); ?>" data-requires="<?php echo esc_attr( implode( ',', (array) $card['requires'] ) ); ?>" data-search="<?php echo esc_attr( $search ); ?>">
			<div class="wtk-kit__top">
				<div class="wtk-kit__titles">
					<div class="wtk-kit__meta">
						<span class="wtk-badge wtk-badge--<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $badges[ $type ] ?? '' ); ?></span>
						<?php if ( isset( $labels[ $col ] ) && 'classic' !== $col ) : ?>
							<span class="wtk-kit__col"><?php echo esc_html( $labels[ $col ] ); ?></span>
						<?php endif; ?>
					</div>
					<h2 class="wtk-kit__name" id="<?php echo esc_attr( $name_id ); ?>"><?php echo esc_html( (string) $card['name'] ); ?></h2>
				</div>
				<?php if ( $switch ) : ?>
					<button type="button" class="wtk-switch" role="switch" aria-checked="<?php echo $card['on'] ? 'true' : 'false'; ?>" aria-labelledby="<?php echo esc_attr( $name_id ); ?>" data-wtk-toggle="<?php echo esc_attr( $id ); ?>"><span class="wtk-switch__knob"></span></button>
				<?php endif; ?>
			</div>
			<p class="wtk-kit__desc"><?php echo esc_html( (string) $card['description'] ); ?></p>

			<?php if ( $card['requires'] && $card['owned'] ) : ?>
				<?php
				$first = $missing ? (string) reset( $missing ) : (string) reset( $card['requires'] );
				$text  = array();
				foreach ( $missing ? $missing : (array) $card['requires'] as $required ) {
					$text[] = $names[ $required ] ?? $required;
				}
				?>
				<div class="wtk-kit__needs" data-wtk-needs<?php echo $missing ? '' : ' hidden'; ?>>
					<strong data-wtk-needs-text>
						<?php
						/* translators: %s: names of the kits it needs */
						echo esc_html( sprintf( __( 'Not running: it needs %s.', 'wootoolkit' ), implode( ', ', $text ) ) );
						?>
					</strong>
					<button type="button" class="wtk-button wtk-button--small wtk-button--red" data-wtk-enable="<?php echo esc_attr( $first ); ?>"><?php esc_html_e( 'Switch it on', 'wootoolkit' ); ?></button>
				</div>
			<?php elseif ( $card['requires'] ) : ?>
				<p class="wtk-kit__note">
					<?php
					$text = array();
					foreach ( (array) $card['requires'] as $required ) {
						$text[] = $names[ $required ] ?? $required;
					}
					/* translators: %s: names of the kits it needs */
					echo esc_html( sprintf( __( 'Works with %s.', 'wootoolkit' ), implode( ', ', $text ) ) );
					?>
				</p>
			<?php endif; ?>

			<div class="wtk-kit__progress" data-wtk-progress role="status" hidden>
				<div class="wtk-kit__progress-text"><span data-wtk-step></span><span data-wtk-step-num></span></div>
				<div class="wtk-bar"><div class="wtk-bar__fill" data-wtk-bar></div></div>
			</div>
			<div class="wtk-kit__error" data-wtk-error role="alert" hidden>
				<span data-wtk-error-text></span>
				<button type="button" class="wtk-button wtk-button--small wtk-button--red" data-wtk-retry><?php esc_html_e( 'Try again', 'wootoolkit' ); ?></button>
			</div>

			<div class="wtk-kit__foot">
				<?php if ( $switch && '' !== $card['settings'] ) : ?>
					<a class="wtk-kit__settings" href="<?php echo esc_url( (string) $card['settings'] ); ?>" data-wtk-settings<?php echo $card['on'] ? '' : ' hidden'; ?>><?php esc_html_e( 'Settings', 'wootoolkit' ); ?><span class="screen-reader-text"> <?php echo esc_html( (string) $card['name'] ); ?></span></a>
				<?php elseif ( $switch ) : ?>
					<a class="wtk-kit__settings" href="#" data-wtk-settings hidden><?php esc_html_e( 'Settings', 'wootoolkit' ); ?></a>
				<?php endif; ?>
				<?php $this->print_card_action( $card, $edition ); ?>
				<?php if ( '' !== $card['guide'] ) : ?>
					<?php echo Screen::external_link( (string) $card['guide'], __( 'Guide', 'wootoolkit' ), 'wtk-kit__guide' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link(). ?>
				<?php endif; ?>
				<span class="wtk-kit__status" data-wtk-status><?php echo esc_html( $this->status_text( $card, $edition ) ); ?></span>
			</div>
		</article>
		<?php
	}

	/**
	 * Prints the install / activate / Get Pro button of a Pro kit that is not running.
	 *
	 * @param array<string, mixed> $card    Card.
	 * @param string               $edition Edition.
	 */
	private function print_card_action( array $card, string $edition ): void {
		if ( 'pro' !== $card['type'] || in_array( $card['state'], array( 'on', 'off', 'waiting' ), true ) ) {
			return;
		}
		$id   = (string) $card['id'];
		$data = sprintf( ' data-wtk-kit-id="%1$s" data-store-id="%2$d" data-kit-name="%3$s"', esc_attr( $id ), (int) $card['store_id'], esc_attr( (string) $card['name'] ) );

		if ( 'available' === $card['state'] || 'inactive' === $card['state'] ) {
			printf(
				'<button type="button" class="wtk-button wtk-button--small" data-wtk-install%1$s>%2$s</button>',
				$data, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				esc_html( 'inactive' === $card['state'] ? __( 'Activate', 'wootoolkit' ) : __( 'Install', 'wootoolkit' ) )
			);
			return;
		}
		if ( in_array( $edition, array( 'nolicence', 'expired' ), true ) ) {
			printf( '<a class="wtk-kit__licence" href="%1$s">%2$s</a>', esc_url( Screen::url( 'wootoolkit-licence' ) ), esc_html__( 'Activate licence to install', 'wootoolkit' ) );
			return;
		}
		if ( 'free' === $edition ) {
			printf(
				'<a class="wtk-button wtk-button--small wtk-button--ink" href="%1$s" target="_blank" rel="noopener noreferrer" data-wtk-getpro%2$s data-desc="%3$s" data-price="%4$s">%5$s</a>',
				esc_url( ProKits::buy_url( $id ) ),
				$data, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				esc_attr( (string) $card['description'] ),
				esc_attr( (string) $card['price'] ),
				esc_html__( 'Get this kit', 'wootoolkit' )
			);
			return;
		}
		// Pro with a licence that does not include this kit.
		echo Screen::external_link( ProKits::buy_url( $id ), __( 'Get this kit', 'wootoolkit' ), 'wtk-button wtk-button--small wtk-button--ink' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link().
	}

	/**
	 * Status text at the bottom right of a card.
	 *
	 * @param array<string, mixed> $card    Card.
	 * @param string               $edition Edition.
	 */
	private function status_text( array $card, string $edition ): string {
		switch ( $card['state'] ) {
			case 'on':
				return __( 'On', 'wootoolkit' );
			case 'waiting':
				return __( 'On, waiting', 'wootoolkit' );
			case 'off':
				return __( 'Off', 'wootoolkit' );
			case 'inactive':
				return __( 'Installed, not active', 'wootoolkit' );
			case 'available':
				return __( 'In your licence', 'wootoolkit' );
		}
		if ( 'free' === $edition ) {
			/* translators: %s: yearly price, e.g. $49 */
			return '' !== $card['price'] ? sprintf( __( '%s / year, or in the membership', 'wootoolkit' ), $card['price'] ) : __( 'In the membership', 'wootoolkit' );
		}
		return 'pro' === $edition ? __( 'Not in your licence', 'wootoolkit' ) : __( 'Needs a licence', 'wootoolkit' );
	}

	/**
	 * The Get Pro panel (free edition).
	 *
	 * @param int $count Number of Pro kits.
	 */
	private function print_pro_panel( int $count ): void {
		?>
		<div class="wtk-modal" data-wtk-modal="pro" hidden>
			<div class="wtk-modal__backdrop" data-wtk-close></div>
			<div class="wtk-dialog" role="dialog" aria-modal="true" aria-labelledby="wtk-pro-title" aria-describedby="wtk-pro-desc" tabindex="-1">
				<div class="wtk-dialog__head">
					<div class="wtk-dialog__titles">
						<span class="wtk-badge wtk-badge--pro"><?php esc_html_e( 'Pro kit', 'wootoolkit' ); ?></span>
						<h2 class="wtk-dialog__title" id="wtk-pro-title" data-wtk-pro-title><?php esc_html_e( 'WooToolkit Pro', 'wootoolkit' ); ?></h2>
						<p class="wtk-dialog__desc" id="wtk-pro-desc" data-wtk-pro-desc>
							<?php
							/* translators: %d: number of Pro kits */
							echo esc_html( sprintf( __( '%d Pro kits for B2B, loyalty and conversion, installed from this page once your licence is active.', 'wootoolkit' ), $count ) );
							?>
						</p>
					</div>
					<button type="button" class="wtk-dialog__close" data-wtk-close aria-label="<?php esc_attr_e( 'Close', 'wootoolkit' ); ?>">&times;</button>
				</div>
				<div class="wtk-plans">
					<div class="wtk-plan" data-wtk-pro-single hidden>
						<span class="wtk-plan__name"><?php esc_html_e( 'Just this kit', 'wootoolkit' ); ?></span>
						<span class="wtk-plan__price"><span data-wtk-pro-price></span><span class="wtk-plan__per"> <?php esc_html_e( '/ year', 'wootoolkit' ); ?></span></span>
						<span class="wtk-plan__note"><?php esc_html_e( '1 site · updates and support', 'wootoolkit' ); ?></span>
						<a class="wtk-button" href="<?php echo esc_url( ProKits::buy_url( '' ) ); ?>" target="_blank" rel="noopener noreferrer" data-wtk-pro-buy><?php esc_html_e( 'Buy this kit', 'wootoolkit' ); ?><span aria-hidden="true"> &#8599;</span><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'wootoolkit' ); ?></span></a>
					</div>
					<div class="wtk-plan wtk-plan--featured">
						<span class="wtk-plan__name">
							<?php
							/* translators: %d: number of Pro kits */
							echo esc_html( sprintf( __( 'All %d Pro kits', 'wootoolkit' ), $count ) );
							?>
						</span>
						<span class="wtk-plan__price">$129<span class="wtk-plan__per"> <?php esc_html_e( '/ year', 'wootoolkit' ); ?></span></span>
						<span class="wtk-plan__note"><?php esc_html_e( '1 site · $199 for 5 · $299 for 50', 'wootoolkit' ); ?></span>
						<?php echo Screen::external_link( 'https://wootoolkit.com/pricing/', __( 'See membership', 'wootoolkit' ), 'wtk-button wtk-button--ink' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in external_link(). ?>
					</div>
				</div>
				<div class="wtk-dialog__how">
					<strong><?php esc_html_e( 'How it works', 'wootoolkit' ); ?></strong>
					<ol>
						<li><?php esc_html_e( 'Buy on wootoolkit.com. You get a licence key by email.', 'wootoolkit' ); ?></li>
						<li><?php esc_html_e( 'Download WooToolkit Pro from your account and install it like any plugin.', 'wootoolkit' ); ?></li>
						<li><?php esc_html_e( 'Enter your key under WooToolkit › Licence. Your kits then install from this page in one click.', 'wootoolkit' ); ?></li>
					</ol>
				</div>
				<div class="wtk-dialog__foot">
					<a href="<?php echo esc_url( Screen::url( 'wootoolkit-help' ) . '#wtk-help-pro' ); ?>"><?php esc_html_e( 'I already have a licence', 'wootoolkit' ); ?></a>
					<span class="wtk-muted"><?php esc_html_e( '↗ opens wootoolkit.com in a new tab', 'wootoolkit' ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}
}
