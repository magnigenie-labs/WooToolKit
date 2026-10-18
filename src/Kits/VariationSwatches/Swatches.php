<?php
/**
 * Variation swatches: settings, swatch types, term data and the swatch markup.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\VariationSwatches;

defined( 'ABSPATH' ) || exit;

/**
 * Decides how each variation attribute is shown (dropdown, colour, image or buttons) and builds the
 * swatch group that sits next to WooCommerce's own <select>. The select stays in the form and stays
 * in charge: the swatches only set its value, so WooCommerce's variation script keeps working.
 *
 * Public methods here are the extension points the Pro kit builds on; see README.md for the filters.
 */
final class Swatches {

	const OPT_DEFAULT_TYPE    = 'wootoolkit_variation_swatches_default_type';
	const OPT_SIZE            = 'wootoolkit_variation_swatches_size';
	const OPT_SHAPE           = 'wootoolkit_variation_swatches_shape';
	const OPT_TOOLTIP         = 'wootoolkit_variation_swatches_tooltip';
	const OPT_OUT_OF_STOCK    = 'wootoolkit_variation_swatches_out_of_stock';
	const OPT_MAX_TERMS       = 'wootoolkit_variation_swatches_max_terms';
	const OPT_ATTRIBUTE_TYPES = 'wootoolkit_variation_swatches_attribute_types';

	/**
	 * Term meta: hex colour.
	 */
	const TERM_COLOR = 'wootoolkit_swatch_color';

	/**
	 * Term meta: attachment id.
	 */
	const TERM_IMAGE = 'wootoolkit_swatch_image';

	/**
	 * Product meta: how the product's custom (non-global) attributes are shown ('', 'select' or 'label').
	 */
	const PRODUCT_META = '_wootoolkit_swatches_custom';

	/**
	 * Per-request cache of stock maps: product id => attribute => value => in stock.
	 *
	 * @var array<int, array<string, array<string, bool>>>
	 */
	private static $stock = array();

	/**
	 * Swatch types: value => label. 'select' keeps WooCommerce's dropdown.
	 *
	 * @return array<string, string>
	 */
	public static function types(): array {
		/**
		 * Swatch types offered for attributes. Add a type here and render it with the
		 * wootoolkit_variation_swatches_item_html filter (unknown types render like 'label').
		 *
		 * @param array<string, string> $types Type => label.
		 */
		$types = (array) apply_filters(
			'wootoolkit_variation_swatches_types',
			array(
				'select' => __( 'Dropdown (WooCommerce default)', 'wootoolkit' ),
				'color'  => __( 'Colour', 'wootoolkit' ),
				'image'  => __( 'Image', 'wootoolkit' ),
				'label'  => __( 'Buttons (text)', 'wootoolkit' ),
			)
		);
		$types['select'] = $types['select'] ?? __( 'Dropdown (WooCommerce default)', 'wootoolkit' );
		return array_map( 'strval', $types );
	}

	/**
	 * A type value constrained to the known types.
	 *
	 * @param mixed  $type     Raw value.
	 * @param string $fallback Value when unknown.
	 */
	public static function sanitize_type( $type, string $fallback = 'select' ): string {
		$type = is_string( $type ) ? sanitize_key( $type ) : '';
		return array_key_exists( $type, self::types() ) ? $type : $fallback;
	}

	/**
	 * Type for attributes that have none of their own: 'select' or 'label'.
	 */
	public static function default_type(): string {
		return 'label' === get_option( self::OPT_DEFAULT_TYPE, 'select' ) ? 'label' : 'select';
	}

	/**
	 * Swatch size in pixels (16-96).
	 */
	public static function size(): int {
		$size = absint( get_option( self::OPT_SIZE, 32 ) );
		return $size ? max( 16, min( 96, $size ) ) : 32;
	}

	/**
	 * 'round' or 'square'.
	 */
	public static function shape(): string {
		return 'square' === get_option( self::OPT_SHAPE, 'round' ) ? 'square' : 'round';
	}

	/**
	 * Whether colour and image swatches show their name on hover and focus.
	 */
	public static function tooltips(): bool {
		return 'no' !== get_option( self::OPT_TOOLTIP, 'yes' );
	}

	/**
	 * How out-of-stock options look: 'cross', 'fade' or 'hide'.
	 */
	public static function out_of_stock_style(): string {
		$style = get_option( self::OPT_OUT_OF_STOCK, 'cross' );
		return in_array( $style, array( 'cross', 'fade', 'hide' ), true ) ? (string) $style : 'cross';
	}

