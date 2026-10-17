<?php
/**
 * Quote page: the quote list and the request form. Override in yourtheme/wootoolkit/quote/quote.php.
 *
 * @package WooToolkit
 *
 * @var WC_Order|null                                         $received Order just created from this browser's request, if any.
 * @var array<string, array>                                  $items    Quote list items.
 * @var array<string, array{0: string, 1: bool}>              $fields   Form fields: name => [label, required].
 * @var array<string, string>                                 $values   Prefilled values.
 * @var string                                                $action   Form action URL.
 * @var string                                                $nonce    Nonce.
 * @var string                                                $honeypot Honeypot field name.
 * @var string                                                $shop_url Shop URL.
 */

use WooToolkit\Kits\RequestAQuote\QuoteList;

defined( 'ABSPATH' ) || exit;
?>
<div class="wootoolkit-quote woocommerce">
<?php if ( $received ) : ?>
	<p class="wootoolkit-quote-received">
		<?php
		/* translators: %s: quote number */
		printf( esc_html__( 'Thank you. We have received your quote request #%s and will email you our quote.', 'wootoolkit' ), esc_html( $received->get_order_number() ) );
		?>
	</p>
<?php elseif ( ! $items ) : ?>
	<p class="wootoolkit-quote-empty"><?php esc_html_e( 'Your quote list is empty.', 'wootoolkit' ); ?></p>
	<?php if ( $shop_url ) : ?>
		<p><a class="button wc-backward" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Return to shop', 'wootoolkit' ); ?></a></p>
	<?php endif; ?>
<?php else : ?>
	<form class="wootoolkit-quote-list" method="post" action="<?php echo esc_url( $action ); ?>">
		<table class="shop_table shop_table_responsive">
			<thead>
				<tr>
					<th class="product-name"><?php esc_html_e( 'Product', 'wootoolkit' ); ?></th>
					<th class="product-quantity"><?php esc_html_e( 'Quantity', 'wootoolkit' ); ?></th>
					<th class="product-remove"><span class="screen-reader-text"><?php esc_html_e( 'Remove item', 'wootoolkit' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $items as $key => $item ) :
				$product = QuoteList::product( $item );
				if ( ! $product ) {
					continue;
				}
				$remove = add_query_arg(
					array(
						'wootoolkit_quote_action' => 'remove',
						'item'                    => $key,
						'_wpnonce'                => $nonce,
					),
					$action
				);
				?>
				<tr>
					<td class="product-name" data-title="<?php esc_attr_e( 'Product', 'wootoolkit' ); ?>">
						<a href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
					</td>
					<td class="product-quantity" data-title="<?php esc_attr_e( 'Quantity', 'wootoolkit' ); ?>">
						<label class="screen-reader-text" for="wootoolkit-qty-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Quantity', 'wootoolkit' ); ?></label>
						<input type="number" class="input-text qty text" id="wootoolkit-qty-<?php echo esc_attr( $key ); ?>" name="quantity[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $item['quantity'] ); ?>" min="0" step="1" inputmode="numeric" />
					</td>
					<td class="product-remove">
						<?php /* translators: %s: product name */ ?>
						<a href="<?php echo esc_url( $remove ); ?>" class="remove" aria-label="<?php echo esc_attr( sprintf( __( 'Remove %s from your quote', 'wootoolkit' ), $product->get_name() ) ); ?>">&times;</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<input type="hidden" name="wootoolkit_quote_action" value="update" />
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
		<p><button type="submit" class="button"><?php esc_html_e( 'Update quote', 'wootoolkit' ); ?></button></p>
	</form>

	<form class="wootoolkit-quote-request" method="post" action="<?php echo esc_url( $action ); ?>">
		<h2><?php esc_html_e( 'Request a quote', 'wootoolkit' ); ?></h2>
		<?php foreach ( $fields as $name => $field ) : ?>
			<p class="form-row form-row-wide">
				<label for="quote_<?php echo esc_attr( $name ); ?>">
					<?php echo esc_html( $field[0] ); ?>
					<?php if ( $field[1] ) : ?>
						<abbr class="required" title="<?php esc_attr_e( 'required', 'wootoolkit' ); ?>">*</abbr>
					<?php endif; ?>
				</label>
				<?php if ( 'message' === $name ) : ?>
					<textarea class="input-text" id="quote_<?php echo esc_attr( $name ); ?>" name="quote_<?php echo esc_attr( $name ); ?>" rows="4"></textarea>
				<?php else : ?>
					<input class="input-text" type="<?php echo 'email' === $name ? 'email' : ( 'phone' === $name ? 'tel' : 'text' ); ?>" id="quote_<?php echo esc_attr( $name ); ?>" name="quote_<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $values[ $name ] ?? '' ); ?>"<?php echo $field[1] ? ' required' : ''; ?> />
				<?php endif; ?>
			</p>
		<?php endforeach; ?>
		<p class="screen-reader-text" aria-hidden="true">
			<label for="<?php echo esc_attr( $honeypot ); ?>"><?php esc_html_e( 'Leave this field empty', 'wootoolkit' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $honeypot ); ?>" name="<?php echo esc_attr( $honeypot ); ?>" value="" tabindex="-1" autocomplete="off" />
		</p>
		<input type="hidden" name="wootoolkit_quote_action" value="submit" />
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
		<p><button type="submit" class="button alt"><?php esc_html_e( 'Send quote request', 'wootoolkit' ); ?></button></p>
	</form>
<?php endif; ?>
</div>
