<?php
/**
 * WooCommerce > Settings > WooToolkit.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Admin;

use WooToolkit\Install\Installer;
use WooToolkit\Kit\ClassicKit;
use WooToolkit\Kit\Kit;
use WooToolkit\Kit\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * The WooToolkit settings tab. The default section switches kits on and off; each active kit
 * with settings gets its own section. WooCommerce handles the nonce, capability and saving.
 */
class SettingsPage extends \WC_Settings_Page {

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
		$this->id       = 'wootoolkit';
		$this->label    = __( 'WooToolkit', 'wootoolkit' );
		$this->registry = $registry;
		parent::__construct();
	}

	/**
	 * Sections: "Kits", then one per active kit that has settings.
	 *
	 * @return array<string, string>
	 */
	protected function get_own_sections() {
		$sections = array( '' => __( 'Kits', 'wootoolkit' ) );
		foreach ( $this->registry->active() as $id => $kit ) {
			if ( ! empty( $kit->settings() ) ) {
				$sections[ $id ] = $kit->name();
			}
		}
		return $sections;
	}

	/**
	 * The kit list: one checkbox per kit.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_default_section() {
		$fields = array(
			array(
				'id'    => 'wootoolkit_kits',
				'type'  => 'title',
				'title' => __( 'Kits', 'wootoolkit' ),
				'desc'  => __( 'Switch on the kits your store needs. Each kit is a separate feature; kits you leave off load no code.', 'wootoolkit' ),
			),
		);

		foreach ( $this->registry->all() as $id => $kit ) {
			$fields[] = array(
				'id'      => Registry::OPTION . '[' . $id . ']',
				'type'    => 'checkbox',
				'title'   => $kit->name(),
				'desc'    => $this->describe( $kit ),
				'default' => 'no',
			);
		}

		$fields[] = array(
			'id'   => 'wootoolkit_kits',
			'type' => 'sectionend',
		);

		return $fields;
	}

	/**
	 * Settings for a kit's own section.
	 *
	 * @param string $section_id Section (kit) id.
	 * @return array<int, array<string, mixed>>
	 */
	protected function get_settings_for_section_core( $section_id ) {
		$kit = $this->registry->get( (string) $section_id );
		return ( $kit && $this->registry->is_active( $kit->id() ) ) ? $kit->settings() : array();
	}

	/**
	 * Outputs the tab, and clears the 1.x upgrade notice once the owner has seen it.
	 */
	public function output() {
		delete_option( Installer::UPGRADE_NOTICE_OPTION );
		parent::output();
	}

	/**
	 * Checkbox label: description, compatibility badges and, for active Classic kits, the settings link.
	 *
	 * @param Kit $kit Kit.
	 */
	private function describe( Kit $kit ): string {
		$parts  = array();
		$compat = $kit->compat();

		if ( '' !== $kit->description() ) {
			$parts[] = esc_html( $kit->description() );
		}
		if ( $kit->is_classic() ) {
			$parts[] = '<strong>' . esc_html__( 'Classic kit', 'wootoolkit' ) . '</strong>';
		}
		if ( empty( $compat['blocks'] ) ) {
			$parts[] = esc_html__( 'Classic cart and checkout only (not the blocks)', 'wootoolkit' );
		}
		if ( empty( $compat['hpos'] ) ) {
			$parts[] = esc_html__( 'Not compatible with HPOS order storage', 'wootoolkit' );
		}

		if ( $kit instanceof ClassicKit && $this->registry->is_active( $kit->id() ) && '' !== $kit->legacy_slug() ) {
			// 1.x kits add their "Settings" link through this filter.
			$links = (array) apply_filters( 'kit_action_links_' . $kit->legacy_slug(), array(), $kit->legacy_slug() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- 1.x hook name, kept for the Classic kits.
			if ( $links ) {
				$parts[] = implode( ' | ', array_map( 'wp_kses_post', $links ) );
			}
		}

		return implode( ' &middot; ', $parts );
	}
}
