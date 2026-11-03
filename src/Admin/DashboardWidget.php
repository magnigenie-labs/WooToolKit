<?php
/**
 * The "From your kits" widget on the WordPress dashboard.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Admin;

use WooToolkit\Kit\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Lists `wootoolkit_dashboard_activity` on Dashboard > Home, while Settings > General allows it.
 */
final class DashboardWidget {

	/**
	 * Widget id.
	 */
	const ID = 'wootoolkit_activity';

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
		add_action( 'wp_dashboard_setup', array( $this, 'register' ) );
	}

	/**
	 * Adds the widget for users who manage WooToolkit, when the setting is on and a kit is on.
	 */
	public function register(): void {
		if ( 'no' === get_option( 'wootoolkit_dashboard_widget', 'yes' ) || ! current_user_can( Admin::capability() ) || ! $this->registry->active_ids() ) {
			return;
		}
		wp_add_dashboard_widget( self::ID, __( 'From your kits', 'wootoolkit' ), array( $this, 'render' ) );
	}

	/**
	 * Prints the widget.
	 */
	public function render(): void {
		$items = DashboardPage::activity();
		if ( ! $items ) {
			echo '<p>' . esc_html__( 'Nothing to report yet. A kit adds a line here when it has something for you.', 'wootoolkit' ) . '</p>';
		} else {
			echo '<ul>';
			foreach ( $items as $item ) {
				echo '<li><strong>' . esc_html( $item['kit'] ) . ':</strong> ' . esc_html( $item['text'] );
				if ( '' !== $item['url'] ) {
					echo ' <a href="' . esc_url( $item['url'] ) . '">' . esc_html( '' !== $item['link_label'] ? $item['link_label'] : __( 'Open', 'wootoolkit' ) ) . '</a>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=wootoolkit' ) ) . '">' . esc_html__( 'Open the WooToolkit dashboard', 'wootoolkit' ) . '</a></p>';
	}
}
