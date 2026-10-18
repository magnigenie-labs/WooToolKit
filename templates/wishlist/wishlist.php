<?php
/**
 * Wishlist page. Override in yourtheme/wootoolkit/wishlist/wishlist.php.
 *
 * @package WooToolkit
 *
 * @var WP_Post|null                     $list      The list shown, null when the visitor has none yet.
 * @var int                              $list_id   List id (0 for none).
 * @var array<int, array<string, mixed>> $rows      Rows: key, product, name, permalink, image, price_html, price_added, in_stock, stock_text, added, cart_url, cart_text, cart_direct, remove_url.
 * @var bool                             $read_only Someone else's list opened with its share link.
 * @var bool                             $shared    Opened with a share link.
 * @var bool                             $is_owner  The visitor owns the list.
 * @var string                           $share_url Read-only link to share ('' when sharing is off).
 * @var string                           $action    URL of this page.
 * @var string                           $nonce     Nonce.
 * @var string                           $shop_url  Shop URL.
 * @var string                           $login_url Login URL for guests ('' for customers).
 * @var array<string, mixed>             $template_args All of the above, for the action hooks.
 */

defined( 'ABSPATH' ) || exit;

$wootoolkit_button = 'button' . ( function_exists( 'wp_theme_get_element_class_name' ) ? ' ' . wp_theme_get_element_class_name( 'button' ) : '' );
?>
<div class="wootoolkit-wishlist woocommerce">
<?php
/**
 * Before the wishlist table (the Pro kit adds the list switcher here).
 *
 * @param array<string, mixed> $args Template arguments.
 */
do_action( 'wootoolkit_wishlist_before_table', $template_args );
?>
<?php if ( $shared && ! $list ) : ?>
	<p class="wootoolkit-wishlist-empty"><?php esc_html_e( 'This wishlist is not available. The link may have expired, or sharing was turned off.', 'wootoolkit' ); ?></p>
<?php elseif ( ! $rows ) : ?>
	<p class="wootoolkit-wishlist-empty"><?php esc_html_e( 'Your wishlist is empty. Tap the heart on any product to save it here.', 'wootoolkit' ); ?></p>
	<?php if ( $shop_url ) : ?>
		<p><a class="<?php echo esc_attr( $wootoolkit_button . ' wc-backward' ); ?>" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Return to shop', 'wootoolkit' ); ?></a></p>
	<?php endif; ?>
