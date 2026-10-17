/**
 * Quote block (editor). The list and form are rendered on the server.
 */
( function ( blocks, element, i18n, blockEditor ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'wootoolkit/quote', {
		apiVersion: 3,
		title: __( 'Quote list and request form', 'wootoolkit' ),
		description: __( 'The products a shopper added to their quote, and the form to request it.', 'wootoolkit' ),
		category: 'woocommerce',
		icon: 'clipboard',
		supports: { html: false },
		edit: function () {
			return el(
				'div',
				blockEditor.useBlockProps( { className: 'wootoolkit-quote-placeholder' } ),
				el( 'p', null, __( 'Quote list and request form. Shoppers see the products they added to their quote here, with the form to send the request.', 'wootoolkit' ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.blockEditor );