	/**
	 * Attributes with more options than this keep the dropdown (0 = no limit).
	 */
	public static function max_terms(): int {
		return absint( get_option( self::OPT_MAX_TERMS, 0 ) );
	}

	/**
	 * Global attribute row (id and WooCommerce type) for a taxonomy, or null.
	 *
	 * @param string $taxonomy Attribute taxonomy (pa_*).
	 * @return array{id: int, type: string}|null
	 */
	public static function attribute_row( string $taxonomy ): ?array {
		if ( 0 !== strpos( $taxonomy, 'pa_' ) ) {
			return null;
		}
		foreach ( wc_get_attribute_taxonomies() as $row ) {
			if ( wc_attribute_taxonomy_name( $row->attribute_name ) === $taxonomy ) {
				return array(
					'id'   => (int) $row->attribute_id,
					'type' => (string) $row->attribute_type,
				);
			}
		}
		return null;
	}

	/**
	 * The swatch type saved on a global attribute ('' when none is saved).
	 *
	 * @param int $attribute_id Attribute id.
	 */
	public static function stored_type( int $attribute_id ): string {
		$types = get_option( self::OPT_ATTRIBUTE_TYPES, array() );
		return is_array( $types ) && isset( $types[ $attribute_id ] ) ? self::sanitize_type( $types[ $attribute_id ], '' ) : '';
	}

	/**
	 * Saves (or, with '', removes) a global attribute's swatch type.
	 *
	 * @param int    $attribute_id Attribute id.
	 * @param string $type         Type.
	 */
	public static function set_type( int $attribute_id, string $type ): void {
		$types = get_option( self::OPT_ATTRIBUTE_TYPES, array() );
		$types = is_array( $types ) ? $types : array();
		$type  = self::sanitize_type( $type, '' );
		if ( '' === $type ) {
			unset( $types[ $attribute_id ] );
		} else {
			$types[ $attribute_id ] = $type;
		}
		update_option( self::OPT_ATTRIBUTE_TYPES, $types, true );
	}

	/**
	 * The type of a global attribute before product overrides: its own, 'color' for WooCommerce's
	 * own "Color / image" attributes, else the store default.
	 *
	 * @param string $taxonomy Attribute taxonomy.
	 */
	public static function taxonomy_type( string $taxonomy ): string {
		$row = self::attribute_row( $taxonomy );
		if ( ! $row ) {
			return self::default_type();
		}
		$stored = self::stored_type( $row['id'] );
		if ( '' !== $stored ) {
			return $stored;
		}
		return 'wc-visual' === $row['type'] ? 'color' : self::default_type();
	}

	/**
	 * How a variation attribute is shown on a product.
	 *
	 * @param string           $attribute Attribute name (taxonomy for global attributes).
	 * @param \WC_Product|null $product   Product.
	 */
	public static function attribute_type( string $attribute, ?\WC_Product $product = null ): string {
		if ( taxonomy_exists( $attribute ) ) {
			$type = self::taxonomy_type( $attribute );
		} else {
			$own  = $product ? (string) $product->get_meta( self::PRODUCT_META ) : '';
			$type = in_array( $own, array( 'select', 'label' ), true ) ? $own : self::default_type();
		}

		/**
		 * How a variation attribute is shown: 'select' (WooCommerce's dropdown), 'color', 'image',
		 * 'label' or a type added with wootoolkit_variation_swatches_types.
		 *
		 * @param string           $type      Swatch type.
		 * @param string           $attribute Attribute name (pa_* taxonomy, or a custom attribute's name).
		 * @param \WC_Product|null $product   Product.
		 */
		return self::sanitize_type( apply_filters( 'wootoolkit_variation_swatches_attribute_type', $type, $attribute, $product ) );
	}

