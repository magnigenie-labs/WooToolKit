<?php
/**
 * WooToolkit > Dashboard.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Admin;

use WooToolkit\Kit\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Kits on, compatibility, the cards from `wootoolkit_dashboard_cards`, "Needs your attention"
 * (`wootoolkit_dashboard_attention`) and "From your kits" (`wootoolkit_dashboard_activity`).
 */
final class DashboardPage {

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
	 * Items that need the owner: active kits missing a kit they need, plus kits' own items.
	 *
	 * @return array<int, array{kit: string, text: string, action_label: string, url: string, level: string}>
	 */
	public function attention(): array {
		$items = array();
		foreach ( $this->registry->active() as $kit ) {
			$missing = $this->registry->missing_requirements( $kit );
			if ( ! $missing ) {
				continue;
			}
			$names = array();
			foreach ( $missing as $id ) {
				$other   = $this->registry->get( $id );
				$names[] = $other ? $other->name() : $id;
			}
			$first   = $this->registry->get( (string) reset( $missing ) );
			$items[] = array(
				'kit'          => $kit->name(),
				/* translators: %s: names of the kits it needs */
				'text'         => sprintf( __( 'needs the %s kit.', 'wootoolkit' ), implode( ', ', $names ) ),
				/* translators: %s: kit name */
				'action_label' => $first ? sprintf( __( 'Switch on %s', 'wootoolkit' ), $first->name() ) : __( 'Open Kits', 'wootoolkit' ),
				'url'          => Admin::settings_url() . ( $first ? '#wtk-kit-' . $first->id() : '' ),
				'level'        => 'red',
			);
		}
		/**
		 * "Needs your attention" items on the WooToolkit dashboard.
		 *
		 * @param array<int, array<string, string>> $items Each: kit, text, action_label, url, level (red|amber).
		 */
		return self::clean_items( apply_filters( 'wootoolkit_dashboard_attention', $items ), array( 'kit', 'text', 'action_label', 'url', 'level' ) );
	}

	/**
	 * Lines from kits that have something to report.
	 *
	 * @return array<int, array{kit: string, text: string, link_label: string, url: string}>
	 */
	public static function activity(): array {
		/**
		 * "From your kits" lines on the WooToolkit dashboard and the WordPress dashboard widget.
		 *
		 * @param array<int, array<string, string>> $items Each: kit, text, link_label, url.
		 */
		return self::clean_items( apply_filters( 'wootoolkit_dashboard_activity', array() ), array( 'kit', 'text', 'link_label', 'url' ) );
	}

	/**
	 * The two right-hand cards of the stats row.
	 *
	 * @return array<int, array{id: string, label: string, title: string, text: string, link_label: string, url: string, tone: string}>
	 */
	public function cards(): array {
		$cards = array();
		if ( Screen::is_free() ) {
			$count   = count( ProKits::all() );
			$cards[] = array(
				'id'         => 'pro',
				'label'      => __( 'WooToolkit Pro', 'wootoolkit' ),
				/* translators: %d: number of Pro kits */
				'title'      => sprintf( __( '%d more kits', 'wootoolkit' ), $count ),
				'text'       => __( 'B2B, loyalty and conversion kits', 'wootoolkit' ),
				'link_label' => __( 'See the Pro kits', 'wootoolkit' ),
				'url'        => Screen::url( 'wootoolkit-kits', array( 'view' => 'pro' ) ),
				'tone'       => 'dark',
			);
		}
		$cards[] = array(
			'id'         => 'updates',
			'label'      => __( 'Updates', 'wootoolkit' ),
			'title'      => __( 'Everything up to date', 'wootoolkit' ),
			'text'       => __( 'Free kits update with the WooToolkit plugin.', 'wootoolkit' ),
			'link_label' => '',
			'url'        => '',
			'tone'       => 'default',
		);
		/**
		 * The right two cards of the dashboard stats row. WooToolkit Pro replaces them with its
		 * Licence and Updates cards.
		 *
		 * @param array<int, array<string, string>> $cards Each: id, label, title, text, link_label, url, tone (default|dark|warn).
		 */
		$cards = self::clean_items( apply_filters( 'wootoolkit_dashboard_cards', $cards ), array( 'id', 'label', 'title', 'text', 'link_label', 'url', 'tone' ) );
		return array_slice( $cards, 0, 2 );
	}

