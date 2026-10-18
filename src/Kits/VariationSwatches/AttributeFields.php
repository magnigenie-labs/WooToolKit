<?php
/**
 * Swatch settings on the attribute and attribute term screens.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\VariationSwatches;

defined( 'ABSPATH' ) || exit;

/**
 * "Swatch type" on Products > Attributes (add and edit), colour and image on each term of colour
 * and image attributes, and a swatch preview column in the term list.
 */
final class AttributeFields {

	const TYPE_FIELD = 'wootoolkit_swatch_type';
	const TYPE_NONCE = 'wootoolkit_swatch_type_nonce';
	const TERM_NONCE = 'wootoolkit_swatch_term_nonce';

	/**
	 * Hooks the fields.
	 */
	public static function hooks(): void {
		add_action( 'woocommerce_after_add_attribute_fields', array( self::class, 'add_attribute_field' ) );
		add_action( 'woocommerce_after_edit_attribute_fields', array( self::class, 'edit_attribute_field' ) );
		add_action( 'woocommerce_attribute_added', array( self::class, 'save_attribute' ) );
		add_action( 'woocommerce_attribute_updated', array( self::class, 'save_attribute' ) );
		add_action( 'woocommerce_attribute_deleted', array( self::class, 'delete_attribute' ) );

		add_action( 'admin_init', array( self::class, 'hook_term_screens' ) );
		add_action( 'created_term', array( self::class, 'save_term' ), 10, 3 );
		add_action( 'edited_term', array( self::class, 'save_term' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Type choices for the attribute screen.
	 *
	 * @return array<string, string>
	 */
	private static function type_choices(): array {
		$types = Swatches::types();
		/* translators: %s: the store-wide default, e.g. "Dropdown (WooCommerce default)" */
		$default = sprintf( __( 'Store default (%s)', 'wootoolkit' ), $types[ Swatches::default_type() ] ?? '' );
		return array( '' => $default ) + $types;
	}

	/**
	 * The type select.
	 *
	 * @param string $current Current type ('' = store default).
	 */
	private static function type_select( string $current ): void {
		echo '<select name="' . esc_attr( self::TYPE_FIELD ) . '" id="' . esc_attr( self::TYPE_FIELD ) . '">';
		foreach ( self::type_choices() as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		wp_nonce_field( 'wootoolkit_swatch_type', self::TYPE_NONCE, false );
	}

	/**
	 * Help text under the type select.
	 */
	private static function type_help(): string {
		return __( 'How this attribute is chosen on product pages. For colour and image swatches, set each term\'s colour or image under "Configure terms".', 'wootoolkit' );
	}

	/**
	 * "Add attribute" form.
	 */
	public static function add_attribute_field(): void {
		echo '<div class="form-field"><label for="' . esc_attr( self::TYPE_FIELD ) . '">' . esc_html__( 'Swatch type', 'wootoolkit' ) . '</label>';
		self::type_select( '' );
		echo '<p class="description">' . esc_html( self::type_help() ) . '</p></div>';
	}

	/**
	 * "Edit attribute" form.
	 */
	public static function edit_attribute_field(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the id of the attribute being edited.
		$attribute_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
		echo '<tr class="form-field"><th scope="row" valign="top"><label for="' . esc_attr( self::TYPE_FIELD ) . '">' . esc_html__( 'Swatch type', 'wootoolkit' ) . '</label></th><td>';
		self::type_select( Swatches::stored_type( $attribute_id ) );
		echo '<p class="description">' . esc_html( self::type_help() ) . '</p></td></tr>';
	}

	/**
	 * Saves the type when an attribute is added or updated from the attribute screen.
	 * Attributes created elsewhere (REST API, imports) carry no field and are left alone.
	 *
	 * @param int $attribute_id Attribute id.
	 */
	public static function save_attribute( $attribute_id ): void {
		if ( ! isset( $_POST[ self::TYPE_FIELD ], $_POST[ self::TYPE_NONCE ] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::TYPE_NONCE ] ) ), 'wootoolkit_swatch_type' )
			|| ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}
		$type = Swatches::sanitize_type( sanitize_key( wp_unslash( $_POST[ self::TYPE_FIELD ] ) ), '' );
		Swatches::set_type( (int) $attribute_id, $type );
	}

	/**
	 * Forgets the type of a deleted attribute.
	 *
	 * @param int $attribute_id Attribute id.
	 */
	public static function delete_attribute( $attribute_id ): void {
		Swatches::set_type( (int) $attribute_id, '' );
	}

	/**
	 * Whether a taxonomy's terms get the kit's colour and image fields: colour or image attributes,
	 * except WooCommerce's own "Color / image" attributes, which have their own fields.
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public static function has_term_fields( string $taxonomy ): bool {
		$row = Swatches::attribute_row( $taxonomy );
		return $row && 'wc-visual' !== $row['type'] && in_array( Swatches::taxonomy_type( $taxonomy ), array( 'color', 'image' ), true );
	}

	/**
	 * Adds the term fields and list column to every attribute taxonomy.
	 */
	public static function hook_term_screens(): void {
		foreach ( wc_get_attribute_taxonomies() as $row ) {
			$taxonomy = wc_attribute_taxonomy_name( $row->attribute_name );
			add_action( $taxonomy . '_add_form_fields', array( self::class, 'add_term_fields' ) );
			add_action( $taxonomy . '_edit_form_fields', array( self::class, 'edit_term_fields' ) );
			add_filter( 'manage_edit-' . $taxonomy . '_columns', array( self::class, 'term_columns' ) );
			add_filter( 'manage_' . $taxonomy . '_custom_column', array( self::class, 'term_column' ), 10, 3 );
		}
	}

	/**
	 * Colour input.
	 *
	 * @param string $value Hex colour.
	 */
	private static function color_input( string $value ): string {
		return sprintf(
			'<input type="text" class="wootoolkit-swatch-color" name="%1$s" id="%1$s" value="%2$s" data-default-color="" />',
			esc_attr( Swatches::TERM_COLOR ),
			esc_attr( $value )
		);
	}

	/**
	 * Image picker.
	 *
	 * @param int $image_id Attachment id.
	 */
	private static function image_input( int $image_id ): string {
		$preview = $image_id ? wp_get_attachment_image( $image_id, 'thumbnail', false, array( 'style' => 'max-width:60px;height:auto' ) ) : '';
		return sprintf(
			'<div class="wootoolkit-swatch-image"><span class="wootoolkit-swatch-image__preview">%1$s</span><input type="hidden" name="%2$s" id="%2$s" value="%3$s" /> <button type="button" class="button wootoolkit-swatch-image__choose">%4$s</button> <button type="button" class="button-link wootoolkit-swatch-image__remove"%5$s>%6$s</button></div>',
			wp_kses_post( $preview ),
			esc_attr( Swatches::TERM_IMAGE ),
			esc_attr( $image_id ? (string) $image_id : '' ),
			esc_html__( 'Choose image', 'wootoolkit' ),
			$image_id ? '' : ' hidden',
			esc_html__( 'Remove', 'wootoolkit' )
		);
	}

	/**
	 * Fields on the "Add new term" form.
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public static function add_term_fields( $taxonomy ): void {
		if ( ! self::has_term_fields( (string) $taxonomy ) ) {
			return;
		}
		wp_nonce_field( 'wootoolkit_swatch_term', self::TERM_NONCE, false );
		echo '<div class="form-field"><label for="' . esc_attr( Swatches::TERM_COLOR ) . '">' . esc_html__( 'Swatch colour', 'wootoolkit' ) . '</label>' . self::color_input( '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in color_input().
		echo '<div class="form-field"><label for="' . esc_attr( Swatches::TERM_IMAGE ) . '">' . esc_html__( 'Swatch image', 'wootoolkit' ) . '</label>' . self::image_input( 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in image_input().
		echo '<p class="description">' . esc_html__( 'Colour swatches use the colour (or the image when there is no colour); image swatches use the image.', 'wootoolkit' ) . '</p></div>';
	}

	/**
	 * Fields on the "Edit term" form.
	 *
	 * @param \WP_Term $term Term.
	 */
	public static function edit_term_fields( $term ): void {
		if ( ! $term instanceof \WP_Term || ! self::has_term_fields( $term->taxonomy ) ) {
			return;
		}
		$color = (string) sanitize_hex_color( (string) get_term_meta( $term->term_id, Swatches::TERM_COLOR, true ) );
		$image = absint( get_term_meta( $term->term_id, Swatches::TERM_IMAGE, true ) );
		echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( Swatches::TERM_COLOR ) . '">' . esc_html__( 'Swatch colour', 'wootoolkit' ) . '</label></th><td>';
		wp_nonce_field( 'wootoolkit_swatch_term', self::TERM_NONCE, false );
		echo self::color_input( $color ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in color_input().
		echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( Swatches::TERM_IMAGE ) . '">' . esc_html__( 'Swatch image', 'wootoolkit' ) . '</label></th><td>' . self::image_input( $image ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in image_input().
		echo '<p class="description">' . esc_html__( 'Colour swatches use the colour (or the image when there is no colour); image swatches use the image.', 'wootoolkit' ) . '</p></td></tr>';
	}

	/**
	 * Saves a term's colour and image (add and edit forms). Checks the kit's own nonce and the
	 * user's right to edit the term; quick edit and the REST API carry no fields and change nothing.
	 *
	 * @param int    $term_id  Term id.
	 * @param int    $tt_id    Term taxonomy id (unused).
	 * @param string $taxonomy Taxonomy.
	 */
	public static function save_term( $term_id, $tt_id = 0, $taxonomy = '' ): void {
		unset( $tt_id );
		if ( ! isset( $_POST[ self::TERM_NONCE ] ) || null === Swatches::attribute_row( (string) $taxonomy )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::TERM_NONCE ] ) ), 'wootoolkit_swatch_term' )
			|| ! current_user_can( 'edit_term', (int) $term_id ) ) {
			return;
		}
		if ( isset( $_POST[ Swatches::TERM_COLOR ] ) ) {
			$color = sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST[ Swatches::TERM_COLOR ] ) ) );
			if ( $color ) {
				update_term_meta( (int) $term_id, Swatches::TERM_COLOR, $color );
			} else {
				delete_term_meta( (int) $term_id, Swatches::TERM_COLOR );
			}
		}
		if ( isset( $_POST[ Swatches::TERM_IMAGE ] ) ) {
			$image = absint( wp_unslash( $_POST[ Swatches::TERM_IMAGE ] ) );
			if ( $image && wp_attachment_is_image( $image ) ) {
				update_term_meta( (int) $term_id, Swatches::TERM_IMAGE, $image );
			} else {
				delete_term_meta( (int) $term_id, Swatches::TERM_IMAGE );
			}
		}
	}

	/**
	 * Adds a "Swatch" column to the term list of colour and image attributes.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function term_columns( $columns ): array {
		$columns  = (array) $columns;
		$screen   = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$taxonomy = $screen ? (string) $screen->taxonomy : '';
		if ( '' === $taxonomy || ! self::has_term_fields( $taxonomy ) ) {
			return $columns;
		}
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'cb' === $key ) {
				$out['wootoolkit_swatch'] = __( 'Swatch', 'wootoolkit' );
			}
		}
		if ( ! isset( $out['wootoolkit_swatch'] ) ) {
			$out['wootoolkit_swatch'] = __( 'Swatch', 'wootoolkit' );
		}
		return $out;
	}

	/**
	 * The swatch preview in the term list.
	 *
	 * @param string $content Column content.
	 * @param string $column  Column key.
	 * @param int    $term_id Term id.
	 */
	public static function term_column( $content, $column, $term_id ): string {
		if ( 'wootoolkit_swatch' !== $column ) {
			return (string) $content;
		}
		$color = (string) sanitize_hex_color( (string) get_term_meta( (int) $term_id, Swatches::TERM_COLOR, true ) );
		$image = absint( get_term_meta( (int) $term_id, Swatches::TERM_IMAGE, true ) );
		if ( $image ) {
			return (string) wp_get_attachment_image( $image, array( 32, 32 ), false, array( 'style' => 'width:32px;height:32px;object-fit:cover;border-radius:4px' ) );
		}
		if ( $color ) {
			return '<span style="display:inline-block;width:24px;height:24px;border-radius:50%;border:1px solid rgba(0,0,0,.2);background:' . esc_attr( $color ) . '" title="' . esc_attr( $color ) . '"></span>';
		}
		return '&mdash;';
	}

	/**
	 * Colour picker and media library on attribute term screens.
	 *
	 * @param string $hook Admin page.
	 */
	public static function enqueue( $hook ): void {
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->taxonomy ) || ! self::has_term_fields( (string) $screen->taxonomy ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();
		wp_enqueue_script( 'wootoolkit-variation-swatches-admin', plugins_url( 'assets/js/variation-swatches-admin.js', WOOTOOLKIT_FILE ), array( 'jquery', 'wp-color-picker' ), WOOTOOLKIT_VERSION, true );
		wp_localize_script(
			'wootoolkit-variation-swatches-admin',
			'wootoolkitSwatchesAdmin',
			array(
				'title'  => __( 'Choose a swatch image', 'wootoolkit' ),
				'button' => __( 'Use this image', 'wootoolkit' ),
			)
		);
	}
}