	/**
	 * Colour and image for one option. Reads the kit's term meta, falling back to the meta WooCommerce
	 * writes for its own "Color / image" attributes.
	 *
	 * @param string           $attribute Attribute name.
	 * @param string           $value     Option value (term slug, or the custom attribute value).
	 * @param \WP_Term|null    $term      Term, for global attributes.
	 * @param \WC_Product|null $product   Product.
	 * @return array{color: string, image_id: int, image_url: string}
	 */
	public static function term_data( string $attribute, string $value, ?\WP_Term $term = null, ?\WC_Product $product = null ): array {
		$color    = '';
		$image_id = 0;
		if ( $term ) {
			$color    = (string) sanitize_hex_color( (string) get_term_meta( $term->term_id, self::TERM_COLOR, true ) );
			$image_id = absint( get_term_meta( $term->term_id, self::TERM_IMAGE, true ) );
			if ( '' === $color ) {
				$color = (string) sanitize_hex_color( (string) get_term_meta( $term->term_id, 'color', true ) );
			}
			if ( ! $image_id ) {
				$image_id = absint( get_term_meta( $term->term_id, 'image', true ) );
			}
		}
		$data = array(
			'color'     => $color,
			'image_id'  => $image_id,
			'image_url' => '',
		);

		/**
		 * Colour and image of one swatch. Return image_id (an attachment) or image_url.
		 *
		 * @param array            $data      array( 'color' => hex, 'image_id' => int, 'image_url' => string ).
		 * @param \WP_Term|null    $term      Term (null for custom attributes).
		 * @param string           $attribute Attribute name.
		 * @param string           $value     Option value.
		 * @param \WC_Product|null $product   Product.
		 */
		$data = (array) apply_filters( 'wootoolkit_variation_swatches_term_data', $data, $term, $attribute, $value, $product );

		$out = array(
			'color'     => (string) sanitize_hex_color( isset( $data['color'] ) ? (string) $data['color'] : '' ),
			'image_id'  => isset( $data['image_id'] ) ? absint( $data['image_id'] ) : 0,
			'image_url' => isset( $data['image_url'] ) ? (string) $data['image_url'] : '',
		);
		if ( '' === $out['image_url'] && $out['image_id'] ) {
			/**
			 * Image size of image swatches.
			 *
			 * @param string $size Registered image size.
			 */
			$size             = (string) apply_filters( 'wootoolkit_variation_swatches_image_size', 'thumbnail' );
			$out['image_url'] = (string) wp_get_attachment_image_url( $out['image_id'], $size );
		}
		return $out;
	}

