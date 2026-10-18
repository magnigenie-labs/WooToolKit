<?php
/**
 * Variation swatches kit.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\VariationSwatches;

use WooToolkit\Kit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Colour, image and button swatches instead of variation dropdowns on product pages.
 */
final class VariationSwatchesKit implements Kit {

	/**
	 * Stable kit id.
	 */
	public function id(): string {
		return 'variation-swatches';
	}

	/**
	 * Kit name.
	 */
	public function name(): string {
		return __( 'Variation swatches', 'wootoolkit' );
	}

	/**
	 * Kit description.
	 */
	public function description(): string {
		return __( 'Show colour, image and button swatches instead of variation dropdowns on product pages, with unavailable and out-of-stock options marked.', 'wootoolkit' );
	}

	/**
	 * A 2.0 kit.
	 */
	public function is_classic(): bool {
		return false;
	}

	/**
	 * Touches no orders; the swatches only drive WooCommerce's own variation form.
	 *
	 * @return array<string, bool>
	 */
	public function compat(): array {
		return array(
			'hpos'   => true,
			'blocks' => true,
		);
	}

	/**
	 * Settings section.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings(): array {
		return array(
			array(
				'id'    => 'wootoolkit_variation_swatches',
				'type'  => 'title',
				'title' => __( 'Variation swatches', 'wootoolkit' ),
				'desc'  => sprintf(
					/* translators: %s: link to Products > Attributes */
					__( 'Choose each attribute\'s swatch type (colour, image or buttons) under %s > Edit, then give its terms a colour or image under "Configure terms". Custom attributes typed on a product can be shown as buttons from the product\'s Attributes tab.', 'wootoolkit' ),
					'<a href="' . esc_url( admin_url( 'edit.php?post_type=product&page=product_attributes' ) ) . '">' . esc_html__( 'Products > Attributes', 'wootoolkit' ) . '</a>'
				),
			),
			array(
				'id'      => Swatches::OPT_DEFAULT_TYPE,
				'type'    => 'select',
				'title'   => __( 'Attributes without a swatch type', 'wootoolkit' ),
				'class'   => 'wc-enhanced-select',
				'options' => array(
					'select' => __( 'Keep the dropdown', 'wootoolkit' ),
					'label'  => __( 'Show buttons with the option names', 'wootoolkit' ),
				),
				'default' => 'select',
			),
			array(
				'id'                => Swatches::OPT_SIZE,
				'type'              => 'number',
				'title'             => __( 'Swatch size (px)', 'wootoolkit' ),
				'default'           => '32',
				'custom_attributes' => array(
					'min'  => '16',
					'max'  => '96',
					'step' => '1',
				),
			),
			array(
				'id'      => Swatches::OPT_SHAPE,
				'type'    => 'select',
				'title'   => __( 'Shape', 'wootoolkit' ),
				'options' => array(
					'round'  => __( 'Round', 'wootoolkit' ),
					'square' => __( 'Square (rounded corners)', 'wootoolkit' ),
				),
				'default' => 'round',
			),
			array(
				'id'      => Swatches::OPT_TOOLTIP,
				'type'    => 'checkbox',
				'title'   => __( 'Tooltips', 'wootoolkit' ),
				'desc'    => __( 'Show the option name above colour and image swatches on hover and keyboard focus', 'wootoolkit' ),
				'default' => 'yes',
			),
			array(
				'id'      => Swatches::OPT_OUT_OF_STOCK,
				'type'    => 'select',
				'title'   => __( 'Out-of-stock options', 'wootoolkit' ),
				'desc'    => __( 'Options that exist but are out of stock. Options that do not exist with the other choices are always greyed out; choosing one clears the other choices.', 'wootoolkit' ),
				'options' => array(
					'cross' => __( 'Crossed out', 'wootoolkit' ),
					'fade'  => __( 'Faded', 'wootoolkit' ),
					'hide'  => __( 'Hidden', 'wootoolkit' ),
				),
				'default' => 'cross',
			),
			array(
				'id'                => Swatches::OPT_MAX_TERMS,
				'type'              => 'number',
				'title'             => __( 'Keep the dropdown above', 'wootoolkit' ),
				'desc'              => __( 'Attributes with more options than this keep the dropdown. 0 = no limit.', 'wootoolkit' ),
				'desc_tip'          => false,
				'default'           => '0',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			),
			array(
				'id'   => 'wootoolkit_variation_swatches',
				'type' => 'sectionend',
			),
		);
	}

	/**
	 * Hooks the kit.
	 */
	public function boot(): void {
		Storefront::hooks();
		AttributeFields::hooks();
		ProductField::hooks();
	}
}
