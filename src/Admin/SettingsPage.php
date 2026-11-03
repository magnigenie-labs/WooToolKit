<?php
/**
 * WooToolkit > Settings.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Admin;

use WooToolkit\Install\Installer;
use WooToolkit\Kit\ClassicKit;
use WooToolkit\Kit\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Settings for WooToolkit itself: General, Who can manage, Classic kits, Privacy and data,
 * Import and export, Advanced. Each kit's own settings are on its page under Kits.
 */
final class SettingsPage {

	/**
	 * admin-post actions.
	 */
	const SAVE   = 'wootoolkit_save_settings';
	const EXPORT = 'wootoolkit_export_settings';
	const IMPORT = 'wootoolkit_import_settings';
	const RESET  = 'wootoolkit_reset_settings';

	/**
	 * WooToolkit's own settings on this page (kept by "Reset all kit settings", not exported when site-specific).
	 */
	const PLUGIN_OPTIONS = array(
		'wootoolkit_dashboard_widget',
		'wootoolkit_show_tips',
		'wootoolkit_attention_alerts',
		Admin::CAP_OPTION,
		'wootoolkit_delete_data',
		'wootoolkit_debug_log',
	);

	/**
	 * Largest settings file accepted, in bytes.
	 */
	const MAX_IMPORT = 2097152;

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
		add_action( 'admin_post_' . self::EXPORT, array( $this, 'handle_export' ) );
		add_action( 'admin_post_' . self::IMPORT, array( $this, 'handle_import' ) );
		add_action( 'admin_post_' . self::RESET, array( $this, 'handle_reset' ) );
	}

	/**
	 * Sections the current user can open: id => label.
	 *
	 * @return array<string, string>
	 */
	public static function sections(): array {
		$sections = array(
			'general'  => __( 'General', 'wootoolkit' ),
			'access'   => __( 'Who can manage', 'wootoolkit' ),
			'classic'  => __( 'Classic kits', 'wootoolkit' ),
			'privacy'  => __( 'Privacy and data', 'wootoolkit' ),
			'import'   => __( 'Import and export', 'wootoolkit' ),
			'advanced' => __( 'Advanced', 'wootoolkit' ),
		);
		if ( ! current_user_can( 'manage_options' ) ) {
			unset( $sections['access'], $sections['import'] );
		}
		return $sections;
	}

	/**
	 * Settings fields per section (WooCommerce settings API format).
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function fields(): array {
		$fields = array(
			'general'  => array(
				array(
					'id'    => 'wootoolkit_general',
					'type'  => 'title',
					'title' => __( 'General', 'wootoolkit' ),
				),
				array(
					'id'      => 'wootoolkit_dashboard_widget',
					'type'    => 'checkbox',
					'title'   => __( 'WordPress dashboard widget', 'wootoolkit' ),
					'desc'    => __( 'A small "From your kits" box on Dashboard › Home, for example quotes waiting for a price.', 'wootoolkit' ),
					'default' => 'yes',
				),
				array(
					'id'      => 'wootoolkit_show_tips',
					'type'    => 'checkbox',
					'title'   => __( 'Tips on kit pages', 'wootoolkit' ),
					'desc'    => __( 'Short hints next to settings. Turn off once you know your way around.', 'wootoolkit' ),
					'default' => 'yes',
				),
				array(
					'id'      => 'wootoolkit_attention_alerts',
					'type'    => 'checkbox',
					'title'   => __( '"Needs your attention" alerts', 'wootoolkit' ),
					'desc'    => __( 'Shown on the WooToolkit dashboard only, never across wp-admin.', 'wootoolkit' ),
					'default' => 'yes',
				),
				array(
					'id'   => 'wootoolkit_general',
					'type' => 'sectionend',
				),
			),
			'access'   => array(
				array(
					'id'    => 'wootoolkit_access',
					'type'  => 'title',
					'title' => __( 'Who can manage WooToolkit', 'wootoolkit' ),
					'desc'  => __( 'The licence and Import/export always need an administrator.', 'wootoolkit' ),
				),
				array(
					'id'      => Admin::CAP_OPTION,
					'type'    => 'radio',
					'title'   => __( 'Switch kits on or off and change their settings', 'wootoolkit' ),
					'options' => array(
						'manage_options'     => __( 'Administrators only', 'wootoolkit' ),
						'manage_woocommerce' => __( 'Administrators and shop managers', 'wootoolkit' ),
					),
					'default' => 'manage_woocommerce',
				),
				array(
					'id'   => 'wootoolkit_access',
					'type' => 'sectionend',
				),
			),
			'privacy'  => array(
				array(
					'id'    => 'wootoolkit_privacy',
					'type'  => 'title',
					'title' => __( 'Privacy and data', 'wootoolkit' ),
				),
				array(
					'id'       => 'wootoolkit_delete_data',
					'type'     => 'checkbox',
					'title'    => __( 'When WooToolkit is deleted', 'wootoolkit' ),
					'desc'     => __( 'Delete all WooToolkit data when the plugin is deleted', 'wootoolkit' ),
					'desc_tip' => __( 'Off by default, so reinstalling keeps your settings, wishlists and invoice numbers. Orders, including quotes, are never deleted.', 'wootoolkit' ),
					'default'  => 'no',
				),
				array(
					'id'   => 'wootoolkit_privacy',
					'type' => 'sectionend',
				),
			),
			'advanced' => array(
				array(
					'id'    => 'wootoolkit_advanced',
					'type'  => 'title',
					'title' => __( 'Advanced', 'wootoolkit' ),
				),
				array(
					'id'      => 'wootoolkit_debug_log',
					'type'    => 'checkbox',
					'title'   => __( 'Debug log', 'wootoolkit' ),
					'desc'    => __( 'Kits write details to WooCommerce › Status › Logs (source "wootoolkit"). Turn on when support asks.', 'wootoolkit' ),
					'default' => 'no',
				),
				array(
					'id'   => 'wootoolkit_advanced',
					'type' => 'sectionend',
				),
			),
		);
		/**
		 * Settings fields per section of WooToolkit > Settings (WooCommerce settings API format).
		 * WooToolkit Pro adds its beta updates checkbox to "advanced".
		 *
		 * @param array<string, array<int, array<string, mixed>>> $fields Section id => fields.
		 */
		$fields = apply_filters( 'wootoolkit_settings_fields', $fields );
		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Lines under "What WooToolkit sends outside your site".
	 *
	 * @return string[]
	 */
	public static function privacy_lines(): array {
		/**
		 * Lines for Settings > Privacy and data > "What WooToolkit sends outside your site".
		 *
		 * @param string[] $lines Plain-text lines.
		 */
		$lines = apply_filters( 'wootoolkit_privacy_lines', array( __( 'The free plugin: nothing.', 'wootoolkit' ) ) );
		return array_values( array_filter( array_map( 'strval', is_array( $lines ) ? $lines : array() ) ) );
	}

	/**
	 * Whether a name looks like it holds a key, secret, token, password or licence.
	 *
	 * @param string $name Option name or array key.
	 */
	private static function is_secret( string $name ): bool {
		return (bool) preg_match( '/(^|_|-)(api_?key|secret|token|password|licen[cs]e(_?key)?)(_|-|$)/i', $name );
	}

	/**
	 * Whether an option is part of a settings file.
	 *
	 * @param string $name Option name.
	 */
	public static function is_exportable( string $name ): bool {
		if ( 0 !== strpos( $name, 'wootoolkit_' ) || self::is_secret( $name ) ) {
			return false;
		}
		$never = array( Installer::VERSION_OPTION, Installer::UPGRADE_NOTICE_OPTION, Installer::LEGACY_CATALOG_OPTION, KitSettingsPage::SAVED_OPTION, Admin::CAP_OPTION, 'wootoolkit_delete_data' );
		if ( in_array( $name, $never, true ) || '_page_id' === substr( $name, -8 ) ) {
			return false;
		}
		// Licence data, invoice number counters and caches stay with the site.
		return ! preg_match( '/^wootoolkit_(pro_|pdf_invoices_counter_)|_(flushed|cache|lock)$/', $name );
	}

	/**
	 * Names of every WooToolkit option on this site.
	 *
	 * @return string[]
	 */
	private static function option_names(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- lists option names; values are read through get_option().
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name", $wpdb->esc_like( 'wootoolkit_' ) . '%' ) );
		return array_map( 'strval', is_array( $names ) ? $names : array() );
	}

	/**
	 * A value made safe for a settings file or an import: no objects, no secret-looking keys, and
	 * no unfiltered HTML unless the user may post it.
	 *
	 * @param mixed $value Value.
	 * @param int   $depth Nesting depth.
	 * @return mixed
	 */
	private static function clean_value( $value, int $depth = 0 ) {
		if ( is_array( $value ) ) {
			if ( $depth > 8 ) {
				return array();
			}
			$out = array();
			foreach ( $value as $key => $item ) {
				if ( is_string( $key ) && self::is_secret( $key ) ) {
					continue;
				}
				$item = self::clean_value( $item, $depth + 1 );
				if ( null !== $item ) {
					$out[ $key ] = $item;
				}
			}
			return $out;
		}
		if ( is_string( $value ) ) {
			return current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
		}
		return is_scalar( $value ) ? $value : null;
	}

	/**
	 * The settings file: kit choices and settings of this site.
	 *
	 * @return array<string, mixed>
	 */
	public static function export_data(): array {
		$options = array();
		foreach ( self::option_names() as $name ) {
			if ( self::is_exportable( $name ) ) {
				$value = self::clean_value( get_option( $name ) );
				if ( null !== $value ) {
					$options[ $name ] = $value;
				}
			}
		}
		return array(
			'plugin'   => 'wootoolkit',
			'format'   => 1,
			'version'  => WOOTOOLKIT_VERSION,
			'site'     => home_url( '/' ),
			'exported' => gmdate( 'c' ),
			'options'  => $options,
		);
	}

	/**
	 * Checks a decoded settings file. Options that a settings file may not set are left out.
	 *
	 * @param mixed $data Decoded JSON.
	 * @return array{options: array<string, mixed>, rejected: string[]}|\WP_Error
	 */
	public static function parse_import( $data ) {
		if ( ! is_array( $data ) || ! isset( $data['plugin'], $data['options'] ) || 'wootoolkit' !== $data['plugin'] || ! is_array( $data['options'] ) ) {
			return new \WP_Error( 'wootoolkit_import_invalid', __( 'That is not a WooToolkit settings file.', 'wootoolkit' ) );
		}
		$options  = array();
		$rejected = array();
		foreach ( $data['options'] as $name => $value ) {
			$name = (string) $name;
			if ( ! self::is_exportable( $name ) ) {
				$rejected[] = $name;
				continue;
			}
			if ( Registry::OPTION === $name ) {
				$kits = array();
				foreach ( is_array( $value ) ? $value : array() as $id => $state ) {
					$kits[ sanitize_key( (string) $id ) ] = 'yes' === $state ? 'yes' : 'no';
				}
				$value = $kits;
			}
			$value = self::clean_value( $value );
			if ( null === $value ) {
				$rejected[] = $name;
				continue;
			}
			$options[ $name ] = $value;
		}
		return array(
			'options'  => $options,
			'rejected' => $rejected,
		);
	}

	/**
	 * Saves imported options.
	 *
	 * @param array<string, mixed> $options Options from parse_import().
	 * @return int Number of options saved.
	 */
	public function apply_import( array $options ): int {
		$count = 0;
		foreach ( $options as $name => $value ) {
			if ( self::is_exportable( (string) $name ) ) {
				update_option( (string) $name, $value );
				++$count;
			}
		}
		$this->flush_kits();
		return $count;
	}

	/**
	 * Deletes every kit setting. Kits stay on or off; the version, licence, invoice counters, page
	 * ids and WooToolkit's own settings are kept.
	 *
	 * @return int Number of options deleted.
	 */
	public function reset_kit_settings(): int {
		$keep  = array_merge( array( Registry::OPTION, Installer::VERSION_OPTION, Installer::UPGRADE_NOTICE_OPTION, KitSettingsPage::SAVED_OPTION ), self::PLUGIN_OPTIONS );
		$count = 0;
		foreach ( self::option_names() as $name ) {
			if ( in_array( $name, $keep, true ) || '_page_id' === substr( $name, -8 ) || preg_match( '/^wootoolkit_(pro_|pdf_invoices_counter_)|licen[cs]e/', $name ) ) {
				continue;
			}
			if ( delete_option( $name ) ) {
				++$count;
			}
		}
		$this->flush_kits();
		return $count;
	}

	/**
	 * Lets every kit flush its caches after its settings changed outside its page.
	 */
	private function flush_kits(): void {
		foreach ( array_keys( $this->registry->all() ) as $id ) {
			do_action( 'woocommerce_update_options_wootoolkit_' . $id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- documented in KitSettingsPage::save().
		}
	}

	/**
	 * URL of a section, with an optional notice code.
	 *
	 * @param string $section Section id.
	 * @param string $notice  Notice code.
	 * @param int    $count   Number shown in the notice.
	 */
	public static function url( string $section, string $notice = '', int $count = 0 ): string {
		$args = array( 'section' => $section );
		if ( '' !== $notice ) {
			$args['wtk_notice'] = $notice;
		}
		if ( $count ) {
			$args['n'] = $count;
		}
		return Screen::url( 'wootoolkit-settings', $args );
	}

	/**
	 * Saves a section (admin-post).
	 */
	public function handle_save(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked just below.
		$section = isset( $_POST['section'] ) && is_string( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';
		check_admin_referer( 'wootoolkit_settings_' . $section );
		$fields = self::fields();
		$cap    = 'access' === $section ? 'manage_options' : Admin::capability();
		if ( ! current_user_can( $cap ) || ! isset( $fields[ $section ] ) || ! isset( self::sections()[ $section ] ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change these settings.', 'wootoolkit' ), 403 );
		}
		KitSettingsPage::load_wc_settings();
		\WC_Admin_Settings::save_fields( $fields[ $section ] );
		/**
		 * A section of WooToolkit > Settings was saved.
		 *
		 * @param string $section Section id.
		 */
		do_action( 'wootoolkit_settings_saved', $section );
		wp_safe_redirect( self::url( $section, 'saved' ) );
		exit;
	}

	/**
	 * Downloads the settings file (admin-post).
	 */
	public function handle_export(): void {
		check_admin_referer( self::EXPORT );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export settings.', 'wootoolkit' ), 403 );
		}
		$name = 'wootoolkit-settings-' . sanitize_title( (string) get_bloginfo( 'name' ) ) . '-' . gmdate( 'Y-m-d' ) . '.json';
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		echo wp_json_encode( self::export_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a JSON file download, not HTML.
		exit;
	}

	/**
	 * Key of the import waiting for confirmation.
	 */
	private static function pending_key(): string {
		return 'wootoolkit_import_' . get_current_user_id();
	}

	/**
	 * Import: preview an uploaded file, apply it after confirmation, or cancel (admin-post).
	 */
	public function handle_import(): void {
		check_admin_referer( self::IMPORT );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to import settings.', 'wootoolkit' ), 403 );
		}
		$step = isset( $_POST['step'] ) && is_string( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : 'preview';

		if ( 'apply' === $step ) {
			$pending = get_transient( self::pending_key() );
			delete_transient( self::pending_key() );
			$count = is_array( $pending ) ? $this->apply_import( $pending ) : 0;
			wp_safe_redirect( self::url( 'import', $count ? 'imported' : 'import_none', $count ) );
			exit;
		}
		if ( 'cancel' === $step ) {
			delete_transient( self::pending_key() );
			wp_safe_redirect( self::url( 'import', 'import_cancelled' ) );
			exit;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a temporary upload path, checked with is_uploaded_file().
		$tmp  = isset( $_FILES['wootoolkit_import']['tmp_name'] ) && is_string( $_FILES['wootoolkit_import']['tmp_name'] ) ? $_FILES['wootoolkit_import']['tmp_name'] : '';
		$size = isset( $_FILES['wootoolkit_import']['size'] ) ? (int) $_FILES['wootoolkit_import']['size'] : 0;
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) || $size <= 0 || $size > self::MAX_IMPORT ) {
			wp_safe_redirect( self::url( 'import', 'import_nofile' ) );
			exit;
		}
		$json   = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the uploaded file.
		$parsed = self::parse_import( json_decode( (string) $json, true ) );
		if ( is_wp_error( $parsed ) ) {
			wp_safe_redirect( self::url( 'import', 'import_invalid' ) );
			exit;
		}
		if ( ! $parsed['options'] ) {
			wp_safe_redirect( self::url( 'import', 'import_none' ) );
			exit;
		}
		set_transient( self::pending_key(), $parsed['options'], 30 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'preview', '1', self::url( 'import' ) ) );
		exit;
	}

	/**
	 * Resets every kit's settings (admin-post).
	 */
	public function handle_reset(): void {
		check_admin_referer( self::RESET );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to reset settings.', 'wootoolkit' ), 403 );
		}
		if ( ! isset( $_POST['confirm'] ) || 'yes' !== $_POST['confirm'] ) {
			wp_safe_redirect( self::url( 'advanced' ) );
			exit;
		}
		$this->reset_kit_settings();
		wp_safe_redirect( self::url( 'advanced', 'reset' ) );
		exit;
	}

	/**
	 * Prints the page.
	 */
	public function render(): void {
		if ( ! current_user_can( Admin::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'wootoolkit' ), 403 );
		}
		$sections = self::sections();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects the section.
		$current = isset( $_GET['section'] ) && is_string( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'general';
		$current = isset( $sections[ $current ] ) ? $current : 'general';

		Screen::open( 'wootoolkit-settings' );
		?>
		<div class="wtk-intro">
			<h1 class="wtk-h1"><?php esc_html_e( 'Settings', 'wootoolkit' ); ?></h1>
			<p class="wtk-lead"><?php esc_html_e( 'Settings for WooToolkit itself. Each kit\'s own settings are on its page under Kits.', 'wootoolkit' ); ?></p>
		</div>
		<div class="wtk-notices" data-wtk-notices><?php $this->print_notice(); ?></div>
		<div class="wtk-split">
			<nav class="wtk-card wtk-sidenav" aria-label="<?php esc_attr_e( 'Settings sections', 'wootoolkit' ); ?>">
				<?php foreach ( $sections as $id => $label ) : ?>
					<a class="wtk-sidenav__item<?php echo $id === $current ? ' is-current' : ''; ?>" href="<?php echo esc_url( self::url( $id ) ); ?>"<?php echo $id === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="wtk-split__main">
				<?php
				switch ( $current ) {
					case 'classic':
						$this->print_classic();
						break;
					case 'import':
						$this->print_import();
						break;
					default:
						$this->print_fields( $current );
						if ( 'privacy' === $current ) {
							$this->print_privacy();
						}
						if ( 'advanced' === $current && current_user_can( 'manage_options' ) ) {
							$this->print_reset();
						}
				}
				?>
			</div>
		</div>
		<?php
		Screen::close();
	}

	/**
	 * Notice from the last action.
	 */
	private function print_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only, fixed messages.
		$code  = isset( $_GET['wtk_notice'] ) && is_string( $_GET['wtk_notice'] ) ? sanitize_key( wp_unslash( $_GET['wtk_notice'] ) ) : '';
		$count = isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$messages = array(
			'saved'            => array( __( 'Settings saved.', 'wootoolkit' ), 'success' ),
			/* translators: %s: number of settings */
			'imported'         => array( sprintf( _n( 'Settings imported: %s setting was updated.', 'Settings imported: %s settings were updated.', $count, 'wootoolkit' ), number_format_i18n( $count ) ), 'success' ),
			'import_none'      => array( __( 'Nothing was imported: the file has no settings this site can use.', 'wootoolkit' ), 'warn' ),
			'import_invalid'   => array( __( 'Nothing was imported: that is not a WooToolkit settings file.', 'wootoolkit' ), 'error' ),
			'import_nofile'    => array( __( 'Choose a settings file first. You will see what changes before anything is saved.', 'wootoolkit' ), 'warn' ),
			'import_cancelled' => array( __( 'Import cancelled. Nothing was changed.', 'wootoolkit' ), 'success' ),
			'reset'            => array( __( 'All kit settings are back to their defaults.', 'wootoolkit' ), 'success' ),
		);
		if ( isset( $messages[ $code ] ) ) {
			Screen::notice( $messages[ $code ][0], $messages[ $code ][1] );
		}
	}

	/**
	 * A section's fields in one form.
	 *
	 * @param string $section Section id.
	 */
	private function print_fields( string $section ): void {
		$fields = self::fields();
		if ( empty( $fields[ $section ] ) ) {
			return;
		}
		KitSettingsPage::load_wc_settings();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wtk-settings-form" data-wtk-form>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE ); ?>">
			<input type="hidden" name="section" value="<?php echo esc_attr( $section ); ?>">
			<?php wp_nonce_field( 'wootoolkit_settings_' . $section ); ?>
			<section class="wtk-card wtk-fields">
				<?php \WC_Admin_Settings::output_fields( $fields[ $section ] ); ?>
			</section>
			<div class="wtk-savebar wtk-savebar--plain">
				<button type="submit" class="wtk-button wtk-button--large" name="save" value="1"><?php esc_html_e( 'Save changes', 'wootoolkit' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Classic kits: on/off and their settings links.
	 */
	private function print_classic(): void {
		$classic = array_filter(
			$this->registry->all(),
			static function ( $kit ) {
				return $kit->is_classic();
			}
		);
		?>
		<section class="wtk-card wtk-section" aria-labelledby="wtk-classic-title">
			<h2 class="wtk-h2" id="wtk-classic-title"><?php esc_html_e( 'Classic kits', 'wootoolkit' ); ?></h2>
			<p class="wtk-muted">
				<?php esc_html_e( 'The kits from WooToolkit 1.x keep working, with bug fixes only, and only with the classic cart and checkout.', 'wootoolkit' ); ?>
				<a href="<?php echo esc_url( Screen::url( 'wootoolkit-kits' ) . '#wtk-classic' ); ?>"><?php esc_html_e( 'Switch them on or off on the Kits page.', 'wootoolkit' ); ?></a>
			</p>
			<ul class="wtk-list">
				<?php foreach ( $classic as $id => $kit ) : ?>
					<?php
					$on  = $this->registry->is_active( (string) $id );
					$url = $kit instanceof ClassicKit && $on ? KitsPage::classic_settings_url( $kit, true ) : '';
					?>
					<li class="wtk-list__row">
						<span class="wtk-list__name"><?php echo esc_html( $kit->name() ); ?></span>
						<span class="wtk-pill wtk-pill--<?php echo $on ? 'mint' : 'plain'; ?>"><?php echo esc_html( $on ? __( 'On', 'wootoolkit' ) : __( 'Off', 'wootoolkit' ) ); ?></span>
						<?php if ( '' !== $url ) : ?>
							<a class="wtk-list__link" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Settings', 'wootoolkit' ); ?><span class="screen-reader-text"> <?php echo esc_html( $kit->name() ); ?></span></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}

	/**
	 * "What WooToolkit sends outside your site".
	 */
	private function print_privacy(): void {
		?>
		<section class="wtk-card wtk-section wtk-section--soft" aria-labelledby="wtk-sends-title">
			<h2 class="wtk-h3" id="wtk-sends-title"><?php esc_html_e( 'What WooToolkit sends outside your site', 'wootoolkit' ); ?></h2>
			<ul class="wtk-plain-list">
				<?php foreach ( self::privacy_lines() as $line ) : ?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
				<li><?php esc_html_e( 'Wishlists and quotes are included in Tools › Export Personal Data and Erase Personal Data.', 'wootoolkit' ); ?></li>
			</ul>
		</section>
		<?php
	}

	/**
	 * Import and export, with the preview of a pending import.
	 */
	private function print_import(): void {
		$pending = get_transient( self::pending_key() );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$preview = isset( $_GET['preview'] ) && is_array( $pending ) && $pending;
		?>
		<section class="wtk-card wtk-section" aria-labelledby="wtk-import-title">
			<h2 class="wtk-h2" id="wtk-import-title"><?php esc_html_e( 'Import and export', 'wootoolkit' ); ?></h2>
			<p class="wtk-muted"><?php esc_html_e( 'Copy your kit choices and settings to another store, for example from staging to live. Orders, customers, invoice numbers and the licence are not included.', 'wootoolkit' ); ?></p>

			<?php if ( $preview ) : ?>
				<div class="wtk-preview">
					<h3 class="wtk-h3"><?php esc_html_e( 'Check what will change', 'wootoolkit' ); ?></h3>
					<div class="wtk-table-wrap">
						<table class="wtk-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Setting', 'wootoolkit' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Now', 'wootoolkit' ); ?></th>
									<th scope="col"><?php esc_html_e( 'After import', 'wootoolkit' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $pending as $name => $value ) : ?>
									<?php
									$now     = get_option( (string) $name, null );
									$changed = maybe_serialize( $now ) !== maybe_serialize( $value );
									?>
									<tr<?php echo $changed ? ' class="is-changed"' : ''; ?>>
										<th scope="row"><code><?php echo esc_html( (string) $name ); ?></code></th>
										<td><?php echo esc_html( null === $now ? __( '(not set)', 'wootoolkit' ) : self::summary( $now ) ); ?></td>
										<td><?php echo esc_html( self::summary( $value ) ); ?><?php echo $changed ? '' : ' <span class="wtk-muted">' . esc_html__( '(no change)', 'wootoolkit' ) . '</span>'; ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wtk-actions">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT ); ?>">
						<?php wp_nonce_field( self::IMPORT ); ?>
						<button type="submit" class="wtk-button" name="step" value="apply"><?php esc_html_e( 'Import these settings', 'wootoolkit' ); ?></button>
						<button type="submit" class="wtk-button wtk-button--ghost" name="step" value="cancel"><?php esc_html_e( 'Cancel', 'wootoolkit' ); ?></button>
					</form>
				</div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wtk-actions">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT ); ?>">
					<?php wp_nonce_field( self::EXPORT ); ?>
					<button type="submit" class="wtk-button"><?php esc_html_e( 'Download settings file', 'wootoolkit' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="wtk-actions wtk-import">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT ); ?>">
					<input type="hidden" name="step" value="preview">
					<?php wp_nonce_field( self::IMPORT ); ?>
					<label class="wtk-label" for="wtk-import-file"><?php esc_html_e( 'Import a settings file', 'wootoolkit' ); ?></label>
					<input type="file" id="wtk-import-file" name="wootoolkit_import" accept=".json,application/json" required>
					<button type="submit" class="wtk-button wtk-button--ghost"><?php esc_html_e( 'Import', 'wootoolkit' ); ?></button>
				</form>
				<p class="wtk-muted"><?php esc_html_e( 'Importing shows you what will change before anything is saved.', 'wootoolkit' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Short text for a value in the import preview.
	 *
	 * @param mixed $value Value.
	 */
	private static function summary( $value ): string {
		$text = is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$text = wp_strip_all_tags( $text );
		return strlen( $text ) > 120 ? substr( $text, 0, 117 ) . '…' : $text;
	}

	/**
	 * "Reset all kit settings" with its confirmation dialog.
	 */
	private function print_reset(): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wtk-card wtk-section wtk-reset">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::RESET ); ?>">
			<?php wp_nonce_field( self::RESET ); ?>
			<div class="wtk-reset__text">
				<strong><?php esc_html_e( 'Reset all kit settings', 'wootoolkit' ); ?></strong>
				<span class="wtk-muted"><?php esc_html_e( 'Every kit goes back to its defaults. Kits stay on or off as they are.', 'wootoolkit' ); ?></span>
			</div>
			<button type="button" class="wtk-button wtk-button--danger-ghost" data-wtk-open="reset"><?php esc_html_e( 'Reset settings', 'wootoolkit' ); ?></button>
			<div class="wtk-modal" data-wtk-modal="reset" hidden>
				<div class="wtk-modal__backdrop" data-wtk-close></div>
				<div class="wtk-dialog wtk-dialog--small" role="alertdialog" aria-modal="true" aria-labelledby="wtk-reset-title" aria-describedby="wtk-reset-desc" tabindex="-1">
					<h2 class="wtk-dialog__title" id="wtk-reset-title"><?php esc_html_e( 'Reset all kit settings?', 'wootoolkit' ); ?></h2>
					<p id="wtk-reset-desc"><?php esc_html_e( 'Every kit\'s settings go back to their defaults. This can\'t be undone, so download a settings file first if you might want them back.', 'wootoolkit' ); ?></p>
					<div class="wtk-actions wtk-actions--end">
						<button type="button" class="wtk-button wtk-button--ghost" data-wtk-close><?php esc_html_e( 'Cancel', 'wootoolkit' ); ?></button>
						<button type="submit" class="wtk-button wtk-button--danger" name="confirm" value="yes"><?php esc_html_e( 'Reset settings', 'wootoolkit' ); ?></button>
					</div>
				</div>
			</div>
		</form>
		<?php
	}
}