<?php else : ?>
	<?php if ( $read_only && $list ) : ?>
		<h2 class="wootoolkit-wishlist-title"><?php echo esc_html( \WooToolkit\Kits\Wishlist\Store::title( $list_id ) ); ?></h2>
	<?php endif; ?>
	<table class="shop_table shop_table_responsive wootoolkit-wishlist-table">
		<thead>
			<tr>
				<th class="product-thumbnail"><span class="screen-reader-text"><?php esc_html_e( 'Image', 'wootoolkit' ); ?></span></th>
				<th class="product-name"><?php esc_html_e( 'Product', 'wootoolkit' ); ?></th>
				<th class="product-price"><?php esc_html_e( 'Price', 'wootoolkit' ); ?></th>
				<th class="product-stock-status"><?php esc_html_e( 'Stock', 'wootoolkit' ); ?></th>
				<th class="product-add-to-cart"><span class="screen-reader-text"><?php esc_html_e( 'Add to cart', 'wootoolkit' ); ?></span></th>
				<?php if ( $is_owner ) : ?>
					<th class="product-remove"><span class="screen-reader-text"><?php esc_html_e( 'Remove item', 'wootoolkit' ); ?></span></th>
				<?php endif; ?>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $rows as $wootoolkit_row ) : ?>
			<tr class="wootoolkit-wishlist-item" data-item-key="<?php echo esc_attr( $wootoolkit_row['key'] ); ?>">
				<td class="product-thumbnail">
					<a href="<?php echo esc_url( $wootoolkit_row['permalink'] ); ?>"><?php echo wp_kses_post( $wootoolkit_row['image'] ); ?></a>
				</td>
				<td class="product-name" data-title="<?php esc_attr_e( 'Product', 'wootoolkit' ); ?>">
					<a href="<?php echo esc_url( $wootoolkit_row['permalink'] ); ?>"><?php echo esc_html( $wootoolkit_row['name'] ); ?></a>
					<?php if ( $wootoolkit_row['added'] ) : ?>
						<?php /* translators: %s: date */ ?>
						<small class="wootoolkit-wishlist-added"><?php echo esc_html( sprintf( __( 'Added %s', 'wootoolkit' ), $wootoolkit_row['added'] ) ); ?></small>
					<?php endif; ?>
				</td>
				<td class="product-price" data-title="<?php esc_attr_e( 'Price', 'wootoolkit' ); ?>">
					<?php echo wp_kses_post( $wootoolkit_row['price_html'] ); ?>
					<?php if ( $wootoolkit_row['price_added'] ) : ?>
						<?php /* translators: %s: price when the product was added */ ?>
						<small class="wootoolkit-wishlist-price-drop"><?php echo wp_kses_post( sprintf( __( 'Was %s when added', 'wootoolkit' ), $wootoolkit_row['price_added'] ) ); ?></small>
					<?php endif; ?>
				</td>
				<td class="product-stock-status" data-title="<?php esc_attr_e( 'Stock', 'wootoolkit' ); ?>">
					<span class="<?php echo esc_attr( $wootoolkit_row['in_stock'] ? 'stock in-stock' : 'stock out-of-stock' ); ?>"><?php echo esc_html( wp_strip_all_tags( $wootoolkit_row['stock_text'] ) ); ?></span>
				</td>
				<td class="product-add-to-cart">
					<a class="<?php echo esc_attr( $wootoolkit_button . ( $wootoolkit_row['cart_direct'] ? ' add_to_cart_button alt' : '' ) ); ?>" href="<?php echo esc_url( $wootoolkit_row['cart_url'] ); ?>" rel="nofollow"><?php echo esc_html( $wootoolkit_row['cart_text'] ); ?></a>
				</td>
				<?php if ( $is_owner ) : ?>
					<td class="product-remove">
						<?php /* translators: %s: product name */ ?>
						<a href="<?php echo esc_url( $wootoolkit_row['remove_url'] ); ?>" class="remove" aria-label="<?php echo esc_attr( sprintf( __( 'Remove %s from your wishlist', 'wootoolkit' ), $wootoolkit_row['name'] ) ); ?>">&times;</a>
						<?php
						/**
						 * Extra actions for one row (the Pro kit adds "Move to").
						 *
						 * @param array<string, mixed> $row  Row.
						 * @param array<string, mixed> $args Template arguments.
						 */
						do_action( 'wootoolkit_wishlist_row_actions', $wootoolkit_row, $template_args );
						?>
					</td>
				<?php endif; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $share_url ) : ?>
		<div class="wootoolkit-wishlist-share">
			<label for="wootoolkit-wishlist-share-url"><?php esc_html_e( 'Share your wishlist (anyone with the link can see it, but not change it)', 'wootoolkit' ); ?></label>
			<div class="wootoolkit-wishlist-share-row">
				<input type="text" class="input-text" id="wootoolkit-wishlist-share-url" value="<?php echo esc_url( $share_url ); ?>" readonly />
				<button type="button" class="<?php echo esc_attr( $wootoolkit_button ); ?> wootoolkit-wishlist-copy" data-target="wootoolkit-wishlist-share-url"><?php esc_html_e( 'Copy link', 'wootoolkit' ); ?></button>
				<a class="wootoolkit-wishlist-share-email" href="<?php echo esc_url( 'mailto:?subject=' . rawurlencode( __( 'My wishlist', 'wootoolkit' ) ) . '&body=' . rawurlencode( $share_url ) ); ?>"><?php esc_html_e( 'Email', 'wootoolkit' ); ?></a>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $login_url ) : ?>
		<p class="wootoolkit-wishlist-guest-note">
			<?php esc_html_e( 'Your wishlist is saved in this browser.', 'wootoolkit' ); ?>
			<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log in to keep it in your account.', 'wootoolkit' ); ?></a>
		</p>
	<?php endif; ?>
<?php endif; ?>
<?php
/**
 * After the wishlist table (the Pro kit adds "Add all to cart" and the alert opt-in here).
 *
 * @param array<string, mixed> $args Template arguments.
 */
do_action( 'wootoolkit_wishlist_after_table', $template_args );
?>
</div>