	/**
	 * Keeps items that are arrays, with string values for the given keys.
	 *
	 * @param mixed    $items Filtered items.
	 * @param string[] $keys  Keys.
	 * @return array<int, array<string, string>>
	 */
	private static function clean_items( $items, array $keys ): array {
		$out = array();
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$row = array();
			foreach ( $keys as $key ) {
				$row[ $key ] = isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) ? (string) $item[ $key ] : '';
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Number of kits on and of kits counted on the dashboard.
	 *
	 * @return array{0: int, 1: int}
	 */
	public function counts(): array {
		$on    = 0;
		$total = 0;
		foreach ( $this->registry->all() as $id => $kit ) {
			$active = $this->registry->is_active( (string) $id );
			$on    += $active ? 1 : 0;
			// Classic kits only count while they are on.
			$total += ( ! $kit->is_classic() || $active ) ? 1 : 0;
		}
		if ( ! Screen::is_free() ) {
			foreach ( ProKits::all() as $id => $entry ) {
				$total += null === $this->registry->get( $id ) ? 1 : 0;
			}
		}
		return array( $on, $total );
	}

	/**
	 * Prints the page.
	 */
	public function render(): void {
		if ( ! current_user_can( Admin::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'wootoolkit' ), 403 );
		}
		list( $on, $total ) = $this->counts();
		$blocks             = $this->registry->supports( 'blocks' );
		$hpos               = $this->registry->supports( 'hpos' );
		$free               = Screen::is_free();
		$kits_url           = Admin::settings_url();
		$help_url           = Screen::url( 'wootoolkit-help' );

		Screen::open( 'wootoolkit' );
		Screen::licence_banner();
		?>
		<div class="wtk-intro wtk-intro--row">
			<div>
				<h1 class="wtk-h1"><?php esc_html_e( 'Your store\'s kits', 'wootoolkit' ); ?></h1>
				<p class="wtk-lead">
					<?php
					echo esc_html(
						$free
							/* translators: 1: kits on, 2: number of kits */
							? sprintf( __( '%1$s of %2$s free kits on.', 'wootoolkit' ), number_format_i18n( $on ), number_format_i18n( $total ) )
							/* translators: 1: kits on, 2: number of kits */
							: sprintf( __( '%1$s of %2$s kits on.', 'wootoolkit' ), number_format_i18n( $on ), number_format_i18n( $total ) )
					);
					echo ' ';
					echo esc_html( $blocks && $hpos ? __( 'Every kit you use works with the Cart and Checkout blocks and HPOS.', 'wootoolkit' ) : __( 'Some kits you use need the classic checkout or posts order storage.', 'wootoolkit' ) );
					?>
				</p>
			</div>
			<a class="wtk-button wtk-button--large" href="<?php echo esc_url( $kits_url ); ?>"><?php esc_html_e( 'Browse kits', 'wootoolkit' ); ?></a>
		</div>

		<div class="wtk-grid wtk-grid--stats">
			<div class="wtk-card wtk-stat">
				<span class="wtk-stat__label"><?php esc_html_e( 'Kits on', 'wootoolkit' ); ?></span>
				<span class="wtk-stat__big"><?php echo esc_html( number_format_i18n( $on ) ); ?> <span class="wtk-stat__of">/ <?php echo esc_html( number_format_i18n( $total ) ); ?></span></span>
				<a href="<?php echo esc_url( $kits_url ); ?>"><?php esc_html_e( 'Manage kits', 'wootoolkit' ); ?></a>
			</div>
			<div class="wtk-card wtk-stat">
				<span class="wtk-stat__label"><?php esc_html_e( 'Compatibility', 'wootoolkit' ); ?></span>
				<span class="wtk-stat__check<?php echo $blocks ? '' : ' is-warn'; ?>"><?php echo Screen::icon( $blocks ? 'check' : 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $blocks ? __( 'Cart and Checkout blocks', 'wootoolkit' ) : __( 'Classic checkout only', 'wootoolkit' ) ); ?></span>
				<span class="wtk-stat__check<?php echo $hpos ? '' : ' is-warn'; ?>"><?php echo Screen::icon( $hpos ? 'check' : 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $hpos ? __( 'HPOS order storage', 'wootoolkit' ) : __( 'Posts order storage only', 'wootoolkit' ) ); ?></span>
				<a href="<?php echo esc_url( $help_url . '#wtk-faq' ); ?>"><?php esc_html_e( 'What this means', 'wootoolkit' ); ?></a>
			</div>
			<?php foreach ( $this->cards() as $card ) : ?>
				<div class="wtk-card wtk-stat wtk-stat--<?php echo esc_attr( in_array( $card['tone'], array( 'dark', 'warn' ), true ) ? $card['tone'] : 'default' ); ?>" data-wtk-card="<?php echo esc_attr( $card['id'] ); ?>">
					<span class="wtk-stat__label"><?php echo esc_html( $card['label'] ); ?></span>
					<span class="wtk-stat__title"><?php echo esc_html( $card['title'] ); ?></span>
					<?php if ( '' !== $card['text'] ) : ?>
						<span class="wtk-stat__text"><?php echo esc_html( $card['text'] ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $card['url'] && '' !== $card['link_label'] ) : ?>
						<a href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( $card['link_label'] ); ?></a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="wtk-columns">
			<div class="wtk-columns__main">
				<?php if ( 'no' !== get_option( 'wootoolkit_attention_alerts', 'yes' ) ) : ?>
					<?php $this->print_attention(); ?>
				<?php endif; ?>
				<section class="wtk-card wtk-section" aria-labelledby="wtk-activity-title">
					<h2 class="wtk-h2" id="wtk-activity-title"><?php esc_html_e( 'From your kits', 'wootoolkit' ); ?></h2>
					<?php self::print_activity( self::activity() ); ?>
				</section>
			</div>
			<aside class="wtk-columns__side">
				<?php $this->print_aside( $free ); ?>
			</aside>
		</div>
		<?php
		Screen::close();
	}

