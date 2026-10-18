/**
 * Wishlist and Wishlist counter blocks (editor). Both are rendered on the server.
 */
( function ( blocks, element, i18n, blockEditor ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'wootoolkit/wishlist', {
		apiVersion: 3,
		title: __( 'Wishlist', 'wootoolkit' ),
		description: __( 'The shopper\'s wishlist: image, name, price, stock, add to cart and remove, with a share link.', 'wootoolkit' ),
		category: 'woocommerce',
		icon: 'heart',
		supports: { html: false },
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'wootoolkit-wishlist-placeholder' } ),
				el( 'p', null, __( 'Wishlist. Shoppers see the products they saved here.', 'wootoolkit' ) )
			);
		},
		save: function () {
			return null;
		},
	} );

	blocks.registerBlockType( 'wootoolkit/wishlist-count', {
		apiVersion: 3,
		title: __( 'Wishlist counter', 'wootoolkit' ),
		description: __( 'A heart with the number of products on the shopper\'s wishlist, linking to the wishlist page. For headers.', 'wootoolkit' ),
		category: 'woocommerce',
		icon: 'heart',
		supports: { html: false },
		edit: function () {
			return el(
				'span',
				blockEditor.useBlockProps( { className: 'wootoolkit-wishlist-counter' } ),
				'♥ ' + __( 'Wishlist', 'wootoolkit' )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.blockEditor );
