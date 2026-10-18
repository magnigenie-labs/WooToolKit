<?php
/**
 * Variation swatches kit.
 *
 * @package WooToolkit
 */

use WooToolkit\Kits\VariationSwatches\AttributeFields;
use WooToolkit\Kits\VariationSwatches\ProductField;
use WooToolkit\Kits\VariationSwatches\Storefront;
use WooToolkit\Kits\VariationSwatches\Swatches;

/**
 * Swatches on the variations form, the attribute and term settings, and their sanitising.
 */
class Test_Variation_Swatches extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		foreach ( array( Swatches::OPT_DEFAULT_TYPE, Swatches::OPT_SIZE, Swatches::OPT_SHAPE, Swatches::OPT_TOOLTIP, Swatches::OPT_OUT_OF_STOCK, Swatches::OPT_MAX_TERMS, Swatches::OPT_ATTRIBUTE_TYPES ) as $option ) {
			delete_option( $option );
		}
		Swatches::flush_cache();
		wp_set_current_user( 0 );
	}

	public function tear_down() {
		$GLOBALS['current_screen'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$_POST                     = array();
		$_REQUEST                  = array();
		Swatches::flush_cache();
		parent::tear_down();
	}

	/**
	 * A global attribute with terms. Returns [taxonomy, attribute id, slug => term id].
	 */
	private function attribute( array $names, string $type = '' ) {
		$slug     = 'wtk' . strtolower( wp_generate_password( 6, false ) );
		$id       = wc_create_attribute(
			array(
				'name' => 'Colour',
				'slug' => $slug,
			)
		);
		$taxonomy = wc_attribute_taxonomy_name( $slug );
		register_taxonomy(
			$taxonomy,
			array( 'product' ),
			array(
				'hierarchical' => false,
				'capabilities' => array(
					'manage_terms' => 'manage_product_terms',
					'edit_terms'   => 'edit_product_terms',
					'delete_terms' => 'delete_product_terms',
					'assign_terms' => 'assign_product_terms',
				),
			)
		);
		// WooCommerce fills this global when it registers attribute taxonomies on init.
		foreach ( wc_get_attribute_taxonomies() as $row ) {
			if ( (int) $row->attribute_id === (int) $id ) {
				$GLOBALS['wc_product_attributes'][ $taxonomy ] = $row; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
		$terms = array();
		foreach ( $names as $name ) {
			$term                             = wp_insert_term( $name, $taxonomy );
			$terms[ sanitize_title( $name ) ] = (int) $term['term_id'];
		}
		if ( '' !== $type ) {
			Swatches::set_type( (int) $id, $type );
		}
		return array( $taxonomy, (int) $id, $terms );
	}

	/**
	 * A variable product with one variation per slug => stock status.
	 */
	private function variable_product( string $taxonomy, int $attribute_id, array $terms, array $in_stock, array $out_of_stock = array() ) {
		$attribute = new WC_Product_Attribute();
		$attribute->set_id( $attribute_id );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( array_values( $terms ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$product = new WC_Product_Variable();
		$product->set_name( 'Swatch tee' );
		$product->set_status( 'publish' );
		$product->set_attributes( array( $attribute ) );
		$product_id = $product->save();

		$variations = array_merge( array_fill_keys( $in_stock, 'instock' ), array_fill_keys( $out_of_stock, 'outofstock' ) );
		foreach ( $variations as $slug => $stock ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $product_id );
			$variation->set_attributes( array( $taxonomy => $slug ) );
			$variation->set_regular_price( '10' );
			$variation->set_stock_status( $stock );
			$variation->save();
		}
		WC_Product_Variable::sync( $product_id );
		return wc_get_product( $product_id );
	}

	private function dropdown( $product, string $attribute, array $args = array() ): string {
		Swatches::flush_cache();
		ob_start();
		wc_dropdown_variation_attribute_options(
			array_merge(
				array(
					'product'   => $product,
					'attribute' => $attribute,
				),
				$args
			)
		);
		return (string) ob_get_clean();
	}

	private function option_values( string $html ): array {
		preg_match_all( '/<option value="([^"]*)"/', $html, $matches );
		return $matches[1];
	}

	public function test_the_kit_is_registered_active_and_hooked_with_safe_defaults() {
		$registry = WooToolkit\Plugin::instance()->registry();
		$this->assertTrue( $registry->is_active( 'variation-swatches' ) );
		$this->assertNotFalse( has_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( Storefront::class, 'filter_dropdown' ) ) );

		$this->assertSame( 'select', Swatches::default_type() );
		$this->assertSame( 32, Swatches::size() );
		$this->assertSame( 'round', Swatches::shape() );
		$this->assertTrue( Swatches::tooltips() );
		$this->assertSame( 'cross', Swatches::out_of_stock_style() );
		$this->assertSame( 0, Swatches::max_terms() );
		$this->assertSame( array( 'select', 'color', 'image', 'label' ), array_keys( Swatches::types() ) );

		update_option( Swatches::OPT_SIZE, '500' );
		update_option( Swatches::OPT_OUT_OF_STOCK, 'explode' );
		$this->assertSame( 96, Swatches::size() );
		$this->assertSame( 'cross', Swatches::out_of_stock_style() );

		foreach ( $registry->get( 'variation-swatches' )->settings() as $field ) {
			$this->assertStringStartsWith( 'wootoolkit_variation_swatches', $field['id'] );
		}
	}

	public function test_attributes_without_a_swatch_type_keep_the_dropdown_untouched() {
		list( $taxonomy, $id, $terms ) = $this->attribute( array( 'Red', 'Blue' ) );
		$product                       = $this->variable_product( $taxonomy, $id, $terms, array( 'red', 'blue' ) );

		remove_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( Storefront::class, 'filter_dropdown' ), 20 );
		$plain = $this->dropdown( $product, $taxonomy );
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( Storefront::class, 'filter_dropdown' ), 20, 2 );

		$this->assertSame( $plain, $this->dropdown( $product, $taxonomy ) );
		Swatches::set_type( $id, 'select' );
		$this->assertSame( $plain, $this->dropdown( $product, $taxonomy ) );
		$this->assertStringNotContainsString( 'wootoolkit-swatch', $plain );
	}

	public function test_colour_swatches_replace_the_dropdown_and_keep_the_select() {
		list( $taxonomy, $id, $terms ) = $this->attribute( array( 'Red', 'Blue' ), 'color' );
		update_term_meta( $terms['red'], Swatches::TERM_COLOR, '#ff0000' );
		update_term_meta( $terms['blue'], Swatches::TERM_COLOR, '#0000ff' );
		$product = $this->variable_product( $taxonomy, $id, $terms, array( 'red', 'blue' ) );

		$html = $this->dropdown( $product, $taxonomy );

		$this->assertStringContainsString( '<div class="wootoolkit-swatches-wrap">', $html );
		$this->assertMatchesRegularExpression( '/<select id="' . $taxonomy . '" class="wootoolkit-swatches-select [^"]*" name="attribute_' . $taxonomy . '"/', $html );
		$this->assertSame( array( '', 'blue', 'red' ), $this->option_values( $html ), 'The select keeps every option, in the attribute\'s order.' );
		$this->assertLessThan( strpos( $html, 'data-value="red"' ), strpos( $html, 'data-value="blue"' ), 'Swatches follow the same order.' );
		$this->assertStringContainsString( 'role="radiogroup" aria-label="Colour" data-attribute_name="attribute_' . $taxonomy . '"', $html );
		$this->assertSame( 2, substr_count( $html, 'role="radio"' ) );
		$this->assertStringContainsString( 'aria-label="Blue" data-label="Blue" data-value="blue" tabindex="0" data-tooltip="Blue"', $html, 'Nothing chosen: the first swatch takes the focus.' );
		$this->assertStringContainsString( 'aria-label="Red" data-label="Red" data-value="red" tabindex="-1" data-tooltip="Red"', $html );
		$this->assertStringContainsString( 'background-color:#ff0000', $html );
		$this->assertStringContainsString( 'wootoolkit-swatches--color wootoolkit-swatches--round wootoolkit-swatches--oos-cross', $html );
		$this->assertTrue( wp_script_is( Storefront::HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_style_is( Storefront::HANDLE, 'enqueued' ) );
	}

	public function test_image_swatches_use_the_term_image_and_fall_back_to_the_name() {
		list( $taxonomy, $id, $terms ) = $this->attribute( array( 'Oak', 'Walnut' ), 'image' );
		$image                         = self::factory()->attachment->create_object( 'oak-swatch.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
		update_term_meta( $terms['oak'], Swatches::TERM_IMAGE, $image );
		$product = $this->variable_product( $taxonomy, $id, $terms, array( 'oak', 'walnut' ) );

		$html = $this->dropdown( $product, $taxonomy );

		$this->assertMatchesRegularExpression( '/data-value="oak"[^>]*><img class="wootoolkit-swatch__image" src="[^"]*oak-swatch\.jpg" alt=""/', $html );
		$this->assertMatchesRegularExpression( '/class="wootoolkit-swatch wootoolkit-swatch--image has-label"[^>]*data-value="walnut"[^>]*><span class="wootoolkit-swatch__label">Walnut<\/span>/', $html, 'A term without an image is a text button.' );
	}

	public function test_label_buttons_mark_the_default_selection() {
		list( $taxonomy, $id, $terms ) = $this->attribute( array( 'Small', 'Large' ), 'label' );
		update_option( Swatches::OPT_SHAPE, 'square' );
		update_option( Swatches::OPT_SIZE, '40' );
		$product = $this->variable_product( $taxonomy, $id, $terms, array( 'small', 'large' ) );
		$product->set_default_attributes( array( $taxonomy => 'large' ) );
		$product->save();

		$html = $this->dropdown( wc_get_product( $product->get_id() ), $taxonomy );

		$this->assertStringContainsString( 'wootoolkit-swatches--square', $html );
		$this->assertStringContainsString( '--wootoolkit-swatch-size:40px', $html );
		$this->assertMatchesRegularExpression( '/class="wootoolkit-swatch wootoolkit-swatch--label is-selected" role="radio" aria-checked="true" aria-label="Large"[^>]*tabindex="0"><span class="wootoolkit-swatch__label">Large<\/span>/', $html );
		$this->assertMatchesRegularExpression( '/aria-checked="false" aria-label="Small"[^>]*tabindex="-1"/', $html );
		$this->assertStringNotContainsString( 'data-tooltip', $html, 'Text buttons need no tooltip.' );
		$this->assertMatchesRegularExpression( '/<option value="large"\s+selected=\'selected\'/', $html );
	}

	public function test_custom_attributes_follow_the_product_setting() {
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( 'Fit' );
		$attribute->set_options( array( 'Slim', 'Regular' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		$product = new WC_Product_Variable();
		$product->set_name( 'Custom attribute tee' );
		$product->set_attributes( array( $attribute ) );
		$product->save();

		$this->assertStringNotContainsString( 'wootoolkit-swatch', $this->dropdown( $product, 'Fit', array( 'options' => array( 'Slim', 'Regular' ) ) ) );

		$product->update_meta_data( Swatches::PRODUCT_META, 'label' );
		$product->save();
		$html = $this->dropdown( $product, 'Fit', array( 'options' => array( 'Slim', 'Regular' ) ) );
		$this->assertStringContainsString( 'data-value="Slim"', $html );
		$this->assertStringContainsString( 'name="attribute_fit"', $html );

		update_option( Swatches::OPT_DEFAULT_TYPE, 'label' );
		$product->update_meta_data( Swatches::PRODUCT_META, 'select' );
		$product->save();
		$this->assertStringNotContainsString( 'wootoolkit-swatch', $this->dropdown( $product, 'Fit', array( 'options' => array( 'Slim', 'Regular' ) ) ), 'The product can keep the dropdown when the store default is buttons.' );
	}

	public function test_the_product_setting_saves_only_known_values() {
		$product = new WC_Product_Variable();
		$product->save();
		$_POST[ Swatches::PRODUCT_META ] = 'label';
		ProductField::save( $product );
		$this->assertSame( 'label', $product->get_meta( Swatches::PRODUCT_META ) );
		$_POST[ Swatches::PRODUCT_META ] = '<b>color</b>';
		ProductField::save( $product );
		$this->assertSame( '', $product->get_meta( Swatches::PRODUCT_META ) );
	}

	public function test_attributes_with_more_options_than_the_limit_keep_the_dropdown() {
		list( $taxonomy, $id, $terms ) = $this->attribute( array( 'One', 'Two', 'Three' ), 'label' );
		$product                       = $this->variable_product( $taxonomy, $id, $terms, array( 'one', 'two', 'three' ) );

		update_option( Swatches::OPT_MAX_TERMS, '2' );
		$this->assertStringNotContainsString( 'wootoolkit-swatch', $this->dropdown( $product, $taxonomy ) );
		update_option( Swatches::OPT_MAX_TERMS, '3' );
		$this->assertStringContainsString( 'wootoolkit-swatch', $this->dropdown( $product, $taxonomy ) );
	}

	public function test_out_of_stock_and_unavailable_options_are_marked() {
		list( $taxonomy, $id, $terms ) = $this->attribute( array( 'Red', 'Blue', 'Green' ), 'color' );
		$product                       = $this->variable_product( $taxonomy, $id, $terms, array( 'red' ), array( 'blue' ) );

		// Green has no variation; WooCommerce would leave it out, so pass it in explicitly.
		$html = $this->dropdown( $product, $taxonomy, array( 'options' => array( 'red', 'blue', 'green' ) ) );

		$this->assertMatchesRegularExpression( '/class="wootoolkit-swatch wootoolkit-swatch--color has-label"[^>]*aria-label="Red" data-label="Red" data-value="red"/', $html );
		$this->assertMatchesRegularExpression( '/class="[^"]*is-out-of-stock[^"]*"[^>]*aria-label="Blue \(out of stock\)" data-label="Blue" data-value="blue"/', $html );
		$this->assertMatchesRegularExpression( '/class="[^"]*is-unavailable[^"]*"[^>]*aria-label="Green \(not available\)"[^>]*data-value="green"/', $html );

		update_option( Swatches::OPT_OUT_OF_STOCK, 'hide' );
		$this->assertStringContainsString( 'wootoolkit-swatches--oos-hide', $this->dropdown( $product, $taxonomy ) );
	}

	public function test_nothing_changes_in_wp_admin() {
		list( $taxonomy, $id, $terms ) = $this->attribute( array( 'Red' ), 'color' );
		$product                       = $this->variable_product( $taxonomy, $id, $terms, array( 'red' ) );
		set_current_screen( 'edit-post' );
		$this->assertStringNotContainsString( 'wootoolkit-swatch', $this->dropdown( $product, $taxonomy ) );
	}

	public function test_names_and_values_are_escaped() {
		list( $taxonomy, $id, $terms ) = $this->attribute( array( 'Red' ), 'label' );
		update_term_meta( $terms['red'], Swatches::TERM_COLOR, 'red;background:url(javascript:alert(1))' );
		$product = $this->variable_product( $taxonomy, $id, $terms, array( 'red' ) );

		add_filter(
			'woocommerce_variation_option_name',
			static function () {
				return 'Red "x" <script>alert(1)</script><b>bold</b>';
			}
		);
		add_filter(
			'wootoolkit_variation_swatches_term_data',
			static function ( $data ) {
				$data['image_url'] = 'javascript:alert(2)';
				return $data;
			}
		);
		$html = $this->dropdown( $product, $taxonomy );
		Swatches::set_type( $id, 'color' );
		$html .= $this->dropdown( $product, $taxonomy );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringNotContainsString( 'background-color:red', $html );
		$this->assertStringNotContainsString( '<img', $html, 'An unsafe image URL is dropped.' );
		$this->assertStringContainsString( 'aria-label="Red &quot;x&quot; bold"', $html );
		$this->assertStringContainsString( '<span class="wootoolkit-swatch__label">Red &quot;x&quot; bold</span>', $html );
	}

	public function test_the_pro_extension_points_change_type_data_and_markup() {
		list( $taxonomy, $id, $terms ) = $this->attribute( array( 'Red' ) );
		$product                       = $this->variable_product( $taxonomy, $id, $terms, array( 'red' ) );

		add_filter(
			'wootoolkit_variation_swatches_types',
			static function ( $types ) {
				$types['radio'] = 'Radio list';
				return $types;
			}
		);
		add_filter(
			'wootoolkit_variation_swatches_attribute_type',
			static function ( $type, $attribute ) use ( $taxonomy ) {
				return $attribute === $taxonomy ? 'radio' : $type;
			},
			10,
			2
		);
		add_filter(
			'wootoolkit_variation_swatches_term_data',
			static function ( $data ) {
				$data['color'] = '#123456';
				return $data;
			}
		);
		add_filter(
			'wootoolkit_variation_swatches_item',
			static function ( $item ) {
				$item['classes'][]    = 'my-extra';
				$item['tooltip_html'] = '<strong>Big</strong><script>x</script>';
				return $item;
			}
		);
		add_filter(
			'wootoolkit_variation_swatches_item_html',
			static function ( $html, $item, $type ) {
				return 'radio' === $type ? str_replace( '<span class="wootoolkit-swatch__label">', '<span class="radio-dot"></span><span class="wootoolkit-swatch__label">', $html ) : $html;
			},
			10,
			3
		);

		$html = $this->dropdown( $product, $taxonomy );
		$this->assertSame( 'radio', Swatches::attribute_type( $taxonomy, $product ) );
		$this->assertStringContainsString( 'wootoolkit-swatches--radio', $html );
		$this->assertStringContainsString( 'my-extra', $html );
		$this->assertStringContainsString( '<span class="radio-dot"></span>', $html );
		$this->assertStringContainsString( '<span class="wootoolkit-swatch__tooltip" aria-hidden="true"><strong>Big</strong>x</span>', $html );
		$this->assertSame( '#123456', Swatches::term_data( $taxonomy, 'red', get_term( $terms['red'] ), $product )['color'] );
	}

	public function test_the_attribute_type_is_saved_only_with_a_valid_nonce_and_capability() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$shop  = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $admin );
		$_POST = array(
			AttributeFields::TYPE_FIELD => 'color',
			AttributeFields::TYPE_NONCE => 'bad',
		);
		$slug  = 'wtknonce' . wp_rand( 100, 999 );
		$id    = wc_create_attribute(
			array(
				'name' => 'Nonce test',
				'slug' => $slug,
			)
		);
		$this->assertSame( '', Swatches::stored_type( (int) $id ), 'A bad nonce saves nothing.' );

		wp_set_current_user( $shop );
		$_POST[ AttributeFields::TYPE_NONCE ] = wp_create_nonce( 'wootoolkit_swatch_type' );
		AttributeFields::save_attribute( $id );
		$this->assertSame( '', Swatches::stored_type( (int) $id ), 'Users who cannot manage attributes save nothing.' );

		wp_set_current_user( $admin );
		$_POST[ AttributeFields::TYPE_NONCE ] = wp_create_nonce( 'wootoolkit_swatch_type' );
		wc_update_attribute(
			$id,
			array(
				'name' => 'Nonce test',
				'slug' => $slug,
			)
		);
		$this->assertSame( 'color', Swatches::stored_type( (int) $id ) );

		$_POST[ AttributeFields::TYPE_FIELD ] = 'evil<script>';
		AttributeFields::save_attribute( $id );
		$this->assertSame( '', Swatches::stored_type( (int) $id ), 'Unknown types fall back to the store default.' );

		Swatches::set_type( (int) $id, 'image' );
		wc_delete_attribute( $id );
		$this->assertSame( '', Swatches::stored_type( (int) $id ) );
	}

	public function test_term_colour_and_image_are_sanitised_and_need_a_nonce_and_capability() {
		list( $taxonomy ) = $this->attribute( array(), 'color' );
		$image            = self::factory()->attachment->create_object( 'blue.png', 0, array( 'post_mime_type' => 'image/png' ) );
		$document         = self::factory()->attachment->create_object( 'notes.pdf', 0, array( 'post_mime_type' => 'application/pdf' ) );
		$admin            = self::factory()->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin );
		$_POST = array(
			AttributeFields::TERM_NONCE => wp_create_nonce( 'wootoolkit_swatch_term' ),
			Swatches::TERM_COLOR        => '#00AAFF',
			Swatches::TERM_IMAGE        => (string) $image,
		);
		$blue  = wp_insert_term( 'Blue', $taxonomy );
		$this->assertSame( '#00AAFF', get_term_meta( $blue['term_id'], Swatches::TERM_COLOR, true ) );
		$this->assertSame( (string) $image, (string) get_term_meta( $blue['term_id'], Swatches::TERM_IMAGE, true ) );

		$_POST[ Swatches::TERM_COLOR ] = 'blue"><script>';
		$_POST[ Swatches::TERM_IMAGE ] = (string) $document;
		wp_update_term( $blue['term_id'], $taxonomy, array( 'description' => 'x' ) );
		$this->assertSame( '', get_term_meta( $blue['term_id'], Swatches::TERM_COLOR, true ), 'An invalid colour clears it.' );
		$this->assertSame( '', get_term_meta( $blue['term_id'], Swatches::TERM_IMAGE, true ), 'Only images can be swatch images.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$_POST[ AttributeFields::TERM_NONCE ] = wp_create_nonce( 'wootoolkit_swatch_term' );
		$_POST[ Swatches::TERM_COLOR ]        = '#111111';
		AttributeFields::save_term( $blue['term_id'], 0, $taxonomy );
		$this->assertSame( '', get_term_meta( $blue['term_id'], Swatches::TERM_COLOR, true ), 'No capability, no change.' );

		wp_set_current_user( $admin );
		$_POST[ AttributeFields::TERM_NONCE ] = 'bad';
		AttributeFields::save_term( $blue['term_id'], 0, $taxonomy );
		$this->assertSame( '', get_term_meta( $blue['term_id'], Swatches::TERM_COLOR, true ), 'Bad nonce, no change.' );

		$tag = self::factory()->term->create( array( 'taxonomy' => 'product_tag' ) );

		$_POST[ AttributeFields::TERM_NONCE ] = wp_create_nonce( 'wootoolkit_swatch_term' );
		AttributeFields::save_term( $tag, 0, 'product_tag' );
		$this->assertSame( '', get_term_meta( $tag, Swatches::TERM_COLOR, true ), 'Only attribute terms get swatch data.' );
	}

	public function test_term_fields_render_only_for_colour_and_image_attributes() {
		list( $color_tax, , $color_terms ) = $this->attribute( array( 'Red' ), 'color' );
		list( $label_tax )                 = $this->attribute( array(), 'label' );
		update_term_meta( $color_terms['red'], Swatches::TERM_COLOR, '#ff0000' );

		ob_start();
		AttributeFields::add_term_fields( $label_tax );
		$this->assertSame( '', ob_get_clean() );

		ob_start();
		AttributeFields::edit_term_fields( get_term( $color_terms['red'] ) );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'name="' . Swatches::TERM_COLOR . '" id="' . Swatches::TERM_COLOR . '" value="#ff0000"', $html );
		$this->assertStringContainsString( 'name="' . AttributeFields::TERM_NONCE . '"', $html );
		$this->assertStringContainsString( 'background:#ff0000', AttributeFields::term_column( '', 'wootoolkit_swatch', $color_terms['red'] ) );
	}
}