	/**
	 * "Needs your attention".
	 */
	private function print_attention(): void {
		$items = $this->attention();
		$tags  = array(
			'red'   => __( 'Not running', 'wootoolkit' ),
			'amber' => __( 'Set up', 'wootoolkit' ),
		);
		?>
		<section class="wtk-card wtk-section wtk-section--list" aria-labelledby="wtk-attention-title">
			<h2 class="wtk-h2" id="wtk-attention-title"><?php esc_html_e( 'Needs your attention', 'wootoolkit' ); ?></h2>
			<?php if ( ! $items ) : ?>
				<p class="wtk-muted wtk-section__empty"><?php esc_html_e( 'Nothing needs your attention. Every kit that is on is running.', 'wootoolkit' ); ?></p>
			<?php else : ?>
				<ul class="wtk-attention">
					<?php foreach ( $items as $item ) : ?>
						<?php $level = 'red' === $item['level'] ? 'red' : 'amber'; ?>
						<li class="wtk-attention__row">
							<span class="wtk-tag wtk-tag--<?php echo esc_attr( $level ); ?>"><?php echo esc_html( $tags[ $level ] ); ?></span>
							<span class="wtk-attention__text"><strong><?php echo esc_html( $item['kit'] ); ?></strong> <?php echo esc_html( $item['text'] ); ?></span>
							<?php if ( '' !== $item['url'] ) : ?>
								<a class="wtk-attention__action" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( '' !== $item['action_label'] ? $item['action_label'] : __( 'Fix it', 'wootoolkit' ) ); ?></a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Prints activity lines (dashboard page and widget).
	 *
	 * @param array<int, array<string, string>> $items Activity items.
	 */
	public static function print_activity( array $items ): void {
		if ( ! $items ) {
			echo '<p class="wtk-muted">' . esc_html__( 'Nothing to report yet. A kit adds a line here when it has something for you.', 'wootoolkit' ) . '</p>';
			return;
		}
		echo '<div class="wtk-activity">';
		foreach ( $items as $item ) {
			$tag = '' !== $item['url'] ? 'a' : 'div';
			printf(
				'<%1$s class="wtk-activity__item"%2$s><span class="wtk-activity__kit">%3$s</span><span class="wtk-activity__text">%4$s</span>%5$s</%1$s>',
				esc_html( $tag ),
				'a' === $tag ? ' href="' . esc_url( $item['url'] ) . '"' : '',
				esc_html( $item['kit'] ),
				esc_html( $item['text'] ),
				'' !== $item['link_label'] ? '<span class="wtk-activity__link">' . esc_html( $item['link_label'] ) . '</span>' : ''
			);
		}
		echo '</div>';
	}

	/**
	 * The side column: Get started, kit settings, custom kits and help.
	 *
	 * @param bool $free Free edition.
	 */
	private function print_aside( bool $free ): void {
		$help    = Screen::url( 'wootoolkit-help' );
		$active  = array_filter(
			$this->registry->active(),
			static function ( $kit ) {
				return ! $kit->is_classic();
			}
		);
		$started = (bool) $active;
		$saved   = 'yes' === get_option( KitSettingsPage::SAVED_OPTION );
		if ( $free && ! ( $started && $saved ) ) :
			?>
			<section class="wtk-card wtk-section" aria-labelledby="wtk-start-title">
				<h2 class="wtk-h2" id="wtk-start-title"><?php esc_html_e( 'Get started', 'wootoolkit' ); ?></h2>
				<ol class="wtk-steps">
					<li class="is-done"><s><?php esc_html_e( 'Install WooToolkit', 'wootoolkit' ); ?></s></li>
					<li<?php echo $started ? ' class="is-done"' : ''; ?>>
						<?php if ( $started ) : ?>
							<s><?php esc_html_e( 'Switch on your first kit', 'wootoolkit' ); ?></s>
						<?php else : ?>
							<a href="<?php echo esc_url( Admin::settings_url() ); ?>"><?php esc_html_e( 'Switch on your first kit', 'wootoolkit' ); ?></a>
						<?php endif; ?>
					</li>
					<li><?php esc_html_e( 'Set it up, then test your checkout', 'wootoolkit' ); ?></li>
				</ol>
				<span class="wtk-muted wtk-small"><?php esc_html_e( 'Shown until you have switched on a kit and saved its settings.', 'wootoolkit' ); ?></span>
			</section>
		<?php endif; ?>
		<?php if ( $active ) : ?>
			<section class="wtk-card wtk-section" aria-labelledby="wtk-quick-title">
				<h2 class="wtk-h2" id="wtk-quick-title"><?php esc_html_e( 'Kit settings', 'wootoolkit' ); ?></h2>
				<ul class="wtk-links">
					<?php foreach ( array_slice( $active, 0, 6, true ) as $id => $kit ) : ?>
						<li><a href="<?php echo esc_url( Admin::settings_url( (string) $id ) ); ?>"><?php echo esc_html( $kit->name() ); ?></a></li>
					<?php endforeach; ?>
				</ul>
				<?php if ( count( $active ) > 6 ) : ?>
					<a class="wtk-muted wtk-small" href="<?php echo esc_url( add_query_arg( 'view', 'on', Admin::settings_url() ) ); ?>">
						<?php
						/* translators: %d: number of kits that are switched on */
						echo esc_html( sprintf( _n( 'All %d kit that is on', 'All %d kits that are on', count( $active ), 'wootoolkit' ), count( $active ) ) );
						?>
					</a>
				<?php endif; ?>
			</section>
		<?php endif; ?>
		<section class="wtk-card wtk-section wtk-section--dark" aria-labelledby="wtk-custom-title">
			<h2 class="wtk-h2" id="wtk-custom-title"><?php esc_html_e( 'Need a kit we don\'t have?', 'wootoolkit' ); ?></h2>
			<p><?php esc_html_e( 'Tell us what your store needs. Custom kits start at $500.', 'wootoolkit' ); ?></p>
			<a class="wtk-button wtk-button--mint" href="<?php echo esc_url( $help . '#wtk-support' ); ?>"><?php esc_html_e( 'Request a kit', 'wootoolkit' ); ?></a>
		</section>
		<section class="wtk-card wtk-section" aria-labelledby="wtk-help-title">
			<h2 class="wtk-h2" id="wtk-help-title"><?php esc_html_e( 'Help', 'wootoolkit' ); ?></h2>
			<ul class="wtk-links">
				<li><a href="<?php echo esc_url( $help . '#wtk-new' ); ?>"><?php esc_html_e( 'What\'s new in 2.0', 'wootoolkit' ); ?></a></li>
				<li><a href="<?php echo esc_url( $help . '#wtk-guides' ); ?>"><?php esc_html_e( 'Guides for every kit', 'wootoolkit' ); ?></a></li>
				<li><a href="<?php echo esc_url( $help . '#wtk-support' ); ?>"><?php esc_html_e( 'Get support', 'wootoolkit' ); ?></a></li>
			</ul>
		</section>
		<?php
	}
}
