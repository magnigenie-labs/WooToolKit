<?php
/**
 * The fields table on the kit's settings screen.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\CheckoutFields;

defined( 'ABSPATH' ) || exit;

/**
 * A custom WooCommerce settings field type: one table per section with the built-in fields (edit
 * label, placeholder, required, hidden, order) and repeatable rows for extra fields. WooCommerce
 * renders it through `woocommerce_admin_field_<type>` and saves it, after its own nonce and
 * capability checks, through `woocommerce_admin_settings_sanitize_option_<option>`.
 */
final class SettingsField {

	const TYPE  = 'wootoolkit_checkout_fields_table';
	const RESET = 'wootoolkit_checkout_fields_reset';

	/**
	 * Keys claimed by extra fields while saving, so two sections never use the same id.
	 *
	 * @var array<string, string>
	 */
	private static $claimed = array();

	/**
	 * Sections cleaned in this request.
	 *
	 * @var array<string, bool>
	 */
	private static $done = array();

	/**
	 * Hooks the field.
	 */
	public static function hooks(): void {
		add_action( 'woocommerce_admin_field_' . self::TYPE, array( self::class, 'render' ) );
		foreach ( Fields::SECTIONS as $section ) {
			add_filter( 'woocommerce_admin_settings_sanitize_option_' . Fields::option( $section ), array( self::class, 'sanitize' ), 10, 3 );
		}
		add_filter( 'woocommerce_save_settings_wootoolkit_' . CheckoutFieldsKit::ID, array( self::class, 'save_on_reset' ) );
	}

	/**
	 * Clears the save-time bookkeeping.
	 */
	public static function flush(): void {
		self::$claimed = array();
		self::$done    = array();
	}

	/**
	 * The section posted for "Reset to defaults", or ''.
	 */
	private static function reset_section(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read while WooCommerce saves the settings, after it verified the nonce and capability.
		return isset( $_POST[ self::RESET ] ) ? sanitize_key( wp_unslash( $_POST[ self::RESET ] ) ) : '';
	}

	/**
	 * Lets the "Reset to defaults" button save the form (WooCommerce still checks the nonce).
	 *
	 * @param bool $save Whether WooCommerce saves.
	 */
	public static function save_on_reset( $save ): bool {
		return (bool) $save || in_array( self::reset_section(), Fields::SECTIONS, true );
	}

