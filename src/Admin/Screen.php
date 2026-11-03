<?php
/**
 * Shared parts of every WooToolkit admin page.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Admin;

use WooToolkit\Install\Installer;

defined( 'ABSPATH' ) || exit;

/**
 * Every page whose slug starts with "wootoolkit" (free or Pro) gets the brand stylesheet and script,
 * the branded header (`wootoolkit_admin_header`) and no notices but WooToolkit's own.
 */
final class Screen {

	/**
	 * Script and style handle.
	 */
	const HANDLE = 'wootoolkit-admin';

	/**
	 * Editions the header and pages know about.
	 */
	const EDITIONS = array( 'free', 'pro', 'nolicence', 'expired' );

	/**
	 * Registers the hooks.
	 */
	public static function hooks(): void {
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'menu_style' ) );
		add_action( 'in_admin_header', array( self::class, 'hide_notices' ), 1000 );
		add_filter( 'admin_body_class', array( self::class, 'body_class' ) );
		add_action( 'wootoolkit_admin_header', array( self::class, 'header' ) );
	}

	/**
	 * The requested admin page slug ('' outside admin.php pages).
	 */
	public static function current_page(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only identifies the screen.
		return isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	}

	/**
	 * Whether this request is for a WooToolkit page (free or Pro).
	 */
	public static function is_wootoolkit_screen(): bool {
		return is_admin() && 0 === strpos( self::current_page(), 'wootoolkit' );
	}

	/**
	 * Edition: free (default), pro, nolicence or expired. WooToolkit Pro returns its licence state.
	 */
	public static function edition(): string {
		/**
		 * The WooToolkit edition the admin pages are drawn for.
		 *
		 * @param string $edition free, pro, nolicence or expired.
		 */
		$edition = (string) apply_filters( 'wootoolkit_admin_edition', 'free' );
		return in_array( $edition, self::EDITIONS, true ) ? $edition : 'free';
	}

	/**
	 * Whether the free plugin is running without WooToolkit Pro.
	 */
	public static function is_free(): bool {
		return 'free' === self::edition();
	}

	/**
	 * Header tabs, slug => label, in order.
	 *
	 * @return array<string, string>
	 */
	public static function tabs(): array {
		$tabs = array(
			'wootoolkit'          => __( 'Dashboard', 'wootoolkit' ),
			'wootoolkit-kits'     => __( 'Kits', 'wootoolkit' ),
			'wootoolkit-settings' => __( 'Settings', 'wootoolkit' ),
			'wootoolkit-help'     => __( 'Help', 'wootoolkit' ),
		);
		/**
		 * Tabs in the WooToolkit header. WooToolkit Pro inserts its Licence page before Help.
		 *
		 * @param array<string, string> $tabs Page slug => label.
		 */
		$tabs = apply_filters( 'wootoolkit_admin_tabs', $tabs );
		$out  = array();
		foreach ( is_array( $tabs ) ? $tabs : array() as $slug => $label ) {
			$out[ sanitize_key( (string) $slug ) ] = (string) $label;
		}
		return $out;
	}

	/**
	 * Text of the plan pill in the header.
	 */
	public static function plan_label(): string {
		/**
		 * Plan pill text in the WooToolkit header, e.g. "Pro · Membership".
		 *
		 * @param string $label   Label.
		 * @param string $edition Edition.
		 */
		return (string) apply_filters( 'wootoolkit_admin_plan_label', __( 'Free', 'wootoolkit' ), self::edition() );
	}

	/**
	 * URL of a WooToolkit page.
	 *
	 * @param string               $page Page slug.
	 * @param array<string, mixed> $args Extra query arguments.
	 */
	public static function url( string $page, array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Prints the branded header (hooked to `wootoolkit_admin_header`).
	 *
	 * @param string $current Slug of the page being shown.
	 */
	public static function header( $current = '' ): void {
		$current = (string) $current;
		$edition = self::edition();
		$tone    = 'pro' === $edition ? 'mint' : ( 'free' === $edition ? 'plain' : 'amber' );
		?>
		<header class="wtk-header">
			<div class="wtk-header__brand">
				<?php echo self::logo( 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				<span class="wtk-header__name"><?php esc_html_e( 'WooToolkit', 'wootoolkit' ); ?></span>
				<span class="wtk-header__version"><?php echo esc_html( WOOTOOLKIT_VERSION ); ?></span>
			</div>
			<nav class="wtk-header__tabs" aria-label="<?php esc_attr_e( 'WooToolkit pages', 'wootoolkit' ); ?>">
				<?php foreach ( self::tabs() as $slug => $label ) : ?>
					<a class="wtk-header__tab<?php echo $slug === $current ? ' is-current' : ''; ?>" href="<?php echo esc_url( self::url( $slug ) ); ?>"<?php echo $slug === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="wtk-header__end">
				<span class="wtk-pill wtk-pill--<?php echo esc_attr( $tone ); ?> wtk-header__plan"><?php echo esc_html( self::plan_label() ); ?></span>
				<?php if ( 'free' === $edition ) : ?>
					<a class="wtk-button wtk-button--mint" href="<?php echo esc_url( self::url( 'wootoolkit-kits', array( 'view' => 'pro' ) ) ); ?>"><?php esc_html_e( 'Get Pro', 'wootoolkit' ); ?></a>
				<?php endif; ?>
			</div>
		</header>
		<?php
	}

	/**
	 * Opens a page: wrapper, header and the main content column.
	 *
	 * @param string $slug Page slug.
	 */
	public static function open( string $slug ): void {
		echo '<div class="wtk-app">';
		/**
		 * Prints the WooToolkit header. WooToolkit Pro's Licence page calls it too.
		 *
		 * @param string $slug Current page slug.
		 */
		do_action( 'wootoolkit_admin_header', $slug );
		echo '<div class="wtk-page" id="wtk-main">';
		echo '<div class="wtk-live screen-reader-text" aria-live="polite" data-wtk-live></div>';
	}

	/**
	 * Closes a page opened with open().
	 */
	public static function close(): void {
		echo '</div></div>';
	}

	/**
	 * The WooToolkit mark.
	 *
	 * @param int $size Width and height in pixels.
	 */
	public static function logo( int $size ): string {
		return '<svg class="wtk-logo" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 256 256" aria-hidden="true" focusable="false"><rect width="256" height="256" rx="52" fill="#086688"/><g transform="translate(0 22)"><path d="M88 66V122Q88 151 117 151H142Q171 151 171 122V66" fill="none" stroke="#44D2B8" stroke-width="22" stroke-linecap="round"/><path d="M128 66V124Q128 150 152 150" fill="none" stroke="#086688" stroke-width="32" stroke-linecap="round"/><path d="M128 66V124Q128 150 152 150" fill="none" stroke="#FFFFFF" stroke-width="21" stroke-linecap="round"/><path d="M128 83H146" fill="none" stroke="#FFFFFF" stroke-width="18" stroke-linecap="round"/></g></svg>';
	}

	/**
	 * A small inline icon.
	 *
	 * @param string $name check, warn, heart, kit or external.
	 */
	public static function icon( string $name ): string {
		$paths = array(
			'check' => '<path d="M20 6 9 17l-5-5"/>',
			'warn'  => '<path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>',
			'kit'   => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg class="wtk-icon wtk-icon--' . esc_attr( $name ) . '" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * A link to wootoolkit.com or WordPress.org that opens in a new tab.
	 *
	 * @param string $url     URL.
	 * @param string $label   Link text.
	 * @param string $classes CSS classes.
	 */
	public static function external_link( string $url, string $label, string $classes = '' ): string {
		return sprintf(
			'<a href="%1$s" class="%2$s" target="_blank" rel="noopener noreferrer">%3$s<span aria-hidden="true"> &#8599;</span><span class="screen-reader-text"> %4$s</span></a>',
			esc_url( $url ),
			esc_attr( trim( 'wtk-external ' . $classes ) ),
			esc_html( $label ),
			esc_html__( '(opens in a new tab)', 'wootoolkit' )
		);
	}

	/**
	 * Prints a WooToolkit notice inside a page.
	 *
	 * @param string $text Message (plain text).
	 * @param string $tone success, warn or error.
	 * @param string $html Extra HTML after the message (already escaped).
	 */
	public static function notice( string $text, string $tone = 'success', string $html = '' ): void {
		$role = 'error' === $tone ? 'alert' : 'status';
		printf(
			'<div class="wtk-notice wtk-notice--%1$s" role="%2$s">%3$s<strong class="wtk-notice__text">%4$s</strong>%5$s</div>',
			esc_attr( $tone ),
			esc_attr( $role ),
			self::icon( 'success' === $tone ? 'check' : 'warn' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			esc_html( $text ),
			$html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller.
		);
	}

	/**
	 * The "WooToolkit Pro is installed, but no licence is active" banner (Pro editions without a licence).
	 */
	public static function licence_banner(): void {
		$edition = self::edition();
		if ( ! in_array( $edition, array( 'nolicence', 'expired' ), true ) ) {
			return;
		}
		$link = '<a class="wtk-button wtk-button--amber" href="' . esc_url( self::url( 'wootoolkit-licence' ) ) . '">' . esc_html__( 'Activate licence', 'wootoolkit' ) . '</a>';
		$text = 'expired' === $edition
			? __( 'Your WooToolkit Pro licence has expired. Your Pro kits keep working; renew to install more kits and get updates.', 'wootoolkit' )
			: __( 'WooToolkit Pro is installed, but no licence is active. Your Pro kits keep working; activate your licence to install more kits and get updates.', 'wootoolkit' );
		self::notice( $text, 'warn', $link );
	}

	/**
	 * On WooToolkit pages, buffers every admin notice and keeps only WooToolkit's own: elements with
	 * the class `wootoolkit-notice` (WooToolkit Pro's licence notice, for example).
	 */
	public static function hide_notices(): void {
		if ( ! self::is_wootoolkit_screen() ) {
			return;
		}
		foreach ( array( 'network_admin_notices', 'user_admin_notices', 'admin_notices' ) as $hook ) {
			add_action( $hook, array( self::class, 'start_notices' ), PHP_INT_MIN );
		}
		add_action( 'all_admin_notices', array( self::class, 'start_notices' ), PHP_INT_MIN );
		add_action( 'all_admin_notices', array( self::class, 'end_notices' ), PHP_INT_MAX );
		// The 1.x upgrade notice has done its job once the owner is here.
		if ( get_option( Installer::UPGRADE_NOTICE_OPTION ) ) {
			delete_option( Installer::UPGRADE_NOTICE_OPTION );
		}
	}

	/**
	 * Whether notice output is being buffered.
	 *
	 * @var bool
	 */
	private static $buffering = false;

	/**
	 * Starts buffering notices (once).
	 */
	public static function start_notices(): void {
		if ( ! self::$buffering ) {
			self::$buffering = true;
			ob_start();
		}
	}

	/**
	 * Prints the buffered notices that are WooToolkit's own.
	 */
	public static function end_notices(): void {
		if ( ! self::$buffering ) {
			return;
		}
		self::$buffering = false;
		echo self::keep_own_notices( (string) ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notices printed by WooToolkit (free or Pro), already escaped by their authors.
	}

	/**
	 * The top-level elements of notice HTML that carry the class `wootoolkit-notice`.
	 *
	 * @param string $html Notices HTML.
	 */
	public static function keep_own_notices( string $html ): string {
		if ( false === strpos( $html, 'wootoolkit-notice' ) || ! class_exists( '\DOMDocument' ) ) {
			return '';
		}
		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div id="wtk-notices-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$root = $doc->getElementById( 'wtk-notices-root' );
		$out  = '';
		if ( $root ) {
			foreach ( $root->childNodes as $node ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
				if ( $node instanceof \DOMElement && preg_match( '/(^|\s)wootoolkit-notice(\s|$)/', (string) $node->getAttribute( 'class' ) ) ) {
					$out .= (string) $doc->saveHTML( $node );
				}
			}
		}
		return $out;
	}

	/**
	 * Body classes on WooToolkit pages.
	 *
	 * @param string $classes Space-separated classes.
	 */
	public static function body_class( $classes ): string {
		$classes = (string) $classes;
		if ( self::is_wootoolkit_screen() ) {
			$classes .= ' wtk-screen';
			if ( 'no' === get_option( 'wootoolkit_show_tips', 'yes' ) ) {
				$classes .= ' wtk-no-tips';
			}
		}
		return $classes;
	}

	/**
	 * Colours the free edition's "Get Pro" menu link (the rest of the WordPress menu is left alone).
	 */
	public static function menu_style(): void {
		wp_register_style( 'wootoolkit-menu', false, array(), WOOTOOLKIT_VERSION );
		wp_enqueue_style( 'wootoolkit-menu' );
		wp_add_inline_style( 'wootoolkit-menu', '#adminmenu .wtk-menu-pro{color:#44d2b8;font-weight:600}' );
	}

	/**
	 * Stylesheet and script for WooToolkit pages, plus WooCommerce's field assets on kit pages.
	 */
	public static function enqueue(): void {
		if ( ! self::is_wootoolkit_screen() ) {
			return;
		}
		wp_enqueue_style( self::HANDLE, plugins_url( 'assets/css/admin.css', WOOTOOLKIT_FILE ), array(), WOOTOOLKIT_VERSION );
		wp_enqueue_script( self::HANDLE, plugins_url( 'assets/js/admin.js', WOOTOOLKIT_FILE ), array(), WOOTOOLKIT_VERSION, true );
		wp_localize_script( self::HANDLE, 'wootoolkitAdmin', self::script_data() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides which assets load.
		if ( 'wootoolkit-kits' === self::current_page() && isset( $_GET['kit'] ) ) {
			// Every WooCommerce settings field type works on kit pages.
			wp_enqueue_style( 'woocommerce_admin_styles' );
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_script( 'jquery-tiptip' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_enqueue_media();
		}
	}

	/**
	 * The wootoolkitAdmin JS object.
	 *
	 * @return array<string, mixed>
	 */
	public static function script_data(): array {
		$data = array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'edition'    => self::edition(),
			'toggle'     => array(
				'action' => 'wootoolkit_toggle_kit',
				'nonce'  => wp_create_nonce( 'wootoolkit_toggle_kit' ),
			),
			'licenceUrl' => self::url( 'wootoolkit-licence' ),
			'i18n'       => array(
				'on'          => __( 'On', 'wootoolkit' ),
				'off'         => __( 'Off', 'wootoolkit' ),
				'waiting'     => __( 'On, waiting', 'wootoolkit' ),
				'saving'      => __( 'Saving…', 'wootoolkit' ),
				/* translators: %s: kit name */
				'switchedOn'  => __( '%s is on.', 'wootoolkit' ),
				/* translators: %s: kit name */
				'switchedOff' => __( '%s is off.', 'wootoolkit' ),
				/* translators: %s: names of the kits it needs */
				'needs'       => __( 'Not running: it needs %s.', 'wootoolkit' ),
				'failed'      => __( 'That did not save. Check your connection and try again.', 'wootoolkit' ),
				/* translators: 1: number of kits shown, 2: number of kits on */
				'shown'       => __( '%1$s kits shown · %2$s on', 'wootoolkit' ),
				'steps'       => array(
					__( 'Downloading from wootoolkit.com…', 'wootoolkit' ),
					__( 'Installing the plugin…', 'wootoolkit' ),
					__( 'Switching it on…', 'wootoolkit' ),
				),
				/* translators: 1: step number, 2: number of steps */
				'stepOf'      => __( '%1$s of %2$s', 'wootoolkit' ),
				'installing'  => __( 'Installing', 'wootoolkit' ),
				/* translators: %s: kit name */
				'installed'   => __( '%s is installed and switched on.', 'wootoolkit' ),
				'installFail' => __( 'The kit could not be installed. Nothing was changed.', 'wootoolkit' ),
				'tryAgain'    => __( 'Try again', 'wootoolkit' ),
				'openSet'     => __( 'Open its settings', 'wootoolkit' ),
				'settings'    => __( 'Settings', 'wootoolkit' ),
				'dismiss'     => __( 'Dismiss', 'wootoolkit' ),
				'copied'      => __( 'Copied', 'wootoolkit' ),
				'copyManual'  => __( 'Press Ctrl+C (or Cmd+C) to copy the selected text.', 'wootoolkit' ),
				'noGuides'    => __( 'No guide matches that search.', 'wootoolkit' ),
			),
		);
		/**
		 * Data for the WooToolkit admin script. WooToolkit Pro adds `install` => array( action, nonce ).
		 *
		 * @param array<string, mixed> $data Script data.
		 */
		$data = apply_filters( 'wootoolkit_admin_script_data', $data );
		return is_array( $data ) ? $data : array();
	}
}