	/**
	 * The options of a variation attribute, in WooCommerce's order (as in its dropdown).
	 *
	 * @param \WC_Product $product   Product.
	 * @param string      $attribute Attribute name.
	 * @param array       $options   Values offered (empty: the product's own).
	 * @return array<int, array{value: string, label: string, term: \WP_Term|null}>
	 */
	public static function options( \WC_Product $product, string $attribute, array $options = array() ): array {
		if ( ! $options && $product instanceof \WC_Product_Variable ) {
			$attributes = $product->get_variation_attributes();
			$options    = isset( $attributes[ $attribute ] ) ? (array) $attributes[ $attribute ] : array();
		}
		$options = array_map( 'strval', $options );
		$out     = array();
		if ( taxonomy_exists( $attribute ) ) {
			$terms = wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) );
			foreach ( $terms as $term ) {
				if ( $term instanceof \WP_Term && in_array( $term->slug, $options, true ) ) {
					$out[] = array(
						'value' => $term->slug,
						'label' => (string) apply_filters( 'woocommerce_variation_option_name', $term->name, $term, $attribute, $product ), // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- WooCommerce's own filter, applied as its dropdown does.
						'term'  => $term,
					);
				}
			}
			return $out;
		}
		foreach ( $options as $option ) {
			$out[] = array(
				'value' => $option,
				'label' => (string) apply_filters( 'woocommerce_variation_option_name', $option, null, $attribute, $product ), // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- WooCommerce's own filter.
				'term'  => null,
			);
		}
		return $out;
	}

	/**
	 * Whether an option is the selected one, compared the way WooCommerce's dropdown does.
	 *
	 * @param string $selected  Selected value.
	 * @param string $value     Option value.
	 * @param bool   $taxonomy  Whether the attribute is global.
	 */
	private static function is_selected( string $selected, string $value, bool $taxonomy ): bool {
		if ( '' === $selected ) {
			return false;
		}
		if ( $taxonomy ) {
			return sanitize_title( $selected ) === $value;
		}
		return sanitize_title( $selected ) === $selected ? sanitize_title( $value ) === $selected : $value === $selected;
	}

	/**
	 * For each option value of an attribute, whether any purchasable variation with it is in stock.
	 * Values no variation uses are missing from the map. Empty for products with more variations
	 * than WooCommerce loads into the page (the same threshold its variation script uses).
	 *
	 * @param \WC_Product $product   Variable product.
	 * @param string      $attribute Attribute name.
	 * @param string[]    $values    All option values (used for variations set to "Any").
	 * @return array<string, bool>
	 */
	public static function stock_map( \WC_Product $product, string $attribute, array $values ): array {
		$product_id = $product->get_id();
		if ( isset( self::$stock[ $product_id ][ $attribute ] ) ) {
			return self::$stock[ $product_id ][ $attribute ];
		}
		$map = array();
		// phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment -- WooCommerce's own filter.
		if ( $product instanceof \WC_Product_Variable && count( $product->get_children() ) <= (int) apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product ) ) {
			$key = 'attribute_' . sanitize_title( $attribute );
			foreach ( $product->get_available_variations( 'objects' ) as $variation ) {
				if ( ! $variation instanceof \WC_Product_Variation ) {
					continue;
				}
				$attributes = $variation->get_variation_attributes();
				$value      = isset( $attributes[ $key ] ) ? (string) $attributes[ $key ] : '';
				$in_stock   = $variation->is_in_stock();
				foreach ( '' === $value ? $values : array( $value ) as $one ) {
					$map[ $one ] = ! empty( $map[ $one ] ) || $in_stock;
				}
			}
		}
		self::$stock[ $product_id ][ $attribute ] = $map;
		return $map;
	}

	/**
	 * Forgets cached stock maps (between tests and after stock changes in the same request).
	 */
	public static function flush_cache(): void {
		self::$stock = array();
	}

	/**
	 * The swatch group for one attribute, or '' when the attribute keeps the dropdown.
	 *
	 * @param array $args The arguments WooCommerce passes to wc_dropdown_variation_attribute_options():
	 *                    product, attribute, options, selected (others are ignored).
	 */
	public static function render_group( array $args ): string {
		$product   = $args['product'] ?? null;
		$attribute = isset( $args['attribute'] ) ? (string) $args['attribute'] : '';
		if ( ! $product instanceof \WC_Product || '' === $attribute ) {
			return '';
		}
		$type = self::attribute_type( $attribute, $product );
		if ( 'select' === $type ) {
			return '';
		}
		$options = self::options( $product, $attribute, isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array() );
		$max     = self::max_terms();
		if ( ! $options || ( $max > 0 && count( $options ) > $max ) ) {
			return '';
		}

		$taxonomy = taxonomy_exists( $attribute );
		$selected = isset( $args['selected'] ) && is_scalar( $args['selected'] ) ? (string) $args['selected'] : '';
		$stock    = self::stock_map( $product, $attribute, wp_list_pluck( $options, 'value' ) );
		$items    = array();
		$focus    = false;
		foreach ( $options as $option ) {
			$item = array_merge(
				self::term_data( $attribute, $option['value'], $option['term'], $product ),
				array(
					'value'        => $option['value'],
					'label'        => wp_strip_all_tags( $option['label'] ),
					'term'         => $option['term'],
					'selected'     => self::is_selected( $selected, $option['value'], $taxonomy ),
					'in_stock'     => ! isset( $stock[ $option['value'] ] ) || $stock[ $option['value'] ],
					'available'    => ! $stock || isset( $stock[ $option['value'] ] ),
					'tooltip'      => self::tooltips() && in_array( $type, array( 'color', 'image' ), true ),
					'tooltip_html' => '',
					'classes'      => array(),
				)
			);

			/**
			 * One swatch before it is rendered. Add 'classes', 'tooltip_html' (HTML shown on hover and
			 * focus, run through wp_kses_post) or change colour, image and label.
			 *
			 * @param array       $item      Swatch data.
			 * @param string      $attribute Attribute name.
			 * @param \WC_Product $product   Product.
			 * @param string      $type      Swatch type.
			 */
			$item    = (array) apply_filters( 'wootoolkit_variation_swatches_item', $item, $attribute, $product, $type );
			$focus   = $focus || ! empty( $item['selected'] );
			$items[] = $item;
		}
		if ( ! $focus ) {
			$items[0]['focusable'] = true;
		}

		$html = '';
		foreach ( $items as $item ) {
			$html .= self::item_html( $item, $type, $attribute, $product );
		}

		$classes = array(
			'wootoolkit-swatches',
			'wootoolkit-swatches--' . sanitize_html_class( $type ),
			'wootoolkit-swatches--' . self::shape(),
			'wootoolkit-swatches--oos-' . self::out_of_stock_style(),
		);
		return sprintf(
			'<div class="%1$s" role="radiogroup" aria-label="%2$s" data-attribute_name="%3$s" style="--wootoolkit-swatch-size:%4$dpx">%5$s</div>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( wp_strip_all_tags( wc_attribute_label( $attribute, $product ) ) ),
			esc_attr( 'attribute_' . sanitize_title( $attribute ) ),
			self::size(),
			$html
		);
	}

	/**
	 * One swatch button.
	 *
	 * @param array       $item      Swatch data (see render_group()).
	 * @param string      $type      Swatch type.
	 * @param string      $attribute Attribute name.
	 * @param \WC_Product $product   Product.
	 */
	public static function item_html( array $item, string $type, string $attribute, \WC_Product $product ): string {
		$label   = isset( $item['label'] ) ? (string) $item['label'] : '';
		$classes = array_merge( array( 'wootoolkit-swatch', 'wootoolkit-swatch--' . sanitize_html_class( $type ) ), array_map( 'sanitize_html_class', (array) ( $item['classes'] ?? array() ) ) );
		$aria    = $label;
		if ( ! empty( $item['selected'] ) ) {
			$classes[] = 'is-selected';
		}
		if ( empty( $item['in_stock'] ) ) {
			$classes[] = 'is-out-of-stock';
			/* translators: %s: option name, e.g. "Red" */
			$aria = sprintf( __( '%s (out of stock)', 'wootoolkit' ), $label );
		} elseif ( isset( $item['available'] ) && ! $item['available'] ) {
			$classes[] = 'is-unavailable';
			/* translators: %s: option name, e.g. "Red" */
			$aria = sprintf( __( '%s (not available)', 'wootoolkit' ), $label );
		}
		$tooltip_html = isset( $item['tooltip_html'] ) ? wp_kses_post( (string) $item['tooltip_html'] ) : '';
		$extra        = '';
		if ( '' !== $tooltip_html ) {
			$classes[]     = 'has-tooltip-html';
			$tooltip_html = '<span class="wootoolkit-swatch__tooltip" aria-hidden="true">' . $tooltip_html . '</span>';
		} elseif ( ! empty( $item['tooltip'] ) ) {
			$extra .= ' data-tooltip="' . esc_attr( $label ) . '"';
		}
		$focusable = ! empty( $item['selected'] ) || ! empty( $item['focusable'] );
		$inner     = self::item_inner( $item, $type );
		if ( in_array( $type, array( 'color', 'image' ), true ) && false !== strpos( $inner, 'wootoolkit-swatch__label' ) ) {
			$classes[] = 'has-label'; // No colour or image yet: shown as a text button.
		}

		$html = sprintf(
			'<button type="button" class="%1$s" role="radio" aria-checked="%2$s" aria-label="%3$s" data-label="%4$s" data-value="%5$s" tabindex="%6$s"%7$s>%8$s%9$s</button>',
			esc_attr( implode( ' ', array_unique( array_filter( $classes ) ) ) ),
			! empty( $item['selected'] ) ? 'true' : 'false',
			esc_attr( $aria ),
			esc_attr( $label ),
			esc_attr( isset( $item['value'] ) ? (string) $item['value'] : '' ),
			$focusable ? '0' : '-1',
			$extra,
			$inner,
			$tooltip_html
		);

		/**
		 * One swatch's HTML. Use it to render a type added with wootoolkit_variation_swatches_types.
		 * The button must keep its data-value and role="radio" for the script to drive the dropdown.
		 *
		 * @param string      $html      Button HTML.
		 * @param array       $item      Swatch data.
		 * @param string      $type      Swatch type.
		 * @param string      $attribute Attribute name.
		 * @param \WC_Product $product   Product.
		 */
		return (string) apply_filters( 'wootoolkit_variation_swatches_item_html', $html, $item, $type, $attribute, $product );
	}

	/**
	 * What is inside a swatch: a colour, an image or the option name. Colour and image swatches
	 * fall back to each other, then to the name, when a term has no colour or image yet.
	 *
	 * @param array  $item Swatch data.
	 * @param string $type Swatch type.
	 */
	public static function item_inner( array $item, string $type ): string {
		$label = isset( $item['label'] ) ? (string) $item['label'] : '';
		$color = isset( $item['color'] ) ? (string) sanitize_hex_color( (string) $item['color'] ) : '';
		$image = isset( $item['image_url'] ) ? esc_url( (string) $item['image_url'] ) : '';

		$image_html = '' !== $image ? sprintf( '<img class="wootoolkit-swatch__image" src="%s" alt="" loading="lazy" decoding="async" />', $image ) : '';
		$color_html = '' !== $color ? sprintf( '<span class="wootoolkit-swatch__fill" style="background-color:%s"></span>', esc_attr( $color ) ) : '';
		$label_html = '<span class="wootoolkit-swatch__label">' . esc_html( $label ) . '</span>';

		if ( 'image' === $type ) {
			$inner = $image_html ? $image_html : $color_html;
		} elseif ( 'color' === $type ) {
			$inner = $color_html ? $color_html : $image_html;
		} else {
			$inner = '';
		}
		return '' !== $inner ? $inner : $label_html;
	}
}