	/**
	 * Clean section settings from the posted table.
	 *
	 * @param mixed                $value  Value cleaned by WooCommerce (unused).
	 * @param array<string, mixed> $option Settings field.
	 * @param mixed                $raw    Unslashed posted value.
	 * @return array{core: array<string, array<string, mixed>>, custom: array<int, array<string, mixed>>}
	 */
	public static function sanitize( $value, $option, $raw ): array {
		unset( $value );
		$section = isset( $option['section'] ) ? (string) $option['section'] : '';
		if ( ! in_array( $section, Fields::SECTIONS, true ) || self::reset_section() === $section ) {
			return array(
				'core'   => array(),
				'custom' => array(),
			);
		}
		return self::clean( $section, is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Cleans one section's posted rows.
	 *
	 * @param string               $section Section.
	 * @param array<string, mixed> $raw     Posted rows.
	 * @return array{core: array<string, array<string, mixed>>, custom: array<int, array<string, mixed>>}
	 */
	public static function clean( string $section, array $raw ): array {
		$defaults = CoreFields::defaults()[ $section ] ?? array();
		$core     = array();
		$posted   = isset( $raw['core'] ) && is_array( $raw['core'] ) ? $raw['core'] : array();
		foreach ( Fields::CORE[ $section ] as $key => $props ) {
			if ( ! isset( $posted[ $key ] ) || ! is_array( $posted[ $key ] ) || ! isset( $defaults[ $key ] ) ) {
				continue;
			}
			$row   = $posted[ $key ];
			$edits = array();
			foreach ( array( 'label', 'placeholder' ) as $prop ) {
				if ( in_array( $prop, $props, true ) && isset( $row[ $prop ] ) && is_scalar( $row[ $prop ] ) ) {
					$text = mb_substr( sanitize_text_field( (string) $row[ $prop ] ), 0, 200 );
					if ( '' !== $text && $text !== $defaults[ $key ][ $prop ] ) {
						$edits[ $prop ] = $text;
					}
				}
			}
			foreach ( array( 'required', 'hidden' ) as $prop ) {
				if ( in_array( $prop, $props, true ) ) {
					$on = ! empty( $row[ $prop ] );
					if ( $on !== (bool) $defaults[ $key ][ $prop ] ) {
						$edits[ $prop ] = $on;
					}
				}
			}
			if ( in_array( 'priority', $props, true ) && isset( $row['priority'] ) && is_scalar( $row['priority'] ) ) {
				$priority = min( 9999, absint( $row['priority'] ) );
				if ( $priority && $priority !== (int) $defaults[ $key ]['priority'] ) {
					$edits['priority'] = $priority;
				}
			}
			if ( $edits ) {
				$core[ $key ] = $edits;
			}
		}

		// Forget this section's own earlier claims (a second save in the same request).
		self::$claimed = array_diff( self::$claimed, array( $section ) );

		$custom = array();
		$rows   = isset( $raw['custom'] ) && is_array( $raw['custom'] ) ? $raw['custom'] : array();
		$types  = Fields::types();
		$taken  = self::taken_keys( $section );

		self::$done[ $section ] = true;
		foreach ( array_values( $rows ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) && is_scalar( $row['label'] ) ? mb_substr( sanitize_text_field( (string) $row['label'] ), 0, 200 ) : '';
			if ( '' === $label ) {
				continue;
			}
			$key = isset( $row['key'] ) && is_scalar( $row['key'] ) ? self::key( (string) $row['key'] ) : '';
			if ( '' === $key ) {
				$key = self::key( $label );
			}
			if ( '' === $key ) {
				$key = 'field';
			}
			$base = $key;
			for ( $n = 2; isset( $taken[ $key ] ); $n++ ) {
				$key = substr( $base, 0, 36 ) . '-' . $n;
			}
			$taken[ $key ]           = true;
			self::$claimed[ $key ] = $section;

			$type  = isset( $row['type'] ) && is_scalar( $row['type'] ) && isset( $types[ (string) $row['type'] ] ) ? (string) $row['type'] : 'text';
			$clean = array_merge(
				Fields::field_defaults(),
				array(
					'key'         => $key,
					'label'       => $label,
					'placeholder' => isset( $row['placeholder'] ) && is_scalar( $row['placeholder'] ) ? mb_substr( sanitize_text_field( (string) $row['placeholder'] ), 0, 200 ) : '',
					'type'        => $type,
					'options'     => ! empty( $types[ $type ]['options'] ) && isset( $row['options'] ) && is_scalar( $row['options'] ) ? Fields::parse_options( (string) $row['options'] ) : array(),
					'required'    => ! empty( $row['required'] ),
					'hidden'      => ! empty( $row['hidden'] ),
					'priority'    => isset( $row['priority'] ) && is_scalar( $row['priority'] ) ? min( 9999, absint( $row['priority'] ) ) : 0,
				)
			);

			/**
			 * One extra field as saved from the settings screen. Add-ons clean their own keys here.
			 *
			 * @param array<string, mixed> $clean   Clean field.
			 * @param array<string, mixed> $row     Posted row (unslashed, not sanitized).
			 * @param string               $section Section.
			 */
			$custom[] = (array) apply_filters( 'wootoolkit_checkout_fields_sanitize_field', $clean, $row, $section );
		}

		return array(
			'core'   => $core,
			'custom' => $custom,
		);
	}

	/**
	 * A field key: lowercase letters, digits and dashes, at most 40 characters.
	 *
	 * @param string $text Text.
	 */
	public static function key( string $text ): string {
		$key = preg_replace( '/[^a-z0-9-]+/', '-', strtolower( remove_accents( $text ) ) );
		return substr( trim( (string) preg_replace( '/-+/', '-', (string) $key ), '-' ), 0, 40 );
	}

	/**
	 * Keys used by the other sections (saved, or claimed earlier in this save).
	 *
	 * @param string $section Section being saved.
	 * @return array<string, bool>
	 */
	private static function taken_keys( string $section ): array {
		$taken = array();
		foreach ( self::$claimed as $key => $owner ) {
			if ( $owner !== $section ) {
				$taken[ $key ] = true;
			}
		}
		foreach ( Fields::SECTIONS as $other ) {
			if ( $other === $section || isset( self::$done[ $other ] ) ) {
				continue;
			}
			$saved = get_option( Fields::option( $other ), array() );
			foreach ( is_array( $saved ) && isset( $saved['custom'] ) && is_array( $saved['custom'] ) ? $saved['custom'] : array() as $field ) {
				if ( is_array( $field ) && ! empty( $field['key'] ) ) {
					$taken[ (string) $field['key'] ] = true;
				}
			}
		}
		return $taken;
	}

	/**
	 * Renders a section's table.
	 *
	 * @param array<string, mixed> $field Settings field.
	 */
	public static function render( $field ): void {
		$section = isset( $field['section'] ) ? (string) $field['section'] : '';
		if ( ! in_array( $section, Fields::SECTIONS, true ) ) {
			return;
		}
		wp_enqueue_script( 'wootoolkit-checkout-fields-admin', plugins_url( 'assets/js/checkout-fields-admin.js', WOOTOOLKIT_FILE ), array(), WOOTOOLKIT_VERSION, true );
		wp_enqueue_style( 'wootoolkit-checkout-fields-admin', plugins_url( 'assets/css/checkout-fields-admin.css', WOOTOOLKIT_FILE ), array(), WOOTOOLKIT_VERSION );

		$option   = Fields::option( $section );
		$config   = Fields::config( $section );
		$defaults = CoreFields::defaults()[ $section ] ?? array();
		$custom   = $config['custom'];
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html( (string) ( $field['title'] ?? '' ) ); ?></th>
			<td class="forminp">
				<div class="wtk-cf" data-wtk-cf="<?php echo esc_attr( $section ); ?>" data-next="<?php echo esc_attr( (string) count( $custom ) ); ?>">
					<input type="hidden" name="<?php echo esc_attr( $option . '[present]' ); ?>" value="1">
					<table class="widefat wtk-cf__table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Field', 'wootoolkit' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Label', 'wootoolkit' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Placeholder', 'wootoolkit' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Type', 'wootoolkit' ); ?></th>
								<th scope="col" class="wtk-cf__narrow"><?php esc_html_e( 'Required', 'wootoolkit' ); ?></th>
								<th scope="col" class="wtk-cf__narrow"><?php esc_html_e( 'Hidden', 'wootoolkit' ); ?></th>
								<th scope="col" class="wtk-cf__narrow"><?php esc_html_e( 'Order', 'wootoolkit' ); ?></th>
								<th scope="col" class="wtk-cf__narrow"><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'wootoolkit' ); ?></span></th>
							</tr>
						</thead>
						<tbody class="wtk-cf__core">
							<?php
							foreach ( CoreFields::effective( $section ) as $key => $values ) {
								self::core_row( $section, (string) $key, $values, $defaults[ $key ] );
							}
							?>
						</tbody>
						<?php
						foreach ( array_values( $custom ) as $index => $row ) {
							$row             = array_merge( Fields::field_defaults(), $row );
							$row['priority'] = (int) $row['priority'] ? (int) $row['priority'] : Fields::default_priority( $section, (int) $index );
							self::custom_row( $section, (string) $index, $row );
						}
						?>
					</table>
					<template>
						<?php
						$new             = Fields::field_defaults();
						$new['priority'] = Fields::default_priority( $section, 0 );
						self::custom_row( $section, '__i__', $new );
						?>
					</template>
					<p class="wtk-cf__actions">
						<button type="button" class="button" data-wtk-cf-add><?php esc_html_e( 'Add field', 'wootoolkit' ); ?></button>
						<button type="submit" class="button-link button-link-delete" name="<?php echo esc_attr( self::RESET ); ?>" value="<?php echo esc_attr( $section ); ?>" data-wtk-cf-reset data-confirm="<?php esc_attr_e( 'Reset this section to WooCommerce\'s defaults? Your edits and extra fields in it are removed (values already saved on orders stay).', 'wootoolkit' ); ?>"><?php esc_html_e( 'Reset to defaults', 'wootoolkit' ); ?></button>
					</p>
					<?php if ( ! empty( $field['desc'] ) ) : ?>
						<p class="description"><?php echo wp_kses_post( (string) $field['desc'] ); ?></p>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * One built-in field row.
	 *
	 * @param string               $section  Section.
	 * @param string               $key      Field key.
	 * @param array<string, mixed> $values   Current settings.
	 * @param array<string, mixed> $defaults WooCommerce defaults.
	 */
	private static function core_row( string $section, string $key, array $values, array $defaults ): void {
		$props = Fields::CORE[ $section ][ $key ];
		$name  = Fields::option( $section ) . '[core][' . $key . ']';
		$edits = Fields::core_edits( $section )[ $key ] ?? array();
		$uid   = 'wtk-cf-' . $section . '-' . $key;
		?>
		<tr class="wtk-cf__row wtk-cf__row--core">
			<td><strong><?php echo esc_html( (string) $defaults['label'] ); ?></strong><br><code><?php echo esc_html( $key ); ?></code></td>
			<td>
				<label class="screen-reader-text" for="<?php echo esc_attr( $uid . '-label' ); ?>"><?php esc_html_e( 'Label', 'wootoolkit' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $uid . '-label' ); ?>" name="<?php echo esc_attr( $name . '[label]' ); ?>" value="<?php echo esc_attr( (string) ( $edits['label'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( (string) $defaults['label'] ); ?>">
			</td>
			<td>
				<?php if ( in_array( 'placeholder', $props, true ) ) : ?>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid . '-placeholder' ); ?>"><?php esc_html_e( 'Placeholder', 'wootoolkit' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $uid . '-placeholder' ); ?>" name="<?php echo esc_attr( $name . '[placeholder]' ); ?>" value="<?php echo esc_attr( (string) ( $edits['placeholder'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( (string) $defaults['placeholder'] ); ?>">
				<?php else : ?>
					<span aria-hidden="true">&ndash;</span>
				<?php endif; ?>
			</td>
			<td><?php esc_html_e( 'Built-in', 'wootoolkit' ); ?></td>
			<?php foreach ( array( 'required', 'hidden' ) as $prop ) : ?>
				<td class="wtk-cf__narrow">
					<?php if ( in_array( $prop, $props, true ) ) : ?>
						<input type="checkbox" name="<?php echo esc_attr( $name . '[' . $prop . ']' ); ?>" value="1" <?php checked( ! empty( $values[ $prop ] ) ); ?> aria-label="<?php echo esc_attr( 'required' === $prop ? __( 'Required', 'wootoolkit' ) : __( 'Hidden', 'wootoolkit' ) ); ?>">
					<?php else : ?>
						<span aria-hidden="true">&ndash;</span>
					<?php endif; ?>
				</td>
			<?php endforeach; ?>
			<td class="wtk-cf__narrow">
				<?php if ( in_array( 'priority', $props, true ) ) : ?>
					<input type="number" min="1" max="9999" step="1" class="small-text" name="<?php echo esc_attr( $name . '[priority]' ); ?>" value="<?php echo esc_attr( (string) (int) $values['priority'] ); ?>" aria-label="<?php esc_attr_e( 'Order', 'wootoolkit' ); ?>">
				<?php else : ?>
					<span aria-hidden="true">&ndash;</span>
				<?php endif; ?>
			</td>
			<td class="wtk-cf__narrow"></td>
		</tr>
		<?php
	}

	/**
	 * One extra field (a tbody with the main row and a details row).
	 *
	 * @param string               $section Section.
	 * @param string               $index   Row index ("__i__" in the template).
	 * @param array<string, mixed> $row     Field settings.
	 */
	private static function custom_row( string $section, string $index, array $row ): void {
		$prefix = Fields::option( $section ) . '[custom][' . $index . ']';
		$uid    = 'wtk-cf-' . $section . '-' . $index;
		$types  = Fields::types();
		$saved  = '' !== (string) $row['key'];
		?>
		<tbody class="wtk-cf__field">
			<tr class="wtk-cf__row">
				<td>
					<?php if ( $saved ) : ?>
						<code><?php echo esc_html( Fields::NAMESPACE . '/' . $row['key'] ); ?></code>
						<input type="hidden" name="<?php echo esc_attr( $prefix . '[key]' ); ?>" value="<?php echo esc_attr( (string) $row['key'] ); ?>">
					<?php else : ?>
						<label class="screen-reader-text" for="<?php echo esc_attr( $uid . '-key' ); ?>"><?php esc_html_e( 'Key', 'wootoolkit' ); ?></label>
						<input type="text" id="<?php echo esc_attr( $uid . '-key' ); ?>" name="<?php echo esc_attr( $prefix . '[key]' ); ?>" value="" placeholder="<?php esc_attr_e( 'Key (made from the label)', 'wootoolkit' ); ?>" pattern="[a-z0-9-]*" maxlength="40">
					<?php endif; ?>
				</td>
				<td>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid . '-label' ); ?>"><?php esc_html_e( 'Label', 'wootoolkit' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $uid . '-label' ); ?>" name="<?php echo esc_attr( $prefix . '[label]' ); ?>" value="<?php echo esc_attr( (string) $row['label'] ); ?>" placeholder="<?php esc_attr_e( 'Label (required)', 'wootoolkit' ); ?>">
				</td>
				<td>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid . '-placeholder' ); ?>"><?php esc_html_e( 'Placeholder', 'wootoolkit' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $uid . '-placeholder' ); ?>" name="<?php echo esc_attr( $prefix . '[placeholder]' ); ?>" value="<?php echo esc_attr( (string) $row['placeholder'] ); ?>">
				</td>
				<td>
					<label class="screen-reader-text" for="<?php echo esc_attr( $uid . '-type' ); ?>"><?php esc_html_e( 'Type', 'wootoolkit' ); ?></label>
					<select id="<?php echo esc_attr( $uid . '-type' ); ?>" name="<?php echo esc_attr( $prefix . '[type]' ); ?>" data-wtk-cf-type>
						<?php foreach ( $types as $slug => $type ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" data-options="<?php echo esc_attr( empty( $type['options'] ) ? '0' : '1' ); ?>" <?php selected( (string) $row['type'], $slug ); ?>><?php echo esc_html( (string) $type['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td class="wtk-cf__narrow"><input type="checkbox" name="<?php echo esc_attr( $prefix . '[required]' ); ?>" value="1" <?php checked( ! empty( $row['required'] ) ); ?> aria-label="<?php esc_attr_e( 'Required', 'wootoolkit' ); ?>"></td>
				<td class="wtk-cf__narrow"><input type="checkbox" name="<?php echo esc_attr( $prefix . '[hidden]' ); ?>" value="1" <?php checked( ! empty( $row['hidden'] ) ); ?> aria-label="<?php esc_attr_e( 'Hidden', 'wootoolkit' ); ?>"></td>
				<td class="wtk-cf__narrow"><input type="number" min="1" max="9999" step="1" class="small-text" name="<?php echo esc_attr( $prefix . '[priority]' ); ?>" value="<?php echo esc_attr( (string) (int) $row['priority'] ); ?>" aria-label="<?php esc_attr_e( 'Order', 'wootoolkit' ); ?>"></td>
				<td class="wtk-cf__narrow"><button type="button" class="button-link button-link-delete" data-wtk-cf-remove aria-label="<?php esc_attr_e( 'Remove field', 'wootoolkit' ); ?>">&times;</button></td>
			</tr>
			<tr class="wtk-cf__details">
				<td></td>
				<td colspan="7">
					<p data-wtk-cf-options>
						<label for="<?php echo esc_attr( $uid . '-options' ); ?>"><?php esc_html_e( 'Choices, one per line ("value | Label", or just the label)', 'wootoolkit' ); ?></label>
						<textarea id="<?php echo esc_attr( $uid . '-options' ); ?>" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" rows="3"><?php echo esc_textarea( Fields::options_text( (array) $row['options'] ) ); ?></textarea>
					</p>
					<?php
					/**
					 * Prints extra settings for an extra field, inside its row. Name inputs "$name_prefix[your_key]".
					 *
					 * @param array<string, mixed> $row         Field settings.
					 * @param string               $name_prefix Input name prefix.
					 * @param string               $section     Section.
					 */
					do_action( 'wootoolkit_checkout_fields_row_settings', $row, $prefix, $section );
					?>
				</td>
			</tr>
		</tbody>
		<?php
	}
}
